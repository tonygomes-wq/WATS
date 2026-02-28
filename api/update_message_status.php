<?php
/**
 * API para atualizar status de mensagens manualmente
 * Usado quando o webhook não está funcionando corretamente
 */

require_once '../config/database.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$userId = $_SESSION['user_id'];

// Obter dados
$data = json_decode(file_get_contents('php://input'), true);
$messageId = $data['message_id'] ?? null;
$status = $data['status'] ?? null;

if (!$messageId || !$status) {
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos']);
    exit;
}

// Validar status
$validStatuses = ['pending', 'sent', 'delivered', 'read', 'failed'];
if (!in_array($status, $validStatuses)) {
    echo json_encode(['success' => false, 'error' => 'Status inválido']);
    exit;
}

try {
    // Atualizar status da mensagem
    $stmt = $pdo->prepare("
        UPDATE chat_messages 
        SET status = ?,
            read_at = CASE WHEN ? = 'read' THEN NOW() ELSE read_at END
        WHERE id = ? 
        AND user_id = ?
        AND from_me = 1
    ");
    
    $stmt->execute([$status, $status, $messageId, $userId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Status atualizado com sucesso',
            'message_id' => $messageId,
            'new_status' => $status
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Mensagem não encontrada ou não pertence ao usuário'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao atualizar status: ' . $e->getMessage()
    ]);
}
