<?php
session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/chat_service.php';

// ✅ FASE 2: Adicionar componentes de segurança
require_once '../includes/RateLimiter.php';
require_once '../includes/InputValidator.php';
require_once '../includes/Logger.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    Logger::warning('Tentativa de acesso não autorizado', [
        'endpoint' => 'chat_delete_message',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userType = $_SESSION['user_type'] ?? 'user';

// ✅ RATE LIMITING - Prevenir deleções em massa
$rateLimiter = new RateLimiter();

// Limite: 10 deleções por minuto (ação destrutiva)
if (!$rateLimiter->allow($userId, 'delete_message', 10, 60)) {
    Logger::warning('Rate limit excedido - deletar mensagem', [
        'user_id' => $userId,
        'endpoint' => 'chat_delete_message'
    ]);
    
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Muitas deleções. Aguarde 1 minuto.',
        'retry_after' => 60
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

// ✅ VALIDAÇÃO DE INPUT
$idValidation = InputValidator::validateId($input['message_id'] ?? 0);
if (!$idValidation['valid']) {
    Logger::warning('Validação falhou - deletar mensagem', [
        'user_id' => $userId,
        'errors' => $idValidation['errors']
    ]);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => implode(', ', $idValidation['errors'])
    ]);
    exit;
}

$messageId = $idValidation['sanitized'];

try {
    // ✅ LOG DE AUDITORIA - Antes de deletar
    Logger::info('Tentativa de deletar mensagem', [
        'user_id' => $userId,
        'user_type' => $userType,
        'message_id' => $messageId
    ]);
    
    $result = deleteMessageForUser($messageId, $userId, $userType);
    
    // ✅ LOG DE AUDITORIA - Após deletar
    if ($result['success']) {
        Logger::info('Mensagem deletada com sucesso', [
            'user_id' => $userId,
            'message_id' => $messageId
        ]);
    } else {
        Logger::warning('Falha ao deletar mensagem', [
            'user_id' => $userId,
            'message_id' => $messageId,
            'error' => $result['error'] ?? 'Erro desconhecido'
        ]);
    }
    
    echo json_encode($result);
} catch (Exception $e) {
    Logger::error('Exceção ao deletar mensagem', [
        'user_id' => $userId,
        'message_id' => $messageId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
