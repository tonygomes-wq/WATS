<?php
// Evitar qualquer saída antes do JSON
ob_start();

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Limpar qualquer saída anterior
ob_clean();

header('Content-Type: application/json');

try {
    requireLogin();
    
    $user_id = $_SESSION['user_id'];
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro de autenticação: ' . $e->getMessage()]);
    exit;
}

switch ($action) {
    case 'create':
        createInstance();
        break;
    case 'status':
        getInstanceStatus();
        break;
    case 'qrcode':
        getQRCode();
        break;
    case 'connect':
        connectInstance();
        break;
    case 'delete':
        deleteInstance();
        break;
    case 'check_exists':
        checkInstanceExists();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
}

/**
 * Criar nova instância automaticamente
 */
function createInstance() {
    global $pdo, $user_id;
    
    try {
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
            echo json_encode(['success' => false, 'message' => 'Você já possui uma instância configurada']);
            return;
        }
        
        // Pegar nome personalizado da instância (se fornecido)
        $customName = $_POST['instance_name'] ?? '';
        
        // Debug: Log dos dados recebidos
        error_log("DEBUG CREATE INSTANCE - User ID: $user_id");
        error_log("DEBUG CREATE INSTANCE - POST data: " . json_encode($_POST));
        error_log("DEBUG CREATE INSTANCE - Custom name: '$customName'");
        
        if (!empty($customName)) {
            // Validar nome personalizado
            if (!preg_match('/^[a-zA-Z0-9-_]+$/', $customName)) {
                echo json_encode(['success' => false, 'message' => 'Nome da instância deve conter apenas letras, números, hífen (-) e underscore (_)']);
                return;
            }
            
            if (strlen($customName) < 3 || strlen($customName) > 50) {
                echo json_encode(['success' => false, 'message' => 'Nome da instância deve ter entre 3 e 50 caracteres']);
                return;
            }
            
            $instanceName = $customName;
        } else {
            // Gerar nome único automaticamente (fallback)
            $instanceName = 'user_' . $user_id . '_' . time();
        }
        
        // Debug: Log do nome final escolhido
        error_log("DEBUG CREATE INSTANCE - Final instance name: '$instanceName'");
        
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
            // Salvar no banco de dados (incluindo URL da Evolution API)
            error_log("DEBUG SAVE - Tentando salvar no banco: instance='$instanceName', user_id=$user_id, url=" . EVOLUTION_API_URL);
            
            $stmt = $pdo->prepare("UPDATE users SET evolution_instance = ?, evolution_token = ?, evolution_api_url = ? WHERE id = ?");
            $success = $stmt->execute([$instanceName, $instanceData['token'], EVOLUTION_API_URL, $user_id]);
            
            // Verificar se realmente salvou
            $rowsAffected = $stmt->rowCount();
            error_log("DEBUG SAVE - Rows affected: $rowsAffected");
            
            if ($success && $rowsAffected > 0) {
                // Verificar se realmente foi salvo
                $checkStmt = $pdo->prepare("SELECT evolution_instance, evolution_token, evolution_api_url FROM users WHERE id = ?");
                $checkStmt->execute([$user_id]);
                $savedData = $checkStmt->fetch();
                
                error_log("DEBUG SAVE - Dados salvos: " . json_encode($savedData));
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Instância criada com sucesso!',
                    'instance_name' => $instanceName,
                    'token' => $instanceData['token'],
                    'evolution_api_url' => EVOLUTION_API_URL,
                    'debug' => [
                        'rows_affected' => $rowsAffected,
                        'saved_instance' => $savedData['evolution_instance'],
                        'saved_token_length' => strlen($savedData['evolution_token']),
                        'saved_url' => $savedData['evolution_api_url']
                    ]
                ]);
            } else {
                error_log("DEBUG SAVE - ERRO: success=$success, rowsAffected=$rowsAffected");
                echo json_encode([
                    'success' => false, 
                    'message' => 'Erro ao salvar instância no banco',
                    'debug' => [
                        'pdo_success' => $success,
                        'rows_affected' => $rowsAffected,
                        'user_id' => $user_id
                    ]
                ]);
            }
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Erro ao criar instância: ' . $response['message']
            ]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
    }
}

/**
 * Obter status da instância
 */
