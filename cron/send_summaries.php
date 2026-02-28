<?php
/**
 * Cron Job - Enviar Resumos por Email
 * Executar a cada 5 minutos via crontab
 * 
 * Crontab:
 * */5 * * * * php /caminho/para/wats/cron/send_summaries.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/email_sender.php';

// Log de execução
$log_file = __DIR__ . '/summaries.log';
$start_time = microtime(true);

function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
    echo "[$timestamp] $message\n";
}

logMessage("=== Iniciando envio de resumos ===");

try {
    // =====================================================
    // RESUMOS DIÁRIOS
    // =====================================================
    
    logMessage("Verificando resumos diários...");
    
    $stmt = $pdo->query("
        SELECT u.id, u.email, u.name, np.daily_summary_time
        FROM users u
        JOIN notification_preferences np ON u.id = np.user_id
        JOIN email_settings es ON u.id = es.user_id
        WHERE np.daily_summary = 1
        AND es.is_enabled = 1
        AND TIME(NOW()) >= np.daily_summary_time
        AND TIME(NOW()) < ADDTIME(np.daily_summary_time, '00:05:00')
    ");
    
    $supervisors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    logMessage("Encontrados " . count($supervisors) . " supervisores para resumo diário");
    
    foreach ($supervisors as $supervisor) {
        try {
            $emailSender = new EmailSender($pdo, $supervisor['id']);
            $result = $emailSender->sendDailySummary($supervisor['id']);
            
            if ($result['success']) {
                logMessage("✅ Resumo diário enviado para {$supervisor['name']} ({$supervisor['email']})");
            } else {
                logMessage("❌ Erro ao enviar para {$supervisor['name']}: {$result['error']}");
            }
        } catch (Exception $e) {
            logMessage("❌ Exceção ao enviar para {$supervisor['name']}: {$e->getMessage()}");
        }
    }
    
    // =====================================================
    // RESUMOS SEMANAIS
    // =====================================================
    
    logMessage("Verificando resumos semanais...");
    
    $current_day = date('w') + 1; // 1=Dom, 2=Seg, ..., 7=Sáb
    $current_time = date('H:i:s');
    
    $stmt = $pdo->prepare("
        SELECT u.id, u.email, u.name
        FROM users u
        JOIN notification_preferences np ON u.id = np.user_id
        JOIN email_settings es ON u.id = es.user_id
        WHERE np.weekly_summary = 1
        AND es.is_enabled = 1
        AND np.weekly_summary_day = ?
        AND ? >= '08:00:00' AND ? < '08:05:00'
    ");
    
    $stmt->execute([$current_day, $current_time, $current_time]);
    $weekly_supervisors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    logMessage("Encontrados " . count($weekly_supervisors) . " supervisores para resumo semanal");
    
    foreach ($weekly_supervisors as $supervisor) {
        try {
            // Implementar sendWeeklySummary() no EmailSender se necessário
            logMessage("ℹ️ Resumo semanal para {$supervisor['name']} (funcionalidade em desenvolvimento)");
        } catch (Exception $e) {
            logMessage("❌ Exceção ao enviar resumo semanal para {$supervisor['name']}: {$e->getMessage()}");
        }
    }
    
    // =====================================================
    // ALERTAS DE FILA
    // =====================================================
    
    logMessage("Verificando alertas de fila...");
    
    $stmt = $pdo->query("
        SELECT 
            u.id, u.email, u.name,
            np.alert_queue_threshold,
            COUNT(cq.id) as queue_count
        FROM users u
        JOIN notification_preferences np ON u.id = np.user_id
        JOIN email_settings es ON u.id = es.user_id
        LEFT JOIN conversation_queue cq ON u.id = cq.supervisor_id AND cq.status = 'waiting'
        WHERE es.is_enabled = 1
        AND np.alert_queue_threshold > 0
        GROUP BY u.id, u.email, u.name, np.alert_queue_threshold
        HAVING queue_count >= np.alert_queue_threshold
    ");
    
    $queue_alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    logMessage("Encontrados " . count($queue_alerts) . " alertas de fila");
    
    foreach ($queue_alerts as $alert) {
        try {
            $emailSender = new EmailSender($pdo, $alert['id']);
            
            $subject = "⚠️ Alerta: Fila de Atendimento Alta";
            $body = "
                <h2 style='color: #ff9800;'>⚠️ Alerta de Fila</h2>
                <p>Olá {$alert['name']},</p>
                <p>A fila de atendimento está acima do limite configurado:</p>
                <ul>
                    <li><strong>Conversas na fila:</strong> {$alert['queue_count']}</li>
                    <li><strong>Limite configurado:</strong> {$alert['alert_queue_threshold']}</li>
                </ul>
                <p>Recomendamos verificar a distribuição de conversas.</p>
                <p><a href='https://{$_SERVER['HTTP_HOST']}/distribution_settings.php' style='background-color: #ff9800; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Ver Fila</a></p>
            ";
            
            $result = $emailSender->send($alert['email'], $subject, $body);
            
            if ($result['success']) {
                logMessage("✅ Alerta de fila enviado para {$alert['name']}");
            } else {
                logMessage("❌ Erro ao enviar alerta para {$alert['name']}: {$result['error']}");
            }
        } catch (Exception $e) {
            logMessage("❌ Exceção ao enviar alerta para {$alert['name']}: {$e->getMessage()}");
        }
    }
    
    // =====================================================
    // ALERTAS DE TEMPO DE ESPERA
    // =====================================================
    
    logMessage("Verificando alertas de tempo de espera...");
    
    $stmt = $pdo->query("
        SELECT 
            u.id, u.email, u.name,
            np.alert_wait_time_threshold,
            cq.id as queue_id,
            cq.customer_name,
            TIMESTAMPDIFF(SECOND, cq.queued_at, NOW()) as wait_seconds
        FROM users u
        JOIN notification_preferences np ON u.id = np.user_id
        JOIN email_settings es ON u.id = es.user_id
        JOIN conversation_queue cq ON u.id = cq.supervisor_id
        WHERE es.is_enabled = 1
        AND cq.status = 'waiting'
        AND np.alert_wait_time_threshold > 0
        AND TIMESTAMPDIFF(SECOND, cq.queued_at, NOW()) >= np.alert_wait_time_threshold
    ");
    
    $wait_alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    logMessage("Encontrados " . count($wait_alerts) . " alertas de tempo de espera");
    
    foreach ($wait_alerts as $alert) {
        try {
            $emailSender = new EmailSender($pdo, $alert['id']);
            
            $wait_minutes = floor($alert['wait_seconds'] / 60);
            
            $subject = "⚠️ Alerta: Cliente aguardando há {$wait_minutes} minutos";
            $body = "
                <h2 style='color: #f44336;'>⚠️ Alerta de Tempo de Espera</h2>
                <p>Olá {$alert['name']},</p>
                <p>Um cliente está aguardando atendimento há muito tempo:</p>
                <ul>
                    <li><strong>Cliente:</strong> {$alert['customer_name']}</li>
                    <li><strong>Tempo de espera:</strong> {$wait_minutes} minutos</li>
                    <li><strong>Limite configurado:</strong> " . floor($alert['alert_wait_time_threshold'] / 60) . " minutos</li>
                </ul>
                <p><a href='https://{$_SERVER['HTTP_HOST']}/distribution_settings.php?tab=queue' style='background-color: #f44336; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Atender Agora</a></p>
            ";
            
            $result = $emailSender->send($alert['email'], $subject, $body);
            
            if ($result['success']) {
                logMessage("✅ Alerta de espera enviado para {$alert['name']} (Cliente: {$alert['customer_name']})");
            } else {
                logMessage("❌ Erro ao enviar alerta para {$alert['name']}: {$result['error']}");
            }
        } catch (Exception $e) {
            logMessage("❌ Exceção ao enviar alerta para {$alert['name']}: {$e->getMessage()}");
        }
    }
    
} catch (Exception $e) {
    logMessage("❌ ERRO FATAL: {$e->getMessage()}");
    logMessage("Stack trace: {$e->getTraceAsString()}");
}

$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 2);

logMessage("=== Finalizado em {$execution_time}s ===\n");
