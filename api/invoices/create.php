<?php
session_start();

require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/InvoiceService.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'JSON inválido']);
    exit;
}

$userId = (int)($payload['user_id'] ?? 0);
$items = $payload['items'] ?? [];

if ($userId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'user_id é obrigatório']);
    exit;
}

if (empty($items) || !is_array($items)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Informe ao menos um item']);
    exit;
}

try {
    $service = new InvoiceService($pdo);
    $invoice = $service->createInvoice([
        'user_id' => $userId,
        'subscription_id' => $payload['subscription_id'] ?? null,
        'payment_id' => $payload['payment_id'] ?? null,
        'amount' => isset($payload['amount']) ? (float)$payload['amount'] : 0,
        'tax_amount' => isset($payload['tax_amount']) ? (float)$payload['tax_amount'] : 0,
        'discount_amount' => isset($payload['discount_amount']) ? (float)$payload['discount_amount'] : 0,
        'currency' => $payload['currency'] ?? 'BRL',
        'status' => $payload['status'] ?? 'sent',
        'due_date' => $payload['due_date'] ?? date('Y-m-d', strtotime('+3 days')),
        'notes' => $payload['notes'] ?? null,
        'items' => $items,
    ]);

    echo json_encode(['success' => true, 'invoice' => $invoice]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
