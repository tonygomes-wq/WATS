<?php
/**
 * WEBHOOK UNIFICADO - Roteia automaticamente para Evolution ou Meta API
 * Detecta a origem do webhook e processa adequadamente
 */

declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// Responder a OPTIONS/HEAD
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS' || $_SERVER['REQUEST_METHOD'] === 'HEAD') {
    http_response_code(200);
    exit;
}

require_once '../config/database.php';
require_once '../includes/api_provider_detector.php';
require_once '../includes/MetaWebhookValidator.php';
require_once '../includes/TokenEncryption.php';

header('Content-Type: application/json');

$rawPayload = file_get_contents('php://input');
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Log de requisição
error_log("[UNIFIED_WEBHOOK] Método: $requestMethod");
error_log("[UNIFIED_WEBHOOK] Payload: " . substr($rawPayload, 0, 500));

// ==================================================================================
// VERIFICAÇÃO DO WEBHOOK (GET) - Meta API
// ==================================================================================
if ($requestMethod === 'GET') {
    // Meta API envia verificação via GET com parâmetros específicos
    $mode = $_GET['hub.mode'] ?? '';
    $token = $_GET['hub.verify_token'] ?? '';
    $challenge = $_GET['hub.challenge'] ?? '';
    
    if ($mode === 'subscribe' && !empty($challenge)) {
        error_log("[UNIFIED_WEBHOOK] Verificação Meta API recebida");
        
        // Verificar token contra todos os usuários configurados com Meta API
        $stmt = $pdo->prepare("
            SELECT id, meta_webhook_verify_token 
            FROM users 
            WHERE whatsapp_provider = 'meta' 
            AND meta_webhook_verify_token IS NOT NULL
        ");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $tokenValid = false;
        foreach ($users as $user) {
            if ($user['meta_webhook_verify_token'] === $token) {
                $tokenValid = true;
                error_log("[UNIFIED_WEBHOOK] Token válido para usuário: " . $user['id']);
                break;
            }
        }
        
        if ($tokenValid) {
            // Retornar challenge para Meta API
            echo $challenge;
            exit;
        } else {
            error_log("[UNIFIED_WEBHOOK] Token inválido: $token");
            http_response_code(403);
            echo json_encode(['error' => 'Invalid verify token']);
            exit;
        }
    }
    
    // Se não for verificação Meta, pode ser Evolution API
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Webhook ativo']);
    exit;
}

// ==================================================================================
// PROCESSAR WEBHOOK (POST)
// ==================================================================================
if ($requestMethod !== 'POST') {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Método não suportado']);
    exit;
}

if (empty($rawPayload)) {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Payload vazio']);
    exit;
}

$payload = json_decode($rawPayload, true);
if (!$payload) {
    error_log("[UNIFIED_WEBHOOK] Erro ao decodificar JSON");
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'JSON inválido']);
    exit;
}

// ==================================================================================
// DETECTAR ORIGEM DO WEBHOOK
// ==================================================================================

$webhookSource = detectWebhookSource($payload);

error_log("[UNIFIED_WEBHOOK] Origem detectada: " . $webhookSource);

if ($webhookSource === 'meta') {
    processMetaWebhook($payload, $pdo);
} elseif ($webhookSource === 'evolution') {
    processEvolutionWebhook($payload, $pdo);
} else {
    error_log("[UNIFIED_WEBHOOK] Origem desconhecida");
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Origem desconhecida']);
    exit;
}

// ==================================================================================
// FUNÇÕES DE DETECÇÃO E PROCESSAMENTO
// ==================================================================================

/**
 * Detecta se o webhook é da Meta API ou Evolution API
 */
function detectWebhookSource($payload) {
    // Meta API tem estrutura específica
    if (isset($payload['object']) && $payload['object'] === 'whatsapp_business_account') {
        return 'meta';
    }
    
    if (isset($payload['entry']) && is_array($payload['entry'])) {
        return 'meta';
    }
    
    // Evolution API tem campo 'event' e 'instance'
    if (isset($payload['event']) && isset($payload['instance'])) {
        return 'evolution';
    }
    
    // Evolution API também pode ter 'data' com 'key'
    if (isset($payload['data']['key']['remoteJid'])) {
        return 'evolution';
    }
    
    return 'unknown';
}

/**
 * Processa webhook da Meta API
 */
function processMetaWebhook($payload, $pdo) {
    global $rawPayload;
    
    error_log("[META_WEBHOOK] Processando webhook da Meta API");
    
    // Validar assinatura HMAC se disponível
    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    
    if (!empty($signature)) {
        // Obter App Secret do usuário baseado no payload
        $encryption = new TokenEncryption();
        $appSecret = MetaWebhookValidator::getAppSecretFromPayload($payload, $pdo);
        
        if ($appSecret) {
            // Descriptografar se necessário
            $decryptedSecret = $encryption->decrypt($appSecret);
            
            if ($decryptedSecret && !MetaWebhookValidator::validateSignature($rawPayload, $signature, $decryptedSecret)) {
                error_log("[META_WEBHOOK] Assinatura HMAC inválida - possível tentativa de ataque");
                MetaWebhookValidator::logInvalidWebhook($pdo, 'invalid_hmac_signature', [
                    'signature' => substr($signature, 0, 20) . '...'
                ]);
                http_response_code(403);
                echo json_encode(['error' => 'Invalid signature']);
                exit;
            }
            
            error_log("[META_WEBHOOK] Assinatura HMAC validada com sucesso");
        } else {
            error_log("[META_WEBHOOK] App Secret não encontrado - pulando validação HMAC");
        }
    } else {
        error_log("[META_WEBHOOK] Assinatura HMAC não fornecida - webhook pode não ser autêntico");
    }
    
    // Incluir processador específico da Meta
    require_once BASE_PATH . '/api/meta_webhook_processor.php';
    require_once BASE_PATH . '/includes/MetaMediaHandler.php';
    
    $processor = new MetaWebhookProcessor($pdo);
    $result = $processor->process($payload);
    
    http_response_code(200);
    echo json_encode(['success' => true, 'processed_by' => 'meta']);
    exit;
}

/**
 * Processa webhook da Evolution API
 */
function processEvolutionWebhook($payload, $pdo) {
    error_log("[EVOLUTION_WEBHOOK] Processando webhook da Evolution API");
    
    // Redirecionar para o webhook existente da Evolution
    require_once BASE_PATH . '/api/chat_webhook.php';
    exit;
}
