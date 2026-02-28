<?php
/**
 * API para obter mensagens recentes (últimas 10 enviadas)
 * Usado para diagnóstico de status de mensagens
 */

if (!isset($_SESSION)) {
    session_start();
}

require_once '../config/database.php';

header('Content-Type: application/json');

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Não autorizado'
    ]);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            message_text,
            status,
            created_at,
            read_at
        FROM chat_messages
        WHERE user_id = ?
        AND from_me = 1
        ORDER BY created_at DESC
        LIMIT 10
    ");
    
    $stmt->execute([$userId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar mensagens: ' . $e->getMessage()
    ]);
}
