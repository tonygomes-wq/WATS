<?php
/**
 * API de Sincronização de Histórico do WhatsApp
 * 
 * Busca conversas e mensagens antigas da Evolution API
 * e as importa para o sistema.
 */

session_start();
require_once '../config/database.php';

header('Content-Type: application/json');
header('X-Accel-Buffering: no'); // Desabilitar buffering do Nginx

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['type' => 'error', 'message' => 'Não autenticado']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Ler configurações
$input = json_decode(file_get_contents('php://input'), true);
$syncMessages = $input['sync_messages'] ?? true;
$syncMedia = $input['sync_media'] ?? true;
$syncContacts = $input['sync_contacts'] ?? true;
$limit = min((int)($input['limit'] ?? 50), 500);
$messagesPerChat = min((int)($input['messages_per_chat'] ?? 200), 500); // Aumentado de 50 para 200

// Função para enviar log em tempo real
function sendLog($message, $level = 'info') {
    echo json_encode(['type' => 'log', 'message' => $message, 'level' => $level]) . "\n";
    flush();
    ob_flush();
}

// Função para enviar progresso
function sendProgress($percent) {
    echo json_encode(['type' => 'progress', 'percent' => $percent]) . "\n";
    flush();
    ob_flush();
}

// Função para enviar estatísticas
function sendStats($conversations, $messages, $media, $errors) {
    echo json_encode([
        'type' => 'stats',
        'conversations' => $conversations,
        'messages' => $messages,
        'media' => $media,
        'errors' => $errors
    ]) . "\n";
    flush();
    ob_flush();
}

