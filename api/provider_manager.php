<?php
/**
 * API - Gerenciador de Providers
 * 
 * Gerencia conexão/desconexão de providers WhatsApp
 * (Z-API, Evolution Go, Meta API, etc)
 * 
 * @version 1.0
 * @since 2026-03-27
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Verificar autenticação
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'disconnect_zapi':
            disconnectZAPI($userId, $pdo);
            break;
            
        case 'disconnect_evolution_go':
            disconnectEvolutionGo($userId, $pdo);
            break;
            
        case 'disconnect_meta':
            disconnectMeta($userId, $pdo);
            break;
            
        default:
            throw new Exception('Ação não reconhecida');
    }
} catch (Exception $e) {
    error_log("PROVIDER_MANAGER Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

exit;

/**
 * Desconectar Z-API
 */
function disconnectZAPI($userId, $pdo) {
    error_log("[PROVIDER_MANAGER] Desconectando Z-API para usuário $userId");
    
    try {
        // Limpar configurações Z-API
        $stmt = $pdo->prepare("
            UPDATE users 
            SET 
                zapi_instance_id = NULL,
                zapi_token = NULL,
                zapi_client_token = NULL,
                whatsapp_provider = 'evolution'
            WHERE id = ?
        ");
        
        if ($stmt->execute([$userId])) {
            error_log("[PROVIDER_MANAGER] Z-API desconectada com sucesso para usuário $userId");
            echo json_encode([
                'success' => true,
                'message' => 'Z-API desconectada com sucesso!'
            ]);
        } else {
            throw new Exception('Erro ao atualizar banco de dados');
        }
        
    } catch (PDOException $e) {
        error_log("[PROVIDER_MANAGER] Erro ao desconectar Z-API: " . $e->getMessage());
        throw new Exception('Erro ao desconectar Z-API: ' . $e->getMessage());
    }
}

/**
 * Desconectar Evolution Go
 */
function disconnectEvolutionGo($userId, $pdo) {
    error_log("[PROVIDER_MANAGER] Desconectando Evolution Go para usuário $userId");
    
    try {
        // Limpar configurações Evolution Go
        $stmt = $pdo->prepare("
            UPDATE users 
            SET 
                evolution_go_instance = NULL,
                evolution_go_token = NULL,
                whatsapp_provider = 'evolution'
            WHERE id = ?
        ");
        
        if ($stmt->execute([$userId])) {
            error_log("[PROVIDER_MANAGER] Evolution Go desconectada com sucesso para usuário $userId");
            echo json_encode([
                'success' => true,
                'message' => 'Evolution Go desconectada com sucesso!'
            ]);
        } else {
            throw new Exception('Erro ao atualizar banco de dados');
        }
        
    } catch (PDOException $e) {
        error_log("[PROVIDER_MANAGER] Erro ao desconectar Evolution Go: " . $e->getMessage());
        throw new Exception('Erro ao desconectar Evolution Go: ' . $e->getMessage());
    }
}

/**
 * Desconectar Meta API
 */
function disconnectMeta($userId, $pdo) {
    error_log("[PROVIDER_MANAGER] Desconectando Meta API para usuário $userId");
    
    try {
        // Limpar configurações Meta API
        $stmt = $pdo->prepare("
            UPDATE users 
            SET 
                meta_phone_number_id = NULL,
                meta_business_account_id = NULL,
                meta_app_id = NULL,
                meta_app_secret = NULL,
                meta_permanent_token = NULL,
                meta_webhook_verify_token = NULL,
                meta_api_version = 'v19.0',
                whatsapp_provider = 'evolution'
            WHERE id = ?
        ");
        
        if ($stmt->execute([$userId])) {
            error_log("[PROVIDER_MANAGER] Meta API desconectada com sucesso para usuário $userId");
            echo json_encode([
                'success' => true,
                'message' => 'Meta API desconectada com sucesso!'
            ]);
        } else {
            throw new Exception('Erro ao atualizar banco de dados');
        }
        
    } catch (PDOException $e) {
        error_log("[PROVIDER_MANAGER] Erro ao desconectar Meta API: " . $e->getMessage());
        throw new Exception('Erro ao desconectar Meta API: ' . $e->getMessage());
    }
}
