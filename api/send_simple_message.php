<?php
/**
 * API Simples para Enviar Mensagens
 * Aceita telefone e mensagem diretamente
 */

header('Content-Type: application/json');
session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar autenticação
requireLogin();

$userId = $_SESSION['user_id'];

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $phone = trim($input['phone'] ?? '');
    $message = trim($input['message'] ?? '');
    
    if (empty($phone) || empty($message)) {
        throw new Exception('Telefone e mensagem são obrigatórios');
    }
    
    // Buscar configuração do usuário
    $stmt = $pdo->prepare("
        SELECT evolution_instance, evolution_token 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !$user['evolution_instance']) {
        throw new Exception('Instância Evolution não configurada');
    }
    
    $instance = $user['evolution_instance'];
    $token = $user['evolution_token'] ?: EVOLUTION_API_KEY;
    
    // Normalizar telefone
    $phoneFormatted = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phoneFormatted, 0, 2) !== '55') {
        $phoneFormatted = '55' . $phoneFormatted;
    }
    $phoneFormatted .= '@s.whatsapp.net';
    
    // Preparar payload para Evolution API
    $payload = [
        'number' => $phoneFormatted,
        'text' => $message
    ];
    
    // Enviar para Evolution API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, EVOLUTION_API_URL . '/message/sendText/' . $instance);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . $token
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Debug logs
    error_log("DEBUG - Enviando para: " . $phoneFormatted);
    error_log("DEBUG - Mensagem: " . $message);
    error_log("DEBUG - HTTP Code: " . $httpCode);
    error_log("DEBUG - Response: " . $response);
    
    if ($curlError) {
        throw new Exception('Erro de conexão: ' . $curlError);
    }
    
    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception('Erro da Evolution API: ' . $response);
    }
    
    $responseData = json_decode($response, true);
    
    // Criar ou buscar conversa
    $conversationId = getOrCreateConversation($pdo, $userId, $phone, $instance);
    
    // Salvar mensagem no banco
    $stmt = $pdo->prepare("
        INSERT INTO chat_messages 
        (conversation_id, user_id, message_type, message_text, from_me, created_at)
        VALUES (?, ?, 'text', ?, 1, NOW())
    ");
    $stmt->execute([$conversationId, $userId, $message]);
    
    // Atualizar última mensagem da conversa
    $stmt = $pdo->prepare("
        UPDATE chat_conversations 
        SET last_message_text = ?, last_message_time = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$message, $conversationId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Mensagem enviada com sucesso',
        'conversation_id' => $conversationId,
        'message_id' => $responseData['key']['id'] ?? null
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function getOrCreateConversation($pdo, $userId, $phone, $instance) {
    // Normalizar telefone
    $phoneClean = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phoneClean, 0, 2) !== '55') {
        $phoneClean = '55' . $phoneClean;
    }
    
    // Buscar conversa existente
    $stmt = $pdo->prepare("
        SELECT id FROM chat_conversations 
        WHERE user_id = ? AND phone = ?
    ");
    $stmt->execute([$userId, $phoneClean]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($conversation) {
        return $conversation['id'];
    }
    
    // Criar nova conversa
    $stmt = $pdo->prepare("
        INSERT INTO chat_conversations 
        (user_id, phone, contact_name, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$userId, $phoneClean, $phoneClean]);
    
    return $pdo->lastInsertId();
}
?>
