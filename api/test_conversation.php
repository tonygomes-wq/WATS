<?php
/**
 * Teste para verificar se conversa existe no banco
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$conversationId = $_GET['id'] ?? 0;
$userId = $_SESSION['user_id'];

try {
    // Buscar conversa diretamente no banco
    $stmt = $pdo->prepare("
        SELECT * FROM chat_conversations 
        WHERE id = ?
    ");
    $stmt->execute([$conversationId]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Buscar mensagens
    $stmt = $pdo->prepare("
        SELECT * FROM chat_messages 
        WHERE conversation_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$conversationId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'conversation' => $conversation,
        'messages' => $messages,
        'user_id' => $userId
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
