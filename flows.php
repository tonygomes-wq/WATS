<?php
/**
 * Página de Listagem de Fluxos de Automação
 * Sistema tipo Typebot para criação visual de chatbots
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

// Verificar permissão - Admin OU Atendente com permissão e instância própria
$isAdmin = isAdmin();
$isAttendant = isAttendant();
$canManageFlows = false;
$userId = $_SESSION['user_id'];
$ownerType = 'supervisor';
$ownerId = $userId;

if ($isAdmin) {
    $canManageFlows = true;
    $ownerType = 'supervisor';
    $ownerId = $userId;
} elseif ($isAttendant) {
    // Verificar se atendente tem permissão para gerenciar fluxos
    $stmt = $pdo->prepare("
        SELECT su.can_manage_flows, ai.instance_name, ai.status
        FROM supervisor_users su
        LEFT JOIN attendant_instances ai ON su.id = ai.attendant_id
        WHERE su.id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $attendantData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($attendantData) {
        $hasPermission = (bool)($attendantData['can_manage_flows'] ?? false);
        $hasInstance = !empty($attendantData['instance_name']) && $attendantData['status'] === 'connected';
        
        if ($hasPermission && $hasInstance) {
            $canManageFlows = true;
            $ownerType = 'attendant';
            $ownerId = $userId;
        }
    }
}

if (!$canManageFlows) {
    header('Location: dashboard.php');
    exit;
}
$pageTitle = 'Fluxos de Atendimento';
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
    
    .flow-status.draft {
        background: #FEF3C7;
        color: #92400E;
    }
    
    .flow-status.published {
        background: #D1FAE5;
        color: #065F46;
    }
    
    .flow-status.paused {
        background: #E5E7EB;
        color: #374151;
    }
    
    .flow-type-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.25rem 0.625rem;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 600;
        margin-top: 0.5rem;
    }
    
    .flow-type-badge.conversational {
        background: #DBEAFE;
        color: #1E40AF;
    }
    
    .flow-type-badge.automation {
        background: #FEF3C7;
        color: #92400E;
    }
    
    .filter-btn, .filter-btn-status {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s;
        border: 2px solid transparent;
        background: white;
        color: #6B7280;
        cursor: pointer;
    }
    
    .filter-btn:hover, .filter-btn-status:hover {
        background: #F3F4F6;
    }
    
    .filter-btn.active {
        background: #10B981;
        color: white;
        border-color: #10B981;
    }
    
    .filter-btn-status.active {
        background: #3B82F6;
        color: white;
        border-color: #3B82F6;
    }
    
    .flow-type-option {
        cursor: pointer;
    }
    
    .flow-type-option input[type="radio"] {
        display: none;
    }
    
    .flow-type-option .option-card {
        padding: 1.5rem;
        border: 2px solid #E5E7EB;
        border-radius: 12px;
        text-align: center;
        transition: all 0.3s;
        background: white;
    }
    
    .flow-type-option input[type="radio"]:checked + .option-card {
        border-color: #10B981;
        background: #F0FDF4;
    }
    
    .flow-type-option .option-card:hover {
        border-color: #10B981;
        transform: translateY(-2px);
    }
    
    .flow-type-option .option-card i {
        color: #10B981;
    }
    
    .flow-type-option .option-card h4 {
        margin: 0.5rem 0 0.25rem;
        color: #1F2937;
    }
    
    .flow-type-option .option-card p {
        margin: 0;
        color: #6B7280;
    }
    
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
    
    .node-type-preview {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
    }
    
    .node-type-badge {
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.7rem;
        font-weight: 500;
    }
    
    .node-type-badge.message { background: #DBEAFE; color: #1E40AF; }
    .node-type-badge.input { background: #FEF3C7; color: #92400E; }
    .node-type-badge.condition { background: #E0E7FF; color: #3730A3; }
    .node-type-badge.delay { background: #FCE7F3; color: #9D174D; }
    
    /* Modo escuro */
    :root[data-theme="dark"] .flow-card {
        background: #1f2937;
    }
    
    :root[data-theme="dark"] .flow-card:hover {
        border-color: #10B981;
    }
    
    :root[data-theme="dark"] .filter-btn,
    :root[data-theme="dark"] .filter-btn-status {
        background: #374151;
        color: #D1D5DB;
    }
    
    :root[data-theme="dark"] .filter-btn:hover,
    :root[data-theme="dark"] .filter-btn-status:hover {
        background: #4B5563;
    }
    
    :root[data-theme="dark"] .flow-type-option .option-card {
        background: #374151;
        border-color: #4B5563;
    }
    
    :root[data-theme="dark"] .flow-type-option input[type="radio"]:checked + .option-card {
        background: #064e3b;
        border-color: #10B981;
    }
    
    :root[data-theme="dark"] .flow-type-option .option-card h4 {
        color: #F3F4F6;
    }
    
    :root[data-theme="dark"] .flow-type-option .option-card p {
        color: #9CA3AF;
    }
    
    :root[data-theme="dark"] .empty-state {
        background: linear-gradient(135deg, #064e3b 0%, #065f46 100%);
        border-color: #10B981;
    }
    
    :root[data-theme="dark"] .empty-state h3,
    :root[data-theme="dark"] .empty-state p {
        color: #f3f4f6;
    }
</style>

<div class="p-6 min-h-screen">
    <!-- Header -->
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white flex items-center gap-3">
                <i class="fas fa-project-diagram text-green-600"></i>
                Fluxos de Automação
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">
                Crie chatbots visuais para automatizar conversas
            </p>
        </div>
        
        <button onclick="openCreateModal()" class="btn-create">
            <i class="fas fa-plus"></i>
            Novo Fluxo
        </button>
    </div>
    
    <!-- Filtros -->
    <div class="mb-6 flex flex-wrap gap-3">
        <button onclick="filterFlows('all')" id="filter-all" class="filter-btn active">
            <i class="fas fa-th mr-2"></i>
            Todos
        </button>
        <button onclick="filterFlows('conversational')" id="filter-conversational" class="filter-btn">
            <i class="fas fa-project-diagram mr-2"></i>
            Conversacionais
        </button>
        <button onclick="filterFlows('automation')" id="filter-automation" class="filter-btn">
            <i class="fas fa-robot mr-2"></i>
            Automação IA
        </button>
        <div class="ml-auto flex gap-2">
            <button onclick="filterFlows('published')" id="filter-published" class="filter-btn-status">
                <i class="fas fa-check-circle mr-1"></i>
                Publicados
            </button>
            <button onclick="filterFlows('draft')" id="filter-draft" class="filter-btn-status">
                <i class="fas fa-edit mr-1"></i>
                Rascunhos
            </button>
        </div>
    </div>
    
    <!-- Grid de Fluxos -->
    <div id="flowsGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Carregando -->
        <div class="col-span-full text-center py-12">
            <i class="fas fa-spinner fa-spin text-4xl text-green-600 mb-4"></i>
            <p class="text-gray-500">Carregando fluxos...</p>
        </div>
    </div>
</div>

<!-- Modal Criar/Editar Fluxo -->
<div id="flowModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-2xl mx-4 overflow-hidden">
        <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4">
            <h3 class="text-xl font-bold text-white" id="modalTitle">Novo Fluxo</h3>
        </div>
        
        <form id="flowForm" class="p-6 space-y-4">
            <input type="hidden" id="flowId" name="id" value="">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Nome do Fluxo *
                </label>
                <input type="text" id="flowName" name="name" required
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white"
                       placeholder="Ex: Atendimento Inicial">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Descrição
                </label>
                <textarea id="flowDescription" name="description" rows="3"
                          class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white"
                          placeholder="Descreva o objetivo deste fluxo..."></textarea>
            </div>
            
            <div id="flowTypeSection">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                    Tipo de Fluxo *
                </label>
                <div class="grid grid-cols-2 gap-4">
                    <label class="flow-type-option">
                        <input type="radio" name="flow_type" value="conversational" checked>
                        <div class="option-card">
                            <i class="fas fa-project-diagram text-4xl"></i>
                            <h4 class="font-bold text-base">Conversacional</h4>
                            <p class="text-xs">Editor visual com nós e conexões</p>
                        </div>
                    </label>
                    <label class="flow-type-option">
                        <input type="radio" name="flow_type" value="automation">
                        <div class="option-card">
                            <i class="fas fa-robot text-4xl"></i>
                            <h4 class="font-bold text-base">Automação IA</h4>
                            <p class="text-xs">Triggers automáticos com IA</p>
                        </div>
                    </label>
                </div>
            </div>
            
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeModal()" 
                        class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    Cancelar
                </button>
                <button type="submit" 
                        class="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition">
                    <i class="fas fa-save mr-2"></i>
                    Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Confirmar Exclusão -->
<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-sm mx-4 p-6">
        <div class="text-center">
            <i class="fas fa-exclamation-triangle text-5xl text-red-500 mb-4"></i>
            <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Excluir Fluxo?</h3>
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
let deleteFlowId = null;
let currentFilter = 'all';
let currentStatusFilter = null;

// Carregar fluxos ao iniciar
document.addEventListener('DOMContentLoaded', loadFlows);

async function loadFlows() {
    try {
        const response = await fetch('api/bot_flows.php?action=list');
        const data = await response.json();
        
        if (data.success) {
            flows = data.flows || [];
            renderFlows();
        } else {
            showError(data.message || 'Erro ao carregar fluxos');
        }
    } catch (error) {
        console.error('Erro:', error);
        showError('Erro ao conectar com o servidor');
    }
}

function filterFlows(filter) {
    // Remover active de todos os botões
    document.querySelectorAll('.filter-btn, .filter-btn-status').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Adicionar active no botão clicado
    const btnId = filter === 'all' || filter === 'conversational' || filter === 'automation' 
        ? `filter-${filter}` 
        : `filter-${filter}`;
    const btn = document.getElementById(btnId);
    if (btn) btn.classList.add('active');
    
    // Atualizar filtro
    if (filter === 'published' || filter === 'draft' || filter === 'paused') {
        currentStatusFilter = currentStatusFilter === filter ? null : filter;
        if (!currentStatusFilter) {
            // Se desativou o filtro de status, remover active
            btn.classList.remove('active');
        }
    } else {
        currentFilter = filter;
        currentStatusFilter = null;
    }
    
    renderFlows();
}

function renderFlows() {
    const grid = document.getElementById('flowsGrid');
    
    // Filtrar flows
    let filteredFlows = flows.filter(flow => {
        // Filtro de tipo
        const flowType = flow.flow_type || 'conversational';
        if (currentFilter !== 'all' && flowType !== currentFilter) {
            return false;
        }
        
        // Filtro de status
        if (currentStatusFilter && flow.status !== currentStatusFilter) {
            return false;
        }
        
        return true;
    });
    
    if (filteredFlows.length === 0) {
        grid.innerHTML = `
            <div class="col-span-full">
                <div class="empty-state">
                    <i class="fas fa-project-diagram"></i>
                    <h3 class="text-2xl font-bold text-gray-800 dark:text-white mb-2">Nenhum fluxo encontrado</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        ${currentFilter !== 'all' || currentStatusFilter 
                            ? 'Tente ajustar os filtros ou crie um novo fluxo.' 
                            : 'Crie seu primeiro fluxo de automação para começar a automatizar conversas.'}
                    </p>
                    <button onclick="openCreateModal()" class="btn-create">
                        <i class="fas fa-plus"></i>
                        Criar Novo Fluxo
                    </button>
                </div>
            </div>
        `;
        return;
    }
    
    grid.innerHTML = filteredFlows.map(flow => {
        const flowType = flow.flow_type || 'conversational';
        const flowTypeLabel = flowType === 'automation' ? 'Automação IA' : 'Conversacional';
        const flowTypeIcon = flowType === 'automation' ? 'fa-robot' : 'fa-project-diagram';
        
        return `
        <div class="flow-card" data-flow-type="${flowType}" data-status="${flow.status}">
            <div class="flex items-start justify-between mb-3">
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white">${escapeHtml(flow.name)}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">${escapeHtml(flow.description || 'Sem descrição')}</p>
                    <span class="flow-type-badge ${flowType}">
                        <i class="fas ${flowTypeIcon}"></i>
                        ${flowTypeLabel}
                    </span>
                </div>
                <span class="flow-status ${flow.status}">
                    <i class="fas fa-circle text-xs"></i>
                    ${getStatusLabel(flow.status)}
                </span>
            </div>
            
            <div class="flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400 mb-4">
                <span><i class="fas fa-code-branch mr-1"></i> v${flow.version || 1}</span>
                <span><i class="fas fa-calendar mr-1"></i> ${formatDate(flow.updated_at)}</span>
            </div>
            
            <div class="flex gap-2">
                <a href="flow_builder_v2.php?id=${flow.id}" 
                   class="flex-1 px-3 py-2 bg-green-600 hover:bg-green-700 text-white text-center rounded-lg text-sm font-medium transition">
                    <i class="fas fa-edit mr-1"></i> Editar
                </a>
                <button onclick="duplicateFlow(${flow.id})" 
                        class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                        title="Duplicar">
                    <i class="fas fa-copy"></i>
                </button>
                <button onclick="openDeleteModal(${flow.id})" 
                        class="px-3 py-2 border border-red-300 rounded-lg text-red-600 hover:bg-red-50 transition"
                        title="Excluir">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
    }).join('');
}

function getStatusLabel(status) {
    const labels = {
        'draft': 'Rascunho',
        'published': 'Publicado',
        'paused': 'Pausado'
    };
    return labels[status] || status;
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

// Modal de criação
function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Novo Fluxo';
    document.getElementById('flowId').value = '';
    document.getElementById('flowName').value = '';
    document.getElementById('flowDescription').value = '';
    document.getElementById('flowTypeSection').style.display = 'block';
    document.querySelector('input[name="flow_type"][value="conversational"]').checked = true;
    document.getElementById('flowModal').classList.remove('hidden');
    document.getElementById('flowModal').classList.add('flex');
}

function closeModal() {
    document.getElementById('flowModal').classList.add('hidden');
    document.getElementById('flowModal').classList.remove('flex');
}

// Form submit
document.getElementById('flowForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const flowId = document.getElementById('flowId').value;
    const name = document.getElementById('flowName').value.trim();
    const description = document.getElementById('flowDescription').value.trim();
    const flowType = document.querySelector('input[name="flow_type"]:checked')?.value || 'conversational';
    
    if (!name) {
        showError('Nome é obrigatório');
        return;
    }
    
    try {
        const action = flowId ? 'update' : 'create';
        const response = await fetch('api/bot_flows.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, id: flowId, name, description, flow_type: flowType })
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeModal();
            
            // Se criou novo, redirecionar para o builder
            if (!flowId && data.flow_id) {
                window.location.href = 'flow_builder_v2.php?id=' + data.flow_id;
            } else {
                loadFlows();
            }
        } else {
            showError(data.message || 'Erro ao salvar fluxo');
        }
    } catch (error) {
        console.error('Erro:', error);
        showError('Erro ao salvar fluxo');
    }
});

// Modal de exclusão
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
        const response = await fetch('api/bot_flows.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id: deleteFlowId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeDeleteModal();
            loadFlows();
        } else {
            showError(data.message || 'Erro ao excluir fluxo');
        }
    } catch (error) {
        console.error('Erro:', error);
        showError('Erro ao excluir fluxo');
    }
}

async function duplicateFlow(id) {
    const flow = flows.find(f => f.id == id);
    if (!flow) return;
    
    try {
        const response = await fetch('api/bot_flows.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'create',
                name: flow.name + ' (Cópia)',
                description: flow.description
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            loadFlows();
        } else {
            showError(data.message || 'Erro ao duplicar fluxo');
        }
    } catch (error) {
        console.error('Erro:', error);
        showError('Erro ao duplicar fluxo');
    }
}

function showError(message) {
    alert('Erro: ' + message);
}

// Fechar modais com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
        closeDeleteModal();
    }
});
</script>

<?php include 'includes/footer_spa.php'; ?>
