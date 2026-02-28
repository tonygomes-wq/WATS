<?php
/**
 * Script para popular dados iniciais de analytics
 * Execute via navegador ou CLI
 */

require_once '../config/database.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];

try {
    // Verificar se tabelas existem
    $tables = ['dispatch_time_analytics', 'contact_engagement_scores'];
    foreach ($tables as $table) {
        $check = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($check->rowCount() === 0) {
            throw new Exception("Tabela $table não existe. Execute a migration primeiro.");
        }
    }
    
    // Popular dispatch_time_analytics
    $stmt = $pdo->prepare("
        INSERT INTO dispatch_time_analytics 
            (user_id, day_of_week, hour_of_day, total_sent, total_responses, engagement_score)
        SELECT 
            m.user_id,
            DAYOFWEEK(m.created_at) as day_of_week,
            HOUR(m.created_at) as hour_of_day,
            COUNT(*) as total_sent,
            COUNT(r.id) as total_responses,
            CASE 
                WHEN COUNT(*) > 0 THEN (COUNT(r.id) / COUNT(*)) * 100 
                ELSE 0 
            END as engagement_score
        FROM chat_messages m
        LEFT JOIN chat_messages r ON r.conversation_id = m.conversation_id 
            AND r.is_from_me = FALSE 
            AND r.created_at > m.created_at 
            AND r.created_at < DATE_ADD(m.created_at, INTERVAL 24 HOUR)
        WHERE m.user_id = :user_id
            AND m.is_from_me = TRUE
            AND m.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        GROUP BY m.user_id, day_of_week, hour_of_day
        HAVING total_sent >= 3
        ON DUPLICATE KEY UPDATE 
            total_sent = VALUES(total_sent),
            total_responses = VALUES(total_responses),
            engagement_score = VALUES(engagement_score)
    ");
    $stmt->execute(['user_id' => $userId]);
    $timeAnalytics = $stmt->rowCount();
    
    // Popular contact_engagement_scores usando stored procedure
    try {
        $stmt = $pdo->prepare("CALL sp_recalculate_engagement_scores(:user_id)");
        $stmt->execute(['user_id' => $userId]);
        $engagementScores = 'Executado via stored procedure';
    } catch (Exception $e) {
        // Se stored procedure falhar, tenta inserir dados básicos
        $engagementScores = 'Stored procedure não disponível: ' . $e->getMessage();
    }
    
    // Verificar dados criados
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dispatch_time_analytics WHERE user_id = ?");
    $stmt->execute([$userId]);
    $totalTimeAnalytics = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM contact_engagement_scores WHERE user_id = ?");
    $stmt->execute([$userId]);
    $totalEngagement = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'message' => 'Dados populados com sucesso',
        'data' => [
            'time_analytics_inserted' => $timeAnalytics,
            'time_analytics_total' => $totalTimeAnalytics,
            'engagement_scores_inserted' => $engagementScores,
            'engagement_scores_total' => $totalEngagement
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
