<?php
/**
 * API DE ESTATÍSTICAS DO KANBAN
 * Retorna estatísticas e métricas do quadro Kanban
 * 
 * @author MAC-IP TECNOLOGIA
 * @version 1.0
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'] ?? 'user';

// Determinar o owner_id (para atendentes, usar o supervisor_id)
if ($userType === 'attendant') {
    $stmt = $pdo->prepare("SELECT supervisor_id FROM supervisor_users WHERE id = ?");
    $stmt->execute([$userId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $ownerId = $data['supervisor_id'] ?? $userId;
} else {
    $ownerId = $userId;
}

try {
    $boardId = $_GET['board_id'] ?? null;
    
    if (!$boardId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'board_id é obrigatório']);
        exit;
    }
    
    // Verificar se o quadro pertence ao usuário
    $stmt = $pdo->prepare("SELECT id FROM kanban_boards WHERE id = ? AND user_id = ?");
    $stmt->execute([$boardId, $ownerId]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Quadro não encontrado']);
        exit;
    }
    
    // Total de cards ativos
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM kanban_cards kc
        INNER JOIN kanban_columns kcol ON kc.column_id = kcol.id
        WHERE kcol.board_id = ? AND kc.is_archived = 0
    ");
    $stmt->execute([$boardId]);
    $totalCards = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Valor total dos cards
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(kc.value), 0) as total_value
        FROM kanban_cards kc
        INNER JOIN kanban_columns kcol ON kc.column_id = kcol.id
        WHERE kcol.board_id = ? AND kc.is_archived = 0
    ");
    $stmt->execute([$boardId]);
    $totalValue = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'];
    
    // Cards por coluna
    $stmt = $pdo->prepare("
        SELECT 
            kcol.id,
            kcol.name,
            kcol.color,
            COUNT(kc.id) as card_count,
            COALESCE(SUM(kc.value), 0) as column_value
        FROM kanban_columns kcol
        LEFT JOIN kanban_cards kc ON kcol.id = kc.column_id AND kc.is_archived = 0
        WHERE kcol.board_id = ?
        GROUP BY kcol.id
        ORDER BY kcol.position
    ");
    $stmt->execute([$boardId]);
    $columnStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Cards por prioridade
    $stmt = $pdo->prepare("
        SELECT 
            kc.priority,
            COUNT(*) as count
        FROM kanban_cards kc
        INNER JOIN kanban_columns kcol ON kc.column_id = kcol.id
        WHERE kcol.board_id = ? AND kc.is_archived = 0
        GROUP BY kc.priority
    ");
    $stmt->execute([$boardId]);
    $priorityStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Cards atrasados
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as overdue_count
        FROM kanban_cards kc
        INNER JOIN kanban_columns kcol ON kc.column_id = kcol.id
        WHERE kcol.board_id = ? 
        AND kc.is_archived = 0 
        AND kc.due_date IS NOT NULL 
        AND kc.due_date < NOW()
    ");
    $stmt->execute([$boardId]);
    $overdueCount = $stmt->fetch(PDO::FETCH_ASSOC)['overdue_count'];
    
    // Cards por responsável
    $stmt = $pdo->prepare("
        SELECT 
            CASE 
                WHEN kc.assigned_type = 'attendant' THEN (SELECT name FROM supervisor_users WHERE id = kc.assigned_to)
                ELSE (SELECT name FROM users WHERE id = kc.assigned_to)
            END as assigned_name,
            COUNT(*) as count
        FROM kanban_cards kc
        INNER JOIN kanban_columns kcol ON kc.column_id = kcol.id
        WHERE kcol.board_id = ? AND kc.is_archived = 0 AND kc.assigned_to IS NOT NULL
        GROUP BY kc.assigned_to, kc.assigned_type
    ");
    $stmt->execute([$boardId]);
    $assigneeStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Taxa de conversão (cards em colunas finais)
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN kcol.is_final = 1 THEN 1 END) as converted,
            COUNT(*) as total
        FROM kanban_cards kc
        INNER JOIN kanban_columns kcol ON kc.column_id = kcol.id
        WHERE kcol.board_id = ? AND kc.is_archived = 0
    ");
    $stmt->execute([$boardId]);
    $conversionData = $stmt->fetch(PDO::FETCH_ASSOC);
    $conversionRate = $conversionData['total'] > 0 
        ? round(($conversionData['converted'] / $conversionData['total']) * 100, 2) 
        : 0;
    
    // Tempo médio por coluna (últimos 30 dias)
    $stmt = $pdo->prepare("
        SELECT 
            kcol.name as column_name,
            AVG(TIMESTAMPDIFF(HOUR, kc.created_at, kc.updated_at)) as avg_hours
        FROM kanban_cards kc
        INNER JOIN kanban_columns kcol ON kc.column_id = kcol.id
        WHERE kcol.board_id = ? 
        AND kc.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY kcol.id
    ");
    $stmt->execute([$boardId]);
    $timeStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'statistics' => [
            'total_cards' => (int)$totalCards,
            'total_value' => (float)$totalValue,
            'overdue_count' => (int)$overdueCount,
            'conversion_rate' => (float)$conversionRate,
            'columns' => $columnStats,
            'priorities' => $priorityStats,
            'assignees' => $assigneeStats,
            'time_stats' => $timeStats
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
