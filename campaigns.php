<?php
$page_title = 'Campanhas';
require_once 'includes/header_spa.php';

// Verificar permissão - Apenas Admin
if (!isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Buscar campanhas do usuário
$stmt = $pdo->prepare("
    SELECT * FROM campaigns 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$userId]);
$campaigns = $stmt->fetchAll();
?>

<div class="refined-container">
    <div class="refined-card">
        <div class="refined-action-bar">
            <div>
                <h1 class="refined-title">
                    <i class="fas fa-bullhorn"></i>Campanhas
                </h1>
                <p style="font-size: 13px; color: var(--text-secondary); margin-top: 4px;">Gerencie suas campanhas de disparo automatizado</p>
            </div>
            <button onclick="openCampaignModal()" class="refined-btn refined-btn-primary">
                <i class="fas fa-plus"></i>Nova Campanha
            </button>
        </div>

        <?php if (empty($campaigns)): ?>
        <div class="refined-empty">
            <i class="fas fa-bullhorn"></i>
            <h3>Nenhuma campanha criada</h3>
            <p>Crie sua primeira campanha para automatizar seus disparos</p>
            <button onclick="openCampaignModal()" class="refined-btn refined-btn-primary" style="margin-top: var(--space-4);">
                <i class="fas fa-plus"></i>Criar Primeira Campanha
            </button>
        </div>
        <?php else: ?>
        <div class="refined-grid refined-grid-3">
            <?php foreach ($campaigns as $campaign): ?>
            <div style="border: 0.5px solid var(--border); border-radius: var(--radius-md); padding: var(--space-6); background: var(--bg-card); transition: all var(--transition-fast);" onmouseover="this.style.borderColor='var(--accent-primary)'" onmouseout="this.style.borderColor='var(--border)'">
                <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: var(--space-4);">
                    <div style="flex: 1;">
                        <h3 style="font-size: 16px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px;">
                            <?php echo htmlspecialchars($campaign['name']); ?>
                        </h3>
                        <p style="font-size: 12px; color: var(--text-secondary);">
                            <?php echo htmlspecialchars($campaign['description'] ?? 'Sem descrição'); ?>
                        </p>
                    </div>
                    <span class="refined-badge <?php echo $campaign['status'] === 'active' ? 'refined-badge-primary' : 'refined-badge-secondary'; ?>">
                        <?php echo $campaign['status'] === 'active' ? 'Ativa' : 'Pausada'; ?>
                    </span>
                </div>

                <div style="display: flex; flex-direction: column; gap: var(--space-2); margin-bottom: var(--space-4);">
                    <div style="display: flex; align-items: center; font-size: 12px; color: var(--text-secondary); gap: 8px;">
                        <i class="fas fa-users" style="width: 16px;"></i>
                        <span><?php echo number_format($campaign['total_contacts'] ?? 0); ?> contatos</span>
                    </div>
                    <div style="display: flex; align-items: center; font-size: 12px; color: var(--text-secondary); gap: 8px;">
                        <i class="fas fa-paper-plane" style="width: 16px;"></i>
                        <span><?php echo number_format($campaign['messages_sent'] ?? 0); ?> enviadas</span>
                    </div>
                    <div style="display: flex; align-items: center; font-size: 12px; color: var(--text-secondary); gap: 8px;">
                        <i class="fas fa-calendar" style="width: 16px;"></i>
                        <span><?php echo date('d/m/Y', strtotime($campaign['created_at'])); ?></span>
                    </div>
                </div>

                <div style="display: flex; gap: var(--space-2);">
                    <button onclick="editCampaign(<?php echo $campaign['id']; ?>)" class="refined-btn refined-btn-sm" style="flex: 1; background: #3b82f6; border-color: #3b82f6; color: white;">
                        <i class="fas fa-edit"></i>Editar
                    </button>
                    <button onclick="toggleCampaign(<?php echo $campaign['id']; ?>, '<?php echo $campaign['status']; ?>')" class="refined-btn refined-btn-sm" style="flex: 1; background: #eab308; border-color: #eab308; color: white;">
                        <i class="fas fa-<?php echo $campaign['status'] === 'active' ? 'pause' : 'play'; ?>"></i>
                        <?php echo $campaign['status'] === 'active' ? 'Pausar' : 'Ativar'; ?>
                    </button>
                    <button onclick="deleteCampaign(<?php echo $campaign['id']; ?>)" class="refined-btn refined-btn-danger refined-btn-sm">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de Campanha -->
