<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Não logado']);
    exit;
}

// Buscar dados do usuário para obter instância
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$instance = $user['evolution_instance'] ?? 'CELULAR-MACIP';
$userToken = $user['evolution_token'] ?: EVOLUTION_API_KEY;

// Verificar se é uma ação especial via POST
$inputData = json_decode(file_get_contents('php://input'), true);
$action = $inputData['action'] ?? '';

if ($action) {
    handleSpecialAction($action, $inputData, $instance, $userToken);
    exit;
}

$endpoint = $_GET['endpoint'] ?? '';
$method = $_GET['method'] ?? 'GET';

if (empty($endpoint)) {
    echo json_encode(['success' => false, 'message' => 'Endpoint não especificado']);
    exit;
}

// Função para lidar com ações especiais
function handleSpecialAction($action, $data, $instance, $token) {
    switch ($action) {
        case 'configure_webhook':
            $webhookUrl = $data['webhook_url'] ?? '';
            if (empty($webhookUrl)) {
                echo json_encode(['success' => false, 'error' => 'URL do webhook não fornecida']);
                return;
            }
            
            $result = callEvolutionAPIWithToken("/webhook/set/$instance", 'POST', [
                'url' => $webhookUrl,
                'enabled' => true,
                'webhookByEvents' => true,
                'events' => [
                    'MESSAGES_UPSERT',
                    'MESSAGES_UPDATE',
                    'SEND_MESSAGE',
                    'CONNECTION_UPDATE',
                    'CHATS_UPSERT',
                    'CONTACTS_UPSERT'
                ]
            ], $token);
            
            echo json_encode($result);
            break;
            
        case 'fix_store_messages':
            $result = callEvolutionAPIWithToken("/settings/set/$instance", 'POST', [
                'rejectCall' => false,
                'msgCall' => '',
                'groupsIgnore' => false,
                'alwaysOnline' => false,
                'readMessages' => false,
                'readStatus' => false,
                'syncFullHistory' => true
            ], $token);
            
            echo json_encode($result);
            break;
            
        case 'get_instance_status':
            $result = callEvolutionAPIWithToken("/instance/connectionState/$instance", 'GET', null, $token);
            echo json_encode($result);
            break;
            
        case 'get_webhook_config':
            $result = callEvolutionAPIWithToken("/webhook/find/$instance", 'GET', null, $token);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Ação desconhecida: ' . $action]);
    }
}

// Função para chamar Evolution API com token específico
function callEvolutionAPIWithToken($endpoint, $method = 'GET', $data = null, $token = null) {
    $url = EVOLUTION_API_URL . $endpoint;
    $apiKey = $token ?: EVOLUTION_API_KEY;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . $apiKey
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => $error];
    }
    
    if ($httpCode >= 200 && $httpCode < 300) {
        $responseData = json_decode($response, true);
        return ['success' => true, 'data' => $responseData, 'http_code' => $httpCode];
    }
    
    return ['success' => false, 'error' => "HTTP $httpCode", 'response' => $response];
}

// Função para chamar Evolution API
function callEvolutionAPI($endpoint, $method = 'GET', $data = null) {
    $url = EVOLUTION_API_URL . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . EVOLUTION_API_KEY
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $result = [
        'success' => false,
        'http_code' => $httpCode,
        'endpoint' => $endpoint,
        'method' => $method,
        'url' => $url
    ];
    
    if ($error) {
        $result['error'] = $error;
        $result['message'] = 'Erro de conexão: ' . $error;
    } elseif ($httpCode >= 200 && $httpCode < 300) {
        $result['success'] = true;
        $result['raw_response'] = $response;
        
        $responseData = json_decode($response, true);
        if ($responseData !== null) {
            $result['data'] = $responseData;
            
            // Procurar QR Code na resposta
            $qrCode = findQRCodeInResponse($responseData);
            if ($qrCode) {
                $result['qr_found'] = true;
                $result['qr_code'] = $qrCode;
            }
        } else {
            $result['data'] = $response;
        }
    } else {
        $result['message'] = 'HTTP Error: ' . $httpCode;
        $result['raw_response'] = $response;
    }
    
    return $result;
}

// Função para encontrar QR Code na resposta
function findQRCodeInResponse($data) {
    if (!is_array($data)) {
        return null;
    }
    
    // Lista de possíveis locais onde o QR Code pode estar
    $possibleLocations = [
        $data['qrcode']['base64'] ?? null,
        $data['base64'] ?? null,
        $data['qr'] ?? null,
        $data['code'] ?? null,
        $data['qrcode'] ?? null,
        $data['qrCode'] ?? null,
        $data['qr_code'] ?? null,
        $data['data']['qrcode']['base64'] ?? null,
        $data['data']['base64'] ?? null,
        $data['data']['qr'] ?? null,
        $data['data']['code'] ?? null,
        $data['data']['qrcode'] ?? null
    ];
    
    foreach ($possibleLocations as $qrCode) {
        if (!empty($qrCode) && is_string($qrCode) && strlen($qrCode) > 100) {
            // Verificar se parece com base64 válido
            if (preg_match('/^[A-Za-z0-9+\/]+=*$/', $qrCode)) {
                return $qrCode;
            }
        }
    }
    
    return null;
}

// Executar chamada
$result = callEvolutionAPI($endpoint, $method);

echo json_encode($result, JSON_PRETTY_PRINT);
?>
