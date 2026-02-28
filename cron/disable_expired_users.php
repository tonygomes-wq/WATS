<?php
/**
 * CRON Job: Desativar Usuários Vencidos
 * Sistema WATS - MACIP Tecnologia LTDA
 * 
 * COMO USAR:
 * 1. Fazer upload deste arquivo para: /cron/disable_expired_users.php
 * 2. Configurar CRON no servidor para executar a cada hora:
 *    0 * * * * php /caminho/completo/wats/cron/disable_expired_users.php
 * 
 * OU via URL (se permitido):
 *    0 * * * * curl https://seudominio.com/cron/disable_expired_users.php
 * 
 * SEGURANÇA:
 * - Apenas pode ser executado via CLI ou com token secreto
 * - Logs são salvos em /cron/logs/
 */

// Configurações
define('CRON_SECRET_TOKEN', 'seu_token_secreto_aqui_12345'); // ALTERAR!
define('LOG_FILE', __DIR__ . '/logs/disable_expired_users.log');

// Verificar se está sendo executado via CLI ou com token válido
$isCLI = (php_sapi_name() === 'cli');
$hasValidToken = isset($_GET['token']) && $_GET['token'] === CRON_SECRET_TOKEN;

if (!$isCLI && !$hasValidToken) {
    http_response_code(403);
    die('Acesso negado. Este script só pode ser executado via CRON.');
}

// Incluir configuração do banco
require_once dirname(__DIR__) . '/config/database.php';

// Função para log
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logDir = dirname(LOG_FILE);
    
    // Criar diretório de logs se não existir
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = "[$timestamp] $message" . PHP_EOL;
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND);
    
    if (php_sapi_name() === 'cli') {
        echo $logEntry;
    }
}

try {
    logMessage('=== INICIANDO DESATIVAÇÃO DE USUÁRIOS VENCIDOS ===');
    
    // Chamar a procedure
    $stmt = $pdo->query("CALL auto_disable_expired_users()");
    $result = $stmt->fetch();
    $usersDisabled = $result['users_disabled'] ?? 0;
    
    logMessage("Usuários desativados: $usersDisabled");
    
    // Buscar detalhes dos usuários desativados
    if ($usersDisabled > 0) {
        $stmt = $pdo->query("
            SELECT id, name, email, plan_expires_at 
            FROM users 
            WHERE is_active = 0 
            AND plan_expires_at < DATE_SUB(NOW(), INTERVAL 12 HOUR)
            ORDER BY plan_expires_at DESC
            LIMIT 10
        ");
        $users = $stmt->fetchAll();
        
        logMessage('Usuários desativados recentemente:');
        foreach ($users as $user) {
            logMessage("  - ID: {$user['id']}, Nome: {$user['name']}, Email: {$user['email']}, Vencimento: {$user['plan_expires_at']}");
        }
    }
    
    // Estatísticas gerais
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_users
        FROM users
    ");
    $stats = $stmt->fetch();
    
    logMessage("Estatísticas: Total: {$stats['total_users']}, Ativos: {$stats['active_users']}, Inativos: {$stats['inactive_users']}");
    logMessage('=== CONCLUÍDO COM SUCESSO ===');
    
    // Resposta para requisições HTTP
    if (!$isCLI) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'users_disabled' => $usersDisabled,
            'stats' => $stats,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
} catch (Exception $e) {
    $errorMsg = 'ERRO: ' . $e->getMessage();
    logMessage($errorMsg);
    
    if (!$isCLI) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    exit(1);
}
