<?php
/**
 * API - Download de Backup
 * 
 * GET: Faz download de um backup local
 * 
 * MACIP Tecnologia LTDA
 */

session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/backup_service.php';

// Verificar autenticação
if (!isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$backupId = (int) ($_GET['id'] ?? 0);

if (!$backupId) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'ID do backup não informado']);
    exit;
}

try {
    $backup = getBackupDownloadPath($backupId, $userId);
    
    if (!$backup) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Backup não encontrado ou indisponível']);
        exit;
    }
    
    $filepath = $backup['local_path'];
    $filename = $backup['filename'];
    
    // Determinar content-type
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $contentTypes = [
        'json' => 'application/json',
        'csv' => 'text/csv',
        'pdf' => 'application/pdf'
    ];
    $contentType = $contentTypes[$ext] ?? 'application/octet-stream';
    
    // Enviar arquivo
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    readfile($filepath);
    exit;
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
