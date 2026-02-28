<?php
/**
 * API - OAuth Google Drive para Backup
 * 
 * GET: Redireciona para autorização Google
 * GET ?code=: Callback de autorização
 * 
 * Usa credenciais por usuário (não do sistema)
 * 
 * MACIP Tecnologia LTDA
 */

session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/cloud_providers/google_drive.php';

// Verificar autenticação
if (!isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userType = $_SESSION['user_type'] ?? 'user';

// Buscar credenciais do usuário no banco
$stmt = $pdo->prepare('SELECT extra_config FROM backup_configs WHERE user_id = ? LIMIT 1');
$stmt->execute([$userId]);
$config = $stmt->fetch(PDO::FETCH_ASSOC);

$googleClientId = '';
$googleClientSecret = '';

if ($config && !empty($config['extra_config'])) {
    $extra = json_decode($config['extra_config'], true);
    if (!empty($extra['google'])) {
        $googleClientId = $extra['google']['client_id'] ?? '';
        $googleClientSecret = $extra['google']['client_secret'] ?? '';
    }
}

// Fallback para variáveis de ambiente (compatibilidade)
if (empty($googleClientId)) {
    $googleClientId = env('GOOGLE_CLIENT_ID', '');
}
if (empty($googleClientSecret)) {
    $googleClientSecret = env('GOOGLE_CLIENT_SECRET', '');
}

$googleRedirectUri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') 
    . '://' . $_SERVER['HTTP_HOST'] . '/api/backup_oauth_google.php';

if (empty($googleClientId) || empty($googleClientSecret)) {
    // Redirecionar de volta com erro
    header('Location: /dashboard.php?page=backups&error=' . urlencode('Configure suas credenciais do Google Drive primeiro (Client ID e Client Secret)'));
    exit;
}

$drive = new GoogleDriveBackup([
    'client_id' => $googleClientId,
    'client_secret' => $googleClientSecret,
    'redirect_uri' => $googleRedirectUri
]);

// Callback de autorização
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    $state = $_GET['state'] ?? '';
    
    // Verificar state para segurança
    if ($state !== ($_SESSION['google_oauth_state'] ?? '')) {
        header('Location: /dashboard.php?page=backups&error=invalid_state');
        exit;
    }
    
    $result = $drive->exchangeCode($code);
    
    if ($result['success']) {
        // Buscar configuração existente
        $stmt = $pdo->prepare('SELECT id, extra_config FROM backup_configs WHERE user_id = ?');
        $stmt->execute([$userId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Preparar credenciais para salvar
        $extra = [];
        if ($existing && !empty($existing['extra_config'])) {
            $extra = json_decode($existing['extra_config'], true) ?: [];
        }
        
        // Atualizar credenciais Google com tokens
        $extra['google'] = [
            'client_id' => $googleClientId,
            'client_secret' => $googleClientSecret,
            'redirect_uri' => $googleRedirectUri,
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'token_expiry' => $result['token_expiry'],
            'connected' => true,
            'connected_at' => date('Y-m-d H:i:s')
        ];
        
        if ($existing) {
            $stmt = $pdo->prepare('
                UPDATE backup_configs 
                SET destination = "google_drive", extra_config = ?, updated_at = NOW()
                WHERE user_id = ?
            ');
            $stmt->execute([json_encode($extra), $userId]);
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO backup_configs (user_id, destination, extra_config)
                VALUES (?, "google_drive", ?)
            ');
            $stmt->execute([$userId, json_encode($extra)]);
        }
        
        // Limpar state da sessão
        unset($_SESSION['google_oauth_state']);
        
        header('Location: /dashboard.php?page=backups&success=google_connected');
        exit;
    } else {
        header('Location: /dashboard.php?page=backups&error=' . urlencode($result['error']));
        exit;
    }
}

// Erro de autorização
if (isset($_GET['error'])) {
    header('Location: /dashboard.php?page=backups&error=' . urlencode($_GET['error_description'] ?? $_GET['error']));
    exit;
}

// Iniciar fluxo de autorização
$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

$authUrl = $drive->getAuthUrl($state);
header('Location: ' . $authUrl);
exit;
