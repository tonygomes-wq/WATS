<?php
/**
 * WEBHOOK DA Z-API PARA CHAT
 * Recebe eventos de mensagens da Z-API em tempo real
 * 
 * Documentação Z-API: https://developer.z-api.io/webhooks
 */

declare(strict_types=1);

// Definir BASE_PATH se não estiver definido
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// Responder imediatamente a requests OPTIONS/HEAD
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS' || $_SERVER['REQUEST_METHOD'] === 'HEAD') {
    http_response_code(200);
    exit;
}

require_once '../config/database.php';
require_once '../includes/bot_engine.php';
require_once '../includes/AutomationEngine.php';
require_once '../includes/IdentifierResolver.php';

header('Content-Type: application/json');

$logPrefix = '[ZAPI_WEBHOOK]';
$rawPayload = file_get_contents('php://input');
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Log de requisição
if ($requestMethod === 'POST' && !empty($rawPayload)) {
    try {
        $payload = json_decode($rawPayload, true);
        $eventType = $payload['event'] ?? 'unknown';
        
        $stmt = $pdo->prepare("
            INSERT INTO webhook_logs (event_type, payload) 
            VALUES (?, ?)
        ");
        $stmt->execute(['zapi_' . $eventType, $rawPayload]);
    } catch (Exception $e) {
        error_log("$logPrefix Erro ao registrar webhook: " . $e->getMessage());
    }
}

// Validação GET (verificação do webhook)
if ($requestMethod === 'GET') {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Z-API Webhook OK']);
    exit;
}

// Se não for POST, retornar 200 OK
if ($requestMethod !== 'POST') {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Ignored non-POST method']);
    exit;
}

// Decodificar payload
$payload = json_decode($rawPayload, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("$logPrefix Erro ao decodificar payload: " . json_last_error_msg());
    http_response_code(200);
    echo json_encode(['success' => false, 'error' => 'Invalid payload']);
    exit;
}

// Registrar webhook no log
$webhookLogId = logWebhookReceived($payload);

try {
    // Processar evento
    $result = processWebhookEvent($payload, $webhookLogId);

    // Sempre retornar 200 OK
    http_response_code(200);

    if ($result['success']) {
        echo json_encode(['success' => true, 'message' => 'Event processed']);
    } else {
        echo json_encode(['success' => false, 'error' => $result['error']]);
    }
} catch (Exception $e) {
    error_log("$logPrefix Exception: " . $e->getMessage());
    updateWebhookLog($webhookLogId, false, $e->getMessage());
    http_response_code(200);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

exit;

// ==================================================================================
// FUNÇÕES DE PROCESSAMENTO
// ==================================================================================

function logWebhookReceived(array $payload): int
{
    global $pdo, $logPrefix;
    try {
        $eventType = $payload['event'] ?? 'unknown';
        $instanceId = $payload['instanceId'] ?? null;
        $phone = isset($payload['data']['phone']) ? cleanPhone($payload['data']['phone']) : '';

        $stmt = $pdo->prepare("
            INSERT INTO chat_webhook_logs (event_type, instance_name, phone, payload, processed) 
            VALUES (?, ?, ?, ?, 0)
        ");
        $stmt->execute(['zapi_' . $eventType, $instanceId, $phone, json_encode($payload)]);
        return (int) $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("$logPrefix Erro ao registrar webhook: " . $e->getMessage());
        return 0;
    }
}

function updateWebhookLog(int $logId, bool $processed, ?string $error = null): void
{
    global $pdo;
    if ($logId === 0) return;
    try {
        $stmt = $pdo->prepare("
            UPDATE chat_webhook_logs 
            SET processed = ?, error_message = ? 
            WHERE id = ?
        ");
        $stmt->execute([$processed ? 1 : 0, $error, $logId]);
    } catch (Exception $e) {
        // Ignorar erros
    }
}

function processWebhookEvent(array $payload, int $webhookLogId): array
{
    global $logPrefix;
    
    $event = $payload['event'] ?? '';
    $instanceId = $payload['instanceId'] ?? '';

    error_log("$logPrefix Processando evento: $event | Instance: $instanceId");

    // Buscar usuário pela instância Z-API
    $userData = getUserByZAPIInstance($instanceId);
    if (!$userData) {
        error_log("$logPrefix Usuário não encontrado para instância Z-API: $instanceId");
        return ['success' => false, 'error' => 'User not found'];
    }

    $userId = (int) $userData['id'];

    // Processar diferentes tipos de eventos Z-API
    switch ($event) {
        case 'message-received':
        case 'received-callback':
            return handleNewMessage($payload, $userId, $webhookLogId, $userData);
        
        case 'message-status':
        case 'status-callback':
            return handleMessageStatus($payload, $userId);
        
        default:
            error_log("$logPrefix Evento não suportado: $event");
            return ['success' => true, 'message' => 'Event not supported (ignored)'];
    }
}

function getUserByZAPIInstance(string $instanceId): ?array
{
    global $pdo, $logPrefix;
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id, 
                zapi_instance_id, 
                zapi_token,
                whatsapp_provider
            FROM users 
            WHERE zapi_instance_id = ? 
            AND whatsapp_provider = 'zapi'
            LIMIT 1
        ");
        $stmt->execute([$instanceId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            return $user;
        }

        return null;
    } catch (Exception $e) {
        error_log("$logPrefix Erro em getUserByZAPIInstance: " . $e->getMessage());
        return null;
    }
}

function handleNewMessage(array $payload, int $userId, int $webhookLogId, array $userData = []): array
{
    global $pdo, $logPrefix;
    try {
        $data = $payload['data'] ?? [];
        
        // Z-API envia dados diferentes da Evolution
        $phone = cleanPhone($data['phone'] ?? $data['from'] ?? '');
        $messageId = $data['messageId'] ?? $data['id'] ?? uniqid('zapi_');
        $fromMe = (bool) ($data['fromMe'] ?? false);
        $timestamp = isset($data['timestamp']) ? (int) $data['timestamp'] : time();
        $instanceId = $payload['instanceId'] ?? '';
        $pushName = $data['senderName'] ?? $data['notifyName'] ?? null;

        // Extrair texto da mensagem
        $messageText = extractZAPIMessageText($data);
        $messageType = detectZAPIMessageType($data);
        
        // Extrair mídia se houver
        $mediaData = extractZAPIMediaData($data, $messageId, $userId);

        if (empty($phone)) {
            return ['success' => false, 'error' => 'Phone number missing'];
        }

        error_log("$logPrefix Nova mensagem de: $phone (tipo: $messageType)");

        // Verificar duplicata
        if ($messageId) {
            $checkStmt = $pdo->prepare("SELECT id FROM chat_messages WHERE message_id = ? LIMIT 1");
            $checkStmt->execute([$messageId]);
            if ($checkStmt->fetch()) {
                updateWebhookLog($webhookLogId, true);
                return ['success' => true, 'message' => 'Duplicate ignored'];
            }
        }

        // Criar ou buscar conversa
        $conversationId = getOrCreateConversation($userId, $phone, $instanceId);
        if (!$conversationId) {
            return ['success' => false, 'error' => 'Failed to create conversation'];
        }

        // Inserir mensagem
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages 
            (conversation_id, user_id, message_id, from_me, message_type, message_text, 
             media_url, media_mimetype, media_filename, caption, status, timestamp) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $status = $fromMe ? 'sent' : 'delivered';

        $stmt->execute([
            $conversationId,
            $userId,
            $messageId,
            $fromMe ? 1 : 0,
            $messageType,
            $messageText,
            $mediaData['url'] ?? null,
            $mediaData['mimetype'] ?? null,
            $mediaData['filename'] ?? null,
            $mediaData['caption'] ?? null,
            $status,
            $timestamp
        ]);

        // Atualizar conversa
        $unreadIncrement = $fromMe ? 0 : 1;
        $stmt = $pdo->prepare("
            UPDATE chat_conversations 
            SET last_message_time = FROM_UNIXTIME(?), 
                updated_at = NOW(),
                last_message_text = ?,
                unread_count = unread_count + ?
            WHERE id = ?
        ");
        $stmt->execute([$timestamp, $messageText, $unreadIncrement, $conversationId]);

        // Atualizar nome do contato se disponível
        if (!$fromMe && !empty($pushName)) {
            updateConversationContactName($conversationId, $pushName);
            createOrUpdateContactFromPushName($userId, $phone, $pushName);
        }

        // Processar bot e automações (apenas para mensagens recebidas)
        if (!$fromMe) {
            try {
                // Bot Engine
                $botEngine = new BotEngine($pdo, $userId);
                if ($botEngine->hasActiveSession($phone)) {
                    $botProcessed = $botEngine->processInput($phone, $messageText ?? '', [
                        'message_id' => $messageId, 
                        'timestamp' => $timestamp
                    ]);
                    if ($botProcessed) {
                        updateWebhookLog($webhookLogId, true);
                        return ['success' => true, 'handler' => 'bot_engine'];
                    }
                } else {
                    $triggerFlowId = $botEngine->checkTriggers($phone, $messageText ?? '', $conversationId);
                    if ($triggerFlowId) {
                        $botEngine->startSession($triggerFlowId, $phone, null);
                        updateWebhookLog($webhookLogId, true);
                        return ['success' => true, 'handler' => 'bot_engine_started'];
                    } else {
                        // Automation Flows
                        try {
                            $automationEngine = new AutomationEngine($pdo, $userId);
                            $automationResult = $automationEngine->checkAndExecute(
                                $phone,
                                $messageText ?? '',
                                $conversationId,
                                [
                                    'timestamp' => $timestamp,
                                    'message_id' => $messageId,
                                    'channel' => 'zapi',
                                    'user_data' => $userData
                                ]
                            );
                            
                            if ($automationResult['flows_executed'] > 0) {
                                error_log("$logPrefix Automation flows executed: {$automationResult['flows_executed']}");
                            }
                        } catch (Exception $automationException) {
                            error_log("$logPrefix Erro ao executar automation flows: " . $automationException->getMessage());
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("$logPrefix Erro em bot/automação: " . $e->getMessage());
            }
        }

        updateWebhookLog($webhookLogId, true);
        return ['success' => true];
        
    } catch (Exception $e) {
        error_log("$logPrefix Erro em handleNewMessage: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function handleMessageStatus(array $payload, int $userId): array
{
    global $pdo, $logPrefix;
    try {
        $data = $payload['data'] ?? [];
        $messageId = $data['messageId'] ?? $data['id'] ?? null;
        $status = $data['status'] ?? null;

        if (!$messageId) {
            return ['success' => false, 'error' => 'Message ID missing'];
        }

        // Mapear status Z-API para nosso formato
        $statusMap = [
            'SENT' => 'sent',
            'DELIVERED' => 'delivered',
            'READ' => 'read',
            'FAILED' => 'failed',
            'ERROR' => 'failed'
        ];

        $newStatus = $statusMap[$status] ?? null;

        if ($newStatus) {
            $stmt = $pdo->prepare("
                UPDATE chat_messages 
                SET status = ?, 
                    read_at = CASE WHEN ? = 'read' THEN NOW() ELSE read_at END
                WHERE message_id = ?
            ");
            $stmt->execute([$newStatus, $newStatus, $messageId]);
            
            error_log("$logPrefix Status atualizado: $messageId -> $newStatus");
        }

        return ['success' => true, 'status' => $newStatus];
        
    } catch (Exception $e) {
        error_log("$logPrefix Erro em handleMessageStatus: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function extractZAPIMessageText(array $data): ?string
{
    // Z-API envia texto em diferentes campos dependendo do tipo
    if (isset($data['text']['message'])) {
        return $data['text']['message'];
    }
    
    if (isset($data['message'])) {
        return $data['message'];
    }
    
    if (isset($data['body'])) {
        return $data['body'];
    }
    
    // Caption para mídias
    if (isset($data['image']['caption'])) {
        return $data['image']['caption'];
    }
    
    if (isset($data['video']['caption'])) {
        return $data['video']['caption'];
    }
    
    return null;
}

function detectZAPIMessageType(array $data): string
{
    if (isset($data['image'])) return 'image';
    if (isset($data['video'])) return 'video';
    if (isset($data['audio'])) return 'audio';
    if (isset($data['document'])) return 'document';
    if (isset($data['sticker'])) return 'sticker';
    if (isset($data['location'])) return 'location';
    if (isset($data['contact'])) return 'contact';
    
    return 'text';
}

function extractZAPIMediaData(array $data, string $messageId, int $userId): array
{
    $type = detectZAPIMessageType($data);
    if ($type === 'text') return [];

    $mediaData = $data[$type] ?? [];
    $mediaUrl = $mediaData['url'] ?? $mediaData['downloadUrl'] ?? null;
    
    // Se temos URL, tentar baixar
    if ($mediaUrl) {
        $localUrl = downloadZAPIMedia($mediaUrl, $messageId, $type, $userId);
        if ($localUrl) {
            $mediaUrl = $localUrl;
        }
    }

    return [
        'url' => $mediaUrl,
        'mimetype' => $mediaData['mimetype'] ?? 'application/octet-stream',
        'filename' => $mediaData['filename'] ?? "$messageId.$type",
        'caption' => $mediaData['caption'] ?? ''
    ];
}

function downloadZAPIMedia(string $url, string $messageId, string $type, int $userId): ?string
{
    try {
        $content = @file_get_contents($url);
        if (!$content) {
            return null;
        }

        $uploadDir = __DIR__ . "/../uploads/user_{$userId}/chat_media/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $extension = $type;
        $filename = $messageId . '.' . $extension;
        $filepath = $uploadDir . $filename;

        if (file_put_contents($filepath, $content)) {
            $publicUrl = "/uploads/user_{$userId}/chat_media/" . $filename;
            error_log("[ZAPI_WEBHOOK] Mídia salva: $publicUrl");
            return $publicUrl;
        }

        return null;
    } catch (Exception $e) {
        error_log("[ZAPI_WEBHOOK] Erro ao baixar mídia: " . $e->getMessage());
        return null;
    }
}

function getOrCreateConversation(int $userId, string $phone, ?string $instanceId = null): ?int
{
    global $pdo;
    try {
        $normalizedPhone = preg_replace('/[^0-9]/', '', $phone);

        // Buscar conversa existente
        $stmt = $pdo->prepare("
            SELECT id 
            FROM chat_conversations 
            WHERE user_id = ? 
            AND REPLACE(REPLACE(phone, '+', ''), '-', '') = ? 
            LIMIT 1
        ");
        $stmt->execute([$userId, $normalizedPhone]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            return (int) $existing['id'];
        }

        // Criar nova conversa
        $stmt = $pdo->prepare("
            INSERT INTO chat_conversations (user_id, phone, instance_name, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $phone, $instanceId]);
        
        return (int) $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("[ZAPI_WEBHOOK] Erro ao criar conversa: " . $e->getMessage());
        return null;
    }
}

function updateConversationContactName(int $convId, string $name): void
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE chat_conversations SET contact_name = ? WHERE id = ?");
        $stmt->execute([$name, $convId]);
    } catch (Exception $e) {
        // Ignorar erros
    }
}

function createOrUpdateContactFromPushName(int $userId, string $phone, string $name): void
{
    global $pdo;
    $phone = cleanPhone($phone);
    try {
        $stmt = $pdo->prepare("SELECT id FROM contacts WHERE user_id = ? AND phone = ?");
        $stmt->execute([$userId, $phone]);
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("
                UPDATE contacts 
                SET name = ? 
                WHERE user_id = ? AND phone = ? AND (name IS NULL OR name = phone)
            ");
            $stmt->execute([$name, $userId, $phone]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO contacts (user_id, phone, name, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $phone, $name]);
        }
    } catch (Exception $e) {
        // Ignorar erros
    }
}

function cleanPhone(string $phone): string
{
    return preg_replace('/[^0-9]/', '', $phone);
}
