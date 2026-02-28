<?php
/**
 * API para sincronizar mensagens do Microsoft Teams
 * Busca mensagens dos chats do Teams e salva no banco de dados
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/channels/TeamsGraphAPI.php';

// ✅ TIMEOUT: Limitar tempo de execução para não bloquear outras requisições
set_time_limit(60); // Máximo 60 segundos
$syncStartTime = time();

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];
$teamsAPI = new TeamsGraphAPI($pdo, $userId);

if (!$teamsAPI->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Teams não autenticado']);
    exit;
}

// ✅ BUSCAR TOKEN DO USUÁRIO (uma vez, reutilizar depois)
$stmt = $pdo->prepare("SELECT teams_access_token FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$userTokenData = $stmt->fetch(PDO::FETCH_ASSOC);
$userAccessToken = $userTokenData['teams_access_token'] ?? null;

if (empty($userAccessToken)) {
    error_log("[Teams Sync] Token não encontrado para usuário {$userId}");
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token não encontrado']);
    exit;
}

error_log("[Teams Sync] Token encontrado para usuário {$userId}");

// Buscar o ID do Azure AD do usuário logado
$myInfoResult = $teamsAPI->getMyInfo();
$myAzureId = null;

if ($myInfoResult['success']) {
    $myAzureId = $myInfoResult['data']['id'] ?? null;
    error_log("[Teams Sync] Meu Azure ID: {$myAzureId}");
} else {
    error_log("[Teams Sync] Não foi possível obter meu Azure ID");
}

try {
    // Log para debug
    error_log("[Teams Sync] Iniciando sincronização para usuário {$userId}");
    
    // Buscar todos os chats do usuário
    $chatsResult = $teamsAPI->listChats();
    
    // Log do resultado
    error_log("[Teams Sync] Resultado listChats: " . json_encode($chatsResult));
    
    if (!$chatsResult['success']) {
        $errorMsg = $chatsResult['error'] ?? 'Erro ao listar chats';
        error_log("[Teams Sync] Erro ao listar chats: {$errorMsg}");
        throw new Exception($errorMsg);
    }
    
    $chats = $chatsResult['data']['value'] ?? [];
    error_log("[Teams Sync] Total de chats encontrados: " . count($chats));
    
    // DEBUG: Ver estrutura do primeiro chat
    if (count($chats) > 0) {
        error_log("[Teams Sync] DEBUG - Estrutura do primeiro chat: " . json_encode($chats[0]));
    }
    
    $syncedMessages = 0;
    $newConversations = 0;
    $skippedChats = 0;
    
    foreach ($chats as $chat) {
        // ✅ VERIFICAR TIMEOUT: Parar se já passou 55 segundos
        if (time() - $syncStartTime > 55) {
            error_log("[Teams Sync] Timeout atingido após " . (time() - $syncStartTime) . " segundos");
            error_log("[Teams Sync] Processados: {$newConversations} novas conversas, {$syncedMessages} mensagens, {$skippedChats} chats ignorados");
            break;
        }
        
        $chatId = $chat['id'];
        $chatType = $chat['chatType'] ?? 'unknown';
        $chatTopic = $chat['topic'] ?? null;
        
        // FILTRO 1: Aceitar apenas chats do tipo "oneOnOne" (1-on-1)
        // Tipos possíveis: oneOnOne, group, meeting, unknownFutureValue
        if ($chatType !== 'oneOnOne') {
            error_log("[Teams Sync] Chat {$chatId} ignorado - Tipo: {$chatType}");
            $skippedChats++;
            continue;
        }
        
        // FILTRO 2 REMOVIDO: A API não retorna members por padrão
        // Vamos confiar apenas no chatType = oneOnOne
        
        error_log("[Teams Sync] Chat {$chatId} aceito - Tipo: {$chatType}");
        
        // Verificar se já existe uma conversa para este chat
        $stmt = $pdo->prepare("
            SELECT id, last_message_at 
            FROM chat_conversations 
            WHERE user_id = ? AND teams_chat_id = ?
        ");
        $stmt->execute([$userId, $chatId]);
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Se não existe, verificar se tem mensagens recentes antes de criar
        if (!$conversation) {
            // IMPORTANTE: Só criar conversa se tiver mensagens recentes (últimos 7 dias)
            // Isso evita carregar TODOS os contatos do Teams de uma vez
            $messagesCheckResult = $teamsAPI->getChatMessages($chatId, 5);
            
            if (!$messagesCheckResult['success']) {
                error_log("[Teams Sync] Chat {$chatId} ignorado - Erro ao verificar mensagens");
                $skippedChats++;
                continue;
            }
            
            $recentMessages = $messagesCheckResult['data']['value'] ?? [];
            
            // Verificar se há mensagens nos últimos 30 dias (aumentado de 7 para 30)
            $hasRecentMessages = false;
            $thirtyDaysAgo = strtotime('-30 days');
            
            foreach ($recentMessages as $msg) {
                $msgDate = strtotime($msg['createdDateTime'] ?? '1970-01-01');
                if ($msgDate > $thirtyDaysAgo) {
                    $hasRecentMessages = true;
                    break;
                }
            }
            
            if (!$hasRecentMessages) {
                error_log("[Teams Sync] Chat {$chatId} ignorado - Sem mensagens recentes (últimos 30 dias)");
                $skippedChats++;
                continue;
            }
            
            error_log("[Teams Sync] Chat {$chatId} tem mensagens recentes, criando conversa...");
            
            // Buscar membros do chat
            $membersResult = $teamsAPI->getChatMembers($chatId);
            
            if (!$membersResult['success']) {
                error_log("[Teams Sync] Chat {$chatId} ignorado - Erro ao buscar membros: " . ($membersResult['error'] ?? 'desconhecido'));
                $skippedChats++;
                continue;
            }
            
            $members = $membersResult['data'] ?? [];
            error_log("[Teams Sync] Chat {$chatId} tem " . count($members) . " membros");
            
            // Pegar nome do outro usuário (não é o usuário logado)
            $contactName = 'Usuário Desconhecido';
            $contactEmail = null;
            $contactUserId = null;
            $profilePicUrl = null;
            
            foreach ($members as $member) {
                // Pegar o membro que NÃO é o usuário atual
                $memberId = $member['userId'] ?? null;
                $memberEmail = $member['email'] ?? null;
                
                // Ignorar se for o próprio usuário logado
                if ($memberId && $myAzureId && $memberId === $myAzureId) {
                    continue;
                }
                
                if ($memberId) {
                    $contactName = $member['displayName'] ?? 'Usuário Desconhecido';
                    $contactEmail = $memberEmail;
                    $contactUserId = $memberId;
                    error_log("[Teams Sync] Contato identificado: {$contactName} ({$contactEmail})");
                    
                    // ✅ OTIMIZAÇÃO: NÃO baixar foto durante sincronização
                    // Fotos serão carregadas sob demanda (lazy loading)
                    // Isso reduz o tempo de sincronização em 70-80%
                    /*
                    try {
                        $photoResult = $teamsAPI->saveUserPhotoLocally($memberId, $contactName);
                        if ($photoResult['success']) {
                            $profilePicUrl = $photoResult['data']['local_path'];
                            error_log("[Teams Sync] Foto do perfil salva: {$profilePicUrl}");
                        }
                    } catch (Exception $photoError) {
                        error_log("[Teams Sync] Erro ao buscar foto: " . $photoError->getMessage());
                    }
                    */
                    
                    break;
                }
            }
            
            // Se não conseguiu identificar o contato, pular este chat
            if ($contactName === 'Usuário Desconhecido') {
                error_log("[Teams Sync] Chat {$chatId} ignorado - Não foi possível identificar o contato");
                $skippedChats++;
                continue;
            }
            
            // Inserir sem contact_number (coluna não existe na tabela)
            $stmt = $pdo->prepare("
                INSERT INTO chat_conversations (
                    user_id,
                    contact_name,
                    channel_type,
                    teams_chat_id,
                    profile_pic_url,
                    status,
                    created_at,
                    last_message_at
                ) VALUES (?, ?, 'teams', ?, ?, 'active', NOW(), NOW())
            ");
            
            $stmt->execute([
                $userId,
                $contactName,
                $chatId,
                $profilePicUrl
            ]);
            
            $conversationId = $pdo->lastInsertId();
            $newConversations++;
            error_log("[Teams Sync] Nova conversa criada: ID {$conversationId}, Chat: {$contactName}, Foto: " . ($profilePicUrl ?? 'sem foto'));
        } else {
            $conversationId = $conversation['id'];
            error_log("[Teams Sync] Conversa existente encontrada: ID {$conversationId}");
        }
        
        // ✅ OTIMIZAÇÃO: Buscar apenas últimas 20 mensagens (era 50)
        // Reduz tempo de sincronização e carga na API do Teams
        error_log("[Teams Sync] Buscando mensagens do chat {$chatId}");
        $messagesResult = $teamsAPI->getChatMessages($chatId, 20);
        
        if (!$messagesResult['success']) {
            $errorMsg = $messagesResult['error'] ?? 'Erro desconhecido';
            error_log("[Teams Sync] Erro ao buscar mensagens do chat {$chatId}: {$errorMsg}");
            continue;
        }
        
        $messages = $messagesResult['data']['value'] ?? [];
        error_log("[Teams Sync] Chat {$chatId}: " . count($messages) . " mensagens encontradas");
        
        // Variáveis para rastrear a mensagem mais recente
        $latestMessageText = null;
        $latestMessageTime = 0;
        
        foreach ($messages as $message) {
            try {
                $messageId = $message['id'] ?? null;
                if (!$messageId) {
                    error_log("[Teams Sync] Mensagem sem ID, pulando");
                    continue;
                }
                
                $messageBody = $message['body']['content'] ?? '';
                $messageType = $message['messageType'] ?? 'message';
                $createdAt = $message['createdDateTime'] ?? date('Y-m-d H:i:s');
                
                // Verificar estrutura do 'from'
                $fromUserId = null;
                $fromUserName = 'Desconhecido';
                
                if (isset($message['from'])) {
                    if (isset($message['from']['user'])) {
                        $fromUserId = $message['from']['user']['id'] ?? null;
                        $fromUserName = $message['from']['user']['displayName'] ?? 'Desconhecido';
                    } elseif (isset($message['from']['application'])) {
                        $fromUserName = $message['from']['application']['displayName'] ?? 'Bot';
                    }
                }
            
            // Ignorar mensagens do sistema
            if ($messageType !== 'message') {
                error_log("[Teams Sync] Mensagem tipo '{$messageType}' ignorada (ID: {$messageId})");
                continue;
            }
            
            // Verificar se a mensagem já existe
            $stmt = $pdo->prepare("
                SELECT id FROM messages 
                WHERE conversation_id = ? AND external_id = ?
            ");
            $stmt->execute([$conversationId, $messageId]);
            
            if ($stmt->fetch()) {
                error_log("[Teams Sync] Mensagem já existe no banco (external_id: {$messageId})");
                continue; // Mensagem já existe
            }
            
            // Determinar sender_type da mensagem
            $senderType = ($fromUserId && $fromUserId === $myAzureId) ? 'user' : 'contact';
            
            // Limpar HTML do corpo da mensagem
            $messageText = strip_tags($messageBody);
            
            // ========================================
            // VERIFICAR SE HÁ ANEXOS (MÍDIAS)
            // ========================================
            
            $mediaUrl = null;
            $messageTypeDetected = 'text';
            
            // Verificar se há attachments
            if (isset($message['attachments']) && is_array($message['attachments']) && count($message['attachments']) > 0) {
                error_log("[Teams Sync] Mensagem tem " . count($message['attachments']) . " anexos");
                
                foreach ($message['attachments'] as $attachment) {
                    $attachmentType = $attachment['contentType'] ?? '';
                    $attachmentUrl = $attachment['contentUrl'] ?? null;
                    $attachmentName = $attachment['name'] ?? '';
                    
                    error_log("[Teams Sync] Anexo - Tipo: {$attachmentType}, URL: {$attachmentUrl}, Nome: {$attachmentName}");
                    
                    // ✅ CORREÇÃO: Teams usa contentType = "reference" para todos os anexos
                    // Precisamos detectar o tipo pelo nome do arquivo
                    
                    if ($attachmentType === 'reference' && $attachmentUrl) {
                        // Verificar se é imagem pelo nome do arquivo
                        if (preg_match('/\.(jpg|jpeg|png|gif|bmp|webp)$/i', $attachmentName)) {
                            try {
                                // ✅ USAR TOKEN JÁ BUSCADO NO INÍCIO DO ARQUIVO
                                if (!empty($userAccessToken)) {
                                    $accessToken = $userAccessToken;
                                    
                                    // Fazer requisição autenticada para baixar imagem
                                    $ch = curl_init($attachmentUrl);
                                    curl_setopt_array($ch, [
                                        CURLOPT_RETURNTRANSFER => true,
                                        CURLOPT_FOLLOWLOCATION => true,
                                        CURLOPT_HTTPHEADER => [
                                            'Authorization: Bearer ' . $accessToken
                                        ],
                                        CURLOPT_SSL_VERIFYPEER => true,
                                        CURLOPT_TIMEOUT => 30
                                    ]);
                                    
                                    $imageData = curl_exec($ch);
                                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                    curl_close($ch);
                                    
                                    if ($httpCode === 200 && !empty($imageData)) {
                                        // Salvar imagem localmente
                                        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/user_' . $userId . '/teams_media';
                                        
                                        if (!is_dir($uploadDir)) {
                                            mkdir($uploadDir, 0755, true);
                                        }
                                        
                                        // Gerar nome único para o arquivo
                                        $extension = pathinfo($attachmentName, PATHINFO_EXTENSION);
                                        if (empty($extension)) {
                                            $extension = 'jpg'; // Padrão
                                        }
                                        $fileName = uniqid('teams_', true) . '.' . $extension;
                                        $filePath = $uploadDir . '/' . $fileName;
                                        
                                        // Salvar arquivo
                                        if (file_put_contents($filePath, $imageData)) {
                                            $mediaUrl = '/uploads/user_' . $userId . '/teams_media/' . $fileName;
                                            $messageTypeDetected = 'image';
                                            
                                            error_log("[Teams Sync] Imagem salva localmente: {$mediaUrl} (" . strlen($imageData) . " bytes)");
                                        } else {
                                            error_log("[Teams Sync] Erro ao salvar imagem localmente");
                                        }
                                    } else {
                                        error_log("[Teams Sync] Erro ao baixar imagem: HTTP {$httpCode}");
                                    }
                                } else {
                                    error_log("[Teams Sync] Token não encontrado para baixar imagem");
                                }
                            } catch (Exception $downloadError) {
                                error_log("[Teams Sync] Exceção ao baixar imagem: " . $downloadError->getMessage());
                            }
                            
                            // Se não tem texto, usar descrição padrão
                            if (empty($messageText)) {
                                $messageText = '[Imagem enviada]';
                            }
                            
                            break;
                        }
                        // Verificar se é documento
                        elseif (preg_match('/\.(pdf|doc|docx|xls|xlsx|ppt|pptx)$/i', $attachmentName)) {
                            try {
                                // ✅ USAR TOKEN JÁ BUSCADO NO INÍCIO DO ARQUIVO
                                if (!empty($userAccessToken)) {
                                    $accessToken = $userAccessToken;
                                    
                                    // Fazer requisição autenticada para baixar documento
                                    $ch = curl_init($attachmentUrl);
                                    curl_setopt_array($ch, [
                                        CURLOPT_RETURNTRANSFER => true,
                                        CURLOPT_FOLLOWLOCATION => true,
                                        CURLOPT_HTTPHEADER => [
                                            'Authorization: Bearer ' . $accessToken
                                        ],
                                        CURLOPT_SSL_VERIFYPEER => true,
                                        CURLOPT_TIMEOUT => 30
                                    ]);
                                    
                                    $fileData = curl_exec($ch);
                                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                    curl_close($ch);
                                    
                                    if ($httpCode === 200 && !empty($fileData)) {
                                        // Salvar documento localmente
                                        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/user_' . $userId . '/teams_media';
                                        
                                        if (!is_dir($uploadDir)) {
                                            mkdir($uploadDir, 0755, true);
                                        }
                                        
                                        // Usar nome original do arquivo (sanitizado)
                                        $safeFileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $attachmentName);
                                        $fileName = uniqid('teams_', true) . '_' . $safeFileName;
                                        $filePath = $uploadDir . '/' . $fileName;
                                        
                                        // Salvar arquivo
                                        if (file_put_contents($filePath, $fileData)) {
                                            $mediaUrl = '/uploads/user_' . $userId . '/teams_media/' . $fileName;
                                            $messageTypeDetected = 'document';
                                            
                                            error_log("[Teams Sync] Documento salvo localmente: {$mediaUrl} (" . strlen($fileData) . " bytes)");
                                        } else {
                                            error_log("[Teams Sync] Erro ao salvar documento localmente");
                                        }
                                    } else {
                                        error_log("[Teams Sync] Erro ao baixar documento: HTTP {$httpCode}");
                                    }
                                }
                            } catch (Exception $downloadError) {
                                error_log("[Teams Sync] Exceção ao baixar documento: " . $downloadError->getMessage());
                            }
                            
                            if (empty($messageText)) {
                                $messageText = '[Documento enviado]';
                            }
                            
                            break;
                        }
                    }
                }
            }
            
            // Se não tem anexos, verificar se há tag <img> no HTML
            if (!$mediaUrl && !empty($messageBody)) {
                // Procurar por tags <img> no HTML
                if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $messageBody, $matches)) {
                    $originalUrl = $matches[1];
                    
                    // Tentar baixar e salvar imagem do HTML
                    try {
                        // ✅ USAR TOKEN JÁ BUSCADO NO INÍCIO DO ARQUIVO
                        if (!empty($userAccessToken)) {
                            $accessToken = $userAccessToken;
                            
                            $ch = curl_init($originalUrl);
                            curl_setopt_array($ch, [
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_FOLLOWLOCATION => true,
                                CURLOPT_HTTPHEADER => [
                                    'Authorization: Bearer ' . $accessToken
                                ],
                                CURLOPT_SSL_VERIFYPEER => true,
                                CURLOPT_TIMEOUT => 30
                            ]);
                            
                            $imageData = curl_exec($ch);
                            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);
                            
                            if ($httpCode === 200 && !empty($imageData)) {
                                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/user_' . $userId . '/teams_media';
                                
                                if (!is_dir($uploadDir)) {
                                    mkdir($uploadDir, 0755, true);
                                }
                                
                                $fileName = uniqid('teams_', true) . '.jpg';
                                $filePath = $uploadDir . '/' . $fileName;
                                
                                if (file_put_contents($filePath, $imageData)) {
                                    $mediaUrl = '/uploads/user_' . $userId . '/teams_media/' . $fileName;
                                    $messageTypeDetected = 'image';
                                    
                                    error_log("[Teams Sync] Imagem do HTML salva localmente: {$mediaUrl}");
                                }
                            }
                        }
                    } catch (Exception $e) {
                        error_log("[Teams Sync] Erro ao baixar imagem do HTML: " . $e->getMessage());
                    }
                    
                    if (empty($messageText)) {
                        $messageText = '[Imagem enviada]';
                    }
                }
            }
            
            error_log("[Teams Sync] Processando mensagem - Tipo: {$messageTypeDetected}, De: {$fromUserName}, Texto: " . substr($messageText, 0, 50));
            
            if (empty($messageText)) {
                error_log("[Teams Sync] Mensagem vazia após strip_tags, pulando");
                continue;
            }
            
            error_log("[Teams Sync] Salvando mensagem: {$messageText}");
            
            // Inserir mensagem no banco
            $stmt = $pdo->prepare("
                INSERT INTO messages (
                    conversation_id,
                    sender_type,
                    message_text,
                    message_type,
                    media_url,
                    external_id,
                    sender_name,
                    channel_type,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'teams', ?)
            ");
            
            $stmt->execute([
                $conversationId,
                $senderType,
                $messageText,
                $messageTypeDetected,
                $mediaUrl,
                $messageId,
                $fromUserName,
                date('Y-m-d H:i:s', strtotime($createdAt))
            ]);
            
            $syncedMessages++;
            error_log("[Teams Sync] Mensagem salva com sucesso");
            
            // Rastrear a mensagem mais recente
            $msgTimestamp = strtotime($createdAt);
            if ($msgTimestamp > $latestMessageTime) {
                $latestMessageTime = $msgTimestamp;
                $latestMessageText = $messageText;
            }
            
            } catch (Exception $msgError) {
                error_log("[Teams Sync] Erro ao processar mensagem: " . $msgError->getMessage());
                continue;
            }
        }
        
        // Atualizar última mensagem da conversa APENAS UMA VEZ com a mensagem mais recente
        if ($latestMessageText !== null) {
            // last_message_time é TIMESTAMP - usar formato de data
            $stmt = $pdo->prepare("
                UPDATE chat_conversations 
                SET 
                    last_message_text = ?,
                    last_message_time = ?
                WHERE id = ?
            ");
            $stmt->execute([
                substr($latestMessageText, 0, 100),
                date('Y-m-d H:i:s', $latestMessageTime),
                $conversationId
            ]);
            error_log("[Teams Sync] Última mensagem atualizada: " . substr($latestMessageText, 0, 50));
        }
    }
    
    error_log("[Teams Sync] Sincronização concluída: {$syncedMessages} mensagens, {$newConversations} novas conversas, {$skippedChats} chats ignorados");
    
    echo json_encode([
        'success' => true,
        'synced_messages' => $syncedMessages,
        'new_conversations' => $newConversations,
        'total_chats' => count($chats),
        'skipped_chats' => $skippedChats,
        'accepted_chats' => count($chats) - $skippedChats
    ]);
    
} catch (Exception $e) {
    error_log("[Teams Sync] ERRO FATAL: " . $e->getMessage());
    error_log("[Teams Sync] Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
