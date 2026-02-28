<?php
/**
 * Security Helpers - Funções auxiliares de segurança
 */

class SecurityHelpers
{
    /**
     * Validar força da senha
     * 
     * @param string $password
     * @param int $minLength Comprimento mínimo (padrão: 8)
     * @return array ['valid' => bool, 'errors' => array, 'strength' => string]
     */
    public static function validatePasswordStrength(string $password, int $minLength = 8): array
    {
        $errors = [];
        $strength = 'weak';

        // Comprimento mínimo
        if (strlen($password) < $minLength) {
            $errors[] = "A senha deve ter no mínimo {$minLength} caracteres.";
        }

        // Verificar complexidade
        $hasLower = preg_match('/[a-z]/', $password);
        $hasUpper = preg_match('/[A-Z]/', $password);
        $hasNumber = preg_match('/[0-9]/', $password);
        $hasSpecial = preg_match('/[^a-zA-Z0-9]/', $password);

        $complexityScore = $hasLower + $hasUpper + $hasNumber + $hasSpecial;

        // Requisitos mínimos
        if (!$hasLower && !$hasUpper) {
            $errors[] = "A senha deve conter letras.";
        }

        if (!$hasNumber) {
            $errors[] = "A senha deve conter pelo menos um número.";
        }

        // Calcular força
        if (strlen($password) >= 12 && $complexityScore >= 3) {
            $strength = 'strong';
        } elseif (strlen($password) >= 10 && $complexityScore >= 2) {
            $strength = 'medium';
        }

        // Verificar senhas comuns
        $commonPasswords = [
            '12345678', 'password', 'senha123', 'admin123', 'qwerty123',
            '123456789', 'abc123456', 'password1', 'senha1234'
        ];

        if (in_array(strtolower($password), $commonPasswords)) {
            $errors[] = "Esta senha é muito comum. Escolha uma senha mais segura.";
            $strength = 'weak';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'strength' => $strength,
            'score' => $complexityScore
        ];
    }

    /**
     * Sanitizar entrada de forma segura
     * 
     * @param mixed $data
     * @param string $type Tipo de sanitização (string, email, int, float, url, html)
     * @return mixed
     */
    public static function sanitize($data, string $type = 'string')
    {
        if (is_array($data)) {
            return array_map(function ($item) use ($type) {
                return self::sanitize($item, $type);
            }, $data);
        }

        switch ($type) {
            case 'email':
                return filter_var($data, FILTER_SANITIZE_EMAIL);

            case 'int':
                return filter_var($data, FILTER_SANITIZE_NUMBER_INT);

            case 'float':
                return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

            case 'url':
                return filter_var($data, FILTER_SANITIZE_URL);

            case 'html':
                // Permitir HTML seguro (usar biblioteca como HTML Purifier em produção)
                return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            case 'string':
            default:
                return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }

    /**
     * Validar formato de token (hexadecimal de 64 caracteres)
     */
    public static function validateTokenFormat(string $token): bool
    {
        return preg_match('/^[a-f0-9]{64}$/i', $token) === 1;
    }

    /**
     * Gerar hash seguro para logs (não reversível)
     */
    public static function hashForLog(string $data): string
    {
        return substr(hash('sha256', $data), 0, 12);
    }

    /**
     * Verificar se IP está em lista de bloqueio
     */
    public static function isIPBlocked(string $ip, PDO $pdo): bool
    {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM ip_blacklist 
                WHERE ip_address = ? AND (expires_at IS NULL OR expires_at > NOW())
            ");
            $stmt->execute([$ip]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log("[Security] Erro ao verificar IP bloqueado: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Adicionar IP à lista de bloqueio
     */
    public static function blockIP(string $ip, PDO $pdo, int $durationMinutes = 60, string $reason = 'Suspicious activity'): bool
    {
        try {
            // Criar tabela se não existir
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS ip_blacklist (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ip_address VARCHAR(45) NOT NULL,
                    reason VARCHAR(255),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME,
                    INDEX idx_ip (ip_address),
                    INDEX idx_expires (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$durationMinutes} minutes"));

            $stmt = $pdo->prepare("
                INSERT INTO ip_blacklist (ip_address, reason, expires_at)
                VALUES (?, ?, ?)
            ");
            return $stmt->execute([$ip, $reason, $expiresAt]);
        } catch (Exception $e) {
            error_log("[Security] Erro ao bloquear IP: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Adicionar headers de segurança
     */
    public static function setSecurityHeaders(): void
    {
        // Prevenir clickjacking
        header('X-Frame-Options: SAMEORIGIN');

        // Prevenir MIME sniffing
        header('X-Content-Type-Options: nosniff');

        // XSS Protection (legacy, mas ainda útil)
        header('X-XSS-Protection: 1; mode=block');

        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Permissions Policy (Feature Policy)
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

        // Content Security Policy (básico - ajustar conforme necessário)
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://unpkg.com https://cdnjs.cloudflare.com",
            "style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com",
            "img-src 'self' data: https:",
            "connect-src 'self'",
            "frame-ancestors 'self'"
        ];
        header('Content-Security-Policy: ' . implode('; ', $csp));
    }

    /**
     * Validar email de forma robusta
     */
    public static function validateEmail(string $email): bool
    {
        // Validação básica
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Verificar formato
        if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
            return false;
        }

        // Verificar domínio (opcional - pode ser lento)
        // $domain = substr(strrchr($email, "@"), 1);
        // return checkdnsrr($domain, 'MX');

        return true;
    }

    /**
     * Validar CPF
     */
    public static function validateCPF(string $cpf): bool
    {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);

        if (strlen($cpf) != 11) {
            return false;
        }

        // Verificar se todos os dígitos são iguais
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        // Validar dígitos verificadores
        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validar CNPJ
     */
    public static function validateCNPJ(string $cnpj): bool
    {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);

        if (strlen($cnpj) != 14) {
            return false;
        }

        // Verificar se todos os dígitos são iguais
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        // Validar dígitos verificadores
        $length = strlen($cnpj) - 2;
        $numbers = substr($cnpj, 0, $length);
        $digits = substr($cnpj, $length);
        $sum = 0;
        $pos = $length - 7;

        for ($i = $length; $i >= 1; $i--) {
            $sum += $numbers[$length - $i] * $pos--;
            if ($pos < 2) {
                $pos = 9;
            }
        }

        $result = $sum % 11 < 2 ? 0 : 11 - $sum % 11;

        if ($result != $digits[0]) {
            return false;
        }

        $length = $length + 1;
        $numbers = substr($cnpj, 0, $length);
        $sum = 0;
        $pos = $length - 7;

        for ($i = $length; $i >= 1; $i--) {
            $sum += $numbers[$length - $i] * $pos--;
            if ($pos < 2) {
                $pos = 9;
            }
        }

        $result = $sum % 11 < 2 ? 0 : 11 - $sum % 11;

        return $result == $digits[1];
    }

    /**
     * Gerar senha aleatória segura
     */
    public static function generateSecurePassword(int $length = 12): string
    {
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers = '0123456789';
        $special = '!@#$%^&*()_+-=[]{}|;:,.<>?';

        $all = $lowercase . $uppercase . $numbers . $special;

        $password = '';
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        for ($i = 4; $i < $length; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        return str_shuffle($password);
    }
}

