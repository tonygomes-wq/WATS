<?php
/**
 * Página de Gerenciamento de Setores (Formato SPA)
 * Permite criar, editar, ativar/desativar e excluir setores
 */

session_start();
require_once 'config/database.php';
require_once 'includes/default_departments.php';

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Verificar se é supervisor
$stmt = $pdo->prepare("SELECT user_type FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || ($user['user_type'] !== 'supervisor' && $user['user_type'] !== 'admin')) {
    header('Location: dashboard.php');
    exit;
}

// Verificar se já tem setores criados
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM departments WHERE supervisor_id = ?");
$stmt->execute([$user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$has_departments = $result['count'] > 0;

$colors = getDepartmentColors();
$page_title = "Setores";
include 'includes/header_spa.php';
?>

<div class="refined-container">
    <div class="refined-card">
        <div class="refined-action-bar">
            <div>
                <h1 class="refined-title">
                    <i class="fas fa-building"></i>
                    Gerenciar Setores
                </h1>
                <p style="font-size: 13px; color: var(--text-secondary); margin-top: 4px;">
                    Organize seus atendentes por setores/departamentos
                </p>
            </div>
            <div class="refined-action-group">
                <?php if (!$has_departments): ?>
                <button onclick="createDefaultDepartments()" class="refined-btn" style="background: #3b82f6; border-color: #3b82f6; color: white;">
                    <i class="fas fa-magic"></i>
                    <span>Criar Setores Padrão</span>
                </button>
                <?php endif; ?>
                <button onclick="openCreateModal()" class="refined-btn refined-btn-primary">
                    <i class="fas fa-plus"></i>
                    <span>Novo Setor</span>
                </button>
            </div>
        </div>

        <div class="refined-section">
            <div class="refined-grid refined-grid-4">
                <div style="background: var(--bg-card); border: 0.5px solid var(--border); border-left: 3px solid #3b82f6; border-radius: var(--radius-md); padding: var(--space-4);">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <p style="font-size: 12px; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Total de Setores</p>
                            <p style="font-size: 32px; font-weight: 700; color: var(--text-primary); margin-top: 8px; font-family: 'SF Mono', monospace; font-variant-numeric: tabular-nums;" id="total-departments">0</p>
                        </div>
                        <div style="background: rgba(59, 130, 246, 0.1); padding: var(--space-4); border-radius: 50%;">
                            <i class="fas fa-building" style="color: #3b82f6; font-size: 24px;"></i>
                        </div>
                    </div>
                </div>

                <div style="background: var(--bg-card); border: 0.5px solid var(--border); border-left: 3px solid #10b981; border-radius: var(--radius-md); padding: var(--space-4);">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <p style="font-size: 12px; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Ativos</p>
                            <p style="font-size: 32px; font-weight: 700; color: #10b981; margin-top: 8px; font-family: 'SF Mono', monospace; font-variant-numeric: tabular-nums;" id="active-departments">0</p>
                        </div>
                        <div style="background: rgba(16, 185, 129, 0.1); padding: var(--space-4); border-radius: 50%;">
                            <i class="fas fa-check-circle" style="color: #10b981; font-size: 24px;"></i>
                        </div>
                    </div>
                </div>

                <div style="background: var(--bg-card); border: 0.5px solid var(--border); border-left: 3px solid #8b5cf6; border-radius: var(--radius-md); padding: var(--space-4);">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <p style="font-size: 12px; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Total de Atendentes</p>
                            <p style="font-size: 32px; font-weight: 700; color: #8b5cf6; margin-top: 8px; font-family: 'SF Mono', monospace; font-variant-numeric: tabular-nums;" id="total-users">0</p>
                        </div>
                        <div style="background: rgba(139, 92, 246, 0.1); padding: var(--space-4); border-radius: 50%;">
                            <i class="fas fa-users" style="color: #8b5cf6; font-size: 24px;"></i>
                        </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Conversas Ativas</p>
                    <p class="text-3xl font-bold text-orange-600 mt-2" id="total-conversations">0</p>
                </div>
                <div class="bg-orange-100 dark:bg-orange-900 p-4 rounded-full">
                    <i class="fas fa-comments text-orange-600 dark:text-orange-300 text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    <i class="fas fa-search mr-2"></i>Buscar
                </label>
                <input type="text" id="search-input" placeholder="Nome do setor..." 
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    <i class="fas fa-filter mr-2"></i>Status
                </label>
                <select id="status-filter" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white">
                    <option value="">Todos</option>
                    <option value="1">Ativos</option>
                    <option value="0">Inativos</option>
                </select>
            </div>

            <div class="flex items-end">
                <button onclick="loadDepartments()" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center justify-center gap-2">
                    <i class="fas fa-sync-alt"></i>
                    Atualizar
                </button>
            </div>
        </div>
    </div>

    <!-- Grid de Setores -->
    <div id="departments-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Preenchido via JavaScript -->
    </div>

    <!-- Estado Vazio -->
    <div id="empty-state" class="hidden text-center py-16">
        <div class="inline-block p-8 bg-gray-100 dark:bg-gray-700 rounded-full mb-4">
            <i class="fas fa-building text-gray-400 text-6xl"></i>
        </div>
        <h3 class="text-2xl font-bold text-gray-700 dark:text-gray-300 mb-2">
            Nenhum setor encontrado
        </h3>
        <p class="text-gray-500 dark:text-gray-400 mb-6">
            Comece criando setores para organizar seus atendentes
        </p>
        <div class="flex gap-3 justify-center">
            <?php if (!$has_departments): ?>
            <button onclick="createDefaultDepartments()" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-3 rounded-lg inline-flex items-center gap-2 shadow-lg">
                <i class="fas fa-magic"></i>
                Criar Setores Padrão
            </button>
            <?php endif; ?>
            <button onclick="openCreateModal()" class="bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded-lg inline-flex items-center gap-2 shadow-lg">
                <i class="fas fa-plus"></i>
                Criar Setor
            </button>
        </div>
    </div>

    <!-- Loading -->
    <div id="loading-state" class="text-center py-16">
        <i class="fas fa-spinner fa-spin text-green-600 text-5xl mb-4"></i>
        <p class="text-gray-600 dark:text-gray-400 text-lg">Carregando setores...</p>
    </div>
</div>

<!-- Modal Criar/Editar Setor -->
<div id="department-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800 z-10">
            <div class="flex items-center justify-between">
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-3" id="modal-title">
                    <i class="fas fa-building text-green-600"></i>
                    Novo Setor
                </h3>
                <button onclick="closeDepartmentModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors" style="background: none !important; border: none !important; padding: 0 !important; box-shadow: none !important; outline: none !important;">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>

        <form id="department-form" class="p-6">
            <input type="hidden" id="department-id" name="department_id">

            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Nome do Setor <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="department-name" name="name" required
                           class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white"
                           placeholder="Ex: Financeiro, Suporte, Vendas...">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Descrição
                    </label>
                    <textarea id="department-description" name="description" rows="3"
                              class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white"
                              placeholder="Descreva as responsabilidades deste setor..."></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                        Cor do Setor <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-6 gap-3" id="color-picker-grid">
                        <?php foreach ($colors as $color): ?>
                            <label class="cursor-pointer color-picker-label">
                                <input type="radio" name="color" value="<?php echo $color['value']; ?>" class="hidden color-radio">
                                <div class="color-option" 
                                     data-color="<?php echo $color['value']; ?>"
                                     title="<?php echo $color['name']; ?>">
                                    <i class="fas fa-check color-check"></i>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Botões -->
            <div class="mt-8 flex justify-end gap-3">
                <button type="button" onclick="closeDepartmentModal()" 
                        class="modal-btn modal-btn-cancel">
                    <i class="fas fa-times"></i>
                    Cancelar
                </button>
                <button type="submit" 
                        class="modal-btn modal-btn-save">
                    <i class="fas fa-save"></i>
                    Salvar Setor
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* ===== MODAL DE SETORES - ESTILOS DEDICADOS ===== */

/* Forçar cores dos quadrados de seleção */
.color-option {
    position: relative;
    overflow: hidden;
    width: 48px;
    height: 48px;
    border-radius: 8px;
    border: 2px solid #d1d5db;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    cursor: pointer;
}

.color-option:hover {
    border-color: #9ca3af;
}

/* Aplicar cada cor individualmente */
<?php foreach ($colors as $color): ?>
.color-option[data-color="<?php echo $color['value']; ?>"] {
    background-color: <?php echo $color['value']; ?> !important;
}
<?php endforeach; ?>

/* Cor selecionada */
.color-option.selected {
    border-color: #10b981 !important;
    border-width: 3px !important;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.3) !important;
}

/* Check mark */
.color-check {
    color: white;
    font-size: 20px;
    text-shadow: 0 0 3px rgba(0,0,0,0.5);
    display: none;
}

.color-option.selected .color-check {
    display: block !important;
}

/* Garantir que o botão X não tenha fundo */
#department-modal button[onclick="closeDepartmentModal()"] {
    all: unset;
    cursor: pointer;
    color: #9ca3af;
    transition: color 0.2s;
}

#department-modal button[onclick="closeDepartmentModal()"]:hover {
    color: #4b5563;
}

.dark #department-modal button[onclick="closeDepartmentModal()"]:hover {
    color: #d1d5db;
}

/* Botões Cancelar e Salvar */
.modal-btn {
    padding: 12px 24px !important;
    border-radius: 8px !important;
    font-weight: 500 !important;
    font-size: 14px !important;
    cursor: pointer !important;
    transition: all 0.2s !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 8px !important;
    border: none !important;
    outline: none !important;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1) !important;
}

.modal-btn-cancel {
    background: #ef4444 !important;
    color: white !important;
}

.modal-btn-cancel:hover {
    background: #dc2626 !important;
}

.modal-btn-save {
    background: #10b981 !important;
    color: white !important;
}

.modal-btn-save:hover {
    background: #059669 !important;
}
</style>

<script src="/assets/js/departments.js"></script>

<?php include 'includes/footer_spa.php'; ?>
