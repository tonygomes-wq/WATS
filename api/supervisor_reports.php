<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../config/database.php';

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

// Verificar se é admin ou supervisor
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
$is_supervisor = isset($_SESSION['is_supervisor']) && $_SESSION['is_supervisor'] == 1;

if (!$is_admin && !$is_supervisor) {
    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
    exit;
}

// Obter dados do POST
$input = json_decode(file_get_contents('php://input'), true);

$period = $input['period'] ?? 'week';
$user_id = $input['user_id'] ?? '';
$status = $input['status'] ?? '';
$start_date = $input['start_date'] ?? '';
$end_date = $input['end_date'] ?? '';
$page = intval($input['page'] ?? 1);
$per_page = 20;

try {
    // Calcular datas baseado no período
    $dates = calculateDates($period, $start_date, $end_date);
    
    // Buscar métricas
    $metrics = getMetrics($pdo, $dates, $user_id, $status);
    
    // Buscar dados para gráficos
    $charts = getChartsData($pdo, $dates, $user_id, $status);
    
    // Buscar conversas
    $conversations = getConversations($pdo, $dates, $user_id, $status, $page, $per_page);
    
    // Buscar métricas do Kanban
    $kanbanMetrics = getKanbanMetrics($pdo, $dates, $_SESSION['user_id']);
    
    echo json_encode([
        'success' => true,
        'metrics' => $metrics,
        'charts' => $charts,
        'conversations' => $conversations['data'],
        'kanban' => $kanbanMetrics,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $conversations['total_pages'],
            'total_records' => $conversations['total_records']
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// =====================================================
// FUNÇÕES AUXILIARES
// =====================================================

/**
 * Calcular datas baseado no período
 */
function calculateDates($period, $start_date, $end_date) {
    $now = new DateTime();
    
    switch ($period) {
        case 'today':
            $start = $now->format('Y-m-d 00:00:00');
            $end = $now->format('Y-m-d 23:59:59');
            break;
            
        case 'yesterday':
            $yesterday = $now->modify('-1 day');
            $start = $yesterday->format('Y-m-d 00:00:00');
            $end = $yesterday->format('Y-m-d 23:59:59');
            break;
            
        case 'week':
            $start = $now->modify('-7 days')->format('Y-m-d 00:00:00');
            $end = (new DateTime())->format('Y-m-d 23:59:59');
            break;
            
        case 'month':
            $start = $now->modify('-30 days')->format('Y-m-d 00:00:00');
            $end = (new DateTime())->format('Y-m-d 23:59:59');
            break;
            
        case 'custom':
            $start = $start_date ? $start_date . ' 00:00:00' : $now->modify('-7 days')->format('Y-m-d 00:00:00');
            $end = $end_date ? $end_date . ' 23:59:59' : (new DateTime())->format('Y-m-d 23:59:59');
            break;
            
        default:
            $start = $now->modify('-7 days')->format('Y-m-d 00:00:00');
            $end = (new DateTime())->format('Y-m-d 23:59:59');
    }
    
    return ['start' => $start, 'end' => $end];
}

/**
 * Buscar métricas gerais
 */
function getMetrics($pdo, $dates, $user_id, $status) {
    $where = ["c.created_at BETWEEN :start AND :end"];
    $params = [':start' => $dates['start'], ':end' => $dates['end']];
    
    if ($user_id) {
        $where[] = "c.user_id = :user_id";
        $params[':user_id'] = $user_id;
    }
    
    if ($status) {
        $where[] = "c.status = :status";
        $params[':status'] = $status;
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Total de atendimentos
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM conversations c WHERE $whereClause");
    $stmt->execute($params);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Atendimentos ativos
    $activeParams = $params;
    $activeParams[':active_status'] = 'active';
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM conversations c WHERE $whereClause AND c.status = :active_status");
    $stmt->execute($activeParams);
    $active = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Atendimentos resolvidos
    $resolvedParams = $params;
    $resolvedParams[':resolved_status'] = 'resolved';
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM conversations c WHERE $whereClause AND c.status = :resolved_status");
    $stmt->execute($resolvedParams);
    $resolved = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Tempo médio (em minutos)
    $stmt = $pdo->prepare("
        SELECT AVG(TIMESTAMPDIFF(MINUTE, c.created_at, c.updated_at)) as avg_time 
        FROM conversations c 
        WHERE $whereClause AND c.status IN ('resolved', 'closed')
    ");
    $stmt->execute($params);
    $avgTime = $stmt->fetch(PDO::FETCH_ASSOC)['avg_time'];
    $avgTimeFormatted = $avgTime ? round($avgTime) . 'min' : '0min';
    
    return [
        'total' => $total,
        'active' => $active,
        'resolved' => $resolved,
        'avg_time' => $avgTimeFormatted
    ];
}

/**
 * Buscar dados para gráficos
 */
function getChartsData($pdo, $dates, $user_id, $status) {
    $where = ["c.created_at BETWEEN :start AND :end"];
    $params = [':start' => $dates['start'], ':end' => $dates['end']];
    
    if ($user_id) {
        $where[] = "c.user_id = :user_id";
        $params[':user_id'] = $user_id;
    }
    
    if ($status) {
        $where[] = "c.status = :status";
        $params[':status'] = $status;
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Atendimentos por dia
    $stmt = $pdo->prepare("
        SELECT DATE(c.created_at) as date, COUNT(*) as count
        FROM conversations c
        WHERE $whereClause
        GROUP BY DATE(c.created_at)
        ORDER BY date
    ");
    $stmt->execute($params);
    $dailyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $daily = [
        'labels' => array_map(function($row) {
            return date('d/m', strtotime($row['date']));
        }, $dailyData),
        'values' => array_map(function($row) {
            return intval($row['count']);
        }, $dailyData)
    ];
    
    // Atendimentos por usuário
    $stmt = $pdo->prepare("
        SELECT u.name, COUNT(c.id) as count
        FROM conversations c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE $whereClause
        GROUP BY c.user_id, u.name
        ORDER BY count DESC
        LIMIT 10
    ");
    $stmt->execute($params);
    $userData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $byUser = [
        'labels' => array_map(function($row) {
            return $row['name'] ?? 'N/A';
        }, $userData),
        'values' => array_map(function($row) {
            return intval($row['count']);
        }, $userData),
        'label' => 'Atendimentos'
    ];
    
    // Atendimentos por status
    $stmt = $pdo->prepare("
        SELECT c.status, COUNT(*) as count
        FROM conversations c
        WHERE $whereClause
        GROUP BY c.status
    ");
    $stmt->execute($params);
    $statusData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $statusLabels = [
        'active' => 'Ativos',
        'resolved' => 'Resolvidos',
        'closed' => 'Encerrados'
    ];
    
    $byStatus = [
        'labels' => array_map(function($row) use ($statusLabels) {
            return $statusLabels[$row['status']] ?? $row['status'];
        }, $statusData),
        'values' => array_map(function($row) {
            return intval($row['count']);
        }, $statusData)
    ];
    
    // Tempo médio por usuário
    $stmt = $pdo->prepare("
        SELECT u.name, AVG(TIMESTAMPDIFF(MINUTE, c.created_at, c.updated_at)) as avg_time
        FROM conversations c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE $whereClause AND c.status IN ('resolved', 'closed')
        GROUP BY c.user_id, u.name
        ORDER BY avg_time DESC
        LIMIT 10
    ");
    $stmt->execute($params);
    $avgTimeData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $avgTimeByUser = [
        'labels' => array_map(function($row) {
            return $row['name'] ?? 'N/A';
        }, $avgTimeData),
        'values' => array_map(function($row) {
            return round(floatval($row['avg_time']));
        }, $avgTimeData),
        'label' => 'Minutos'
    ];
    
    return [
        'daily' => $daily,
        'by_user' => $byUser,
        'by_status' => $byStatus,
        'avg_time_by_user' => $avgTimeByUser
    ];
}

/**
 * Buscar conversas
 */
function getConversations($pdo, $dates, $user_id, $status, $page, $per_page) {
    $where = ["c.created_at BETWEEN :start AND :end"];
    $params = [':start' => $dates['start'], ':end' => $dates['end']];
    
    if ($user_id) {
        $where[] = "c.user_id = :user_id";
        $params[':user_id'] = $user_id;
    }
    
    if ($status) {
        $where[] = "c.status = :status";
        $params[':status'] = $status;
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Contar total
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM conversations c WHERE $whereClause");
    $stmt->execute($params);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total / $per_page);
    
    // Buscar dados
    $offset = ($page - 1) * $per_page;
    $params[':limit'] = $per_page;
    $params[':offset'] = $offset;
    
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.contact_name,
            c.contact_number,
            c.status,
            c.created_at,
            c.updated_at,
            u.name as user_name,
            COUNT(m.id) as message_count,
            TIMESTAMPDIFF(MINUTE, c.created_at, c.updated_at) as duration_minutes
        FROM conversations c
        LEFT JOIN users u ON c.user_id = u.id
        LEFT JOIN messages m ON c.id = m.conversation_id
        WHERE $whereClause
        GROUP BY c.id, c.contact_name, c.contact_number, c.status, c.created_at, c.updated_at, u.name
        ORDER BY c.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    
    $stmt->execute($params);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar dados
    $formatted = array_map(function($conv) {
        return [
            'id' => $conv['id'],
            'contact_name' => $conv['contact_name'],
            'contact_number' => $conv['contact_number'],
            'user_name' => $conv['user_name'],
            'status' => $conv['status'],
            'message_count' => $conv['message_count'],
            'duration' => $conv['duration_minutes'] ? $conv['duration_minutes'] . 'min' : '0min',
            'created_at' => $conv['created_at']
        ];
    }, $conversations);
    
    return [
        'data' => $formatted,
        'total_records' => $total,
        'total_pages' => $total_pages
    ];
}

/**
 * Buscar métricas do Kanban
 */
function getKanbanMetrics($pdo, $dates, $user_id) {
    // Verificar se tabelas do Kanban existem
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'kanban_cards'");
        if ($stmt->rowCount() == 0) {
            return null; // Kanban não configurado
        }
    } catch (Exception $e) {
        return null;
    }
    
    $params = [':start' => $dates['start'], ':end' => $dates['end'], ':user_id' => $user_id];
    
    // Total de cards
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM kanban_cards kc
        INNER JOIN kanban_columns kcol ON kc.column_id = kcol.id
        INNER JOIN kanban_boards kb ON kcol.board_id = kb.id
        WHERE kb.user_id = :user_id AND kc.created_at BETWEEN :start AND :end
    ");
    $stmt->execute($params);
    $totalCards = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Cards por coluna
    $stmt = $pdo->prepare("
        SELECT kcol.name, COUNT(kc.id) as count, kcol.color
        FROM kanban_columns kcol
        INNER JOIN kanban_boards kb ON kcol.board_id = kb.id
        LEFT JOIN kanban_cards kc ON kc.column_id = kcol.id AND kc.is_archived = 0
        WHERE kb.user_id = :user_id
        GROUP BY kcol.id, kcol.name, kcol.color
        ORDER BY kcol.position
    ");
    $stmt->execute([':user_id' => $user_id]);
    $cardsByColumn = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Valor total dos cards
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(kc.value), 0) as total_value
        FROM kanban_cards kc
        INNER JOIN kanban_columns kcol ON kc.column_id = kcol.id
        INNER JOIN kanban_boards kb ON kcol.board_id = kb.id
        WHERE kb.user_id = :user_id AND kc.is_archived = 0
    ");
    $stmt->execute([':user_id' => $user_id]);
    $totalValue = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'];
    
    // Cards criados no período
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as created
        FROM kanban_cards kc
        INNER JOIN kanban_columns kcol ON kc.column_id = kcol.id
        INNER JOIN kanban_boards kb ON kcol.board_id = kb.id
        WHERE kb.user_id = :user_id AND kc.created_at BETWEEN :start AND :end
    ");
    $stmt->execute($params);
    $createdInPeriod = $stmt->fetch(PDO::FETCH_ASSOC)['created'];
    
    // Cards finalizados (em colunas finais) no período
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as completed
        FROM kanban_cards kc
        INNER JOIN kanban_columns kcol ON kc.column_id = kcol.id
        INNER JOIN kanban_boards kb ON kcol.board_id = kb.id
        WHERE kb.user_id = :user_id 
        AND kcol.is_final = 1 
        AND kc.updated_at BETWEEN :start AND :end
    ");
    $stmt->execute($params);
    $completedInPeriod = $stmt->fetch(PDO::FETCH_ASSOC)['completed'];
    
    // Cards por prioridade
    $stmt = $pdo->prepare("
        SELECT kc.priority, COUNT(*) as count
        FROM kanban_cards kc
        INNER JOIN kanban_columns kcol ON kc.column_id = kcol.id
        INNER JOIN kanban_boards kb ON kcol.board_id = kb.id
        WHERE kb.user_id = :user_id AND kc.is_archived = 0
        GROUP BY kc.priority
    ");
    $stmt->execute([':user_id' => $user_id]);
    $cardsByPriority = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Cards vencidos
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as overdue
        FROM kanban_cards kc
        INNER JOIN kanban_columns kcol ON kc.column_id = kcol.id
        INNER JOIN kanban_boards kb ON kcol.board_id = kb.id
        WHERE kb.user_id = :user_id 
        AND kc.is_archived = 0 
        AND kcol.is_final = 0
        AND kc.due_date < CURDATE()
    ");
    $stmt->execute([':user_id' => $user_id]);
    $overdueCards = $stmt->fetch(PDO::FETCH_ASSOC)['overdue'];
    
    return [
        'total_cards' => intval($totalCards),
        'total_value' => floatval($totalValue),
        'created_in_period' => intval($createdInPeriod),
        'completed_in_period' => intval($completedInPeriod),
        'overdue_cards' => intval($overdueCards),
        'by_column' => [
            'labels' => array_column($cardsByColumn, 'name'),
            'values' => array_map('intval', array_column($cardsByColumn, 'count')),
            'colors' => array_column($cardsByColumn, 'color')
        ],
        'by_priority' => [
            'labels' => array_map(function($p) {
                $labels = ['low' => 'Baixa', 'normal' => 'Normal', 'high' => 'Alta', 'urgent' => 'Urgente'];
                return $labels[$p['priority']] ?? $p['priority'];
            }, $cardsByPriority),
            'values' => array_map('intval', array_column($cardsByPriority, 'count'))
        ]
    ];
}
