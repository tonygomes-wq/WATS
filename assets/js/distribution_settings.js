/**
 * JavaScript para Configurações de Distribuição Automática
 */

let currentTab = 'rules';
let editingRuleId = null;

// Carregar ao iniciar
document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadRules();
    
    // Atualizar estatísticas a cada 30 segundos
    setInterval(loadStats, 30000);
});

/**
 * Carregar estatísticas
 */
async function loadStats() {
    try {
        const response = await fetch('/api/distribution.php?action=get_stats');
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('active-rules').textContent = data.stats.active_rules;
            document.getElementById('queue-count').textContent = data.stats.queue_count;
            document.getElementById('distributed-today').textContent = data.stats.distributed_today;
            document.getElementById('avg-wait-time').textContent = formatTime(data.stats.avg_wait_time);
        }
    } catch (error) {
        console.error('Erro ao carregar estatísticas:', error);
    }
}

/**
 * Carregar regras de distribuição
 */
async function loadRules() {
    try {
        const response = await fetch('/api/distribution.php?action=list_rules');
        const data = await response.json();
        
        if (data.success) {
            renderRules(data.rules);
        }
    } catch (error) {
        console.error('Erro ao carregar regras:', error);
    }
}

/**
 * Renderizar regras na tabela
 */
function renderRules(rules) {
    const tbody = document.getElementById('rules-table-body');
    tbody.innerHTML = '';
    
    if (rules.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                    <div class="flex flex-col items-center justify-center">
                        <i class="fas fa-cogs text-4xl mb-3 text-gray-300"></i>
                        <p class="text-lg font-medium">Nenhuma regra configurada</p>
                        <p class="text-sm mt-1">Clique em "Nova Regra" para criar sua primeira regra de distribuição</p>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    const typeLabels = {
        'round_robin': 'Rodízio',
        'least_busy': 'Menos Ocupado',
        'by_department': 'Por Setor',
        'by_skill': 'Por Habilidade',
        'manual': 'Manual'
    };
    
    rules.forEach(rule => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors';
        
        const statusBadge = rule.is_active == 1
            ? '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Ativa</span>'
            : '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Inativa</span>';
        
        const priorityBadge = getPriorityBadge(rule.priority);
        
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                    <div class="flex-shrink-0 h-10 w-10 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                        <i class="fas fa-random text-purple-600 dark:text-purple-400"></i>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">${escapeHtml(rule.name)}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">ID: ${rule.id}</div>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                    ${typeLabels[rule.type] || rule.type}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                ${priorityBadge}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                <div class="flex items-center gap-1">
                    <i class="fas fa-users text-gray-400 text-xs"></i>
                    ${rule.max_conversations_per_attendant}
                </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                <div class="flex items-center gap-1">
                    <i class="fas fa-clock text-gray-400 text-xs"></i>
                    ${rule.work_hours_start.substring(0,5)} - ${rule.work_hours_end.substring(0,5)}
                </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                ${statusBadge}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">
                <div class="flex items-center gap-2">
                    <button onclick="editRule(${rule.id})" 
                            class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors" 
                            title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="toggleRule(${rule.id})" 
                            class="text-yellow-600 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-300 transition-colors" 
                            title="Ativar/Desativar">
                        <i class="fas fa-toggle-${rule.is_active == 1 ? 'on' : 'off'}"></i>
                    </button>
                    <button onclick="deleteRule(${rule.id})" 
                            class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 transition-colors" 
                            title="Excluir">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        
        tbody.appendChild(row);
    });
}

/**
 * Carregar fila de espera
 */
async function loadQueue() {
    try {
        const response = await fetch('/api/distribution.php?action=get_queue');
        const data = await response.json();
        
        if (data.success) {
            renderQueue(data.queue);
        }
    } catch (error) {
        console.error('Erro ao carregar fila:', error);
    }
}

/**
 * Renderizar fila de espera
 */
