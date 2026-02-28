/**
 * JavaScript para Gerenciamento de Respostas Rápidas
 */

let allTemplates = [];
let editingTemplateId = null;

// Carregar ao iniciar
document.addEventListener('DOMContentLoaded', function() {
    loadTemplates();
    loadStats();
    loadCategories();
    
    // Preview em tempo real
    document.getElementById('template-message').addEventListener('input', updatePreview);
});

/**
 * Carregar todos os templates
 */
async function loadTemplates() {
    try {
        const response = await fetch('/api/quick_replies.php?action=list');
        const data = await response.json();
        
        if (data.success) {
            allTemplates = data.templates;
            renderTemplates(allTemplates);
        }
    } catch (error) {
        console.error('Erro ao carregar templates:', error);
    }
}

/**
 * Carregar estatísticas
 */
async function loadStats() {
    try {
        const response = await fetch('/api/quick_replies.php?action=stats');
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('total-templates').textContent = data.stats.total || '0';
            document.getElementById('active-templates').textContent = data.stats.active || '0';
            document.getElementById('total-uses').textContent = data.stats.total_uses || '0';
            document.getElementById('total-categories').textContent = data.stats.categories || '0';
        }
    } catch (error) {
        console.error('Erro ao carregar estatísticas:', error);
    }
}

/**
 * Carregar categorias para filtros
 */
