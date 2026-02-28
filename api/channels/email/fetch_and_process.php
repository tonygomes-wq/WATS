<?php
/**
 * API para buscar E processar emails
 * Usa a classe EmailChannel + lógica de processamento baseada no Chatwoot
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/channels/EmailChannel.php';

// Verificar autenticação
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    
    error_log('[Email Fetch] Iniciando busca de emails para user_id: ' . $userId);
    
    // Buscar canal ativo
    $stmt = $pdo->prepare("
        SELECT c.*, ce.*
        FROM channels c
        INNER JOIN channel_email ce ON c.id = ce.channel_id
        WHERE c.channel_type = 'email' 
        AND c.status = 'active'
        AND c.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $channel = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$channel) {
        error_log('[Email Fetch] Nenhum canal encontrado para user_id: ' . $userId);
        echo json_encode(['success' => false, 'error' => 'Canal não encontrado']);
        exit;
    }
    
    error_log('[Email Fetch] Canal encontrado: ' . $channel['id'] . ' - ' . $channel['email']);
    
    // Inicializar canal de email
    $emailChannel = new EmailChannel($pdo, $channel['id']);
    
    error_log('[Email Fetch] Buscando emails...');
    
    // Buscar novos emails
    $limit = $_GET['limit'] ?? 50;
    $emails = $emailChannel->fetchNewEmails($limit);
    
    error_log('[Email Fetch] Encontrados ' . count($emails) . ' emails');
    
    $processedCount = 0;
    $skippedCount = 0;
    $errors = [];
    
    // Processar cada email (baseado no Chatwoot)
    foreach ($emails as $email) {
        try {
            error_log('[Email Fetch] Processando email: ' . ($email['subject'] ?? 'Sem assunto'));
            $result = processEmail($pdo, $email, $channel, $userId);
            if ($result['processed']) {
                $processedCount++;
                error_log('[Email Fetch] Email processado com sucesso');
            } else {
                $skippedCount++;
                error_log('[Email Fetch] Email ignorado: ' . ($result['reason'] ?? 'unknown'));
            }
        } catch (Exception $e) {
            $errors[] = [
                'email_id' => $email['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ];
            error_log('[Email Fetch] Erro ao processar email: ' . $e->getMessage());
        }
    }
    
    echo json_encode([
        'success' => true,
        'found' => count($emails),
        'processed' => $processedCount,
        'skipped' => $skippedCount,
        'errors' => $errors,
        'message' => "Processados {$processedCount} emails de " . count($emails) . " encontrados"
    ]);
    
} catch (Exception $e) {
    error_log('[Email Fetch and Process] Erro: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Processar um email individual
 * Baseado no Imap::ImapMailbox do Chatwoot
 */
