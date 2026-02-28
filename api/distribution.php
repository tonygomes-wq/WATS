<?php
/**
 * API de Distribuição Automática de Conversas
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
$user_type = $_SESSION['user_type'] ?? 'user';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Verificar se é supervisor ou admin
$is_supervisor = isset($_SESSION['is_supervisor']) && $_SESSION['is_supervisor'] == 1;
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;

if (!$is_supervisor && !$is_admin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
    exit;
}

try {
    switch ($action) {
        case 'list_rules':
            listRules($pdo, $user_id);
            break;
            
        case 'get_rule':
            getRule($pdo, $user_id);
            break;
            
        case 'create_rule':
            createRule($pdo, $user_id);
            break;
            
        case 'update_rule':
            updateRule($pdo, $user_id);
            break;
            
        case 'delete_rule':
            deleteRule($pdo, $user_id);
            break;
            
        case 'toggle_rule':
            toggleRule($pdo, $user_id);
            break;
            
        case 'get_queue':
            getQueue($pdo, $user_id);
            break;
            
        case 'get_history':
            getHistory($pdo, $user_id);
            break;
            
        case 'get_stats':
            getStats($pdo, $user_id);
            break;
            
        case 'assign_manual':
            assignManual($pdo, $user_id);
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
 * Listar regras de distribuição
 */
function listRules($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM distribution_rules 
        WHERE supervisor_id = ? 
        ORDER BY priority DESC, name
    ");
    $stmt->execute([$user_id]);
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'rules' => $rules
    ]);
}

/**
 * Obter regra específica
 */
function getRule($pdo, $user_id) {
    $rule_id = $_GET['id'] ?? null;
    
    if (!$rule_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID não fornecido']);
        return;
    }
    
    $stmt = $pdo->prepare("
        SELECT * FROM distribution_rules 
        WHERE id = ? AND supervisor_id = ?
    ");
    $stmt->execute([$rule_id, $user_id]);
    $rule = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$rule) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Regra não encontrada']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'rule' => $rule
    ]);
}

/**
 * Criar regra de distribuição
 */
