<?php
/**
 * CHAT DE EMAIL - Interface estilo Gmail
 * Design limpo e funcional para gerenciamento de emails
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$page_title = 'Email';

require_once 'includes/header_spa.php';
?>

<style>
:root {
    --gmail-red: #ea4335;
    --gmail-blue: #4285f4;
    --gmail-green: #34a853;
    --gmail-yellow: #fbbc04;
    --bg-primary: #1e293b;
    --bg-secondary: #0f172a;
    --bg-hover: #334155;
    --bg-selected: #1e3a5f;
    --text-primary: #f1f5f9;
    --text-secondary: #94a3b8;
    --border-color: #334155;
    --sidebar-width: 256px;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

.email-layout {
    display: flex;
    height: calc(100vh - 60px);
    background: var(--bg-secondary);
    font-family: 'Google Sans', 'Roboto', Arial, sans-serif;
}

/* Sidebar */
.email-sidebar {
    width: var(--sidebar-width);
    background: var(--bg-primary);
    border-right: 1px solid var(--border-color);
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}

.compose-btn {
    margin: 16px;
    padding: 12px 24px;
    background: var(--gmail-red);
    border: none;
    border-radius: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    color: white;
    transition: all 0.2s;
    box-shadow: 0 1px 2px 0 rgba(0,0,0,0.3), 0 1px 3px 1px rgba(0,0,0,0.15);
}

.compose-btn:hover {
    background: #d33426;
    box-shadow: 0 1px 3px 0 rgba(0,0,0,0.3), 0 4px 8px 3px rgba(0,0,0,0.15);
}

.compose-btn i {
    font-size: 20px;
}

.nav-list {
    list-style: none;
    padding: 0 8px;
}

.nav-item {
    padding: 8px 16px;
    margin: 2px 0;
    border-radius: 0 24px 24px 0;
    display: flex;
    align-items: center;
    gap: 16px;
    cursor: pointer;
    font-size: 14px;
    color: var(--text-primary);
    transition: background 0.2s;
}

.nav-item:hover {
    background: var(--bg-hover);
}

.nav-item.active {
    background: var(--bg-selected);
    color: var(--gmail-blue);
    font-weight: 700;
}

.nav-item i {
    width: 20px;
    text-align: center;
    font-size: 18px;
}

.nav-item .count {
    margin-left: auto;
    font-weight: 700;
}

/* Main Content */
.email-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* Toolbar */
.email-toolbar {
    padding: 8px 16px;
    background: var(--bg-primary);
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 16px;
}

.search-box {
    flex: 1;
    max-width: 720px;
    position: relative;
}

.search-box input {
    width: 100%;
    padding: 12px 16px 12px 48px;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    font-size: 14px;
    color: var(--text-primary);
}

.search-box input:focus {
    outline: none;
    background: var(--bg-hover);
    border-color: var(--gmail-blue);
}

.search-box i {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
}

.toolbar-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-left: auto;
}

.toolbar-btn {
    width: 40px;
    height: 40px;
    border: none;
    background: transparent;
    border-radius: 50%;
    cursor: pointer;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
}

.toolbar-btn:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

/* Email Actions Bar */
.email-actions {
    padding: 8px 16px;
    background: var(--bg-primary);
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 8px;
}

.action-btn {
    padding: 8px 12px;
    border: none;
    background: transparent;
    border-radius: 4px;
    cursor: pointer;
    color: var(--text-secondary);
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}

.action-btn:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.pagination {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-secondary);
    font-size: 13px;
}

/* Email List */
.email-list {
    flex: 1;
    overflow-y: auto;
    background: var(--bg-secondary);
}

.email-row {
    padding: 12px 16px;
    border-bottom: 1px solid var(--border-color);
    display: grid;
    grid-template-columns: 40px 200px 1fr 120px;
    gap: 16px;
    align-items: center;
    cursor: pointer;
    transition: all 0.2s;
    background: var(--bg-primary);
}

.email-row:hover {
    background: var(--bg-hover);
    box-shadow: inset 1px 0 0 var(--border-color), inset -1px 0 0 var(--border-color), 0 1px 2px 0 rgba(0,0,0,0.3);
    z-index: 1;
}

.email-row.unread {
    background: var(--bg-primary);
    font-weight: 700;
}

.email-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
}

.email-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.email-star {
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 18px;
}

.email-star:hover,
.email-star.starred {
    color: var(--gmail-yellow);
}

