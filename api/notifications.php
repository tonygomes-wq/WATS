<?php
if (!isset($_SESSION)) {
    session_start();
}

require_once '../config/database.php';

header('Content-Type: application/json');

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Não autorizado'
    ]);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Verificar se a tabela existe
$tableExists = false;
try {
    $check = $pdo->query("SHOW TABLES LIKE 'notifications'");
    $tableExists = $check->rowCount() > 0;
} catch (Exception $e) {
    $tableExists = false;
}

// Se tabela não existe, retornar dados vazios
if (!$tableExists) {
    echo json_encode([
        'success' => true,
        'message' => 'Tabelas não configuradas.',
        'notifications' => [],
        'unread_count' => 0
    ]);
    exit;
}

require_once '../includes/notification_system.php';
$notificationSystem = new NotificationSystem($pdo, $userId);

try {
    switch ($action) {
        case 'get_unread':
            $limit = (int)($_GET['limit'] ?? 50);
            $notifications = $notificationSystem->getUnread($limit);
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'count' => count($notifications)
            ]);
            break;
            
        case 'count_unread':
            $count = $notificationSystem->countUnread();
            
            echo json_encode([
                'success' => true,
                'count' => $count
            ]);
            break;
            
        case 'mark_read':
            $notificationId = (int)($_POST['id'] ?? 0);
            
            if (!$notificationId) {
                throw new Exception('ID da notificação é obrigatório');
            }
            
            $success = $notificationSystem->markAsRead($notificationId);
            
            echo json_encode([
                'success' => $success,
                'message' => 'Notificação marcada como lida'
            ]);
            break;
            
        case 'mark_all_read':
            $success = $notificationSystem->markAllAsRead();
            
            echo json_encode([
                'success' => $success,
                'message' => 'Todas as notificações marcadas como lidas'
            ]);
            break;
            
        case 'get_recent':
            $hours = (int)($_GET['hours'] ?? 24);
            
            $stmt = $pdo->prepare("
                SELECT * FROM notifications
                WHERE user_id = ?
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                ORDER BY created_at DESC
                LIMIT 100
            ");
            
            $stmt->execute([$userId, $hours]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications
            ]);
            break;
            
        case 'delete':
            $notificationId = (int)($_POST['id'] ?? 0);
            
            if (!$notificationId) {
                throw new Exception('ID da notificação é obrigatório');
            }
            
            $stmt = $pdo->prepare("
                DELETE FROM notifications
                WHERE id = ? AND user_id = ?
            ");
            
            $success = $stmt->execute([$notificationId, $userId]);
            
            echo json_encode([
                'success' => $success,
                'message' => 'Notificação excluída'
            ]);
            break;
            
        case 'clean_old':
            $days = (int)($_POST['days'] ?? 30);
            $deleted = $notificationSystem->cleanOldNotifications($days);
            
            echo json_encode([
                'success' => true,
                'deleted' => $deleted,
                'message' => "{$deleted} notificações antigas removidas"
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
