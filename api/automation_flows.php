<?php
/**
 * API de Fluxos de Automação com IA
 * Responsável por CRUD de fluxos e consulta de logs
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$canManageFlows = isAdmin();
if (!$canManageFlows) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$input = [];

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $input = json_decode($rawInput, true);
    }

    if (!$input) {
        $input = $_POST;
    }
}

$action = '';
if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';
} else {
    $action = $input['action'] ?? '';
}

$allowedTriggerTypes = ['keyword', 'first_message', 'off_hours', 'no_response', 'manual'];

try {
    switch ($action) {
        case 'list':
            $statusFilter = $_GET['status'] ?? null;
            $params = [$userId];
            $query = "SELECT * FROM automation_flows WHERE user_id = ?";

            if ($statusFilter && in_array($statusFilter, ['active', 'paused'])) {
                $query .= " AND status = ?";
                $params[] = $statusFilter;
            }

            $query .= " ORDER BY updated_at DESC";

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $flows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($flows as &$flow) {
                $flow['trigger_config'] = $flow['trigger_config'] ? json_decode($flow['trigger_config'], true) : new stdClass();
                $flow['agent_config'] = $flow['agent_config'] ? json_decode($flow['agent_config'], true) : new stdClass();
                $flow['action_config'] = $flow['action_config'] ? json_decode($flow['action_config'], true) : new stdClass();
            }

            echo json_encode(['success' => true, 'flows' => $flows]);
            break;

        case 'get':
            $flowId = intval($_GET['id'] ?? 0);
            if ($flowId <= 0) {
                throw new Exception('ID inválido');
            }

            $stmt = $pdo->prepare("SELECT * FROM automation_flows WHERE id = ? AND user_id = ?");
            $stmt->execute([$flowId, $userId]);
            $flow = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$flow) {
                throw new Exception('Fluxo não encontrado');
            }

            $flow['trigger_config'] = $flow['trigger_config'] ? json_decode($flow['trigger_config'], true) : new stdClass();
            $flow['agent_config'] = $flow['agent_config'] ? json_decode($flow['agent_config'], true) : new stdClass();
            $flow['action_config'] = $flow['action_config'] ? json_decode($flow['action_config'], true) : new stdClass();

            echo json_encode(['success' => true, 'flow' => $flow]);
            break;

        case 'create':
            requirePost();
            $name = sanitize($input['name'] ?? '');
            $description = trim($input['description'] ?? '');
            $triggerType = $input['trigger_type'] ?? 'keyword';
            $status = in_array($input['status'] ?? '', ['active', 'paused']) ? $input['status'] : 'paused';
            $triggerConfig = $input['trigger_config'] ?? [];
            $agentConfig = $input['agent_config'] ?? [];
            $actionConfig = $input['action_config'] ?? [];

            if (empty($name)) {
                throw new Exception('Nome é obrigatório');
            }

            if (!in_array($triggerType, $allowedTriggerTypes)) {
                throw new Exception('Tipo de gatilho inválido');
            }

            $stmt = $pdo->prepare("INSERT INTO automation_flows (user_id, name, description, status, trigger_type, trigger_config, agent_config, action_config) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $userId,
                $name,
                $description,
                $status,
                $triggerType,
                json_encode($triggerConfig, JSON_UNESCAPED_UNICODE),
                json_encode($agentConfig, JSON_UNESCAPED_UNICODE),
                json_encode($actionConfig, JSON_UNESCAPED_UNICODE)
            ]);

            echo json_encode(['success' => true, 'message' => 'Fluxo criado com sucesso', 'flow_id' => $pdo->lastInsertId()]);
            break;

        case 'update':
            requirePost();
            $flowId = intval($input['id'] ?? 0);
            $name = sanitize($input['name'] ?? '');
            $description = trim($input['description'] ?? '');
            $triggerType = $input['trigger_type'] ?? 'keyword';
            $status = in_array($input['status'] ?? '', ['active', 'paused']) ? $input['status'] : 'paused';
            $triggerConfig = $input['trigger_config'] ?? [];
            $agentConfig = $input['agent_config'] ?? [];
            $actionConfig = $input['action_config'] ?? [];

            if ($flowId <= 0 || empty($name)) {
                throw new Exception('ID e nome são obrigatórios');
            }

            if (!in_array($triggerType, $allowedTriggerTypes)) {
                throw new Exception('Tipo de gatilho inválido');
            }

            $stmt = $pdo->prepare("SELECT id FROM automation_flows WHERE id = ? AND user_id = ?");
            $stmt->execute([$flowId, $userId]);
            if (!$stmt->fetch()) {
                throw new Exception('Fluxo não encontrado');
            }

            $stmt = $pdo->prepare("UPDATE automation_flows SET name = ?, description = ?, status = ?, trigger_type = ?, trigger_config = ?, agent_config = ?, action_config = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([
                $name,
                $description,
                $status,
                $triggerType,
                json_encode($triggerConfig, JSON_UNESCAPED_UNICODE),
                json_encode($agentConfig, JSON_UNESCAPED_UNICODE),
                json_encode($actionConfig, JSON_UNESCAPED_UNICODE),
                $flowId,
                $userId
            ]);

            echo json_encode(['success' => true, 'message' => 'Fluxo atualizado com sucesso']);
            break;

        case 'delete':
            requirePost();
            $flowId = intval($input['id'] ?? 0);
            if ($flowId <= 0) {
                throw new Exception('ID inválido');
            }

            $stmt = $pdo->prepare("DELETE FROM automation_flows WHERE id = ? AND user_id = ?");
            $stmt->execute([$flowId, $userId]);

            echo json_encode(['success' => true, 'message' => 'Fluxo removido']);
            break;

        case 'toggle_status':
            requirePost();
            $flowId = intval($input['id'] ?? 0);
            if ($flowId <= 0) {
                throw new Exception('ID inválido');
            }

            $stmt = $pdo->prepare("UPDATE automation_flows SET status = IF(status = 'active', 'paused', 'active') WHERE id = ? AND user_id = ?");
            $stmt->execute([$flowId, $userId]);

            echo json_encode(['success' => true, 'message' => 'Status atualizado']);
            break;

        case 'logs':
            $flowId = intval($_GET['flow_id'] ?? 0);
            if ($flowId <= 0) {
                throw new Exception('ID de fluxo inválido');
            }

            $limit = intval($_GET['limit'] ?? 20);
            $limit = max(1, min($limit, 100));

            $stmt = $pdo->prepare("SELECT afl.* FROM automation_flow_logs afl INNER JOIN automation_flows af ON af.id = afl.flow_id WHERE af.user_id = ? AND afl.flow_id = ? ORDER BY afl.executed_at DESC LIMIT ?");
            $stmt->bindValue(1, $userId, PDO::PARAM_INT);
            $stmt->bindValue(2, $flowId, PDO::PARAM_INT);
            $stmt->bindValue(3, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'logs' => $logs]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
            break;
    }
} catch (Exception $e) {
    error_log('Erro na API de fluxos: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function requirePost(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
        exit;
    }
}