.email-sender {
    font-size: 14px;
    color: var(--text-primary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.email-content {
    display: flex;
    gap: 8px;
    overflow: hidden;
}

.email-subject {
    font-size: 14px;
    color: var(--text-primary);
    font-weight: inherit;
}

.email-preview {
    font-size: 14px;
    color: var(--text-secondary);
    font-weight: 400;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.email-date {
    font-size: 12px;
    color: var(--text-secondary);
    text-align: right;
}

/* Empty State */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 16px;
    opacity: 0.3;
}

.loading {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--text-secondary);
}

.loading i {
    font-size: 32px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>

<div class="email-layout">
    <!-- Sidebar -->
    <div class="email-sidebar">
        <button class="compose-btn" onclick="composeEmail()">
            <i class="fas fa-pen"></i>
            Escrever
        </button>
        
        <ul class="nav-list">
            <li class="nav-item active" data-folder="inbox">
                <i class="fas fa-inbox"></i>
                <span>Caixa de entrada</span>
                <span class="count" id="inbox-count">9</span>
            </li>
            <li class="nav-item" data-folder="starred">
                <i class="far fa-star"></i>
                <span>Com estrela</span>
            </li>
            <li class="nav-item" data-folder="sent">
                <i class="fas fa-paper-plane"></i>
                <span>Enviados</span>
            </li>
            <li class="nav-item" data-folder="drafts">
                <i class="far fa-file"></i>
                <span>Rascunhos</span>
            </li>
            <li class="nav-item" data-folder="trash">
                <i class="far fa-trash-alt"></i>
                <span>Lixeira</span>
            </li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="email-main">
        <!-- Toolbar -->
        <div class="email-toolbar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Pesquisar e-mail" id="search-input">
            </div>
            <div class="toolbar-actions">
                <button class="toolbar-btn" title="Ajuda" onclick="showHelp()">
                    <i class="far fa-question-circle"></i>
                </button>
                <button class="toolbar-btn" title="Configurações" onclick="goToSettings()">
                    <i class="fas fa-cog"></i>
                </button>
                <button class="toolbar-btn" title="Canais" onclick="goToChannels()">
                    <i class="fas fa-plug"></i>
                </button>
            </div>
        </div>
        
        <!-- Actions Bar -->
        <div class="email-actions">
            <input type="checkbox" id="select-all" title="Selecionar">
            <button class="action-btn" onclick="syncEmails()" title="Atualizar">
                <i class="fas fa-sync-alt"></i>
            </button>
            <button class="action-btn" onclick="testConnection()" title="Testar Conexão">
                <i class="fas fa-plug"></i>
                Testar
            </button>
            <button class="action-btn" onclick="showBulkActions()" title="Ações em lote" id="bulk-actions-btn" style="display: none;">
                <i class="fas fa-trash-alt"></i>
                Excluir selecionados
            </button>
            <button class="action-btn" onclick="markAsRead()" title="Marcar como lido" id="mark-read-btn" style="display: none;">
                <i class="fas fa-envelope-open"></i>
                Marcar como lido
            </button>
            
            <div class="pagination">
                <span id="email-range">1-6 de 6</span>
                <button class="toolbar-btn" title="Mais antigos">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="toolbar-btn" title="Mais recentes">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        
        <!-- Email List -->
        <div class="email-list" id="email-list">
            <div class="loading">
                <i class="fas fa-spinner fa-spin"></i>
            </div>
        </div>
    </div>
</div>

<script>
let emails = [];
let currentFolder = 'inbox';
let selectedEmails = [];

// Carregar emails
async function loadEmails() {
    try {
        const response = await fetch(`api/email_conversations.php?folder=${currentFolder}`);
        const data = await response.json();
        
        if (data.success && data.conversations) {
            emails = data.conversations;
            renderEmails();
        } else {
            showEmptyState();
        }
    } catch (error) {
        console.error('Erro ao carregar emails:', error);
        showEmptyState();
    }
}

// Renderizar lista de emails
function renderEmails() {
    const container = document.getElementById('email-list');
    
    if (emails.length === 0) {
        showEmptyState();
        return;
    }
    
    container.innerHTML = emails.map(email => `
        <div class="email-row ${email.unread ? 'unread' : ''}" onclick="openEmail(${email.id})">
            <div class="email-checkbox">
                <input type="checkbox" data-email-id="${email.id}" onclick="event.stopPropagation(); toggleEmailSelection(${email.id})">
                <i class="email-star ${email.starred ? 'fas starred' : 'far'} fa-star" onclick="toggleStar(${email.id}, event)"></i>
            </div>
            <div class="email-sender">${escapeHtml(email.contact_name || email.display_name || 'Sem nome')}</div>
            <div class="email-content">
                <span class="email-subject">${escapeHtml(email.subject || 'Sem assunto')}</span>
                <span class="email-preview"> - ${escapeHtml(email.preview || '')}</span>
            </div>
            <div class="email-date">${formatDate(email.last_message_at || email.created_at)}</div>
        </div>
    `).join('');
    
    updateEmailCount();
}

// Escape HTML para segurança
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Mostrar estado vazio
function showEmptyState() {
    const container = document.getElementById('email-list');
    container.innerHTML = `
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>Nenhum email encontrado</p>
            <button onclick="syncEmails()" style="margin-top: 16px; padding: 8px 16px; background: var(--gmail-blue); color: white; border: none; border-radius: 4px; cursor: pointer;">
                Sincronizar Emails
            </button>
        </div>
    `;
}

// Sincronizar emails
async function syncEmails() {
    const btn = event?.target?.closest('.action-btn');
    const icon = btn?.querySelector('i');
    
    if (icon) icon.classList.add('fa-spin');
    
    try {
        const response = await fetch('api/channels/email/simple_fetch.php?limit=10');
        const data = await response.json();
        
        if (data.success) {
            await loadEmails();
            showToast(`✓ ${data.processed} novos emails sincronizados`, 'success');
        } else {
            showToast('Erro ao sincronizar emails: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Erro ao sincronizar:', error);
        showToast('Erro ao sincronizar emails', 'error');
    } finally {
        if (icon) icon.classList.remove('fa-spin');
    }
}

// Abrir email (visualizar detalhes)
function openEmail(id) {
    const email = emails.find(e => e.id === id);
    if (!email) return;
    
    // Redirecionar para visualização do email
    window.location.href = `email_view.php?id=${id}`;
}

// Toggle estrela
async function toggleStar(id, event) {
    event.stopPropagation();
    const star = event.target;
    const isStarred = !star.classList.contains('starred');
    
    star.classList.toggle('fas');
    star.classList.toggle('far');
    star.classList.toggle('starred');
    
    // Salvar no backend
    try {
        const response = await fetch('api/email_star.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                id: id, 
                starred: isStarred 
            })
        });
        
        const data = await response.json();
        
        if (!data.success) {
            // Reverter em caso de erro
            star.classList.toggle('fas');
            star.classList.toggle('far');
            star.classList.toggle('starred');
            showToast('Erro ao atualizar estrela', 'error');
        }
    } catch (error) {
        console.error('Erro ao salvar estrela:', error);
        // Reverter em caso de erro
        star.classList.toggle('fas');
        star.classList.toggle('far');
        star.classList.toggle('starred');
    }
}

