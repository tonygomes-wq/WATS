<?php
/**
 * API para salvar/configurar canal Facebook Messenger
 */

header('Content-Type: application/json');

require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/channels/FacebookChannel.php';

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
$pageId = $input['page_id'] ?? '';
$pageAccessToken = $input['page_access_token'] ?? '';
$userAccessToken = $input['user_access_token'] ?? '';

if (empty($pageId) || empty($pageAccessToken) || empty($userAccessToken)) {
    echo json_encode(['success' => false, 'error' => 'Todos os campos são obrigatórios']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Verificar se já existe canal Facebook para este usuário
    $stmt = $pdo->prepare("
        SELECT c.id FROM channels c
        JOIN channel_facebook cf ON cf.channel_id = c.id
        WHERE c.user_id = ? AND c.channel_type = 'facebook'
    ");
    $stmt->execute([$user_id]);
    $existingChannel = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingChannel) {
        // Atualizar canal existente
        $channelId = $existingChannel['id'];
        
        $stmt = $pdo->prepare("
            UPDATE channel_facebook 
            SET page_id = ?, page_access_token = ?, user_access_token = ?, webhook_verified = FALSE
            WHERE channel_id = ?
        ");
        $stmt->execute([$pageId, $pageAccessToken, $userAccessToken, $channelId]);
        
    } else {
        // Criar novo canal
        $stmt = $pdo->prepare("
            INSERT INTO channels (user_id, channel_type, name, status, created_at)
            VALUES (?, 'facebook', 'Facebook Messenger', 'inactive', NOW())
        ");
        $stmt->execute([$user_id]);
        $channelId = $pdo->lastInsertId();
        
        // Criar configuração do Facebook
        $stmt = $pdo->prepare("
            INSERT INTO channel_facebook (channel_id, page_id, page_access_token, user_access_token, webhook_verified, created_at)
            VALUES (?, ?, ?, ?, FALSE, NOW())
        ");
        $stmt->execute([$channelId, $pageId, $pageAccessToken, $userAccessToken]);
    }
    
    // Validar credenciais e configurar webhook
    $facebookChannel = new FacebookChannel($pdo, $channelId);
    
    if (!$facebookChannel->validateCredentials()) {
        throw new Exception('Credenciais do Facebook inválidas. Verifique os tokens e tente novamente.');
    }
    
    if (!$facebookChannel->setupWebhook()) {
        throw new Exception('Erro ao configurar webhook. Verifique as permissões da página.');
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Canal Facebook configurado com sucesso!',
        'channel_id' => $channelId
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
