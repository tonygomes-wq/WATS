<?php
/**
 * API para salvar configuração do canal Email
 */

// Habilitar exibição de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Capturar erros fatais
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Erro fatal: ' . $error['message'] . ' em ' . $error['file'] . ':' . $error['line']
        ]);
    }
});

session_start();
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../../config/database.php';
    require_once __DIR__ . '/../../../includes/functions.php';
    require_once __DIR__ . '/../../../includes/channels/EmailChannel.php';
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao carregar dependências: ' . $e->getMessage()
    ]);
    exit;
}

// Verificar autenticação
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

// Permissões: qualquer usuário logado pode configurar canais
// (ajuste depois se necessário para restringir apenas a admin/supervisor)

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$authMethod = $data['auth_method'] ?? 'password';
$email = $data['email'] ?? '';

// Campos específicos por método de autenticação
if ($authMethod === 'oauth') {
    // OAuth 2.0 (Microsoft)
    $oauthClientId = $data['oauth_client_id'] ?? '';
    $oauthClientSecret = $data['oauth_client_secret'] ?? '';
    $oauthTenantId = $data['oauth_tenant_id'] ?? '';
    
    if (empty($email) || empty($oauthClientId) || empty($oauthClientSecret) || empty($oauthTenantId)) {
        echo json_encode([
            'success' => false,
            'error' => 'Email e credenciais OAuth são obrigatórios'
        ]);
        exit;
    }
    
    // Para OAuth, usar Graph API endpoints
    $imapHost = 'graph.microsoft.com';
    $imapPort = 443;
    $imapEncryption = 'tls';
    $smtpHost = 'graph.microsoft.com';
    $smtpPort = 443;
    $smtpEncryption = 'tls';
    $password = ''; // Não usa senha com OAuth
    $fromName = $data['from_name'] ?? '';
    
} else {
    // Autenticação tradicional (senha/app password)
    $password = $data['password'] ?? '';
    $imapHost = $data['imap_host'] ?? '';
    $imapPort = $data['imap_port'] ?? 993;
    $imapEncryption = $data['imap_encryption'] ?? 'ssl';
    $smtpHost = $data['smtp_host'] ?? '';
    $smtpPort = $data['smtp_port'] ?? 587;
    $smtpEncryption = $data['smtp_encryption'] ?? 'tls';
    $fromName = $data['from_name'] ?? '';
    
    // Validar campos obrigatórios
    if (empty($email) || empty($password) || empty($imapHost) || empty($smtpHost)) {
        echo json_encode([
            'success' => false,
            'error' => 'Email, senha, host IMAP e host SMTP são obrigatórios'
        ]);
        exit;
    }
}

