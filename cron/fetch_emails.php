<?php
/**
 * Cron Job: Buscar emails periodicamente
 * Executar a cada 5 minutos via crontab ou Task Scheduler
 * 
 * Crontab: */5 * * * * php /path/to/wats/cron/fetch_emails.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/channels/EmailChannel.php';

// Log de início
$logFile = __DIR__ . '/../logs/email_cron.log';
$startTime = date('Y-m-d H:i:s');

function logMessage($message) {
    global $logFile, $startTime;
    $log = "[{$startTime}] {$message}\n";
    file_put_contents($logFile, $log, FILE_APPEND);
    echo $log;
}

logMessage("=== Iniciando busca de emails ===");

try {
    // Buscar todos os canais de email ativos
    $stmt = $pdo->prepare("
        SELECT c.id, c.user_id, c.name, ce.*
        FROM channels c
        INNER JOIN channel_email ce ON c.id = ce.channel_id
        WHERE c.channel_type = 'email' 
        AND c.status = 'active'
    ");
    $stmt->execute();
    $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($channels)) {
        logMessage("Nenhum canal de email ativo encontrado");
        exit(0);
    }
    
    logMessage("Encontrados " . count($channels) . " canais de email ativos");
    
    $totalEmails = 0;
    $totalErrors = 0;
    
    foreach ($channels as $channel) {
        try {
            logMessage("Processando canal: {$channel['name']} (ID: {$channel['id']})");
            
            // Inicializar canal
            $emailChannel = new EmailChannel($pdo, $channel['id']);
            
            // Buscar novos emails (limite de 50 por vez)
            $emails = $emailChannel->fetchNewEmails(50);
            
            $count = count($emails);
            $totalEmails += $count;
            
            logMessage("  → {$count} novos emails encontrados");
            
            if ($count > 0) {
                // Emails já são processados automaticamente pelo fetchNewEmails
                logMessage("  → Emails processados e salvos no banco de dados");
            }
            
        } catch (Exception $e) {
            $totalErrors++;
            logMessage("  ✗ ERRO no canal {$channel['id']}: " . $e->getMessage());
        }
    }
    
    logMessage("=== Busca concluída ===");
    logMessage("Total de emails: {$totalEmails}");
    logMessage("Total de erros: {$totalErrors}");
    
} catch (Exception $e) {
    logMessage("✗ ERRO FATAL: " . $e->getMessage());
    exit(1);
}

exit(0);
