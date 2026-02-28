<?php
/**
 * API: Salvar Configurações do Provedor VoIP
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

requireLogin();

// Apenas Admin pode configurar
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
if (!$is_admin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validar dados obrigatórios
    if (empty($data['server_host']) || empty($data['sip_domain'])) {
        throw new Exception('Host e domínio SIP são obrigatórios');
    }
    
    // Atualizar ou inserir configurações
    $stmt = $pdo->prepare("
        INSERT INTO voip_provider_settings (
            id, provider_type, server_host, wss_port, sip_domain,
            esl_port, esl_password, stun_server
        ) VALUES (1, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            provider_type = VALUES(provider_type),
            server_host = VALUES(server_host),
            wss_port = VALUES(wss_port),
            sip_domain = VALUES(sip_domain),
            esl_port = VALUES(esl_port),
            esl_password = VALUES(esl_password),
            stun_server = VALUES(stun_server)
    ");
    
    $stmt->execute([
        $data['provider_type'] ?? 'freeswitch',
        $data['server_host'],
        $data['wss_port'] ?? 8083,
        $data['sip_domain'],
        $data['esl_port'] ?? 8021,
        $data['esl_password'] ?? '',
        $data['stun_server'] ?? 'stun:stun.l.google.com:19302'
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Configurações salvas com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
