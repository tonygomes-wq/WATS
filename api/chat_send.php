<?php
/**
 * API PARA ENVIAR MENSAGENS NO CHAT
 * Suporta Evolution API e Meta API (híbrido)
 * Detecta automaticamente qual API usar baseado na configuração do usuário
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/whatsapp_meta_service.php';
require_once '../includes/Meta24HourWindow.php';

// ✅ NOVOS: Componentes de segurança
require_once '../includes/RateLimiter.php';
require_once '../includes/InputValidator.php';
require_once '../includes/Logger.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    handleSendMessage($userId);
} catch (Exception $e) {
    // Log de erro crítico
    Logger::error('Exceção ao enviar mensagem', [
        'user_id' => $userId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

exit;

/**
 * Enviar mensagem
 */
function handleSendMessage(int $userId): void
{
    global $pdo;
    
    // ✅ RATE LIMITING - Prevenir spam e abuso
    $rateLimiter = new RateLimiter();
    
    // Limite 1: 60 mensagens por minuto por usuário
    if (!$rateLimiter->allow($userId, 'send_message', 60, 60)) {
        Logger::warning('Rate limit excedido - usuário', [
            'user_id' => $userId,
            'action' => 'send_message'
        ]);
        
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Limite de mensagens excedido. Aguarde 1 minuto.',
            'retry_after' => 60
        ]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $userType = $_SESSION['user_type'] ?? 'user';
    
    // ✅ VALIDAÇÃO DE INPUT - Prevenir SQL injection e XSS
    
    // Validar conversation_id
    $convValidation = InputValidator::validateId($input['conversation_id'] ?? 0, 'ID da conversa');
    if (!$convValidation['valid']) {
        Logger::warning('Validação falhou - conversation_id', [
            'user_id' => $userId,
            'errors' => $convValidation['errors']
        ]);
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => implode(', ', $convValidation['errors'])
        ]);
        return;
    }
    $conversationId = $convValidation['sanitized'];
    
    // Validar mensagem
    $msgValidation = InputValidator::validateMessage($input['message'] ?? '');
    if (!$msgValidation['valid']) {
        Logger::warning('Validação falhou - mensagem', [
            'user_id' => $userId,
            'conversation_id' => $conversationId,
            'errors' => $msgValidation['errors']
        ]);
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => implode(', ', $msgValidation['errors'])
        ]);
        return;
    }
    $messageText = $msgValidation['sanitized'];
    
    // Limite 2: 10 mensagens por minuto por conversa (anti-spam)
    if (!$rateLimiter->allow("conv_{$conversationId}", 'send_message', 10, 60)) {
        Logger::warning('Rate limit excedido - conversa', [
            'user_id' => $userId,
            'conversation_id' => $conversationId
        ]);
        
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Muitas mensagens para este contato. Aguarde 1 minuto.',
            'retry_after' => 60
        ]);
        return;
    }
    
    // Validar quoted_message_id (opcional)
    $quotedMessageId = null;
    if (isset($input['quoted_message_id']) && !empty($input['quoted_message_id'])) {
        $quotedValidation = InputValidator::validateId($input['quoted_message_id'], 'ID da mensagem citada');
        if ($quotedValidation['valid']) {
            $quotedMessageId = $quotedValidation['sanitized'];
        }
    }
    
    // Log de tentativa de envio
    Logger::info('Tentativa de envio de mensagem', [
        'user_id' => $userId,
        'conversation_id' => $conversationId,
        'message_length' => strlen($messageText)
    ]);
    
    // Verificar se é atendente e buscar supervisor
    $ownerUserId = $userId;
    $instance = null;
    $token = null;
    
    if ($userType === 'attendant') {
        // Atendente: buscar dados do supervisor
        $stmt = $pdo->prepare("SELECT supervisor_id FROM supervisor_users WHERE id = ?");
        $stmt->execute([$userId]);
        $attendant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($attendant && $attendant['supervisor_id']) {
            $ownerUserId = $attendant['supervisor_id'];
        }
    }
    
    // Buscar conversa (sem filtrar por user_id para permitir atendentes)
    $stmt = $pdo->prepare("
        SELECT phone, contact_name, user_id, channel_type 
        FROM chat_conversations 
        WHERE id = ?
    ");
    $stmt->execute([$conversationId]);
    $conversation = $stmt->fetch();
    
    if (!$conversation) {
        Logger::warning('Conversa não encontrada', [
            'user_id' => $userId,
            'conversation_id' => $conversationId
        ]);
        
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Conversa não encontrada']);
        return;
    }
    
    // Buscar configurações da instância do dono da conversa ou supervisor
    $instanceUserId = ($userType === 'attendant') ? $ownerUserId : $conversation['user_id'];
    
    $stmt = $pdo->prepare("
        SELECT 
            evolution_instance, evolution_token, whatsapp_provider,
            meta_phone_number_id, meta_business_account_id, meta_app_id,
            meta_app_secret, meta_permanent_token, meta_api_version
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$instanceUserId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        Logger::error('Usuário não encontrado para envio', [
            'user_id' => $userId,
            'instance_user_id' => $instanceUserId,
            'conversation_id' => $conversationId
        ]);
        
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'Usuário não encontrado.'
        ]);
        return;
    }
    
    // Verificar se tem alguma API configurada
    $provider = $user['whatsapp_provider'] ?? 'evolution';
    $channelType = $conversation['channel_type'] ?? 'whatsapp';
    $hasEvolution = !empty($user['evolution_instance']) && !empty($user['evolution_token']);
    $hasMeta = !empty($user['meta_phone_number_id']) && !empty($user['meta_permanent_token']);
    
    // Verificar se tem alguma API configurada (apenas para WhatsApp)
    if ($channelType !== 'teams' && !$hasEvolution && !$hasMeta) {
        Logger::error('Nenhuma API configurada', [
            'user_id' => $userId,
            'instance_user_id' => $instanceUserId,
            'channel_type' => $channelType
        ]);
        
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'Nenhuma API configurada. Configure Evolution API ou Meta API em "Minha Instância".'
        ]);
        return;
    }
    
    // Log de início de envio
    Logger::info('Iniciando envio de mensagem', [
        'user_id' => $userId,
        'conversation_id' => $conversationId,
        'provider' => $provider,
        'channel_type' => $channelType
    ]);
    
    // Enviar mensagem via API apropriada
    $result = ['success' => false, 'error' => 'Provedor não configurado'];
    $messageId = null;
    $timestamp = time();
    
    if ($channelType === 'teams') {
        // ========== MICROSOFT TEAMS ==========
        error_log("[CHAT_SEND_TEAMS] Iniciando envio via Microsoft Teams Graph API");
        error_log("[CHAT_SEND_TEAMS] Conversa ID: {$conversationId} | User ID: {$instanceUserId}");
        
        // Buscar teams_chat_id da conversa
        $stmt = $pdo->prepare("SELECT teams_chat_id, contact_name FROM chat_conversations WHERE id = ?");
        $stmt->execute([$conversationId]);
        $conv = $stmt->fetch();
        
        if (!$conv) {
            error_log("[CHAT_SEND_TEAMS] ERRO: Conversa não encontrada no banco");
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Conversa não encontrada'
            ]);
            return;
        }
        
        if (empty($conv['teams_chat_id'])) {
            error_log("[CHAT_SEND_TEAMS] ERRO: teams_chat_id vazio para conversa {$conversationId}");
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Chat ID do Teams não encontrado. Sincronize as mensagens do Teams primeiro.'
            ]);
            return;
        }
        
        $teamsChatId = $conv['teams_chat_id'];
        $contactName = $conv['contact_name'] ?? 'Desconhecido';
        error_log("[CHAT_SEND_TEAMS] Teams Chat ID: {$teamsChatId} | Contato: {$contactName}");
        
        // Carregar classe TeamsGraphAPI
        require_once '../includes/channels/TeamsGraphAPI.php';
        
        try {
            $teamsAPI = new TeamsGraphAPI($pdo, $instanceUserId);
            
            // Verificar autenticação
            if (!$teamsAPI->isAuthenticated()) {
                error_log("[CHAT_SEND_TEAMS] ERRO: Usuário {$instanceUserId} não está autenticado no Teams");
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Teams não autenticado. Configure e conecte sua conta do Teams em "Configurar Microsoft Teams".'
                ]);
                return;
            }
            
            error_log("[CHAT_SEND_TEAMS] Usuário autenticado. Enviando mensagem...");
            
            // Enviar mensagem
            $result = $teamsAPI->sendChatMessage($teamsChatId, $messageText);
            
            if (!$result['success']) {
                $errorMsg = $result['error'] ?? 'Erro desconhecido';
                $errorCode = $result['code'] ?? 'N/A';
                error_log("[CHAT_SEND_TEAMS] ERRO ao enviar: {$errorMsg} (Código: {$errorCode})");
                
                // Mensagens de erro mais amigáveis
                $friendlyError = $errorMsg;
                if (strpos($errorMsg, 'token') !== false || strpos($errorMsg, 'Unauthorized') !== false) {
                    $friendlyError = 'Sessão do Teams expirou. Reconecte sua conta em "Configurar Microsoft Teams".';
                } elseif (strpos($errorMsg, 'not found') !== false || strpos($errorMsg, 'NotFound') !== false) {
                    $friendlyError = 'Chat não encontrado no Teams. O chat pode ter sido excluído.';
                } elseif (strpos($errorMsg, 'Forbidden') !== false) {
                    $friendlyError = 'Sem permissão para enviar mensagens neste chat. Verifique as permissões no Azure AD.';
                }
                
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => $friendlyError,
                    'technical_error' => $errorMsg
                ]);
                return;
            }
            
            // Extrair message_id da resposta
            $messageId = $result['data']['id'] ?? uniqid('teams_');
            $timestamp = isset($result['data']['createdDateTime']) ? strtotime($result['data']['createdDateTime']) : time();
            
            error_log("[CHAT_SEND_TEAMS] ✅ Mensagem enviada com sucesso! Message ID: {$messageId}");
            
            // ✅ SINCRONIZAR IMEDIATAMENTE após enviar (buscar resposta do contato)
            error_log("[CHAT_SEND_TEAMS] Aguardando 2 segundos para sincronizar resposta...");
            sleep(2); // Aguardar 2 segundos para dar tempo do contato responder
            
            try {
                // Buscar mensagens do chat
                $messagesResult = $teamsAPI->getChatMessages($teamsChatId, 10);
                
                if ($messagesResult['success']) {
                    $messages = $messagesResult['data']['value'] ?? [];
                    error_log("[CHAT_SEND_TEAMS] Encontradas " . count($messages) . " mensagens no chat");
                    
                    // Buscar meu Azure ID para comparar
                    $myInfo = $teamsAPI->getMyInfo();
                    $myAzureId = $myInfo['success'] ? ($myInfo['data']['id'] ?? null) : null;
                    
                    // Salvar mensagens no banco (se houver novas)
                    foreach ($messages as $msg) {
                        $msgId = $msg['id'] ?? null;
                        if (!$msgId) continue;
                        
                        // Verificar se mensagem já existe
                        $stmt = $pdo->prepare("SELECT id FROM messages WHERE conversation_id = ? AND external_id = ?");
                        $stmt->execute([$conversationId, $msgId]);
                        if ($stmt->fetch()) continue; // Já existe
                        
                        // Extrair dados da mensagem
                        $msgBody = strip_tags($msg['body']['content'] ?? '');
                        $msgType = $msg['messageType'] ?? 'message';
                        $msgCreatedAt = $msg['createdDateTime'] ?? date('Y-m-d H:i:s');
                        
                        if ($msgType !== 'message' || empty($msgBody)) continue;
                        
                        // Determinar se é do usuário ou do contato
                        $fromUserId = null;
                        $fromUserName = 'Desconhecido';
                        if (isset($msg['from']['user'])) {
                            $fromUserId = $msg['from']['user']['id'] ?? null;
                            $fromUserName = $msg['from']['user']['displayName'] ?? 'Desconhecido';
                        }
                        
                        $senderType = ($fromUserId && $myAzureId && $fromUserId === $myAzureId) ? 'user' : 'contact';
                        
                        // Inserir mensagem
                        $stmt = $pdo->prepare("
                            INSERT INTO messages (
                                conversation_id, sender_type, message_text, external_id,
                                sender_name, channel_type, created_at
                            ) VALUES (?, ?, ?, ?, ?, 'teams', ?)
                        ");
                        
                        $stmt->execute([
                            $conversationId,
                            $senderType,
                            $msgBody,
                            $msgId,
                            $fromUserName,
                            date('Y-m-d H:i:s', strtotime($msgCreatedAt))
                        ]);
                        
                        error_log("[CHAT_SEND_TEAMS] ✅ Mensagem sincronizada: " . substr($msgBody, 0, 50) . " (sender_type: {$senderType})");
                    }
                    
                    // Atualizar última mensagem da conversa
                    if (count($messages) > 0) {
                        $lastMsg = $messages[0]; // Mensagens vêm ordenadas por data desc
                        $lastMsgText = strip_tags($lastMsg['body']['content'] ?? '');
                        $lastMsgTime = $lastMsg['createdDateTime'] ?? date('Y-m-d H:i:s');
                        
                        $stmt = $pdo->prepare("
                            UPDATE chat_conversations 
                            SET last_message_text = ?, last_message_time = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            substr($lastMsgText, 0, 100),
                            date('Y-m-d H:i:s', strtotime($lastMsgTime)),
                            $conversationId
                        ]);
                    }
                }
            } catch (Exception $syncError) {
                error_log("[CHAT_SEND_TEAMS] ⚠️ Erro na sincronização: " . $syncError->getMessage());
                // Não falhar o envio por causa da sincronização
            }
            
        } catch (Exception $e) {
            error_log("[CHAT_SEND_TEAMS] EXCEÇÃO: " . $e->getMessage());
            error_log("[CHAT_SEND_TEAMS] Stack trace: " . $e->getTraceAsString());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Erro ao enviar mensagem via Teams: ' . $e->getMessage()
            ]);
            return;
        }
        
    } else {
        // ========== WHATSAPP / EMAIL ==========
        $phone = $conversation['phone'];
        
        if (empty($phone)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Número de telefone não encontrado na conversa']);
            return;
        }
        
        if ($provider === 'meta' && $hasMeta) {
            // Enviar via Meta API
            error_log("[CHAT_SEND] Enviando via Meta API");
            $result = sendMessageViaMeta($phone, $messageText, $user, $instanceUserId, $conversationId);
            
            // Fallback para Evolution se Meta falhar e Evolution estiver configurado
            if (!$result['success'] && $hasEvolution) {
                error_log("[CHAT_SEND] Meta API falhou, tentando fallback para Evolution API");
                $result = sendMessageViaEvolution(
                    $phone, 
                    $messageText, 
                    $user['evolution_instance'], 
                    $user['evolution_token']
                );
                $result['fallback'] = true;
            }
        } else {
            // Enviar via Evolution API (padrão)
            if (!$hasEvolution) {
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'error' => 'Evolution API não configurada. Configure em "Minha Instância".'
                ]);
                return;
            }
            
            error_log("[CHAT_SEND] Enviando via Evolution API");
            $result = sendMessageViaEvolution(
                $phone, 
                $messageText, 
                $user['evolution_instance'], 
                $user['evolution_token']
            );
        }
        
        if (!$result['success']) {
            Logger::error('Falha ao enviar mensagem', [
                'user_id' => $userId,
                'conversation_id' => $conversationId,
                'provider' => $provider,
                'channel_type' => $channelType,
                'error' => $result['error'] ?? 'Erro desconhecido'
            ]);
            
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $result['error'] ?? 'Erro ao enviar mensagem'
            ]);
            return;
        }
        
        // Log de sucesso
        Logger::info('Mensagem enviada com sucesso', [
            'user_id' => $userId,
            'conversation_id' => $conversationId,
            'message_id' => $result['message_id'] ?? null,
            'provider' => $provider,
            'channel_type' => $channelType
        ]);
        
        // Registrar mensagem no banco
        $messageId = $result['message_id'] ?? null;
        $timestamp = $result['timestamp'] ?? time();
    }
    
    // Salvar mensagem enviada no banco
    if ($channelType === 'teams') {
        // Para Teams, verificar se mensagem já foi salva na sincronização
        $stmt = $pdo->prepare("SELECT id FROM messages WHERE conversation_id = ? AND external_id = ?");
        $stmt->execute([$conversationId, $messageId]);
        $existingMsg = $stmt->fetch();
        
        if ($existingMsg) {
            // Mensagem já foi salva na sincronização
            $insertedId = $existingMsg['id'];
            error_log("[CHAT_SEND_TEAMS] Mensagem já existe no banco (ID: {$insertedId})");
        } else {
            // Salvar mensagem enviada
            $stmt = $pdo->prepare("
                INSERT INTO messages (
                    conversation_id, sender_type, message_text, external_id,
                    sender_name, channel_type, created_at
                ) VALUES (?, 'user', ?, ?, 'Suporte - Macip', 'teams', NOW())
            ");
            
            $stmt->execute([
                $conversationId,
                $messageText,
                $messageId
            ]);
            
            $insertedId = $pdo->lastInsertId();
            error_log("[CHAT_SEND_TEAMS] Mensagem salva no banco (ID: {$insertedId})");
        }
    } else {
        // Para WhatsApp, salvar na tabela chat_messages (mesma tabela que o webhook usa)
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (
                conversation_id, user_id, message_id, from_me, message_type,
                message_text, status, timestamp
            ) VALUES (?, ?, ?, 1, 'text', ?, 'sent', ?)
        ");
        
        $stmt->execute([
            $conversationId,
            $userId,
            $messageId,
            $messageText,
            $timestamp
        ]);
        
        $insertedId = $pdo->lastInsertId();
        error_log("[CHAT_SEND] Mensagem WhatsApp salva em chat_messages (ID: {$insertedId})");
    }
    
    // Atualizar conversa
    $stmt = $pdo->prepare("
        UPDATE chat_conversations 
        SET 
            last_message = ?,
            last_message_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$messageText, $conversationId]);
    
    // Buscar mensagem inserida para retornar
    if ($insertedId) {
        if ($channelType === 'teams') {
            // Teams: buscar da tabela messages
            $stmt = $pdo->prepare("
                SELECT 
                    m.id, m.external_id, m.sender_type, 
                    m.message_text, m.created_at, m.sender_name
                FROM messages m
                WHERE m.id = ?
            ");
            $stmt->execute([$insertedId]);
            $message = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($message) {
                $message['id'] = (int) $message['id'];
                $message['from_me'] = ($message['sender_type'] === 'user');
                $message['time_formatted'] = date('H:i', strtotime($message['created_at']));
                $message['created_at_formatted'] = formatMessageDateTime($message['created_at']);
                $message['timestamp'] = strtotime($message['created_at']);
            }
        } else {
            // WhatsApp: buscar da tabela chat_messages
            $stmt = $pdo->prepare("
                SELECT 
                    m.id, m.message_id as external_id, m.from_me,
                    m.message_text, m.timestamp, m.status
                FROM chat_messages m
                WHERE m.id = ?
            ");
            $stmt->execute([$insertedId]);
            $message = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($message) {
                $message['id'] = (int) $message['id'];
                $message['from_me'] = (bool) $message['from_me'];
                $message['time_formatted'] = date('H:i', $message['timestamp']);
                $message['created_at_formatted'] = formatMessageDateTime(date('Y-m-d H:i:s', $message['timestamp']));
                $message['timestamp'] = (int) $message['timestamp'];
            }
        }
        
        // Fallback se não conseguiu buscar
        if (!$message) {
            $message = [
                'id' => $insertedId,
                'external_id' => $messageId,
                'from_me' => true,
                'message_text' => $messageText,
                'time_formatted' => date('H:i'),
                'created_at_formatted' => 'Agora',
                'timestamp' => time()
            ];
        }
    } else {
        // Fallback se não conseguiu inserir
        $message = [
            'id' => 0,
            'external_id' => $messageId,
            'from_me' => true,
            'message_text' => $messageText,
            'time_formatted' => date('H:i'),
            'created_at_formatted' => 'Agora',
            'timestamp' => time()
        ];
    }
    
    // Garantir que todos os campos necessários existem
    if (!isset($message['timestamp'])) {
        $message['timestamp'] = isset($message['created_at']) ? strtotime($message['created_at']) : time();
    }
    if (!isset($message['time_formatted'])) {
        $message['time_formatted'] = date('H:i', $message['timestamp']);
    }
    if (!isset($message['created_at_formatted'])) {
        $message['created_at_formatted'] = isset($message['created_at']) 
            ? formatMessageDateTime($message['created_at']) 
            : 'Agora';
    }
    if (!isset($message['sender_name'])) {
        $message['sender_name'] = null;
    }
    
    $response = [
        'success' => true,
        'message' => $message
    ];
    
    // Adicionar informação de fallback se aplicável
    if (isset($result['fallback']) && $result['fallback']) {
        $response['warning'] = 'Enviado via Evolution API (fallback)';
    }
    
    echo json_encode($response);
}

/**
 * Enviar mensagem via Evolution API
 */
function sendMessageViaEvolution(string $phone, string $message, string $instance, string $token): array
{
    // Formatar número para padrão internacional
    $phoneFormatted = preg_replace('/[^0-9]/', '', $phone);
    
    // Se não começar com código do país, adicionar 55 (Brasil)
    if (strlen($phoneFormatted) < 12) {
        $phoneFormatted = '55' . $phoneFormatted;
    }
    
    // Usar o token do usuário ou a API key global
    $apiKey = !empty($token) ? $token : EVOLUTION_API_KEY;
    
    // Evolution API aceita número sem sufixo (apenas dígitos)
    $data = [
        'number' => $phoneFormatted,
        'text' => $message
    ];
    
    // Log para debug
    error_log("[CHAT_SEND] Enviando para: $phoneFormatted | Instância: $instance | API Key: " . substr($apiKey, 0, 10) . "...");
    error_log("[CHAT_SEND] URL: " . EVOLUTION_API_URL . '/message/sendText/' . $instance);
    error_log("[CHAT_SEND] Payload: " . json_encode($data));
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, EVOLUTION_API_URL . '/message/sendText/' . $instance);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . $apiKey
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Log para debug
    error_log("[CHAT_SEND] HTTP Code: $httpCode | Response: $response");
    
    if ($httpCode >= 200 && $httpCode < 300) {
        $responseData = json_decode($response, true);
        
        return [
            'success' => true,
            'message_id' => $responseData['key']['id'] ?? null,
            'timestamp' => $responseData['messageTimestamp'] ?? time()
        ];
    }
    
    // Erro
    $errorMessage = 'Erro ao enviar mensagem';
    
    if (!empty($curlError)) {
        $errorMessage = "Erro de conexão: $curlError";
    } elseif (!empty($response)) {
        $errorData = json_decode($response, true);
        if (is_array($errorData)) {
            $errorMessage = $errorData['message'] ?? $errorData['error'] ?? $errorData['response']['message'] ?? $errorMessage;
        } else {
            $errorMessage = "Erro HTTP $httpCode: $response";
        }
    } else {
        $errorMessage = "Erro HTTP $httpCode sem resposta";
    }
    
    error_log("[CHAT_SEND] ERRO: $errorMessage");
    
    return [
        'success' => false,
        'error' => $errorMessage,
        'http_code' => $httpCode
    ];
}

