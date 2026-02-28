<?php
/**
 * API - Visualizar conteúdo do backup
 * 
 * GET: Obtém conteúdo do backup para visualização
 * Suporta backups locais e na nuvem (Google Drive, OneDrive, Dropbox, S3)
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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$backupId = (int) ($_GET['id'] ?? 0);

if (!$backupId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID do backup é obrigatório']);
    exit;
}

try {
    // Buscar backup do usuário
    $stmt = $pdo->prepare('
        SELECT * FROM backups 
        WHERE id = ? AND user_id = ? AND status = "completed"
    ');
    $stmt->execute([$backupId, $userId]);
    $backup = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$backup) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Backup não encontrado']);
        exit;
    }
    
    $content = null;
    $source = 'unknown';
    
    // Tentar obter conteúdo do backup
    // 1. Primeiro, verificar se existe localmente
    $localPath = $backup['local_path'];
    if (!empty($localPath) && file_exists($localPath)) {
        $content = file_get_contents($localPath);
        $source = 'local';
    }
    
    // 2. Se não existe localmente, tentar buscar da nuvem
    if ($content === null && !empty($backup['destination'])) {
        $cloudContent = fetchBackupFromCloud($userId, $backup);
        if ($cloudContent !== null) {
            $content = $cloudContent;
            $source = $backup['destination'];
        }
    }
    
    // 3. Tentar caminho alternativo local
    if ($content === null) {
        $altPath = dirname(__DIR__) . '/backups/' . $userId . '/' . $backup['filename'];
        if (file_exists($altPath)) {
            $content = file_get_contents($altPath);
            $source = 'local_alt';
        }
    }
    
    if ($content === null) {
        http_response_code(404);
        echo json_encode([
            'success' => false, 
            'error' => 'Arquivo de backup não encontrado. O backup pode ter sido enviado para um destino externo ou expirado.',
            'destination' => $backup['destination'],
            'can_fetch_from_cloud' => in_array($backup['destination'], ['google_drive', 'onedrive', 'dropbox', 's3'])
        ]);
        exit;
    }
    
    // Descomprimir se necessário
    $filename = $backup['filename'];
    if (strpos($filename, '.gz') !== false) {
        $decompressed = @gzdecode($content);
        if ($decompressed !== false) {
            $content = $decompressed;
            $filename = str_replace('.gz', '', $filename);
        }
    }
    
    // Descriptografar se necessário (requer senha)
    if (strpos($filename, '.enc') !== false) {
        $password = $_GET['password'] ?? null;
        if (empty($password)) {
            echo json_encode([
                'success' => false, 
                'error' => 'Este backup está criptografado. Forneça a senha para visualizar.',
                'encrypted' => true
            ]);
            exit;
        }
        
        $key = hash('sha256', $password, true);
        $iv = substr(hash('sha256', $password . 'iv'), 0, 16);
        $decrypted = openssl_decrypt(base64_decode($content), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        
        if ($decrypted === false) {
            echo json_encode([
                'success' => false, 
                'error' => 'Senha incorreta ou arquivo corrompido.',
                'encrypted' => true
            ]);
            exit;
        }
        
        $content = $decrypted;
        $filename = str_replace('.enc', '', $filename);
    }
    
    // Determinar formato
    $format = pathinfo($filename, PATHINFO_EXTENSION);
    if (empty($format)) {
        // Tentar detectar pelo conteúdo
        $trimmed = ltrim($content);
        if ($trimmed[0] === '{' || $trimmed[0] === '[') {
            $format = 'json';
        } else {
            $format = 'csv';
        }
    }
    
    if ($format === 'json') {
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erro ao decodificar JSON do backup: ' . json_last_error_msg()]);
            exit;
        }
        
        // Formatar dados para exibição
        $conversations = [];
        foreach ($data['conversations'] ?? [] as $convData) {
            $conv = $convData['conversation'] ?? [];
            $messages = $convData['messages'] ?? [];
            
            $conversations[] = [
                'id' => $conv['id'] ?? 0,
                'phone' => $conv['phone'] ?? '',
                'contact_name' => $conv['contact_name'] ?? 'Sem nome',
                'messages_count' => count($messages),
                'messages' => array_map(function($msg) {
                    return [
                        'id' => $msg['id'] ?? 0,
                        'from_me' => (bool) ($msg['from_me'] ?? false),
                        'message_type' => $msg['message_type'] ?? 'text',
                        'message_text' => $msg['message_text'] ?? '',
                        'media_url' => $msg['media_url'] ?? null,
                        'status' => $msg['status'] ?? '',
                        'created_at' => $msg['created_at'] ?? ''
                    ];
                }, $messages)
            ];
        }
        
        echo json_encode([
            'success' => true,
            'source' => $source,
            'backup' => [
                'id' => $backup['id'],
                'filename' => $backup['filename'],
                'created_at' => $backup['created_at'],
                'conversations_count' => $backup['conversations_count'],
                'messages_count' => $backup['messages_count'],
                'size_bytes' => $backup['size_bytes'],
                'format' => $format,
                'destination' => $backup['destination']
            ],
            'backup_info' => $data['backup_info'] ?? null,
            'conversations' => $conversations
        ]);
        
    } elseif ($format === 'csv') {
        // Para CSV, retornar as primeiras linhas como preview
        $lines = explode("\n", $content);
        $header = str_getcsv($lines[0] ?? '');
        $rows = [];
        
        for ($i = 1; $i < min(count($lines), 101); $i++) {
            if (!empty(trim($lines[$i]))) {
                $rows[] = str_getcsv($lines[$i]);
            }
        }
        
        echo json_encode([
            'success' => true,
            'source' => $source,
            'backup' => [
                'id' => $backup['id'],
                'filename' => $backup['filename'],
                'created_at' => $backup['created_at'],
                'conversations_count' => $backup['conversations_count'],
                'messages_count' => $backup['messages_count'],
                'size_bytes' => $backup['size_bytes'],
                'format' => $format,
                'destination' => $backup['destination']
            ],
            'csv_header' => $header,
            'csv_rows' => $rows,
            'total_rows' => count($lines) - 1
        ]);
        
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Formato de backup não suportado para visualização: ' . $format]);
    }
    
} catch (Exception $e) {
    error_log("[BACKUP_VIEW] Erro: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Busca conteúdo do backup da nuvem
 */
