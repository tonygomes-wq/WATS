/**
 * JavaScript para Gerenciamento de Atendentes
 */

let users = [];
let editingUserId = null;

// Carregar atendentes ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    loadUsers();
    
    // Event listeners para filtros
    document.getElementById('search-input').addEventListener('input', debounce(loadUsers, 500));
    document.getElementById('status-filter').addEventListener('change', loadUsers);
    document.getElementById('department-filter').addEventListener('change', loadUsers);
    
    // Form submit
    document.getElementById('user-form').addEventListener('submit', handleFormSubmit);
    
    // Fechar modal com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeUserModal();
        }
    });
});

// Carregar lista de atendentes
async function loadUsers() {
    const search = document.getElementById('search-input').value;
    const status = document.getElementById('status-filter').value;
    const department = document.getElementById('department-filter').value;
    
    document.getElementById('loading-state').classList.remove('hidden');
    document.getElementById('empty-state').classList.add('hidden');
    document.getElementById('users-table-body').innerHTML = '';
    
    try {
        const params = new URLSearchParams({
            action: 'list',
            search: search,
            status: status,
            department: department
        });
        
        const response = await fetch(`api/supervisor_users_manager.php?${params}`);
        const data = await response.json();
        
        if (data.success) {
            users = data.users;
            renderUsers(users);
            updateStats(users);
        } else {
            showError(data.error);
        }
    } catch (error) {
        console.error('Erro ao carregar atendentes:', error);
        showError('Erro ao carregar atendentes');
    } finally {
        document.getElementById('loading-state').classList.add('hidden');
    }
}

// Renderizar tabela de atendentes
function renderUsers(users) {
    const tbody = document.getElementById('users-table-body');
    tbody.innerHTML = '';
    
    if (users.length === 0) {
        document.getElementById('empty-state').classList.remove('hidden');
        return;
    }
    
    users.forEach(user => {
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors';
        
        // Status badge
        let statusBadge = '';
        if (user.status === 'active') {
            statusBadge = '<span class="px-3 py-1 text-xs font-bold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300"><i class="fas fa-check-circle mr-1"></i>Ativo</span>';
        } else if (user.status === 'blocked') {
            statusBadge = '<span class="px-3 py-1 text-xs font-bold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300"><i class="fas fa-ban mr-1"></i>Bloqueado</span>';
        } else {
            statusBadge = '<span class="px-3 py-1 text-xs font-bold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300"><i class="fas fa-pause-circle mr-1"></i>Inativo</span>';
        }
        
        // Último acesso
        const lastLogin = user.last_login ? new Date(user.last_login).toLocaleString('pt-BR') : 'Nunca';
        
        // Iniciais do nome
        const initials = user.name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
        
        tr.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                    <div class="flex-shrink-0 h-12 w-12">
                        <div class="h-12 w-12 rounded-full bg-gradient-to-br from-green-400 to-green-600 flex items-center justify-center shadow-lg">
                            <span class="text-white font-bold text-lg">${initials}</span>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-bold text-gray-900 dark:text-white">
                            ${escapeHtml(user.name)}
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            <i class="fas fa-envelope mr-1"></i>${escapeHtml(user.email)}
                        </div>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4">
                <div class="text-sm text-gray-900 dark:text-white">
                    ${user.departments || '<span class="text-gray-400 italic">Nenhum setor</span>'}
                </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                ${statusBadge}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                <span class="flex items-center gap-2 font-semibold">
                    <i class="fas fa-comments text-green-600"></i>
                    ${user.active_conversations || 0}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                <i class="fas fa-clock mr-1"></i>${lastLogin}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <div class="flex items-center justify-end gap-2">
                    <button onclick="editUser(${user.id})" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 p-2 hover:bg-blue-50 dark:hover:bg-blue-900 rounded transition-colors" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    ${user.status === 'active' ? 
                        `<button onclick="blockUser(${user.id})" class="text-red-600 hover:text-red-900 dark:text-red-400 p-2 hover:bg-red-50 dark:hover:bg-red-900 rounded transition-colors" title="Bloquear">
                            <i class="fas fa-ban"></i>
                        </button>` :
                        `<button onclick="unblockUser(${user.id})" class="text-green-600 hover:text-green-900 dark:text-green-400 p-2 hover:bg-green-50 dark:hover:bg-green-900 rounded transition-colors" title="Desbloquear">
                            <i class="fas fa-check-circle"></i>
                        </button>`
                    }
                    <button onclick="deleteUser(${user.id})" class="text-red-600 hover:text-red-900 dark:text-red-400 p-2 hover:bg-red-50 dark:hover:bg-red-900 rounded transition-colors" title="Excluir">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        
        tbody.appendChild(tr);
    });
}

