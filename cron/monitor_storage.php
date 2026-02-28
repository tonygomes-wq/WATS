<?php
/**
 * Cron Job de Monitoramento de Storage
 * Verifica uso de storage e envia alertas
 * 
 * CONFIGURAÇÃO CRON:
 * 0 */6 * * * /usr/bin/php /path/to/cron/monitor_storage.php
 * (Executa a cada 6 horas)
 * 
 * MACIP Tecnologia LTDA
 */

// Evitar execução via browser
if (php_sapi_name() !== 'cli' && !defined('ALLOW_WEB_CRON')) {
    die('Este script deve ser executado via linha de comando ou cron job.');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/StorageMonitor.php';

$logFile = __DIR__ . '/../logs/storage_monitor_' . date('Y-m-d') . '.log';

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message\n";
    echo $logLine;
    file_put_contents($logFile, $logLine, FILE_APPEND);
}

logMessage("========================================");
logMessage("INICIANDO MONITORAMENTO DE STORAGE");
logMessage("========================================");

try {
    $monitor = new StorageMonitor($pdo);
    
    // 1. Verificar estatísticas gerais do sistema
    logMessage("\n[1/3] Coletando estatísticas do sistema...");
    $stats = $monitor->getSystemStats();
    
    logMessage("  - Tamanho do banco: {$stats['database_size_mb']} MB");
    logMessage("  - Tamanho dos uploads: {$stats['uploads_size_mb']} MB");
    logMessage("  - Tamanho total: {$stats['total_size_mb']} MB");
    logMessage("  - Total de usuários: {$stats['total_users']}");
    logMessage("  - Total de mensagens: {$stats['total_messages']}");
    logMessage("  - Média por usuário: {$stats['avg_per_user_mb']} MB");
    
    // 2. Verificar uso de storage por usuário
    logMessage("\n[2/3] Verificando uso de storage por usuário...");
    $alerts = $monitor->checkAllUsers();
    
    if (empty($alerts)) {
        logMessage("  ✓ Nenhum alerta de storage detectado");
    } else {
        logMessage("  ⚠ " . count($alerts) . " alertas detectados:");
        
        foreach ($alerts as $alert) {
            $icon = $alert['level'] === 'critical' ? '🔴' : '⚠️';
            logMessage("    $icon {$alert['email']}: {$alert['percentage']}% ({$alert['usage_mb']}/{$alert['limit_mb']} MB)");
        }
        
        // 3. Enviar alertas
        logMessage("\n[3/3] Enviando alertas...");
        $monitor->sendAlerts($alerts);
        logMessage("  ✓ Alertas enviados com sucesso");
    }
    
    // Registrar no banco
    $stmt = $pdo->prepare("
        INSERT INTO storage_checks (
            total_size_mb,
            alerts_count,
            critical_count,
            warning_count,
            created_at
        ) VALUES (?, ?, ?, ?, NOW())
    ");
    
    $criticalCount = count(array_filter($alerts, fn($a) => $a['level'] === 'critical'));
    $warningCount = count(array_filter($alerts, fn($a) => $a['level'] === 'warning'));
    
    $stmt->execute([
        $stats['total_size_mb'],
        count($alerts),
        $criticalCount,
        $warningCount
    ]);
    
    logMessage("\n========================================");
    logMessage("MONITORAMENTO CONCLUÍDO COM SUCESSO");
    logMessage("========================================\n");
    
} catch (Exception $e) {
    logMessage("\n[ERRO] " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    exit(1);
}

exit(0);
