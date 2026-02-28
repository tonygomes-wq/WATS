<?php
/**
 * API para configuração do Microsoft Teams Graph API
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/channels/TeamsGraphAPI.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];
$teamsAPI = new TeamsGraphAPI($pdo, $userId);

$method = $_SERVER['REQUEST_METHOD'];

// Log para debug
error_log("Teams Config - Method: " . $method);
error_log("Teams Config - GET: " . print_r($_GET, true));
error_log("Teams Config - POST: " . print_r($_POST, true));

// Tentar pegar action do JSON body se não vier em GET/POST
$rawInput = file_get_contents('php://input');
$GLOBALS['jsonInput'] = json_decode($rawInput, true);
$action = $_GET['action'] ?? ($_POST['action'] ?? ($GLOBALS['jsonInput']['action'] ?? null));

error_log("Teams Config - Action: " . ($action ?? 'NULL'));
error_log("Teams Config - Raw Input: " . $rawInput);

try {
    switch ($action) {
        case 'save_credentials':
            handleSaveCredentials($teamsAPI);
            break;
            
        case 'authorize':
            handleAuthorize($teamsAPI);
            break;
            
        case 'admin_consent':
            handleAdminConsent($teamsAPI);
            break;
            
        case 'disconnect':
            handleDisconnect($teamsAPI);
            break;
            
        case 'get_user_info':
            handleGetUserInfo($teamsAPI);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ação inválida']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

exit;

/**
 * Salvar credenciais do Azure AD
 */
function handleSaveCredentials($teamsAPI) {
    $jsonInput = $GLOBALS['jsonInput'] ?? null;
    
    // Log para debug
    error_log("Teams Config - handleSaveCredentials - Input: " . print_r($jsonInput, true));
    
    if (!$jsonInput || json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'JSON inválido: ' . json_last_error_msg()]);
        return;
    }
    
    $clientId = trim($jsonInput['client_id'] ?? '');
    $clientSecret = trim($jsonInput['client_secret'] ?? '');
    $tenantId = trim($jsonInput['tenant_id'] ?? '');
    
    if (empty($clientId) || empty($tenantId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Client ID e Tenant ID são obrigatórios']);
        return;
    }
    
    // Se client_secret estiver vazio, não atualizar (manter o atual)
    if (empty($clientSecret)) {
        global $pdo, $userId;
        $stmt = $pdo->prepare("
            UPDATE users 
            SET 
                teams_client_id = ?,
                teams_tenant_id = ?
            WHERE id = ?
        ");
        $result = $stmt->execute([$clientId, $tenantId, $userId]);
    } else {
        $result = $teamsAPI->saveCredentials($clientId, $clientSecret, $tenantId);
    }
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Credenciais salvas com sucesso']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao salvar credenciais']);
    }
}

/**
 * Redirecionar para autorização OAuth
 */
function handleAuthorize($teamsAPI) {
    $redirectUri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                   "://{$_SERVER['HTTP_HOST']}/teams_oauth_callback.php";
    
    $authUrl = $teamsAPI->getAuthorizationUrl($redirectUri);
    header('Location: ' . $authUrl);
    exit;
}

/**
 * Redirecionar para consentimento de administrador
 */
function handleAdminConsent($teamsAPI) {
    $redirectUri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                   "://{$_SERVER['HTTP_HOST']}/teams_oauth_callback.php";
    
    $adminConsentUrl = $teamsAPI->getAdminConsentUrl($redirectUri);
    header('Location: ' . $adminConsentUrl);
    exit;
}

/**
 * Desconectar conta
 */
function handleDisconnect($teamsAPI) {
    $result = $teamsAPI->disconnect();
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Desconectado com sucesso']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao desconectar']);
    }
}

/**
 * Obter informações do usuário autenticado
 */
function handleGetUserInfo($teamsAPI) {
    if (!$teamsAPI->isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Não autenticado']);
        return;
    }
    
    $result = $teamsAPI->getMe();
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'user' => $result['data']
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Erro ao obter informações do usuário'
        ]);
    }
}
