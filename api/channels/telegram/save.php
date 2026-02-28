<?php
/**
 * API para salvar/configurar canal Telegram
 */

header('Content-Type: application/json');

require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/channels/TelegramChannel.php';

// Verificar autenticação
session_start();
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Capturar dados
$input = json_decode(file_get_contents('php://input'), true);
$botToken = $input['bot_token'] ?? '';

if (empty($botToken)) {
    echo json_encode(['success' => false, 'error' => 'Bot token é obrigatório']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Verificar se já existe canal Telegram para este usuário
    $stmt = $pdo->prepare("
        SELECT c.id FROM channels c
        JOIN channel_telegram ct ON ct.channel_id = c.id
        WHERE c.user_id = ? AND c.channel_type = 'telegram'
    ");
    $stmt->execute([$user_id]);
    $existingChannel = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingChannel) {
        // Atualizar canal existente
        $channelId = $existingChannel['id'];
        
        $stmt = $pdo->prepare("
            UPDATE channel_telegram 
            SET bot_token = ?, webhook_verified = FALSE
            WHERE channel_id = ?
        ");
        $stmt->execute([$botToken, $channelId]);
        
    } else {
        // Criar novo canal
        $stmt = $pdo->prepare("
            INSERT INTO channels (user_id, channel_type, name, status, created_at)
            VALUES (?, 'telegram', 'Telegram Bot', 'inactive', NOW())
        ");
        $stmt->execute([$user_id]);
        $channelId = $pdo->lastInsertId();
        
        // Criar configuração do Telegram
        $stmt = $pdo->prepare("
            INSERT INTO channel_telegram (channel_id, bot_token, webhook_verified, created_at)
            VALUES (?, ?, FALSE, NOW())
        ");
        $stmt->execute([$channelId, $botToken]);
    }
    
    // Validar credenciais e configurar webhook
    $telegramChannel = new TelegramChannel($pdo, $channelId);
    
    if (!$telegramChannel->validateCredentials()) {
        throw new Exception('Token do bot inválido. Verifique o token e tente novamente.');
    }
    
    if (!$telegramChannel->setupWebhook()) {
        throw new Exception('Erro ao configurar webhook. Tente novamente.');
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Canal Telegram configurado com sucesso!',
        'channel_id' => $channelId
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
