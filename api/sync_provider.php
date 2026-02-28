<?php
/**
 * API para Sincronização Automática ao Trocar de Provider
 * Gerencia a troca entre Evolution API e Meta API
 */

if (!isset($_SESSION)) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/api_provider_detector.php';

header('Content-Type: application/json');

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Não autorizado'
    ]);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_current':
        getCurrentProvider($userId, $pdo);
        break;
    
    case 'switch':
        switchProvider($userId, $pdo);
        break;
    
    case 'validate':
        validateProviderConfig($userId, $pdo);
        break;
    
    default:
        echo json_encode([
            'success' => false,
            'error' => 'Ação inválida'
        ]);
}

/**
 * Retorna o provider atual do usuário
 */
function getCurrentProvider($userId, $pdo) {
    $detector = new ApiProviderDetector($pdo);
    $detection = $detector->detectProvider($userId);
    $info = $detector->getProviderInfo($userId);
    
    echo json_encode([
        'success' => true,
        'current_provider' => $detection['provider'],
        'config' => $detection['config'],
        'info' => $info
    ]);
}

/**
 * Troca o provider do usuário
 */
function switchProvider($userId, $pdo) {
    $newProvider = $_POST['provider'] ?? '';
    
    if (!in_array($newProvider, ['evolution', 'meta'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Provider inválido. Use "evolution" ou "meta"'
        ]);
        return;
    }
    
    // Verificar se o novo provider está configurado
    $stmt = $pdo->prepare("
        SELECT 
            evolution_instance,
            evolution_token,
            meta_phone_number_id,
            meta_permanent_token
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode([
            'success' => false,
            'error' => 'Usuário não encontrado'
        ]);
        return;
    }
    
    // Validar se o provider escolhido está configurado
    if ($newProvider === 'evolution') {
        if (empty($user['evolution_instance']) || empty($user['evolution_token'])) {
            echo json_encode([
                'success' => false,
                'error' => 'Evolution API não está configurada. Configure em Minha Instância primeiro.'
            ]);
            return;
        }
    } else {
        if (empty($user['meta_phone_number_id']) || empty($user['meta_permanent_token'])) {
            echo json_encode([
                'success' => false,
                'error' => 'Meta API não está configurada. Configure em Minha Instância primeiro.'
            ]);
            return;
        }
    }
    
    // Atualizar provider
    $detector = new ApiProviderDetector($pdo);
    $result = $detector->syncProviderChange($userId, $newProvider);
    
    if ($result['success']) {
        // Limpar cache de sessão se houver
        if (isset($_SESSION['api_provider'])) {
            unset($_SESSION['api_provider']);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Provider alterado com sucesso',
            'new_provider' => $newProvider,
            'reload_required' => true
        ]);
    } else {
        echo json_encode($result);
    }
}

/**
 * Valida configuração do provider
 */
function validateProviderConfig($userId, $pdo) {
    $provider = $_GET['provider'] ?? '';
    
    if (!in_array($provider, ['evolution', 'meta'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Provider inválido'
        ]);
        return;
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            evolution_instance,
            evolution_token,
            evolution_api_url,
            meta_phone_number_id,
            meta_business_account_id,
            meta_permanent_token,
            meta_webhook_verify_token
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode([
            'success' => false,
            'error' => 'Usuário não encontrado'
        ]);
        return;
    }
    
    $isConfigured = false;
    $missingFields = [];
    
    if ($provider === 'evolution') {
        $isConfigured = !empty($user['evolution_instance']) && !empty($user['evolution_token']);
        if (!$isConfigured) {
            if (empty($user['evolution_instance'])) {
                $missingFields[] = 'Nome da Instância';
            }
            if (empty($user['evolution_token'])) {
                $missingFields[] = 'Token da Instância';
            }
        }
    } else {
        $isConfigured = !empty($user['meta_phone_number_id']) 
                     && !empty($user['meta_permanent_token']);
        if (!$isConfigured) {
            if (empty($user['meta_phone_number_id'])) {
                $missingFields[] = 'Phone Number ID';
            }
            if (empty($user['meta_permanent_token'])) {
                $missingFields[] = 'Permanent Token';
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'provider' => $provider,
        'is_configured' => $isConfigured,
        'missing_fields' => $missingFields
    ]);
}
