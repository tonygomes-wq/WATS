<?php
/**
 * Webhook Endpoint para Telegram
 * Recebe e processa mensagens do Telegram Bot API
 */

header('Content-Type: application/json');

require_once '../../config/database.php';
require_once '../../includes/channels/TelegramChannel.php';

// Capturar bot token da URL
$botToken = $_GET['token'] ?? '';

if (empty($botToken)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing bot token']);
    exit;
}

try {
    // Buscar canal pelo bot_token
    $stmt = $pdo->prepare("
        SELECT c.id 
        FROM channels c
        JOIN channel_telegram ct ON ct.channel_id = c.id
        WHERE ct.bot_token = ? AND c.status = 'active'
    ");
    $stmt->execute([$botToken]);
    $channel = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$channel) {
        http_response_code(404);
        echo json_encode(['error' => 'Channel not found or inactive']);
        exit;
    }
    
    // Capturar payload do webhook
    $payload = json_decode(file_get_contents('php://input'), true);
    
    if (!$payload) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload']);
        exit;
    }
    
    // Log do webhook recebido (opcional, para debug)
    error_log("Telegram Webhook: " . json_encode($payload));
    
    // Processar webhook
    $telegramChannel = new TelegramChannel($pdo, $channel['id']);
    $result = $telegramChannel->receiveWebhook($payload);
    
    // Retornar resposta
    http_response_code($result['success'] ? 200 : 500);
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Telegram Webhook Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
