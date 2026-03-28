<?php
/**
 * API - Gerenciador de Instâncias Evolution Go
 * Gerencia criação, QR Code e status de instâncias Evolution Go
 */

session_start();

// ✅ GARANTIR TIMEZONE CORRETO
date_default_timezone_set('America/Sao_Paulo');

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/channels/providers/EvolutionGoProvider.php';

header('Content-Type: application/json');

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
    
    $instance = $user['evolution_go_instance'];
    $token = $user['evolution_go_token'];
    
    // Criar provider
    $provider = new EvolutionGoProvider($instance, $token);
    
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
