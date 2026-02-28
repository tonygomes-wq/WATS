<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

requireAdmin(); // Apenas administradores podem usar esta API

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'create_for_user':
        createInstanceForUser();
        break;
    case 'get_user_status':
        getUserInstanceStatus();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
}

/**
 * Criar instância para um usuário específico (apenas admin)
 */
function createInstanceForUser() {
    global $pdo;
    
    $user_id = intval($_POST['user_id'] ?? 0);
    
    if ($user_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de usuário inválido']);
        return;
    }
    
    // Buscar dados do usuário
    $stmt = $pdo->prepare("SELECT name, email, evolution_instance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        return;
    }
    
    // Verificar se já tem instância
    if (!empty($user['evolution_instance'])) {
        echo json_encode(['success' => false, 'message' => 'Usuário já possui uma instância configurada']);
        return;
    }
    
    // Gerar nome único para a instância
    $instanceName = 'user_' . $user_id . '_' . time();
    
    // Dados para criar a instância
    $instanceData = [
        'instanceName' => $instanceName,
        'token' => generateInstanceToken(),
        'qrcode' => true,
        'integration' => 'WHATSAPP-BAILEYS'
    ];
    
    // Criar instância via Evolution API
    $response = callEvolutionAPI('/instance/create', 'POST', $instanceData);
    
    if ($response['success']) {
        // Salvar no banco de dados
        $stmt = $pdo->prepare("UPDATE users SET evolution_instance = ?, evolution_token = ? WHERE id = ?");
        $success = $stmt->execute([$instanceName, $instanceData['token'], $user_id]);
        
        if ($success) {
            echo json_encode([
                'success' => true,
                'message' => 'Instância criada com sucesso para ' . $user['name'],
                'instance_name' => $instanceName,
                'token' => $instanceData['token'],
                'user_name' => $user['name']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar instância no banco']);
        }
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Erro ao criar instância na Evolution API: ' . $response['message']
        ]);
    }
}

/**
 * Obter status da instância de um usuário
 */
function getUserInstanceStatus() {
    global $pdo;
    
    $user_id = intval($_GET['user_id'] ?? 0);
    
    if ($user_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de usuário inválido']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT evolution_instance, evolution_token FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (empty($user['evolution_instance'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Nenhuma instância configurada',
            'status' => 'not_configured'
        ]);
        return;
    }
    
    // Verificar status na Evolution API
    $response = callEvolutionAPI('/instance/connectionState/' . $user['evolution_instance'], 'GET');
    
    if ($response['success']) {
        $data = $response['data'];
        echo json_encode([
            'success' => true,
            'instance_name' => $user['evolution_instance'],
            'status' => $data['instance']['state'] ?? 'unknown',
            'data' => $data
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao verificar status: ' . $response['message'],
            'status' => 'error'
        ]);
    }
}

/**
 * Função auxiliar para chamar Evolution API
 */
function callEvolutionAPI($endpoint, $method = 'GET', $data = null) {
    $url = EVOLUTION_API_URL . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . EVOLUTION_API_KEY
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'message' => 'Erro de conexão: ' . $error];
    }
    
    if ($httpCode >= 200 && $httpCode < 300) {
        $responseData = json_decode($response, true);
        return ['success' => true, 'data' => $responseData];
    } else {
        $errorData = json_decode($response, true);
        return [
            'success' => false, 
            'message' => $errorData['message'] ?? 'Erro HTTP: ' . $httpCode,
            'http_code' => $httpCode
        ];
    }
}

/**
 * Gerar token único para a instância
 */
function generateInstanceToken() {
    return bin2hex(random_bytes(16));
}
?>
