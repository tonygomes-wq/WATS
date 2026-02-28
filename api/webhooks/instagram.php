<?php
/**
 * Instagram Webhook Endpoint
 * Recebe e processa webhooks do Instagram Graph API
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/channels/InstagramChannel.php';

// Verificação do webhook (GET request do Facebook)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';
    
    // Token de verificação (deve ser configurado no Facebook App)
    $verifyToken = getenv('INSTAGRAM_VERIFY_TOKEN') ?: 'wats_instagram_verify_token_2024';
    
    if ($mode === 'subscribe' && $token === $verifyToken) {
        echo $challenge;
        exit;
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'Token de verificação inválido']);
        exit;
    }
}

// Processar webhook (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $payload = json_decode($input, true);
    
    // Log do payload recebido
    error_log('[Instagram Webhook] Payload: ' . $input);
    
    if (!$payload) {
        http_response_code(400);
        echo json_encode(['error' => 'Payload inválido']);
        exit;
    }
    
    try {
        // Buscar canal ativo do Instagram
        $stmt = $pdo->prepare("
            SELECT c.*, ci.instagram_account_id, ci.access_token, ci.page_id
            FROM channels c
            INNER JOIN channel_instagram ci ON c.id = ci.channel_id
            WHERE c.channel_type = 'instagram' AND c.is_active = 1
            LIMIT 1
        ");
        $stmt->execute();
        $channel = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$channel) {
            error_log('[Instagram Webhook] Nenhum canal ativo encontrado');
            // Retornar 200 mesmo assim para não gerar erros no Facebook
            echo json_encode(['status' => 'ok', 'message' => 'Nenhum canal ativo']);
            exit;
        }
        
        // Inicializar canal do Instagram
        $instagram = new InstagramChannel($pdo, $channel['id']);
        
        // Processar webhook
        $result = $instagram->processWebhook($payload);
        
        echo json_encode([
            'status' => 'ok',
            'processed' => $result
        ]);
        
    } catch (Exception $e) {
        error_log('[Instagram Webhook] Erro: ' . $e->getMessage());
        
        // Retornar 200 para não gerar retry do Facebook
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    
    exit;
}

// Método não permitido
http_response_code(405);
echo json_encode(['error' => 'Método não permitido']);
