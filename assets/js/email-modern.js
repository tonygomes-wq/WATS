/**
 * EMAIL MODERN - JavaScript
 * Funcionalidades da interface de email estilo Gmail
 */

let emails = [];
let currentEmail = null;
let currentFolder = 'inbox';
let currentPage = 1;
let itemsPerPage = 50;

// Inicializar
document.addEventListener('DOMContentLoaded', () => {
    loadEmails();
    
    // Auto-refresh a cada 30 segundos
    setInterval(loadEmails, 30000);
});

// Carregar emails
async function loadEmails() {
    try {
        const response = await fetch('api/email_conversations.php');
        const data = await response.json();
        
        if (data.success && data.conversations) {
            emails = data.conversations;
            updateCounts();
            renderEmails();
        } else {
            showEmptyState('Nenhum email encontrado');
        }
    } catch (error) {
        console.error('Erro ao carregar emails:', error);
        showEmptyState('Erro ao carregar emails');
    }
}

// Atualizar contadores
function updateCounts() {
    const inboxCount = emails.filter(e => !e.is_read).length;
    document.getElementById('inbox-count').textContent = inboxCount;
}

// Renderizar lista de emails
function renderEmails() {
    const container = document.getElementById('email-items');
    
    // Filtrar por pasta
    let filteredEmails = filterEmailsByFolder(emails, currentFolder);
    
    // Filtrar por busca
    const searchQuery = document.getElementById('email-search-input').value.toLowerCase();
    if (searchQuery) {
        filteredEmails = filteredEmails.filter(email => 
            (email.contact_name || '').toLowerCase().includes(searchQuery) ||
            (email.email_subject || '').toLowerCase().includes(searchQuery) ||
            (email.last_message_text || '').toLowerCase().includes(searchQuery)
        );
    }
    
    // Paginação
    const totalEmails = filteredEmails.length;
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = Math.min(startIndex + itemsPerPage, totalEmails);
    const paginatedEmails = filteredEmails.slice(startIndex, endIndex);
    
    // Atualizar info de paginação
    document.getElementById('pagination-info').textContent = 
        `${startIndex + 1}-${endIndex} de ${totalEmails}`;
    
    // Habilitar/desabilitar botões
    document.getElementById('prev-btn').disabled = currentPage === 1;
    document.getElementById('next-btn').disabled = endIndex >= totalEmails;
    
    // Renderizar
    if (paginatedEmails.length === 0) {
        showEmptyState('Nenhum email nesta pasta');
        return;
    }
    
    container.innerHTML = '';
    
    paginatedEmails.forEach(email => {
        const div = document.createElement('div');
        div.className = `email-item ${!email.is_read ? 'unread' : ''} ${currentEmail && currentEmail.id === email.id ? 'active' : ''}`;
        div.dataset.emailId = email.id;
        
        const date = formatEmailDate(email.last_message_time || email.created_at);
        const sender = email.contact_name || email.phone || 'Sem nome';
        const subject = email.email_subject || 'Sem assunto';
        const preview = email.last_message_text || '';
        
        div.innerHTML = `
            <input type="checkbox" onclick="event.stopPropagation()" onchange="toggleEmailSelection(${email.id})">
            <button class="star ${email.is_starred ? 'starred' : ''}" onclick="event.stopPropagation(); toggleStar(${email.id})">
                <i class="${email.is_starred ? 'fas' : 'far'} fa-star"></i>
            </button>
            <div class="email-sender">${escapeHtml(sender)}</div>
            <div class="email-content">
                <span class="email-subject">${escapeHtml(subject)}</span>
                <span class="email-preview"> - ${escapeHtml(preview)}</span>
            </div>
            <div class="email-date">${date}</div>
        `;
        
        div.onclick = () => openEmail(email);
        container.appendChild(div);
    });
}

// Filtrar emails por pasta
function filterEmailsByFolder(emails, folder) {
    switch (folder) {
        case 'inbox':
            return emails.filter(e => !e.is_archived && !e.is_deleted);
        case 'starred':
            return emails.filter(e => e.is_starred);
        case 'sent':
            return emails.filter(e => e.is_sent);
        case 'drafts':
            return emails.filter(e => e.is_draft);
        case 'trash':
            return emails.filter(e => e.is_deleted);
        default:
            return emails;
    }
}

