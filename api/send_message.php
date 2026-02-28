<?php
// Iniciar output buffering para capturar qualquer output indesejado
ob_start();

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/whatsapp_meta_service.php';
require_once '../includes/plan_check.php';

// Limpar qualquer output que possa ter sido gerado pelos includes
ob_end_clean();

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

// Receber dados
$rawBody = file_get_contents('php://input');
$input = json_decode($rawBody, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
    // Pode ser multipart/form-data (futuro) - por enquanto apenas JSON
    $input = $_POST;
}

$contactId = intval($input['contact_id'] ?? 0);
$phone = formatPhone($input['phone'] ?? '');
$message = $input['message'] ?? '';
$templateName = trim($input['template_name'] ?? '');
$templateLanguage = trim($input['template_language'] ?? 'pt_BR');
$templateComponents = $input['template_components'] ?? [];
$media = $input['media'] ?? null; // Suporte para mídia (imagem, vídeo, etc)
$userId = $_SESSION['user_id'];
$logPrefix = sprintf('[SEND_MESSAGE][user:%s]', $userId);

if (empty($phone) || empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
    exit;
}

// Verificar limite do plano antes de enviar
$check = checkPlanLimit($userId);
if (!$check['allowed']) {
    echo json_encode(['success' => false, 'error' => $check['message']]);
    exit;
}

// Buscar configurações da instância do usuário
$stmt = $pdo->prepare("SELECT evolution_instance, evolution_token, whatsapp_provider, meta_phone_number_id, meta_business_account_id, meta_app_id, meta_app_secret, meta_permanent_token, meta_webhook_verify_token, meta_api_version FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['success' => false, 'error' => 'Usuário não encontrado']);
    exit;
}

$provider = $user['whatsapp_provider'] ?? 'evolution';
$metaConfig = [
    'meta_phone_number_id' => $user['meta_phone_number_id'] ?? null,
    'meta_business_account_id' => $user['meta_business_account_id'] ?? null,
    'meta_app_id' => $user['meta_app_id'] ?? null,
    'meta_app_secret' => $user['meta_app_secret'] ?? null,
    'meta_permanent_token' => $user['meta_permanent_token'] ?? null,
    'meta_webhook_verify_token' => $user['meta_webhook_verify_token'] ?? null,
    'meta_api_version' => $user['meta_api_version'] ?? 'v19.0',
];

$result = null;
$transportUsed = null;
$shouldFallbackEvolution = ($provider === 'meta');

