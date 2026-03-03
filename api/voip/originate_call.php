<?php
/**
 * API: Originar Chamada VoIP
 * Inicia uma chamada VoIP para um número
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
    
    if (empty($data['to'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Número de destino não informado'
        ]);
        exit;
    }
    
    $to = $data['to'];
    $contactId = $data['contact_id'] ?? null;
    
    // Validar número (básico)
    $to = preg_replace('/[^0-9+]/', '', $to);
    
    if (empty($to)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Número inválido'
        ]);
        exit;
    }
    
    // Obter credenciais do usuário
    $voipManager = new VoIPManager($pdo);
    $credentials = $voipManager->getUserCredentials($userId);
    
    if (!$credentials) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Conta VoIP não encontrada'
        ]);
        exit;
    }
    
    $extension = $credentials['sip_extension'];
    
    // Buscar informações do contato (se fornecido)
    $contactName = null;
    if ($contactId) {
        $stmt = $pdo->prepare("SELECT name FROM contacts WHERE id = ? AND user_id = ?");
        $stmt->execute([$contactId, $userId]);
        $contact = $stmt->fetch(PDO::FETCH_ASSOC);
        $contactName = $contact['name'] ?? null;
    }
    
    // Gerar ID único para a chamada
    $callId = uniqid('call_', true);
    
    // Registrar chamada no banco
    $callDbId = $voipManager->registerCall([
        'user_id' => $userId,
        'voip_user_id' => $credentials['id'],
        'contact_id' => $contactId,
        'call_id' => $callId,
        'direction' => 'outbound',
        'caller_number' => $extension,
        'caller_name' => $credentials['display_name'],
        'callee_number' => $to,
        'callee_name' => $contactName
    ]);
    
    // Tentar originar chamada no FreeSWITCH
    $freeswitchAPI = new FreeSwitchAPI();
    $fsCallId = $freeswitchAPI->originateCall($extension, $to);
    
    if ($fsCallId) {
        // Atualizar com ID do FreeSWITCH
        $stmt = $pdo->prepare("UPDATE voip_calls SET freeswitch_uuid = ? WHERE id = ?");
        $stmt->execute([$fsCallId, $callDbId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Chamada iniciada',
            'call_id' => $callId,
            'freeswitch_uuid' => $fsCallId,
            'to' => $to,
            'from' => $extension
        ]);
    } else {
        // Atualizar status para falha
        $voipManager->updateCallStatus($callId, 'failed', [
            'hangup_cause' => 'ORIGINATE_FAILED'
        ]);
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Falha ao originar chamada no servidor VoIP'
        ]);
    }
    
} catch (Exception $e) {
    error_log("VoIP Originate Call Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao iniciar chamada'
    ]);
}
