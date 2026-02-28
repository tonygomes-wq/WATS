<?php
/**
 * API DE CARDS DO KANBAN
 * CRUD completo de cards
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
    switch ($method) {
        case 'GET':
            handleGet($pdo, $ownerId, $userId, $userType);
            break;
        case 'POST':
            handlePost($pdo, $ownerId, $userId, $userType);
            break;
        case 'PUT':
            handlePut($pdo, $ownerId, $userId, $userType);
            break;
        case 'DELETE':
            handleDelete($pdo, $ownerId, $userId);
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
 * GET - Listar cards ou buscar um específico
 */
function handleGet($pdo, $ownerId, $userId, $userType) {
    $cardId = $_GET['id'] ?? null;
    $boardId = $_GET['board_id'] ?? null;
    $columnId = $_GET['column_id'] ?? null;
    $archived = isset($_GET['archived']) ? (int)$_GET['archived'] : 0;
    
    if ($cardId) {
        // Buscar card específico
        $stmt = $pdo->prepare("
            SELECT kc.*, 
                   kcol.name as column_name,
                   kb.name as board_name,
                   CASE 
                       WHEN kc.assigned_type = 'attendant' THEN (SELECT name FROM supervisor_users WHERE id = kc.assigned_to)
                       ELSE (SELECT name FROM users WHERE id = kc.assigned_to)
                   END as assigned_name,
                   kc.source_channel
            FROM kanban_cards kc
            INNER JOIN kanban_columns kcol ON kc.column_id = kcol.id
            INNER JOIN kanban_boards kb ON kcol.board_id = kb.id
            WHERE kc.id = ? AND kb.user_id = ?
        ");
        $stmt->execute([$cardId, $ownerId]);
        $card = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$card) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Card não encontrado']);
            return;
        }
        
        // Buscar labels do card
        $stmtLabels = $pdo->prepare("
            SELECT kl.* FROM kanban_labels kl
            INNER JOIN kanban_card_labels kcl ON kl.id = kcl.label_id
            WHERE kcl.card_id = ?
        ");
        $stmtLabels->execute([$cardId]);
        $card['labels'] = $stmtLabels->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'card' => $card]);
        
    } else {
        // Listar cards do quadro
        if (!$boardId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'board_id é obrigatório']);
            return;
        }
        
        $sql = "
            SELECT kc.*, 
                   kcol.name as column_name,
                   CASE 
                       WHEN kc.assigned_type = 'attendant' THEN (SELECT name FROM supervisor_users WHERE id = kc.assigned_to)
                       ELSE (SELECT name FROM users WHERE id = kc.assigned_to)
                   END as assigned_name,
                   (SELECT COUNT(*) FROM kanban_card_comments WHERE card_id = kc.id) as comments_count,
                   (SELECT COUNT(*) FROM kanban_card_attachments WHERE card_id = kc.id) as attachments_count,
                   kc.source_channel
            FROM kanban_cards kc
            INNER JOIN kanban_columns kcol ON kc.column_id = kcol.id
            INNER JOIN kanban_boards kb ON kcol.board_id = kb.id
            WHERE kb.id = ? AND kb.user_id = ? AND kc.is_archived = ?
        ";
        
        $params = [$boardId, $ownerId, $archived];
        
        if ($columnId) {
            $sql .= " AND kc.column_id = ?";
            $params[] = $columnId;
        }
        
        $sql .= " ORDER BY kc.column_id, kc.position ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Buscar labels para cada card
        foreach ($cards as &$card) {
            $stmtLabels = $pdo->prepare("
                SELECT kl.* FROM kanban_labels kl
                INNER JOIN kanban_card_labels kcl ON kl.id = kcl.label_id
                WHERE kcl.card_id = ?
            ");
            $stmtLabels->execute([$card['id']]);
            $card['labels'] = $stmtLabels->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode(['success' => true, 'cards' => $cards]);
    }
}

/**
 * POST - Criar novo card
 */
