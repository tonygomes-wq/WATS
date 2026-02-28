<?php
/**
 * Iniciar fluxo de autorização OAuth 2.0 - Microsoft 365
 */

session_start();
require_once 'config/oauth_config.php';
require_once 'includes/functions.php';

// Verificar se usuário está logado
requireLogin();

// Gerar state para segurança
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

// Redirecionar para Microsoft
$authUrl = getAuthorizationUrl($state);
header('Location: ' . $authUrl);
exit;
?>