// Abrir email
async function openEmail(email) {
    currentEmail = email;
    
    // Marcar como ativo na lista
    document.querySelectorAll('.email-item').forEach(el => el.classList.remove('active'));
    document.querySelector(`[data-email-id="${email.id}"]`)?.classList.add('active');
    
    // Mostrar viewer
    const viewer = document.getElementById('email-viewer');
    viewer.classList.add('active');
    
    // Carregar mensagens
    viewer.innerHTML = '<div class="email-loading"><i class="fas fa-spinner fa-spin"></i><br><br>Carregando email...</div>';
    
    try {
        const response = await fetch(`api/email_messages.php?conversation_id=${email.id}`);
        const data = await response.json();
        
        if (data.success && data.messages && data.messages.length > 0) {
            renderEmailViewer(email, data.messages);
            
            // Marcar como lido
            if (!email.is_read) {
                markAsRead(email.id);
            }
        } else {
            viewer.innerHTML = '<div class="email-loading">Nenhuma mensagem encontrada</div>';
        }
    } catch (error) {
        console.error('Erro ao carregar mensagens:', error);
        viewer.innerHTML = '<div class="email-loading">Erro ao carregar mensagens</div>';
    }
}

// Renderizar visualizador de email
function renderEmailViewer(email, messages) {
    const viewer = document.getElementById('email-viewer');
    
    // Pegar primeira mensagem (email original)
    const firstMessage = messages[0];
    
    const senderName = email.contact_name || email.phone || 'Sem nome';
    const senderEmail = email.phone || '';
    const subject = email.email_subject || firstMessage.email_subject || 'Sem assunto';
    const date = formatFullDate(firstMessage.created_at);
    const initials = getInitials(senderName);
    
    viewer.innerHTML = `
        <div class="email-viewer-header">
            <button class="back-btn" onclick="closeEmailViewer()">
                <i class="fas fa-arrow-left"></i>
            </button>
            <div class="email-actions-top">
                <button onclick="archiveEmail(${email.id})" title="Arquivar">
                    <i class="fas fa-archive"></i>
                </button>
                <button onclick="deleteEmail(${email.id})" title="Excluir">
                    <i class="fas fa-trash"></i>
                </button>
                <button onclick="markAsUnread(${email.id})" title="Marcar como não lida">
                    <i class="fas fa-envelope"></i>
                </button>
                <button onclick="toggleStar(${email.id})" title="Adicionar estrela">
                    <i class="${email.is_starred ? 'fas' : 'far'} fa-star"></i>
                </button>
            </div>
        </div>
        
        <div class="email-viewer-content">
            <h2 class="email-title">${escapeHtml(subject)}</h2>
            
            <div class="email-from">
                <div class="avatar">${initials}</div>
                <div class="sender-info">
                    <div class="sender-name">${escapeHtml(senderName)}</div>
                    <div class="sender-email">&lt;${escapeHtml(senderEmail)}&gt;</div>
                </div>
                <div class="email-time">${date}</div>
            </div>
            
            <div class="email-body">
                ${formatEmailBody(firstMessage.message_text)}
            </div>
            
            ${messages.length > 1 ? renderEmailThread(messages.slice(1)) : ''}
            
            <div class="email-reply-actions">
                <button class="btn-reply" onclick="replyEmail(${email.id})">
                    <i class="fas fa-reply"></i> Responder
                </button>
                <button class="btn-forward" onclick="forwardEmail(${email.id})">
                    <i class="fas fa-share"></i> Encaminhar
                </button>
            </div>
        </div>
    `;
}

