<?php
// Webhook da API Oficial do WhatsApp (Meta)
// Responsável por validar o token de verificação e registrar eventos recebidos

declare(strict_types=1);

require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    handleVerification();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleIncomingEvent();
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

exit;

function handleVerification(): void
{
    global $pdo;

    $mode = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '';
    $verifyToken = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '';

    if ($mode !== 'subscribe' || empty($verifyToken)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid verification request']);
        return;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE meta_webhook_verify_token = ? LIMIT 1");
    $stmt->execute([$verifyToken]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid verify token']);
        return;
    }

    http_response_code(200);
    echo $challenge ?: 'VERIFIED';
}

function handleIncomingEvent(): void
{
    $payload = file_get_contents('php://input');
    $data = json_decode($payload, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
        return;
    }

    // Registrar em log para auditoria/debug
    error_log('[META WEBHOOK] ' . $payload);

    // TODO: Associar eventos a usuários específicos e atualizar status no dispatch_history

    http_response_code(200);
    echo json_encode(['success' => true]);
}
