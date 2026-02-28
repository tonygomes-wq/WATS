<?php
/**
 * API DE POLLING PARA MENSAGENS DO TEAMS
 * Busca novas mensagens do Teams em tempo real
 * Chamado periodicamente pelo chat.php
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

$userId = $_SESSION['user_id'];

try {
    // Buscar conversas do Teams com novas mensagens (últimos 30 segundos)
    // Teams usa tabela 'messages' (não 'chat_messages' que é do WhatsApp)
    $stmt = $pdo->prepare("
        SELECT 
            cc.id as conversation_id,
            cc.contact_name,
            cc.teams_chat_id,
            cc.profile_pic_url,
            COUNT(m.id) as new_messages_count,
            MAX(m.created_at) as last_message_time
        FROM chat_conversations cc
        INNER JOIN messages m ON m.conversation_id = cc.id
        WHERE cc.user_id = ?
        AND cc.channel_type = 'teams'
        AND m.sender_type = 'contact'
        AND m.created_at >= DATE_SUB(NOW(), INTERVAL 30 SECOND)
        GROUP BY cc.id
        ORDER BY last_message_time DESC
    ");
    
    $stmt->execute([$userId]);
    $updates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar resposta
    $result = [
        'success' => true,
        'has_updates' => count($updates) > 0,
        'updates' => []
    ];
    
    foreach ($updates as $update) {
        $result['updates'][] = [
            'conversation_id' => (int) $update['conversation_id'],
            'contact_name' => $update['contact_name'],
            'teams_chat_id' => $update['teams_chat_id'],
            'profile_pic_url' => $update['profile_pic_url'],
            'new_messages_count' => (int) $update['new_messages_count'],
            'last_message_time' => $update['last_message_time']
        ];
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("[CHAT_POLL_TEAMS] Erro: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
