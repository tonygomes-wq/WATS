<?php
/**
 * Configuração OAuth 2.0 para Microsoft 365
 * WATS - Sistema de Notificações por Email
 */

// Configurações do Azure AD
define('OAUTH_CLIENT_ID', 'b67b18c8-a450-46d7-9bf7-1cba4138993d');
define('OAUTH_CLIENT_SECRET', 's~F8Q~rYxd0s9.ze1ScBbYDmPZQocE3mBF.t6aAf');
// Use 'organizations' para contas corporativas ou o Tenant ID específico
// Para encontrar seu Tenant ID: Portal Azure > Azure Active Directory > Visão Geral > ID do locatário
define('OAUTH_TENANT_ID', 'organizations'); // Permite qualquer conta corporativa Microsoft 365
define('OAUTH_REDIRECT_URI', 'https://wats.macip.com.br/oauth_callback.php');

// URLs do Microsoft OAuth
define('OAUTH_AUTHORITY', 'https://login.microsoftonline.com/' . OAUTH_TENANT_ID);
define('OAUTH_AUTHORIZE_URL', OAUTH_AUTHORITY . '/oauth2/v2.0/authorize');
define('OAUTH_TOKEN_URL', OAUTH_AUTHORITY . '/oauth2/v2.0/token');

// Escopos necessários
define('OAUTH_SCOPES', 'openid email profile offline_access https://graph.microsoft.com/Mail.Send https://graph.microsoft.com/User.Read');

/**
 * Gerar URL de autorização
 * @param string $state Token de segurança
 * @param bool $forcePrompt Forçar seleção de conta (true para nova conexão)
 */
function getAuthorizationUrl($state = null, $forcePrompt = true) {
    $state = $state ?: bin2hex(random_bytes(16));
    
    $params = [
        'client_id' => OAUTH_CLIENT_ID,
        'response_type' => 'code',
        'redirect_uri' => OAUTH_REDIRECT_URI,
        'response_mode' => 'query',
        'scope' => OAUTH_SCOPES,
        'state' => $state,
        'prompt' => $forcePrompt ? 'select_account' : 'none' // Força mostrar tela de seleção de conta
    ];
    
    return OAUTH_AUTHORIZE_URL . '?' . http_build_query($params);
}

/**
 * Trocar código de autorização por tokens
 */
function exchangeCodeForTokens($code) {
    $params = [
        'client_id' => OAUTH_CLIENT_ID,
        'client_secret' => OAUTH_CLIENT_SECRET,
        'code' => $code,
        'redirect_uri' => OAUTH_REDIRECT_URI,
        'grant_type' => 'authorization_code',
        'scope' => OAUTH_SCOPES
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, OAUTH_TOKEN_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['error' => 'Falha ao obter tokens', 'response' => $response];
    }
    
    return json_decode($response, true);
}

/**
 * Renovar access token usando refresh token
 */
function refreshAccessToken($refreshToken) {
    $params = [
        'client_id' => OAUTH_CLIENT_ID,
        'client_secret' => OAUTH_CLIENT_SECRET,
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token',
        'scope' => OAUTH_SCOPES
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, OAUTH_TOKEN_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['error' => 'Falha ao renovar token', 'response' => $response];
    }
    
    return json_decode($response, true);
}

/**
 * Enviar email usando Microsoft Graph API
 */
function sendEmailWithGraph($accessToken, $to, $subject, $body, $isHtml = true) {
    $message = [
        'message' => [
            'subject' => $subject,
            'body' => [
                'contentType' => $isHtml ? 'HTML' : 'Text',
                'content' => $body
            ],
            'toRecipients' => [
                [
                    'emailAddress' => [
                        'address' => $to
                    ]
                ]
            ]
        ],
        'saveToSentItems' => true
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://graph.microsoft.com/v1.0/me/sendMail');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'success' => $httpCode === 202,
        'http_code' => $httpCode,
        'response' => $response
    ];
}

/**
 * Obter informações do usuário
 */
function getUserInfo($accessToken) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://graph.microsoft.com/v1.0/me');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}
?>
