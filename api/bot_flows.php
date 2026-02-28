<?php
/**
 * API de Fluxos (builder tipo Typebot)
 * CRUD de fluxo, nodes/edges e publicação de versão
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

// Verificar permissão - Admin OU Atendente com permissão
$isAdmin = isAdmin();
$isAttendant = isAttendant();
$canManageFlows = false;
$userId = $_SESSION['user_id'];
$ownerType = 'supervisor';
$ownerId = $userId;

if ($isAdmin) {
    $canManageFlows = true;
    $ownerType = 'supervisor';
    $ownerId = $userId;
} elseif ($isAttendant) {
    // Verificar se atendente tem permissão para gerenciar fluxos
    $stmt = $pdo->prepare("
        SELECT su.can_manage_flows, ai.instance_name, ai.status
        FROM supervisor_users su
        LEFT JOIN attendant_instances ai ON su.id = ai.attendant_id
        WHERE su.id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $attendantData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($attendantData) {
        $hasPermission = (bool)($attendantData['can_manage_flows'] ?? false);
        $hasInstance = !empty($attendantData['instance_name']) && $attendantData['status'] === 'connected';
        
        if ($hasPermission && $hasInstance) {
            $canManageFlows = true;
            $ownerType = 'attendant';
            $ownerId = $userId;
        }
    }
}

if (!$canManageFlows) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}
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

$action = $method === 'GET' ? ($_GET['action'] ?? 'list') : ($input['action'] ?? '');

function jsonResponse($data, int $code = 200)
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

try {
    switch ($action) {
        case 'list':
            // Buscar bot_flows baseado no tipo de proprietário
            if ($ownerType === 'attendant') {
                // Atendente: buscar apenas seus próprios fluxos
                $stmt = $pdo->prepare("SELECT * FROM bot_flows WHERE owner_type = 'attendant' AND owner_id = ? ORDER BY updated_at DESC");
                $stmt->execute([$ownerId]);
            } else {
                // Supervisor: buscar fluxos do supervisor (compatibilidade com user_id ou owner_type)
                $stmt = $pdo->prepare("SELECT * FROM bot_flows WHERE (user_id = ? OR (owner_type = 'supervisor' AND owner_id = ?)) ORDER BY updated_at DESC");
                $stmt->execute([$userId, $userId]);
            }
            $botFlows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Buscar automation_flows (ainda não migrados) - apenas para supervisores
            $automationFlows = [];
            if ($ownerType === 'supervisor') {
                $stmt = $pdo->prepare("SELECT *, 'automation' as flow_type FROM automation_flows WHERE user_id = ? ORDER BY updated_at DESC");
                $stmt->execute([$userId]);
                $automationFlows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Combinar e ordenar por updated_at
            $allFlows = array_merge($botFlows, $automationFlows);
            usort($allFlows, function($a, $b) {
                return strtotime($b['updated_at']) - strtotime($a['updated_at']);
            });
            
            jsonResponse(['success' => true, 'flows' => $allFlows]);
            break;

        case 'get':
            $flowId = intval($_GET['id'] ?? 0);
            if ($flowId <= 0) {
                throw new Exception('ID inválido');
            }
            $stmt = $pdo->prepare("SELECT * FROM bot_flows WHERE id = ? AND user_id = ?");
            $stmt->execute([$flowId, $userId]);
            $flow = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$flow) {
                throw new Exception('Fluxo não encontrado');
            }

            $nodesStmt = $pdo->prepare("SELECT * FROM bot_nodes WHERE flow_id = ? ORDER BY sort_order ASC, id ASC");
            $nodesStmt->execute([$flowId]);
            $nodes = $nodesStmt->fetchAll(PDO::FETCH_ASSOC);

            $edgesStmt = $pdo->prepare("SELECT * FROM bot_edges WHERE flow_id = ? ORDER BY sort_order ASC, id ASC");
            $edgesStmt->execute([$flowId]);
            $edgesRaw = $edgesStmt->fetchAll(PDO::FETCH_ASSOC);
            $edges = [];
            foreach ($edgesRaw as $e) {
                $edges[] = array_merge($e, [
                    'condition' => $e['condition_json'] ? json_decode($e['condition_json'], true) : new stdClass()
                ]);
            }

            jsonResponse(['success' => true, 'flow' => $flow, 'nodes' => $nodes, 'edges' => $edges]);
            break;

        case 'create':
            $name = sanitize($input['name'] ?? '');
            $description = trim($input['description'] ?? '');
            $flowType = in_array($input['flow_type'] ?? '', ['conversational', 'automation']) ? $input['flow_type'] : 'conversational';
            
            if (empty($name)) {
                throw new Exception('Nome é obrigatório');
            }
            
            $pdo->beginTransaction();
            
            // Criar flow em bot_flows com owner_type e owner_id
            $stmt = $pdo->prepare("INSERT INTO bot_flows (user_id, owner_type, owner_id, name, description, status, flow_type) VALUES (?, ?, ?, ?, ?, 'draft', ?)");
            $stmt->execute([$userId, $ownerType, $ownerId, $name, $description, $flowType]);
            $flowId = $pdo->lastInsertId();
            
            // Se for automation, criar também em automation_flows (apenas supervisores)
            if ($flowType === 'automation' && $ownerType === 'supervisor') {
                $stmtAuto = $pdo->prepare("INSERT INTO automation_flows (user_id, bot_flow_id, name, description, status) VALUES (?, ?, ?, ?, 'paused')");
                $stmtAuto->execute([$userId, $flowId, $name, $description]);
            }
            
            $pdo->commit();
            jsonResponse(['success' => true, 'flow_id' => $flowId]);
            break;

        case 'update':
            $flowId = intval($input['id'] ?? 0);
            $name = sanitize($input['name'] ?? '');
            $description = trim($input['description'] ?? '');
            $status = in_array($input['status'] ?? '', ['draft', 'published', 'paused']) ? $input['status'] : 'draft';
            if ($flowId <= 0 || empty($name)) {
                throw new Exception('Dados inválidos');
            }
            $stmt = $pdo->prepare("UPDATE bot_flows SET name = ?, description = ?, status = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$name, $description, $status, $flowId, $userId]);
            jsonResponse(['success' => true, 'message' => 'Fluxo atualizado']);
            break;

        case 'save_layout':
            $flowId = intval($input['id'] ?? 0);
            $nodes = $input['nodes'] ?? [];
            $edges = $input['edges'] ?? [];
            if ($flowId <= 0) {
                throw new Exception('ID inválido');
            }

            $pdo->beginTransaction();

            $deleteNodes = $pdo->prepare("DELETE FROM bot_nodes WHERE flow_id = ?");
            $deleteEdges = $pdo->prepare("DELETE FROM bot_edges WHERE flow_id = ?");
            $deleteEdges->execute([$flowId]);
            $deleteNodes->execute([$flowId]);

            $idMap = [];
            $insertNode = $pdo->prepare("INSERT INTO bot_nodes (flow_id, type, label, config, pos_x, pos_y, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($nodes as $idx => $node) {
                $insertNode->execute([
                    $flowId,
                    $node['type'] ?? 'message',
                    $node['label'] ?? null,
                    isset($node['config']) ? json_encode($node['config'], JSON_UNESCAPED_UNICODE) : null,
                    intval($node['x'] ?? 0),
                    intval($node['y'] ?? 0),
                    $idx
                ]);
                $newId = (int)$pdo->lastInsertId();
                $clientId = $node['id'] ?? $idx;
                $idMap[$clientId] = $newId;
            }

            $insertEdge = $pdo->prepare("INSERT INTO bot_edges (flow_id, from_node_id, to_node_id, condition_json, sort_order) VALUES (?, ?, ?, ?, ?)");
            foreach ($edges as $idx => $edge) {
                $fromClient = $edge['from'] ?? null;
                $toClient = $edge['to'] ?? null;
                $fromId = ($fromClient !== null && isset($idMap[$fromClient])) ? $idMap[$fromClient] : null;
                $toId = ($toClient !== null && isset($idMap[$toClient])) ? $idMap[$toClient] : null;
                if (!$fromId || !$toId) {
                    continue;
                }
                $insertEdge->execute([
                    $flowId,
                    $fromId,
                    $toId,
                    isset($edge['condition']) ? json_encode($edge['condition'], JSON_UNESCAPED_UNICODE) : null,
                    $idx
                ]);
            }

            $pdo->commit();
            jsonResponse(['success' => true, 'message' => 'Layout salvo', 'id_map' => $idMap]);
            break;

        case 'publish':
            $flowId = intval($input['id'] ?? 0);
            if ($flowId <= 0) {
                throw new Exception('ID inválido');
            }

            $stmt = $pdo->prepare("SELECT * FROM bot_flows WHERE id = ? AND user_id = ?");
            $stmt->execute([$flowId, $userId]);
            $flow = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$flow) {
                throw new Exception('Fluxo não encontrado');
            }

            $nodesStmt = $pdo->prepare("SELECT * FROM bot_nodes WHERE flow_id = ? ORDER BY sort_order ASC, id ASC");
            $nodesStmt->execute([$flowId]);
            $nodes = $nodesStmt->fetchAll(PDO::FETCH_ASSOC);

            $edgesStmt = $pdo->prepare("SELECT * FROM bot_edges WHERE flow_id = ? ORDER BY sort_order ASC, id ASC");
            $edgesStmt->execute([$flowId]);
            $edges = $edgesStmt->fetchAll(PDO::FETCH_ASSOC);

            $nextVersion = intval($flow['version']) + 1;
            $payload = [
                'flow' => $flow,
                'nodes' => $nodes,
                'edges' => $edges,
            ];

            $pdo->beginTransaction();
            $insertVersion = $pdo->prepare("INSERT INTO bot_flow_versions (flow_id, version, name, description, payload_json) VALUES (?, ?, ?, ?, ?)");
            $insertVersion->execute([$flowId, $nextVersion, $flow['name'], $flow['description'], json_encode($payload, JSON_UNESCAPED_UNICODE)]);

            $updateFlow = $pdo->prepare("UPDATE bot_flows SET status = 'published', version = ?, published_version = ?, is_published = 1 WHERE id = ? AND user_id = ?");
            $updateFlow->execute([$nextVersion, $nextVersion, $flowId, $userId]);

            $pdo->commit();
            jsonResponse(['success' => true, 'message' => 'Fluxo publicado', 'version' => $nextVersion]);
            break;

        case 'delete':
            $flowId = intval($input['id'] ?? 0);
            if ($flowId <= 0) {
                throw new Exception('ID inválido');
            }
            $stmt = $pdo->prepare("DELETE FROM bot_flows WHERE id = ? AND user_id = ?");
            $stmt->execute([$flowId, $userId]);
            jsonResponse(['success' => true, 'message' => 'Fluxo excluído']);
            break;
        
        case 'save_automation':
            $flowId = intval($input['flow_id'] ?? 0);
            $automationFlowId = intval($input['automation_flow_id'] ?? 0);
            $triggerType = $input['trigger_type'] ?? '';
            $triggerConfig = $input['trigger_config'] ?? [];
            $agentConfig = $input['agent_config'] ?? [];
            $actionsConfig = $input['actions_config'] ?? [];
            
            if ($flowId <= 0) {
                throw new Exception('ID do flow inválido');
            }
            
            $pdo->beginTransaction();
            
            // Verificar se automation_flow já existe
            if ($automationFlowId > 0) {
                // Atualizar existente
                $stmt = $pdo->prepare("
                    UPDATE automation_flows 
                    SET trigger_type = ?, trigger_config = ?, agent_config = ?, actions_config = ?, updated_at = NOW()
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([
                    $triggerType,
                    json_encode($triggerConfig, JSON_UNESCAPED_UNICODE),
                    json_encode($agentConfig, JSON_UNESCAPED_UNICODE),
                    json_encode($actionsConfig, JSON_UNESCAPED_UNICODE),
                    $automationFlowId,
                    $userId
                ]);
            } else {
                // Criar novo
                $stmtFlow = $pdo->prepare("SELECT name, description FROM bot_flows WHERE id = ? AND user_id = ?");
                $stmtFlow->execute([$flowId, $userId]);
                $flow = $stmtFlow->fetch(PDO::FETCH_ASSOC);
                
                if (!$flow) {
                    throw new Exception('Flow não encontrado');
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO automation_flows (user_id, bot_flow_id, name, description, trigger_type, trigger_config, agent_config, actions_config, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'paused')
                ");
                $stmt->execute([
                    $userId,
                    $flowId,
                    $flow['name'],
                    $flow['description'],
                    $triggerType,
                    json_encode($triggerConfig, JSON_UNESCAPED_UNICODE),
                    json_encode($agentConfig, JSON_UNESCAPED_UNICODE),
                    json_encode($actionsConfig, JSON_UNESCAPED_UNICODE)
                ]);
            }
            
            $pdo->commit();
            jsonResponse(['success' => true, 'message' => 'Automation flow salvo com sucesso']);
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Ação não suportada'], 400);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
}
