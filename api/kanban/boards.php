<?php
/**
 * API DE QUADROS DO KANBAN
 * CRUD de quadros (boards)
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
 * GET - Listar quadros ou buscar um específico
 */
function handleGet($pdo, $ownerId) {
    $boardId = $_GET['id'] ?? null;
    
    if ($boardId) {
        $stmt = $pdo->prepare("SELECT * FROM kanban_boards WHERE id = ? AND user_id = ?");
        $stmt->execute([$boardId, $ownerId]);
        $board = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$board) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Quadro não encontrado']);
            return;
        }
        
        echo json_encode(['success' => true, 'board' => $board]);
        
    } else {
        $stmt = $pdo->prepare("SELECT * FROM kanban_boards WHERE user_id = ? ORDER BY is_default DESC, name ASC");
        $stmt->execute([$ownerId]);
        $boards = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'boards' => $boards]);
    }
}

/**
 * POST - Criar novo quadro
 */
function handlePost($pdo, $ownerId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $name = trim($input['name'] ?? '');
    
    if (!$name) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nome do quadro é obrigatório']);
        return;
    }
    
    $pdo->beginTransaction();
    
    try {
        // Criar quadro
        $stmt = $pdo->prepare("
            INSERT INTO kanban_boards (user_id, name, description, icon, color, is_default)
            VALUES (?, ?, ?, ?, ?, 0)
        ");
        
        $stmt->execute([
            $ownerId,
            $name,
            $input['description'] ?? null,
            $input['icon'] ?? 'fa-columns',
            $input['color'] ?? '#3B82F6'
        ]);
        
        $boardId = $pdo->lastInsertId();
        
        // Criar colunas padrão
        $defaultColumns = [
            ['Novos', '#6366F1', 'fa-inbox', 0],
            ['Em Andamento', '#F59E0B', 'fa-spinner', 1],
            ['Concluído', '#10B981', 'fa-check-circle', 2]
        ];
        
        $stmtCol = $pdo->prepare("
            INSERT INTO kanban_columns (board_id, name, color, icon, position, is_final)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($defaultColumns as $col) {
            $isFinal = ($col[0] === 'Concluído') ? 1 : 0;
            $stmtCol->execute([$boardId, $col[0], $col[1], $col[2], $col[3], $isFinal]);
        }
        
        // Criar labels padrão
        $defaultLabels = [
            ['Importante', '#EF4444'],
            ['Em Progresso', '#F59E0B'],
            ['Aguardando', '#3B82F6']
        ];
        
        $stmtLabel = $pdo->prepare("INSERT INTO kanban_labels (board_id, name, color) VALUES (?, ?, ?)");
        foreach ($defaultLabels as $label) {
            $stmtLabel->execute([$boardId, $label[0], $label[1]]);
        }
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'board_id' => $boardId, 'message' => 'Quadro criado']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * PUT - Atualizar quadro
 */
function handlePut($pdo, $ownerId) {
    $boardId = $_GET['id'] ?? null;
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$boardId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID do quadro é obrigatório']);
        return;
    }
    
    // Verificar se o quadro pertence ao usuário
    $stmt = $pdo->prepare("SELECT id FROM kanban_boards WHERE id = ? AND user_id = ?");
    $stmt->execute([$boardId, $ownerId]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Quadro não encontrado']);
        return;
    }
    
    // Atualizar quadro
    $updates = [];
    $params = [];
    
    $fields = ['name', 'description', 'icon', 'color'];
    
    foreach ($fields as $field) {
        if (isset($input[$field])) {
            $updates[] = "$field = ?";
            $params[] = $input[$field];
        }
    }
    
    // Definir como padrão
    if (isset($input['is_default']) && $input['is_default']) {
        // Remover padrão dos outros
        $stmt = $pdo->prepare("UPDATE kanban_boards SET is_default = 0 WHERE user_id = ?");
        $stmt->execute([$ownerId]);
        
        $updates[] = "is_default = 1";
    }
    
    if (!empty($updates)) {
        $params[] = $boardId;
        $sql = "UPDATE kanban_boards SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
    
    echo json_encode(['success' => true, 'message' => 'Quadro atualizado']);
}

/**
 * DELETE - Excluir quadro
 */
function handleDelete($pdo, $ownerId) {
    $boardId = $_GET['id'] ?? null;
    
    if (!$boardId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID do quadro é obrigatório']);
        return;
    }
    
    // Verificar se o quadro pertence ao usuário
    $stmt = $pdo->prepare("SELECT id, is_default FROM kanban_boards WHERE id = ? AND user_id = ?");
    $stmt->execute([$boardId, $ownerId]);
    $board = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$board) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Quadro não encontrado']);
        return;
    }
    
    // Não permitir excluir o quadro padrão
    if ($board['is_default']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Não é possível excluir o quadro padrão']);
        return;
    }
    
    // Excluir quadro (cascade vai excluir colunas, cards, etc)
    $stmt = $pdo->prepare("DELETE FROM kanban_boards WHERE id = ?");
    $stmt->execute([$boardId]);
    
    echo json_encode(['success' => true, 'message' => 'Quadro excluído']);
}
