<?php
/**
 * API - Deletar Conversa
 */

header('Content-Type: application/json');
session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar autenticação
requireLogin();

$userId = $_SESSION['user_id'];

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $conversationId = $data['conversation_id'] ?? null;
    
    if (!$conversationId) {
        throw new Exception('ID da conversa não fornecido');
    }
    
    // Verificar se a conversa pertence ao usuário
    $stmt = $pdo->prepare("
        SELECT id FROM chat_conversations 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$conversationId, $userId]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Conversa não encontrada ou sem permissão');
    }
    
    // Deletar mensagens da conversa
    $stmt = $pdo->prepare("DELETE FROM chat_messages WHERE conversation_id = ?");
    $stmt->execute([$conversationId]);
    
    // Deletar conversa
    $stmt = $pdo->prepare("DELETE FROM chat_conversations WHERE id = ?");
    $stmt->execute([$conversationId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Conversa deletada com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
