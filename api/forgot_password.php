<?php
/**
 * API para Recuperação de Senha
 * Gera token e envia email com link de redefinição
 * 
 * Suporta envio via:
 * - SMTP tradicional (Gmail, Outlook, etc.)
 * - OAuth Microsoft 365
 * - Fallback para mail() nativo
 */

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/RateLimiter.php';
require_once '../includes/SecurityHelpers.php';

// Adicionar security headers
SecurityHelpers::setSecurityHeaders();

// Rate limiting
$rateLimiter = new RateLimiter($pdo);
$clientIP = RateLimiter::getClientIP();

// Rate limit: 3 tentativas por hora por IP
$ipCheck = $rateLimiter->check($clientIP, 'forgot_password', 3, 60, 120);
if (!$ipCheck['allowed']) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Muitas solicitações. Tente novamente mais tarde.'
    ]);
    exit;
}

// Receber dados
$input = json_decode(file_get_contents('php://input'), true);
$email = sanitize($input['email'] ?? '');

// Validar email
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'Por favor, informe um email válido.'
    ]);
    exit;
}

// Rate limit por email também
$emailCheck = $rateLimiter->check($email, 'forgot_password', 3, 60, 120);
if (!$emailCheck['allowed']) {
    // Não revelar que atingiu limite
    echo json_encode([
        'success' => true,
        'message' => 'Se o email estiver cadastrado, você receberá um link para redefinir sua senha.'
    ]);
    exit;
}

try {
    // Verificar se usuário existe
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Por segurança, não revelar se o email existe ou não
        echo json_encode([
            'success' => true,
            'message' => 'Se o email estiver cadastrado, você receberá um link para redefinir sua senha.'
        ]);
        exit;
    }
    
    // Verificar/criar tabela password_resets se não existir
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            used TINYINT(1) DEFAULT 0,
            INDEX idx_token (token),
            INDEX idx_user_id (user_id),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Exception $e) {
        error_log("[FORGOT_PASSWORD] Erro ao criar tabela password_resets: " . $e->getMessage());
    }
    
    // Gerar token único
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+30 minutes')); // Reduzido para 30 minutos
    
    // Salvar token no banco
    $stmt = $pdo->prepare("
        INSERT INTO password_resets (user_id, token, expires_at, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$user['id'], $token, $expires]);
    
    // Registrar tentativas
    $rateLimiter->record($clientIP, 'forgot_password');
    $rateLimiter->record($email, 'forgot_password');
    
    // Gerar link de redefinição
    $resetLink = APP_URL . '/reset_password.php?token=' . $token;
    
    // Montar conteúdo do email
    $subject = 'Redefinição de Senha - ' . SITE_NAME;
    $message = buildPasswordResetEmail($user['name'], $resetLink);
    
    // Tentar enviar email usando EmailSender (SMTP/OAuth)
    $emailSent = sendPasswordResetEmail($pdo, $user['email'], $subject, $message);
    
    if ($emailSent) {
        // Registrar na auditoria (se disponível)
        if (file_exists('../includes/AuditLogger.php')) {
            require_once '../includes/AuditLogger.php';
            $audit = new AuditLogger();
            $audit->log('password_reset_requested', 'user', $user['id'], null, ['email_hash' => SecurityHelpers::hashForLog($email)], $user['id']);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Email enviado com sucesso! Verifique sua caixa de entrada e spam.'
        ]);
    } else {
        // Log sanitizado
        error_log("[FORGOT_PASSWORD] Falha ao enviar email (hash: " . SecurityHelpers::hashForLog($email) . ")");
        
        echo json_encode([
            'success' => false,
            'message' => 'Não foi possível enviar o email. Por favor, entre em contato com o suporte ou tente novamente mais tarde.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Erro na recuperação de senha: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar solicitação. Tente novamente.'
    ]);
}

/**
 * Envia email de recuperação de senha
 * Tenta usar EmailSender (SMTP/OAuth), com fallback para mail()
 */
function sendPasswordResetEmail($pdo, $to, $subject, $body) {
    // 1. Tentar usar configuração de email do sistema (admin)
    $adminEmailSent = tryAdminEmailSender($pdo, $to, $subject, $body);
    if ($adminEmailSent) {
        return true;
    }
    
    // 2. Fallback: usar mail() nativo do PHP
    return tryNativeMail($to, $subject, $body);
}

/**
 * Tenta enviar usando configuração de email do admin
 */
