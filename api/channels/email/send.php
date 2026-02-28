<?php
/**
 * API para enviar email
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

// Verificar autenticação
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$to = $data['to'] ?? '';
$subject = $data['subject'] ?? '';
$body = $data['body'] ?? '';
$conversationId = $data['conversation_id'] ?? null;
$replyToMessageId = $data['reply_to_message_id'] ?? null;

// Validar campos obrigatórios
// Se for reply, subject não é obrigatório (mantém o original)
if (empty($to) || empty($body)) {
    echo json_encode([
        'success' => false,
        'error' => 'Destinatário e mensagem são obrigatórios'
    ]);
    exit;
}

// Se não for reply e não tiver subject
if (empty($replyToMessageId) && empty($subject)) {
    echo json_encode([
        'success' => false,
        'error' => 'Assunto é obrigatório para novos emails'
    ]);
    exit;
}

try {
    // Buscar canal ativo de Email
    $userId = $_SESSION['user_id'] ?? 0;
    
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
        echo json_encode([
            'success' => false,
            'error' => 'Nenhum canal de email ativo encontrado'
        ]);
        exit;
    }
    
    // Enviar via SMTP usando mail() nativo do PHP
    if ($channel['auth_method'] === 'password') {
        $fromEmail = $channel['email'];
        
        // Configurar headers
        $headers = "From: $fromEmail\r\n";
        $headers .= "Reply-To: $fromEmail\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        // Enviar email
        $mailSent = @mail($to, $subject, $body, $headers);
        
        if (!$mailSent) {
            throw new Exception('Erro ao enviar email. Verifique a configuração do servidor SMTP.');
        }
        
        // Salvar mensagem enviada no banco
        saveOutgoingMessage($pdo, $channel, $userId, $to, $subject, $body, $conversationId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Email enviado com sucesso'
        ]);
        
    } else {
        // OAuth não implementado ainda
        echo json_encode([
            'success' => false,
            'error' => 'Envio via OAuth não implementado'
        ]);
    }
    
} catch (Exception $e) {
    error_log('[Email Send] Erro: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Salvar mensagem enviada no banco
 */
function saveOutgoingMessage($pdo, $channel, $userId, $to, $subject, $body, $conversationId = null) {
    try {
        // Verificar estrutura
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
        
        // Se não tem conversation_id, criar/buscar conversa
        if (!$conversationId) {
            $whereClauses = ["contact_number = ?"];
            $whereParams = [$to];
            
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
                $convParams = [$to, $to, $channel['id']];
                
                if ($hasUserId) {
                    $convCols[] = 'user_id';
                    $convVals[] = '?';
                    $convParams[] = $userId;
                }
                
                if ($hasChannelType) {
                    $convCols[] = 'channel_type';
                    $convVals[] = "'email'";
                }
                
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
            }
        }
        
        // Salvar mensagem
        $msgCols = ['conversation_id', 'sender_type', 'message_text', 'created_at'];
        $msgVals = ['?', "'user'", '?', 'NOW()'];
        $msgParams = [$conversationId, "📧 $subject\n\n$body"];
        
        $stmt = $pdo->prepare("
            INSERT INTO messages (" . implode(', ', $msgCols) . ")
            VALUES (" . implode(', ', $msgVals) . ")
        ");
        $stmt->execute($msgParams);
        
        // Atualizar conversa
        $stmt = $pdo->prepare("
            UPDATE conversations 
            SET last_message_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$conversationId]);
        
    } catch (Exception $e) {
        error_log('[Email Send] Erro ao salvar mensagem: ' . $e->getMessage());
    }
}
