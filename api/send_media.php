<?php
/**
 * API - Enviar Mídia V2 (SIMPLIFICADO)
 * 
 * Versão simplificada que usa o código que funcionou no teste direto
 * 
 * @version 2.0
 * @since 2026-02-04
 */

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/handlers/WhatsAppMediaHandler.php';
require_once __DIR__ . '/handlers/TeamsMediaHandler.php';
require_once __DIR__ . '/handlers/MediaStorageManager.php';
require_once __DIR__ . '/handlers/MessageDatabaseManager.php';

// Verificar autenticação
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    $userType = $_SESSION['user_type'] ?? 'user';
    
    // ✅ LOG INICIAL
    error_log("=== SEND_MEDIA_V2 INICIADO ===");
    error_log("User ID: $userId");
    error_log("User Type: $userType");
    
    // Verificar se há arquivo
    if (!isset($_FILES['file'])) {
        error_log("ERROR: Nenhum arquivo enviado");
        throw new Exception('Nenhum arquivo enviado');
    }
    
    error_log("Arquivo recebido: " . $_FILES['file']['name']);
    
    $file = $_FILES['file'];
    $conversationId = $_POST['conversation_id'] ?? null;
    $mediaType = $_POST['media_type'] ?? $_POST['type'] ?? 'image';
    $caption = $_POST['caption'] ?? '';
    
    error_log("Conversation ID: $conversationId");
    error_log("Media Type: $mediaType");
    
    if (!$conversationId) {
        error_log("ERROR: ID da conversa não fornecido");
        throw new Exception('ID da conversa não fornecido');
    }
    
    // Buscar phone da conversa
    $stmt = $pdo->prepare("SELECT phone, contact_name, channel_type, teams_chat_id FROM chat_conversations WHERE id = ?");
    $stmt->execute([$conversationId]);
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conv) {
        throw new Exception('Conversa não encontrada');
    }
    
    $phone = $conv['phone'];
    $channelType = $conv['channel_type'] ?? 'whatsapp';
    $teamsChatId = $conv['teams_chat_id'] ?? null;
    
    // DEBUG: Log do phone encontrado
    error_log("DEBUG send_media_v2 - Conversation ID: $conversationId");
    error_log("DEBUG send_media_v2 - Contact Name: " . ($conv['contact_name'] ?? 'N/A'));
    error_log("DEBUG send_media_v2 - Channel Type: $channelType");
    error_log("DEBUG send_media_v2 - Phone: $phone");
    error_log("DEBUG send_media_v2 - Teams Chat ID: " . ($teamsChatId ?? 'N/A'));
    
    // Determinar usuário owner
    $ownerUserId = $userId;
    if ($userType === 'attendant') {
        $stmt = $pdo->prepare("SELECT supervisor_id FROM supervisor_users WHERE id = ?");
        $stmt->execute([$userId]);
        $attendant = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($attendant && $attendant['supervisor_id']) {
            $ownerUserId = $attendant['supervisor_id'];
        }
    }
    
    // Buscar configuração Evolution
    $stmt = $pdo->prepare("SELECT evolution_instance, evolution_token FROM users WHERE id = ?");
    $stmt->execute([$ownerUserId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !$user['evolution_instance']) {
        throw new Exception('Instância não configurada');
    }
    
    $config = [
        'instance' => $user['evolution_instance'],
        'token' => $user['evolution_token'] ?: EVOLUTION_API_KEY,
        'url' => EVOLUTION_API_URL
    ];
    
    // ✅ CORREÇÃO: Verificar tipo de canal antes de enviar
    error_log("Channel Type detectado: $channelType");
    error_log("Teams Chat ID: " . ($teamsChatId ?? 'NULL'));
    
    if ($channelType === 'teams') {
        error_log("=== INICIANDO ENVIO VIA TEAMS ===");
        
        // Enviar via Teams
        error_log("DEBUG send_media_v2 - Enviando via Teams");
        
        // Salvar arquivo localmente ANTES de enviar
        error_log("Salvando arquivo localmente...");
        $storage = MediaStorageManager::save($file, $ownerUserId);
        
        if (!$storage['success']) {
            throw new Exception($storage['error'] ?? 'Erro ao salvar arquivo');
        }
        
        $localPath = $_SERVER['DOCUMENT_ROOT'] . $storage['media_url'];
        error_log("DEBUG send_media_v2 - Arquivo salvo em: $localPath");
        
        // Buscar configuração do Teams
        require_once __DIR__ . '/../includes/channels/TeamsGraphAPI.php';
        $teamsAPI = new TeamsGraphAPI($pdo, $ownerUserId);
        
        // Enviar via Teams
        $result = TeamsMediaHandler::send(
            $file,
            $teamsChatId,
            $mediaType,
            $caption,
            $teamsAPI,
            $localPath
        );
        
        if (!$result['success']) {
            throw new Exception($result['error'] ?? 'Erro ao enviar mídia para Teams');
        }
        
        // Salvar no banco
        $messageUserId = ($userType === 'attendant') ? $ownerUserId : $userId;
        
        $mediaData = [
            'media_url' => $storage['media_url'],
            'filename' => $file['name'],
            'mimetype' => mime_content_type($file['tmp_name']) ?: $file['type'],
            'size' => $file['size'],
            'caption' => $caption
        ];
        
        $insertedId = MessageDatabaseManager::saveMediaMessage(
            $pdo,
            $conversationId,
            $messageUserId,
            $mediaType,
            $mediaData
        );
        
    } else {
        // Enviar via WhatsApp
        $result = WhatsAppMediaHandler::send(
            $file,
            $phone,
            $mediaType,
            $caption,
            $config
        );
        
        if (!$result['success']) {
            throw new Exception($result['error'] ?? 'Erro ao enviar mídia');
        }
        
        // Salvar localmente
        $storage = MediaStorageManager::save($file, $ownerUserId);
        
        if (!$storage['success']) {
            throw new Exception($storage['error'] ?? 'Erro ao salvar arquivo');
        }
        
        // Salvar no banco
        $messageUserId = ($userType === 'attendant') ? $ownerUserId : $userId;
        
        $mediaData = [
            'media_url' => $storage['media_url'],
            'filename' => $file['name'],
            'mimetype' => mime_content_type($file['tmp_name']) ?: $file['type'],
            'size' => $file['size'],
            'caption' => $caption
        ];
        
        $insertedId = MessageDatabaseManager::saveMediaMessage(
            $pdo,
            $conversationId,
            $messageUserId,
            $mediaType,
            $mediaData
        );
    }
    
    // Buscar mensagem inserida
    $stmt = $pdo->prepare("
        SELECT 
            m.id, m.message_id as external_id, m.from_me,
            m.message_text, m.message_type, m.media_url, m.media_filename,
            m.media_mimetype, m.media_size, m.timestamp, m.created_at
        FROM chat_messages m
        WHERE m.id = ?
    ");
    $stmt->execute([$insertedId]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($message) {
        $message['id'] = (int) $message['id'];
        $message['from_me'] = (bool) $message['from_me'];
        $message['timestamp'] = (int) $message['timestamp'];
        $message['time_formatted'] = date('H:i', $message['timestamp']);
        $message['file_name'] = $message['media_filename'];
        $message['file_size'] = (int) $message['media_size'];
        $message['caption'] = $message['message_text'];
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'conversation_id' => $conversationId
    ]);
    
} catch (Exception $e) {
    error_log("send_media_v2 Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
