<?php
/**
 * API de Ações de Conversas
 * Resolver, Encerrar, Transferir, Notas Internas
 */

session_start();
require_once '../config/database.php';

// ✅ FASE 2: Adicionar componentes de segurança
require_once '../includes/RateLimiter.php';
require_once '../includes/InputValidator.php';
require_once '../includes/Logger.php';

header('Content-Type: application/json');

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    Logger::warning('Tentativa de acesso não autorizado', [
        'endpoint' => 'conversation_actions',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ✅ RATE LIMITING - Prevenir abuso de ações
$rateLimiter = new RateLimiter();

// Limite: 30 ações por minuto
if (!$rateLimiter->allow($user_id, 'conversation_actions', 30, 60)) {
    Logger::warning('Rate limit excedido - ações de conversa', [
        'user_id' => $user_id,
        'action' => $action,
        'endpoint' => 'conversation_actions'
    ]);
    
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Muitas ações. Aguarde 1 minuto.',
        'retry_after' => 60
    ]);
    exit;
}

// Verificar se é supervisor
$stmt = $pdo->prepare("SELECT user_type FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || ($user['user_type'] !== 'supervisor' && $user['user_type'] !== 'admin')) {
    Logger::warning('Tentativa de acesso negado - não é supervisor', [
        'user_id' => $user_id,
        'user_type' => $user['user_type'] ?? 'unknown',
        'action' => $action
    ]);
    
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado. Apenas supervisores podem executar esta ação.']);
    exit;
}

// ✅ VALIDAÇÃO DE AÇÃO
$validActions = ['resolve', 'close', 'transfer', 'add_note', 'get_notes', 'delete_note'];
if (!in_array($action, $validActions)) {
    Logger::warning('Ação inválida', [
        'user_id' => $user_id,
        'action' => $action
    ]);
    
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ação inválida']);
    exit;
}