function getInstanceStatus() {
    global $pdo, $user_id;
    
    try {
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
                'message' => 'Erro ao verificar status: ' . ($response['message'] ?? 'Erro desconhecido'),
                'status' => 'error'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erro interno: ' . $e->getMessage(),
            'status' => 'error'
        ]);
    }
}

/**
 * Conectar instância (obter QR Code)
 */
function connectInstance() {
    global $pdo, $user_id;
    
    $stmt = $pdo->prepare("SELECT evolution_instance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (empty($user['evolution_instance'])) {
        echo json_encode(['success' => false, 'message' => 'Instância não configurada']);
        return;
    }
    
    // Conectar instância
    $response = callEvolutionAPI('/instance/connect/' . $user['evolution_instance'], 'GET');
    
    if ($response['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Instância conectada com sucesso!',
            'data' => $response['data']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao conectar instância: ' . $response['message']
        ]);
    }
}

/**
 * Obter QR Code da instância
 */
function getQRCode() {
    global $pdo, $user_id;
    
    try {
        $stmt = $pdo->prepare("SELECT evolution_instance FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (empty($user['evolution_instance'])) {
            echo json_encode(['success' => false, 'message' => 'Instância não configurada']);
            return;
        }
        
        $instanceName = $user['evolution_instance'];
        
        // Primeiro, verificar se a instância existe
        $statusResponse = callEvolutionAPI('/instance/connectionState/' . $instanceName, 'GET');
        
        if (!$statusResponse['success'] || $statusResponse['http_code'] == 404) {
            // Instância não existe, criar uma nova
            error_log("DEBUG QR - Instância não existe, criando nova: $instanceName");
            
            $instanceData = [
                'instanceName' => $instanceName,
                'token' => generateInstanceToken(),
                'qrcode' => true,
                'integration' => 'WHATSAPP-BAILEYS'
            ];
            
            $createResponse = callEvolutionAPI('/instance/create', 'POST', $instanceData);
            
            if (!$createResponse['success']) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao criar instância: ' . $createResponse['message']
                ]);
                return;
            }
            
            // Aguardar criação
            sleep(3);
        }
        
        // Tentar conectar para gerar QR Code (endpoint que funciona!)
        error_log("DEBUG QR - Tentando obter QR Code para: $instanceName");
        $connectResponse = callEvolutionAPI('/instance/connect/' . $instanceName, 'GET');
        
        // Log completo da resposta
        error_log("DEBUG QR - Connect response: " . json_encode($connectResponse));
        
        if ($connectResponse['success'] && isset($connectResponse['data'])) {
            error_log("DEBUG QR - Dados recebidos: " . json_encode($connectResponse['data']));
            
            $qrCode = extractQRCode($connectResponse['data']);
            error_log("DEBUG QR - QR Code extraído: " . ($qrCode ? 'SIM (' . strlen($qrCode) . ' chars)' : 'NÃO'));
            
            if ($qrCode) {
                echo json_encode([
                    'success' => true,
                    'base64' => $qrCode,
                    'source' => 'connect_endpoint'
                ]);
                return;
            }
        } else {
            error_log("DEBUG QR - Falha na chamada API: " . ($connectResponse['message'] ?? 'Erro desconhecido'));
        }
        
        // Se nada funcionou, retornar erro com sugestão
        echo json_encode([
            'success' => false,
            'message' => 'Não foi possível gerar QR Code. A instância pode estar conectada ou em estado inválido.',
            'suggestion' => 'Tente desconectar o WhatsApp Web e gerar novamente, ou remova a instância e crie uma nova.'
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erro interno: ' . $e->getMessage()
        ]);
    }
}

/**
 * Extrair QR Code de diferentes formatos de resposta
 */