function renderQueue(queue) {
    const tbody = document.getElementById('queue-table-body');
    tbody.innerHTML = '';
    
    if (queue.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                    <div class="flex flex-col items-center justify-center">
                        <i class="fas fa-check-circle text-4xl mb-3 text-green-300"></i>
                        <p class="text-lg font-medium">Fila vazia</p>
                        <p class="text-sm mt-1">Não há conversas aguardando distribuição</p>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    queue.forEach(item => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-gray-50 dark:hover:bg-gray-700';
        
        const priorityBadge = getPriorityBadge(item.priority);
        
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900 dark:text-white">${escapeHtml(item.customer_name || 'Sem nome')}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">${escapeHtml(item.customer_phone || '')}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                ${item.department_name ? `
                    <span class="px-2 py-1 text-xs font-semibold rounded-full" style="background-color: ${item.department_color}20; color: ${item.department_color}">
                        ${escapeHtml(item.department_name)}
                    </span>
                ` : '<span class="text-gray-400">-</span>'}
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                ${priorityBadge}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                ${item.wait_time_formatted}
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                    Aguardando
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">
                <button onclick="assignManual(${item.id})" 
                        class="text-green-600 hover:text-green-800 transition-colors" 
                        title="Atribuir Manualmente">
                    <i class="fas fa-user-plus mr-1"></i>Atribuir
                </button>
            </td>
        `;
        
        tbody.appendChild(row);
    });
}

/**
 * Carregar histórico
 */
async function loadHistory() {
    try {
        const response = await fetch('/api/distribution.php?action=get_history&limit=50');
        const data = await response.json();
        
        if (data.success) {
            renderHistory(data.history);
        }
    } catch (error) {
        console.error('Erro ao carregar histórico:', error);
    }
}

/**
 * Renderizar histórico
 */
function renderHistory(history) {
    const tbody = document.getElementById('history-table-body');
    tbody.innerHTML = '';
    
    if (history.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                    <div class="flex flex-col items-center justify-center">
                        <i class="fas fa-history text-4xl mb-3 text-gray-300"></i>
                        <p class="text-lg font-medium">Nenhum histórico</p>
                        <p class="text-sm mt-1">Ainda não há distribuições registradas</p>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    history.forEach(item => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-gray-50 dark:hover:bg-gray-700';
        
        const typeLabels = {
            'round_robin': 'Rodízio',
            'least_busy': 'Menos Ocupado',
            'by_department': 'Por Setor',
            'manual': 'Manual'
        };
        
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                ${item.assigned_at_formatted}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                #${item.conversation_id}
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900 dark:text-white">${escapeHtml(item.attendant_name)}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">${escapeHtml(item.attendant_email)}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                    ${typeLabels[item.distribution_type] || item.distribution_type}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                ${item.wait_time_formatted}
            </td>
        `;
        
        tbody.appendChild(row);
    });
}

/**
 * Trocar de tab
 */
function switchTab(tab) {
    currentTab = tab;
    
    // Atualizar botões
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active', 'border-purple-600', 'text-purple-600');
        btn.classList.add('border-transparent', 'text-gray-500');
    });
    
    const activeBtn = document.getElementById(`tab-${tab}`);
    activeBtn.classList.add('active', 'border-purple-600', 'text-purple-600');
    activeBtn.classList.remove('border-transparent', 'text-gray-500');
    
    // Atualizar conteúdo
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    document.getElementById(`content-${tab}`).classList.remove('hidden');
    
    // Carregar dados da tab
    if (tab === 'queue') loadQueue();
    if (tab === 'history') loadHistory();
}

/**
 * Abrir modal de criação de regra
 */
function openCreateRuleModal() {
    editingRuleId = null;
    document.getElementById('rule-modal-title').innerHTML = '<i class="fas fa-cogs text-purple-600 mr-2"></i>Nova Regra de Distribuição';
    document.getElementById('rule-form').reset();
    document.getElementById('rule-id').value = '';
    document.getElementById('rule-active').checked = true;
    document.getElementById('rule-auto-assign').checked = true;
    document.getElementById('rule-notify').checked = true;
    document.getElementById('rule-modal').classList.remove('hidden');
}

/**
 * Editar regra
 */
