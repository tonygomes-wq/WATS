<?php
/**
 * API de Envio Multi-Canal
 * Envia mensagens através do canal correto (WhatsApp, Telegram, Facebook)
 * Suporta Evolution API e Meta API para WhatsApp
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/channels/TelegramChannel.php';
require_once '../includes/channels/FacebookChannel.php';
require_once '../includes/whatsapp_meta_service.php';
require_once '../includes/Meta24HourWindow.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];

// Capturar dados
$contactId = $_POST['contact_id'] ?? null;
$messageText = $_POST['message'] ?? '';
$conversationId = $_POST['conversation_id'] ?? null;

if (empty($messageText)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Mensagem é obrigatória']);
    exit;
}

try {
    // Buscar informações do contato e canal
    $stmt = $pdo->prepare("
        SELECT 
            c.id as contact_id,
            c.phone,
            c.source,
            c.source_id,
            m.channel_id,
            m.channel_type,
            ch.status as channel_status
        FROM contacts c
        LEFT JOIN (
            SELECT contact_id, channel_id, channel_type, MAX(created_at) as last_msg
            FROM messages
            WHERE channel_id IS NOT NULL
            GROUP BY contact_id
        ) m ON c.id = m.contact_id
        LEFT JOIN channels ch ON m.channel_id = ch.id
        WHERE c.id = ? AND c.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$contactId, $userId]);
    $contact = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$contact) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Contato não encontrado']);
        exit;
    }
    
    // Determinar canal (priorizar channel_type, depois source, padrão whatsapp)
    $channelType = $contact['channel_type'] ?? $contact['source'] ?? 'whatsapp';
    $channelId = $contact['channel_id'];
    $sourceId = $contact['source_id'] ?? $contact['phone'];
    
    $result = null;
    $messageId = null;
    
    // Enviar pela API correta
    switch ($channelType) {
        case 'telegram':
            if (!$channelId) {
                throw new Exception('Canal Telegram não configurado');
            }
            
            $channel = new TelegramChannel($pdo, $channelId);
            $result = $channel->sendMessage([
                'chat_id' => $sourceId,
                'text' => $messageText
            ]);
            
            if ($result['success']) {
                $messageId = $result['message_id'];
                $externalId = $result['external_id'];
            }
            break;
            
        case 'facebook':
            if (!$channelId) {
                throw new Exception('Canal Facebook não configurado');
            }
            
            $channel = new FacebookChannel($pdo, $channelId);
            $result = $channel->sendMessage([
                'recipient_id' => $sourceId,
                'text' => $messageText
            ]);
            
            if ($result['success']) {
                $messageId = $result['message_id'];
                $externalId = $result['external_id'];
            }
            break;
            
        case 'whatsapp':
        default:
            // Enviar via WhatsApp (Evolution ou Meta API)
            $result = sendWhatsAppMessage($contact['phone'], $messageText, $userId);
            
            if ($result['success']) {
                $messageId = $result['message_id'] ?? null;
                $externalId = $messageId;
            }
            break;
    }
    
    // Verificar resultado
    if (!$result || !$result['success']) {
        throw new Exception($result['error'] ?? 'Erro ao enviar mensagem');
    }
    
    // Salvar mensagem no banco de dados
    $stmt = $pdo->prepare("
        INSERT INTO messages 
        (user_id, contact_id, channel_id, channel_type, external_id, message_text, from_me, message_type, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 1, 'text', 'sent', NOW())
    ");
    
    $stmt->execute([
        $userId,
        $contactId,
        $channelId,
        $channelType,
        $externalId ?? null,
        $messageText
    ]);
    
    $dbMessageId = $pdo->lastInsertId();
    
    // Atualizar conversa
    if ($conversationId) {
        $stmt = $pdo->prepare("
            UPDATE chat_conversations 
            SET last_message_text = ?, last_message_time = NOW(), updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$messageText, $conversationId]);
    }
    
    echo json_encode([
        'success' => true,
        'message_id' => $dbMessageId,
        'external_id' => $externalId ?? null,
        'channel_type' => $channelType
    ]);
    
} catch (Exception $e) {
    error_log("[CHAT_SEND_MULTICHANNEL] Erro: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Enviar mensagem via WhatsApp (híbrido: Evolution ou Meta API)
 * Detecta automaticamente qual API usar baseado na configuração do usuário
 */
function sendWhatsAppMessage(string $phone, string $message, int $userId): array
{
    global $pdo;
    
    // Buscar configurações do usuário
    $stmt = $pdo->prepare("
        SELECT 
            evolution_instance, evolution_token, whatsapp_provider,
            meta_phone_number_id, meta_permanent_token, meta_api_version
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return ['success' => false, 'error' => 'Usuário não encontrado'];
    }
    
    $provider = $user['whatsapp_provider'] ?? 'evolution';
    $hasEvolution = !empty($user['evolution_instance']) && !empty($user['evolution_token']);
    $hasMeta = !empty($user['meta_phone_number_id']) && !empty($user['meta_permanent_token']);
    
    // Tentar Meta API se configurado
    if ($provider === 'meta' && $hasMeta) {
        $userConfig = [
            'meta_phone_number_id' => $user['meta_phone_number_id'],
            'meta_permanent_token' => $user['meta_permanent_token'],
            'meta_api_version' => $user['meta_api_version'] ?? 'v19.0'
        ];
        
        $result = sendMetaTextMessage($phone, $message, $userConfig, $userId);
        
        // Fallback para Evolution se Meta falhar
        if (!$result['success'] && $hasEvolution) {
            error_log("[MULTICHANNEL] Meta API falhou, usando Evolution API como fallback");
            return sendWhatsAppViaEvolution($phone, $message, $user);
        }
        
        return $result;
    }
    
    // Usar Evolution API (padrão)
    if (!$hasEvolution) {
        return ['success' => false, 'error' => 'Nenhuma API de WhatsApp configurada'];
    }
    
    return sendWhatsAppViaEvolution($phone, $message, $user);
}

/**
 * Enviar mensagem via Evolution API
 */
function sendWhatsAppViaEvolution(string $phone, string $message, array $user): array
{
    $instanceName = $user['evolution_instance'];
    $apiKey = !empty($user['evolution_token']) ? $user['evolution_token'] : EVOLUTION_API_KEY;
    
    $evolutionUrl = EVOLUTION_API_URL . '/message/sendText/' . $instanceName;
    
    $data = [
        'number' => $phone,
        'text' => $message
    ];
    
    $ch = curl_init($evolutionUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        $responseData = json_decode($response, true);
        return [
            'success' => true,
            'message_id' => $responseData['key']['id'] ?? null
        ];
    }
    
    return [
        'success' => false,
        'error' => 'Erro ao enviar mensagem via Evolution API'
    ];
}
