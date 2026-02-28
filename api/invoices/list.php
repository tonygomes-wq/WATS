<?php
session_start();

require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];
$isAdmin = isAdmin();

$targetUserId = $userId;
if ($isAdmin && isset($_GET['user_id'])) {
    $targetUserId = (int) $_GET['user_id'];
}

$limit = isset($_GET['limit']) ? max(1, min(200, (int) $_GET['limit'])) : 50;

do {
    if ($targetUserId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'user_id inválido']);
        return;
    }

    if (!$isAdmin && $targetUserId !== $userId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso negado']);
        return;
    }
} while (false);

$statusFilter = null;
if (!empty($_GET['status'])) {
    $statusFilter = $_GET['status'];
}

$query = "SELECT id, invoice_number, status, total_amount, currency, due_date, created_at, pdf_path
    FROM invoices
    WHERE user_id = ?";
$params = [$targetUserId];

if ($statusFilter) {
    $query .= " AND status = ?";
    $params[] = $statusFilter;
}

$query .= " ORDER BY created_at DESC LIMIT ?";
$params[] = $limit;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'invoices' => $invoices,
]);
