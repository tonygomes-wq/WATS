<?php
/**
 * API: Histórico de Chamadas VoIP
 * Retorna o histórico de chamadas do usuário
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/voip/VoIPManager.php';

requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // Obter filtros da query string
    $filters = [
        'direction' => $_GET['direction'] ?? null, // inbound, outbound
        'status' => $_GET['status'] ?? null, // initiated, answered, ended, failed
        'date_from' => $_GET['date_from'] ?? null,
        'date_to' => $_GET['date_to'] ?? null,
        'limit' => min((int)($_GET['limit'] ?? 50), 200),
        'offset' => (int)($_GET['offset'] ?? 0)
    ];
    
    // Remover filtros vazios
    $filters = array_filter($filters, function($value) {
        return $value !== null && $value !== '';
    });
    
    // Buscar histórico
    $voipManager = new VoIPManager($pdo);
    $calls = $voipManager->getCallHistory($userId, $filters);
    
    // Formatar dados
    $formattedCalls = array_map(function($call) {
        return [
            'id' => (int)$call['id'],
            'call_id' => $call['call_id'],
            'direction' => $call['direction'],
            'status' => $call['status'],
            'caller_number' => $call['caller_number'],
            'caller_name' => $call['caller_name'],
            'callee_number' => $call['callee_number'],
            'callee_name' => $call['callee_name'],
            'contact_id' => $call['contact_id'] ? (int)$call['contact_id'] : null,
            'contact_name' => $call['contact_name'],
            'contact_phone' => $call['contact_phone'],
            'start_time' => $call['start_time'],
            'answer_time' => $call['answer_time'],
            'end_time' => $call['end_time'],
            'duration' => $call['duration'] ? (int)$call['duration'] : null,
            'duration_formatted' => $call['duration'] ? formatDuration($call['duration']) : null,
            'hangup_cause' => $call['hangup_cause'],
            'quality_score' => $call['quality_score'] ? (float)$call['quality_score'] : null,
            'recording_url' => $call['recording_url'],
            'created_at' => $call['created_at']
        ];
    }, $calls);
    
    // Contar total (para paginação)
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM voip_calls 
        WHERE user_id = ?
    ");
    $countStmt->execute([$userId]);
    $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true,
        'calls' => $formattedCalls,
        'pagination' => [
            'total' => $total,
            'limit' => $filters['limit'],
            'offset' => $filters['offset'],
            'has_more' => ($filters['offset'] + $filters['limit']) < $total
        ]
    ]);
    
} catch (Exception $e) {
    error_log("VoIP Call History Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar histórico de chamadas'
    ]);
}

/**
 * Formatar duração em segundos para formato legível
 */
function formatDuration($seconds) {
    if ($seconds < 60) {
        return $seconds . 's';
    }
    
    $minutes = floor($seconds / 60);
    $remainingSeconds = $seconds % 60;
    
    if ($minutes < 60) {
        return sprintf('%dm %ds', $minutes, $remainingSeconds);
    }
    
    $hours = floor($minutes / 60);
    $remainingMinutes = $minutes % 60;
    
    return sprintf('%dh %dm %ds', $hours, $remainingMinutes, $remainingSeconds);
}
