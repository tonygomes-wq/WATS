<?php
session_start();

require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/ScheduledDispatchService.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$isAdmin = isAdmin();

$status = $_GET['status'] ?? null;
$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));

$filters = [];
if ($status) {
    $filters['status'] = $status;
}
if ($from) {
    $filters['from'] = $from;
}
if ($to) {
    $filters['to'] = $to;
}
if ($isAdmin && !empty($_GET['user_id'])) {
    $filters['user_id'] = (int) $_GET['user_id'];
}

try {
    $service = new ScheduledDispatchService($pdo);
    $items = $service->getDispatches($userId, $filters, $isAdmin, $page, $limit);
    $total = $service->countDispatches($userId, $filters, $isAdmin);

    echo json_encode([
        'success' => true,
        'data' => $items,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => (int) ceil($total / $limit),
        ],
    ]);
} catch (Throwable $e) {
    error_log('[scheduled_dispatch:list] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao carregar agendamentos.']);
}
