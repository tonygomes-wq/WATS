<?php
/**
 * API - Configuração de Backup
 * 
 * GET: Obtém configuração atual
 * POST: Salva configuração
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
$userType = $_SESSION['user_type'] ?? 'user';

// Apenas supervisores e admins podem configurar backups
if (!in_array($userType, ['admin', 'supervisor', 'user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sem permissão para configurar backups']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $config = getBackupConfig($userId);
        
        echo json_encode([
            'success' => true,
            'config' => $config ? [
                'id' => (int) $config['id'],
                'destination' => $config['destination'],
                'schedule' => $config['schedule'],
                'schedule_time' => $config['schedule_time'],
                'retention_days' => (int) $config['retention_days'],
                'format' => $config['format'],
                'include_media' => (bool) $config['include_media'],
                'is_active' => (bool) $config['is_active'],
                'last_backup_at' => $config['last_backup_at'],
                'next_backup_at' => $config['next_backup_at']
            ] : null
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    
    // Salvar credenciais de cloud provider (antes de conectar OAuth)
    if (!empty($input['save_cloud_credentials'])) {
        $provider = $input['provider'] ?? '';
        
        // Buscar configuração existente
        $stmt = $pdo->prepare('SELECT id, extra_config FROM backup_configs WHERE user_id = ?');
        $stmt->execute([$userId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $extra = [];
        if ($existing && !empty($existing['extra_config'])) {
            $extra = json_decode($existing['extra_config'], true) ?: [];
        }
        
        // Salvar credenciais por provider
        if ($provider === 'google_drive') {
            $extra['google'] = [
                'client_id' => $input['client_id'] ?? '',
                'client_secret' => $input['client_secret'] ?? ''
            ];
        } elseif ($provider === 'onedrive') {
            $extra['onedrive'] = [
                'client_id' => $input['client_id'] ?? '',
                'client_secret' => $input['client_secret'] ?? '',
                'tenant_id' => $input['tenant_id'] ?? 'common'
            ];
        } elseif ($provider === 'dropbox') {
            $extra['dropbox'] = [
                'app_key' => $input['app_key'] ?? '',
                'app_secret' => $input['app_secret'] ?? ''
            ];
        }
        
        if ($existing) {
            $stmt = $pdo->prepare('UPDATE backup_configs SET extra_config = ?, updated_at = NOW() WHERE user_id = ?');
            $stmt->execute([json_encode($extra), $userId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO backup_configs (user_id, destination, extra_config) VALUES (?, ?, ?)');
            $stmt->execute([$userId, $provider, json_encode($extra)]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Credenciais salvas']);
        exit;
    }
    
    // Validar destino
    $validDestinations = ['download', 'ftp', 'network', 'google_drive', 'onedrive', 'dropbox', 's3'];
    
    // Converter 'local' antigo para 'download' antes da validação
    if (isset($input['destination']) && $input['destination'] === 'local') {
        $input['destination'] = 'download';
    }
    
    if (isset($input['destination']) && !in_array($input['destination'], $validDestinations)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Destino inválido: ' . $input['destination']]);
        exit;
    }
    
    // Validar configurações FTP se destino for FTP
    if (isset($input['destination']) && $input['destination'] === 'ftp') {
        if (empty($input['ftp_config']['host']) || empty($input['ftp_config']['user']) || empty($input['ftp_config']['pass'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Configurações FTP incompletas']);
            exit;
        }
    }
    
    // Validar configurações de Rede se destino for network
    if (isset($input['destination']) && $input['destination'] === 'network') {
        if (empty($input['network_config']) || empty($input['network_config']['path'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Caminho de rede não informado. Informe o caminho completo (ex: \\\\servidor\\pasta ou /mnt/backup)']);
            exit;
        }
        
        // Validar formato do caminho
        $path = trim($input['network_config']['path']);
        if (strlen($path) < 3) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Caminho de rede inválido']);
            exit;
        }
    }
    
    // Validar agendamento
    $validSchedules = ['manual', 'daily', 'weekly', 'monthly'];
    if (isset($input['schedule']) && !in_array($input['schedule'], $validSchedules)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Agendamento inválido']);
        exit;
    }
    
    // Validar formato
    $validFormats = ['json', 'csv', 'pdf'];
    if (isset($input['format']) && !in_array($input['format'], $validFormats)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Formato inválido']);
        exit;
    }
    
    // Validar retenção
    if (isset($input['retention_days'])) {
        $retention = (int) $input['retention_days'];
        if ($retention < 1 || $retention > 365) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Retenção deve ser entre 1 e 365 dias']);
            exit;
        }
    }
    
    try {
        $configId = saveBackupConfig($userId, $input);
        
        echo json_encode([
            'success' => true,
            'config_id' => $configId,
            'message' => 'Configuração salva com sucesso'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método não permitido']);