function createRule($pdo, $user_id) {
    $data = $_POST;
    
    // Validações
    if (empty($data['name']) || empty($data['type'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
        return;
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO distribution_rules 
        (supervisor_id, name, type, priority, max_conversations_per_attendant, 
         auto_assign, notify_attendant, work_hours_start, work_hours_end, 
         work_days, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $success = $stmt->execute([
        $user_id,
        $data['name'],
        $data['type'],
        $data['priority'] ?? 50,
        $data['max_conversations_per_attendant'] ?? 5,
        isset($data['auto_assign']) ? 1 : 0,
        isset($data['notify_attendant']) ? 1 : 0,
        $data['work_hours_start'] ?? '08:00:00',
        $data['work_hours_end'] ?? '18:00:00',
        $data['work_days'] ?? '1,2,3,4,5',
        isset($data['is_active']) ? 1 : 0
    ]);
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Regra criada com sucesso',
            'id' => $pdo->lastInsertId()
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao criar regra']);
    }
}

/**
 * Atualizar regra de distribuição
 */
function updateRule($pdo, $user_id) {
    $data = $_POST;
    $rule_id = $data['id'] ?? null;
    
    if (!$rule_id || empty($data['name']) || empty($data['type'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
        return;
    }
    
    // Verificar se regra existe e pertence ao supervisor
    $stmt = $pdo->prepare("SELECT id FROM distribution_rules WHERE id = ? AND supervisor_id = ?");
    $stmt->execute([$rule_id, $user_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Regra não encontrada']);
        return;
    }
    
    $stmt = $pdo->prepare("
        UPDATE distribution_rules 
        SET name = ?, type = ?, priority = ?, max_conversations_per_attendant = ?,
            auto_assign = ?, notify_attendant = ?, work_hours_start = ?, 
            work_hours_end = ?, work_days = ?, is_active = ?
        WHERE id = ? AND supervisor_id = ?
    ");
    
    $success = $stmt->execute([
        $data['name'],
        $data['type'],
        $data['priority'] ?? 50,
        $data['max_conversations_per_attendant'] ?? 5,
        isset($data['auto_assign']) ? 1 : 0,
        isset($data['notify_attendant']) ? 1 : 0,
        $data['work_hours_start'] ?? '08:00:00',
        $data['work_hours_end'] ?? '18:00:00',
        $data['work_days'] ?? '1,2,3,4,5',
        isset($data['is_active']) ? 1 : 0,
        $rule_id,
        $user_id
    ]);
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Regra atualizada com sucesso'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao atualizar regra']);
    }
}

/**
 * Deletar regra
 */
function deleteRule($pdo, $user_id) {
    $rule_id = $_POST['id'] ?? $_GET['id'] ?? null;
    
    if (!$rule_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID não fornecido']);
        return;
    }
    
    $stmt = $pdo->prepare("
        DELETE FROM distribution_rules 
        WHERE id = ? AND supervisor_id = ?
    ");
    
    $success = $stmt->execute([$rule_id, $user_id]);
    
    if ($success && $stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Regra excluída com sucesso'
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Regra não encontrada']);
    }
}

/**
 * Alternar status ativo/inativo
 */
function toggleRule($pdo, $user_id) {
    $rule_id = $_POST['id'] ?? null;
    
    if (!$rule_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID não fornecido']);
        return;
    }
    
    $stmt = $pdo->prepare("
        UPDATE distribution_rules 
        SET is_active = NOT is_active
        WHERE id = ? AND supervisor_id = ?
    ");
    
    $success = $stmt->execute([$rule_id, $user_id]);
    
    if ($success && $stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Status atualizado'
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Regra não encontrada']);
    }
}

/**
 * Obter fila de espera
 */
function getQueue($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT 
            q.*,
            d.name as department_name,
            d.color as department_color,
            TIMESTAMPDIFF(SECOND, q.queued_at, NOW()) as current_wait_seconds
        FROM conversation_queue q
        LEFT JOIN departments d ON q.department_id = d.id
        WHERE q.supervisor_id = ? AND q.status = 'waiting'
        ORDER BY q.priority DESC, q.queued_at ASC
    ");
    $stmt->execute([$user_id]);
    $queue = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar dados
    foreach ($queue as &$item) {
        $item['wait_time_formatted'] = formatSeconds($item['current_wait_seconds']);
    }
    
    echo json_encode([
        'success' => true,
        'queue' => $queue
    ]);
}

/**
 * Obter histórico de distribuição
 */
function getHistory($pdo, $user_id) {
    $limit = $_GET['limit'] ?? 50;
    
    $stmt = $pdo->prepare("
        SELECT 
            h.*,
            su.name as attendant_name,
            su.email as attendant_email,
            dr.name as rule_name
        FROM distribution_history h
        JOIN supervisor_users su ON h.attendant_id = su.id
        LEFT JOIN distribution_rules dr ON h.rule_id = dr.id
        WHERE h.supervisor_id = ?
        ORDER BY h.assigned_at DESC
        LIMIT ?
    ");
    $stmt->execute([$user_id, (int)$limit]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar dados
    foreach ($history as &$item) {
        $item['wait_time_formatted'] = formatSeconds($item['wait_time_seconds']);
        $item['assigned_at_formatted'] = date('d/m/Y H:i', strtotime($item['assigned_at']));
    }
    
    echo json_encode([
        'success' => true,
        'history' => $history
    ]);
}

/**
 * Obter estatísticas
 */
function getStats($pdo, $user_id) {
    // Regras ativas
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM distribution_rules 
        WHERE supervisor_id = ? AND is_active = 1
    ");
    $stmt->execute([$user_id]);
    $active_rules = $stmt->fetch()['count'];
    
    // Fila de espera
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM conversation_queue 
        WHERE supervisor_id = ? AND status = 'waiting'
    ");
    $stmt->execute([$user_id]);
    $queue_count = $stmt->fetch()['count'];
    
    // Distribuídas hoje
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM distribution_history 
        WHERE supervisor_id = ? AND DATE(assigned_at) = CURDATE()
    ");
    $stmt->execute([$user_id]);
    $distributed_today = $stmt->fetch()['count'];
    
    // Tempo médio de espera
    $stmt = $pdo->prepare("
        SELECT AVG(wait_time_seconds) as avg 
        FROM distribution_history 
        WHERE supervisor_id = ? AND DATE(assigned_at) = CURDATE()
    ");
    $stmt->execute([$user_id]);
    $avg_wait = $stmt->fetch()['avg'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'active_rules' => (int)$active_rules,
            'queue_count' => (int)$queue_count,
            'distributed_today' => (int)$distributed_today,
            'avg_wait_time' => round($avg_wait)
        ]
    ]);
}

/**
 * Atribuir conversa manualmente
 */
function assignManual($pdo, $user_id) {
    $queue_id = $_POST['queue_id'] ?? null;
    $attendant_id = $_POST['attendant_id'] ?? null;
    
    if (!$queue_id || !$attendant_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
        return;
    }
    
    // Verificar se item da fila existe
    $stmt = $pdo->prepare("
        SELECT * FROM conversation_queue 
        WHERE id = ? AND supervisor_id = ? AND status = 'waiting'
    ");
    $stmt->execute([$queue_id, $user_id]);
    $queue_item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$queue_item) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Item da fila não encontrado']);
        return;
    }
    
    // Verificar se atendente existe e pertence ao supervisor
    $stmt = $pdo->prepare("
        SELECT id FROM supervisor_users 
        WHERE id = ? AND supervisor_id = ? AND status = 'active'
    ");
    $stmt->execute([$attendant_id, $user_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Atendente não encontrado']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Atualizar fila
        $stmt = $pdo->prepare("
            UPDATE conversation_queue 
            SET status = 'assigned', assigned_to = ?, assigned_at = NOW(),
                wait_time_seconds = TIMESTAMPDIFF(SECOND, queued_at, NOW())
            WHERE id = ?
        ");
        $stmt->execute([$attendant_id, $queue_id]);
        
        // Atualizar conversa
        $stmt = $pdo->prepare("
            UPDATE chat_conversations 
            SET assigned_to = ?, status = 'in_progress'
            WHERE id = ?
        ");
        $stmt->execute([$attendant_id, $queue_item['conversation_id']]);
        
        // Registrar no histórico
        $stmt = $pdo->prepare("
            INSERT INTO distribution_history 
            (conversation_id, supervisor_id, attendant_id, distribution_type, wait_time_seconds)
            VALUES (?, ?, ?, 'manual', TIMESTAMPDIFF(SECOND, ?, NOW()))
        ");
        $stmt->execute([
            $queue_item['conversation_id'],
            $user_id,
            $attendant_id,
            $queue_item['queued_at']
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Conversa atribuída com sucesso'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao atribuir conversa: ' . $e->getMessage()]);
    }
}

/**
 * Formatar segundos em formato legível
 */
function formatSeconds($seconds) {
    if ($seconds < 60) {
        return $seconds . 's';
    }
    
    $minutes = floor($seconds / 60);
    $secs = $seconds % 60;
    
    if ($minutes < 60) {
        return $minutes . 'min ' . $secs . 's';
    }
    
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    
    return $hours . 'h ' . $mins . 'min';
}
