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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não suportado']);
    exit;
}

$dispatchId = (int)($_POST['id'] ?? ($_GET['id'] ?? 0));
if ($dispatchId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'ID inválido']);
    exit;
}

try {
    $service = new ScheduledDispatchService($pdo);
    $success = $service->cancelDispatch($dispatchId, (int)$_SESSION['user_id'], isAdmin());

    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Agendamento não encontrado ou já processado.']);
    }
} catch (Throwable $e) {
    error_log('[scheduled_dispatch:cancel] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao cancelar agendamento.']);
}
