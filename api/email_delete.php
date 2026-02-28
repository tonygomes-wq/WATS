<?php
/**
 * API para excluir conversa de email
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$conversationId = $data['id'] ?? 0;

if (!$conversationId) {
    echo json_encode(['success' => false, 'error' => 'ID da conversa é obrigatório']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    
    // Verificar se a conversa existe
    $hasUserId = false;
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM conversations LIKE 'user_id'");
        $hasUserId = $checkCol->rowCount() > 0;
    } catch (Exception $e) {
        // Ignorar
    }
    
    $sql = "SELECT id FROM conversations WHERE id = ?";
    $params = [$conversationId];
    
    if ($hasUserId) {
        $sql .= " AND user_id = ?";
        $params[] = $userId;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso negado']);
        exit;
    }
    
    // Excluir mensagens da conversa
    $stmt = $pdo->prepare("DELETE FROM messages WHERE conversation_id = ?");
    $stmt->execute([$conversationId]);
    
    // Excluir conversa
    $stmt = $pdo->prepare("DELETE FROM conversations WHERE id = ?");
    $stmt->execute([$conversationId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Conversa excluída com sucesso'
    ]);
    
} catch (Exception $e) {
    error_log('[Email Delete] Erro: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
