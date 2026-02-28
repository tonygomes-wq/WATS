/**
 * Funcionalidades de Supervisor para o Chat
 * Gerenciamento de conversas, transferências, notas internas, etc.
 */

// Variáveis globais (não redeclarar se já existem)
window.departments = window.departments || [];
window.supervisorUsers = window.supervisorUsers || [];

// Carregar setores ao iniciar
async function loadDepartments() {
    try {
        const response = await fetch('api/departments_manager.php?action=list');
        const data = await response.json();
        
        if (data.success) {
            window.departments = data.departments.filter(d => d.is_active == 1);
            renderDepartmentsMenu();
        }
    } catch (error) {
        console.error('Erro ao carregar setores:', error);
    }
}

// Renderizar menu de setores
function renderDepartmentsMenu() {
    const menu = document.getElementById('departments-menu');
    if (!menu) return;
    
    menu.innerHTML = window.departments.map(dept => `
        <button onclick="filterConversations('department_${dept.id}'); toggleDepartmentsMenu();" 
                data-filter="department_${dept.id}" 
                class="conversation-filter-horizontal w-full text-left px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 transition flex items-center justify-between text-sm">
            <span class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full" style="background-color: ${dept.color}"></span>
                <span class="dark:text-gray-200">${dept.name}</span>
            </span>
            <span class="bg-gray-400 text-white text-xs px-2 py-0.5 rounded-full" id="dept-${dept.id}-count">0</span>
        </button>
    `).join('');
}

// Toggle menu de setores
function toggleDepartmentsMenu() {
    const menu = document.getElementById('departments-menu');
    const arrow = document.getElementById('departments-arrow');
    
    if (menu.classList.contains('hidden')) {
        menu.classList.remove('hidden');
        arrow.classList.add('rotate-180');
    } else {
        menu.classList.add('hidden');
        arrow.classList.remove('rotate-180');
    }
}

// Filtrar conversas por categoria (estende a função base do chat.php)
// Nota: A função principal filterConversations está definida em chat.php
// Esta função adiciona funcionalidades específicas do supervisor
function filterConversationsSupervisor(filter) {
    // Usar a função base se existir
    if (typeof window.filterConversations === 'function') {
        window.filterConversations(filter);
    } else {
        // Fallback caso a função base não exista
        window.currentFilter = filter;
        
        // Atualizar UI dos filtros horizontais
        document.querySelectorAll('.conversation-filter-horizontal').forEach(btn => {
            btn.classList.remove('active');
        });
        
        const activeBtn = document.querySelector(`[data-filter="${filter}"]`);
        if (activeBtn) {
            activeBtn.classList.add('active');
        }
        
        // Recarregar conversas com filtro
        if (typeof loadConversations === 'function') {
            loadConversations();
        }
    }
}

// Abrir modal de nota interna
function openInternalNoteModal() {
    if (!currentConversationId) {
        showError('Selecione uma conversa primeiro');
        return;
    }
    
    const modal = document.getElementById('internal-note-modal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.getElementById('internal-note-text').value = '';
        document.getElementById('internal-note-text').focus();
    }
}

// Fechar modal de nota interna
function closeInternalNoteModal() {
    const modal = document.getElementById('internal-note-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

// Salvar nota interna
async function saveInternalNote() {
    const note = document.getElementById('internal-note-text').value.trim();
    
    if (!note) {
        showError('Digite uma nota');
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'add_note');
        formData.append('conversation_id', currentConversationId);
        formData.append('note', note);
        
        const response = await fetch('api/conversation_actions.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('Nota interna adicionada');
            closeInternalNoteModal();
            loadInternalNotes();
        } else {
            showError(data.error || 'Erro ao salvar nota');
        }
    } catch (error) {
        console.error('Erro:', error);
        showError('Erro ao salvar nota interna');
    }
}

// Marcar como resolvido
async function markAsResolved() {
    if (!currentConversationId) {
        showError('Selecione uma conversa primeiro');
        return;
    }
    
    if (!confirm('Marcar esta conversa como resolvida?')) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'resolve');
        formData.append('conversation_id', currentConversationId);
        
        const response = await fetch('api/conversation_actions.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('Conversa marcada como resolvida');
            loadConversations();
        } else {
            showError(data.error || 'Erro ao resolver conversa');
        }
    } catch (error) {
        console.error('Erro:', error);
        showError('Erro ao resolver conversa');
    }
}

// Encerrar conversa
async function closeConversation() {
    if (!currentConversationId) {
        showError('Selecione uma conversa primeiro');
        return;
    }
    
    if (!confirm('Encerrar esta conversa? Ela será arquivada.')) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'close');
        formData.append('conversation_id', currentConversationId);
        
        const response = await fetch('api/conversation_actions.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('Conversa encerrada');
            loadConversations();
            // Limpar área de chat
            document.getElementById('no-chat-selected').style.display = 'flex';
            document.getElementById('chat-area').style.opacity = '0';
            document.getElementById('chat-area').style.pointerEvents = 'none';
            currentConversationId = null;
        } else {
            showError(data.error || 'Erro ao encerrar conversa');
        }
    } catch (error) {
        console.error('Erro:', error);
        showError('Erro ao encerrar conversa');
    }
}

// Abrir modal de transferência
async function openTransferModal() {
    if (!currentConversationId) {
        showError('Selecione uma conversa primeiro');
        return;
    }
    
    // Carregar atendentes e setores
    await loadSupervisorUsers();
    
    const modal = document.getElementById('transfer-modal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.getElementById('transfer-type').value = 'user';
        toggleTransferType();
    }
}