function handlePost($pdo, $ownerId, $userId, $userType) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $columnId = $input['column_id'] ?? null;
    $title = trim($input['title'] ?? '');
    
    if (!$columnId || !$title) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'column_id e title são obrigatórios']);
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
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Coluna não encontrada']);
        return;
    }
    
    // Obter próxima posição
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(position), -1) + 1 as next_pos FROM kanban_cards WHERE column_id = ?");
    $stmt->execute([$columnId]);
    $nextPos = $stmt->fetch(PDO::FETCH_ASSOC)['next_pos'];
    
    // Inserir card
    $stmt = $pdo->prepare("
        INSERT INTO kanban_cards (
            column_id, title, description, contact_name, contact_phone,
            assigned_to, assigned_type, priority, due_date, value, position, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $assignedTo = $input['assigned_to'] ?? null;
    $assignedType = $assignedTo ? 'attendant' : null;
    
    $stmt->execute([
        $columnId,
        $title,
        $input['description'] ?? null,
        $input['contact_name'] ?? null,
        $input['contact_phone'] ?? null,
        $assignedTo,
        $assignedType,
        $input['priority'] ?? 'normal',
        $input['due_date'] ?? null,
        $input['value'] ?? null,
        $nextPos,
        $userId
    ]);
    
    $cardId = $pdo->lastInsertId();
    
    // Adicionar labels
    if (!empty($input['labels'])) {
        $stmtLabel = $pdo->prepare("INSERT INTO kanban_card_labels (card_id, label_id) VALUES (?, ?)");
        foreach ($input['labels'] as $labelId) {
            $stmtLabel->execute([$cardId, $labelId]);
        }
    }
    
    echo json_encode(['success' => true, 'card_id' => $cardId, 'message' => 'Card criado com sucesso']);
}

/**
 * PUT - Atualizar card
 */
function handlePut($pdo, $ownerId, $userId, $userType) {
    $cardId = $_GET['id'] ?? null;
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Ação especial: arquivar todos da coluna
    if (isset($input['action']) && $input['action'] === 'archive_column') {
        $columnId = $input['column_id'] ?? null;
        
        if (!$columnId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'column_id é obrigatório']);
            return;
        }
        
        $stmt = $pdo->prepare("
            UPDATE kanban_cards kc
            INNER JOIN kanban_columns kcol ON kc.column_id = kcol.id
            INNER JOIN kanban_boards kb ON kcol.board_id = kb.id
            SET kc.is_archived = 1, kc.archived_at = NOW()
            WHERE kc.column_id = ? AND kb.user_id = ?
        ");
        $stmt->execute([$columnId, $ownerId]);
        
        echo json_encode(['success' => true, 'message' => 'Cards arquivados']);
        return;
    }
    
    if (!$cardId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID do card é obrigatório']);
        return;
    }
    
    // Verificar se o card pertence ao usuário
    $stmt = $pdo->prepare("
        SELECT kc.id FROM kanban_cards kc
        INNER JOIN kanban_columns kcol ON kc.column_id = kcol.id
        INNER JOIN kanban_boards kb ON kcol.board_id = kb.id
        WHERE kc.id = ? AND kb.user_id = ?
    ");
    $stmt->execute([$cardId, $ownerId]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Card não encontrado']);
        return;
    }
    
    // Atualizar card
    $updates = [];
    $params = [];
    
    $fields = ['title', 'description', 'contact_name', 'contact_phone', 'priority', 'due_date', 'value', 'column_id'];
    
    foreach ($fields as $field) {
        if (isset($input[$field])) {
            $updates[] = "$field = ?";
            $params[] = $input[$field] ?: null;
        }
    }
    
    if (isset($input['assigned_to'])) {
        $updates[] = "assigned_to = ?";
        $updates[] = "assigned_type = ?";
        $params[] = $input['assigned_to'] ?: null;
        $params[] = $input['assigned_to'] ? 'attendant' : null;
    }
    
    if (!empty($updates)) {
        $params[] = $cardId;
        $sql = "UPDATE kanban_cards SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
    
    // Atualizar labels
    if (isset($input['labels'])) {
        // Remover labels existentes
        $stmt = $pdo->prepare("DELETE FROM kanban_card_labels WHERE card_id = ?");
        $stmt->execute([$cardId]);
        
        // Adicionar novas labels
        if (!empty($input['labels'])) {
            $stmtLabel = $pdo->prepare("INSERT INTO kanban_card_labels (card_id, label_id) VALUES (?, ?)");
            foreach ($input['labels'] as $labelId) {
                $stmtLabel->execute([$cardId, $labelId]);
            }
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Card atualizado']);
}

/**
 * DELETE - Arquivar card
 */
function handleDelete($pdo, $ownerId, $userId) {
    $cardId = $_GET['id'] ?? null;
    
    if (!$cardId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID do card é obrigatório']);
        return;
    }
    
    // Verificar se o card pertence ao usuário
    $stmt = $pdo->prepare("
        SELECT kc.id FROM kanban_cards kc
        INNER JOIN kanban_columns kcol ON kc.column_id = kcol.id
        INNER JOIN kanban_boards kb ON kcol.board_id = kb.id
        WHERE kc.id = ? AND kb.user_id = ?
    ");
    $stmt->execute([$cardId, $ownerId]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Card não encontrado']);
        return;
    }
    
    // Arquivar (soft delete)
    $stmt = $pdo->prepare("UPDATE kanban_cards SET is_archived = 1, archived_at = NOW() WHERE id = ?");
    $stmt->execute([$cardId]);
    
    echo json_encode(['success' => true, 'message' => 'Card arquivado']);
}
