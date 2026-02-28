<?php
/**
 * API de Relatórios de Atendimento
 * Fornece dados para relatórios e análises
 */

session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Verificar se é supervisor ou admin
$user_type = $_SESSION['user_type'] ?? 'user';
$is_supervisor = isset($_SESSION['is_supervisor']) && $_SESSION['is_supervisor'] == 1;
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;

if (!$is_supervisor && !$is_admin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
    exit;
}

try {
    switch ($action) {
        case 'overview':
            getOverviewMetrics($pdo, $user_id);
            break;
            
        case 'by_attendant':
            getReportByAttendant($pdo, $user_id);
            break;
            
        case 'by_department':
            getReportByDepartment($pdo, $user_id);
            break;
            
        case 'timeline':
            getTimelineData($pdo, $user_id);
            break;
            
        case 'performance':
            getPerformanceData($pdo, $user_id);
            break;
            
        case 'attendants_list':
            getAttendantsList($pdo, $user_id);
            break;
            
        case 'departments_list':
            getDepartmentsList($pdo, $user_id);
            break;

        case 'peak_hours':
            getPeakHoursData($pdo, $user_id);
            break;
            
        case 'export':
            exportReport($pdo, $user_id);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ação inválida']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Obter métricas gerais
 */
function getOverviewMetrics($pdo, $supervisor_id) {
    $period = $_GET['period'] ?? 'last7days';
    $start_date = $_GET['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? null;
    $attendant_id = $_GET['attendant_id'] ?? null;
    $department_id = $_GET['department_id'] ?? null;
    $status = $_GET['status'] ?? null;
    
    // Calcular datas baseado no período
    $dates = calculatePeriodDates($period, $start_date, $end_date);
    
    // Verificar se tabela chat_conversations existe
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'chat_conversations'");
        $tableExists = $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $tableExists = false;
    }
    
    if (!$tableExists) {
        echo json_encode([
            'success' => true,
            'metrics' => [
                'total_conversations' => 0,
                'resolved_conversations' => 0,
                'open_conversations' => 0,
                'in_progress_conversations' => 0,
                'resolution_rate' => '0.0',
                'avg_resolution_time' => '0min',
                'avg_resolution_time_minutes' => 0,
                'avg_satisfaction' => 'N/A',
                'avg_satisfaction_raw' => 0
            ]
        ]);
        return;
    }
    
    // Verificar colunas disponíveis
    $columns = [];
    $stmt = $pdo->query("SHOW COLUMNS FROM chat_conversations");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
    }
    
    $hasResolvedAt = in_array('resolved_at', $columns);
    $hasSatisfaction = in_array('satisfaction_rating', $columns);
    $hasAssignedTo = in_array('assigned_to', $columns);
    
    // Query base adaptativa
    $sql = "
        SELECT 
            COUNT(DISTINCT c.id) as total_conversations,
            COUNT(DISTINCT CASE WHEN c.status IN ('resolved', 'closed') THEN c.id END) as resolved_conversations,
            " . ($hasResolvedAt ? "AVG(TIMESTAMPDIFF(MINUTE, c.created_at, c.resolved_at))" : "AVG(TIMESTAMPDIFF(MINUTE, c.created_at, c.updated_at))") . " as avg_resolution_time_minutes,
            " . ($hasSatisfaction ? "AVG(c.satisfaction_rating)" : "NULL") . " as avg_satisfaction,
            COUNT(DISTINCT CASE WHEN c.status = 'open' OR c.status = 'pending' THEN c.id END) as open_conversations,
            COUNT(DISTINCT CASE WHEN c.status = 'in_progress' OR c.status = 'active' THEN c.id END) as in_progress_conversations
        FROM chat_conversations c
    ";
    
    $params = [];
    $whereConditions = ["c.created_at BETWEEN ? AND ?"];
    $params[] = $dates['start'];
    $params[] = $dates['end'];
    
    // Filtrar por supervisor se tiver assigned_to
    if ($hasAssignedTo) {
        $sql .= " LEFT JOIN supervisor_users su ON c.assigned_to = su.id";
        $whereConditions[] = "(su.supervisor_id = ? OR c.user_id = ?)";
        $params[] = $supervisor_id;
        $params[] = $supervisor_id;
    } else {
        $whereConditions[] = "c.user_id = ?";
        $params[] = $supervisor_id;
    }
    
    if ($attendant_id && $hasAssignedTo) {
        $whereConditions[] = "c.assigned_to = ?";
        $params[] = $attendant_id;
    }
    
    if ($status) {
        $whereConditions[] = "c.status = ?";
        $params[] = $status;
    }
    
    $sql .= " WHERE " . implode(" AND ", $whereConditions);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $metrics = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calcular taxa de resolução
    $resolution_rate = 0;
    if ($metrics['total_conversations'] > 0) {
        $resolution_rate = ($metrics['resolved_conversations'] / $metrics['total_conversations']) * 100;
    }
    
    // Formatar tempo médio
    $avg_time_formatted = formatMinutes($metrics['avg_resolution_time_minutes']);
    
    // Formatar satisfação
    $avg_satisfaction = $metrics['avg_satisfaction'] ? number_format($metrics['avg_satisfaction'], 1) : 'N/A';
    
    echo json_encode([
        'success' => true,
        'metrics' => [
            'total_conversations' => (int)($metrics['total_conversations'] ?? 0),
            'resolved_conversations' => (int)($metrics['resolved_conversations'] ?? 0),
            'open_conversations' => (int)($metrics['open_conversations'] ?? 0),
            'in_progress_conversations' => (int)($metrics['in_progress_conversations'] ?? 0),
            'resolution_rate' => number_format($resolution_rate, 1),
            'avg_resolution_time' => $avg_time_formatted,
            'avg_resolution_time_minutes' => (float)($metrics['avg_resolution_time_minutes'] ?? 0),
            'avg_satisfaction' => $avg_satisfaction,
            'avg_satisfaction_raw' => (float)($metrics['avg_satisfaction'] ?? 0)
        ]
    ]);
}

/**
 * Relatório por atendente
 */
function getReportByAttendant($pdo, $supervisor_id) {
    $period = $_GET['period'] ?? 'last7days';
    $start_date = $_GET['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? null;
    $department_id = $_GET['department_id'] ?? null;
    
    $dates = calculatePeriodDates($period, $start_date, $end_date);
    
    // Verificar se tabelas existem
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'supervisor_users'");
        if ($stmt->rowCount() == 0) {
            echo json_encode(['success' => true, 'attendants' => []]);
            return;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => true, 'attendants' => []]);
        return;
    }
    
    // Verificar se chat_conversations existe e suas colunas
    $hasConversations = false;
    $hasAssignedTo = false;
    $hasResolvedAt = false;
    $hasSatisfaction = false;
    
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'chat_conversations'");
        $hasConversations = $stmt->rowCount() > 0;
        
        if ($hasConversations) {
            $stmt = $pdo->query("SHOW COLUMNS FROM chat_conversations");
            $columns = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns[] = $row['Field'];
            }
            $hasAssignedTo = in_array('assigned_to', $columns);
            $hasResolvedAt = in_array('resolved_at', $columns);
            $hasSatisfaction = in_array('satisfaction_rating', $columns);
        }
    } catch (Exception $e) {
        // Ignorar
    }
    
    // Query simplificada
    $sql = "
        SELECT 
            su.id,
            su.name,
            su.email,
            su.status,
            0 as total_conversations,
            0 as resolved_conversations,
            0 as avg_resolution_time_minutes,
            0 as avg_satisfaction,
            0 as total_messages_sent
        FROM supervisor_users su
        WHERE su.supervisor_id = ?
        ORDER BY su.name
    ";
    
    $params = [$supervisor_id];
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $attendants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Se tiver conversas, buscar métricas para cada atendente
    if ($hasConversations && $hasAssignedTo) {
        foreach ($attendants as &$att) {
            $convSql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as resolved
                FROM chat_conversations 
                WHERE assigned_to = ? AND created_at BETWEEN ? AND ?";
            $convStmt = $pdo->prepare($convSql);
            $convStmt->execute([$att['id'], $dates['start'], $dates['end']]);
            $convData = $convStmt->fetch(PDO::FETCH_ASSOC);
            
            $att['total_conversations'] = (int)($convData['total'] ?? 0);
            $att['resolved_conversations'] = (int)($convData['resolved'] ?? 0);
        }
    }
    
    // Processar dados
    foreach ($attendants as &$attendant) {
        $total = (int)$attendant['total_conversations'];
        $resolved = (int)$attendant['resolved_conversations'];
        
        $attendant['resolution_rate'] = $total > 0 ? number_format(($resolved / $total) * 100, 1) : '0.0';
        $attendant['avg_time_formatted'] = formatMinutes($attendant['avg_resolution_time_minutes']);
        $attendant['satisfaction_formatted'] = $attendant['avg_satisfaction'] ? number_format($attendant['avg_satisfaction'], 1) : 'N/A';
    }
    
    echo json_encode([
        'success' => true,
        'attendants' => $attendants
    ]);
}