function extractQRCode($data) {
    error_log("DEBUG EXTRACT - Dados recebidos: " . json_encode($data));
    
    // O teste mostrou que o campo 'code' contém os dados do QR Code
    if (!empty($data['code']) && is_string($data['code'])) {
        $qrData = $data['code'];
        error_log("DEBUG EXTRACT - Campo 'code' encontrado: " . substr($qrData, 0, 100) . "...");
        
        // Tentar múltiplas APIs de QR Code (igual ao teste que funciona)
        $qrApis = [
            'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qrData),
            'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . urlencode($qrData),
            'https://quickchart.io/qr?text=' . urlencode($qrData) . '&size=300'
        ];
        
        foreach ($qrApis as $api) {
            error_log("DEBUG EXTRACT - Tentando API: $api");
            $qrImageData = @file_get_contents($api);
            if ($qrImageData !== false && strlen($qrImageData) > 1000) {
                error_log("DEBUG EXTRACT - QR Code gerado com sucesso via: $api");
                return base64_encode($qrImageData);
            }
        }
        
        error_log("DEBUG EXTRACT - Todas as APIs falharam");
    } else {
        error_log("DEBUG EXTRACT - Campo 'code' não encontrado ou vazio");
    }
    
    // Tentar outros formatos como fallback
    error_log("DEBUG EXTRACT - Tentando formatos alternativos");
    $possibleLocations = [
        'qrcode.base64' => $data['qrcode']['base64'] ?? null,
        'base64' => $data['base64'] ?? null,
        'qr' => $data['qr'] ?? null,
        'qrcode' => $data['qrcode'] ?? null,
        'qrCode' => $data['qrCode'] ?? null,
        'qr_code' => $data['qr_code'] ?? null
    ];
    
    foreach ($possibleLocations as $location => $qrCode) {
        if (!empty($qrCode) && is_string($qrCode) && strlen($qrCode) > 100) {
            if (preg_match('/^[A-Za-z0-9+\/]+=*$/', $qrCode)) {
                error_log("DEBUG EXTRACT - QR Code encontrado em: $location");
                return $qrCode;
            }
        }
    }
    
    error_log("DEBUG EXTRACT - Nenhum QR Code encontrado em nenhum formato");
    return null;
}

/**
 * Deletar instância do usuário
 */
function deleteInstance() {
    global $pdo, $user_id;
    
    // Buscar dados da instância do usuário
    $stmt = $pdo->prepare("SELECT evolution_instance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user || empty($user['evolution_instance'])) {
        echo json_encode(['success' => false, 'message' => 'Nenhuma instância encontrada']);
        return;
    }
    
    $instanceName = $user['evolution_instance'];
    
    // Deletar instância na Evolution API
    $response = callEvolutionAPI('/instance/delete/' . $instanceName, 'DELETE');
    
    // Independente do resultado da API, limpar do banco (caso já foi deletada manualmente)
    $stmt = $pdo->prepare("UPDATE users SET evolution_instance = NULL, evolution_token = NULL WHERE id = ?");
    $success = $stmt->execute([$user_id]);
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Instância removida com sucesso!',
            'api_result' => $response['success'] ? 'Deletada da Evolution API' : 'Apenas removida do banco (pode já ter sido deletada)'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao remover instância do banco']);
    }
}

/**
 * Verificar se a instância ainda existe na Evolution API
 */
function checkInstanceExists() {
    global $pdo, $user_id;
    
    // Buscar dados da instância do usuário
    $stmt = $pdo->prepare("SELECT evolution_instance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user || empty($user['evolution_instance'])) {
        echo json_encode([
            'success' => true,
            'exists' => false,
            'message' => 'Nenhuma instância configurada'
        ]);
        return;
    }
    
    $instanceName = $user['evolution_instance'];
    
    // Verificar se existe na Evolution API
    $response = callEvolutionAPI('/instance/connectionState/' . $instanceName, 'GET');
    
    if ($response['success']) {
        echo json_encode([
            'success' => true,
            'exists' => true,
            'instance_name' => $instanceName,
            'message' => 'Instância existe na Evolution API'
        ]);
    } else {
        // Se retornou erro 404 ou similar, a instância não existe mais
        if (isset($response['http_code']) && $response['http_code'] == 404) {
            // Limpar do banco automaticamente
            $stmt = $pdo->prepare("UPDATE users SET evolution_instance = NULL, evolution_token = NULL WHERE id = ?");
            $stmt->execute([$user_id]);
            
            echo json_encode([
                'success' => true,
                'exists' => false,
                'cleaned' => true,
                'message' => 'Instância não existe mais na Evolution API. Configuração limpa automaticamente.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'exists' => 'unknown',
                'message' => 'Erro ao verificar instância: ' . $response['message']
            ]);
        }
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
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
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

// Capturar qualquer erro não tratado
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Erro fatal: ' . $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ]);
    }
});
?>
