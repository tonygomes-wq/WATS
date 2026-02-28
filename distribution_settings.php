<?php
/**
 * Configurações de Distribuição Automática
 * Permite supervisores configurarem regras de distribuição
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

// Verificar se é supervisor ou admin
$user_type = $_SESSION['user_type'] ?? 'user';
$is_supervisor = isset($_SESSION['is_supervisor']) && $_SESSION['is_supervisor'] == 1;
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;

if (!$is_supervisor && !$is_admin) {
    header('Location: chat.php');
    exit;
}

$page_title = 'Distribuição Automática';
include 'includes/header_spa.php';
?>

<div class="container mx-auto px-4 py-6">
    <!-- Cabeçalho -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                <i class="fas fa-random text-purple-600"></i>
                Distribuição Automática
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">
                Configure como as conversas são distribuídas entre os atendentes
            </p>
        </div>
        <button onclick="openCreateRuleModal()" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium transition-colors flex items-center gap-2">
            <i class="fas fa-plus"></i>
            Nova Regra
        </button>
    </div>

    <!-- Cards de Métricas -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Regras Ativas</p>
                    <p id="active-rules" class="text-3xl font-bold text-gray-900 dark:text-white mt-2">-</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-cogs text-purple-600 dark:text-purple-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Na Fila</p>
                    <p id="queue-count" class="text-3xl font-bold text-gray-900 dark:text-white mt-2">-</p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 dark:bg-yellow-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-clock text-yellow-600 dark:text-yellow-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Distribuídas Hoje</p>
                    <p id="distributed-today" class="text-3xl font-bold text-gray-900 dark:text-white mt-2">-</p>
                </div>
                <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Tempo Médio</p>
                    <p id="avg-wait-time" class="text-3xl font-bold text-gray-900 dark:text-white mt-2">-</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-stopwatch text-blue-600 dark:text-blue-400 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg mb-6">
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="flex space-x-4 px-6" aria-label="Tabs">
                <button onclick="switchTab('rules')" id="tab-rules" class="tab-button active px-4 py-4 text-sm font-medium border-b-2 border-purple-600 text-purple-600">
                    <i class="fas fa-cogs mr-2"></i>Regras
                </button>
                <button onclick="switchTab('queue')" id="tab-queue" class="tab-button px-4 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                    <i class="fas fa-list mr-2"></i>Fila de Espera
                </button>
                <button onclick="switchTab('history')" id="tab-history" class="tab-button px-4 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                    <i class="fas fa-history mr-2"></i>Histórico
                </button>
            </nav>
        </div>

        <!-- Conteúdo das Tabs -->
        <div class="p-6">
            <!-- Tab: Regras -->
            <div id="content-rules" class="tab-content">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Regra</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tipo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Prioridade</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Máx. Conversas</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Horário</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="rules-table-body" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <!-- Será preenchido via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab: Fila de Espera -->
            <div id="content-queue" class="tab-content hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cliente</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Setor</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Prioridade</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tempo Espera</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="queue-table-body" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <!-- Será preenchido via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab: Histórico -->
            <div id="content-history" class="tab-content hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Data/Hora</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Conversa</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Atendente</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tipo Distribuição</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tempo Espera</th>
                            </tr>
                        </thead>
                        <tbody id="history-table-body" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <!-- Será preenchido via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Criar/Editar Regra -->
<div id="rule-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center">
    <div class="relative p-5 border w-full max-w-3xl shadow-lg rounded-lg bg-white dark:bg-gray-800 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 id="rule-modal-title" class="text-xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-cogs text-purple-600 mr-2"></i>Nova Regra de Distribuição
            </h3>
            <button onclick="closeRuleModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form id="rule-form" onsubmit="handleRuleSubmit(event)">
            <input type="hidden" id="rule-id" name="id">

            <!-- Nome -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Nome da Regra *
                </label>
                <input type="text" id="rule-name" name="name" required
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white"
                       placeholder="Ex: Distribuição Balanceada">
            </div>

            <!-- Tipo de Distribuição -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Tipo de Distribuição *
                </label>
                <select id="rule-type" name="type" required
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white">
                    <option value="round_robin">Rodízio (Round Robin)</option>
                    <option value="least_busy">Menos Ocupado</option>
                    <option value="by_department">Por Setor</option>
                    <option value="manual">Manual</option>
                </select>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    <strong>Rodízio:</strong> Distribui em ordem circular | 
                    <strong>Menos Ocupado:</strong> Atendente com menos conversas ativas
                </p>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <!-- Prioridade -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Prioridade
                    </label>
                    <input type="number" id="rule-priority" name="priority" min="0" max="100" value="50"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Maior = mais importante</p>
                </div>

                <!-- Máximo de Conversas -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Máx. Conversas por Atendente
                    </label>
                    <input type="number" id="rule-max-conversations" name="max_conversations_per_attendant" min="1" max="20" value="5"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white">
                </div>
            </div>

            <!-- Horário de Trabalho -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Horário de Trabalho
                </label>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs text-gray-600 dark:text-gray-400">Início</label>
                        <input type="time" id="rule-work-start" name="work_hours_start" value="08:00"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="text-xs text-gray-600 dark:text-gray-400">Fim</label>
                        <input type="time" id="rule-work-end" name="work_hours_end" value="18:00"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white">
                    </div>
                </div>
            </div>

            <!-- Opções -->
            <div class="mb-6 space-y-2">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" id="rule-auto-assign" name="auto_assign" checked
                           class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Atribuir automaticamente</span>
                </label>
                
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" id="rule-notify" name="notify_attendant" checked
                           class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Notificar atendente</span>
                </label>
                
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" id="rule-active" name="is_active" checked
                           class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Regra ativa</span>
                </label>
            </div>

            <!-- Botões -->
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeRuleModal()" 
                        class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-lg font-medium transition-colors">
                    Cancelar
                </button>
                <button type="submit" 
                        class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium transition-colors">
                    <i class="fas fa-save mr-2"></i>Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<script src="/assets/js/distribution_settings.js?v=<?php echo time(); ?>"></script>

<?php include 'includes/footer_spa.php'; ?>
