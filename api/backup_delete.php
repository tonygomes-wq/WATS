<?php
/**
 * API - Deletar Backup
 * 
 * POST: Remove um backup
 * 
 * MACIP Tecnologia LTDA
 */

session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/backup_service.php';

header('Content-Type: application/json');

// Verificar autenticação
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$backupId = (int) ($input['backup_id'] ?? 0);

if (!$backupId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID do backup não informado']);
    exit;
}

try {
    $result = deleteBackup($backupId, $userId);
    
    if ($result['success']) {
        echo json_encode(['success' => true, 'message' => 'Backup removido com sucesso']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Erro ao remover backup']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
