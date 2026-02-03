<?php
/**
 * Health Check Endpoint
 * Usado pelo Docker e Easypanel para verificar se a aplicação está funcionando
 */

header('Content-Type: application/json');

$health = [
    'status' => 'ok',
    'timestamp' => time(),
    'datetime' => date('Y-m-d H:i:s'),
    'checks' => []
];

// Verificar conexão com banco de dados
try {
    require_once __DIR__ . '/config/database.php';
    
    $stmt = $pdo->query('SELECT 1');
    $health['checks']['database'] = [
        'status' => 'connected',
        'host' => DB_HOST,
        'name' => DB_NAME
    ];
} catch (Exception $e) {
    $health['status'] = 'error';
    $health['checks']['database'] = [
        'status' => 'disconnected',
        'error' => $e->getMessage()
    ];
    http_response_code(503);
}

// Verificar se diretórios de upload existem e são graváveis
$uploadDir = __DIR__ . '/uploads';
if (is_dir($uploadDir) && is_writable($uploadDir)) {
    $health['checks']['uploads'] = [
        'status' => 'writable',
        'path' => $uploadDir
    ];
} else {
    $health['status'] = 'warning';
    $health['checks']['uploads'] = [
        'status' => 'not_writable',
        'path' => $uploadDir
    ];
}

// Verificar se diretório de logs existe e é gravável
$logsDir = __DIR__ . '/logs';
if (is_dir($logsDir) && is_writable($logsDir)) {
    $health['checks']['logs'] = [
        'status' => 'writable',
        'path' => $logsDir
    ];
} else {
    $health['status'] = 'warning';
    $health['checks']['logs'] = [
        'status' => 'not_writable',
        'path' => $logsDir
    ];
}

// Verificar extensões PHP necessárias
$requiredExtensions = ['pdo', 'pdo_mysql', 'mysqli', 'curl', 'gd', 'mbstring', 'zip'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (empty($missingExtensions)) {
    $health['checks']['php_extensions'] = [
        'status' => 'ok',
        'loaded' => $requiredExtensions
    ];
} else {
    $health['status'] = 'error';
    $health['checks']['php_extensions'] = [
        'status' => 'missing',
        'missing' => $missingExtensions
    ];
    http_response_code(503);
}

// Informações do sistema
$health['system'] = [
    'php_version' => PHP_VERSION,
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'upload_max_filesize' => ini_get('upload_max_filesize')
];

echo json_encode($health, JSON_PRETTY_PRINT);
