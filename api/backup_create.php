<?php
/**
 * API - Criar Backup de Conversas
 * 
 * POST: Dispara criação de backup
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

// Apenas supervisores e admins podem criar backups
if (!in_array($userType, ['admin', 'supervisor', 'user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sem permissão para criar backups']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Obter configuração do usuário para saber o destino
$config = getBackupConfig($userId);
$destination = $config['destination'] ?? 'download';
    
// Converter 'local' antigo para 'download'
if ($destination === 'local') {
    $destination = 'download';
}

// Se force_local for true, forçar download local
if (isset($input['force_local']) && $input['force_local'] === true) {
    $destination = 'download';
    error_log("[BACKUP_CREATE_API] force_local detectado, destination forçado para: download");
} else {
    error_log("[BACKUP_CREATE_API] force_local não detectado, destination: $destination");
}

error_log("[BACKUP_CREATE_API] Destination final antes de criar backup: $destination");

$options = [
    'format' => $input['format'] ?? 'json',
    'date_from' => $input['date_from'] ?? null,
    'date_to' => $input['date_to'] ?? null,
    'include_media' => (bool) ($input['include_media'] ?? false),
    'compress' => (bool) ($input['compress'] ?? true),
    'incremental' => (bool) ($input['incremental'] ?? false),
    'encrypt' => (bool) ($input['encrypt'] ?? false),
    'encrypt_password' => $input['encrypt_password'] ?? null,
    'send_email' => (bool) ($input['send_email'] ?? true),
    'config_id' => $config ? $config['id'] : null,
    'destination' => $destination,
    'retention_days' => $config ? $config['retention_days'] : 30,
    'config' => $config
];

// Validar formato
if (!in_array($options['format'], ['json', 'csv'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Formato inválido. Use json ou csv.']);
    exit;
}

// Validar datas
if ($options['date_from'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $options['date_from'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Data inicial inválida. Use formato YYYY-MM-DD.']);
    exit;
}

if ($options['date_to'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $options['date_to'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Data final inválida. Use formato YYYY-MM-DD.']);
    exit;
}

try {
    // Obter configuração do usuário para saber o destino
    $config = getBackupConfig($userId);
    $destination = $config['destination'] ?? 'download';
    
    // Converter 'local' antigo para 'download'
    if ($destination === 'local') {
        $destination = 'download';
    }
    
    // Buscar extra_config completo para cloud providers
    $stmt = $pdo->prepare('SELECT * FROM backup_configs WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $fullConfig = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Adicionar destino às opções
    $options['destination'] = $destination;
    $options['config'] = $fullConfig; // Passar config completo com extra_config
    
    $result = createConversationBackup($userId, $options);
    
    if ($result['success']) {
        $response = [
            'success' => true,
            'backup_id' => $result['backup_id'],
            'filename' => $result['filename'],
            'size' => $result['size'],
            'size_formatted' => formatFileSize($result['size']),
            'conversations' => $result['conversations'],
            'messages' => $result['messages'],
            'destination' => $destination
        ];
        
        // Mensagens específicas por destino
        if ($destination === 'download') {
            $response['download_url'] = '/api/backup_download.php?id=' . $result['backup_id'] . '&auto=1';
            $response['message'] = 'Backup criado! O download iniciará automaticamente.';
        } elseif ($destination === 'ftp') {
            $response['message'] = 'Backup enviado para o servidor FTP com sucesso!';
        } elseif ($destination === 'network') {
            $response['message'] = 'Backup salvo no servidor de rede com sucesso!';
        } elseif ($destination === 'google_drive') {
            $response['message'] = 'Backup enviado para o Google Drive com sucesso!';
            $response['remote_url'] = $result['remote_url'] ?? null;
        } elseif ($destination === 'onedrive') {
            $response['message'] = 'Backup enviado para o OneDrive com sucesso!';
            $response['remote_url'] = $result['remote_url'] ?? null;
        } elseif ($destination === 'dropbox') {
            $response['message'] = 'Backup enviado para o Dropbox com sucesso!';
            $response['remote_url'] = $result['remote_url'] ?? null;
        } elseif ($destination === 's3') {
            $response['message'] = 'Backup enviado para o Amazon S3 com sucesso!';
            $response['remote_url'] = $result['remote_url'] ?? null;
        } else {
            $response['message'] = 'Backup criado com sucesso!';
        }
        
        echo json_encode($response);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Erro ao criar backup']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
