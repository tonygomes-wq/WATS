<?php
/**
 * OAuth Callback para Microsoft Teams Graph API
 * Recebe o código de autorização e troca por access token
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/channels/TeamsGraphAPI.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$teamsAPI = new TeamsGraphAPI($pdo, $userId);

// Verificar se há erro
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
    $errorDescription = htmlspecialchars($_GET['error_description'] ?? 'Erro desconhecido');
    
    $_SESSION['teams_auth_error'] = "Erro na autenticação: {$error} - {$errorDescription}";
    header('Location: teams_graph_config.php');
    exit;
}

// Verificar se há código de autorização
if (!isset($_GET['code'])) {
    $_SESSION['teams_auth_error'] = 'Código de autorização não recebido';
    header('Location: teams_graph_config.php');
    exit;
}

$code = $_GET['code'];
$redirectUri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
               "://{$_SERVER['HTTP_HOST']}/teams_oauth_callback.php";

// Trocar código por token
$result = $teamsAPI->exchangeCodeForToken($code, $redirectUri);

if ($result['success']) {
    $_SESSION['teams_auth_success'] = 'Autenticação realizada com sucesso!';
} else {
    $_SESSION['teams_auth_error'] = $result['error'] ?? 'Erro ao obter token de acesso';
}

header('Location: teams_graph_config.php');
exit;