try {
    // Verificar se tabela channel_email existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'channel_email'");
    if ($stmt->rowCount() === 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Tabela channel_email não existe. Execute a migration: database/migrations/create_channel_email_table.sql'
        ]);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Verificar se já existe um canal Email
    $stmt = $pdo->prepare("SELECT id FROM channels WHERE channel_type = 'email' LIMIT 1");
    $stmt->execute();
    $existingChannel = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingChannel) {
        // Atualizar canal existente
        $channelId = $existingChannel['id'];
        
        $stmt = $pdo->prepare("
            UPDATE channels 
            SET name = ?, status = 'active', updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute(['Email - ' . $email, $channelId]);
        
        // Verificar se já existe configuração em channel_email
        $stmt = $pdo->prepare("SELECT id FROM channel_email WHERE channel_id = ?");
        $stmt->execute([$channelId]);
        $existingEmailConfig = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Atualizar configurações do Email
        if ($authMethod === 'oauth') {
            // Recuperar tokens da sessão
            $oauthAccessToken = $_SESSION['oauth_access_token'] ?? null;
            $oauthRefreshToken = $_SESSION['oauth_refresh_token'] ?? null;
            $oauthExpiresIn = $_SESSION['oauth_expires_in'] ?? 3600;
            $oauthExpiresAt = date('Y-m-d H:i:s', time() + $oauthExpiresIn);
            
            if ($existingEmailConfig) {
                // UPDATE
                $stmt = $pdo->prepare("
                    UPDATE channel_email 
                    SET email = ?, auth_method = 'oauth', 
                        oauth_client_id = ?, oauth_client_secret = ?, oauth_tenant_id = ?,
                        oauth_access_token = ?, oauth_refresh_token = ?, oauth_token_expires_at = ?,
                        imap_host = ?, imap_port = ?, imap_encryption = ?,
                        smtp_host = ?, smtp_port = ?, smtp_encryption = ?, 
                        from_name = ?, updated_at = NOW()
                    WHERE channel_id = ?
                ");
                $stmt->execute([
                    $email, $oauthClientId, $oauthClientSecret, $oauthTenantId,
                    $oauthAccessToken, $oauthRefreshToken, $oauthExpiresAt,
                    $imapHost, $imapPort, $imapEncryption,
                    $smtpHost, $smtpPort, $smtpEncryption, $fromName, $channelId
                ]);
            } else {
                // INSERT
                $stmt = $pdo->prepare("
                    INSERT INTO channel_email (
                        channel_id, email, auth_method, 
                        oauth_client_id, oauth_client_secret, oauth_tenant_id,
                        oauth_access_token, oauth_refresh_token, oauth_token_expires_at,
                        imap_host, imap_port, imap_encryption,
                        smtp_host, smtp_port, smtp_encryption, from_name, 
                        created_at, updated_at
                    )
                    VALUES (?, ?, 'oauth', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $channelId, $email, $oauthClientId, $oauthClientSecret, $oauthTenantId,
                    $oauthAccessToken, $oauthRefreshToken, $oauthExpiresAt,
                    $imapHost, $imapPort, $imapEncryption,
                    $smtpHost, $smtpPort, $smtpEncryption, $fromName
                ]);
            }
        } else {
            if ($existingEmailConfig) {
                // UPDATE
                $stmt = $pdo->prepare("
                    UPDATE channel_email 
                    SET email = ?, password = ?, auth_method = 'password',
                        imap_host = ?, imap_port = ?, imap_encryption = ?,
                        smtp_host = ?, smtp_port = ?, smtp_encryption = ?, 
                        from_name = ?, updated_at = NOW()
                    WHERE channel_id = ?
                ");
                $stmt->execute([
                    $email, $password, $imapHost, $imapPort, $imapEncryption,
                    $smtpHost, $smtpPort, $smtpEncryption, $fromName, $channelId
                ]);
            } else {
                // INSERT
                $stmt = $pdo->prepare("
                    INSERT INTO channel_email (
                        channel_id, email, password, auth_method,
                        imap_host, imap_port, imap_encryption,
                        smtp_host, smtp_port, smtp_encryption, from_name, 
                        created_at, updated_at
                    )
                    VALUES (?, ?, ?, 'password', ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $channelId, $email, $password, $imapHost, $imapPort, $imapEncryption,
                    $smtpHost, $smtpPort, $smtpEncryption, $fromName
                ]);
            }
        }
        
    } else {
        // Criar novo canal
        $userId = $_SESSION['user_id'] ?? 0;
        
        $stmt = $pdo->prepare("
            INSERT INTO channels (user_id, channel_type, name, status, created_at, updated_at)
            VALUES (?, 'email', ?, 'active', NOW(), NOW())
        ");
        $stmt->execute([$userId, 'Email - ' . $email]);
        $channelId = $pdo->lastInsertId();
        
        // Inserir configurações do Email
        if ($authMethod === 'oauth') {
            // Recuperar tokens da sessão
            $oauthAccessToken = $_SESSION['oauth_access_token'] ?? null;
            $oauthRefreshToken = $_SESSION['oauth_refresh_token'] ?? null;
            $oauthExpiresIn = $_SESSION['oauth_expires_in'] ?? 3600;
            $oauthExpiresAt = date('Y-m-d H:i:s', time() + $oauthExpiresIn);
            
            $stmt = $pdo->prepare("
                INSERT INTO channel_email (
                    channel_id, email, auth_method, 
                    oauth_client_id, oauth_client_secret, oauth_tenant_id,
                    oauth_access_token, oauth_refresh_token, oauth_token_expires_at,
                    imap_host, imap_port, imap_encryption,
                    smtp_host, smtp_port, smtp_encryption, from_name, 
                    created_at, updated_at
                )
                VALUES (?, ?, 'oauth', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $channelId, $email, $oauthClientId, $oauthClientSecret, $oauthTenantId,
                $oauthAccessToken, $oauthRefreshToken, $oauthExpiresAt,
                $imapHost, $imapPort, $imapEncryption,
                $smtpHost, $smtpPort, $smtpEncryption, $fromName
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO channel_email (
                    channel_id, email, password, auth_method,
                    imap_host, imap_port, imap_encryption,
                    smtp_host, smtp_port, smtp_encryption, from_name, 
                    created_at, updated_at
                )
                VALUES (?, ?, ?, 'password', ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $channelId, $email, $password, $imapHost, $imapPort, $imapEncryption,
                $smtpHost, $smtpPort, $smtpEncryption, $fromName
            ]);
        }
    }
    
    // Commit da transação
    $pdo->commit();
    
    // Para OAuth, não validar credenciais agora (validação será feita ao buscar emails)
    if ($authMethod === 'oauth') {
        echo json_encode([
            'success' => true,
            'message' => 'Canal Email (OAuth) configurado com sucesso',
            'channel_id' => $channelId,
            'email' => $email,
            'auth_method' => 'oauth'
        ]);
    } else {
        // Para senha, tentar validar credenciais (opcional)
        try {
            // Validação será feita ao buscar emails
            // Por enquanto, apenas salvar a configuração
            echo json_encode([
                'success' => true,
                'message' => 'Canal Email configurado com sucesso',
                'channel_id' => $channelId,
                'email' => $email,
                'auth_method' => 'password'
            ]);
        } catch (Exception $e) {
            // Mesmo com erro de validação, manter configuração salva
            echo json_encode([
                'success' => true,
                'message' => 'Canal Email salvo (validação pendente)',
                'channel_id' => $channelId,
                'email' => $email,
                'warning' => 'Não foi possível validar credenciais: ' . $e->getMessage()
            ]);
        }
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao salvar configuração: ' . $e->getMessage()
    ]);
}
