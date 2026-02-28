<?php
/**
 * Webhook Endpoint para Facebook Messenger
 * Recebe e processa mensagens do Facebook Messenger Platform
 */

header('Content-Type: application/json');

require_once '../../config/database.php';
require_once '../../includes/channels/FacebookChannel.php';

// Verificação do webhook (GET request)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $verifyToken = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';
    $mode = $_GET['hub_mode'] ?? '';
    
    // Token de verificação configurado no Facebook App
    // Deve ser o mesmo configurado em FACEBOOK_VERIFY_TOKEN no .env
    $expectedToken = getenv('FACEBOOK_VERIFY_TOKEN') ?: 'wats_facebook_verify_token_2026';
    
    if ($mode === 'subscribe' && $verifyToken === $expectedToken) {
        echo $challenge;
        exit;
    } else {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

// Processar webhook (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Capturar payload do webhook
        $payload = json_decode(file_get_contents('php://input'), true);
        
        if (!$payload || !isset($payload['entry'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid payload']);
            exit;
        }
        
        // Log do webhook recebido (opcional, para debug)
        error_log("Facebook Webhook: " . json_encode($payload));
        
        // Processar cada entry
        foreach ($payload['entry'] as $entry) {
            if (!isset($entry['messaging'])) {
                continue;
            }
            
            foreach ($entry['messaging'] as $event) {
                // Identificar página pelo recipient ID
                $recipientId = $event['recipient']['id'] ?? null;
                
                if (!$recipientId) {
                    continue;
                }
                
                // Buscar canal pela page_id
                $stmt = $pdo->prepare("
                    SELECT c.id 
                    FROM channels c
                    JOIN channel_facebook cf ON cf.channel_id = c.id
                    WHERE cf.page_id = ? AND c.status = 'active'
                ");
                $stmt->execute([$recipientId]);
                $channel = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$channel) {
                    error_log("Facebook channel not found for page_id: {$recipientId}");
                    continue;
                }
                
                // Processar webhook
                $facebookChannel = new FacebookChannel($pdo, $channel['id']);
                $result = $facebookChannel->receiveWebhook($payload);
                
                if (!$result['success']) {
                    error_log("Facebook webhook processing error: " . ($result['error'] ?? 'Unknown error'));
                }
            }
        }
        
        // Facebook espera resposta 200 OK rapidamente
        http_response_code(200);
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        error_log("Facebook Webhook Error: " . $e->getMessage());
        http_response_code(200); // Ainda retornar 200 para não reenviar
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo 'Method Not Allowed';
