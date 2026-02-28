<?php
/**
 * Script de Limpeza Automática de Dados Antigos
 * Executa a política de retenção definida em config/data_retention.php
 * 
 * CONFIGURAÇÃO CRON:
 * 0 3 * * * /usr/bin/php /path/to/cron/cleanup_old_data.php
 * (Executa diariamente às 3h da manhã)
 * 
 * MACIP Tecnologia LTDA
 */

// Evitar execução via browser
if (php_sapi_name() !== 'cli' && !defined('ALLOW_WEB_CRON')) {
    die('Este script deve ser executado via linha de comando ou cron job.');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/data_retention.php';

// Carregar política de retenção
$retentionPolicy = require __DIR__ . '/../config/data_retention.php';
$settings = $retentionPolicy['cleanup_settings'];

// Verificar se limpeza está habilitada
if (!$settings['enabled']) {
    echo "[INFO] Limpeza automática está desabilitada.\n";
    exit(0);
}

// Configurar tempo máximo de execução
set_time_limit($settings['max_execution_time']);

// Log de início
$startTime = microtime(true);
$logFile = __DIR__ . '/../logs/cleanup_' . date('Y-m-d') . '.log';
$totalDeleted = 0;

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message\n";
    echo $logLine;
    file_put_contents($logFile, $logLine, FILE_APPEND);
}

logMessage("========================================");
logMessage("INICIANDO LIMPEZA AUTOMÁTICA DE DADOS");
logMessage("========================================");