// Selecionar/desselecionar email
function toggleEmailSelection(id) {
    const index = selectedEmails.indexOf(id);
    if (index > -1) {
        selectedEmails.splice(index, 1);
    } else {
        selectedEmails.push(id);
    }
    updateSelectAllCheckbox();
    updateBulkActionsVisibility();
}

// Atualizar visibilidade dos botões de ações em lote
function updateBulkActionsVisibility() {
    const bulkBtn = document.getElementById('bulk-actions-btn');
    const markReadBtn = document.getElementById('mark-read-btn');
    
    if (selectedEmails.length > 0) {
        if (bulkBtn) bulkBtn.style.display = 'flex';
        if (markReadBtn) markReadBtn.style.display = 'flex';
    } else {
        if (bulkBtn) bulkBtn.style.display = 'none';
        if (markReadBtn) markReadBtn.style.display = 'none';
    }
}

// Excluir emails selecionados
async function showBulkActions() {
    if (selectedEmails.length === 0) return;
    
    if (!confirm(`Deseja excluir ${selectedEmails.length} email(s)?`)) {
        return;
    }
    
    try {
        const promises = selectedEmails.map(id => 
            fetch('api/email_delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            })
        );
        
        await Promise.all(promises);
        
        showToast(`${selectedEmails.length} email(s) excluído(s)`, 'success');
        selectedEmails = [];
        updateBulkActionsVisibility();
        loadEmails();
    } catch (error) {
        console.error('Erro ao excluir emails:', error);
        showToast('Erro ao excluir emails', 'error');
    }
}

// Marcar como lido
async function markAsRead() {
    if (selectedEmails.length === 0) return;
    
    showToast('Funcionalidade em desenvolvimento', 'info');
    // TODO: Implementar API para marcar como lido
}

// Selecionar todos
document.getElementById('select-all')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.email-checkbox input[type="checkbox"]');
    checkboxes.forEach(cb => {
        cb.checked = this.checked;
        const emailId = parseInt(cb.dataset.emailId);
        if (this.checked && !selectedEmails.includes(emailId)) {
            selectedEmails.push(emailId);
        } else if (!this.checked) {
            selectedEmails = [];
        }
    });
    updateBulkActionsVisibility();
});

