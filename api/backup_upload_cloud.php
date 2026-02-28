<?php
/**
 * API - Upload de Backup para Nuvem
 * 
 * POST: Faz upload de um backup existente para o provedor de nuvem configurado
 * 
 * MACIP Tecnologia LTDA
 */

session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/backup_service.php';
require_once '../includes/cloud_providers/google_drive.php';

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

// Verificar backup
$stmt = $pdo->prepare('SELECT * FROM backups WHERE id = ? AND user_id = ? AND status = "completed"');
$stmt->execute([$backupId, $userId]);
$backup = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$backup) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Backup não encontrado']);
    exit;
}

// Verificar configuração
$stmt = $pdo->prepare('SELECT * FROM backup_configs WHERE user_id = ?');
$stmt->execute([$userId]);
$config = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$config) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Configuração de backup não encontrada']);
    exit;
}

$destination = $config['destination'];

try {
    switch ($destination) {
        case 'google_drive':
            $result = uploadBackupToGoogleDrive($backupId, $userId);
            break;
            
        case 'onedrive':
            $result = ['success' => false, 'error' => 'OneDrive ainda não implementado'];
            break;
            
        case 'dropbox':
            $result = ['success' => false, 'error' => 'Dropbox ainda não implementado'];
            break;
            
        case 's3':
            $result = ['success' => false, 'error' => 'Amazon S3 ainda não implementado'];
            break;
            
        case 'local':
        default:
            $result = ['success' => false, 'error' => 'Backup já está armazenado localmente'];
            break;
    }
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Backup enviado para ' . ucfirst(str_replace('_', ' ', $destination)),
            'file_id' => $result['file_id'] ?? null,
            'remote_url' => $result['web_view_link'] ?? null
        ]);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
