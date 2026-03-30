<?php
/**
 * API para buscar mensagens recentes (todas as conversas)
 * Usado para monitoramento e diagnóstico de webhook
 */

session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$user_id = $_SESSION['user_id'];
$limit = (int)($_GET['limit'] ?? 10);
$limit = min($limit, 50); // Máximo 50 mensagens

try {
    // Buscar mensagens recentes do usuário (todas as conversas)
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.message_id,
            m.from_me,
            m.message_type,
            m.message_text,
            m.status,
            m.timestamp,
            FROM_UNIXTIME(m.timestamp) as created_at,
            c.phone,
            c.contact_name
        FROM chat_messages m
        JOIN chat_conversations c ON m.conversation_id = c.id
        WHERE c.user_id = ?
        ORDER BY m.timestamp DESC
        LIMIT ?
    ");
    $stmt->execute([$user_id, $limit]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar mensagens
    foreach ($messages as &$msg) {
        $msg['id'] = (int) $msg['id'];
        $msg['from_me'] = (bool) $msg['from_me'];
        $msg['timestamp'] = (int) $msg['timestamp'];
        $msg['created_at_formatted'] = date('d/m/Y H:i:s', $msg['timestamp']);
        $msg['time_formatted'] = formatTime($msg['created_at']);
        
        // Truncar mensagem longa
        if (strlen($msg['message_text']) > 100) {
            $msg['message_text'] = substr($msg['message_text'], 0, 100) . '...';
        }
    }
    
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'count' => count($messages)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function formatTime($datetime) {
    $time = strtotime($datetime);
    $now = time();
    
    // Hoje
    if (date('Y-m-d', $time) === date('Y-m-d', $now)) {
        return 'Hoje às ' . date('H:i', $time);
    }
    
    // Ontem
    if (date('Y-m-d', $time) === date('Y-m-d', $now - 86400)) {
        return 'Ontem às ' . date('H:i', $time);
    }
    
    // Mais antigo
    return date('d/m/Y H:i', $time);
}
?>
