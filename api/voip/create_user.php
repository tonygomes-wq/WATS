<?php
/**
 * API: Criar Usuário VoIP
 * Cria uma conta VoIP (ramal) para o usuário logado
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/voip/VoIPManager.php';

requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Usuário';

try {
    // Verificar se usuário já tem conta VoIP
    $stmt = $pdo->prepare("SELECT id FROM voip_users WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Você já possui uma conta VoIP'
        ]);
        exit;
    }
    
    // Obter dados do POST
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Preparar dados do usuário
    $voipData = [
        'display_name' => $data['display_name'] ?? $userName,
        'voicemail_enabled' => $data['voicemail_enabled'] ?? true,
        'username' => "user_{$userId}"
    ];
    
    // Criar usuário VoIP
    $voipManager = new VoIPManager($pdo);
    $result = $voipManager->createVoIPUser($userId, $voipData);
    
    if ($result['success']) {
        // Log de sucesso
        error_log("VoIP: Usuário criado - User ID: {$userId}, Extension: {$result['extension']}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Conta VoIP criada com sucesso!',
            'data' => [
                'extension' => $result['extension'],
                'display_name' => $voipData['display_name'],
                'voicemail_enabled' => $voipData['voicemail_enabled']
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Erro ao criar conta VoIP'
        ]);
    }
    
} catch (Exception $e) {
    error_log("VoIP Create User Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno ao criar conta VoIP'
    ]);
}