try {
    // ============================================
    // 1. LIMPEZA DE MENSAGENS
    // ============================================
    logMessage("\n[1/8] Limpando mensagens antigas...");
    
    // Histórico de disparos
    $days = $retentionPolicy['messages']['dispatch_history'];
    $stmt = $pdo->prepare("
        DELETE FROM dispatch_history 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        LIMIT ?
    ");
    $stmt->execute([$days, $settings['batch_size']]);
    $deleted = $stmt->rowCount();
    $totalDeleted += $deleted;
    logMessage("  - Dispatch History: $deleted registros deletados (> $days dias)");
    
    // Logs de webhook
    $days = $retentionPolicy['messages']['webhook_logs'];
    $stmt = $pdo->prepare("
        DELETE FROM webhook_logs 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        LIMIT ?
    ");
    $stmt->execute([$days, $settings['batch_size']]);
    $deleted = $stmt->rowCount();
    $totalDeleted += $deleted;
    logMessage("  - Webhook Logs: $deleted registros deletados (> $days dias)");
    
    // ============================================
    // 2. LIMPEZA DE LOGS
    // ============================================
    logMessage("\n[2/8] Limpando logs antigos...");
    
    // Logs de auditoria
    $days = $retentionPolicy['logs']['audit_logs'];
    $stmt = $pdo->prepare("
        DELETE FROM audit_logs 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        LIMIT ?
    ");
    $stmt->execute([$days, $settings['batch_size']]);
    $deleted = $stmt->rowCount();
    $totalDeleted += $deleted;
    logMessage("  - Audit Logs: $deleted registros deletados (> $days dias)");
    
    // Tentativas de login
    $days = $retentionPolicy['logs']['login_attempts'];
    $stmt = $pdo->prepare("
        DELETE FROM login_attempts 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        LIMIT ?
    ");
    $stmt->execute([$days, $settings['batch_size']]);
    $deleted = $stmt->rowCount();
    $totalDeleted += $deleted;
    logMessage("  - Login Attempts: $deleted registros deletados (> $days dias)");
    
    // ============================================
    // 3. LIMPEZA DE CAMPANHAS
    // ============================================
    logMessage("\n[3/8] Limpando campanhas antigas...");
    
    // Rascunhos não usados
    $days = $retentionPolicy['campaigns']['draft_campaigns'];
    $stmt = $pdo->prepare("
        DELETE FROM dispatch_campaigns 
        WHERE status = 'draft' 
        AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        LIMIT ?
    ");
    $stmt->execute([$days, $settings['batch_size']]);
    $deleted = $stmt->rowCount();
    $totalDeleted += $deleted;
    logMessage("  - Draft Campaigns: $deleted registros deletados (> $days dias)");
    
    // Respostas de campanhas
    $days = $retentionPolicy['campaigns']['campaign_responses'];
    $stmt = $pdo->prepare("
        DELETE FROM dispatch_responses 
        WHERE received_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        LIMIT ?
    ");
    $stmt->execute([$days, $settings['batch_size']]);
    $deleted = $stmt->rowCount();
    $totalDeleted += $deleted;
    logMessage("  - Campaign Responses: $deleted registros deletados (> $days dias)");
    
    // ============================================
    // 4. LIMPEZA DE MÍDIA
    // ============================================
    logMessage("\n[4/8] Limpando arquivos de mídia antigos...");
    
    // Arquivos temporários
    $tempDir = __DIR__ . '/../uploads/temp/';
    if (is_dir($tempDir)) {
        $files = glob($tempDir . '*');
        $deletedFiles = 0;
        $cutoffTime = time() - (86400 * $retentionPolicy['media']['temp_files']);
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $deletedFiles++;
                }
            }
        }
        logMessage("  - Temp Files: $deletedFiles arquivos deletados");
    }
    
    // QR Codes antigos
    $qrDir = __DIR__ . '/../uploads/qrcodes/';
    if (is_dir($qrDir)) {
        $files = glob($qrDir . '*.png');
        $deletedFiles = 0;
        $cutoffTime = time() - (86400 * $retentionPolicy['media']['qr_codes']);
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $deletedFiles++;
                }
            }
        }
        logMessage("  - QR Codes: $deletedFiles arquivos deletados");
    }
    
    // ============================================
    // 5. LIMPEZA DE SESSÕES
    // ============================================
    logMessage("\n[5/8] Limpando sessões expiradas...");
    
    // Sessões expiradas (se usar DB para sessões)
    $days = $retentionPolicy['sessions']['expired_sessions'];
    $stmt = $pdo->prepare("
        DELETE FROM sessions 
        WHERE last_activity < DATE_SUB(NOW(), INTERVAL ? DAY)
        LIMIT ?
    ");
    $stmt->execute([$days, $settings['batch_size']]);
    $deleted = $stmt->rowCount();
    $totalDeleted += $deleted;
    logMessage("  - Expired Sessions: $deleted registros deletados (> $days dias)");
    
    // Tokens de reset de senha
    $days = $retentionPolicy['sessions']['password_reset_tokens'];
    $stmt = $pdo->prepare("
        DELETE FROM password_resets 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$days]);
    $deleted = $stmt->rowCount();
    $totalDeleted += $deleted;
    logMessage("  - Password Reset Tokens: $deleted registros deletados (> $days dias)");
    
    // ============================================
    // 6. LIMPEZA DE NOTIFICAÇÕES
    // ============================================
    logMessage("\n[6/8] Limpando notificações antigas...");
    
    // Notificações lidas
    $days = $retentionPolicy['notifications']['read_notifications'];
    $stmt = $pdo->prepare("
        DELETE FROM notifications 
        WHERE is_read = 1 
        AND read_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        LIMIT ?
    ");
    $stmt->execute([$days, $settings['batch_size']]);
    $deleted = $stmt->rowCount();
    $totalDeleted += $deleted;
    logMessage("  - Read Notifications: $deleted registros deletados (> $days dias)");
    
    // ============================================
    // 7. OTIMIZAÇÃO DE TABELAS
    // ============================================
    logMessage("\n[7/8] Otimizando tabelas...");
    
    $tables = [
        'dispatch_history',
        'webhook_logs',
        'audit_logs',
        'login_attempts',
        'dispatch_campaigns',
        'dispatch_responses',
        'notifications'
    ];
    
    foreach ($tables as $table) {
        try {
            $pdo->exec("OPTIMIZE TABLE $table");
            logMessage("  - Tabela '$table' otimizada");
        } catch (PDOException $e) {
            logMessage("  - Erro ao otimizar '$table': " . $e->getMessage());
        }
    }
    
    // ============================================
    // 8. VERIFICAÇÃO DE STORAGE
    // ============================================
    logMessage("\n[8/8] Verificando uso de storage...");
    
    // Calcular tamanho do banco de dados
    $stmt = $pdo->query("
        SELECT 
            SUM(data_length + index_length) / 1024 / 1024 AS size_mb
        FROM information_schema.TABLES 
        WHERE table_schema = DATABASE()
    ");
    $dbSize = $stmt->fetch(PDO::FETCH_ASSOC);
    $sizeMB = round($dbSize['size_mb'], 2);
    logMessage("  - Tamanho do banco de dados: {$sizeMB} MB");
    
    // Calcular tamanho dos uploads
    $uploadDir = __DIR__ . '/../uploads/';
    $uploadSize = 0;
    if (is_dir($uploadDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploadDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            $uploadSize += $file->getSize();
        }
    }
    $uploadSizeMB = round($uploadSize / 1024 / 1024, 2);
    logMessage("  - Tamanho dos uploads: {$uploadSizeMB} MB");
    
    $totalSizeMB = $sizeMB + $uploadSizeMB;
    logMessage("  - Tamanho total: {$totalSizeMB} MB");
    
    // ============================================
    // RESUMO FINAL
    // ============================================
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 2);
    
    logMessage("\n========================================");
    logMessage("LIMPEZA CONCLUÍDA COM SUCESSO");
    logMessage("========================================");
    logMessage("Total de registros deletados: $totalDeleted");
    logMessage("Tempo de execução: {$executionTime}s");
    logMessage("Tamanho total do sistema: {$totalSizeMB} MB");
    logMessage("========================================\n");
    
    // Registrar no banco
    $stmt = $pdo->prepare("
        INSERT INTO cleanup_history (
            total_deleted,
            execution_time,
            storage_size_mb,
            created_at
        ) VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$totalDeleted, $executionTime, $totalSizeMB]);
    
} catch (Exception $e) {
    logMessage("\n[ERRO] " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    exit(1);
}

exit(0);