// Renderizar thread de emails
function renderEmailThread(messages) {
    let html = '<div class="email-thread">';
    
    messages.forEach(msg => {
        const date = formatFullDate(msg.created_at);
        const senderName = msg.sender_name || 'Você';
        const initials = getInitials(senderName);
        
        html += `
            <div class="email-thread-item">
                <div class="email-from">
                    <div class="avatar">${initials}</div>
                    <div class="sender-info">
                        <div class="sender-name">${escapeHtml(senderName)}</div>
                    </div>
                    <div class="email-time">${date}</div>
                </div>
                <div class="email-body">
                    ${formatEmailBody(msg.message_text)}
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    return html;
}

// Fechar visualizador (mobile)
function closeEmailViewer() {
    document.getElementById('email-viewer').classList.remove('active');
}

// Filtrar por pasta
function filterByFolder(folder) {
    currentFolder = folder;
    currentPage = 1;
    
    // Atualizar UI
    document.querySelectorAll('.folder').forEach(el => el.classList.remove('active'));
    document.querySelector(`[data-folder="${folder}"]`)?.classList.add('active');
    
    renderEmails();
    
    // Prevenir navegação padrão
    return false;
}

// Buscar emails
function searchEmails() {
    currentPage = 1;
    renderEmails();
}

// Paginação
function previousPage() {
    if (currentPage > 1) {
        currentPage--;
        renderEmails();
    }
}

function nextPage() {
    currentPage++;
    renderEmails();
}

// Atualizar emails
function refreshEmails() {
    const btn = document.querySelector('button[onclick="refreshEmails()"]');
    if (btn) {
        const icon = btn.querySelector('i');
        if (icon) icon.classList.add('fa-spin');
    }
    
    loadEmails().then(() => {
        if (btn) {
            const icon = btn.querySelector('i');
            if (icon) icon.classList.remove('fa-spin');
        }
    });
    
    return false;
}

// Marcar como lido
async function markAsRead(emailId) {
    try {
        await fetch(`api/email_mark_read.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ conversation_id: emailId })
        });
        
        // Atualizar localmente
        const email = emails.find(e => e.id === emailId);
        if (email) {
            email.is_read = true;
            updateCounts();
            renderEmails();
        }
    } catch (error) {
        console.error('Erro ao marcar como lido:', error);
    }
}

// Marcar como não lido
async function markAsUnread(emailId) {
    try {
        await fetch(`api/email_mark_unread.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ conversation_id: emailId })
        });
        
        // Atualizar localmente
        const email = emails.find(e => e.id === emailId);
        if (email) {
            email.is_read = false;
            updateCounts();
            renderEmails();
        }
    } catch (error) {
        console.error('Erro ao marcar como não lido:', error);
    }
}

// Toggle estrela
async function toggleStar(emailId) {
    const email = emails.find(e => e.id === emailId);
    if (!email) return;
    
    email.is_starred = !email.is_starred;
    renderEmails();
    
    try {
        await fetch(`api/email_toggle_star.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ conversation_id: emailId, starred: email.is_starred })
        });
    } catch (error) {
        console.error('Erro ao alternar estrela:', error);
        email.is_starred = !email.is_starred;
        renderEmails();
    }
}

// Arquivar email
async function archiveEmail(emailId) {
    if (!confirm('Arquivar este email?')) return;
    
    try {
        await fetch(`api/email_archive.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ conversation_id: emailId })
        });
        
        loadEmails();
        closeEmailViewer();
    } catch (error) {
        console.error('Erro ao arquivar:', error);
        alert('Erro ao arquivar email');
    }
}

// Deletar email
async function deleteEmail(emailId) {
    if (!confirm('Mover este email para a lixeira?')) return;
    
    try {
        await fetch(`api/email_delete.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ conversation_id: emailId })
        });
        
        loadEmails();
        closeEmailViewer();
    } catch (error) {
        console.error('Erro ao deletar:', error);
        alert('Erro ao deletar email');
    }
}

// Compose
function composeEmail() {
    document.getElementById('compose-modal').classList.remove('hidden');
    document.getElementById('compose-to').value = '';
    document.getElementById('compose-subject').value = '';
    document.getElementById('compose-message').value = '';
    document.getElementById('compose-cc').value = '';
    document.getElementById('compose-bcc').value = '';
    document.getElementById('compose-cc-field').classList.add('hidden');
    document.getElementById('compose-bcc-field').classList.add('hidden');
    document.getElementById('compose-to').focus();
    return false;
}

function closeCompose() {
    const to = document.getElementById('compose-to').value;
    const subject = document.getElementById('compose-subject').value;
    const message = document.getElementById('compose-message').value;
    
    // Perguntar apenas se houver conteúdo
    if (to || subject || message) {
        if (!confirm('Descartar rascunho?')) {
            return false;
        }
    }
    
    document.getElementById('compose-modal').classList.add('hidden');
    return false;
}

function minimizeCompose() {
    const modal = document.getElementById('compose-modal');
    modal.style.maxHeight = '48px';
    modal.style.overflow = 'hidden';
    return false;
}

function maximizeCompose() {
    const modal = document.getElementById('compose-modal');
    if (modal.style.maxHeight === '100vh') {
        modal.style.maxHeight = '690px';
        modal.style.width = '560px';
        modal.style.right = '48px';
        modal.style.borderRadius = '8px 8px 0 0';
    } else {
        modal.style.maxHeight = '100vh';
        modal.style.width = '100vw';
        modal.style.right = '0';
        modal.style.borderRadius = '0';
    }
    return false;
}