<div id="campaignModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" onclick="closeCampaignModal(event)">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden" onclick="event.stopPropagation()">
        <!-- Header -->
        <div class="bg-gradient-to-r from-green-600 to-green-500 p-6 text-white">
            <div class="flex justify-between items-center">
                <h2 class="text-2xl font-bold" id="campaignModalTitle">
                    <i class="fas fa-bullhorn mr-2"></i>Nova Campanha
                </h2>
                <button onclick="closeCampaignModal()" class="text-white hover:text-gray-200 transition">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Formulário -->
        <form id="campaignForm" class="p-6 space-y-4 overflow-y-auto" style="max-height: calc(90vh - 140px);">
            <input type="hidden" id="campaign_id" name="campaign_id">
            
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">
                    Nome da Campanha *
                </label>
                <input type="text" id="campaign_name" name="name" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                       placeholder="Ex: Campanha de Boas-vindas">
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">
                    Descrição
                </label>
                <textarea id="campaign_description" name="description" rows="3"
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                          placeholder="Descreva o objetivo desta campanha"></textarea>
            </div>
            
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-yellow-600 mr-3 mt-1"></i>
                    <div>
                        <h4 class="font-semibold text-yellow-800 mb-1">Funcionalidade em Desenvolvimento</h4>
                        <p class="text-sm text-yellow-700">
                            O sistema de campanhas automatizadas está em desenvolvimento. 
                            Em breve você poderá criar gatilhos, fluxos automáticos e muito mais!
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg transition">
                    <i class="fas fa-save mr-2"></i>Salvar Campanha
                </button>
                <button type="button" onclick="closeCampaignModal()" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 px-4 rounded-lg transition">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openCampaignModal(campaignId = null) {
    const modal = document.getElementById('campaignModal');
    const form = document.getElementById('campaignForm');
    const title = document.getElementById('campaignModalTitle');
    
    form.reset();
    
    if (campaignId) {
        title.innerHTML = '<i class="fas fa-edit mr-2"></i>Editar Campanha';
        // Carregar dados da campanha via AJAX
        loadCampaignData(campaignId);
    } else {
        title.innerHTML = '<i class="fas fa-plus mr-2"></i>Nova Campanha';
    }
    
    modal.classList.remove('hidden');
}

function closeCampaignModal(event) {
    if (!event || event.target.id === 'campaignModal') {
        document.getElementById('campaignModal').classList.add('hidden');
    }
}

function loadCampaignData(id) {
    // TODO: Implementar carregamento via AJAX
    alert('Funcionalidade de edição será implementada em breve!');
}

function editCampaign(id) {
    openCampaignModal(id);
}

function toggleCampaign(id, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'paused' : 'active';
    const action = newStatus === 'active' ? 'ativar' : 'pausar';
    
    if (confirm(`Deseja ${action} esta campanha?`)) {
        alert('Funcionalidade será implementada em breve!');
    }
}

function deleteCampaign(id) {
    if (confirm('Tem certeza que deseja excluir esta campanha? Esta ação não pode ser desfeita.')) {
        alert('Funcionalidade de exclusão será implementada em breve!');
    }
}

// Submeter formulário
document.getElementById('campaignForm').addEventListener('submit', function(e) {
    e.preventDefault();
    alert('Funcionalidade de salvamento será implementada em breve!');
});

// Fechar modal com ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeCampaignModal();
    }
});
</script>

<?php require_once 'includes/footer_spa.php'; ?>
