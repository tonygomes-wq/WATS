<?php
/**
 * API: Obter Credenciais VoIP
 * Retorna as credenciais SIP do usuário para conexão WebRTC
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
    $voipManager = new VoIPManager($pdo);
    $credentials = $voipManager->getUserCredentials($userId);
    
    if (!$credentials) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Conta VoIP não encontrada',
            'has_account' => false
        ]);
        exit;
    }
    
    // Buscar configurações do provedor
    $stmt = $pdo->prepare("SELECT * FROM voip_provider_settings WHERE id = 1");
    $stmt->execute();
    $provider = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$provider || empty($provider['server_host'])) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'error' => 'Provedor VoIP não configurado',
            'has_account' => true,
            'provider_configured' => false
        ]);
        exit;
    }
    
    // Montar URL WebSocket Secure
    $wssUrl = "wss://{$provider['server_host']}:{$provider['wss_port']}";
    
    // Retornar credenciais
    echo json_encode([
        'success' => true,
        'has_account' => true,
        'provider_configured' => true,
        'credentials' => [
            'extension' => $credentials['sip_extension'],
            'username' => $credentials['sip_username'],
            'display_name' => $credentials['display_name'],
            'sip_domain' => $provider['sip_domain'],
            'wss_url' => $wssUrl,
            'stun_server' => $provider['stun_server'] ?? 'stun:stun.l.google.com:19302'
        ],
        'provider' => [
            'type' => $provider['provider_type'],
            'host' => $provider['server_host'],
            'domain' => $provider['sip_domain']
        ]
    ]);
    
} catch (Exception $e) {
    error_log("VoIP Get Credentials Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao obter credenciais VoIP'
    ]);
}
