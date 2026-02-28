<?php
/**
 * Bootstrap comum para páginas públicas
 * - Carrega configuração do banco
 * - Define tempo de sessão baseado em SESSION_LIFETIME
 * - Garante que funções auxiliares estejam disponíveis
 */

if (!defined('APP_INIT_LOADED')) {
    define('APP_INIT_LOADED', true);

    if (!defined('BASE_PATH')) {
        define('BASE_PATH', dirname(__DIR__));
    }

    require_once BASE_PATH . '/config/database.php';
    require_once BASE_PATH . '/includes/functions.php';

    if (session_status() !== PHP_SESSION_ACTIVE) {
        $lifetimeMinutes = defined('SESSION_LIFETIME') ? (int)SESSION_LIFETIME : 120;
        $lifetimeSeconds = max(60, $lifetimeMinutes * 60);
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

        session_set_cookie_params([
            'lifetime' => $lifetimeSeconds,
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }
}
