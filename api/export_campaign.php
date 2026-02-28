<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';

header('Content-Type: text/csv; charset=utf-8');

$userId = $_SESSION['user_id'];
$campaignId = (int)($_GET['id'] ?? 0);

if (!$campaignId) {
    http_response_code(400);
    die('ID da campanha é obrigatório');
}

// Verificar se a campanha pertence ao usuário
$stmt = $pdo->prepare("SELECT name FROM dispatch_campaigns WHERE id = ? AND user_id = ?");
$stmt->execute([$campaignId, $userId]);
$campaign = $stmt->fetch();

if (!$campaign) {
    http_response_code(404);
    die('Campanha não encontrada');
}

// Nome do arquivo
$filename = 'campanha_' . $campaignId . '_' . date('Y-m-d_His') . '.csv';
header("Content-Disposition: attachment; filename=\"{$filename}\"");

// Buscar dados da campanha
$stmt = $pdo->prepare("
    SELECT 
        dh.id,
        dh.phone,
        dh.contact_name,
        dh.message,
        dh.status,
        dh.sent_at,
        dh.delivered_at,
        dh.read_at,
        dh.error_message,
        dr.message_text as response_text,
        dr.sentiment,
        dr.response_time_seconds,
        dr.received_at as response_received_at
    FROM dispatch_history dh
    LEFT JOIN dispatch_responses dr ON dh.id = dr.dispatch_id AND dr.is_first_response = 1
    WHERE dh.campaign_id = ? AND dh.user_id = ?
    ORDER BY dh.sent_at DESC
");

$stmt->execute([$campaignId, $userId]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Abrir output
$output = fopen('php://output', 'w');

// BOM para UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Cabeçalhos
fputcsv($output, [
    'ID',
    'Telefone',
    'Nome',
    'Status',
    'Enviado Em',
    'Entregue Em',
    'Lido Em',
    'Erro',
    'Respondeu',
    'Resposta',
    'Sentimento',
    'Tempo de Resposta (min)',
    'Respondido Em'
], ';');

// Dados
foreach ($records as $record) {
    $hasResponse = !empty($record['response_text']);
    $responseTime = $record['response_time_seconds'] ? round($record['response_time_seconds'] / 60, 2) : '';
    
    $sentimentLabels = [
        'positive' => 'Positivo',
        'neutral' => 'Neutro',
        'negative' => 'Negativo',
        'unknown' => 'Desconhecido'
    ];
    
    $statusLabels = [
        'pending' => 'Pendente',
        'sent' => 'Enviado',
        'delivered' => 'Entregue',
        'read' => 'Lido',
        'failed' => 'Falhou'
    ];
    
    fputcsv($output, [
        $record['id'],
        $record['phone'],
        $record['contact_name'] ?: 'N/A',
        $statusLabels[$record['status']] ?? $record['status'],
        $record['sent_at'] ? date('d/m/Y H:i:s', strtotime($record['sent_at'])) : '',
        $record['delivered_at'] ? date('d/m/Y H:i:s', strtotime($record['delivered_at'])) : '',
        $record['read_at'] ? date('d/m/Y H:i:s', strtotime($record['read_at'])) : '',
        $record['error_message'] ?: '',
        $hasResponse ? 'Sim' : 'Não',
        $record['response_text'] ?: '',
        $hasResponse ? ($sentimentLabels[$record['sentiment']] ?? '') : '',
        $responseTime,
        $record['response_received_at'] ? date('d/m/Y H:i:s', strtotime($record['response_received_at'])) : ''
    ], ';');
}

fclose($output);
exit;