// Atualizar checkbox "selecionar todos"
function updateSelectAllCheckbox() {
    const selectAll = document.getElementById('select-all');
    if (selectAll) {
        selectAll.checked = selectedEmails.length === emails.length && emails.length > 0;
    }
}

// Formatar data
function formatDate(dateStr) {
    if (!dateStr) return '';
    
    const date = new Date(dateStr);
    const now = new Date();
    const diff = now - date;
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    
    if (days === 0) {
        return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    } else if (days < 7) {
        const weekday = date.toLocaleDateString('pt-BR', { weekday: 'short' });
        return weekday.charAt(0).toUpperCase() + weekday.slice(1);
    } else {
        return date.toLocaleDateString('pt-BR', { day: 'numeric', month: 'short' });
    }
}

// Atualizar contador
function updateEmailCount() {
    const count = emails.filter(e => e.unread).length;
    const countEl = document.getElementById('inbox-count');
    if (countEl) {
        countEl.textContent = count || '';
    }
    
    const rangeEl = document.getElementById('email-range');
    if (rangeEl) {
        rangeEl.textContent = emails.length > 0 ? `1-${emails.length} de ${emails.length}` : '0';
    }
}

// Compor email
function composeEmail() {
    window.location.href = 'email_compose.php';
}

// Pesquisar emails
document.getElementById('search-input')?.addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase();
    
    if (!term) {
        renderEmails();
        return;
    }
    
    const filtered = emails.filter(email => {
        const sender = (email.contact_name || email.display_name || '').toLowerCase();
        const subject = (email.subject || '').toLowerCase();
        const preview = (email.preview || '').toLowerCase();
        
        return sender.includes(term) || subject.includes(term) || preview.includes(term);
    });
    
    const container = document.getElementById('email-list');
    if (filtered.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <p>Nenhum resultado encontrado para "${escapeHtml(term)}"</p>
            </div>
        `;
    } else {
        container.innerHTML = filtered.map(email => `
            <div class="email-row ${email.unread ? 'unread' : ''}" onclick="openEmail(${email.id})">
                <div class="email-checkbox">
                    <input type="checkbox" data-email-id="${email.id}" onclick="event.stopPropagation(); toggleEmailSelection(${email.id})">
                    <i class="email-star ${email.starred ? 'fas starred' : 'far'} fa-star" onclick="toggleStar(${email.id}, event)"></i>
                </div>
                <div class="email-sender">${escapeHtml(email.contact_name || email.display_name || 'Sem nome')}</div>
                <div class="email-content">
                    <span class="email-subject">${escapeHtml(email.subject || 'Sem assunto')}</span>
                    <span class="email-preview"> - ${escapeHtml(email.preview || '')}</span>
                </div>
                <div class="email-date">${formatDate(email.last_message_at || email.created_at)}</div>
            </div>
        `).join('');
    }
});

// Toast notification
function showToast(message, type = 'info') {
    // Usar sistema de notificações do WATS se disponível
    if (typeof showNotification === 'function') {
        showNotification(message, type);
    } else {
        // Fallback simples
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 24px;
            background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 10000;
            animation: slideIn 0.3s ease;
        `;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
}

// Navegação de pastas
document.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('click', function() {
        document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
        this.classList.add('active');
        currentFolder = this.dataset.folder;
        
        // Carregar emails da pasta selecionada
        loadEmails();
    });
});

// Auto-refresh a cada 30 segundos
setInterval(() => {
    loadEmails();
}, 30000);

// Carregar ao iniciar
loadEmails();

// Funções da toolbar
function showHelp() {
    alert('Ajuda do Email\n\n' +
          '• Clique em um email para visualizar\n' +
          '• Use a estrela para marcar emails importantes\n' +
          '• Clique em "Escrever" para compor novo email\n' +
          '• Use a busca para encontrar emails\n' +
          '• Sincronize para buscar novos emails');
}

function goToSettings() {
    window.location.href = 'settings.php';
}

function goToChannels() {
    window.location.href = 'channels.php';
}

// Testar conexão
async function testConnection() {
    try {
        const response = await fetch('api/channels/email/test_connection.php');
        const data = await response.json();
        
        if (data.success) {
            alert(`✓ Conexão bem-sucedida!\n\n` +
                  `Total de emails: ${data.total_emails}\n` +
                  `Emails não lidos: ${data.unread_emails}\n\n` +
                  `Email: ${data.debug.email}`);
        } else {
            alert(`✗ Erro na conexão:\n\n${data.error}\n\n` +
                  `Debug: ${JSON.stringify(data.debug, null, 2)}`);
        }
    } catch (error) {
        console.error('Erro ao testar conexão:', error);
        alert('Erro ao testar conexão: ' + error.message);
    }
}
</script>

<?php require_once 'includes/footer_spa.php'; ?>
