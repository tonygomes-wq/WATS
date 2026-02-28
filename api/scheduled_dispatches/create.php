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

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'JSON inválido']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$message = trim($payload['message'] ?? '');
$scheduledRaw = $payload['scheduled_for'] ?? '';
$contactIds = $payload['contacts'] ?? [];

if ($message === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Informe a mensagem a ser enviada.']);
    exit;
}

if (empty($contactIds) || !is_array($contactIds)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Selecione ao menos um contato.']);
    exit;
}

try {
    $scheduledDate = new DateTime($scheduledRaw ?: '');
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Data de agendamento inválida.']);
    exit;
}

if ($scheduledDate <= new DateTime('+1 minute')) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'A data/hora precisa ser pelo menos 1 minuto à frente.']);
    exit;
}

$contactIds = array_values(array_unique(array_map('intval', $contactIds)));
$placeholders = implode(',', array_fill(0, count($contactIds), '?'));
$params = array_merge([$userId], $contactIds);

$stmt = $pdo->prepare("SELECT id, name, phone FROM contacts WHERE user_id = ? AND id IN ($placeholders)");
$stmt->execute($params);
$contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($contacts)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Nenhum contato válido encontrado.']);
    exit;
}

$contactEntries = [];
foreach ($contacts as $contact) {
    $phone = formatPhone($contact['phone'] ?? '');
    if (!$phone) {
        continue;
    }
    $contactEntries[] = [
        'contact_id' => (int) $contact['id'],
        'name' => $contact['name'] ?: 'Sem nome',
        'phone' => $phone,
    ];
}

if (empty($contactEntries)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Os contatos selecionados não possuem telefone válido.']);
    exit;
}

try {
    $service = new ScheduledDispatchService($pdo);
    $dispatch = $service->schedule([
        'user_id' => $userId,
        'message' => $message,
        'scheduled_for' => $scheduledDate->format('Y-m-d H:i:s'),
        'contacts' => $contactEntries,
    ]);

    echo json_encode(['success' => true, 'dispatch' => $dispatch]);
} catch (Throwable $e) {
    error_log('[scheduled_dispatch:create] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao salvar agendamento.']);
}