async function loadCategories() {
    try {
        const response = await fetch('/api/quick_replies.php?action=categories');
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('category-filter');
            const datalist = document.getElementById('categories-list');
            
            data.categories.forEach(cat => {
                const option = document.createElement('option');
                option.value = cat;
                option.textContent = cat;
                select.appendChild(option.cloneNode(true));
                datalist.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar categorias:', error);
    }
}

/**
 * Renderizar templates na tabela
 */
function renderTemplates(templates) {
    const tbody = document.getElementById('templates-table-body');
    tbody.innerHTML = '';
    
    if (templates.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                    <div class="flex flex-col items-center justify-center">
                        <i class="fas fa-inbox text-4xl mb-3 text-gray-300"></i>
                        <p class="text-lg font-medium">Nenhum template encontrado</p>
                        <p class="text-sm mt-1">Clique em "Nova Resposta Rápida" para criar seu primeiro template</p>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    templates.forEach(template => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors';
        
        const statusBadge = template.is_active == 1
            ? '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Ativo</span>'
            : '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Inativo</span>';
        
        const messagePreview = template.message.length > 100 
            ? template.message.substring(0, 100) + '...' 
            : template.message;
        
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                    <div class="flex-shrink-0 h-10 w-10 bg-yellow-100 dark:bg-yellow-900 rounded-full flex items-center justify-center">
                        <i class="fas fa-bolt text-yellow-600 dark:text-yellow-400"></i>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">${escapeHtml(template.name)}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">/${template.shortcut}</div>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4">
                <div class="text-sm text-gray-600 dark:text-gray-400 max-w-md">
                    <div class="line-clamp-2">${escapeHtml(messagePreview)}</div>
                </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                    ${escapeHtml(template.category)}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                <div class="flex items-center gap-1">
                    <i class="fas fa-chart-bar text-gray-400 text-xs"></i>
                    ${template.usage_count}
                </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                ${statusBadge}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">
                <div class="flex items-center gap-2">
                    <button onclick="editTemplate(${template.id})" 
                            class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors" 
                            title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="toggleTemplateStatus(${template.id})" 
                            class="text-yellow-600 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-300 transition-colors" 
                            title="Ativar/Desativar">
                        <i class="fas fa-toggle-${template.is_active == 1 ? 'on' : 'off'}"></i>
                    </button>
                    <button onclick="deleteTemplate(${template.id})" 
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
 * Filtrar templates
 */
function filterTemplates() {
    const search = document.getElementById('search-input').value.toLowerCase();
    const category = document.getElementById('category-filter').value;
    const status = document.getElementById('status-filter').value;
    const sort = document.getElementById('sort-filter').value;
    
    let filtered = allTemplates.filter(t => {
        const matchSearch = !search || 
            t.name.toLowerCase().includes(search) || 
            t.shortcut.toLowerCase().includes(search) ||
            t.message.toLowerCase().includes(search);
        
        const matchCategory = !category || t.category === category;
        const matchStatus = !status || t.is_active == status;
        
        return matchSearch && matchCategory && matchStatus;
    });
    
    // Ordenar
    filtered.sort((a, b) => {
        switch(sort) {
            case 'usage':
                return b.usage_count - a.usage_count;
            case 'recent':
                return new Date(b.created_at) - new Date(a.created_at);
            default: // name
                return a.name.localeCompare(b.name);
        }
    });
    
    renderTemplates(filtered);
}

/**
 * Abrir modal de criação
 */
function openCreateModal() {
    editingTemplateId = null;
    document.getElementById('modal-title').innerHTML = '<i class="fas fa-bolt text-yellow-500 mr-2"></i>Nova Resposta Rápida';
    document.getElementById('template-form').reset();
    document.getElementById('template-id').value = '';
    document.getElementById('template-active').checked = true;
    document.getElementById('template-category').value = 'Geral';
    updatePreview();
    document.getElementById('template-modal').classList.remove('hidden');
    document.getElementById('template-modal').classList.add('flex');
}

/**
 * Editar template
 */
async function editTemplate(id) {
    editingTemplateId = id;
    
    try {
        const response = await fetch(`/api/quick_replies.php?action=get&id=${id}`);
        const data = await response.json();
        
        if (data.success) {
            const t = data.template;
            document.getElementById('modal-title').innerHTML = '<i class="fas fa-edit text-blue-500 mr-2"></i>Editar Resposta Rápida';
            document.getElementById('template-id').value = t.id;
            document.getElementById('template-name').value = t.name;
            document.getElementById('template-shortcut').value = t.shortcut;
            document.getElementById('template-category').value = t.category;
            document.getElementById('template-message').value = t.message;
            document.getElementById('template-active').checked = t.is_active == 1;
            updatePreview();
            document.getElementById('template-modal').classList.remove('hidden');
            document.getElementById('template-modal').classList.add('flex');
        } else {
            alert('Erro: ' + data.error);
        }
    } catch (error) {
        console.error('Erro ao carregar template:', error);
        alert('Erro ao carregar template');
    }
}

/**
 * Salvar template (criar ou editar)
 */
async function handleSubmit(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    formData.append('action', editingTemplateId ? 'update' : 'create');
    
    try {
        const response = await fetch('/api/quick_replies.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ ' + data.message);
            closeModal();
            loadTemplates();
            loadStats();
            loadCategories();
        } else {
            alert('❌ Erro: ' + data.error);
        }
    } catch (error) {
        console.error('Erro ao salvar:', error);
        alert('❌ Erro ao salvar template');
    }
}

/**
 * Excluir template
 */
async function deleteTemplate(id) {
    if (!confirm('⚠️ Tem certeza que deseja excluir este template?\n\nEsta ação não pode ser desfeita.')) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        
        const response = await fetch('/api/quick_replies.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ ' + data.message);
            loadTemplates();
            loadStats();
        } else {
            alert('❌ Erro: ' + data.error);
        }
    } catch (error) {
        console.error('Erro ao excluir:', error);
        alert('❌ Erro ao excluir template');
    }
}

/**
 * Alternar status ativo/inativo
 */
async function toggleTemplateStatus(id) {
    try {
        const formData = new FormData();
        formData.append('action', 'toggle_status');
        formData.append('id', id);
        
        const response = await fetch('/api/quick_replies.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            loadTemplates();
            loadStats();
        } else {
            alert('❌ Erro: ' + data.error);
        }
    } catch (error) {
        console.error('Erro ao alterar status:', error);
        alert('❌ Erro ao alterar status');
    }
}

/**
 * Atualizar preview da mensagem
 */
function updatePreview() {
    const message = document.getElementById('template-message').value;
    const preview = document.getElementById('message-preview');
    
    if (!message) {
        preview.innerHTML = '<span class="text-gray-400 italic">Digite uma mensagem para ver o preview...</span>';
        return;
    }
    
    // Substituir variáveis por exemplos
    let previewText = message
        .replace(/{nome}/g, '<strong class="text-blue-600">João Silva</strong>')
        .replace(/{telefone}/g, '<strong class="text-blue-600">(11) 98765-4321</strong>')
        .replace(/{email}/g, '<strong class="text-blue-600">joao@email.com</strong>')
        .replace(/{empresa}/g, '<strong class="text-blue-600">Empresa XYZ</strong>')
        .replace(/{atendente}/g, '<strong class="text-blue-600">Maria</strong>')
        .replace(/{data}/g, '<strong class="text-blue-600">' + new Date().toLocaleDateString('pt-BR') + '</strong>')
        .replace(/{hora}/g, '<strong class="text-blue-600">' + new Date().toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'}) + '</strong>');
    
    // Preservar quebras de linha
    previewText = previewText.replace(/\n/g, '<br>');
    
    preview.innerHTML = previewText;
}

/**
 * Fechar modal
 */
function closeModal() {
    document.getElementById('template-modal').classList.add('hidden');
    document.getElementById('template-modal').classList.remove('flex');
}

/**
 * Escapar HTML para prevenir XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Função auxiliar para usar template no chat (será implementada na integração)
 */
async function useTemplate(id) {
    try {
        const formData = new FormData();
        formData.append('action', 'use');
        formData.append('id', id);
        
        await fetch('/api/quick_replies.php', {
            method: 'POST',
            body: formData
        });
    } catch (error) {
        console.error('Erro ao registrar uso:', error);
    }
}
