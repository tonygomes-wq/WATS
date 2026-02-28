<?php
/**
 * API para listar conversas de Email
 * Integração com sistema de chat multi-canal
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
$search = $_GET['search'] ?? '';
$limit = min((int)($_GET['limit'] ?? 50), 100);
$offset = (int)($_GET['offset'] ?? 0);

try {
    // Buscar conversas de email (agrupadas por remetente)
    $sql = "
        SELECT 
            m.contact_id,
            c.name as contact_name,
            c.email as contact_email,
            c.profile_pic_url,
            MAX(m.timestamp) as last_message_time,
            COUNT(CASE WHEN m.is_read = 0 AND m.from_me = 0 THEN 1 END) as unread_count,
            (SELECT message_text FROM messages 
             WHERE contact_id = m.contact_id 
             AND channel_id IN (SELECT id FROM channels WHERE channel_type = 'email' AND user_id = ?)
             ORDER BY timestamp DESC LIMIT 1) as last_message_text,
            (SELECT subject FROM messages 
             WHERE contact_id = m.contact_id 
             AND channel_id IN (SELECT id FROM channels WHERE channel_type = 'email' AND user_id = ?)
             ORDER BY timestamp DESC LIMIT 1) as last_subject
        FROM messages m
        INNER JOIN contacts c ON c.id = m.contact_id
        WHERE m.channel_id IN (
            SELECT id FROM channels 
            WHERE channel_type = 'email' 
            AND user_id = ?
            AND status = 'active'
        )
    ";
    
    $params = [$userId, $userId, $userId];
    
    // Adicionar busca se fornecida
    if (!empty($search)) {
        $sql .= " AND (c.name LIKE ? OR c.email LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $sql .= "
        GROUP BY m.contact_id, c.name, c.email, c.profile_pic_url
        ORDER BY last_message_time DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar conversas para o padrão do chat
    $formattedConversations = [];
    foreach ($conversations as $conv) {
        $formattedConversations[] = [
            'id' => $conv['contact_id'],
            'contact_id' => $conv['contact_id'],
            'contact_name' => $conv['contact_name'] ?? $conv['contact_email'],
            'contact_email' => $conv['contact_email'],
            'profile_pic_url' => $conv['profile_pic_url'] ?? '/assets/img/default-avatar.png',
            'last_message_text' => $conv['last_message_text'] ?? '',
            'last_subject' => $conv['last_subject'] ?? 'Sem assunto',
            'last_message_time' => $conv['last_message_time'],
            'unread_count' => (int)$conv['unread_count'],
            'channel_type' => 'email',
            'channel_icon' => '📧'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'conversations' => $formattedConversations,
        'total' => count($formattedConversations)
    ]);
    
} catch (Exception $e) {
    error_log('[Email Conversations] Erro: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
