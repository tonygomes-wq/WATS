<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$categoryId = intval($_GET['category_id'] ?? 0);
$userId = $_SESSION['user_id'];

if ($categoryId === 0) {
    echo json_encode(['contacts' => []]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT c.id, c.name, c.phone
        FROM contacts c
        INNER JOIN contact_categories cc ON c.id = cc.contact_id
        WHERE cc.category_id = ? AND c.user_id = ?
        ORDER BY c.name, c.phone
    ");
    $stmt->execute([$categoryId, $userId]);
    $contacts = $stmt->fetchAll();
    
    echo json_encode(['contacts' => $contacts]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
