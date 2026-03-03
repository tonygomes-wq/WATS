<?php

/**
 * Bootstrap para Testes
 * 
 * Configuração inicial para execução de testes
 */

// Autoloader do Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Carregar dependências
require_once __DIR__ . '/../includes/Logger.php';
require_once __DIR__ . '/../includes/RateLimiter.php';
require_once __DIR__ . '/../includes/InputValidator.php';

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

// Iniciar sessão para testes
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Definir constantes de teste
define('TESTING', true);
define('TEST_DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('TEST_DB_NAME', getenv('DB_NAME') ?: 'test_database');
define('TEST_DB_USER', getenv('DB_USER') ?: 'test_user');
define('TEST_DB_PASS', getenv('DB_PASS') ?: 'test_pass');
