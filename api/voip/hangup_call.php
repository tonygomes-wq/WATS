<?php
/**
 * API: Desligar Chamada VoIP
 * Encerra uma chamada ativa
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/voip/VoIPManager.php';
require_once '../../includes/voip/FreeSwitchAPI.php';

requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // Obter dados do POST
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['call_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID da chamada não informado'
        ]);
        exit;
    }
    
    $callId = $data['call_id'];
    
    // Buscar chamada no banco
    $stmt = $pdo->prepare("
        SELECT * FROM voip_calls 
        WHERE call_id = ? AND user_id = ?
    ");
    $stmt->execute([$callId, $userId]);
    $call = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$call) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Chamada não encontrada'
        ]);
        exit;
    }
    
    // Verificar se chamada já foi encerrada
    if ($call['status'] === 'ended' || $call['status'] === 'failed') {
        echo json_encode([
            'success' => true,
            'message' => 'Chamada já foi encerrada',
            'status' => $call['status']
        ]);
        exit;
    }
    
    // Desligar no FreeSWITCH (se tiver UUID)
    $hangupSuccess = true;
    if (!empty($call['freeswitch_uuid'])) {
        $freeswitchAPI = new FreeSwitchAPI();
        $hangupSuccess = $freeswitchAPI->hangupCall($call['freeswitch_uuid']);
    }
    
    // Atualizar status no banco
    $voipManager = new VoIPManager($pdo);
    $voipManager->updateCallStatus($callId, 'ended', [
        'hangup_cause' => $data['hangup_cause'] ?? 'NORMAL_CLEARING'
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Chamada encerrada',
        'call_id' => $callId,
        'freeswitch_hangup' => $hangupSuccess
    ]);
    
} catch (Exception $e) {
    error_log("VoIP Hangup Call Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao encerrar chamada'
    ]);
}
