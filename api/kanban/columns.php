<?php
/**
 * API DE COLUNAS DO KANBAN
 * CRUD de colunas
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
 * GET - Listar colunas ou buscar uma específica
 */
function handleGet($pdo, $ownerId) {
    $columnId = $_GET['id'] ?? null;
    $boardId = $_GET['board_id'] ?? null;
    
    if ($columnId) {
        $stmt = $pdo->prepare("
            SELECT kcol.* FROM kanban_columns kcol
            INNER JOIN kanban_boards kb ON kcol.board_id = kb.id
            WHERE kcol.id = ? AND kb.user_id = ?
        ");
        $stmt->execute([$columnId, $ownerId]);
        $column = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$column) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Coluna não encontrada']);
            return;
        }
        
        echo json_encode(['success' => true, 'column' => $column]);
        
    } else if ($boardId) {
        $stmt = $pdo->prepare("
            SELECT kcol.* FROM kanban_columns kcol
            INNER JOIN kanban_boards kb ON kcol.board_id = kb.id
            WHERE kb.id = ? AND kb.user_id = ?
            ORDER BY kcol.position ASC
        ");
        $stmt->execute([$boardId, $ownerId]);
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'columns' => $columns]);
        
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'board_id ou id é obrigatório']);
    }
}

/**
 * POST - Criar nova coluna
 */
function handlePost($pdo, $ownerId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $boardId = $input['board_id'] ?? null;
    $name = trim($input['name'] ?? '');
    
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
    
    // Obter próxima posição
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(position), -1) + 1 as next_pos FROM kanban_columns WHERE board_id = ?");
    $stmt->execute([$boardId]);
    $nextPos = $stmt->fetch(PDO::FETCH_ASSOC)['next_pos'];
    
    // Inserir coluna
    $stmt = $pdo->prepare("
        INSERT INTO kanban_columns (board_id, name, color, icon, position, wip_limit, is_final)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $boardId,
        $name,
        $input['color'] ?? '#6B7280',
        $input['icon'] ?? null,
        $nextPos,
        $input['wip_limit'] ?? null,
        $input['is_final'] ?? 0
    ]);
    
    $columnId = $pdo->lastInsertId();
    
    echo json_encode(['success' => true, 'column_id' => $columnId, 'message' => 'Coluna criada']);
}

/**
 * PUT - Atualizar coluna ou reordenar
 */
function handlePut($pdo, $ownerId) {
    $columnId = $_GET['id'] ?? null;
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Ação especial: reordenar colunas
    if (isset($input['action']) && $input['action'] === 'reorder') {
        $columns = $input['columns'] ?? [];
        
        foreach ($columns as $col) {
            $stmt = $pdo->prepare("
                UPDATE kanban_columns kcol
                INNER JOIN kanban_boards kb ON kcol.board_id = kb.id
                SET kcol.position = ?
                WHERE kcol.id = ? AND kb.user_id = ?
            ");
            $stmt->execute([$col['position'], $col['id'], $ownerId]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Colunas reordenadas']);
        return;
    }
    
    if (!$columnId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID da coluna é obrigatório']);
        return;
    }
    
    // Verificar se a coluna pertence ao usuário
    $stmt = $pdo->prepare("
        SELECT kcol.id FROM kanban_columns kcol
        INNER JOIN kanban_boards kb ON kcol.board_id = kb.id
        WHERE kcol.id = ? AND kb.user_id = ?
    ");
    $stmt->execute([$columnId, $ownerId]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Coluna não encontrada']);
        return;
    }
    
    // Atualizar coluna
    $updates = [];
    $params = [];
    
    $fields = ['name', 'color', 'icon', 'wip_limit', 'is_final'];
    
    foreach ($fields as $field) {
        if (isset($input[$field])) {
            $updates[] = "$field = ?";
            $params[] = $input[$field];
        }
    }
    
    if (!empty($updates)) {
        $params[] = $columnId;
        $sql = "UPDATE kanban_columns SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
    
    echo json_encode(['success' => true, 'message' => 'Coluna atualizada']);
}

/**
 * DELETE - Excluir coluna
 */
function handleDelete($pdo, $ownerId) {
    $columnId = $_GET['id'] ?? null;
    
    if (!$columnId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID da coluna é obrigatório']);
        return;
    }
    
    // Verificar se a coluna pertence ao usuário
    $stmt = $pdo->prepare("
        SELECT kcol.id, kcol.board_id FROM kanban_columns kcol
        INNER JOIN kanban_boards kb ON kcol.board_id = kb.id
        WHERE kcol.id = ? AND kb.user_id = ?
    ");
    $stmt->execute([$columnId, $ownerId]);
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$column) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Coluna não encontrada']);
        return;
    }
    
    // Verificar se é a única coluna
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM kanban_columns WHERE board_id = ?");
    $stmt->execute([$column['board_id']]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count <= 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Não é possível excluir a única coluna do quadro']);
        return;
    }
    
    // Mover cards para a primeira coluna
    $stmt = $pdo->prepare("SELECT id FROM kanban_columns WHERE board_id = ? AND id != ? ORDER BY position ASC LIMIT 1");
    $stmt->execute([$column['board_id'], $columnId]);
    $firstColumn = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($firstColumn) {
        $stmt = $pdo->prepare("UPDATE kanban_cards SET column_id = ? WHERE column_id = ?");
        $stmt->execute([$firstColumn['id'], $columnId]);
    }
    
    // Excluir coluna
    $stmt = $pdo->prepare("DELETE FROM kanban_columns WHERE id = ?");
    $stmt->execute([$columnId]);
    
    echo json_encode(['success' => true, 'message' => 'Coluna excluída']);
}
