<?php

/**
 * WEBHOOK DA EVOLUTION API PARA CHAT
 * Recebe eventos de mensagens em tempo real
 * VERSÃO: ROBUSTA (Evita 409 Conflict)
 */

declare(strict_types=1);

// Definir BASE_PATH se não estiver definido
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// --- CABEÇALHOS E VALIDAÇÃO PRÉVIA ---
// Responder imediatamente a requests OPTIONS/HEAD para acalmar servidores/proxies
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS' || $_SERVER['REQUEST_METHOD'] === 'HEAD') {
    http_response_code(200);
    exit;
}

require_once '../config/database.php';
require_once '../libs/google_ai.php';
require_once '../includes/bot_engine.php';
require_once '../includes/AutomationEngine.php';

header('Content-Type: application/json');

// Log de requisição
$logPrefix = '[CHAT_WEBHOOK]';
$rawPayload = file_get_contents('php://input');
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Registrar requisição no banco para diagnóstico
if ($requestMethod === 'POST' && !empty($rawPayload)) {
    try {
        $payload = json_decode($rawPayload, true);
        $eventType = $payload['event'] ?? 'unknown';
        
        $stmt = $pdo->prepare("
            INSERT INTO webhook_logs (event_type, payload) 
            VALUES (?, ?)
        ");
        $stmt->execute([$eventType, $rawPayload]);
    } catch (Exception $e) {
        // Ignorar erros de log para não afetar o webhook
    }
}

// Validação GET (verificação do webhook)
if ($requestMethod === 'GET') {
    handleWebhookVerification();
    exit;
}

// Se não for POST, retornar 200 OK para evitar retentativas infinitas
if ($requestMethod !== 'POST') {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Ignored non-POST method']);
    exit;
}

// ==================================================================================
// OLD AUTOMATION FLOWS LOGIC (DEPRECATED - Now using AutomationEngine)
// ==================================================================================
// The old processAutomationFlows function has been replaced by AutomationEngine
// which provides a more robust and modular implementation with proper separation
// of concerns (TriggerEvaluator, AIProcessor, ActionExecutor, VariableSubstitutor)

// ==================================================================================
// PROCESSAMENTO PRINCIPAL RESTAURADO
// ==================================================================================

// Decodificar payload
$payload = json_decode($rawPayload, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $decoded = base64_decode($rawPayload, true);
    if ($decoded !== false) {
        $payload = json_decode($decoded, true);
        error_log("$logPrefix Payload decodificado de Base64");
    }
}

if (!$payload || json_last_error() !== JSON_ERROR_NONE) {
    error_log("$logPrefix Erro ao decodificar payload: " . json_last_error_msg());
    // Retornamos 200 OK mesmo com erro para não travar a fila da API
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
    // Sempre retornar 200 OK
    http_response_code(200);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

exit; // FIM DO SCRIPT PRINCIPAL

// ==================================================================================
// FUNÇÕES DE PROCESSAMENTO
// ==================================================================================

function handleWebhookVerification(): void
{
    global $logPrefix;
    $token = $_GET['token'] ?? '';
    // Aceita qualquer token para simplificar, já que retorna 200 sempre
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Webhook verified']);
}

function logWebhookReceived(array $payload): int
{
    global $pdo, $logPrefix;
    try {
        $eventType = $payload['event'] ?? 'unknown';
        $instanceName = $payload['instance'] ?? null;
        $phone = isset($payload['data']['key']['remoteJid']) ? cleanPhone($payload['data']['key']['remoteJid']) : '';

        $stmt = $pdo->prepare("INSERT INTO chat_webhook_logs (event_type, instance_name, phone, payload, processed) VALUES (?, ?, ?, ?, 0)");
        $stmt->execute([$eventType, $instanceName, $phone, json_encode($payload)]);
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
        $stmt = $pdo->prepare("UPDATE chat_webhook_logs SET processed = ?, error_message = ? WHERE id = ?");
        $stmt->execute([$processed ? 1 : 0, $error, $logId]);
    } catch (Exception $e) {
    }
}

function processWebhookEvent(array $payload, int $webhookLogId): array
{
    global $logPrefix;
    $event = $payload['event'] ?? '';
    $instance = $payload['instance'] ?? '';

    // error_log("$logPrefix Processando evento: $event | Instância: $instance");

    $userData = getUserByInstance($instance);
    if (!$userData) {
        error_log("$logPrefix Usuário não encontrado para instância: $instance");
        return ['success' => false, 'error' => 'User not found'];
    }

    $userId = (int) $userData['id'];

    switch ($event) {
        case 'messages.upsert':
        case 'message.create':
            return handleNewMessage($payload, $userId, $webhookLogId, $userData);
        case 'messages.update':
        case 'message.update':
            return handleMessageUpdate($payload, $userId);
        case 'messages.delete':
            return handleMessageDelete($payload, $userId);
        default:
            // Não retornar erro, apenas ignorar
            return ['success' => true, 'message' => 'Event not supported (ignored)'];
    }
}

function getUserByInstance(string $instance): ?array
{
    global $pdo, $logPrefix;
    try {
        // Primeiro, buscar na tabela users (supervisores/admins)
        $stmt = $pdo->prepare("SELECT id, evolution_api_url, evolution_token as evolution_api_key, evolution_instance, 'supervisor' as user_type FROM users WHERE evolution_instance = ? LIMIT 1");
        $stmt->execute([$instance]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            return $user;
        }

        // Se não encontrou, buscar na tabela attendant_instances (atendentes com instância própria)
        try {
            $checkTable = $pdo->query("SHOW TABLES LIKE 'attendant_instances'");
            if ($checkTable->rowCount() > 0) {
                $stmt = $pdo->prepare("
                    SELECT 
                        su.id,
                        u.evolution_api_url,
                        u.evolution_token as evolution_api_key,
                        ai.instance_name as evolution_instance,
                        'attendant' as user_type,
                        ai.attendant_id,
                        su.supervisor_id
                    FROM attendant_instances ai
                    JOIN supervisor_users su ON ai.attendant_id = su.id
                    JOIN users u ON su.supervisor_id = u.id
                    WHERE ai.instance_name = ?
                    LIMIT 1
                ");
                $stmt->execute([$instance]);
                $attendant = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($attendant) {
                    error_log("$logPrefix Instância de atendente encontrada: $instance (attendant_id: {$attendant['id']})");
                    return $attendant;
                }
            }
        } catch (Exception $e) {
            error_log("$logPrefix Erro ao buscar instância de atendente: " . $e->getMessage());
        }

        return null;
    } catch (Exception $e) {
        error_log("$logPrefix Erro em getUserByInstance: " . $e->getMessage());
        return null;
    }
}

function handleNewMessage(array $payload, int $userId, int $webhookLogId, array $userData = []): array
{
    global $pdo, $logPrefix;
    try {
        $data = $payload['data'] ?? [];
        $key = $data['key'] ?? [];
        $message = $data['message'] ?? [];

        $phone = cleanPhone($key['remoteJid'] ?? '');
        $messageId = $key['id'] ?? null;
        $fromMe = (bool) ($key['fromMe'] ?? false);
        $timestamp = $data['messageTimestamp'] ?? time();
        $instance = $payload['instance'] ?? $userData['evolution_instance'] ?? '';
        $pushName = $data['pushName'] ?? null;

        $apiUrl = $userData['evolution_api_url'] ?? '';
        $apiKey = $userData['evolution_api_key'] ?? '';

        $messageText = extractMessageText($message);
        $messageType = detectMessageType($message);
        $mediaData = extractMediaData($message, $messageId, $instance, $apiUrl, $apiKey, $userId);

        // NÃO usar URL do WhatsApp como fallback - apenas salvar se conseguir baixar localmente
        // Se não conseguir baixar, deixar media_url como NULL
        if (empty($mediaData['url']) && $messageType !== 'text') {
            error_log("$logPrefix Não foi possível baixar mídia para message_id: $messageId (tipo: $messageType)");
            // Definir texto alternativo
            if (empty($messageText)) {
                switch ($messageType) {
                    case 'image':
                        $messageText = '[Imagem não disponível]';
                        break;
                    case 'audio':
                        $messageText = '[Áudio não disponível]';
                        break;
                    case 'video':
                        $messageText = '[Vídeo não disponível]';
                        break;
                    case 'document':
                        $messageText = '[Documento não disponível]';
                        break;
                    default:
                        $messageText = '[Mídia não disponível]';
                }
            }
        }

        if (empty($phone)) return ['success' => false, 'error' => 'Phone number missing'];

        error_log("$logPrefix Nova mensagem de: $phone");

        // Verificar duplicata por message_id OU por combinação de conversation+timestamp+texto
        if ($messageId) {
            $checkStmt = $pdo->prepare("SELECT id FROM chat_messages WHERE message_id = ? LIMIT 1");
            $checkStmt->execute([$messageId]);
            if ($checkStmt->fetch()) {
                updateWebhookLog($webhookLogId, true);
                return ['success' => true, 'message' => 'Duplicate ignored (by message_id)'];
            }
        }

        // Verificar duplicata por timestamp (mensagens nos últimos 5 segundos com mesmo texto)
        $conversationIdCheck = getOrCreateConversation($userId, $phone, $instance);
        if ($conversationIdCheck && $messageText) {
            $checkStmt = $pdo->prepare("
                SELECT id FROM chat_messages 
                WHERE conversation_id = ? 
                AND message_text = ? 
                AND from_me = ?
                AND timestamp >= ? - 5
                AND timestamp <= ? + 5
                LIMIT 1
            ");
            $checkStmt->execute([$conversationIdCheck, $messageText, $fromMe ? 1 : 0, $timestamp, $timestamp]);
            if ($checkStmt->fetch()) {
                updateWebhookLog($webhookLogId, true);
                return ['success' => true, 'message' => 'Duplicate ignored (by content+time)'];
            }
        }

        $conversationId = getOrCreateConversation($userId, $phone, $instance);
        if (!$conversationId) return ['success' => false, 'error' => 'Failed to create conversation'];

        $stmt = $pdo->prepare("INSERT INTO chat_messages (conversation_id, user_id, message_id, from_me, message_type, message_text, media_url, media_mimetype, media_filename, caption, status, timestamp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
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

        // --- ATUALIZAR CONVERSA (CRÍTICO PARA APARECER NO TOPO) ---
        // Incrementamos unread_count apenas se NÃO for mensagem minha
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


        if (!$fromMe && !empty($pushName)) {
            updateConversationContactName($conversationId, $pushName);
            createOrUpdateContactFromPushName($userId, $phone, $pushName);

            // --- NOVO: BUSCAR FOTO DE PERFIL ---
            if (!empty($instance) && !empty($apiUrl) && !empty($apiKey)) {
                fetchAndSaveContactProfilePic($userId, $phone, $instance, $apiUrl, $apiKey);
            }
        }

        if (!$fromMe) {
            // Capturar resposta a disparo
            try {
                captureDispatchResponse($userId, $conversationId, $phone, $messageText, $messageType, $timestamp);
            } catch (Exception $responseException) {
                error_log("$logPrefix Erro ao capturar resposta: " . $responseException->getMessage());
            }

            try {
                $botEngine = new BotEngine($pdo, $userId);
                if ($botEngine->hasActiveSession($phone)) {
                    $botProcessed = $botEngine->processInput($phone, $messageText ?? '', ['message_id' => $messageId, 'timestamp' => $timestamp]);
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
                        // No bot session and no bot trigger - check automation flows
                        try {
                            $automationEngine = new AutomationEngine($pdo, $userId);
                            $automationResult = $automationEngine->checkAndExecute(
                                $phone,
                                $messageText ?? '',
                                $conversationId,
                                [
                                    'timestamp' => (int) $timestamp,
                                    'message_id' => $messageId,
                                    'channel' => 'whatsapp',
                                    'user_data' => $userData
                                ]
                            );
                            
                            if ($automationResult['flows_executed'] > 0) {
                                error_log("$logPrefix Automation flows executed: {$automationResult['flows_executed']}");
                            }
                            
                            if (!$automationResult['success'] && !empty($automationResult['errors'])) {
                                error_log("$logPrefix Automation errors: " . json_encode($automationResult['errors']));
                            }
                        } catch (Exception $automationException) {
                            error_log("$logPrefix Erro ao executar automation flows: " . $automationException->getMessage());
                        }
                    }
                }
            } catch (Exception $automationException) {
                error_log("$logPrefix Erro automação: " . $automationException->getMessage());
            }
        }

        updateWebhookLog($webhookLogId, true);
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function handleMessageUpdate(array $payload, int $userId): array
{
    global $pdo, $logPrefix;
    try {
        $data = $payload['data'] ?? [];
        $messageId = null;
        $status = null;
        
        // FORMATO 1: data.key.id + data.update.status (Evolution API v1)
        if (isset($data['key']['id'])) {
            $messageId = $data['key']['id'];
            $status = $data['update']['status'] ?? null;
        }
        // FORMATO 2: data é um array de updates (Evolution API v2)
        elseif (is_array($data) && isset($data[0])) {
            $firstUpdate = $data[0];
            $messageId = $firstUpdate['key']['id'] ?? $firstUpdate['keyId'] ?? null;
            $status = $firstUpdate['update']['status'] ?? $firstUpdate['status'] ?? null;
            
            // Processar múltiplos updates se existirem
            $results = [];
            foreach ($data as $updateItem) {
                $itemMsgId = $updateItem['key']['id'] ?? $updateItem['keyId'] ?? null;
                $itemStatus = $updateItem['update']['status'] ?? $updateItem['status'] ?? null;
                if ($itemMsgId && $itemStatus) {
                    $results[] = processStatusUpdate($itemMsgId, $itemStatus, $pdo, $logPrefix);
                }
            }
            if (!empty($results)) {
                return ['success' => true, 'processed' => count($results), 'results' => $results];
            }
        }
        // FORMATO 3: data.keyId + data.status (formato alternativo)
        elseif (isset($data['keyId'])) {
            $messageId = $data['keyId'];
            $status = $data['status'] ?? null;
        }
        // FORMATO 4: data.id + data.status (outro formato possível)
        elseif (isset($data['id']) && !isset($data['action'])) {
            $messageId = $data['id'];
            $status = $data['status'] ?? null;
        }
        
        // Log do formato detectado
        error_log("$logPrefix [MESSAGE_UPDATE] Formato detectado - messageId: " . ($messageId ?? 'NULL') . ", status: " . ($status ?? 'NULL'));
        
        // Se status vier como número, converter
        if (is_numeric($status)) {
            $statusNumMap = [
                0 => 'ERROR',
                1 => 'PENDING',
                2 => 'SERVER_ACK',
                3 => 'DELIVERY_ACK',
                4 => 'READ',
                5 => 'PLAYED'
            ];
            $status = $statusNumMap[(int)$status] ?? null;
        }

        if (!$messageId) {
            error_log("$logPrefix [MESSAGE_UPDATE] Message ID não encontrado no payload: " . json_encode($data));
            return ['success' => false, 'error' => 'Message ID missing'];
        }

        // Mapear status da Evolution API para nosso formato
        // A Evolution API pode enviar: PENDING, SERVER_ACK, DELIVERY_ACK, READ, PLAYED
        // Também pode enviar em minúsculas ou com variações
        $statusMap = [
            'PENDING' => 'pending',
            'pending' => 'pending',
            'SERVER_ACK' => 'sent',
            'server_ack' => 'sent',
            'SENT' => 'sent',
            'sent' => 'sent',
            'DELIVERY_ACK' => 'delivered',
            'delivery_ack' => 'delivered',
            'DELIVERED' => 'delivered',
            'delivered' => 'delivered',
            'READ' => 'read',
            'read' => 'read',
            'PLAYED' => 'read',
            'played' => 'read',
            'ERROR' => 'failed',
            'error' => 'failed',
            'FAILED' => 'failed',
            'failed' => 'failed'
        ];
        
        $newStatus = $statusMap[$status] ?? null;
        
        error_log("$logPrefix [MESSAGE_UPDATE] messageId: $messageId, rawStatus: $status, newStatus: $newStatus");

        // Atualizar status no dispatch_history também
        if ($newStatus && in_array($newStatus, ['delivered', 'read'])) {
            $column = $newStatus . '_at';
            $stmt = $pdo->prepare("
                UPDATE dispatch_history 
                SET status = ?, {$column} = NOW() 
                WHERE message_id = ?
            ");
            $stmt->execute([$newStatus, $messageId]);
            $rowsAffected = $stmt->rowCount();
            error_log("$logPrefix [MESSAGE_UPDATE] dispatch_history atualizado: $messageId -> $newStatus (rows: $rowsAffected)");
        }

        if ($newStatus) {
            // Atualizar status da mensagem - sem filtrar por user_id para garantir que funcione
            $stmt = $pdo->prepare("
                UPDATE chat_messages 
                SET status = ?, 
                    read_at = CASE WHEN ? IN ('read') THEN NOW() ELSE read_at END
                WHERE message_id = ?
            ");
            $stmt->execute([$newStatus, $newStatus, $messageId]);
            $rowsAffected = $stmt->rowCount();
            error_log("$logPrefix [MESSAGE_UPDATE] chat_messages atualizado: $messageId -> $newStatus (rows: $rowsAffected)");
            
            // Se não encontrou pelo message_id exato, tentar buscar por padrão parcial
            if ($rowsAffected === 0) {
                error_log("$logPrefix [MESSAGE_UPDATE] Nenhuma linha atualizada, tentando busca parcial...");
                
                // Verificar se a mensagem existe
                $checkStmt = $pdo->prepare("SELECT id, message_id, status FROM chat_messages WHERE message_id LIKE ? ORDER BY id DESC LIMIT 1");
                $checkStmt->execute(['%' . substr($messageId, -10)]);
                $existingMsg = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existingMsg) {
                    error_log("$logPrefix [MESSAGE_UPDATE] Encontrada mensagem similar: ID={$existingMsg['id']}, message_id={$existingMsg['message_id']}, status={$existingMsg['status']}");
                } else {
                    error_log("$logPrefix [MESSAGE_UPDATE] Nenhuma mensagem encontrada com message_id similar a: $messageId");
                }
            }
        }
        return ['success' => true, 'status' => $newStatus, 'rows_affected' => $rowsAffected ?? 0];
    } catch (Exception $e) {
        error_log("$logPrefix [MESSAGE_UPDATE] Erro: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function handleMessageDelete(array $payload, int $userId): array
{
    global $pdo, $logPrefix;
    try {
        $key = $payload['data']['key'] ?? [];
        $messageId = $key['id'] ?? null;
        if (!$messageId) return ['success' => false];
        $stmt = $pdo->prepare("UPDATE chat_messages SET message_text = '[Mensagem deletada]', status = 'failed' WHERE message_id = ? AND user_id = ?");
        $stmt->execute([$messageId, $userId]);
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false];
    }
}

/**
 * Processa uma atualização de status individual
 */
function processStatusUpdate(string $messageId, $status, PDO $pdo, string $logPrefix): array
{
    // Se status vier como número, converter
    if (is_numeric($status)) {
        $statusNumMap = [
            0 => 'ERROR',
            1 => 'PENDING',
            2 => 'SERVER_ACK',
            3 => 'DELIVERY_ACK',
            4 => 'READ',
            5 => 'PLAYED'
        ];
        $status = $statusNumMap[(int)$status] ?? null;
    }
    
    // Mapear status da Evolution API para nosso formato
    $statusMap = [
        'PENDING' => 'pending',
        'pending' => 'pending',
        'SERVER_ACK' => 'sent',
        'server_ack' => 'sent',
        'SENT' => 'sent',
        'sent' => 'sent',
        'DELIVERY_ACK' => 'delivered',
        'delivery_ack' => 'delivered',
        'DELIVERED' => 'delivered',
        'delivered' => 'delivered',
        'READ' => 'read',
        'read' => 'read',
        'PLAYED' => 'read',
        'played' => 'read',
        'ERROR' => 'failed',
        'error' => 'failed',
        'FAILED' => 'failed',
        'failed' => 'failed'
    ];
    
    $newStatus = $statusMap[$status] ?? null;
    
    if (!$newStatus) {
        return ['success' => false, 'error' => 'Unknown status: ' . $status];
    }
    
    // Atualizar status no dispatch_history também
    if (in_array($newStatus, ['delivered', 'read'])) {
        $column = $newStatus . '_at';
        $stmt = $pdo->prepare("
            UPDATE dispatch_history 
            SET status = ?, {$column} = NOW() 
            WHERE message_id = ?
        ");
        $stmt->execute([$newStatus, $messageId]);
    }
    
    // Atualizar status da mensagem
    $stmt = $pdo->prepare("
        UPDATE chat_messages 
        SET status = ?, 
            read_at = CASE WHEN ? IN ('read') THEN NOW() ELSE read_at END
        WHERE message_id = ?
    ");
    $stmt->execute([$newStatus, $newStatus, $messageId]);
    $rowsAffected = $stmt->rowCount();
    
    error_log("$logPrefix [PROCESS_STATUS] $messageId -> $newStatus (rows: $rowsAffected)");
    
    return ['success' => true, 'message_id' => $messageId, 'status' => $newStatus, 'rows' => $rowsAffected];
}

function getOrCreateConversation(int $userId, string $phone, ?string $instanceName = null): ?int
{
    global $pdo;
    try {
        $normalizedPhone = preg_replace('/[^0-9]/', '', $phone);

        // Verificar se coluna instance_name existe
        $hasInstanceColumn = false;
        try {
            $checkCol = $pdo->query("SHOW COLUMNS FROM chat_conversations LIKE 'instance_name'");
            $hasInstanceColumn = $checkCol->rowCount() > 0;
        } catch (Exception $e) {
            $hasInstanceColumn = false;
        }

        // Criar coluna se não existir
        if (!$hasInstanceColumn) {
            try {
                $pdo->exec("ALTER TABLE chat_conversations ADD COLUMN instance_name VARCHAR(100) DEFAULT NULL AFTER user_id");
                error_log("[CHAT_WEBHOOK] Coluna instance_name criada");
            } catch (Exception $e) {
                error_log("[CHAT_WEBHOOK] Erro ao criar coluna instance_name: " . $e->getMessage());
            }
        }

        // NOVA LÓGICA: Buscar conversa existente por user_id + phone PRIMEIRO
        // Depois atualizar com instance_name se necessário
        $stmt = $pdo->prepare("SELECT id, instance_name FROM chat_conversations WHERE user_id = ? AND REPLACE(REPLACE(phone, '+', ''), '-', '') = ? LIMIT 1");
        $stmt->execute([$userId, $normalizedPhone]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $existingId = (int) $existing['id'];
            $existingInstance = $existing['instance_name'];
            
            // Se a conversa existe mas não tem instance_name, atualizar
            if ($instanceName && empty($existingInstance)) {
                $stmt = $pdo->prepare("UPDATE chat_conversations SET instance_name = ? WHERE id = ?");
                $stmt->execute([$instanceName, $existingId]);
                error_log("[CHAT_WEBHOOK] Conversa $existingId atualizada com instance_name: $instanceName");
            }
            
            return $existingId;
        }

        // Se não existe, criar nova conversa
        if ($instanceName) {
            $stmt = $pdo->prepare("INSERT INTO chat_conversations (user_id, instance_name, phone, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$userId, $instanceName, $phone]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO chat_conversations (user_id, phone, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$userId, $phone]);
        }
        
        error_log("[CHAT_WEBHOOK] Nova conversa criada - user_id: $userId, phone: $phone, instance: $instanceName");
        return (int) $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("[CHAT_WEBHOOK] Erro conversa: " . $e->getMessage());
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
            $stmt = $pdo->prepare("UPDATE contacts SET name = ? WHERE user_id = ? AND phone = ? AND (name IS NULL OR name = phone)");
            $stmt->execute([$name, $userId, $phone]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO contacts (user_id, phone, name, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$userId, $phone, $name]);
        }
    } catch (Exception $e) {
    }
}

function extractMessageText(array $message): ?string
{
    if (isset($message['conversation']) && is_string($message['conversation'])) return $message['conversation'];
    if (isset($message['extendedTextMessage']['text'])) return $message['extendedTextMessage']['text'];
    
    // Tratar contato compartilhado
    if (isset($message['contactMessage'])) {
        $displayName = $message['contactMessage']['displayName'] ?? 'Sem nome';
        $vcard = $message['contactMessage']['vcard'] ?? '';
        
        // Extrair número de telefone do vCard
        $phone = '';
        if (preg_match('/waid=(\d+)/', $vcard, $matches)) {
            $phone = $matches[1];
        } elseif (preg_match('/TEL[^:]*:([+\d\s()-]+)/', $vcard, $matches)) {
            $phone = preg_replace('/[^\d]/', '', $matches[1]);
        }
        
        // Retornar com formato especial que inclui o telefone
        return "[Contato compartilhado] $displayName" . ($phone ? "|$phone" : '');
    }
    
    // Tratar localização
    if (isset($message['locationMessage'])) {
        $lat = $message['locationMessage']['degreesLatitude'] ?? '';
        $lng = $message['locationMessage']['degreesLongitude'] ?? '';
        $name = $message['locationMessage']['name'] ?? '';
        $address = $message['locationMessage']['address'] ?? '';
        $locationText = '[Localização]';
        if ($name) $locationText .= " $name";
        if ($address) $locationText .= " - $address";
        return $locationText;
    }
    
    $types = ['imageMessage', 'videoMessage', 'documentMessage', 'audioMessage'];
    foreach ($types as $type) {
        if (isset($message[$type]['caption'])) return $message[$type]['caption'];
    }
    return null;
}

function detectMessageType(array $message): string
{
    if (isset($message['imageMessage'])) return 'image';
    if (isset($message['videoMessage'])) return 'video';
    if (isset($message['audioMessage'])) return 'audio';
    if (isset($message['documentMessage'])) return 'document';
    if (isset($message['stickerMessage'])) return 'sticker';
    if (isset($message['contactMessage'])) return 'contact';
    if (isset($message['locationMessage'])) return 'location';
    return 'text';
}

function extractMediaData($message, $messageId, $instance, $apiUrl, $apiKey, $userId = null): array
{
    $type = detectMessageType($message);
    if ($type === 'text') return [];

    $m = $message[$type . 'Message'] ?? [];
    
    // Tentar baixar a mídia da Evolution API
    $mediaUrl = null;
    if (!empty($apiUrl) && !empty($apiKey) && !empty($messageId)) {
        $mediaUrl = downloadMediaFromEvolution($messageId, $instance, $apiUrl, $apiKey, $type, $userId);
    }
    
    // Fallback: usar URL direta se disponível
    if (!$mediaUrl && isset($m['url'])) {
        $mediaUrl = $m['url'];
    }
    
    return [
        'url' => $mediaUrl,
        'mimetype' => $m['mimetype'] ?? '',
        'filename' => "$messageId.$type",
        'caption' => $m['caption'] ?? ''
    ];
}

function downloadMediaFromEvolution($messageId, $instance, $apiUrl, $apiKey, $type, $userId = null): ?string
{
    try {
        // IMPORTANTE: Evolution API v2 usa endpoint diferente
        // Tentar primeiro com o endpoint correto da v2
        $url = rtrim($apiUrl, '/') . "/chat/getBase64FromMediaMessage/{$instance}";
        
        $payload = json_encode([
            'message' => [
                'key' => [
                    'id' => $messageId
                ]
            ]
        ]);
        
        error_log("[CHAT_WEBHOOK] Tentando baixar mídia: messageId=$messageId, url=$url, userId=$userId");
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'apikey: ' . $apiKey
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if (($httpCode !== 200 && $httpCode !== 201) || !$response) {
            error_log("[CHAT_WEBHOOK] Erro ao baixar mídia: HTTP $httpCode, cURL error: $curlError");
            error_log("[CHAT_WEBHOOK] Response: " . substr($response, 0, 200));
            return null;
        }
        
        $data = json_decode($response, true);
        
        // Evolution API pode retornar em diferentes formatos
        $base64Media = $data['base64'] ?? $data['media']['data'] ?? null;
        $mimetype = $data['mimetype'] ?? $data['media']['mimetype'] ?? 'application/octet-stream';
        
        if (!$base64Media) {
            error_log("[CHAT_WEBHOOK] Base64 vazio na resposta da API. Response keys: " . implode(', ', array_keys($data ?? [])));
            return null;
        }
        
        // Remover prefixo data:image/... se existir
        if (strpos($base64Media, 'data:') === 0) {
            $base64Media = substr($base64Media, strpos($base64Media, ',') + 1);
        }
        
        // Salvar arquivo localmente com separação por usuário
        if ($userId) {
            $uploadDir = __DIR__ . "/../uploads/user_{$userId}/chat_media/";
        } else {
            // Fallback para diretório antigo se userId não fornecido
            $uploadDir = __DIR__ . '/../uploads/chat_media/';
        }
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Determinar extensão pelo mimetype
        $extension = 'bin';
        if (strpos($mimetype, 'image/jpeg') !== false || strpos($mimetype, 'image/jpg') !== false) {
            $extension = 'jpg';
        } elseif (strpos($mimetype, 'image/png') !== false) {
            $extension = 'png';
        } elseif (strpos($mimetype, 'image/gif') !== false) {
            $extension = 'gif';
        } elseif (strpos($mimetype, 'image/webp') !== false) {
            $extension = 'webp';
        } elseif (strpos($mimetype, 'video/mp4') !== false) {
            $extension = 'mp4';
        } elseif (strpos($mimetype, 'audio/ogg') !== false) {
            $extension = 'ogg';
        } elseif (strpos($mimetype, 'audio/mpeg') !== false || strpos($mimetype, 'audio/mp3') !== false) {
            $extension = 'mp3';
        } elseif (strpos($mimetype, 'audio/') !== false) {
            $extension = 'ogg'; // Fallback para áudio
        } else {
            $extension = $type; // Usar tipo detectado
        }
        
        $filename = $messageId . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        // Decodificar e salvar
        $mediaContent = base64_decode($base64Media);
        if ($mediaContent && file_put_contents($filepath, $mediaContent)) {
            // URL pública reflete a estrutura de diretórios
            if ($userId) {
                $publicUrl = "/uploads/user_{$userId}/chat_media/" . $filename;
            } else {
                $publicUrl = '/uploads/chat_media/' . $filename;
            }
            $fileSize = strlen($mediaContent);
            error_log("[CHAT_WEBHOOK] Mídia salva com sucesso: $publicUrl (tamanho: $fileSize bytes, tipo: $mimetype, userId: $userId)");
            return $publicUrl;
        }
        
        error_log("[CHAT_WEBHOOK] Erro ao salvar arquivo: $filepath (tamanho decodificado: " . strlen($mediaContent ?? '') . " bytes)");
        return null;
        
    } catch (Exception $e) {
        error_log("[CHAT_WEBHOOK] Exceção ao baixar mídia: " . $e->getMessage());
        return null;
    }
}

function downloadAndSaveMedia($media, $id, $instance)
{
    // Função legada - mantida para compatibilidade
    return null;
}

function cleanPhone(string $phone): string
{
    return preg_replace('/[^0-9]/', '', str_replace('@s.whatsapp.net', '', $phone));
}

// --- NOVA FUNÇÃO DE FOTO ---
function fetchAndSaveContactProfilePic(int $userId, string $phone, string $instance, string $apiUrl, string $apiKey): void
{
    global $pdo, $logPrefix;

    $stmt = $pdo->prepare("SELECT profile_picture_updated_at FROM contacts WHERE user_id = ? AND phone = ? LIMIT 1");
    $stmt->execute([$userId, $phone]);
    $contact = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($contact && !empty($contact['profile_picture_updated_at'])) {
        $lastUpdate = strtotime($contact['profile_picture_updated_at']);
        if ((time() - $lastUpdate) < 86400) return;
    }

    try {
        $url = rtrim($apiUrl, '/') . "/chat/fetchProfilePictureUrl/{$instance}";
        $payload = json_encode(['number' => $phone]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'apikey: ' . $apiKey]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            $picUrl = $data['profilePictureUrl'] ?? null;

            if ($picUrl) {
                $localUrl = saveContactProfilePicture($phone, $picUrl);
                if ($localUrl) {
                    $stmt = $pdo->prepare("UPDATE contacts SET profile_picture_url = ?, profile_picture_updated_at = NOW() WHERE user_id = ? AND phone = ?");
                    $stmt->execute([$localUrl, $userId, $phone]);
                    error_log("$logPrefix Foto de perfil atualizada para $phone");
                }
            }
        }
    } catch (Exception $e) {
        error_log("$logPrefix Erro ao buscar foto de perfil: " . $e->getMessage());
    }
}

function saveContactProfilePicture($phone, $url)
{
    try {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        $content = @file_get_contents($url);
        if ($content && strlen($content) > 100) {
            // Salvar na pasta padrão que o chat busca
            $dir = __DIR__ . "/../uploads/profile_pictures/";
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $filename = $cleanPhone . ".jpg";
            file_put_contents($dir . $filename, $content);
            return "/uploads/profile_pictures/$filename";
        }
    } catch (Exception $e) {
        error_log("Erro ao salvar foto de perfil: " . $e->getMessage());
    }
    return null;
}

function captureDispatchResponse(int $userId, int $conversationId, string $phone, ?string $messageText, string $messageType, int $timestamp): void
{
    global $pdo, $logPrefix;

    // Buscar último disparo para este contato nos últimos 7 dias
    $stmt = $pdo->prepare("
        SELECT dh.*, dc.id as campaign_id
        FROM dispatch_history dh
        LEFT JOIN dispatch_campaigns dc ON dh.campaign_id = dc.id
        WHERE dh.user_id = ?
        AND dh.phone = ?
        AND dh.status IN ('sent', 'delivered', 'read')
        AND dh.sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY dh.sent_at DESC
        LIMIT 1
    ");

    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    $stmt->execute([$userId, $cleanPhone]);
    $dispatch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dispatch) {
        return; // Não há disparo recente para este contato
    }

    // Calcular tempo de resposta em segundos
    $responseTime = $timestamp - strtotime($dispatch['sent_at']);

    // Verificar se é primeira resposta
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dispatch_responses WHERE dispatch_id = ?");
    $stmt->execute([$dispatch['id']]);
    $isFirstResponse = $stmt->fetchColumn() == 0;

    // Verificar se já existe esta resposta (evitar duplicatas)
    $stmt = $pdo->prepare("
        SELECT id FROM dispatch_responses 
        WHERE dispatch_id = ? 
        AND message_text = ? 
        AND received_at >= DATE_SUB(NOW(), INTERVAL 10 SECOND)
        LIMIT 1
    ");
    $stmt->execute([$dispatch['id'], $messageText]);
    if ($stmt->fetch()) {
        return; // Resposta duplicada
    }

    // Inserir resposta
    $stmt = $pdo->prepare("
        INSERT INTO dispatch_responses 
        (dispatch_id, campaign_id, user_id, contact_id, phone, message_text, 
         message_type, is_first_response, response_time_seconds, received_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?))
    ");

    $stmt->execute([
        $dispatch['id'],
        $dispatch['campaign_id'],
        $userId,
        $dispatch['contact_id'],
        $cleanPhone,
        $messageText ?? '',
        $messageType,
        $isFirstResponse ? 1 : 0,
        $responseTime,
        $timestamp
    ]);

    $responseId = $pdo->lastInsertId();

    // Atualizar contadores da campanha
    if ($dispatch['campaign_id']) {
        $stmt = $pdo->prepare("
            UPDATE dispatch_campaigns 
            SET response_count = response_count + 1 
            WHERE id = ?
        ");
        $stmt->execute([$dispatch['campaign_id']]);
    }

    // Processar sentimento em tempo real para respostas importantes
    $sentiment = 'unknown';
    try {
        require_once BASE_PATH . '/includes/sentiment_analyzer.php';
        $analyzer = new SentimentAnalyzer($pdo);
        $analyzer->processResponseSentiment($responseId);

        // Buscar sentimento processado
        $stmt = $pdo->prepare("SELECT sentiment FROM dispatch_responses WHERE id = ?");
        $stmt->execute([$responseId]);
        $sentiment = $stmt->fetchColumn() ?: 'unknown';
    } catch (Exception $e) {
        error_log("$logPrefix Erro ao processar sentimento: " . $e->getMessage());
    }

    // Criar notificações
    try {
        require_once BASE_PATH . '/includes/notification_system.php';
        $notificationSystem = new NotificationSystem($pdo, $userId);

        // Buscar dados do contato
        $stmt = $pdo->prepare("
            SELECT c.name, dr.phone, dr.message_text, dr.is_first_response, dc.name as campaign_name
            FROM dispatch_responses dr
            LEFT JOIN contacts c ON dr.contact_id = c.id
            LEFT JOIN dispatch_campaigns dc ON dr.campaign_id = dc.id
            WHERE dr.id = ?
        ");
        $stmt->execute([$responseId]);
        $responseData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($responseData) {
            $responseData['id'] = $responseId;
            $responseData['campaign_id'] = $dispatch['campaign_id'];
            $responseData['contact_name'] = $responseData['name'] ?: 'Contato';
            $responseData['sentiment'] = $sentiment;

            // Notificação de resposta negativa (alta prioridade)
            if ($sentiment === 'negative') {
                $notificationSystem->notifyNegativeResponse($responseData);
            } else {
                // Notificação de resposta normal
                $notificationSystem->notifyNewResponse($responseData);
            }
        }
    } catch (Exception $e) {
        error_log("$logPrefix Erro ao criar notificação: " . $e->getMessage());
    }

    error_log("$logPrefix Resposta capturada: dispatch_id={$dispatch['id']}, response_id={$responseId}, sentiment={$sentiment}");
}
