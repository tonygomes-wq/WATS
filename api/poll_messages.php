<?php
/**
 * Sistema de Polling para Buscar Mensagens
 * Alternativa ao webhook quando ele não funciona
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Verificar autenticação
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'] ?? 'user';

try {
    // Verificar se é atendente ou usuário normal
    $instance = null;
    $token = null;
    $ownerUserId = $userId; // ID do dono das conversas
    
    if ($userType === 'attendant') {
        // Atendente: buscar dados do supervisor/admin associado
        $stmt = $pdo->prepare("SELECT supervisor_id FROM supervisor_users WHERE id = ?");
        $stmt->execute([$userId]);
        $attendant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($attendant && $attendant['supervisor_id']) {
            $ownerUserId = $attendant['supervisor_id'];
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$ownerUserId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $instance = $user['evolution_instance'];
                $token = $user['evolution_token'] ?: EVOLUTION_API_KEY;
            }
        }
        
        if (!$instance) {
            throw new Exception('Supervisor não encontrado ou sem instância configurada');
        }
    } else {
        // Usuário normal: buscar seus próprios dados
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            throw new Exception('Usuário não encontrado');
        }
        
        $instance = $user['evolution_instance'] ?? 'CELULAR-TONY';
        $token = $user['evolution_token'] ?: EVOLUTION_API_KEY;
    }
    
    $evolutionUrl = EVOLUTION_API_URL;
    
    // Buscar conversas ativas (últimas 24 horas) - usar ownerUserId para atendentes
    $stmt = $pdo->prepare("
        SELECT DISTINCT phone 
        FROM chat_conversations 
        WHERE user_id = ? 
        AND last_message_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY last_message_time DESC
        LIMIT 20
    ");
    $stmt->execute([$ownerUserId]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $newMessages = 0;
    $errors = [];
    
    foreach ($conversations as $conv) {
        $phone = $conv['phone'];
        
        // Buscar mensagens deste contato na Evolution API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $evolutionUrl . '/chat/findMessages/' . $instance);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'where' => [
                'key' => [
                    'remoteJid' => $phone . '@s.whatsapp.net'
                ]
            ],
            'limit' => 50
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $token
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode != 200) {
            $errors[] = "Erro ao buscar mensagens de $phone: HTTP $httpCode";
            continue;
        }
        
        $messages = json_decode($response, true);
        
        if (!is_array($messages)) {
            continue;
        }
        
        // Processar cada mensagem
        foreach ($messages as $msg) {
            $key = $msg['key'] ?? [];
            $message = $msg['message'] ?? [];
            
            $messageId = $key['id'] ?? '';
            $fromMe = $key['fromMe'] ?? false;
            
            // Só processar mensagens recebidas (não enviadas por mim)
            if ($fromMe) {
                continue;
            }
            
            // Verificar se já existe no banco
            $stmt = $pdo->prepare("SELECT id FROM chat_messages WHERE message_id = ?");
            $stmt->execute([$messageId]);
            if ($stmt->fetch()) {
                continue; // Já existe
            }
            
            // Extrair texto da mensagem - VERSÃO MELHORADA
            $messageText = '';
            
            // Tentar todas as formas possíveis de extrair texto
            if (isset($message['conversation'])) {
                $messageText = $message['conversation'];
            } elseif (isset($message['extendedTextMessage']['text'])) {
                $messageText = $message['extendedTextMessage']['text'];
            } elseif (isset($message['imageMessage'])) {
                $caption = $message['imageMessage']['caption'] ?? '';
                $messageText = $caption ? "[Imagem] $caption" : '[Imagem]';
            } elseif (isset($message['videoMessage'])) {
                $caption = $message['videoMessage']['caption'] ?? '';
                $messageText = $caption ? "[Vídeo] $caption" : '[Vídeo]';
            } elseif (isset($message['documentMessage'])) {
                $caption = $message['documentMessage']['caption'] ?? '';
                $fileName = $message['documentMessage']['fileName'] ?? '';
                $messageText = $caption ? "[Documento] $caption" : "[Documento] $fileName";
            } elseif (isset($message['audioMessage'])) {
                $messageText = '[Áudio]';
            } elseif (isset($message['stickerMessage'])) {
                $messageText = '[Sticker]';
            } elseif (isset($message['locationMessage'])) {
                $messageText = '[Localização]';
            } elseif (isset($message['contactMessage'])) {
                $displayName = $message['contactMessage']['displayName'] ?? '';
                $messageText = "[Contato] $displayName";
            } else {
                // Log da estrutura completa para debug
                error_log("POLLING: Tipo de mensagem desconhecido: " . json_encode($message));
                $messageText = '[Mensagem não suportada]';
            }
            
            // Buscar ou criar conversa - usar ownerUserId para atendentes
            $conversationId = getOrCreateConversation($pdo, $ownerUserId, $phone);
            
            // Salvar mensagem
            $stmt = $pdo->prepare("
                INSERT INTO chat_messages 
                (conversation_id, user_id, message_id, message_type, message_text, from_me, created_at)
                VALUES (?, ?, ?, 'text', ?, 0, NOW())
            ");
            $stmt->execute([$conversationId, $userId, $messageId, $messageText]);
            
            // Atualizar conversa
            $stmt = $pdo->prepare("
                UPDATE chat_conversations 
                SET last_message_text = ?, last_message_time = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$messageText, $conversationId]);
            
            $newMessages++;
            
            error_log("POLLING: Nova mensagem de $phone: $messageText");
        }
    }
    
    echo json_encode([
        'success' => true,
        'new_messages' => $newMessages,
        'conversations_checked' => count($conversations),
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    error_log("ERRO POLLING: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function getOrCreateConversation($pdo, $userId, $phone) {
    // Normalizar telefone
    $phoneClean = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phoneClean, 0, 2) !== '55') {
        $phoneClean = '55' . $phoneClean;
    }
    
    // Buscar conversa existente
    $stmt = $pdo->prepare("
        SELECT id FROM chat_conversations 
        WHERE user_id = ? AND phone = ?
    ");
    $stmt->execute([$userId, $phoneClean]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($conversation) {
        return $conversation['id'];
    }
    
    // Criar nova conversa
    $stmt = $pdo->prepare("
        INSERT INTO chat_conversations 
        (user_id, phone, contact_name, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$userId, $phoneClean, $phoneClean]);
    
    return $pdo->lastInsertId();
}
?>
