<?php
/**
 * API: Transferir Chamada VoIP
 * Transfere uma chamada ativa para outro ramal/número
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
    
    if (empty($data['call_id']) || empty($data['destination'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID da chamada e destino são obrigatórios'
        ]);
        exit;
    }
    
    $callId = $data['call_id'];
    $destination = preg_replace('/[^0-9+]/', '', $data['destination']);
    
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
    
    // Verificar se chamada está ativa
    if ($call['status'] !== 'answered') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Chamada não está ativa',
            'current_status' => $call['status']
        ]);
        exit;
    }
    
    // Transferir no FreeSWITCH
    if (empty($call['freeswitch_uuid'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'UUID do FreeSWITCH não encontrado'
        ]);
        exit;
    }
    
    $freeswitchAPI = new FreeSwitchAPI();
    $transferSuccess = $freeswitchAPI->transferCall($call['freeswitch_uuid'], $destination);
    
    if ($transferSuccess) {
        // Registrar transferência no banco
        $stmt = $pdo->prepare("
            INSERT INTO voip_call_transfers (
                call_id, transferred_by, transferred_to, transferred_at
            ) VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$call['id'], $userId, $destination]);
        
        // Atualizar status da chamada
        $voipManager = new VoIPManager($pdo);
        $voipManager->updateCallStatus($callId, 'transferred', [
            'hangup_cause' => 'ATTENDED_TRANSFER'
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Chamada transferida com sucesso',
            'call_id' => $callId,
            'destination' => $destination
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Falha ao transferir chamada no servidor VoIP'
        ]);
    }
    
} catch (Exception $e) {
    error_log("VoIP Transfer Call Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao transferir chamada'
    ]);
}