/**
 * Relatório por setor
 */
function getReportByDepartment($pdo, $supervisor_id) {
    // Verificar se tabela departments existe
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'departments'");
        if ($stmt->rowCount() == 0) {
            echo json_encode(['success' => true, 'departments' => []]);
            return;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => true, 'departments' => []]);
        return;
    }
    
    $period = $_GET['period'] ?? 'last7days';
    $start_date = $_GET['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? null;
    
    $dates = calculatePeriodDates($period, $start_date, $end_date);
    
    // Query simplificada
    $sql = "SELECT id, name, color FROM departments ORDER BY name";
    $stmt = $pdo->query($sql);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Adicionar métricas vazias
    foreach ($departments as &$dept) {
        $dept['total_attendants'] = 0;
        $dept['total_conversations'] = 0;
        $dept['resolved_conversations'] = 0;
        $dept['resolution_rate'] = '0.0';
        $dept['avg_time_formatted'] = '0min';
    }
    
    echo json_encode([
        'success' => true,
        'departments' => $departments
    ]);
}

/**
 * Dados para gráfico de linha do tempo
 */
function getTimelineData($pdo, $supervisor_id) {
    $period = $_GET['period'] ?? 'last7days';
    $start_date = $_GET['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? null;
    
    $dates = calculatePeriodDates($period, $start_date, $end_date);
    
    // Verificar se tabela existe
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'chat_conversations'");
        if ($stmt->rowCount() == 0) {
            echo json_encode(['success' => true, 'timeline' => []]);
            return;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => true, 'timeline' => []]);
        return;
    }
    
    // Query simplificada
    $sql = "
        SELECT 
            DATE(c.created_at) as date,
            COUNT(c.id) as total,
            SUM(CASE WHEN c.status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN c.status IN ('open', 'pending') THEN 1 ELSE 0 END) as open,
            SUM(CASE WHEN c.status IN ('in_progress', 'active') THEN 1 ELSE 0 END) as in_progress
        FROM chat_conversations c
        WHERE c.user_id = ?
          AND c.created_at BETWEEN ? AND ?
        GROUP BY DATE(c.created_at)
        ORDER BY date ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$supervisor_id, $dates['start'], $dates['end']]);
    $timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'timeline' => $timeline
    ]);
}

