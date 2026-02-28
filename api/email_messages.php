<?php
/**
 * API DE MENSAGENS DE EMAIL
 * Retorna mensagens de uma conversa de email
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

// Apenas Admin pode acessar
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
    exit;
}

$userId = $_SESSION['user_id'];
$conversationId = (int) ($_GET['conversation_id'] ?? 0);

if (!$conversationId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID da conversa é obrigatório']);
    exit;
}

try {
    // Verificar se a conversa pertence ao usuário
    $stmt = $pdo->prepare("
        SELECT id, contact_name, contact_number, additional_attributes 
        FROM conversations 
        WHERE id = ? AND user_id = ? AND channel_type = 'email'
    ");
    $stmt->execute([$conversationId, $userId]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversation) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Conversa não encontrada']);
        exit;
    }
    
    // Buscar mensagens
    $stmt = $pdo->prepare("
        SELECT 
            id,
            conversation_id,
            sender_type,
            message_text,
            media_url,
            additional_data,
            is_read,
            created_at
        FROM messages
        WHERE conversation_id = ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$conversationId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar mensagens
    foreach ($messages as &$msg) {
        $msg['from_me'] = ($msg['sender_type'] === 'user');
        $msg['timestamp'] = strtotime($msg['created_at']);
        
        // Extrair dados adicionais do email
        if (!empty($msg['additional_data'])) {
            $additionalData = json_decode($msg['additional_data'], true);
            $msg['email_subject'] = $additionalData['subject'] ?? null;
            $msg['email_from'] = $additionalData['from'] ?? null;
            $msg['email_to'] = $additionalData['to'] ?? null;
            $msg['body_html'] = $additionalData['body_html'] ?? null;
        }
    }
    
    // Marcar mensagens como lidas
    $stmt = $pdo->prepare("
        UPDATE messages 
        SET is_read = 1 
        WHERE conversation_id = ? AND sender_type = 'contact' AND is_read = 0
    ");
    $stmt->execute([$conversationId]);
    
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'conversation' => $conversation
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
