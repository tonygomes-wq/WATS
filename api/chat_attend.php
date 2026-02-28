<?php
/**
 * API PARA ATENDER CONVERSA
 * Permite que um atendente "pegue" uma conversa para si
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'] ?? 'user';
$userName = $_SESSION['user_name'] ?? 'Atendente';

// Buscar nome do usuário se não estiver na sessão
if (empty($userName) || $userName === 'Atendente') {
    if ($userType === 'attendant') {
        $stmt = $pdo->prepare("SELECT name FROM supervisor_users WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    }
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    $userName = $userData['name'] ?? 'Atendente';
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($method) {
        case 'POST':
            $action = $input['action'] ?? 'attend';
            
            switch ($action) {
                case 'attend':
                    // Atender conversa
                    attendConversation($pdo, $userId, $userType, $userName, $input);
                    break;
                    
                case 'release':
                    // Liberar conversa (devolver para fila geral)
                    releaseConversation($pdo, $userId, $input);
                    break;
                    
                case 'close':
                    // Encerrar conversa (mover para histórico)
                    closeConversation($pdo, $userId, $userName, $input);
                    break;
                    
                default:
                    throw new Exception('Ação inválida');
            }
            break;
            
        case 'GET':
            // Verificar status de atendimento de uma conversa
            checkAttendanceStatus($pdo, $userId, $userType);
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
 * Atender uma conversa
 */
function attendConversation($pdo, $userId, $userType, $userName, $input) {
    $conversationId = (int) ($input['conversation_id'] ?? 0);
    
    if (!$conversationId) {
        throw new Exception('ID da conversa é obrigatório');
    }
    
    // Verificar se conversa existe
    $stmt = $pdo->prepare("SELECT * FROM chat_conversations WHERE id = ?");
    $stmt->execute([$conversationId]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversation) {
        throw new Exception('Conversa não encontrada');
    }
    
    // Verificar se já está sendo atendida por outro atendente
    if (!empty($conversation['attended_by']) && $conversation['attended_by'] != $userId) {
        // Buscar nome do atendente atual
        $attendantName = $conversation['attended_by_name'] ?? 'Outro atendente';
        throw new Exception("Esta conversa já está sendo atendida por: $attendantName");
    }
    
    // Atualizar conversa com dados do atendente
    $stmt = $pdo->prepare("
        UPDATE chat_conversations 
        SET attended_by = ?,
            attended_by_name = ?,
            attended_by_type = ?,
            attended_at = NOW(),
            status = 'in_progress'
        WHERE id = ?
    ");
    $stmt->execute([$userId, $userName, $userType, $conversationId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Conversa atendida com sucesso',
        'attended_by' => $userId,
        'attended_by_name' => $userName
    ]);
}

/**
 * Liberar conversa (devolver para fila geral)
 */
function releaseConversation($pdo, $userId, $input) {
    $conversationId = (int) ($input['conversation_id'] ?? 0);
    
    if (!$conversationId) {
        throw new Exception('ID da conversa é obrigatório');
    }
    
    // Verificar se o usuário é o atendente atual
    $stmt = $pdo->prepare("SELECT attended_by FROM chat_conversations WHERE id = ?");
    $stmt->execute([$conversationId]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversation) {
        throw new Exception('Conversa não encontrada');
    }
    
    if ($conversation['attended_by'] != $userId) {
        throw new Exception('Você não é o atendente desta conversa');
    }
    
    // Liberar conversa
    $stmt = $pdo->prepare("
        UPDATE chat_conversations 
        SET attended_by = NULL,
            attended_by_name = NULL,
            attended_by_type = NULL,
            attended_at = NULL,
            status = 'open'
        WHERE id = ?
    ");
    $stmt->execute([$conversationId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Conversa liberada para a fila geral'
    ]);
}

/**
 * Encerrar conversa (mover para histórico)
 */
function closeConversation($pdo, $userId, $userName, $input) {
    $conversationId = (int) ($input['conversation_id'] ?? 0);
    $reason = trim($input['reason'] ?? 'Encerrada pelo atendente');
    
    if (!$conversationId) {
        throw new Exception('ID da conversa é obrigatório');
    }
    
    // Buscar dados da conversa
    $stmt = $pdo->prepare("SELECT * FROM chat_conversations WHERE id = ?");
    $stmt->execute([$conversationId]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversation) {
        throw new Exception('Conversa não encontrada');
    }
    
    // Atualizar status para encerrada
    $stmt = $pdo->prepare("
        UPDATE chat_conversations 
        SET status = 'closed',
            closed_by = ?,
            closed_by_name = ?,
            closed_at = NOW(),
            close_reason = ?
        WHERE id = ?
    ");
    $stmt->execute([$userId, $userName, $reason, $conversationId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Conversa encerrada e movida para o histórico'
    ]);
}

/**
 * Verificar status de atendimento
 */
function checkAttendanceStatus($pdo, $userId, $userType) {
    $conversationId = (int) ($_GET['conversation_id'] ?? 0);
    
    if (!$conversationId) {
        throw new Exception('ID da conversa é obrigatório');
    }
    
    $stmt = $pdo->prepare("
        SELECT id, attended_by, attended_by_name, attended_at, status, closed_at, closed_by_name
        FROM chat_conversations 
        WHERE id = ?
    ");
    $stmt->execute([$conversationId]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversation) {
        throw new Exception('Conversa não encontrada');
    }
    
    $isAttendedByMe = ($conversation['attended_by'] == $userId);
    $isAttended = !empty($conversation['attended_by']);
    $isClosed = ($conversation['status'] === 'closed');
    
    echo json_encode([
        'success' => true,
        'conversation_id' => $conversationId,
        'status' => $conversation['status'],
        'is_attended' => $isAttended,
        'is_attended_by_me' => $isAttendedByMe,
        'attended_by' => $conversation['attended_by'],
        'attended_by_name' => $conversation['attended_by_name'],
        'attended_at' => $conversation['attended_at'],
        'is_closed' => $isClosed,
        'closed_at' => $conversation['closed_at'],
        'closed_by_name' => $conversation['closed_by_name'],
        'can_attend' => !$isAttended && !$isClosed,
        'can_view' => $isAttendedByMe || $isClosed || !$isAttended
    ]);
}
?>