// Atualizar estatísticas
function updateStats(users) {
    const total = users.length;
    const active = users.filter(u => u.status === 'active').length;
    const blocked = users.filter(u => u.status === 'blocked').length;
    
    document.getElementById('total-users').textContent = total;
    document.getElementById('active-users').textContent = active;
    document.getElementById('blocked-users').textContent = blocked;
}

// Abrir modal de criação
function openCreateModal() {
    editingUserId = null;
    document.getElementById('modal-title').innerHTML = '<i class="fas fa-user-plus text-green-600"></i> Novo Atendente';
    document.getElementById('user-form').reset();
    document.getElementById('user-id').value = '';
    document.getElementById('user-password').required = true;
    document.getElementById('password-required').classList.remove('hidden');
    document.getElementById('password-hint').classList.add('hidden');
    
    // Ocultar seções de permissões e 2FA ao criar novo atendente
    document.getElementById('permissions-section').classList.add('hidden');
    document.getElementById('2fa-section').classList.add('hidden');
    
    document.getElementById('user-modal').classList.remove('hidden');
    document.getElementById('user-modal').classList.add('flex');
}

// Editar atendente
async function editUser(userId) {
    editingUserId = userId;
    
    try {
        const response = await fetch(`api/supervisor_users_manager.php?action=get&user_id=${userId}`);
        const data = await response.json();
        
        if (data.success) {
            const user = data.user;
            
            document.getElementById('modal-title').innerHTML = '<i class="fas fa-user-edit text-blue-600"></i> Editar Atendente';
            document.getElementById('user-id').value = user.id;
            document.getElementById('user-name').value = user.name;
            document.getElementById('user-email').value = user.email;
            document.getElementById('user-phone').value = user.phone || '';
            document.getElementById('user-password').value = '';
            document.getElementById('user-password').required = false;
            document.getElementById('password-required').classList.add('hidden');
            document.getElementById('password-hint').classList.remove('hidden');
            
            // Marcar setores
            const checkboxes = document.querySelectorAll('input[name="departments[]"]');
            checkboxes.forEach(cb => {
                cb.checked = user.department_ids.includes(cb.value);
            });
            
            // Carregar configuração de instância WhatsApp
            const instanceSupervisor = document.getElementById('instance_supervisor');
            const instanceOwn = document.getElementById('instance_own');
            const instanceConfigAllowed = document.getElementById('instance_config_allowed');
            const ownInstanceOptions = document.getElementById('own-instance-options');
            
            if (instanceSupervisor && instanceOwn) {
                if (user.use_own_instance == 1) {
                    instanceOwn.checked = true;
                    if (ownInstanceOptions) ownInstanceOptions.classList.remove('hidden');
                } else {
                    instanceSupervisor.checked = true;
                    if (ownInstanceOptions) ownInstanceOptions.classList.add('hidden');
                }
            }
            
            if (instanceConfigAllowed) {
                instanceConfigAllowed.checked = user.instance_config_allowed == 1;
            }
            
            // Mostrar seções de permissões e 2FA
            document.getElementById('permissions-section').classList.remove('hidden');
            document.getElementById('2fa-section').classList.remove('hidden');
            
            // Carregar permissões e status do 2FA
            loadMenuPermissions(userId);
            load2FAStatus(userId);
            
            document.getElementById('user-modal').classList.remove('hidden');
            document.getElementById('user-modal').classList.add('flex');
        } else {
            showError(data.error);
        }
    } catch (error) {
        console.error('Erro ao carregar atendente:', error);
        showError('Erro ao carregar dados do atendente');
    }
}

