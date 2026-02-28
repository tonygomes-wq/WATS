<?php
/**
 * API - Listar Backups
 * 
 * GET: Lista backups do usuário
 * 
 * MACIP Tecnologia LTDA
 */

session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Verificar autenticação
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userType = $_SESSION['user_type'] ?? 'user';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
$offset = max(0, (int) ($_GET['offset'] ?? 0));

// Incluir backup_service.php para usar formatFileSize()
require_once '../includes/backup_service.php';

try {
    // Buscar backups diretamente do banco (excluindo os deletados)
    $stmt = $pdo->prepare("
        SELECT * FROM backups 
        WHERE user_id = ? AND status != 'deleted'
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$userId, $limit, $offset]);
    $backups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar estatísticas
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_backups,
            COALESCE(SUM(size_bytes), 0) as total_size,
            MAX(created_at) as last_backup
        FROM backups 
        WHERE user_id = ? AND status = 'completed'
    ");
    $stmt->execute([$userId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Buscar configuração
    $stmt = $pdo->prepare("SELECT * FROM backup_configs WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Formatar dados para resposta
    $formattedBackups = array_map(function($backup) {
        $destination = $backup['destination'] ?? 'download';
        // Converter 'local' antigo para 'download'
        if ($destination === 'local') {
            $destination = 'download';
        }
        
        return [
            'id' => (int) $backup['id'],
            'filename' => $backup['filename'] ?? 'backup.json',
            'backup_type' => $backup['backup_type'] ?? 'full',
            'destination' => $destination,
            'size_bytes' => (int) ($backup['size_bytes'] ?? 0),
            'size_formatted' => formatFileSize($backup['size_bytes'] ?? 0),
            'conversations_count' => (int) ($backup['conversations_count'] ?? 0),
            'messages_count' => (int) ($backup['messages_count'] ?? 0),
            'date_from' => $backup['date_from'] ?? null,
            'date_to' => $backup['date_to'] ?? null,
            'status' => $backup['status'] ?? 'pending',
            'error_message' => $backup['error_message'] ?? null,
            'started_at' => $backup['started_at'] ?? null,
            'completed_at' => $backup['completed_at'] ?? null,
            'expires_at' => $backup['expires_at'] ?? null,
            'created_at' => $backup['created_at'] ?? null,
            'remote_url' => $backup['remote_url'] ?? null,
            'can_download' => ($backup['status'] ?? '') === 'completed' && $destination === 'download' && !empty($backup['local_path'])
        ];
    }, $backups);
    
    // Processar configuração para incluir status de conexão dos cloud providers
    $configResponse = null;
    if ($config) {
        $dest = $config['destination'] ?? 'download';
        if ($dest === 'local') $dest = 'download';
        
        $configResponse = [
            'destination' => $dest,
            'schedule' => $config['schedule'] ?? 'manual',
            'schedule_time' => $config['schedule_time'] ?? '03:00:00',
            'retention_days' => (int) ($config['retention_days'] ?? 30),
            'format' => $config['format'] ?? 'json',
            'include_media' => (bool) ($config['include_media'] ?? 0),
            'is_active' => (bool) ($config['is_active'] ?? 1)
        ];
        
        // Decodificar extra_config para obter status de conexão
        if (!empty($config['extra_config'])) {
            $extra = json_decode($config['extra_config'], true);
            
            // FTP config
            if (!empty($extra['ftp'])) {
                $configResponse['ftp_config'] = [
                    'host' => $extra['ftp']['host'] ?? '',
                    'port' => $extra['ftp']['port'] ?? 21,
                    'user' => $extra['ftp']['user'] ?? '',
                    'path' => $extra['ftp']['path'] ?? '',
                    'ssl' => $extra['ftp']['ssl'] ?? false
                ];
            }
            
            // Network config
            if (!empty($extra['network'])) {
                $configResponse['network_config'] = [
                    'path' => $extra['network']['path'] ?? '',
                    'user' => $extra['network']['user'] ?? ''
                ];
            }
            
            // Google Drive
            if (!empty($extra['google'])) {
                $configResponse['google_config'] = [
                    'client_id' => $extra['google']['client_id'] ?? '',
                    'has_secret' => !empty($extra['google']['client_secret'])
                ];
                $configResponse['google_connected'] = !empty($extra['google']['access_token']);
            }
            
            // OneDrive
            if (!empty($extra['onedrive'])) {
                $configResponse['onedrive_config'] = [
                    'client_id' => $extra['onedrive']['client_id'] ?? '',
                    'tenant_id' => $extra['onedrive']['tenant_id'] ?? 'common',
                    'has_secret' => !empty($extra['onedrive']['client_secret'])
                ];
                $configResponse['onedrive_connected'] = !empty($extra['onedrive']['access_token']);
            }
            
            // Dropbox
            if (!empty($extra['dropbox'])) {
                $configResponse['dropbox_config'] = [
                    'app_key' => $extra['dropbox']['app_key'] ?? '',
                    'has_secret' => !empty($extra['dropbox']['app_secret'])
                ];
                $configResponse['dropbox_connected'] = !empty($extra['dropbox']['access_token']);
            }
            
            // S3
            if (!empty($extra['s3'])) {
                $configResponse['s3_config'] = [
                    'access_key' => $extra['s3']['access_key'] ?? '',
                    'bucket' => $extra['s3']['bucket'] ?? '',
                    'region' => $extra['s3']['region'] ?? 'us-east-1',
                    'has_secret' => !empty($extra['s3']['secret_key'])
                ];
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'backups' => $formattedBackups,
        'stats' => [
            'total_backups' => (int) ($stats['total_backups'] ?? 0),
            'total_size' => (int) ($stats['total_size'] ?? 0),
            'total_size_formatted' => formatFileSize($stats['total_size'] ?? 0),
            'last_backup' => $stats['last_backup'] ?? null,
            'next_scheduled' => $config ? ($config['next_backup_at'] ?? null) : null
        ],
        'config' => $configResponse
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