/**
 * Enviar mensagem via Meta API
 */
function sendMessageViaMeta(string $phone, string $message, array $user, int $userId, int $conversationId): array
{
    global $pdo;
    
    // Verificar janela de 24 horas
    $windowChecker = new Meta24HourWindow($pdo);
    $windowCheck = $windowChecker->checkWindow($userId, $conversationId);
    
    if (!$windowCheck['within_window']) {
        error_log("[CHAT_SEND_META] Fora da janela de 24h - não é possível enviar mensagem de texto");
        return [
            'success' => false,
            'error' => 'Fora da janela de 24 horas. Use um template aprovado ou aguarde resposta do cliente.',
            'window_expired' => true
        ];
    }
    
    // Configurar dados da Meta API
    $userConfig = [
        'meta_phone_number_id' => $user['meta_phone_number_id'],
        'meta_permanent_token' => $user['meta_permanent_token'],
        'meta_api_version' => $user['meta_api_version'] ?? 'v19.0'
    ];
    
    // Enviar mensagem de texto via Meta API
    $result = sendMetaTextMessage($phone, $message, $userConfig, $userId);
    
    if ($result['success']) {
        return [
            'success' => true,
            'message_id' => $result['message_id'] ?? null,
            'timestamp' => time()
        ];
    }
    
    return [
        'success' => false,
        'error' => $result['error'] ?? 'Erro ao enviar via Meta API'
    ];
}

/**
 * Formatar data/hora da mensagem
 */
function formatMessageDateTime(string $datetime): string
{
    $time = strtotime($datetime);
    $now = time();
    
    if (date('Y-m-d', $time) === date('Y-m-d', $now)) {
        return 'Hoje às ' . date('H:i', $time);
    }
    
    if (date('Y-m-d', $time) === date('Y-m-d', $now - 86400)) {
        return 'Ontem às ' . date('H:i', $time);
    }
    
    $diff = $now - $time;
    if ($diff < 604800) {
        $days = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
        return $days[date('w', $time)] . ' às ' . date('H:i', $time);
    }
    
    return date('d/m/Y H:i', $time);
}