// Fechar modal
function closeUserModal() {
    document.getElementById('user-modal').classList.add('hidden');
    document.getElementById('user-modal').classList.remove('flex');
    document.getElementById('user-form').reset();
    editingUserId = null;
}

// Submit do formulário
async function handleFormSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    formData.append('action', editingUserId ? 'update' : 'create');
    
    // Coletar setores selecionados
    const departments = [];
    document.querySelectorAll('input[name="departments[]"]:checked').forEach(cb => {
        departments.push(cb.value);
    });
    formData.append('departments', JSON.stringify(departments));
    
    // Coletar configuração de instância WhatsApp
    const instanceTypeRadio = document.querySelector('input[name="instance_type"]:checked');
    if (instanceTypeRadio) {
        formData.append('instance_type', instanceTypeRadio.value);
    }
    
    const instanceConfigAllowed = document.getElementById('instance_config_allowed');
    if (instanceConfigAllowed && instanceConfigAllowed.checked) {
        formData.append('instance_config_allowed', '1');
    }
    
    try {
        const response = await fetch('api/supervisor_users_manager.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Se estiver editando, salvar permissões também
            if (editingUserId) {
                await saveMenuPermissions(editingUserId);
            }
            
            showSuccess(data.message);
            closeUserModal();
            loadUsers();
        } else {
            showError(data.error);
        }
    } catch (error) {
        console.error('Erro ao salvar atendente:', error);
        showError('Erro ao salvar atendente');
    }
}

// Bloquear atendente
async function blockUser(userId) {
    if (!confirm('Deseja realmente bloquear este atendente?')) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'block');
        formData.append('user_id', userId);
        
        const response = await fetch('api/supervisor_users_manager.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(data.message);
            loadUsers();
        } else {
            showError(data.error);
        }
    } catch (error) {
        console.error('Erro ao bloquear atendente:', error);
        showError('Erro ao bloquear atendente');
    }
}

// Desbloquear atendente
async function unblockUser(userId) {
    try {
        const formData = new FormData();
        formData.append('action', 'unblock');
        formData.append('user_id', userId);
        
        const response = await fetch('api/supervisor_users_manager.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(data.message);
            loadUsers();
        } else {
            showError(data.error);
        }
    } catch (error) {
        console.error('Erro ao desbloquear atendente:', error);
        showError('Erro ao desbloquear atendente');
    }
}

// Excluir atendente
async function deleteUser(userId) {
    if (!confirm('Deseja realmente excluir este atendente? Esta ação não pode ser desfeita.')) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('user_id', userId);
        
        const response = await fetch('api/supervisor_users_manager.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(data.message);
            loadUsers();
        } else {
            showError(data.error);
        }
    } catch (error) {
        console.error('Erro ao excluir atendente:', error);
        showError('Erro ao excluir atendente');
    }
}

// Toggle visibilidade da senha
function togglePassword() {
    const passwordInput = document.getElementById('user-password');
    const passwordIcon = document.getElementById('password-icon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        passwordIcon.classList.remove('fa-eye');
        passwordIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        passwordIcon.classList.remove('fa-eye-slash');
        passwordIcon.classList.add('fa-eye');
    }
}

// Utilitários
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

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

function showSuccess(message) {
    alert('✅ ' + message);
}

function showError(message) {
    alert('❌ ' + message);
}

// ============================================
// FUNÇÕES DE PERMISSÕES DE MENU
// ============================================

// Carregar permissões de menu do atendente
async function loadMenuPermissions(userId) {
    try {
        const response = await fetch(`api/supervisor_users_manager.php?action=get&user_id=${userId}`);
        const data = await response.json();
        
        if (data.success && data.user.allowed_menus) {
            const permissions = JSON.parse(data.user.allowed_menus);
            
            // Marcar checkboxes baseado nas permissões
            document.querySelector('input[name="menu_dashboard"]').checked = permissions.dashboard || false;
            document.querySelector('input[name="menu_dispatch"]').checked = permissions.dispatch || false;
            document.querySelector('input[name="menu_contacts"]').checked = permissions.contacts || false;
            document.querySelector('input[name="menu_kanban"]').checked = permissions.kanban || false;
        }
    } catch (error) {
        console.error('Erro ao carregar permissões:', error);
    }
}