async function editRule(id) {
    editingRuleId = id;
    
    try {
        const response = await fetch(`/api/distribution.php?action=get_rule&id=${id}`);
        const data = await response.json();
        
        if (data.success) {
            const r = data.rule;
            document.getElementById('rule-modal-title').innerHTML = '<i class="fas fa-edit text-blue-500 mr-2"></i>Editar Regra de Distribuição';
            document.getElementById('rule-id').value = r.id;
            document.getElementById('rule-name').value = r.name;
            document.getElementById('rule-type').value = r.type;
            document.getElementById('rule-priority').value = r.priority;
            document.getElementById('rule-max-conversations').value = r.max_conversations_per_attendant;
            document.getElementById('rule-work-start').value = r.work_hours_start.substring(0, 5);
            document.getElementById('rule-work-end').value = r.work_hours_end.substring(0, 5);
            document.getElementById('rule-auto-assign').checked = r.auto_assign == 1;
            document.getElementById('rule-notify').checked = r.notify_attendant == 1;
            document.getElementById('rule-active').checked = r.is_active == 1;
            document.getElementById('rule-modal').classList.remove('hidden');
        } else {
            alert('❌ Erro: ' + data.error);
        }
    } catch (error) {
        console.error('Erro ao carregar regra:', error);
        alert('❌ Erro ao carregar regra');
    }
}

/**
 * Salvar regra
 */
async function handleRuleSubmit(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    formData.append('action', editingRuleId ? 'update_rule' : 'create_rule');
    
    try {
        const response = await fetch('/api/distribution.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ ' + data.message);
            closeRuleModal();
            loadRules();
            loadStats();
        } else {
            alert('❌ Erro: ' + data.error);
        }
    } catch (error) {
        console.error('Erro ao salvar:', error);
        alert('❌ Erro ao salvar regra');
    }
}

/**
 * Excluir regra
 */
async function deleteRule(id) {
    if (!confirm('⚠️ Tem certeza que deseja excluir esta regra?\n\nEsta ação não pode ser desfeita.')) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'delete_rule');
        formData.append('id', id);
        
        const response = await fetch('/api/distribution.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ ' + data.message);
            loadRules();
            loadStats();
        } else {
            alert('❌ Erro: ' + data.error);
        }
    } catch (error) {
        console.error('Erro ao excluir:', error);
        alert('❌ Erro ao excluir regra');
    }
}

/**
 * Alternar status da regra
 */
async function toggleRule(id) {
    try {
        const formData = new FormData();
        formData.append('action', 'toggle_rule');
        formData.append('id', id);
        
        const response = await fetch('/api/distribution.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            loadRules();
            loadStats();
        } else {
            alert('❌ Erro: ' + data.error);
        }
    } catch (error) {
        console.error('Erro ao alternar status:', error);
        alert('❌ Erro ao alternar status');
    }
}

/**
 * Atribuir conversa manualmente
 */
async function assignManual(queueId) {
    // Aqui você pode abrir um modal para selecionar o atendente
    // Por enquanto, vou deixar um placeholder
    alert('Funcionalidade de atribuição manual em desenvolvimento.\n\nEm breve você poderá selecionar o atendente para esta conversa.');
}

/**
 * Fechar modal de regra
 */
function closeRuleModal() {
    document.getElementById('rule-modal').classList.add('hidden');
}

/**
 * Obter badge de prioridade
 */
function getPriorityBadge(priority) {
    if (priority >= 80) {
        return '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Alta</span>';
    } else if (priority >= 50) {
        return '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Média</span>';
    } else {
        return '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Baixa</span>';
    }
}

/**
 * Formatar tempo
 */
function formatTime(seconds) {
    if (!seconds || seconds == 0) return '0s';
    
    if (seconds < 60) {
        return seconds + 's';
    }
    
    const minutes = Math.floor(seconds / 60);
    const secs = seconds % 60;
    
    if (minutes < 60) {
        return minutes + 'min' + (secs > 0 ? ' ' + secs + 's' : '');
    }
    
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    
    return hours + 'h' + (mins > 0 ? ' ' + mins + 'min' : '');
}

/**
 * Escapar HTML
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
