<?php
/**
 * API SIMPLIFICADA para buscar e salvar emails
 * Versão robusta que funciona com qualquer estrutura de banco
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    
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
        echo json_encode(['success' => false, 'error' => 'Canal não encontrado']);
        exit;
    }
    
    // Verificar estrutura do banco
    $hasChannelType = false;
    $hasContactId = false;
    $hasUserId = false;
    
    try {
        $check = $pdo->query("SHOW COLUMNS FROM conversations LIKE 'channel_type'");
        $hasChannelType = $check->rowCount() > 0;
        
        $check = $pdo->query("SHOW COLUMNS FROM conversations LIKE 'contact_id'");
        $hasContactId = $check->rowCount() > 0;
        
        $check = $pdo->query("SHOW COLUMNS FROM conversations LIKE 'user_id'");
        $hasUserId = $check->rowCount() > 0;
    } catch (Exception $e) {
        // Ignorar
    }
    
    // Buscar emails via IMAP
    if (!function_exists('imap_open')) {
        echo json_encode(['success' => false, 'error' => 'IMAP não disponível']);
        exit;
    }
    
    $mailbox = sprintf(
        '{%s:%d/imap/%s}INBOX',
        $channel['imap_host'],
        $channel['imap_port'],
        strtolower($channel['imap_encryption'])
    );
    
    $imap = @imap_open($mailbox, $channel['email'], $channel['password']);
    
    if (!$imap) {
        echo json_encode(['success' => false, 'error' => 'Erro ao conectar: ' . imap_last_error()]);
        exit;
    }
    
    // Buscar últimos 10 emails não lidos
    $limit = min((int)($_GET['limit'] ?? 10), 50);
    $unreadEmails = imap_search($imap, 'UNSEEN');
    
    if (!$unreadEmails) {
        imap_close($imap);
        echo json_encode([
            'success' => true,
            'processed' => 0,
            'message' => 'Nenhum email novo encontrado'
        ]);
        exit;
    }
    
    rsort($unreadEmails);
    $unreadEmails = array_slice($unreadEmails, 0, $limit);
    
    $processed = 0;
    $errors = [];
    
    foreach ($unreadEmails as $emailNum) {
        try {
            $header = imap_headerinfo($imap, $emailNum);
            $body = imap_fetchbody($imap, $emailNum, 1);
            
            if (!$header || !isset($header->from[0])) {
                continue;
            }
            
            $fromEmail = $header->from[0]->mailbox . '@' . $header->from[0]->host;
            $fromName = $header->from[0]->personal ?? $fromEmail;
            $subject = isset($header->subject) ? imap_utf8($header->subject) : 'Sem assunto';
            $messageId = $header->message_id ?? uniqid('email_');
            $receivedDate = date('Y-m-d H:i:s', $header->udate);
            
            // Decodificar nome se necessário
            if (preg_match('/=\?.*\?=/', $fromName)) {
                $decoded = imap_mime_header_decode($fromName);
                $fromName = $decoded[0]->text ?? $fromName;
            }
            
            // 1. Criar/buscar contato
            $contactId = null;
            if ($hasContactId) {
                $stmt = $pdo->prepare("
                    SELECT id FROM contacts 
                    WHERE phone = ? 
                    LIMIT 1
                ");
                $stmt->execute([$fromEmail]);
                $contact = $stmt->fetch();
                
                if (!$contact) {
                    $insertCols = ['name', 'phone', 'created_at'];
                    $insertVals = ['?', '?', 'NOW()'];
                    $insertParams = [$fromName, $fromEmail];
                    
                    if ($hasUserId) {
                        $insertCols[] = 'user_id';
                        $insertVals[] = '?';
                        $insertParams[] = $userId;
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO contacts (" . implode(', ', $insertCols) . ")
                        VALUES (" . implode(', ', $insertVals) . ")
                    ");
                    $stmt->execute($insertParams);
                    $contactId = $pdo->lastInsertId();
                } else {
                    $contactId = $contact['id'];
                }
            }
            
            // 2. Criar/buscar conversa
            $whereClauses = ["contact_number = ?"];
            $whereParams = [$fromEmail];
            
            if ($hasUserId) {
                $whereClauses[] = "user_id = ?";
                $whereParams[] = $userId;
            }
            
            if ($hasChannelType) {
                $whereClauses[] = "channel_type = 'email'";
            }
            
            $stmt = $pdo->prepare("
                SELECT id FROM conversations 
                WHERE " . implode(' AND ', $whereClauses) . "
                LIMIT 1
            ");
            $stmt->execute($whereParams);
            $conv = $stmt->fetch();
            
            if (!$conv) {
                // Criar nova conversa
                $convCols = ['contact_name', 'contact_number', 'channel_id', 'status', 'created_at', 'last_message_at'];
                $convVals = ['?', '?', '?', "'active'", 'NOW()', 'NOW()'];
                $convParams = [$fromName, $fromEmail, $channel['id']];
                
                if ($hasUserId) {
                    $convCols[] = 'user_id';
                    $convVals[] = '?';
                    $convParams[] = $userId;
                }
                
                if ($hasContactId && $contactId) {
                    $convCols[] = 'contact_id';
                    $convVals[] = '?';
                    $convParams[] = $contactId;
                }
                
                if ($hasChannelType) {
                    $convCols[] = 'channel_type';
                    $convVals[] = "'email'";
                }
                
                // Adicionar assunto em additional_attributes
                $convCols[] = 'additional_attributes';
                $convVals[] = '?';
                $convParams[] = json_encode(['subject' => $subject]);
                
                $stmt = $pdo->prepare("
                    INSERT INTO conversations (" . implode(', ', $convCols) . ")
                    VALUES (" . implode(', ', $convVals) . ")
                ");
                $stmt->execute($convParams);
                $conversationId = $pdo->lastInsertId();
            } else {
                $conversationId = $conv['id'];
                
                // Atualizar última mensagem
                $stmt = $pdo->prepare("
                    UPDATE conversations 
                    SET last_message_at = NOW(), updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$conversationId]);
            }
            
            // 3. Criar mensagem
            $bodyPreview = mb_substr(strip_tags($body), 0, 200);
            
            $msgCols = ['conversation_id', 'sender_type', 'message_text', 'created_at'];
            $msgVals = ['?', "'contact'", '?', '?'];
            $msgParams = [$conversationId, "📧 $subject\n\n$bodyPreview", $receivedDate];
            
            $stmt = $pdo->prepare("
                INSERT INTO messages (" . implode(', ', $msgCols) . ")
                VALUES (" . implode(', ', $msgVals) . ")
            ");
            $stmt->execute($msgParams);
            
            $processed++;
            
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
            error_log('[Email Simple] Erro: ' . $e->getMessage());
        }
    }
    
    imap_close($imap);
    
    echo json_encode([
        'success' => true,
        'processed' => $processed,
        'found' => count($unreadEmails),
        'errors' => $errors,
        'message' => "Processados $processed de " . count($unreadEmails) . " emails"
    ]);
    
} catch (Exception $e) {
    error_log('[Email Simple] Erro fatal: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
