<?php
/**
 * Callback OAuth 2.0 - Microsoft 365
 * Recebe o código de autorização e troca por tokens
 */

session_start();
require_once 'config/database.php';
require_once 'config/oauth_config.php';
require_once 'includes/functions.php';

// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Verificar se há erro
if (isset($_GET['error'])) {
    $error = $_GET['error'];
    $errorDescription = $_GET['error_description'] ?? 'Erro desconhecido';
    
    // Redirecionar com erro
    header('Location: email_settings.php?oauth_error=' . urlencode($errorDescription));
    exit;
}

// Verificar se há código de autorização
if (!isset($_GET['code'])) {
    header('Location: email_settings.php?oauth_error=' . urlencode('Código de autorização não recebido'));
    exit;
}

$code = $_GET['code'];

// Trocar código por tokens
$tokens = exchangeCodeForTokens($code);

if (isset($tokens['error'])) {
    header('Location: email_settings.php?oauth_error=' . urlencode($tokens['error']));
    exit;
}

// Obter informações do usuário
$userInfo = getUserInfo($tokens['access_token']);
$email = $userInfo['mail'] ?? $userInfo['userPrincipalName'] ?? '';

// Salvar tokens no banco de dados
try {
    // Verificar se já existe configuração
    $stmt = $pdo->prepare("SELECT id FROM email_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $existing = $stmt->fetch();
    
    $tokenData = [
        'access_token' => $tokens['access_token'],
        'refresh_token' => $tokens['refresh_token'] ?? '',
        'expires_in' => $tokens['expires_in'] ?? 3600,
        'token_type' => $tokens['token_type'] ?? 'Bearer',
        'obtained_at' => time()
    ];
    
    if ($existing) {
        // Atualizar
        $stmt = $pdo->prepare("
            UPDATE email_settings 
            SET oauth_provider = 'microsoft',
                oauth_tokens = ?,
                from_email = ?,
                smtp_host = 'graph.microsoft.com',
                smtp_port = 443,
                smtp_encryption = 'oauth2',
                is_enabled = 1,
                updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->execute([json_encode($tokenData), $email, $user_id]);
    } else {
        // Inserir
        $stmt = $pdo->prepare("
            INSERT INTO email_settings 
            (user_id, oauth_provider, oauth_tokens, from_email, smtp_host, smtp_port, smtp_encryption, is_enabled, created_at, updated_at)
            VALUES (?, 'microsoft', ?, ?, 'graph.microsoft.com', 443, 'oauth2', 1, NOW(), NOW())
        ");
        $stmt->execute([$user_id, json_encode($tokenData), $email]);
    }
    
    // Redirecionar com sucesso
    header('Location: email_settings.php?oauth_success=1&email=' . urlencode($email));
    exit;
    
} catch (Exception $e) {
    header('Location: email_settings.php?oauth_error=' . urlencode('Erro ao salvar tokens: ' . $e->getMessage()));
    exit;
}
?>
