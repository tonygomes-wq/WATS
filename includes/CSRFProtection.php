<?php
/**
 * CSRF Protection - Proteção contra Cross-Site Request Forgery
 * Gera e valida tokens CSRF para formulários
 */

class CSRFProtection
{
    private const TOKEN_NAME = 'csrf_token';
    private const TOKEN_TIME_NAME = 'csrf_token_time';
    private const TOKEN_LIFETIME = 3600; // 1 hora

    /**
     * Gerar token CSRF
     */
    public static function generateToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION[self::TOKEN_NAME] = $token;
        $_SESSION[self::TOKEN_TIME_NAME] = time();

        return $token;
    }

    /**
     * Obter token atual (ou gerar se não existir)
     */
    public static function getToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION[self::TOKEN_NAME]) || self::isTokenExpired()) {
            return self::generateToken();
        }

        return $_SESSION[self::TOKEN_NAME];
    }

    /**
     * Validar token CSRF
     */
    public static function validateToken(?string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Token não fornecido
        if (empty($token)) {
            return false;
        }

        // Token não existe na sessão
        if (empty($_SESSION[self::TOKEN_NAME])) {
            return false;
        }

        // Token expirado
        if (self::isTokenExpired()) {
            self::clearToken();
            return false;
        }

        // Comparação segura contra timing attacks
        $valid = hash_equals($_SESSION[self::TOKEN_NAME], $token);

        // Regenerar token após uso (one-time token)
        if ($valid) {
            self::generateToken();
        }

        return $valid;
    }

    /**
     * Verificar se token expirou
     */
    private static function isTokenExpired(): bool
    {
        if (empty($_SESSION[self::TOKEN_TIME_NAME])) {
            return true;
        }

        return (time() - $_SESSION[self::TOKEN_TIME_NAME]) > self::TOKEN_LIFETIME;
    }

    /**
     * Limpar token da sessão
     */
    public static function clearToken(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION[self::TOKEN_NAME]);
            unset($_SESSION[self::TOKEN_TIME_NAME]);
        }
    }

    /**
     * Gerar campo hidden HTML com token
     */
    public static function getTokenField(): string
    {
        $token = self::getToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Gerar meta tag para uso em AJAX
     */
    public static function getTokenMeta(): string
    {
        $token = self::getToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Validar requisição (POST, PUT, DELETE, PATCH)
     * Lança exceção se inválido
     */
    public static function validateRequest(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Apenas validar métodos que modificam dados
        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            return;
        }

        // Obter token da requisição
        $token = null;

        // 1. Tentar obter do POST
        if (!empty($_POST['csrf_token'])) {
            $token = $_POST['csrf_token'];
        }
        // 2. Tentar obter do header (para AJAX)
        elseif (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        // 3. Tentar obter do JSON body
        elseif ($method !== 'GET') {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            if (!empty($data['csrf_token'])) {
                $token = $data['csrf_token'];
            }
        }

        // Validar token
        if (!self::validateToken($token)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Token CSRF inválido ou expirado. Recarregue a página e tente novamente.'
            ]);
            exit;
        }
    }

    /**
     * Middleware para validação automática
     * Usar no início de scripts que processam formulários
     */
    public static function protect(): void
    {
        try {
            self::validateRequest();
        } catch (Exception $e) {
            error_log("[CSRF] Erro na validação: " . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao validar requisição. Tente novamente.'
            ]);
            exit;
        }
    }
}