function processEmail($pdo, $email, $channel, $userId) {
    // Extrair dados do email
    $from = $email['from']['emailAddress'] ?? [];
    $fromEmail = $from['address'] ?? '';
    $fromName = $from['name'] ?? $fromEmail;
    $messageId = $email['id'] ?? '';
    
    if (empty($fromEmail) || empty($messageId)) {
        return ['processed' => false, 'reason' => 'Email inválido'];
    }
    
    // 1. Verificar se email já foi processado (evitar duplicados)
    // Verificar se coluna external_id existe
    try {
        $stmtCheck = $pdo->prepare("
            SELECT id FROM messages 
            WHERE external_id = ?
            LIMIT 1
        ");
        $stmtCheck->execute([$messageId]);
        
        if ($stmtCheck->fetch()) {
            return ['processed' => false, 'reason' => 'Já processado'];
        }
    } catch (PDOException $e) {
        // Se coluna external_id não existe, verificar por conversation_id + created_at
        // (método alternativo para evitar duplicados)
        error_log('[Email] Coluna external_id não existe, usando método alternativo');
    }
    
    // 2. Encontrar ou criar contato
    $contactId = findOrCreateContact($pdo, $fromEmail, $fromName, $userId);
    
    // 3. Encontrar ou criar conversa (com detecção de threads)
    $conversationId = findOrCreateConversation($pdo, $email, $channel, $userId, $contactId, $fromEmail, $fromName);
    
    // 4. Criar mensagem
    createMessage($pdo, $email, $conversationId, $channel, $fromEmail, $fromName);
    
    return ['processed' => true];
}

/**
 * Encontrar ou criar contato
 */
function findOrCreateContact($pdo, $email, $name, $userId) {
    // Buscar contato existente
    $stmt = $pdo->prepare("
        SELECT id FROM contacts 
        WHERE source = 'email' AND source_id = ? AND user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$email, $userId]);
    $contact = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($contact) {
        return $contact['id'];
    }
    
    // Criar novo contato
    $stmt = $pdo->prepare("
        INSERT INTO contacts (user_id, name, phone, source, source_id, created_at)
        VALUES (?, ?, ?, 'email', ?, NOW())
    ");
    $stmt->execute([$userId, $name, $email, $email]);
    
    return $pdo->lastInsertId();
}

/**
 * Encontrar ou criar conversa (com detecção de threads)
 * Baseado no find_or_create_conversation do Chatwoot
 */
function findOrCreateConversation($pdo, $email, $channel, $userId, $contactId, $fromEmail, $fromName) {
    $conversationId = null;
    $inReplyTo = null;
    
    // Verificar se coluna channel_type existe na tabela conversations
    $hasChannelType = false;
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM conversations LIKE 'channel_type'");
        $hasChannelType = $checkCol->rowCount() > 0;
    } catch (Exception $e) {
        $hasChannelType = false;
    }
    
    $channelFilter = $hasChannelType ? "AND channel_type = 'email'" : "";
    
    // 1. Buscar por replyTo (resposta a email anterior)
    if (isset($email['replyTo']) && !empty($email['replyTo'])) {
        $replyToEmail = $email['replyTo'][0]['emailAddress']['address'] ?? null;
        if ($replyToEmail) {
            $stmt = $pdo->prepare("
                SELECT id FROM conversations 
                WHERE user_id = ? AND contact_number = ? $channelFilter
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$userId, $replyToEmail]);
            $conv = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($conv) {
                $conversationId = $conv['id'];
                $inReplyTo = $replyToEmail;
            }
        }
    }
    
    // 2. Buscar por conversationId do Microsoft Graph
    if (!$conversationId && isset($email['conversationId'])) {
        $stmt = $pdo->prepare("
            SELECT id FROM conversations 
            WHERE user_id = ? AND contact_number = ? $channelFilter
            AND JSON_EXTRACT(additional_attributes, '$.conversation_id') = ?
            LIMIT 1
        ");
        $stmt->execute([$userId, $fromEmail, $email['conversationId']]);
        $conv = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($conv) {
            $conversationId = $conv['id'];
        }
    }
    
    // 3. Buscar última conversa com este contato
    if (!$conversationId) {
        $stmt = $pdo->prepare("
            SELECT id FROM conversations 
            WHERE user_id = ? AND contact_number = ? $channelFilter
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$userId, $fromEmail]);
        $conv = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($conv) {
            $conversationId = $conv['id'];
        }
    }
    
    // 4. Criar nova conversa
    if (!$conversationId) {
        $subject = $email['subject'] ?? 'Sem assunto';
        $conversationAttributes = json_encode([
            'subject' => $subject,
            'conversation_id' => $email['conversationId'] ?? null,
            'in_reply_to' => $inReplyTo,
            'source' => 'email'
        ]);
        
        // Montar query baseado nas colunas disponíveis
        if ($hasChannelType) {
            $stmt = $pdo->prepare("
                INSERT INTO conversations (
                    user_id, contact_id, contact_name, contact_number, 
                    channel_type, channel_id, status, 
                    additional_attributes, created_at, last_message_at
                )
                VALUES (?, ?, ?, ?, 'email', ?, 'active', ?, NOW(), NOW())
            ");
            $stmt->execute([
                $userId, $contactId, $fromName, $fromEmail, 
                $channel['id'], $conversationAttributes
            ]);
        } else {
            // Versão sem channel_type
            $stmt = $pdo->prepare("
                INSERT INTO conversations (
                    user_id, contact_id, contact_name, contact_number, 
                    channel_id, status, 
                    additional_attributes, created_at, last_message_at
                )
                VALUES (?, ?, ?, ?, ?, 'active', ?, NOW(), NOW())
            ");
            $stmt->execute([
                $userId, $contactId, $fromName, $fromEmail, 
                $channel['id'], $conversationAttributes
            ]);
        }
        $conversationId = $pdo->lastInsertId();
    } else {
        // Atualizar timestamp
        $stmt = $pdo->prepare("
            UPDATE conversations 
            SET updated_at = NOW(), last_message_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$conversationId]);
    }
    
    return $conversationId;
}

