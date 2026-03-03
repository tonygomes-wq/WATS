<?php
/**
 * API: Hold/Unhold Chamada VoIP
 * Coloca uma chamada em espera ou retoma
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
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
    $action = $data['action'] ?? 'hold'; // hold ou unhold
    
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
    
    // Executar ação no FreeSWITCH
    if (empty($call['freeswitch_uuid'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'UUID do FreeSWITCH não encontrado'
        ]);
        exit;
    }
    
    $freeswitchAPI = new FreeSwitchAPI();
    
    if ($action === 'hold') {
        $success = $freeswitchAPI->holdCall($call['freeswitch_uuid']);
        $message = 'Chamada colocada em espera';
    } else {
        $success = $freeswitchAPI->unholdCall($call['freeswitch_uuid']);
        $message = 'Chamada retomada';
    }
    
    if ($success) {
        // Atualizar flag no banco (opcional)
        $stmt = $pdo->prepare("
            UPDATE voip_calls 
            SET on_hold = ? 
            WHERE id = ?
        ");
        $stmt->execute([($action === 'hold' ? 1 : 0), $call['id']]);
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'call_id' => $callId,
            'action' => $action,
            'on_hold' => ($action === 'hold')
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Falha ao executar ação no servidor VoIP'
        ]);
    }
    
} catch (Exception $e) {
    error_log("VoIP Hold/Unhold Call Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao executar ação'
    ]);
}
