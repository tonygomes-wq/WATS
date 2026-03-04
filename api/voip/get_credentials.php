<?php
/**
 * API: Obter Credenciais VoIP
 * Retorna as credenciais SIP do usuário para conexão WebRTC
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // Buscar conta VoIP do usuário
    $stmt = $pdo->prepare("SELECT * FROM voip_users WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$account) {
        echo json_encode([
            'success' => true,
            'has_account' => false,
            'provider_configured' => false,
            'account' => null
        ]);
        exit;
    }
    
    // Verificar se tem servidor configurado
    $hasServer = !empty($account['sip_server']) && !empty($account['sip_domain']);
    
    // Retornar dados da conta
    echo json_encode([
        'success' => true,
        'has_account' => true,
        'provider_configured' => $hasServer,
        'account' => [
            'id' => $account['id'],
            'account_name' => $account['account_name'],
            'sip_server' => $account['sip_server'],
            'sip_proxy' => $account['sip_proxy'],
            'sip_username' => $account['sip_username'],
            'sip_domain' => $account['sip_domain'],
            'auth_id' => $account['auth_id'],
            'display_name' => $account['display_name'],
            'voicemail_number' => $account['voicemail_number'],
            'transport' => $account['transport'] ?? 'udp',
            'srtp' => $account['srtp'] ?? 0,
            'publish_presence' => $account['publish_presence'] ?? 1,
            'ice' => $account['ice'] ?? 1,
            'extension' => $account['extension']
        ],
        'credentials' => [
            'extension' => $account['extension'],
            'username' => $account['sip_username'],
            'display_name' => $account['display_name'],
            'sip_domain' => $account['sip_domain'],
            'sip_server' => $account['sip_server'],
            'transport' => $account['transport'] ?? 'udp'
        ]
    ]);
    
} catch (Exception $e) {
    error_log("VoIP Get Credentials Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao obter credenciais VoIP: ' . $e->getMessage()
    ]);
}