/**
 * Dados para gráficos de desempenho
 */
function getPerformanceData($pdo, $supervisor_id) {
    $period = $_GET['period'] ?? 'last7days';
    $start_date = $_GET['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? null;
    
    $dates = calculatePeriodDates($period, $start_date, $end_date);
    
    // Verificar se tabela existe
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'chat_conversations'");
        if ($stmt->rowCount() == 0) {
            echo json_encode(['success' => true, 'status_distribution' => [], 'satisfaction_distribution' => []]);
            return;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => true, 'status_distribution' => [], 'satisfaction_distribution' => []]);
        return;
    }
    
    // Status distribution - query simplificada
    $sql = "
        SELECT 
            c.status,
            COUNT(*) as count
        FROM chat_conversations c
        WHERE c.user_id = ?
          AND c.created_at BETWEEN ? AND ?
        GROUP BY c.status
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$supervisor_id, $dates['start'], $dates['end']]);
    $status_dist = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'status_distribution' => $status_dist,
        'satisfaction_distribution' => []
    ]);
}

/**
 * Listar atendentes para filtro
 */
function getAttendantsList($pdo, $supervisor_id) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'supervisor_users'");
        if ($stmt->rowCount() == 0) {
            echo json_encode(['success' => true, 'attendants' => []]);
            return;
        }
        
        $stmt = $pdo->prepare("
            SELECT id, name, email, status
            FROM supervisor_users
            WHERE supervisor_id = ?
            ORDER BY name ASC
        ");
        $stmt->execute([$supervisor_id]);
        $attendants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'attendants' => $attendants
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => true, 'attendants' => []]);
    }
}

