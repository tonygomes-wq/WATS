/**
 * JavaScript para Configurações de Email
 */

let currentTab = 'smtp';

document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadSettings();
    loadPreferences();
});

/**
 * Desconectar conta Microsoft
 */
async function disconnectMicrosoft() {
    if (!confirm('Tem certeza que deseja desconectar a conta Microsoft?\n\nVocê precisará reconectar para enviar emails novamente.')) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'disconnect_oauth');
        
        const response = await fetch('api/email_notifications.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Conta Microsoft desconectada com sucesso!');
            // Limpar parâmetros da URL e recarregar
            window.location.href = window.location.pathname;
        } else {
            alert('❌ Erro: ' + (data.error || 'Falha ao desconectar'));
        }
    } catch (error) {
        console.error('Erro ao desconectar:', error);
        alert('❌ Erro ao desconectar conta Microsoft');
    }
}

/**
 * Carregar estatísticas
 */
async function loadStats() {
    try {
        const response = await fetch('api/email_notifications.php?action=get_stats');
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('total-sent').textContent = data.stats.total_sent;
            document.getElementById('total-failed').textContent = data.stats.total_failed;
            document.getElementById('sent-today').textContent = data.stats.sent_today;
            document.getElementById('success-rate').textContent = data.stats.success_rate + '%';
        }
    } catch (error) {
        console.error('Erro ao carregar estatísticas:', error);
    }
}

/**
 * Carregar configurações SMTP
 */
async function loadSettings() {
    try {
        const response = await fetch('api/email_notifications.php?action=get_settings');
        const data = await response.json();
        
        if (data.success && data.settings) {
            const s = data.settings;
            document.getElementById('smtp-host').value = s.smtp_host || '';
            document.getElementById('smtp-port').value = s.smtp_port || '';
            document.getElementById('smtp-username').value = s.smtp_username || '';
            document.getElementById('smtp-password').value = s.smtp_password || '';
            document.getElementById('smtp-encryption').value = s.smtp_encryption || 'tls';
            document.getElementById('from-email').value = s.from_email || '';
            document.getElementById('from-name').value = s.from_name || '';
            document.getElementById('is-enabled').checked = s.is_enabled == 1;
        }
    } catch (error) {
        console.error('Erro ao carregar configurações:', error);
    }
}

/**
 * Salvar configurações SMTP
 */
async function saveSettings(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    formData.append('action', 'save_settings');
    
    try {
        const response = await fetch('api/email_notifications.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ ' + data.message);
        } else {
            alert('❌ Erro: ' + data.error);
        }
    } catch (error) {
        console.error('Erro ao salvar:', error);
        alert('❌ Erro ao salvar configurações');
    }
}

/**
 * Carregar preferências
 */
async function loadPreferences() {
    try {
        const response = await fetch('api/email_notifications.php?action=get_preferences');
        const data = await response.json();
        
        if (data.success && data.preferences) {
            const p = data.preferences;
            const form = document.getElementById('preferences-form');
            
            form.querySelector('[name="notify_new_conversation"]').checked = p.notify_new_conversation == 1;
            form.querySelector('[name="notify_conversation_assigned"]').checked = p.notify_conversation_assigned == 1;
            form.querySelector('[name="notify_sla_warning"]').checked = p.notify_sla_warning == 1;
            form.querySelector('[name="daily_summary"]').checked = p.daily_summary == 1;
            form.querySelector('[name="daily_summary_time"]').value = p.daily_summary_time ? p.daily_summary_time.substring(0, 5) : '18:00';
            form.querySelector('[name="weekly_summary"]').checked = p.weekly_summary == 1;
        }
    } catch (error) {
        console.error('Erro ao carregar preferências:', error);
    }
}

/**
 * Salvar preferências
 */
async function savePreferences(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    formData.append('action', 'save_preferences');
    
    try {
        const response = await fetch('api/email_notifications.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ ' + data.message);
        } else {
            alert('❌ Erro: ' + data.error);
        }
    } catch (error) {
        console.error('Erro ao salvar:', error);
        alert('❌ Erro ao salvar preferências');
    }
}