// Salvar permissões de menu
async function saveMenuPermissions(userId) {
    const permissions = {
        chat: true, // Sempre true
        profile: true, // Sempre true
        dashboard: document.querySelector('input[name="menu_dashboard"]').checked,
        dispatch: document.querySelector('input[name="menu_dispatch"]').checked,
        contacts: document.querySelector('input[name="menu_contacts"]').checked,
        kanban: document.querySelector('input[name="menu_kanban"]').checked
    };
    
    try {
        const formData = new FormData();
        formData.append('action', 'update_permissions');
        formData.append('user_id', userId);
        formData.append('permissions', JSON.stringify(permissions));
        
        const response = await fetch('api/supervisor_users_manager.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (!data.success) {
            console.error('Erro ao salvar permissões:', data.error);
        }
    } catch (error) {
        console.error('Erro ao salvar permissões:', error);
    }
}

// ============================================
// FUNÇÕES DE 2FA
// ============================================

// Carregar status do 2FA
async function load2FAStatus(userId) {
    try {
        const response = await fetch('/api/attendant_2fa_manager.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'get_2fa_status',
                attendant_id: userId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            const status = data.data;
            
            if (status.enabled) {
                // 2FA está ativo
                document.getElementById('2fa-disabled-state').classList.add('hidden');
                document.getElementById('2fa-enabled-state').classList.remove('hidden');
                
                // Mostrar badge se for obrigatório
                if (status.forced_by_supervisor) {
                    document.getElementById('2fa-forced-badge').classList.remove('hidden');
                } else {
                    document.getElementById('2fa-forced-badge').classList.add('hidden');
                }
            } else {
                // 2FA está desativado
                document.getElementById('2fa-disabled-state').classList.remove('hidden');
                document.getElementById('2fa-enabled-state').classList.add('hidden');
            }
        }
    } catch (error) {
        console.error('Erro ao carregar status do 2FA:', error);
    }
}

// Ativar 2FA
async function enable2FA(force = false) {
    if (!editingUserId) {
        showError('Nenhum atendente selecionado');
        return;
    }
    
    const confirmMsg = force 
        ? 'Ativar 2FA OBRIGATÓRIO para este atendente? Ele não poderá desativar sozinho.'
        : 'Ativar 2FA para este atendente?';
    
    if (!confirm(confirmMsg)) return;
    
    try {
        const response = await fetch('/api/attendant_2fa_manager.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'enable_2fa',
                attendant_id: editingUserId,
                force: force
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('2FA ativado com sucesso!');
            
            // Mostrar modal com QR Code e códigos
            showQRCodeModal(data.data);
            
            // Atualizar status do 2FA
            load2FAStatus(editingUserId);
        } else {
            showError(data.message);
        }
    } catch (error) {
        console.error('Erro ao ativar 2FA:', error);
        showError('Erro ao ativar 2FA');
    }
}

// Desativar 2FA
async function disable2FA() {
    if (!editingUserId) {
        showError('Nenhum atendente selecionado');
        return;
    }
    
    if (!confirm('Desativar 2FA para este atendente? Ele perderá a proteção adicional.')) return;
    
    try {
        const response = await fetch('/api/attendant_2fa_manager.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'disable_2fa',
                attendant_id: editingUserId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('2FA desativado com sucesso!');
            load2FAStatus(editingUserId);
        } else {
            showError(data.message);
        }
    } catch (error) {
        console.error('Erro ao desativar 2FA:', error);
        showError('Erro ao desativar 2FA');
    }
}

// Regenerar códigos de backup
async function regenerateBackupCodes() {
    if (!editingUserId) {
        showError('Nenhum atendente selecionado');
        return;
    }
    
    if (!confirm('Regenerar códigos de backup? Os códigos antigos serão invalidados.')) return;
    
    try {
        const response = await fetch('/api/attendant_2fa_manager.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'regenerate_backup_codes',
                attendant_id: editingUserId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('Códigos de backup regenerados!');
            showBackupCodesModal(data.data.backup_codes);
        } else {
            showError(data.message);
        }
    } catch (error) {
        console.error('Erro ao regenerar códigos:', error);
        showError('Erro ao regenerar códigos de backup');
    }
}

