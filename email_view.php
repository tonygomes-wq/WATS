<?php
/**
 * VISUALIZAÇÃO DE EMAIL
 * Página para visualizar detalhes completos de um email
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$conversationId = $_GET['id'] ?? 0;

if (!$conversationId) {
    header('Location: email_chat.php');
    exit;
}

// Buscar conversa
$hasChannelType = false;
$hasContactId = false;

try {
    $checkCol = $pdo->query("SHOW COLUMNS FROM conversations LIKE 'channel_type'");
    $hasChannelType = $checkCol->rowCount() > 0;
    
    $checkCol = $pdo->query("SHOW COLUMNS FROM conversations LIKE 'contact_id'");
    $hasContactId = $checkCol->rowCount() > 0;
} catch (Exception $e) {
    // Ignorar erro
}

$contactJoin = $hasContactId ? "LEFT JOIN contacts c ON conv.contact_id = c.id" : "";

$sql = "
    SELECT 
        conv.*
        " . ($hasContactId ? ", c.name as contact_db_name, c.phone as contact_email" : "") . "
    FROM conversations conv
    $contactJoin
    WHERE conv.id = ?
";

$params = [$conversationId];

if ($hasChannelType) {
    $sql .= " AND conv.channel_type = 'email'";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$conversation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conversation) {
    header('Location: email_chat.php');
    exit;
}

// Buscar mensagens da conversa
$stmt = $pdo->prepare("
    SELECT * FROM messages 
    WHERE conversation_id = ? 
    ORDER BY created_at ASC
");
$stmt->execute([$conversationId]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Extrair assunto
$attributes = json_decode($conversation['additional_attributes'] ?? '{}', true);
$subject = $attributes['subject'] ?? 'Sem assunto';

$page_title = 'Email - ' . htmlspecialchars($subject);

require_once 'includes/header_spa.php';
?>

<style>
:root {
    --gmail-red: #ea4335;
    --gmail-blue: #4285f4;
    --bg-primary: #1e293b;
    --bg-secondary: #0f172a;
    --bg-hover: #334155;
    --text-primary: #f1f5f9;
    --text-secondary: #94a3b8;
    --border-color: #334155;
}

.email-view-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    background: var(--bg-secondary);
    min-height: calc(100vh - 60px);
}

.email-header {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.email-actions-bar {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border-color);
}

.action-btn {
    padding: 8px 16px;
    background: transparent;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    color: var(--text-primary);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}

.action-btn:hover {
    background: var(--bg-hover);
}

.action-btn.primary {
    background: var(--gmail-blue);
    border-color: var(--gmail-blue);
}

.action-btn.primary:hover {
    background: #3367d6;
}

.email-subject {
    font-size: 24px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 16px;
}

.email-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--text-secondary);
    font-size: 14px;
}

.email-from {
    font-weight: 600;
    color: var(--text-primary);
}

.email-messages {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    overflow: hidden;
}

.message-item {
    padding: 20px;
    border-bottom: 1px solid var(--border-color);
}

.message-item:last-child {
    border-bottom: none;
}

.message-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.message-sender {
    font-weight: 600;
    color: var(--text-primary);
}

.message-date {
    color: var(--text-secondary);
    font-size: 13px;
}

.message-body {
    color: var(--text-primary);
    line-height: 1.6;
    white-space: pre-wrap;
}

.reply-box {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
}

.reply-box textarea {
    width: 100%;
    min-height: 150px;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 12px;
    color: var(--text-primary);
    font-family: inherit;
    font-size: 14px;
    resize: vertical;
}

.reply-box textarea:focus {
    outline: none;
    border-color: var(--gmail-blue);
}

.reply-actions {
    display: flex;
    gap: 12px;
    margin-top: 12px;
}
</style>

<div class="email-view-container">
    <div class="email-header">
        <div class="email-actions-bar">
            <button class="action-btn" onclick="window.location.href='email_chat.php'">
                <i class="fas fa-arrow-left"></i>
                Voltar
            </button>
            <button class="action-btn" onclick="testConnection()" id="test-btn">
                <i class="fas fa-plug"></i>
                Testar Conexão
            </button>
            <button class="action-btn primary" onclick="showReplyBox()">
                <i class="fas fa-reply"></i>
                Responder
            </button>
            <button class="action-btn" onclick="deleteEmail()">
                <i class="far fa-trash-alt"></i>
                Excluir
            </button>
        </div>
        
        <div class="email-subject"><?php echo htmlspecialchars($subject); ?></div>
        
        <div class="email-meta">
            <span class="email-from"><?php echo htmlspecialchars($conversation['contact_name'] ?? 'Sem nome'); ?></span>
            <span>&lt;<?php echo htmlspecialchars($conversation['contact_number']); ?>&gt;</span>
            <span>•</span>
            <span><?php echo date('d/m/Y H:i', strtotime($conversation['created_at'])); ?></span>
        </div>
    </div>
    
    <div class="email-messages">
        <?php foreach ($messages as $message): ?>
            <div class="message-item">
                <div class="message-header">
                    <span class="message-sender">
                        <?php echo $message['sender_type'] === 'user' ? 'Você' : htmlspecialchars($conversation['contact_name']); ?>
                    </span>
                    <span class="message-date">
                        <?php echo date('d/m/Y H:i', strtotime($message['created_at'])); ?>
                    </span>
                </div>
                <div class="message-body">
                    <?php 
                    // Tentar extrair corpo do additional_data
                    if (!empty($message['additional_data'])) {
                        $data = json_decode($message['additional_data'], true);
                        $body = $data['body_preview'] ?? $message['message_text'];
                    } else {
                        $body = $message['message_text'];
                    }
                    echo nl2br(htmlspecialchars($body)); 
                    ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="reply-box" id="reply-box" style="display: none;">
        <textarea id="reply-text" placeholder="Digite sua resposta..."></textarea>
        <div class="reply-actions">
            <button class="action-btn primary" onclick="sendReply()">
                <i class="fas fa-paper-plane"></i>
                Enviar
            </button>
            <button class="action-btn" onclick="hideReplyBox()">
                Cancelar
            </button>
        </div>
    </div>
</div>

<script>
const conversationId = <?php echo $conversationId; ?>;
const contactEmail = '<?php echo htmlspecialchars($conversation['contact_number']); ?>';
const subject = '<?php echo htmlspecialchars($subject); ?>';

function showReplyBox() {
    document.getElementById('reply-box').style.display = 'block';
    document.getElementById('reply-text').focus();
}

function hideReplyBox() {
    document.getElementById('reply-box').style.display = 'none';
    document.getElementById('reply-text').value = '';
}

async function sendReply() {
    const replyText = document.getElementById('reply-text').value.trim();
    
    if (!replyText) {
        alert('Digite uma mensagem');
        return;
    }
    
    try {
        const response = await fetch('api/channels/email/send.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                to: contactEmail,
                subject: 'Re: ' + subject,
                body: replyText,
                conversation_id: conversationId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Resposta enviada com sucesso!');
            location.reload();
        } else {
            alert('Erro ao enviar resposta: ' + data.error);
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao enviar resposta');
    }
}

async function deleteEmail() {
    if (!confirm('Deseja realmente excluir esta conversa?')) {
        return;
    }
    
    try {
        const response = await fetch('api/email_delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id: conversationId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            window.location.href = 'email_chat.php';
        } else {
            alert('Erro ao excluir: ' + data.error);
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao excluir email');
    }
}
</script>

<?php require_once 'includes/footer_spa.php'; ?>