function fetchBackupFromCloud($userId, $backup) {
    global $pdo;
    
    $destination = $backup['destination'];
    $remoteId = $backup['remote_path'];
    
    if (empty($remoteId)) {
        return null;
    }
    
    // Buscar configuração do usuário
    $stmt = $pdo->prepare('SELECT * FROM backup_configs WHERE user_id = ?');
    $stmt->execute([$userId]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config || empty($config['extra_config'])) {
        return null;
    }
    
    $extra = json_decode($config['extra_config'], true);
    
    try {
        switch ($destination) {
            case 'google_drive':
                return fetchFromGoogleDrive($extra['google'] ?? [], $remoteId);
                
            case 'onedrive':
                return fetchFromOneDrive($extra['onedrive'] ?? [], $remoteId);
                
            case 'dropbox':
                return fetchFromDropbox($extra['dropbox'] ?? [], $backup['remote_url'] ?? $remoteId);
                
            case 's3':
                return fetchFromS3($extra['s3'] ?? [], $remoteId);
                
            default:
                return null;
        }
    } catch (Exception $e) {
        error_log("[BACKUP_VIEW] Erro ao buscar da nuvem ({$destination}): " . $e->getMessage());
        return null;
    }
}

/**
 * Busca arquivo do Google Drive
 */
function fetchFromGoogleDrive($credentials, $fileId) {
    if (empty($credentials['access_token'])) {
        return null;
    }
    
    require_once dirname(__DIR__) . '/includes/cloud_providers/google_drive.php';
    
    $drive = new GoogleDriveBackup($credentials);
    return $drive->downloadFile($fileId);
}

/**
 * Busca arquivo do OneDrive
 */
function fetchFromOneDrive($credentials, $fileId) {
    if (empty($credentials['access_token'])) {
        return null;
    }
    
    require_once dirname(__DIR__) . '/includes/cloud_providers/onedrive.php';
    
    $onedrive = new OneDriveBackup($credentials);
    return $onedrive->downloadFile($fileId);
}

/**
 * Busca arquivo do Dropbox
 */
function fetchFromDropbox($credentials, $path) {
    if (empty($credentials['access_token'])) {
        return null;
    }
    
    require_once dirname(__DIR__) . '/includes/cloud_providers/dropbox.php';
    
    $dropbox = new DropboxBackup($credentials);
    return $dropbox->downloadFile($path);
}

/**
 * Busca arquivo do S3
 */
function fetchFromS3($credentials, $key) {
    if (empty($credentials['access_key']) || empty($credentials['secret_key'])) {
        return null;
    }
    
    require_once dirname(__DIR__) . '/includes/cloud_providers/s3.php';
    
    $s3 = new S3Backup($credentials);
    return $s3->downloadFile($key);
}