try {
    Logger::info('Executando ação de conversa', [
        'user_id' => $user_id,
        'action' => $action
    ]);
    
    switch ($action) {
        case 'resolve':
            resolveConversation($pdo, $user_id);
            break;
            
        case 'close':
            closeConversation($pdo, $user_id);
            break;
            
        case 'transfer':
            transferConversation($pdo, $user_id);
            break;
            
        case 'add_note':
            addInternalNote($pdo, $user_id);
            break;
            
        case 'get_notes':
            getInternalNotes($pdo, $user_id);
            break;
            
        case 'delete_note':
            deleteInternalNote($pdo, $user_id);
            break;
    }
} catch (Exception $e) {
    Logger::error('Exceção em conversation_actions', [
        'user_id' => $user_id,
        'action' => $action,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Marcar conversa como resolvida
 */
function resolveConversation($pdo, $user_id) {
    // ✅ VALIDAÇÃO DE INPUT
    $idValidation = InputValidator::validateId($_POST['conversation_id'] ?? 0);
    if (!$idValidation['valid']) {
        Logger::warning('Validação falhou - resolver conversa', [
            'user_id' => $user_id,
            'errors' => $idValidation['errors']
        ]);
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => implode(', ', $idValidation['errors'])
        ]);
        return;
    }
    
    $conversation_id = $idValidation['sanitized'];
    
    // Verificar se a conversa pertence ao usuário
    $stmt = $pdo->prepare("SELECT id FROM chat_conversations WHERE id = ? AND user_id = ?");
    $stmt->execute([$conversation_id, $user_id]);
    
    if (!$stmt->fetch()) {
        Logger::warning('Tentativa de resolver conversa não autorizada', [
            'user_id' => $user_id,
            'conversation_id' => $conversation_id
        ]);
        
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Conversa não encontrada']);
        return;
    }
    
    // Atualizar status
    $stmt = $pdo->prepare("
        UPDATE chat_conversations 
        SET status = 'resolved', resolved_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([$conversation_id]);
    
    Logger::info('Conversa resolvida', [
        'user_id' => $user_id,
        'conversation_id' => $conversation_id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Conversa marcada como resolvida'
    ]);
}

/**
 * Encerrar conversa
 */
function closeConversation($pdo, $user_id) {
    // ✅ VALIDAÇÃO DE INPUT
    $idValidation = InputValidator::validateId($_POST['conversation_id'] ?? 0);
    if (!$idValidation['valid']) {
        Logger::warning('Validação falhou - encerrar conversa', [
            'user_id' => $user_id,
            'errors' => $idValidation['errors']
        ]);
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => implode(', ', $idValidation['errors'])
        ]);
        return;
    }
    
    $conversation_id = $idValidation['sanitized'];
    
    // Verificar se a conversa pertence ao usuário
    $stmt = $pdo->prepare("SELECT id FROM chat_conversations WHERE id = ? AND user_id = ?");
    $stmt->execute([$conversation_id, $user_id]);
    
    if (!$stmt->fetch()) {
        Logger::warning('Tentativa de encerrar conversa não autorizada', [
            'user_id' => $user_id,
            'conversation_id' => $conversation_id
        ]);
        
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Conversa não encontrada']);
        return;
    }
    
    // Atualizar status e arquivar
    $stmt = $pdo->prepare("
        UPDATE chat_conversations 
        SET status = 'closed', closed_at = NOW(), is_archived = 1
        WHERE id = ?
    ");
    
    $stmt->execute([$conversation_id]);
    
    Logger::info('Conversa encerrada e arquivada', [
        'user_id' => $user_id,
        'conversation_id' => $conversation_id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Conversa encerrada e arquivada'
    ]);
}

/**
 * Transferir conversa
 */
function transferConversation($pdo, $user_id) {
    // ✅ VALIDAÇÃO DE INPUT
    $idValidation = InputValidator::validateId($_POST['conversation_id'] ?? 0);
    if (!$idValidation['valid']) {
        Logger::warning('Validação falhou - transferir conversa', [
            'user_id' => $user_id,
            'errors' => $idValidation['errors']
        ]);
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => implode(', ', $idValidation['errors'])
        ]);
        return;
    }
    
    $conversation_id = $idValidation['sanitized'];
    $to_user_id = isset($_POST['to_user_id']) ? (int)$_POST['to_user_id'] : null;
    $to_department_id = isset($_POST['to_department_id']) ? (int)$_POST['to_department_id'] : null;
    $reason = trim($_POST['reason'] ?? '');
    
    // Validações
    if (!$to_user_id && !$to_department_id) {
        Logger::warning('Transferência sem destino', [
            'user_id' => $user_id,
            'conversation_id' => $conversation_id
        ]);
        
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Selecione um atendente ou setor']);
        return;
    }
    
    // Verificar se a conversa pertence ao usuário
    $stmt = $pdo->prepare("
        SELECT id, assigned_to, department_id 
        FROM chat_conversations 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$conversation_id, $user_id]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversation) {
        Logger::warning('Tentativa de transferir conversa não autorizada', [
            'user_id' => $user_id,
            'conversation_id' => $conversation_id
        ]);
        
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Conversa não encontrada']);
        return;
    }
    
    $pdo->beginTransaction();
    
    try {
        // Atualizar conversa
        $stmt = $pdo->prepare("
            UPDATE chat_conversations 
            SET assigned_to = ?, department_id = ?, status = 'transferred'
            WHERE id = ?
        ");
        
        $stmt->execute([$to_user_id, $to_department_id, $conversation_id]);
        
        // Registrar transferência
        $stmt = $pdo->prepare("
            INSERT INTO conversation_transfers 
            (conversation_id, from_user_id, to_user_id, from_department_id, to_department_id, reason)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $conversation_id,
            $conversation['assigned_to'],
            $to_user_id,
            $conversation['department_id'],
            $to_department_id,
            $reason
        ]);
        
        $pdo->commit();
        
        Logger::info('Conversa transferida', [
            'user_id' => $user_id,
            'conversation_id' => $conversation_id,
            'from_user' => $conversation['assigned_to'],
            'to_user' => $to_user_id,
            'to_department' => $to_department_id,
            'reason' => $reason
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Conversa transferida com sucesso'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        
        Logger::error('Erro ao transferir conversa', [
            'user_id' => $user_id,
            'conversation_id' => $conversation_id,
            'error' => $e->getMessage()
        ]);
        
        throw $e;
    }
}

/**
 * Adicionar nota interna
 */
function addInternalNote($pdo, $user_id) {
    // ✅ VALIDAÇÃO DE INPUT
    $idValidation = InputValidator::validateId($_POST['conversation_id'] ?? 0);
    if (!$idValidation['valid']) {
        Logger::warning('Validação falhou - adicionar nota', [
            'user_id' => $user_id,
            'errors' => $idValidation['errors']
        ]);
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => implode(', ', $idValidation['errors'])
        ]);
        return;
    }
    
    $conversation_id = $idValidation['sanitized'];
    $note = trim($_POST['note'] ?? '');
    
    // Validações
    if (empty($note)) {
        Logger::warning('Nota vazia', [
            'user_id' => $user_id,
            'conversation_id' => $conversation_id
        ]);
        
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nota não pode estar vazia']);
        return;
    }
    
    // Validar tamanho da nota
    if (strlen($note) > 5000) {
        Logger::warning('Nota muito longa', [
            'user_id' => $user_id,
            'conversation_id' => $conversation_id,
            'note_length' => strlen($note)
        ]);
        
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nota muito longa (máximo 5000 caracteres)']);
        return;
    }
    
    // Verificar se a conversa pertence ao usuário
    $stmt = $pdo->prepare("SELECT id FROM chat_conversations WHERE id = ? AND user_id = ?");
    $stmt->execute([$conversation_id, $user_id]);
    
    if (!$stmt->fetch()) {
        Logger::warning('Tentativa de adicionar nota em conversa não autorizada', [
            'user_id' => $user_id,
            'conversation_id' => $conversation_id
        ]);
        
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Conversa não encontrada']);
        return;
    }
    
    // Buscar ID do supervisor_user (se existir)
    $stmt = $pdo->prepare("SELECT id FROM supervisor_users WHERE supervisor_id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $supervisor_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$supervisor_user) {
        Logger::warning('Usuário não é supervisor', [
            'user_id' => $user_id,
            'conversation_id' => $conversation_id
        ]);
        
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Usuário não encontrado como atendente']);
        return;
    }
    
    // Inserir nota
    $stmt = $pdo->prepare("
        INSERT INTO conversation_notes (conversation_id, user_id, note)
        VALUES (?, ?, ?)
    ");
    
    $stmt->execute([$conversation_id, $supervisor_user['id'], $note]);
    $noteId = $pdo->lastInsertId();
    
    Logger::info('Nota interna adicionada', [
        'user_id' => $user_id,
        'conversation_id' => $conversation_id,
        'note_id' => $noteId,
        'note_length' => strlen($note)
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Nota interna adicionada',
        'note_id' => $noteId
    ]);
}