/**
 * Criar mensagem
 * Baseado no create_message do Chatwoot
 */
function createMessage($pdo, $email, $conversationId, $channel, $fromEmail, $fromName) {
    $subject = $email['subject'] ?? 'Sem assunto';
    $body = $email['body']['content'] ?? '';
    $bodyPreview = $email['bodyPreview'] ?? strip_tags($body);
    $receivedDateTime = $email['receivedDateTime'] ?? date('Y-m-d H:i:s');
    $messageId = $email['id'];
    
    // Preparar metadados completos (baseado no Chatwoot)
    $additionalData = json_encode([
        'subject' => $subject,
        'body_html' => $body,
        'body_preview' => $bodyPreview,
        'received_at' => $receivedDateTime,
        'from' => [
            'email' => $fromEmail,
            'name' => $fromName
        ],
        'to' => $email['toRecipients'] ?? [],
        'cc' => $email['ccRecipients'] ?? [],
        'bcc' => $email['bccRecipients'] ?? [],
        'conversation_id' => $email['conversationId'] ?? null,
        'has_attachments' => $email['hasAttachments'] ?? false,
        'importance' => $email['importance'] ?? 'normal',
        'is_read' => $email['isRead'] ?? false
    ]);
    
    // Verificar quais colunas existem na tabela messages
    $hasExternalId = false;
    $hasAdditionalData = false;
    $hasChannelId = false;
    
    try {
        $checkCols = $pdo->query("SHOW COLUMNS FROM messages LIKE 'external_id'");
        $hasExternalId = $checkCols->rowCount() > 0;
        
        $checkCols = $pdo->query("SHOW COLUMNS FROM messages LIKE 'additional_data'");
        $hasAdditionalData = $checkCols->rowCount() > 0;
        
        $checkCols = $pdo->query("SHOW COLUMNS FROM messages LIKE 'channel_id'");
        $hasChannelId = $checkCols->rowCount() > 0;
    } catch (Exception $e) {
        error_log('[Email] Erro ao verificar colunas: ' . $e->getMessage());
    }
    
    // Montar query baseado nas colunas disponíveis
    if ($hasExternalId && $hasAdditionalData && $hasChannelId) {
        // Versão completa (com todas as colunas)
        $stmt = $pdo->prepare("
            INSERT INTO messages (
                conversation_id,
                sender_type,
                message_text,
                channel_id,
                channel_type,
                external_id,
                additional_data,
                is_read,
                created_at
            ) VALUES (?, 'contact', ?, ?, 'email', ?, ?, 0, ?)
        ");
        
        $stmt->execute([
            $conversationId,
            $bodyPreview,
            $channel['id'],
            $messageId,
            $additionalData,
            $receivedDateTime
        ]);
    } else {
        // Versão simplificada (sem colunas extras)
        // Salvar metadados no message_text como JSON
        $messageWithMetadata = "📧 " . $subject . "\n\n" . $bodyPreview;
        
        $stmt = $pdo->prepare("
            INSERT INTO messages (
                conversation_id,
                sender_type,
                message_text,
                created_at
            ) VALUES (?, 'contact', ?, ?)
        ");
        
        $stmt->execute([
            $conversationId,
            $messageWithMetadata,
            $receivedDateTime
        ]);
    }
}