try {
    // Buscar configuração do usuário
    $stmt = $pdo->prepare("
        SELECT 
            evolution_instance,
            evolution_token,
            evolution_api_url
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData || empty($userData['evolution_instance']) || empty($userData['evolution_token'])) {
        echo json_encode(['type' => 'error', 'message' => 'Configuração da Evolution API não encontrada']);
        exit;
    }
    
    // Verificar se URL está configurada
    if (empty($userData['evolution_api_url'])) {
        sendLog("URL da Evolution API não configurada", 'error');
        echo json_encode(['type' => 'error', 'message' => 'URL da Evolution API não configurada. Configure em /corrigir_url_evolution.php']);
        exit;
    }
    
    $instance = $userData['evolution_instance'];
    $token = $userData['evolution_token'];
    $evolutionUrl = rtrim($userData['evolution_api_url'], '/');
    
    sendLog("Configuração carregada: Instância $instance");
    
    // Contadores
    $stats = [
        'conversations' => 0,
        'messages' => 0,
        'media' => 0,
        'errors' => 0
    ];
    
    // ==========================================
    // ETAPA 1: BUSCAR CONVERSAS
    // ==========================================
    sendLog("Buscando conversas do WhatsApp...");
    sendProgress(10);
    
    // Endpoint para buscar conversas
    $url = "$evolutionUrl/chat/findChats/$instance";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['limit' => $limit]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'apikey: ' . $token
        ],
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        sendLog("Erro ao buscar conversas: HTTP $httpCode", 'error');
        echo json_encode(['type' => 'error', 'message' => 'Erro ao buscar conversas da Evolution API']);
        exit;
    }
    
    $chats = json_decode($response, true);
    
    if (!is_array($chats)) {
        sendLog("Resposta inválida da API", 'error');
        echo json_encode(['type' => 'error', 'message' => 'Resposta inválida da Evolution API']);
        exit;
    }
    
    sendLog("Encontradas " . count($chats) . " conversas");
    sendProgress(20);
    
    // ==========================================
    // ETAPA 2: PROCESSAR CADA CONVERSA
    // ==========================================
    $totalChats = count($chats);
    $processedChats = 0;
    
    foreach ($chats as $chat) {
        try {
            $remoteJid = $chat['id'] ?? null;
            
            if (!$remoteJid) {
                $stats['errors']++;
                continue;
            }
            
            // Filtrar grupos, broadcasts e newsletters
            if (strpos($remoteJid, '@g.us') !== false ||
                strpos($remoteJid, '@broadcast') !== false ||
                strpos($remoteJid, '@newsletter') !== false) {
                sendLog("Pulando grupo/broadcast: $remoteJid");
                continue;
            }
            
            // Limpar telefone
            $phone = preg_replace('/[^0-9]/', '', str_replace('@s.whatsapp.net', '', $remoteJid));
            
            // Validar telefone (10-15 dígitos)
            if (!preg_match('/^[0-9]{10,15}$/', $phone)) {
                sendLog("Telefone inválido: $phone");
                $stats['errors']++;
                continue;
            }
            
            // Nome do contato
            $contactName = $chat['name'] ?? $chat['pushName'] ?? null;
            
            sendLog("Processando conversa: $phone ($contactName)");
            
            // Verificar se conversa já existe
            $stmt = $pdo->prepare("
                SELECT id FROM chat_conversations 
                WHERE user_id = ? AND phone = ?
                LIMIT 1
            ");
            $stmt->execute([$user_id, $phone]);
            $existingConv = $stmt->fetch();
            
            if ($existingConv) {
                $conversationId = $existingConv['id'];
                sendLog("Conversa já existe (ID: $conversationId)");
            } else {
                // Criar conversa
                $stmt = $pdo->prepare("
                    INSERT INTO chat_conversations 
                    (user_id, phone, contact_name, created_at, updated_at)
                    VALUES (?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$user_id, $phone, $contactName]);
                $conversationId = $pdo->lastInsertId();
                $stats['conversations']++;
                sendLog("Nova conversa criada (ID: $conversationId)", 'success');
            }
            
            // ==========================================
            // ETAPA 3: BUSCAR MENSAGENS DA CONVERSA
            // ==========================================
            if ($syncMessages) {
                sendLog("Buscando mensagens de $phone...");
                
                $url = "$evolutionUrl/chat/findMessages/$instance";
                
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode([
                        'where' => [
                            'key' => [
                                'remoteJid' => $remoteJid
                            ]
                        ],
                        'limit' => $messagesPerChat // Usar limite configurável
                    ]),
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'apikey: ' . $token
                    ],
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_SSL_VERIFYPEER => false
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200 && $response) {
                    $messages = json_decode($response, true);
                    
                    if (is_array($messages)) {
                        sendLog("Encontradas " . count($messages) . " mensagens");
                        
                        foreach ($messages as $msg) {
                            try {
                                $key = $msg['key'] ?? [];
                                $message = $msg['message'] ?? [];
                                
                                $messageId = $key['id'] ?? null;
                                $fromMe = (bool)($key['fromMe'] ?? false);
                                $timestamp = $msg['messageTimestamp'] ?? time();
                                
                                // Verificar se mensagem já existe
                                if ($messageId) {
                                    $stmt = $pdo->prepare("SELECT id FROM chat_messages WHERE message_id = ? LIMIT 1");
                                    $stmt->execute([$messageId]);
                                    if ($stmt->fetch()) {
                                        continue; // Pular duplicadas
                                    }
                                }
                                
                                // Extrair texto
                                $messageText = null;
                                $messageType = 'text';
                                $mediaUrl = null;
                                $mediaMimetype = null;
                                $caption = null;
                                
                                if (isset($message['conversation'])) {
                                    $messageText = $message['conversation'];
                                } elseif (isset($message['extendedTextMessage']['text'])) {
                                    $messageText = $message['extendedTextMessage']['text'];
                                } elseif (isset($message['imageMessage'])) {
                                    $messageType = 'image';
                                    $caption = $message['imageMessage']['caption'] ?? null;
                                    $messageText = $caption ?: '[Imagem]';
                                    $mediaMimetype = $message['imageMessage']['mimetype'] ?? 'image/jpeg';
                                } elseif (isset($message['videoMessage'])) {
                                    $messageType = 'video';
                                    $caption = $message['videoMessage']['caption'] ?? null;
                                    $messageText = $caption ?: '[Vídeo]';
                                    $mediaMimetype = $message['videoMessage']['mimetype'] ?? 'video/mp4';
                                } elseif (isset($message['audioMessage'])) {
                                    $messageType = 'audio';
                                    $messageText = '[Áudio]';
                                    $mediaMimetype = $message['audioMessage']['mimetype'] ?? 'audio/ogg';
                                } elseif (isset($message['documentMessage'])) {
                                    $messageType = 'document';
                                    $messageText = '[Documento]';
                                    $mediaMimetype = $message['documentMessage']['mimetype'] ?? 'application/octet-stream';
                                }
                                
                                // Tentar baixar mídia se necessário
                                if ($syncMedia && $messageType !== 'text' && $messageId) {
                                    // Aqui você pode implementar o download da mídia
                                    // Por enquanto, vamos apenas registrar que existe mídia
                                    $stats['media']++;
                                }
                                
                                // Salvar mensagem
                                $stmt = $pdo->prepare("
                                    INSERT INTO chat_messages 
                                    (conversation_id, user_id, message_id, from_me, message_type, message_text, 
                                     media_url, media_mimetype, caption, status, timestamp, created_at)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?))
                                ");
                                
                                $stmt->execute([
                                    $conversationId,
                                    $user_id,
                                    $messageId,
                                    $fromMe ? 1 : 0,
                                    $messageType,
                                    $messageText,
                                    $mediaUrl,
                                    $mediaMimetype,
                                    $caption,
                                    'delivered',
                                    $timestamp,
                                    $timestamp
                                ]);
                                
                                $stats['messages']++;
                                
                            } catch (Exception $e) {
                                $stats['errors']++;
                                sendLog("Erro ao processar mensagem: " . $e->getMessage(), 'error');
                            }
                        }
                        
                        // Atualizar última mensagem da conversa
                        $stmt = $pdo->prepare("
                            UPDATE chat_conversations 
                            SET 
                                last_message_text = (
                                    SELECT message_text FROM chat_messages 
                                    WHERE conversation_id = ? 
                                    ORDER BY timestamp DESC LIMIT 1
                                ),
                                last_message_time = (
                                    SELECT FROM_UNIXTIME(timestamp) FROM chat_messages 
                                    WHERE conversation_id = ? 
                                    ORDER BY timestamp DESC LIMIT 1
                                ),
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$conversationId, $conversationId, $conversationId]);
                    }
                }
            }
            
            // Atualizar contato se necessário
            if ($syncContacts && $contactName) {
                $stmt = $pdo->prepare("
                    INSERT INTO contacts (user_id, phone, name, created_at, updated_at)
                    VALUES (?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE name = ?, updated_at = NOW()
                ");
                $stmt->execute([$user_id, $phone, $contactName, $contactName]);
            }
            
            $processedChats++;
            $progress = 20 + (($processedChats / $totalChats) * 70);
            sendProgress($progress);
            sendStats($stats['conversations'], $stats['messages'], $stats['media'], $stats['errors']);
            
        } catch (Exception $e) {
            $stats['errors']++;
            sendLog("Erro ao processar conversa: " . $e->getMessage(), 'error');
        }
    }
    
    // ==========================================
    // FINALIZAÇÃO
    // ==========================================
    sendProgress(100);
    sendStats($stats['conversations'], $stats['messages'], $stats['media'], $stats['errors']);
    sendLog("Sincronização concluída!", 'success');
    sendLog("Conversas: {$stats['conversations']}, Mensagens: {$stats['messages']}, Mídias: {$stats['media']}, Erros: {$stats['errors']}", 'success');
    
    echo json_encode(['type' => 'complete', 'stats' => $stats]) . "\n";
    
} catch (Exception $e) {
    sendLog("Erro fatal: " . $e->getMessage(), 'error');
    echo json_encode(['type' => 'error', 'message' => $e->getMessage()]) . "\n";
}
?>
