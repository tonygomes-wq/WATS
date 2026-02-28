<?php
/**
 * Página de Listagem de Automation Flows
 * Sistema de automação baseado em IA com triggers automáticos
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

// Verificar permissão - Apenas Admin
$canManageFlows = isAdmin();
if (!$canManageFlows) {
    header('Location: dashboard.php');
    exit;
}

$userId = $_SESSION['user_id'];
$pageTitle = 'Automation Flows';
require_once 'includes/header_spa.php';
?>

<style>
    .flow-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }
    
    .flow-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        border-color: #10B981;
    }
    
    .flow-status {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .flow-status.active {
        background: #D1FAE5;
        color: #065F46;
    }
    
    .flow-status.paused {
        background: #E5E7EB;
        color: #374151;
    }
    
    .trigger-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.25rem 0.5rem;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 500;
    }
    
    .trigger-badge.keyword { background: #DBEAFE; color: #1E40AF; }
    .trigger-badge.first_message { background: #FEF3C7; color: #92400E; }
    .trigger-badge.off_hours { background: #E0E7FF; color: #3730A3; }
    .trigger-badge.no_response { background: #FCE7F3; color: #9D174D; }
    .trigger-badge.manual { background: #F3F4F6; color: #374151; }
    
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
        border-radius: 16px;
        border: 2px dashed #10B981;
    }
    
    .empty-state i {
        font-size: 4rem;
        color: #10B981;
        margin-bottom: 1rem;
    }
    
    .btn-create {
        background: linear-gradient(135deg, #10B981 0%, #059669 100%);
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
    }
    
    .btn-create:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    }
    
    .filter-btn {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s ease;
        border: 2px solid transparent;
        background: transparent;
        cursor: pointer;
    }
    
    .filter-btn:hover {
        background: rgba(16, 185, 129, 0.1);
    }
    
    .filter-btn.active {
        background: #10B981;
        color: white;
        border-color: #10B981;
    }
    
    /* Modo escuro */
    :root[data-theme="dark"] .flow-card {
        background: #1f2937;
    }
    
    :root[data-theme="dark"] .flow-card:hover {
        border-color: #10B981;
    }
    
    :root[data-theme="dark"] .empty-state {
        background: linear-gradient(135deg, #064e3b 0%, #065f46 100%);
        border-color: #10B981;
    }
    
    :root[data-theme="dark"] .empty-state h3,
    :root[data-theme="dark"] .empty-state p {
        color: #f3f4f6;
    }
    
    :root[data-theme="dark"] .filter-btn {
        color: #d1d5db;
    }
    
    :root[data-theme="dark"] .filter-btn:hover {
        background: rgba(16, 185, 129, 0.2);
    }
    
    :root[data-theme="dark"] .filter-btn.active {
        color: white;
    }
</style>

<div class="p-6 min-h-screen">
    <!-- Header -->
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white flex items-center gap-3">
                <i class="fas fa-robot text-green-600"></i>
                Automation Flows
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">
                Automações inteligentes baseadas em IA com triggers automáticos
            </p>
        </div>
        
        <button onclick="createNewFlow()" class="btn-create">
            <i class="fas fa-plus"></i>
            Novo Automation Flow
        </button>
    </div>
    
    <!-- Filtros de Status -->
    <div class="mb-6 flex gap-2">
        <button class="filter-btn active" data-filter="all" onclick="filterFlows('all')">
            <i class="fas fa-list mr-1"></i> Todos
        </button>
        <button class="filter-btn" data-filter="active" onclick="filterFlows('active')">
            <i class="fas fa-check-circle mr-1"></i> Ativos
        </button>
        <button class="filter-btn" data-filter="paused" onclick="filterFlows('paused')">
            <i class="fas fa-pause-circle mr-1"></i> Pausados
        </button>
    </div>
    
    <!-- Grid de Fluxos -->
    <div id="flowsGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Carregando -->
        <div class="col-span-full text-center py-12">
            <i class="fas fa-spinner fa-spin text-4xl text-green-600 mb-4"></i>
            <p class="text-gray-500 dark:text-gray-400">Carregando automation flows...</p>
        </div>
    </div>
</div>

<!-- Modal Confirmar Exclusão -->
<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-sm mx-4 p-6">
        <div class="text-center">
            <i class="fas fa-exclamation-triangle text-5xl text-red-500 mb-4"></i>
            <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Excluir Automation Flow?</h3>
            <p class="text-gray-600 dark:text-gray-400 mb-6">Esta ação não pode ser desfeita.</p>
        </div>
        
        <div class="flex gap-3">
            <button onclick="closeDeleteModal()" 
                    class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                Cancelar
            </button>
            <button onclick="confirmDelete()" 
                    class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition">
                <i class="fas fa-trash mr-2"></i>
                Excluir
            </button>
        </div>
    </div>
</div>

<script>
let flows = [];
let currentFilter = 'all';
let deleteFlowId = null;

// Carregar fluxos ao iniciar
document.addEventListener('DOMContentLoaded', loadFlows);

async function loadFlows() {
    try {
        const response = await fetch('api/automation_flows.php?action=list');
        const data = await response.json();
        
        if (data.success) {
            flows = data.flows || [];
            renderFlows();
        } else {
            showToast(data.message || 'Erro ao carregar flows', 'error');
        }
    } catch (error) {
        console.error('Erro:', error);
        showToast('Erro ao conectar com o servidor', 'error');
    }
}

function renderFlows() {
    const grid = document.getElementById('flowsGrid');
    
    // Filtrar flows
    let filteredFlows = flows;
    if (currentFilter !== 'all') {
        filteredFlows = flows.filter(f => f.status === currentFilter);
    }
    
    if (filteredFlows.length === 0) {
        if (flows.length === 0) {
            // Nenhum flow criado
            grid.innerHTML = `
                <div class="col-span-full">
                    <div class="empty-state">
                        <i class="fas fa-robot"></i>
                        <h3 class="text-2xl font-bold text-gray-800 dark:text-white mb-2">Nenhum automation flow criado</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-6">Crie seu primeiro automation flow para automatizar conversas com IA.</p>
                        <button onclick="createNewFlow()" class="btn-create">
                            <i class="fas fa-plus"></i>
                            Criar Primeiro Flow
                        </button>
                    </div>
                </div>
            `;
        } else {
            // Nenhum flow no filtro atual
            grid.innerHTML = `
                <div class="col-span-full text-center py-12">
                    <i class="fas fa-filter text-4xl text-gray-400 mb-4"></i>
                    <p class="text-gray-500 dark:text-gray-400">Nenhum flow ${currentFilter === 'active' ? 'ativo' : 'pausado'} encontrado.</p>
                </div>
            `;
        }
        return;
    }
    
    grid.innerHTML = filteredFlows.map(flow => createFlowCard(flow)).join('');
}

function createFlowCard(flow) {
    const triggerLabel = getTriggerLabel(flow.trigger_type);
    const statusLabel = flow.status === 'active' ? 'Ativo' : 'Pausado';
    const statusIcon = flow.status === 'active' ? 'fa-check-circle' : 'fa-pause-circle';
    
    return `
        <div class="flow-card">
            <div class="flex items-start justify-between mb-3">
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white">${escapeHtml(flow.name)}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">${escapeHtml(flow.description || 'Sem descrição')}</p>
                </div>
                <span class="flow-status ${flow.status}">
                    <i class="fas ${statusIcon} text-xs"></i>
                    ${statusLabel}
                </span>
            </div>
            
            <div class="mb-4">
                <span class="trigger-badge ${flow.trigger_type}">
                    <i class="fas ${getTriggerIcon(flow.trigger_type)}"></i>
                    ${triggerLabel}
                </span>
            </div>
            
            <div class="flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400 mb-4">
                <span><i class="fas fa-calendar mr-1"></i> ${formatDate(flow.updated_at)}</span>
            </div>
            
            <div class="flex gap-2">
                <a href="flow_automation_editor.php?id=${flow.id}" 
                   class="flex-1 px-3 py-2 bg-green-600 hover:bg-green-700 text-white text-center rounded-lg text-sm font-medium transition">
                    <i class="fas fa-edit mr-1"></i> Editar
                </a>
                <button onclick="toggleStatus(${flow.id}, '${flow.status}')" 
                        class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                        title="${flow.status === 'active' ? 'Pausar' : 'Ativar'}">
                    <i class="fas ${flow.status === 'active' ? 'fa-pause' : 'fa-play'}"></i>
                </button>
                <button onclick="openDeleteModal(${flow.id})" 
                        class="px-3 py-2 border border-red-300 rounded-lg text-red-600 hover:bg-red-50 dark:hover:bg-red-900 transition"
                        title="Excluir">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
}

function getTriggerLabel(type) {
    const labels = {
        'keyword': 'Palavra-chave',
        'first_message': 'Primeira Mensagem',
        'off_hours': 'Fora de Horário',
        'no_response': 'Sem Resposta',
        'manual': 'Manual'
    };
    return labels[type] || type;
}

function getTriggerIcon(type) {
    const icons = {
        'keyword': 'fa-key',
        'first_message': 'fa-comment-dots',
        'off_hours': 'fa-moon',
        'no_response': 'fa-clock',
        'manual': 'fa-hand-pointer'
    };
    return icons[type] || 'fa-cog';
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function filterFlows(filter) {
    currentFilter = filter;
    
    // Atualizar botões de filtro
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-filter="${filter}"]`).classList.add('active');
    
    renderFlows();
}