/**
 * Carregar templates
 */
async function loadTemplates() {
    try {
        const response = await fetch('api/email_notifications.php?action=list_templates');
        const data = await response.json();
        
        if (data.success) {
            renderTemplates(data.templates);
        }
    } catch (error) {
        console.error('Erro ao carregar templates:', error);
    }
}

/**
 * Renderizar templates
 */
function renderTemplates(templates) {
    const container = document.getElementById('templates-list');
    container.innerHTML = '';
    
    if (templates.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-8">Nenhum template encontrado</p>';
        return;
    }
    
    templates.forEach(template => {
        const div = document.createElement('div');
        div.className = 'border border-gray-200 dark:border-gray-700 rounded-lg p-4 mb-4';
        
        const typeLabels = {
            'new_conversation': 'Nova Conversa',
            'assigned': 'Atribuída',
            'sla_warning': 'Alerta SLA',
            'daily_summary': 'Resumo Diário'
        };
        
        div.innerHTML = `
            <div class="flex justify-between items-start">
                <div>
                    <h4 class="font-semibold text-gray-900 dark:text-white">${escapeHtml(template.name)}</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400">${typeLabels[template.type] || template.type}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-500 mt-2"><strong>Assunto:</strong> ${escapeHtml(template.subject)}</p>
                </div>
                <div class="flex gap-2">
                    ${template.is_default ? '<span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">Padrão</span>' : ''}
                    ${template.is_active ? '<span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">Ativo</span>' : '<span class="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded">Inativo</span>'}
                </div>
            </div>
        `;
        
        container.appendChild(div);
    });
}

/**
 * Carregar logs
 */
async function loadLogs() {
    try {
        const response = await fetch('api/email_notifications.php?action=get_logs&limit=50');
        const data = await response.json();
        
        if (data.success) {
            renderLogs(data.logs);
        }
    } catch (error) {
        console.error('Erro ao carregar logs:', error);
    }
}

/**
 * Renderizar logs
 */
function renderLogs(logs) {
    const tbody = document.getElementById('logs-table-body');
    tbody.innerHTML = '';
    
    if (logs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="px-6 py-8 text-center text-gray-500">Nenhum log encontrado</td></tr>';
        return;
    }
    
    logs.forEach(log => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-gray-50 dark:hover:bg-gray-700';
        
        const statusBadge = log.status === 'sent'
            ? '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Enviado</span>'
            : '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Falhou</span>';
        
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">${log.created_at_formatted}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">${escapeHtml(log.recipient_email)}</td>
            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">${escapeHtml(log.subject || '-')}</td>
            <td class="px-6 py-4 whitespace-nowrap">${statusBadge}</td>
        `;
        
        tbody.appendChild(row);
    });
}

/**
 * Testar envio de email
 */
async function testEmail() {
    const email = prompt('Digite o email para teste:');
    
    if (!email) return;
    
    const formData = new FormData();
    formData.append('action', 'test_email');
    formData.append('to', email);
    
    try {
        const response = await fetch('api/email_notifications.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Email de teste enviado com sucesso!');
            loadStats();
            if (currentTab === 'logs') loadLogs();
        } else {
            alert('❌ Erro ao enviar: ' + data.error);
        }
    } catch (error) {
        console.error('Erro ao testar:', error);
        alert('❌ Erro ao enviar email de teste');
    }
}

/**
 * Trocar de tab
 */
function switchTab(tab) {
    currentTab = tab;
    
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active', 'border-blue-600', 'text-blue-600');
        btn.classList.add('border-transparent', 'text-gray-500');
    });
    
    document.getElementById(`tab-${tab}`).classList.add('active', 'border-blue-600', 'text-blue-600');
    document.getElementById(`tab-${tab}`).classList.remove('border-transparent', 'text-gray-500');
    
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    document.getElementById(`content-${tab}`).classList.remove('hidden');
    
    if (tab === 'templates') loadTemplates();
    if (tab === 'logs') loadLogs();
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
