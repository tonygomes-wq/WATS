<?php
/**
 * API de Chat em Tempo Real - Busca Direta do WhatsApp
 * NÃO depende do armazenamento da Evolution API
 * Busca mensagens diretamente do WhatsApp e salva localmente
 */

require_once '../config/database.php';

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // Buscar dados do usuário
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('Usuário não encontrado');
    }
    
    $instance = $user['evolution_instance'] ?? 'CELULAR-TONY';
    $token = $user['evolution_token'] ?: EVOLUTION_API_KEY;
    $evolutionUrl = EVOLUTION_API_URL;
    
    // Buscar conversas ativas
    $stmt = $pdo->prepare("
        SELECT id, phone, contact_name 
        FROM chat_conversations 
        WHERE user_id = ? 
        ORDER BY last_message_time DESC 
        LIMIT 20
    ");
    $stmt->execute([$userId]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalNewMessages = 0;
    $conversationsUpdated = 0;
    $errors = [];
    
    foreach ($conversations as $conv) {
        $phone = $conv['phone'];
        $conversationId = $conv['id'];
        
        // Buscar mensagens via Evolution API
        $url = $evolutionUrl . '/chat/findMessages/' . $instance;
        
        $postData = [
            'where' => [
                'key' => [
                    'remoteJid' => $phone . '@s.whatsapp.net'
                ]
            ],
            'limit' => 50
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $token
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $errors[] = "Erro ao buscar mensagens de $phone: HTTP $httpCode - $curlError";
            continue;
        }
        
        $result = json_decode($response, true);
        
        // O formato correto é: {"messages": {"total": X, "records": [...]}}
        if (!isset($result['messages']['records']) || !is_array($result['messages']['records'])) {
            continue;
        }
        
        $messages = $result['messages']['records'];
        $newMessagesCount = 0;
        
        foreach ($messages as $msg) {
            $key = $msg['key'] ?? [];
            $message = $msg['message'] ?? [];
            
            $messageId = $key['id'] ?? null;
            if (!$messageId) continue;
            
            // Verificar se já existe
            $stmt = $pdo->prepare("
                SELECT id FROM chat_messages 
                WHERE evolution_message_id = ? OR message_id = ?
            ");
            $stmt->execute([$messageId, $messageId]);
            if ($stmt->fetch()) continue;
            
            // Extrair informações
            $isFromMe = $key['fromMe'] ?? false;
            $timestamp = $msg['messageTimestamp'] ?? time();
            
            // Extrair texto da mensagem
            $messageText = '';
            $messageType = 'text';
            
            if (isset($message['conversation'])) {
                $messageText = $message['conversation'];
            } elseif (isset($message['extendedTextMessage']['text'])) {
                $messageText = $message['extendedTextMessage']['text'];
            } elseif (isset($message['imageMessage'])) {
                $messageType = 'image';
                $messageText = '[Imagem]' . (isset($message['imageMessage']['caption']) ? ': ' . $message['imageMessage']['caption'] : '');
            } elseif (isset($message['videoMessage'])) {
                $messageType = 'video';
                $messageText = '[Vídeo]' . (isset($message['videoMessage']['caption']) ? ': ' . $message['videoMessage']['caption'] : '');
            } elseif (isset($message['audioMessage'])) {
                $messageType = 'audio';
                $messageText = '[Áudio]';
            } elseif (isset($message['documentMessage'])) {
                $messageType = 'document';
                $messageText = '[Documento]' . (isset($message['documentMessage']['fileName']) ? ': ' . $message['documentMessage']['fileName'] : '');
            } elseif (isset($message['stickerMessage'])) {
                $messageType = 'sticker';
                $messageText = '[Figurinha]';
            } else {
                $messageText = '[Mensagem não suportada]';
            }
            
            // Salvar mensagem
            $stmt = $pdo->prepare("
                INSERT INTO chat_messages 
                (conversation_id, user_id, evolution_message_id, message_id, is_from_me, from_me, message_type, message_text, timestamp, created_at, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?), 'delivered')
            ");
            
            $stmt->execute([
                $conversationId,
                $userId,
                $messageId,
                $messageId,
                $isFromMe ? 1 : 0,
                $isFromMe ? 1 : 0,
                $messageType,
                $messageText,
                $timestamp,
                $timestamp
            ]);
            
            $newMessagesCount++;
            $totalNewMessages++;
        }
        
        if ($newMessagesCount > 0) {
            // Atualizar conversa
            $stmt = $pdo->prepare("
                UPDATE chat_conversations 
                SET last_message_time = NOW(), updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$conversationId]);
            
            $conversationsUpdated++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'new_messages' => $totalNewMessages,
        'conversations_checked' => count($conversations),
        'conversations_updated' => $conversationsUpdated,
        'errors' => $errors,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