function createNewFlow() {
    window.location.href = 'flow_automation_editor.php';
}

async function toggleStatus(id, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'paused' : 'active';
    const action = newStatus === 'active' ? 'ativar' : 'pausar';
    
    if (!confirm(`Deseja ${action} este automation flow?`)) {
        return;
    }
    
    try {
        const response = await fetch('api/automation_flows.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle_status', id: id })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(`Flow ${newStatus === 'active' ? 'ativado' : 'pausado'} com sucesso`, 'success');
            loadFlows();
        } else {
            showToast(data.message || 'Erro ao atualizar status', 'error');
        }
    } catch (error) {
        console.error('Erro:', error);
        showToast('Erro ao atualizar status', 'error');
    }
}

function openDeleteModal(id) {
    deleteFlowId = id;
    document.getElementById('deleteModal').classList.remove('hidden');
    document.getElementById('deleteModal').classList.add('flex');
}

function closeDeleteModal() {
    deleteFlowId = null;
    document.getElementById('deleteModal').classList.add('hidden');
    document.getElementById('deleteModal').classList.remove('flex');
}

async function confirmDelete() {
    if (!deleteFlowId) return;
    
    try {
        const response = await fetch('api/automation_flows.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id: deleteFlowId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Flow excluído com sucesso', 'success');
            closeDeleteModal();
            loadFlows();
        } else {
            showToast(data.message || 'Erro ao excluir flow', 'error');
        }
    } catch (error) {
        console.error('Erro:', error);
        showToast('Erro ao excluir flow', 'error');
    }
}

function showToast(message, type = 'info') {
    // Implementação simples de toast - pode ser melhorada com biblioteca
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white z-50 ${
        type === 'success' ? 'bg-green-600' : 
        type === 'error' ? 'bg-red-600' : 
        'bg-blue-600'
    }`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// Fechar modais com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDeleteModal();
    }
});
</script>

<?php include 'includes/footer_spa.php'; ?>
