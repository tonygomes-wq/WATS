<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
            $totalContacts = (int)($_POST['total_contacts'] ?? 0);
            
            if (empty($name)) {
                throw new Exception('Nome da campanha é obrigatório');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO dispatch_campaigns 
                (user_id, name, description, category_id, total_contacts, created_by, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'draft', NOW())
            ");
            
            $stmt->execute([$userId, $name, $description, $categoryId, $totalContacts, $userId]);
            $campaignId = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'campaign_id' => $campaignId,
                'message' => 'Campanha criada com sucesso'
            ]);
            break;
            
        case 'list':
            $status = $_GET['status'] ?? 'all';
            $limit = (int)($_GET['limit'] ?? 50);
            $offset = (int)($_GET['offset'] ?? 0);
            
            $sql = "
                SELECT 
                    dc.*,
                    c.name as category_name,
                    c.color as category_color,
                    u.name as created_by_name,
                    CASE 
                        WHEN dc.total_contacts > 0 
                        THEN ROUND((dc.sent_count / dc.total_contacts) * 100, 2)
                        ELSE 0 
                    END as progress_percent,
                    CASE 
                        WHEN dc.response_count > 0 AND dc.sent_count > 0
                        THEN ROUND((dc.response_count / dc.sent_count) * 100, 2)
                        ELSE 0 
                    END as response_rate
                FROM dispatch_campaigns dc
                LEFT JOIN categories c ON dc.category_id = c.id
                LEFT JOIN users u ON dc.created_by = u.id
                WHERE dc.user_id = ?
            ";
            
            if ($status !== 'all') {
                $sql .= " AND dc.status = ?";
            }
            
            $sql .= " ORDER BY dc.created_at DESC LIMIT ? OFFSET ?";
            
            $stmt = $pdo->prepare($sql);
            
            if ($status !== 'all') {
                $stmt->execute([$userId, $status, $limit, $offset]);
            } else {
                $stmt->execute([$userId, $limit, $offset]);
            }
            
            $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM dispatch_campaigns WHERE user_id = ?" . 
                ($status !== 'all' ? " AND status = ?" : ""));
            
            if ($status !== 'all') {
                $stmtCount->execute([$userId, $status]);
            } else {
                $stmtCount->execute([$userId]);
            }
            
            $total = $stmtCount->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'campaigns' => $campaigns,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]);
            break;
            
        case 'get':
            $campaignId = (int)($_GET['id'] ?? 0);
            
            if (!$campaignId) {
                throw new Exception('ID da campanha é obrigatório');
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    dc.*,
                    c.name as category_name,
                    c.color as category_color,
                    u.name as created_by_name,
                    CASE 
                        WHEN dc.total_contacts > 0 
                        THEN ROUND((dc.sent_count / dc.total_contacts) * 100, 2)
                        ELSE 0 
                    END as progress_percent,
                    CASE 
                        WHEN dc.response_count > 0 AND dc.sent_count > 0
                        THEN ROUND((dc.response_count / dc.sent_count) * 100, 2)
                        ELSE 0 
                    END as response_rate
                FROM dispatch_campaigns dc
                LEFT JOIN categories c ON dc.category_id = c.id
                LEFT JOIN users u ON dc.created_by = u.id
                WHERE dc.id = ? AND dc.user_id = ?
            ");
            
            $stmt->execute([$campaignId, $userId]);
            $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$campaign) {
                throw new Exception('Campanha não encontrada');
            }
            
            echo json_encode([
                'success' => true,
                'campaign' => $campaign
            ]);
            break;
            
        case 'update':
            $campaignId = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (!$campaignId) {
                throw new Exception('ID da campanha é obrigatório');
            }
            
            if (empty($name)) {
                throw new Exception('Nome da campanha é obrigatório');
            }
            
            $stmt = $pdo->prepare("
                UPDATE dispatch_campaigns 
                SET name = ?, description = ?
                WHERE id = ? AND user_id = ?
            ");
            
            $stmt->execute([$name, $description, $campaignId, $userId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Campanha atualizada com sucesso'
            ]);
            break;
            
        case 'complete':
            $campaignId = (int)($_POST['id'] ?? 0);
            
            if (!$campaignId) {
                throw new Exception('ID da campanha é obrigatório');
            }
            
            $stmt = $pdo->prepare("
                UPDATE dispatch_campaigns 
                SET status = 'completed', completed_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            
            $stmt->execute([$campaignId, $userId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Campanha finalizada com sucesso'
            ]);
            break;
            
        case 'cancel':
            $campaignId = (int)($_POST['id'] ?? 0);
            
            if (!$campaignId) {
                throw new Exception('ID da campanha é obrigatório');
            }
            
            $stmt = $pdo->prepare("
                UPDATE dispatch_campaigns 
                SET status = 'cancelled'
                WHERE id = ? AND user_id = ?
            ");
            
            $stmt->execute([$campaignId, $userId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Campanha cancelada'
            ]);
            break;
            
        case 'delete':
            $campaignId = (int)($_POST['id'] ?? 0);
            
            if (!$campaignId) {
                throw new Exception('ID da campanha é obrigatório');
            }
            
            $stmt = $pdo->prepare("
                DELETE FROM dispatch_campaigns 
                WHERE id = ? AND user_id = ?
            ");
            
            $stmt->execute([$campaignId, $userId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Campanha excluída com sucesso'
            ]);
            break;
            
        case 'stats':
            $campaignId = (int)($_GET['id'] ?? 0);
            
            if (!$campaignId) {
                throw new Exception('ID da campanha é obrigatório');
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_dispatches,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                    SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
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
                WHERE campaign_id = ? AND user_id = ?
            ");
            
            $stmt->execute([$campaignId, $userId]);
            $dispatchStats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_responses,
                    SUM(CASE WHEN is_first_response = 1 THEN 1 ELSE 0 END) as first_responses,
                    AVG(response_time_seconds) as avg_response_time,
                    SUM(CASE WHEN sentiment = 'positive' THEN 1 ELSE 0 END) as positive,
                    SUM(CASE WHEN sentiment = 'neutral' THEN 1 ELSE 0 END) as neutral,
                    SUM(CASE WHEN sentiment = 'negative' THEN 1 ELSE 0 END) as negative
                FROM dispatch_responses
                WHERE campaign_id = ? AND user_id = ?
            ");
            
            $stmt->execute([$campaignId, $userId]);
            $responseStats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'dispatch_stats' => $dispatchStats,
                'response_stats' => $responseStats
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
