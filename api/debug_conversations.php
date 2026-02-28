<?php
/**
 * Debug de conversas - Verificar por que conversas não aparecem na lista
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'] ?? 'user';
$isAttendant = ($userType === 'attendant');

try {
    // Buscar últimas 10 conversas do usuário (sem filtros)
    $stmt = $pdo->prepare("
        SELECT 
            id,
            user_id,
            phone,
            contact_name,
            status,
            attended_by,
            last_message_time,
            created_at,
            updated_at,
            is_archived,
            channel_type
        FROM chat_conversations 
        WHERE user_id = ?
        ORDER BY last_message_time DESC
        LIMIT 10
    ");
    $stmt->execute([$userId]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Contar total de conversas
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM chat_conversations WHERE user_id = ?");
    $stmt->execute([$userId]);
    $total = $stmt->fetch()['total'];
    
    // Buscar conversas que deveriam aparecer no inbox
    $stmt = $pdo->prepare("
        SELECT 
            id,
            phone,
            contact_name,
            status,
            attended_by,
            last_message_time
        FROM chat_conversations 
        WHERE user_id = ?
        AND is_archived = 0
        AND (attended_by IS NULL OR attended_by = ?)
        AND (status IS NULL OR status NOT IN ('closed', 'in_progress'))
        ORDER BY last_message_time DESC
        LIMIT 10
    ");
    $stmt->execute([$userId, $userId]);
    $inboxConversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'user_id' => $userId,
        'user_type' => $userType,
        'is_attendant' => $isAttendant,
        'total_conversations' => $total,
        'last_10_conversations' => $conversations,
        'inbox_conversations' => $inboxConversations,
        'debug_info' => [
            'filter_applied' => 'attended_by IS NULL OR attended_by = user_id',
            'status_filter' => 'status IS NULL OR status NOT IN (closed, in_progress)'
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
