<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_unread':
            // Buscar notificações não lidas
            $stmt = $pdo->prepare("
                SELECT 
                    dr.id,
                    dr.message_text,
                    dr.sentiment,
                    dr.received_at,
                    dr.is_first_response,
                    c.name as contact_name,
                    dr.phone,
                    dc.name as campaign_name
                FROM dispatch_responses dr
                LEFT JOIN contacts c ON dr.contact_id = c.id
                LEFT JOIN dispatch_campaigns dc ON dr.campaign_id = dc.id
                WHERE dr.user_id = ?
                AND dr.received_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY dr.received_at DESC
                LIMIT 50
            ");
            
            $stmt->execute([$userId]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'count' => count($notifications)
            ]);
            break;
            
        case 'get_summary':
            // Resumo de respostas recentes
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_responses,
                    SUM(CASE WHEN sentiment = 'positive' THEN 1 ELSE 0 END) as positive,
                    SUM(CASE WHEN sentiment = 'negative' THEN 1 ELSE 0 END) as negative,
                    SUM(CASE WHEN sentiment = 'neutral' THEN 1 ELSE 0 END) as neutral,
                    SUM(CASE WHEN is_first_response = 1 THEN 1 ELSE 0 END) as first_responses
                FROM dispatch_responses
                WHERE user_id = ?
                AND received_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            
            $stmt->execute([$userId]);
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'summary' => $summary
            ]);
            break;
            
        case 'mark_read':
            // Marcar notificações como lidas (implementação futura)
            echo json_encode([
                'success' => true,
                'message' => 'Funcionalidade em desenvolvimento'
            ]);
            break;
            
        default:
            throw new Exception('Ação inválida');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
