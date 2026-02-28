<?php
/**
 * Microsoft OAuth 2.0 Callback
 * Processa o retorno da autenticação Microsoft e obtém tokens
 */

session_start();

// Processar POST do JavaScript (prioridade para evitar HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $code = $data['code'] ?? '';
    $clientId = $data['client_id'] ?? '';
    $clientSecret = $data['client_secret'] ?? '';
    $tenantId = $data['tenant_id'] ?? '';
    $email = $data['email'] ?? '';
    
    if (empty($code) || empty($clientId) || empty($clientSecret) || empty($tenantId)) {
        echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
        exit;
    }
    
    try {
        // Trocar código por tokens
        $redirectUri = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/api/oauth/microsoft/callback.php';
        
        $tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";
        
        $postData = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
            'scope' => 'https://graph.microsoft.com/Mail.Read https://graph.microsoft.com/Mail.Send offline_access'
        ];
        
        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $error = json_decode($response, true);
            throw new Exception($error['error_description'] ?? 'Erro ao obter tokens');
        }
        
        $tokens = json_decode($response, true);
        
        if (!isset($tokens['access_token'])) {
            throw new Exception('Token de acesso não recebido');
        }
        
        // Salvar tokens na sessão para uso posterior
        $_SESSION['oauth_access_token'] = $tokens['access_token'];
        $_SESSION['oauth_refresh_token'] = $tokens['refresh_token'] ?? '';
        $_SESSION['oauth_expires_in'] = $tokens['expires_in'] ?? 3600;
        $_SESSION['oauth_email'] = $email;
        
        echo json_encode([
            'success' => true,
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? '',
            'expires_in' => $tokens['expires_in'] ?? 3600,
            'email' => $email
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    
    exit;
}

// Verificar se há código de autorização (GET request)
if (!isset($_GET['code'])) {
    $error = $_GET['error_description'] ?? $_GET['error'] ?? 'Autorização negada';
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Erro OAuth</title>
        <script>
            if (window.opener) {
                window.opener.postMessage({ type: 'oauth_error', error: '<?= htmlspecialchars($error) ?>' }, '*');
            }
            setTimeout(() => window.close(), 2000);
        </script>
    </head>
    <body>
        <p>Erro na autenticação. Esta janela será fechada automaticamente.</p>
    </body>
    </html>
    <?php
    exit;
}

$code = $_GET['code'];
$state = $_GET['state'] ?? '';

// Decodificar state para obter dados
$stateData = [];
if (!empty($state)) {
    try {
        $stateData = json_decode(base64_decode($state), true);
    } catch (Exception $e) {
        // Ignorar erro de decode
    }
}

// Recuperar dados da sessão PHP
$clientId = $_SESSION['oauth_client_id'] ?? '';
$clientSecret = $_SESSION['oauth_client_secret'] ?? '';
$tenantId = $_SESSION['oauth_tenant_id'] ?? '';
$email = $_SESSION['oauth_email'] ?? ($stateData['email'] ?? '');

// Se não estiver na sessão, mostrar erro
if (empty($clientId) || empty($clientSecret) || empty($tenantId)) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Erro OAuth</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; padding: 40px; text-align: center; }
            .error { color: #ef4444; font-size: 48px; }
        </style>
        <script>
            if (window.opener) {
                window.opener.postMessage({ 
                    type: 'oauth_error', 
                    error: 'Credenciais OAuth não encontradas na sessão. Tente novamente.'
                }, '*');
            }
            setTimeout(() => window.close(), 3000);
        </script>
    </head>
    <body>
        <div class="error">✗</div>
        <h2>Erro de Sessão</h2>
        <p>Credenciais OAuth não encontradas.</p>
        <p>Por favor, feche esta janela e tente autenticar novamente.</p>
    </body>
    </html>
    <?php
    exit;
}

// Processar OAuth com dados da sessão
try {
    $redirectUri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/api/oauth/microsoft/callback.php';
    
    $tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";
    
    $postData = [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'code' => $code,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code',
        'scope' => 'https://graph.microsoft.com/Mail.Read https://graph.microsoft.com/Mail.Send offline_access'
    ];
    
    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        $error = json_decode($response, true);
        throw new Exception($error['error_description'] ?? 'Erro ao obter tokens');
    }
    
    $tokens = json_decode($response, true);
    
    if (!isset($tokens['access_token'])) {
        throw new Exception('Token de acesso não recebido');
    }
    
    // Salvar tokens na sessão
    $_SESSION['oauth_access_token'] = $tokens['access_token'];
    $_SESSION['oauth_refresh_token'] = $tokens['refresh_token'] ?? '';
    $_SESSION['oauth_expires_in'] = $tokens['expires_in'] ?? 3600;
    $_SESSION['oauth_email'] = $email;
    
    // Sucesso - fechar popup
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Autenticação Concluída</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; padding: 40px; text-align: center; }
            .success { color: #10b981; font-size: 48px; }
        </style>
        <script>
            if (window.opener) {
                window.opener.postMessage({ 
                    type: 'oauth_success', 
                    data: {
                        success: true,
                        email: '<?= htmlspecialchars($email) ?>',
                        access_token: '<?= htmlspecialchars(substr($tokens['access_token'], 0, 20)) ?>...'
                    }
                }, '*');
            }
            setTimeout(() => window.close(), 1500);
        </script>
    </head>
    <body>
        <div class="success">✓</div>
        <h2>Autenticação Concluída!</h2>
        <p>Esta janela será fechada automaticamente...</p>
    </body>
    </html>
    <?php
    
} catch (Exception $e) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Erro na Autenticação</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; padding: 40px; text-align: center; }
            .error { color: #ef4444; font-size: 48px; }
        </style>
        <script>
            if (window.opener) {
                window.opener.postMessage({ 
                    type: 'oauth_error', 
                    error: '<?= htmlspecialchars($e->getMessage()) ?>'
                }, '*');
            }
            setTimeout(() => window.close(), 3000);
        </script>
    </head>
    <body>
        <div class="error">✗</div>
        <h2>Erro na Autenticação</h2>
        <p><?= htmlspecialchars($e->getMessage()) ?></p>
        <p>Esta janela será fechada automaticamente...</p>
    </body>
    </html>
    <?php
}
exit;
?>