/**
 * Listar setores para filtro
 */
function getDepartmentsList($pdo, $supervisor_id) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'departments'");
        if ($stmt->rowCount() == 0) {
            echo json_encode(['success' => true, 'departments' => []]);
            return;
        }
        
        $stmt = $pdo->query("SELECT id, name, color FROM departments ORDER BY name ASC");
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'departments' => $departments
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => true, 'departments' => []]);
    }
}

/**
 * Dados de horários de pico
 */
function getPeakHoursData($pdo, $supervisor_id) {
    $period = $_GET['period'] ?? 'last7days';
    $start_date = $_GET['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? null;
    
    $dates = calculatePeriodDates($period, $start_date, $end_date);
    
    // Verificar se tabela existe
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'chat_messages'");
        if ($stmt->rowCount() == 0) {
            echo json_encode(['success' => true, 'peak_hours' => []]);
            return;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => true, 'peak_hours' => []]);
        return;
    }
    
    // Agrupar mensagens por hora e dia da semana
    $sql = "
        SELECT 
            DAYOFWEEK(created_at) as day_of_week,
            HOUR(created_at) as hour_of_day,
            COUNT(*) as count
        FROM chat_messages
        WHERE created_at BETWEEN ? AND ?
        GROUP BY day_of_week, hour_of_day
        ORDER BY day_of_week, hour_of_day
    ";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$dates['start'], $dates['end']]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Processar para formato de matriz [dia][hora]
        $heatmap = [];
        for ($d = 1; $d <= 7; $d++) {
            for ($h = 0; $h < 24; $h++) {
                $heatmap[$d][$h] = 0;
            }
        }
        
        foreach ($data as $row) {
            $heatmap[$row['day_of_week']][$row['hour_of_day']] = (int)$row['count'];
        }
        
        echo json_encode([
            'success' => true,
            'peak_hours' => $heatmap
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Exportar relatório para CSV
 */
function exportReport($pdo, $supervisor_id) {
    // Implementar exportação CSV
    // Por enquanto, retornar sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Exportação em desenvolvimento'
    ]);
}

/**
 * Calcular datas baseado no período
 */
function calculatePeriodDates($period, $start_date = null, $end_date = null) {
    $end = date('Y-m-d 23:59:59');
    $start = date('Y-m-d 00:00:00');
    
    switch ($period) {
        case 'today':
            // Já definido acima
            break;
            
        case 'yesterday':
            $start = date('Y-m-d 00:00:00', strtotime('-1 day'));
            $end = date('Y-m-d 23:59:59', strtotime('-1 day'));
            break;
            
        case 'last7days':
            $start = date('Y-m-d 00:00:00', strtotime('-7 days'));
            break;
            
        case 'last30days':
            $start = date('Y-m-d 00:00:00', strtotime('-30 days'));
            break;
            
        case 'thismonth':
            $start = date('Y-m-01 00:00:00');
            break;
            
        case 'lastmonth':
            $start = date('Y-m-01 00:00:00', strtotime('first day of last month'));
            $end = date('Y-m-t 23:59:59', strtotime('last day of last month'));
            break;
            
        case 'custom':
            if ($start_date && $end_date) {
                $start = $start_date . ' 00:00:00';
                $end = $end_date . ' 23:59:59';
            }
            break;
    }
    
    return [
        'start' => $start,
        'end' => $end
    ];
}

/**
 * Formatar minutos em formato legível
 */
function formatMinutes($minutes) {
    if ($minutes === null || $minutes == 0) {
        return 'N/A';
    }
    
    $hours = floor($minutes / 60);
    $mins = round($minutes % 60);
    
    if ($hours > 0) {
        return $hours . 'h ' . $mins . 'min';
    }
    
    return $mins . 'min';
}