try {
    if ($provider === 'meta') {
        if (!empty($templateName)) {
            $result = sendMetaTemplateMessage($phone, $templateName, $templateLanguage, $templateComponents, $metaConfig, $userId);
            $transportUsed = 'meta_template';
        } else {
            $result = sendMetaTextMessage($phone, $message, $metaConfig, $userId);
            $transportUsed = 'meta_text';
        }

        if (!$result['success']) {
            error_log($logPrefix . '[meta] erro: ' . ($result['error'] ?? 'desconhecido'));
            if ($shouldFallbackEvolution && !empty($user['evolution_instance']) && !empty($user['evolution_token'])) {
                error_log($logPrefix . ' fallback para Evolution API');
                $fallbackSuccess = sendWhatsAppMessage($phone, $message, $user['evolution_instance'], $user['evolution_token']);
                $transportUsed = 'evolution_fallback';
                $result = $fallbackSuccess
                    ? ['success' => true]
                    : ['success' => false, 'error' => 'Falha no fallback para Evolution API'];
            }
        }
    } else {
        if (empty($user['evolution_instance']) || empty($user['evolution_token'])) {
            echo json_encode([
                'success' => false,
                'error' => 'Você precisa configurar sua instância Evolution API primeiro. Vá em "Minha Instância".'
            ]);
            exit;
        }

        $transportUsed = 'evolution';
        
        // Verificar se tem mídia para enviar
        if (!empty($media) && !empty($media['base64'])) {
            $result = [
                'success' => sendWhatsAppMedia($phone, $message, $media, $user['evolution_instance'], $user['evolution_token'])
            ];
        } else {
            $result = [
                'success' => sendWhatsAppMessage($phone, $message, $user['evolution_instance'], $user['evolution_token'])
            ];
        }
    }

    // Registrar no histórico
    $status = $result['success'] ? 'sent' : 'failed';
    $stmt = $pdo->prepare("
        INSERT INTO dispatch_history (user_id, contact_id, message, status, sent_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$userId, $contactId, $message, $status]);

    if ($result['success']) {
        // Incrementar contador de mensagens após envio bem-sucedido
        incrementMessageCount($userId);
        echo json_encode([
            'success' => true,
            'transport' => $transportUsed,
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'transport' => $transportUsed,
            'error' => $result['error'] ?? 'Erro ao enviar mensagem'
        ]);
    }
} catch (Exception $e) {
    error_log($logPrefix . ' Exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Função para enviar mensagem via Evolution API
 * Usa a instância e token específicos do usuário
 */
function sendWhatsAppMessage($phone, $message, $instance, $token) {
    // Formatar número para padrão internacional
    // Remover caracteres não numéricos do telefone
    $phoneClean = preg_replace('/[^0-9]/', '', $phone);
    
    // Se já começa com 55, não adicionar novamente
    if (substr($phoneClean, 0, 2) !== '55') {
        $phoneClean = '55' . $phoneClean;
    }
    
    $phoneFormatted = $phoneClean . '@s.whatsapp.net';
    
    $data = [
        'number' => $phoneFormatted,
        'text' => $message
    ];
    
    $url = EVOLUTION_API_URL . '/message/sendText/' . $instance;
    
    error_log("[SEND_MESSAGE] URL: " . $url);
    error_log("[SEND_MESSAGE] Phone: " . $phoneFormatted);
    error_log("[SEND_MESSAGE] Instance: " . $instance);
    error_log("[SEND_MESSAGE] Token (primeiros 20): " . substr($token, 0, 20));
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . $token
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    error_log("[SEND_MESSAGE] HTTP Code: " . $httpCode);
    error_log("[SEND_MESSAGE] Response: " . substr($response, 0, 500));
    if ($curlError) {
        error_log("[SEND_MESSAGE] cURL Error: " . $curlError);
    }
    
    // Considerar sucesso se o código HTTP for 200 ou 201
    return $httpCode >= 200 && $httpCode < 300;
}

/**
 * Função para enviar mídia (imagem, vídeo, áudio, documento) via Evolution API
 * Formato correto baseado no chat_service.php
 */
function sendWhatsAppMedia($phone, $caption, $media, $instance, $token) {
    // Formatar número para padrão internacional
    $phoneClean = preg_replace('/[^0-9]/', '', $phone);
    
    if (substr($phoneClean, 0, 2) !== '55') {
        $phoneClean = '55' . $phoneClean;
    }
    
    $phoneFormatted = $phoneClean . '@s.whatsapp.net';
    
    // Determinar tipo de mídia baseado no mimetype
    $mimetype = $media['mimetype'] ?? 'image/png';
    $filename = $media['filename'] ?? 'arquivo';
    $base64 = $media['base64'] ?? '';
    
    // Determinar tipo de mídia
    $mediaType = 'image';
    if (strpos($mimetype, 'video') !== false) {
        $mediaType = 'video';
    } elseif (strpos($mimetype, 'audio') !== false) {
        $mediaType = 'audio';
    } elseif (strpos($mimetype, 'application') !== false || strpos($mimetype, 'text') !== false) {
        $mediaType = 'document';
    }
    
    // Payload no formato correto da Evolution API
    $data = [
        'number' => $phoneFormatted,
        'mediatype' => $mediaType,
        'mimetype' => $mimetype,
        'media' => $base64, // Base64 puro, sem data URL
        'fileName' => $filename
    ];
    
    // Adicionar caption se houver
    if (!empty($caption)) {
        $data['caption'] = $caption;
    }
    
    $endpoint = '/message/sendMedia/' . $instance;
    $url = EVOLUTION_API_URL . $endpoint;
    
    error_log("[SEND_MEDIA] URL: " . $url);
    error_log("[SEND_MEDIA] Phone: " . $phoneFormatted);
    error_log("[SEND_MEDIA] MediaType: " . $mediaType);
    error_log("[SEND_MEDIA] Mimetype: " . $mimetype);
    error_log("[SEND_MEDIA] Filename: " . $filename);
    error_log("[SEND_MEDIA] Base64 length: " . strlen($base64));
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . $token
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    error_log("[SEND_MEDIA] HTTP Code: " . $httpCode);
    error_log("[SEND_MEDIA] Response: " . substr($response, 0, 500));
    if ($curlError) {
        error_log("[SEND_MEDIA] cURL Error: " . $curlError);
    }
    
    return $httpCode >= 200 && $httpCode < 300;
}