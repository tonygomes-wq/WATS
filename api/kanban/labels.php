<?php
/**
 * API DE LABELS/ETIQUETAS DO KANBAN
 * CRUD de etiquetas
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
$method = $_SERVER['REQUEST_METHOD'];

// Determinar o owner_id
if ($userType === 'attendant') {
    $stmt = $pdo->prepare("SELECT supervisor_id FROM supervisor_users WHERE id = ?");
    $stmt->execute([$userId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $ownerId = $data['supervisor_id'] ?? $userId;
} else {
    $ownerId = $userId;
}

try {
    switch ($method) {
        case 'GET':
            handleGet($pdo, $ownerId);
            break;
        case 'POST':
            handlePost($pdo, $ownerId);
            break;
        case 'PUT':
            handlePut($pdo, $ownerId);
            break;
        case 'DELETE':
            handleDelete($pdo, $ownerId);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * GET - Listar labels
 */
function handleGet($pdo, $ownerId) {
    $boardId = $_GET['board_id'] ?? null;
    
    if (!$boardId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'board_id é obrigatório']);
        return;
    }
    
    // Verificar se o quadro pertence ao usuário
    $stmt = $pdo->prepare("SELECT id FROM kanban_boards WHERE id = ? AND user_id = ?");
    $stmt->execute([$boardId, $ownerId]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Quadro não encontrado']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM kanban_labels WHERE board_id = ? ORDER BY name ASC");
    $stmt->execute([$boardId]);
    $labels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'labels' => $labels]);
}

/**
 * POST - Criar nova label
 */
function handlePost($pdo, $ownerId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $boardId = $input['board_id'] ?? null;
    $name = trim($input['name'] ?? '');
    $color = $input['color'] ?? '#6B7280';
    
    if (!$boardId || !$name) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'board_id e name são obrigatórios']);
        return;
    }
    
    // Verificar se o quadro pertence ao usuário
    $stmt = $pdo->prepare("SELECT id FROM kanban_boards WHERE id = ? AND user_id = ?");
    $stmt->execute([$boardId, $ownerId]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Quadro não encontrado']);
        return;
    }
    
    // Verificar se já existe label com mesmo nome
    $stmt = $pdo->prepare("SELECT id FROM kanban_labels WHERE board_id = ? AND name = ?");
    $stmt->execute([$boardId, $name]);
    
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Já existe uma etiqueta com este nome']);
        return;
    }
    
    // Inserir label
    $stmt = $pdo->prepare("INSERT INTO kanban_labels (board_id, name, color) VALUES (?, ?, ?)");
    $stmt->execute([$boardId, $name, $color]);
    
    $labelId = $pdo->lastInsertId();
    
    echo json_encode(['success' => true, 'label_id' => $labelId, 'message' => 'Etiqueta criada']);
}

/**
 * PUT - Atualizar label
 */
function handlePut($pdo, $ownerId) {
    $labelId = $_GET['id'] ?? null;
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$labelId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID da etiqueta é obrigatório']);
        return;
    }
    
    // Verificar se a label pertence ao usuário
    $stmt = $pdo->prepare("
        SELECT kl.id FROM kanban_labels kl
        INNER JOIN kanban_boards kb ON kl.board_id = kb.id
        WHERE kl.id = ? AND kb.user_id = ?
    ");
    $stmt->execute([$labelId, $ownerId]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Etiqueta não encontrada']);
        return;
    }
    
    // Atualizar label
    $updates = [];
    $params = [];
    
    if (isset($input['name'])) {
        $updates[] = "name = ?";
        $params[] = trim($input['name']);
    }
    
    if (isset($input['color'])) {
        $updates[] = "color = ?";
        $params[] = $input['color'];
    }
    
    if (!empty($updates)) {
        $params[] = $labelId;
        $sql = "UPDATE kanban_labels SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
    
    echo json_encode(['success' => true, 'message' => 'Etiqueta atualizada']);
}

/**
 * DELETE - Excluir label
 */
function handleDelete($pdo, $ownerId) {
    $labelId = $_GET['id'] ?? null;
    
    if (!$labelId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID da etiqueta é obrigatório']);
        return;
    }
    
    // Verificar se a label pertence ao usuário
    $stmt = $pdo->prepare("
        SELECT kl.id FROM kanban_labels kl
        INNER JOIN kanban_boards kb ON kl.board_id = kb.id
        WHERE kl.id = ? AND kb.user_id = ?
    ");
    $stmt->execute([$labelId, $ownerId]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Etiqueta não encontrada']);
        return;
    }
    
    // Remover associações com cards
    $stmt = $pdo->prepare("DELETE FROM kanban_card_labels WHERE label_id = ?");
    $stmt->execute([$labelId]);
    
    // Excluir label
    $stmt = $pdo->prepare("DELETE FROM kanban_labels WHERE id = ?");
    $stmt->execute([$labelId]);
    
    echo json_encode(['success' => true, 'message' => 'Etiqueta excluída']);
}