// Mostrar modal com QR Code
function showQRCodeModal(data) {
    document.getElementById('qrcode-image').src = data.qr_code_url;
    
    const codesList = document.getElementById('backup-codes-list');
    codesList.innerHTML = data.backup_codes.map(code => 
        `<div class="text-gray-800 dark:text-gray-200">${code}</div>`
    ).join('');
    
    document.getElementById('qrcode-modal').classList.remove('hidden');
    document.getElementById('qrcode-modal').classList.add('flex');
}

// Fechar modal QR Code
function closeQRCodeModal() {
    document.getElementById('qrcode-modal').classList.add('hidden');
    document.getElementById('qrcode-modal').classList.remove('flex');
}

// Mostrar modal de códigos de backup
function showBackupCodesModal(codes) {
    const codesList = document.getElementById('new-backup-codes-list');
    codesList.innerHTML = codes.map(code => 
        `<div class="text-gray-800 dark:text-gray-200">${code}</div>`
    ).join('');
    
    document.getElementById('backup-codes-modal').classList.remove('hidden');
    document.getElementById('backup-codes-modal').classList.add('flex');
}

// Fechar modal de códigos de backup
function closeBackupCodesModal() {
    document.getElementById('backup-codes-modal').classList.add('hidden');
    document.getElementById('backup-codes-modal').classList.remove('flex');
}

// Copiar códigos de backup
function copyBackupCodes() {
    const codes = Array.from(document.getElementById('backup-codes-list').children)
        .map(div => div.textContent)
        .join('\n');
    
    navigator.clipboard.writeText(codes).then(() => {
        showSuccess('Códigos copiados para a área de transferência!');
    }).catch(err => {
        console.error('Erro ao copiar:', err);
        showError('Erro ao copiar códigos');
    });
}

// Copiar novos códigos de backup
function copyNewBackupCodes() {
    const codes = Array.from(document.getElementById('new-backup-codes-list').children)
        .map(div => div.textContent)
        .join('\n');
    
    navigator.clipboard.writeText(codes).then(() => {
        showSuccess('Códigos copiados para a área de transferência!');
    }).catch(err => {
        console.error('Erro ao copiar:', err);
        showError('Erro ao copiar códigos');
    });
}

// Imprimir QR Code
function printQRCode() {
    const printWindow = window.open('', '_blank');
    const qrImage = document.getElementById('qrcode-image').src;
    const codes = Array.from(document.getElementById('backup-codes-list').children)
        .map(div => div.textContent)
        .join('<br>');
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>2FA - Configuração</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                h1 { color: #16a34a; }
                .qr-container { text-align: center; margin: 20px 0; }
                .qr-container img { border: 2px solid #ddd; padding: 10px; }
                .codes { background: #f9fafb; padding: 15px; border-radius: 8px; margin-top: 20px; }
                .codes h2 { color: #dc2626; }
                .warning { background: #fef3c7; padding: 10px; border-left: 4px solid #f59e0b; margin: 20px 0; }
            </style>
        </head>
        <body>
            <h1>🔐 Configuração de Autenticação de Dois Fatores (2FA)</h1>
            
            <div class="warning">
                <strong>⚠️ Importante:</strong> Guarde este documento em local seguro!
            </div>
            
            <div class="qr-container">
                <h2>1. Escaneie este QR Code no Google Authenticator:</h2>
                <img src="${qrImage}" alt="QR Code 2FA">
            </div>
            
            <div class="codes">
                <h2>2. Códigos de Backup (use se perder acesso ao app):</h2>
                <div style="font-family: monospace; line-height: 1.8;">
                    ${codes}
                </div>
            </div>
            
            <p style="margin-top: 30px; color: #6b7280; font-size: 12px;">
                Gerado em: ${new Date().toLocaleString('pt-BR')}<br>
                Sistema WATS - MACIP Tecnologia LTDA
            </p>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250);
}
