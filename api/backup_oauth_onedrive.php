<?php
/**
 * API - OAuth OneDrive para Backup
 * 
 * GET: Redireciona para autorização Microsoft
 * GET ?code=: Callback de autorização
 * 
 * Usa credenciais por usuário
 * 
 * MACIP Tecnologia LTDA
 */

session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/cloud_providers/onedrive.php';

// Verificar autenticação
if (!isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);

// Buscar credenciais do usuário no banco
$stmt = $pdo->prepare('SELECT extra_config FROM backup_configs WHERE user_id = ? LIMIT 1');
$stmt->execute([$userId]);
$config = $stmt->fetch(PDO::FETCH_ASSOC);

$clientId = '';
$clientSecret = '';
$tenantId = 'common';

if ($config && !empty($config['extra_config'])) {
    $extra = json_decode($config['extra_config'], true);
    if (!empty($extra['onedrive'])) {
        $clientId = $extra['onedrive']['client_id'] ?? '';
        $clientSecret = $extra['onedrive']['client_secret'] ?? '';
        $tenantId = $extra['onedrive']['tenant_id'] ?? 'common';
    }
}

$redirectUri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') 
    . '://' . $_SERVER['HTTP_HOST'] . '/api/backup_oauth_onedrive.php';

if (empty($clientId) || empty($clientSecret)) {
    header('Location: /dashboard.php?page=backups&error=' . urlencode('Configure suas credenciais do OneDrive primeiro (Client ID e Client Secret)'));
    exit;
}

$onedrive = new OneDriveBackup([
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'tenant_id' => $tenantId,
    'redirect_uri' => $redirectUri
]);

// Callback de autorização
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    $state = $_GET['state'] ?? '';
    
    // Verificar state para segurança
    if ($state !== ($_SESSION['onedrive_oauth_state'] ?? '')) {
        header('Location: /dashboard.php?page=backups&error=invalid_state');
        exit;
    }
    
    $result = $onedrive->exchangeCode($code);
    
    if ($result['success']) {
        // Buscar configuração existente
        $stmt = $pdo->prepare('SELECT id, extra_config FROM backup_configs WHERE user_id = ?');
        $stmt->execute([$userId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $extra = [];
        if ($existing && !empty($existing['extra_config'])) {
            $extra = json_decode($existing['extra_config'], true) ?: [];
        }
        
        // Atualizar credenciais OneDrive com tokens
        $extra['onedrive'] = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'tenant_id' => $tenantId,
            'redirect_uri' => $redirectUri,
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'token_expiry' => $result['token_expiry'],
            'connected' => true,
            'connected_at' => date('Y-m-d H:i:s')
        ];
        
        if ($existing) {
            $stmt = $pdo->prepare('
                UPDATE backup_configs 
                SET destination = "onedrive", extra_config = ?, updated_at = NOW()
                WHERE user_id = ?
            ');
            $stmt->execute([json_encode($extra), $userId]);
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO backup_configs (user_id, destination, extra_config)
                VALUES (?, "onedrive", ?)
            ');
            $stmt->execute([$userId, json_encode($extra)]);
        }
        
        unset($_SESSION['onedrive_oauth_state']);
        
        header('Location: /dashboard.php?page=backups&success=onedrive_connected');
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
$_SESSION['onedrive_oauth_state'] = $state;

$authUrl = $onedrive->getAuthUrl($state);
header('Location: ' . $authUrl);
exit;