function toggleCcBcc() {
    document.getElementById('compose-cc-field').classList.toggle('hidden');
    document.getElementById('compose-bcc-field').classList.toggle('hidden');
    return false;
}

// Funções da toolbar
function formatText() {
    alert('Formatação de texto em desenvolvimento');
    return false;
}

function attachFile() {
    alert('Anexar arquivo em desenvolvimento');
    return false;
}

function insertLink() {
    const url = prompt('Digite a URL:');
    if (url) {
        const textarea = document.getElementById('compose-message');
        const text = textarea.value;
        const cursorPos = textarea.selectionStart;
        const linkText = `[Link](${url})`;
        textarea.value = text.substring(0, cursorPos) + linkText + text.substring(cursorPos);
    }
    return false;
}

function insertEmoji() {
    alert('Inserir emoji em desenvolvimento');
    return false;
}

function insertImage() {
    alert('Inserir imagem em desenvolvimento');
    return false;
}

function showMoreOptions() {
    alert('Mais opções em desenvolvimento');
    return false;
}

function showSendOptions() {
    alert('Opções de envio:\n- Enviar agora\n- Agendar envio\n- Enviar e arquivar');
    return false;
}

function deleteCompose() {
    if (confirm('Descartar este rascunho?')) {
        document.getElementById('compose-modal').classList.add('hidden');
        // Limpar campos
        document.getElementById('compose-to').value = '';
        document.getElementById('compose-subject').value = '';
        document.getElementById('compose-message').value = '';
        document.getElementById('compose-cc').value = '';
        document.getElementById('compose-bcc').value = '';
    }
    return false;
}

async function sendEmail() {
    const to = document.getElementById('compose-to').value;
    const subject = document.getElementById('compose-subject').value;
    const message = document.getElementById('compose-message').value;
    
    if (!to || !subject || !message) {
        alert('Preencha todos os campos');
        return;
    }
    
    try {
        const response = await fetch('api/email_send.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ to, subject, message })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Email enviado com sucesso!');
            closeCompose();
            loadEmails();
        } else {
            alert('Erro ao enviar email: ' + (data.error || 'Erro desconhecido'));
        }
    } catch (error) {
        console.error('Erro ao enviar email:', error);
        alert('Erro ao enviar email');
    }
}

// Responder email
function replyEmail(emailId) {
    const email = emails.find(e => e.id === emailId);
    if (!email) return;
    
    document.getElementById('compose-to').value = email.phone || '';
    document.getElementById('compose-subject').value = 'Re: ' + (email.email_subject || '');
    composeEmail();
}

// Encaminhar email
function forwardEmail(emailId) {
    const email = emails.find(e => e.id === emailId);
    if (!email) return;
    
    document.getElementById('compose-subject').value = 'Fwd: ' + (email.email_subject || '');
    composeEmail();
}

// Utilitários
function formatEmailDate(dateStr) {
    const date = new Date(dateStr);
    const now = new Date();
    const diff = now - date;
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    
    if (days === 0) {
        return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    } else if (days < 7) {
        return date.toLocaleDateString('pt-BR', { weekday: 'short' });
    } else {
        return date.toLocaleDateString('pt-BR', { day: 'numeric', month: 'short' });
    }
}

function formatFullDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('pt-BR', { 
        weekday: 'short', 
        day: 'numeric', 
        month: 'short', 
        hour: '2-digit', 
        minute: '2-digit' 
    });
}

function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatEmailBody(text) {
    if (!text) return '';
    return text.replace(/\n/g, '<br>');
}

function showEmptyState(message) {
    document.getElementById('email-items').innerHTML = `
        <div class="email-loading">
            <i class="fas fa-inbox"></i><br><br>
            ${message}
        </div>
    `;
}

// Seleção múltipla
function toggleSelectAll() {
    const checked = document.getElementById('select-all').checked;
    document.querySelectorAll('.email-item input[type="checkbox"]').forEach(cb => {
        cb.checked = checked;
    });
    return false;
}

function toggleEmailSelection(emailId) {
    // Implementar lógica de seleção múltipla
    console.log('Email selecionado:', emailId);
    return false;
}

function showBulkActions() {
    // Implementar ações em lote
    const selectedCount = document.querySelectorAll('.email-item input[type="checkbox"]:checked').length;
    if (selectedCount === 0) {
        alert('Selecione pelo menos um email');
    } else {
        alert(`${selectedCount} email(s) selecionado(s)\n\nAções em lote em desenvolvimento`);
    }
    return false;
}