/**
 * Obter notas internas
 */
function getInternalNotes($pdo, $user_id) {
    // ✅ VALIDAÇÃO DE INPUT
    $idValidation = InputValidator::validateId($_GET['conversation_id'] ?? 0);
    if (!$idValidation['valid']) {
        Logger::warning('Validação falhou - obter notas', [
            'user_id' => $user_id,
            'errors' => $idValidation['errors']
        ]);
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => implode(', ', $idValidation['errors'])
        ]);
        return;
    }
    
    $conversation_id = $idValidation['sanitized'];
    
    // Verificar se a conversa pertence ao usuário
    $stmt = $pdo->prepare("SELECT id FROM chat_conversations WHERE id = ? AND user_id = ?");
    $stmt->execute([$conversation_id, $user_id]);
    
    if (!$stmt->fetch()) {
        Logger::warning('Tentativa de obter notas de conversa não autorizada', [
            'user_id' => $user_id,
            'conversation_id' => $conversation_id
        ]);
        
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Conversa não encontrada']);
        return;
    }
    
    // Buscar notas
    $stmt = $pdo->prepare("
        SELECT 
            cn.*,
            su.name as user_name
        FROM conversation_notes cn
        LEFT JOIN supervisor_users su ON cn.user_id = su.id
        WHERE cn.conversation_id = ?
        ORDER BY cn.created_at DESC
    ");
    
    $stmt->execute([$conversation_id]);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    Logger::info('Notas obtidas', [
        'user_id' => $user_id,
        'conversation_id' => $conversation_id,
        'notes_count' => count($notes)
    ]);
    
    echo json_encode([
        'success' => true,
        'notes' => $notes
    ]);
}

/**
 * Excluir nota interna
 */
function deleteInternalNote($pdo, $user_id) {
    // ✅ VALIDAÇÃO DE INPUT
    $idValidation = InputValidator::validateId($_POST['note_id'] ?? 0);
    if (!$idValidation['valid']) {
        Logger::warning('Validação falhou - deletar nota', [
            'user_id' => $user_id,
            'errors' => $idValidation['errors']
        ]);
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => implode(', ', $idValidation['errors'])
        ]);
        return;
    }
    
    $note_id = $idValidation['sanitized'];
    
    // Verificar se a nota pertence a uma conversa do usuário
    $stmt = $pdo->prepare("
        SELECT cn.id, cn.conversation_id
        FROM conversation_notes cn
        INNER JOIN chat_conversations cc ON cn.conversation_id = cc.id
        WHERE cn.id = ? AND cc.user_id = ?
    ");
    $stmt->execute([$note_id, $user_id]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$note) {
        Logger::warning('Tentativa de deletar nota não autorizada', [
            'user_id' => $user_id,
            'note_id' => $note_id
        ]);
        
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Nota não encontrada']);
        return;
    }
    
    // Excluir nota
    $stmt = $pdo->prepare("DELETE FROM conversation_notes WHERE id = ?");
    $stmt->execute([$note_id]);
    
    Logger::info('Nota excluída', [
        'user_id' => $user_id,
        'note_id' => $note_id,
        'conversation_id' => $note['conversation_id']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Nota excluída'
    ]);
}
