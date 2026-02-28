<?php
/**
 * API DE COMENTÁRIOS DO KANBAN
 * CRUD de comentários nos cards
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

// Determinar o owner_id e nome do usuário
if ($userType === 'attendant') {
    $stmt = $pdo->prepare("SELECT supervisor_id, name FROM supervisor_users WHERE id = ?");
    $stmt->execute([$userId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $ownerId = $data['supervisor_id'] ?? $userId;
    $userName = $data['name'] ?? 'Atendente';
} else {
    $ownerId = $userId;
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $userName = $data['name'] ?? 'Usuário';
}

try {
    switch ($method) {
        case 'GET':
            handleGet($pdo, $ownerId);
            break;
        case 'POST':
            handlePost($pdo, $ownerId, $userId, $userType, $userName);
            break;
        case 'DELETE':
            handleDelete($pdo, $ownerId, $userId, $userType);
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
 * GET - Listar comentários de um card
 */
function handleGet($pdo, $ownerId) {
    $cardId = $_GET['card_id'] ?? null;
    
    if (!$cardId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'card_id é obrigatório']);
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
    
    // Buscar comentários
    $stmt = $pdo->prepare("
        SELECT kcc.*,
               CASE 
                   WHEN kcc.user_type = 'attendant' THEN (SELECT name FROM supervisor_users WHERE id = kcc.user_id)
                   ELSE (SELECT name FROM users WHERE id = kcc.user_id)
               END as user_name
        FROM kanban_card_comments kcc
        WHERE kcc.card_id = ?
        ORDER BY kcc.created_at DESC
    ");
    $stmt->execute([$cardId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'comments' => $comments]);
}

/**
 * POST - Criar novo comentário
 */
function handlePost($pdo, $ownerId, $userId, $userType, $userName) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $cardId = $input['card_id'] ?? null;
    $comment = trim($input['comment'] ?? '');
    $isInternal = $input['is_internal'] ?? 0;
    
    if (!$cardId || !$comment) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'card_id e comment são obrigatórios']);
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
    
    // Inserir comentário
    $stmt = $pdo->prepare("
        INSERT INTO kanban_card_comments (card_id, user_id, user_type, comment, is_internal)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$cardId, $userId, $userType, $comment, $isInternal]);
    
    $commentId = $pdo->lastInsertId();
    
    // Registrar no histórico
    $stmt = $pdo->prepare("
        INSERT INTO kanban_card_history (card_id, user_id, user_type, action, changes)
        VALUES (?, ?, ?, 'commented', ?)
    ");
    $stmt->execute([$cardId, $userId, $userType, json_encode(['comment_id' => $commentId])]);
    
    echo json_encode([
        'success' => true, 
        'comment_id' => $commentId, 
        'message' => 'Comentário adicionado',
        'comment' => [
            'id' => $commentId,
            'card_id' => $cardId,
            'user_id' => $userId,
            'user_type' => $userType,
            'user_name' => $userName,
            'comment' => $comment,
            'is_internal' => $isInternal,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
}

/**
 * DELETE - Excluir comentário
 */
function handleDelete($pdo, $ownerId, $userId, $userType) {
    $commentId = $_GET['id'] ?? null;
    
    if (!$commentId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID do comentário é obrigatório']);
        return;
    }
    
    // Verificar se o comentário pertence ao usuário (ou é do mesmo card do owner)
    $stmt = $pdo->prepare("
        SELECT kcc.id, kcc.user_id, kcc.user_type FROM kanban_card_comments kcc
        INNER JOIN kanban_cards kc ON kcc.card_id = kc.id
        INNER JOIN kanban_columns kcol ON kc.column_id = kcol.id
        INNER JOIN kanban_boards kb ON kcol.board_id = kb.id
        WHERE kcc.id = ? AND kb.user_id = ?
    ");
    $stmt->execute([$commentId, $ownerId]);
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$comment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Comentário não encontrado']);
        return;
    }
    
    // Verificar se é o autor do comentário ou o owner do quadro
    if ($comment['user_id'] != $userId || $comment['user_type'] != $userType) {
        // Verificar se é o owner
        if ($userType === 'attendant') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Você só pode excluir seus próprios comentários']);
            return;
        }
    }
    
    // Excluir comentário
    $stmt = $pdo->prepare("DELETE FROM kanban_card_comments WHERE id = ?");
    $stmt->execute([$commentId]);
    
    echo json_encode(['success' => true, 'message' => 'Comentário excluído']);
}
