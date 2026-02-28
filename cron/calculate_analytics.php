<?php
/**
 * Job para calcular analytics diárias
 * Executar via cron: 0 1 * * * php /path/to/calculate_analytics.php
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/database.php';

$logPrefix = '[ANALYTICS_JOB]';

try {
    // Buscar todas as campanhas ativas ou concluídas recentemente
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            dc.id as campaign_id,
            dc.user_id
        FROM dispatch_campaigns dc
        WHERE dc.status IN ('in_progress', 'completed')
        AND dc.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");
    
    $stmt->execute();
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processed = 0;
    
    foreach ($campaigns as $campaign) {
        $campaignId = $campaign['campaign_id'];
        $userId = $campaign['user_id'];
        $today = date('Y-m-d');
        
        // Calcular estatísticas do dia
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_sent,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as total_delivered,
                SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as total_read,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as total_failed,
                AVG(CASE 
                    WHEN delivered_at IS NOT NULL 
                    THEN TIMESTAMPDIFF(SECOND, sent_at, delivered_at)
                    ELSE NULL 
                END) as avg_delivery_time,
                AVG(CASE 
                    WHEN read_at IS NOT NULL 
                    THEN TIMESTAMPDIFF(SECOND, sent_at, read_at)
                    ELSE NULL 
                END) as avg_read_time
            FROM dispatch_history
            WHERE campaign_id = ?
            AND DATE(sent_at) = ?
        ");
        
        $stmt->execute([$campaignId, $today]);
        $dispatchStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Estatísticas de respostas
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_responses,
                AVG(response_time_seconds) as avg_response_time,
                SUM(CASE WHEN sentiment = 'positive' THEN 1 ELSE 0 END) as positive_responses,
                SUM(CASE WHEN sentiment = 'negative' THEN 1 ELSE 0 END) as negative_responses,
                SUM(CASE WHEN sentiment = 'neutral' THEN 1 ELSE 0 END) as neutral_responses
            FROM dispatch_responses
            WHERE campaign_id = ?
            AND DATE(received_at) = ?
        ");
        
        $stmt->execute([$campaignId, $today]);
        $responseStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calcular taxa de resposta
        $responseRate = $dispatchStats['total_sent'] > 0 
            ? round(($responseStats['total_responses'] / $dispatchStats['total_sent']) * 100, 2)
            : 0;
        
        // Inserir ou atualizar analytics
        $stmt = $pdo->prepare("
            INSERT INTO dispatch_analytics (
                campaign_id, user_id, date,
                total_sent, total_delivered, total_read, total_failed,
                total_responses, response_rate,
                avg_response_time_seconds,
                positive_responses, negative_responses, neutral_responses,
                created_at, updated_at
            ) VALUES (
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?,
                ?,
                ?, ?, ?,
                NOW(), NOW()
            )
            ON DUPLICATE KEY UPDATE
                total_sent = VALUES(total_sent),
                total_delivered = VALUES(total_delivered),
                total_read = VALUES(total_read),
                total_failed = VALUES(total_failed),
                total_responses = VALUES(total_responses),
                response_rate = VALUES(response_rate),
                avg_response_time_seconds = VALUES(avg_response_time_seconds),
                positive_responses = VALUES(positive_responses),
                negative_responses = VALUES(negative_responses),
                neutral_responses = VALUES(neutral_responses),
                updated_at = NOW()
        ");
        
        $stmt->execute([
            $campaignId,
            $userId,
            $today,
            $dispatchStats['total_sent'] ?? 0,
            $dispatchStats['total_delivered'] ?? 0,
            $dispatchStats['total_read'] ?? 0,
            $dispatchStats['total_failed'] ?? 0,
            $responseStats['total_responses'] ?? 0,
            $responseRate,
            $responseStats['avg_response_time'] ?? 0,
            $responseStats['positive_responses'] ?? 0,
            $responseStats['negative_responses'] ?? 0,
            $responseStats['neutral_responses'] ?? 0
        ]);
        
        $processed++;
    }
    
    echo "$logPrefix Processadas {$processed} campanhas\n";
    error_log("$logPrefix Processadas {$processed} campanhas");
    
    exit(0);
} catch (Exception $e) {
    echo "$logPrefix Erro: " . $e->getMessage() . "\n";
    error_log("$logPrefix Erro: " . $e->getMessage());
    exit(1);
}