// Fechar modal de transferência
function closeTransferModal() {
    const modal = document.getElementById('transfer-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

// Carregar atendentes
async function loadSupervisorUsers() {
    try {
        const response = await fetch('api/supervisor_users_manager.php?action=list');
        const data = await response.json();
        
        if (data.success) {
            window.supervisorUsers = data.users.filter(u => u.status === 'active');
            renderTransferUsers();
        }
    } catch (error) {
        console.error('Erro ao carregar atendentes:', error);
    }
}

// Renderizar lista de atendentes para transferência
function renderTransferUsers() {
    const select = document.getElementById('transfer-user');
    if (!select) return;
    
    select.innerHTML = '<option value="">Selecione um atendente</option>' +
        window.supervisorUsers.map(user => `
            <option value="${user.id}">${user.name} (${user.email})</option>
        `).join('');
}

// Renderizar lista de setores para transferência
function renderTransferDepartments() {
    const select = document.getElementById('transfer-department');
    if (!select) return;
    
    select.innerHTML = '<option value="">Selecione um setor</option>' +
        window.departments.map(dept => `
            <option value="${dept.id}">${dept.name}</option>
        `).join('');
}

// Toggle tipo de transferência
function toggleTransferType() {
    const type = document.getElementById('transfer-type').value;
    const userDiv = document.getElementById('transfer-user-div');
    const deptDiv = document.getElementById('transfer-department-div');
    
    if (type === 'user') {
        userDiv.classList.remove('hidden');
        deptDiv.classList.add('hidden');
    } else {
        userDiv.classList.add('hidden');
        deptDiv.classList.remove('hidden');
        renderTransferDepartments();
    }
}

// Executar transferência
async function executeTransfer() {
    const type = document.getElementById('transfer-type').value;
    const reason = document.getElementById('transfer-reason').value.trim();
    
    let toUserId = null;
    let toDepartmentId = null;
    
    if (type === 'user') {
        toUserId = document.getElementById('transfer-user').value;
        if (!toUserId) {
            showError('Selecione um atendente');
            return;
        }
    } else {
        toDepartmentId = document.getElementById('transfer-department').value;
        if (!toDepartmentId) {
            showError('Selecione um setor');
            return;
        }
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'transfer');
        formData.append('conversation_id', currentConversationId);
        if (toUserId) formData.append('to_user_id', toUserId);
        if (toDepartmentId) formData.append('to_department_id', toDepartmentId);
        if (reason) formData.append('reason', reason);
        
        const response = await fetch('api/conversation_actions.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('Conversa transferida com sucesso');
            closeTransferModal();
            loadConversations();
        } else {
            showError(data.error || 'Erro ao transferir conversa');
        }
    } catch (error) {
        console.error('Erro:', error);
        showError('Erro ao transferir conversa');
    }
}

// Carregar notas internas
async function loadInternalNotes() {
    if (!currentConversationId) return;
    
    try {
        const response = await fetch(`api/conversation_actions.php?action=get_notes&conversation_id=${currentConversationId}`);
        const data = await response.json();
        
        if (data.success && data.notes && data.notes.length > 0) {
            displayInternalNotes(data.notes);
        }
    } catch (error) {
        console.error('Erro ao carregar notas:', error);
    }
}

// Exibir notas internas no chat
function displayInternalNotes(notes) {
    const container = document.getElementById('chat-messages-container');
    if (!container) return;
    
    notes.forEach(note => {
        const noteDiv = document.createElement('div');
        noteDiv.className = 'bg-yellow-50 border-l-4 border-yellow-400 p-3 rounded my-2';
        noteDiv.innerHTML = `
            <div class="flex items-start gap-2">
                <i class="fas fa-sticky-note text-yellow-600 mt-1"></i>
                <div class="flex-1">
                    <p class="text-xs text-yellow-800 font-semibold mb-1">
                        Nota Interna - ${note.user_name} - ${new Date(note.created_at).toLocaleString('pt-BR')}
                    </p>
                    <p class="text-sm text-yellow-900">${escapeHtml(note.note)}</p>
                </div>
            </div>
        `;
        container.appendChild(noteDiv);
    });
}

// Atualizar contadores
function updateConversationCounts(conversations) {
    const counts = {
        inbox: 0,
        my_chats: 0,
        resolved: 0,
        closed: 0
    };
    
    conversations.forEach(conv => {
        if (!conv.assigned_to && conv.status === 'open') {
            counts.inbox++;
        } else if (conv.assigned_to && conv.status === 'in_progress') {
            counts.my_chats++;
        } else if (conv.status === 'resolved') {
            counts.resolved++;
        } else if (conv.status === 'closed') {
            counts.closed++;
        }
        
        // Contar por setor
        if (conv.department_id) {
            const deptCount = document.getElementById(`dept-${conv.department_id}-count`);
            if (deptCount) {
                const current = parseInt(deptCount.textContent) || 0;
                deptCount.textContent = current + 1;
            }
        }
    });
    
    // Atualizar contadores no topo da área principal
    const inboxCount = document.getElementById('inbox-count-main');
    const myChatsCount = document.getElementById('my-chats-count-main');
    const resolvedCount = document.getElementById('resolved-count-main');
    const closedCount = document.getElementById('closed-count-main');
    
    if (inboxCount) inboxCount.textContent = counts.inbox;
    if (myChatsCount) myChatsCount.textContent = counts.my_chats;
    if (resolvedCount) resolvedCount.textContent = counts.resolved;
    if (closedCount) closedCount.textContent = counts.closed;
}

// Inicializar funcionalidades de supervisor
document.addEventListener('DOMContentLoaded', function() {
    // Verificar se é supervisor
    const isSupervisor = document.querySelector('[data-filter="my_chats"]');
    if (isSupervisor) {
        loadDepartments();
    }
});

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}
