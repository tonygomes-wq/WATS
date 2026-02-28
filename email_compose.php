<?php
/**
 * COMPOR NOVO EMAIL
 * Modal/página para escrever novo email
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$page_title = 'Novo Email';
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

.compose-container {
    max-width: 800px;
    margin: 40px auto;
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    overflow: hidden;
}

.compose-header {
    padding: 16px 20px;
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.compose-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--text-primary);
}

.compose-close {
    background: transparent;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 20px;
    padding: 4px 8px;
}

.compose-close:hover {
    color: var(--text-primary);
}

.compose-body {
    padding: 20px;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    color: var(--text-secondary);
    font-size: 14px;
}

.form-group input,
.form-group textarea {
    width: 100%;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 10px 12px;
    color: var(--text-primary);
    font-family: inherit;
    font-size: 14px;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--gmail-blue);
}

.form-group textarea {
    min-height: 300px;
    resize: vertical;
}

.compose-footer {
    padding: 16px 20px;
    background: var(--bg-secondary);
    border-top: 1px solid var(--border-color);
    display: flex;
    gap: 12px;
}

.btn {
    padding: 10px 24px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}

.btn-primary {
    background: var(--gmail-blue);
    color: white;
}

.btn-primary:hover {
    background: #3367d6;
}

.btn-secondary {
    background: transparent;
    border: 1px solid var(--border-color);
    color: var(--text-primary);
}

.btn-secondary:hover {
    background: var(--bg-hover);
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>

<div class="compose-container">
    <div class="compose-header">
        <div class="compose-title">Nova mensagem</div>
        <button class="compose-close" onclick="window.location.href='email_chat.php'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <div class="compose-body">
        <form id="compose-form">
            <div class="form-group">
                <label for="to">Para</label>
                <input type="email" id="to" name="to" placeholder="destinatario@exemplo.com" required>
            </div>
            
            <div class="form-group">
                <label for="subject">Assunto</label>
                <input type="text" id="subject" name="subject" placeholder="Assunto do email" required>
            </div>
            
            <div class="form-group">
                <label for="body">Mensagem</label>
                <textarea id="body" name="body" placeholder="Digite sua mensagem..." required></textarea>
            </div>
        </form>
    </div>
    
    <div class="compose-footer">
        <button class="btn btn-primary" onclick="sendEmail()" id="send-btn">
            <i class="fas fa-paper-plane"></i>
            Enviar
        </button>
        <button class="btn btn-secondary" onclick="window.location.href='email_chat.php'">
            Cancelar
        </button>
    </div>
</div>

<script>
async function sendEmail() {
    const form = document.getElementById('compose-form');
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const to = document.getElementById('to').value.trim();
    const subject = document.getElementById('subject').value.trim();
    const body = document.getElementById('body').value.trim();
    
    const sendBtn = document.getElementById('send-btn');
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
    
    try {
        const response = await fetch('api/channels/email/send.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                to: to,
                subject: subject,
                body: body
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Email enviado com sucesso!');
            window.location.href = 'email_chat.php';
        } else {
            alert('Erro ao enviar email: ' + data.error);
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar';
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao enviar email');
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar';
    }
}

// Atalho Ctrl+Enter para enviar
document.getElementById('body').addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'Enter') {
        sendEmail();
    }
});
</script>

<?php require_once 'includes/footer_spa.php'; ?>