function tryAdminEmailSender($pdo, $to, $subject, $body) {
    try {
        // Verificar se a tabela email_settings existe
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'email_settings'");
        if ($tableCheck->rowCount() === 0) {
            error_log("[FORGOT_PASSWORD] Tabela email_settings não existe");
            return false;
        }
        
        // Buscar primeiro admin com email configurado
        $stmt = $pdo->prepare("
            SELECT es.*, u.id as user_id
            FROM email_settings es
            JOIN users u ON es.user_id = u.id
            WHERE u.is_admin = 1 AND es.is_enabled = 1
            ORDER BY u.id ASC
            LIMIT 1
        ");
        $stmt->execute();
        $adminSettings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$adminSettings) {
            error_log("[FORGOT_PASSWORD] Nenhum admin com email configurado");
            return false;
        }
        
        // Verificar se EmailSender existe
        if (!file_exists('../includes/email_sender.php')) {
            error_log("[FORGOT_PASSWORD] Arquivo email_sender.php não encontrado");
            return false;
        }
        
        // Carregar EmailSender
        require_once '../includes/email_sender.php';
        
        $emailSender = new EmailSender($pdo, $adminSettings['user_id']);
        $result = $emailSender->send($to, $subject, $body, true);
        
        if ($result['success']) {
            error_log("[FORGOT_PASSWORD] Email enviado via EmailSender (admin ID: {$adminSettings['user_id']})");
            return true;
        } else {
            error_log("[FORGOT_PASSWORD] Falha EmailSender: " . ($result['error'] ?? 'Erro desconhecido'));
            return false;
        }
        
    } catch (Exception $e) {
        error_log("[FORGOT_PASSWORD] Exceção EmailSender: " . $e->getMessage());
        return false;
    }
}

/**
 * Fallback: envia usando mail() nativo
 */
function tryNativeMail($to, $subject, $body) {
    try {
        // Verificar se a função mail() está disponível
        if (!function_exists('mail')) {
            error_log("[FORGOT_PASSWORD] Função mail() não disponível no servidor");
            return false;
        }
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        
        // Usar constantes se definidas, senão usar valores padrão
        $fromEmail = defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'noreply@' . parse_url(APP_URL, PHP_URL_HOST);
        $siteName = defined('SITE_NAME') ? SITE_NAME : 'Sistema';
        
        $headers .= "From: {$siteName} <{$fromEmail}>\r\n";
        $headers .= "Reply-To: {$fromEmail}\r\n";
        
        $sent = @mail($to, $subject, $body, $headers);
        
        if ($sent) {
            error_log("[FORGOT_PASSWORD] Email enviado via mail() nativo para: {$to}");
        } else {
            error_log("[FORGOT_PASSWORD] Falha ao enviar via mail() nativo para: {$to}");
        }
        
        return $sent;
        
    } catch (Exception $e) {
        error_log("[FORGOT_PASSWORD] Exceção mail(): " . $e->getMessage());
        return false;
    }
}

/**
 * Monta o HTML do email de recuperação de senha
 */
function buildPasswordResetEmail($userName, $resetLink) {
    $siteName = SITE_NAME;
    $year = date('Y');
    
    return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; background: #16a34a; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
                .warning { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 Redefinição de Senha</h1>
                </div>
                <div class='content'>
                    <p>Olá <strong>" . htmlspecialchars($userName) . "</strong>,</p>
                    
                    <p>Recebemos uma solicitação para redefinir a senha da sua conta no <strong>{$siteName}</strong>.</p>
                    
                    <p>Clique no botão abaixo para criar uma nova senha:</p>
                    
                    <p style='text-align: center;'>
                        <a href='{$resetLink}' class='button'>Redefinir Minha Senha</a>
                    </p>
                    
                    <p>Ou copie e cole este link no seu navegador:</p>
                    <p style='background: #e5e7eb; padding: 10px; border-radius: 5px; word-break: break-all; font-size: 12px;'>
                        {$resetLink}
                    </p>
                    
                    <div class='warning'>
                        <strong>⚠️ Importante:</strong>
                        <ul>
                            <li>Este link expira em <strong>30 minutos</strong></li>
                            <li>Se você não solicitou esta redefinição, ignore este email</li>
                            <li>Sua senha atual permanecerá ativa até que você crie uma nova</li>
                        </ul>
                    </div>
                    
                    <p>Se você tiver alguma dúvida, entre em contato com nosso suporte.</p>
                    
                    <p>Atenciosamente,<br>
                    <strong>Equipe {$siteName}</strong></p>
                </div>
                <div class='footer'>
                    <p>Este é um email automático, por favor não responda.</p>
                    <p>&copy; {$year} {$siteName}. Todos os direitos reservados.</p>
                </div>
            </div>
        </body>
        </html>
    ";
}
