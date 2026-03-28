<?php
/**
 * API - Gerenciador de Instâncias Evolution Go
 * Gerencia criação, QR Code e status de instâncias Evolution Go
 */

// Capturar qualquer saída indesejada
ob_start();

session_start();

// ✅ GARANTIR TIMEZONE CORRETO
date_default_timezone_set('America/Sao_Paulo');

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/channels/providers/EvolutionGoProvider.php';

// Limpar buffer e definir header
ob_end_clean();
header('Content-Type: application/json');

// Tratamento de erros global
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("[EVOGO_MANAGER] PHP Error: $errstr in $errfile:$errline");
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

set_exception_handler(function($e) {
    error_log("[EVOGO_MANAGER] Uncaught Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno: ' . $e->getMessage()
    ]);
    exit;
});

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

try {
    // Buscar configuração do usuário
    $stmt = $pdo->prepare("SELECT evolution_go_instance, evolution_go_token FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || empty($user['evolution_go_instance'])) {
        throw new Exception('Instância Evolution Go não configurada');
    }
    
    // Preparar dados da instância no formato esperado pelo provider
    $instanceData = [
        'instance_id' => $user['evolution_go_instance'],
        'api_key' => $user['evolution_go_token'],
        'token' => $user['evolution_go_token'],
        'api_url' => EVOLUTION_GO_API_URL
    ];
    
    // Criar provider
    $provider = new EvolutionGoProvider($instanceData, $pdo);
    
    switch ($action) {
        case 'qrcode':
            handleQRCode($provider);
            break;
            
        case 'status':
            handleStatus($provider);
            break;
            
        case 'connect':
            handleConnect($provider);
            break;
            
        case 'disconnect':
            handleDisconnect($provider);
            break;
            
        default:
            throw new Exception('Ação inválida');
    }
    
} catch (Exception $e) {
    error_log("[EVOGO_MANAGER] Erro: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

exit;

/**
 * Gerar QR Code
 */
function handleQRCode(EvolutionGoProvider $provider): void
{
    error_log("[EVOGO_MANAGER] Gerando QR Code...");
    
    $result = $provider->generateQRCode();
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'base64' => $result['qrcode']['base64'] ?? $result['base64'] ?? null,
            'message' => 'QR Code gerado com sucesso'
        ]);
    } else {
        throw new Exception($result['error'] ?? 'Erro ao gerar QR Code');
    }
}

/**
 * Verificar status da instância
 */
function handleStatus(EvolutionGoProvider $provider): void
{
    error_log("[EVOGO_MANAGER] Verificando status...");
    
    $result = $provider->getStatus();
    
    if ($result['success']) {
        $status = $result['status'] ?? 'unknown';
        $connected = ($status === 'open' || $status === 'connected');
        
        echo json_encode([
            'success' => true,
            'status' => $status,
            'connected' => $connected,
            'data' => $result
        ]);
    } else {
        throw new Exception($result['error'] ?? 'Erro ao verificar status');
    }
}

/**
 * Conectar instância
 */
function handleConnect(EvolutionGoProvider $provider): void
{
    error_log("[EVOGO_MANAGER] Conectando instância...");
    
    $result = $provider->connect();
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Instância conectada com sucesso'
        ]);
    } else {
        throw new Exception($result['error'] ?? 'Erro ao conectar instância');
    }
}

/**
 * Desconectar instância
 */
function handleDisconnect(EvolutionGoProvider $provider): void
{
    global $pdo, $userId;
    
    error_log("[EVOGO_MANAGER] Desconectando instância...");
    
    $result = $provider->disconnect();
    
    if ($result['success']) {
        // Limpar configuração do banco
        $stmt = $pdo->prepare("UPDATE users SET evolution_go_instance = NULL, evolution_go_token = NULL WHERE id = ?");
        $stmt->execute([$userId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Instância desconectada com sucesso'
        ]);
    } else {
        throw new Exception($result['error'] ?? 'Erro ao desconectar instância');
    }
}
