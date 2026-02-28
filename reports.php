<?php
/**
 * Página de Relatórios de Atendimento
 * Relatórios detalhados por atendente, setor, período
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

$page_title = 'Relatórios de Atendimento';
include 'includes/header_spa.php';
?>

<div class="container mx-auto px-4 py-6">
    <!-- Cabeçalho -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
            <i class="fas fa-chart-line text-blue-600"></i>
            Relatórios de Atendimento
        </h1>
        <p class="text-gray-600 dark:text-gray-400 mt-2">
            Análise detalhada de desempenho e métricas de atendimento
        </p>
    </div>

    <!-- Filtros -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
            <i class="fas fa-filter text-blue-600"></i>
            Filtros
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Período -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Período
                </label>
                <select id="period-filter" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                    <option value="today">Hoje</option>
                    <option value="yesterday">Ontem</option>
                    <option value="last7days" selected>Últimos 7 dias</option>
                    <option value="last30days">Últimos 30 dias</option>
                    <option value="thismonth">Este mês</option>
                    <option value="lastmonth">Mês passado</option>
                    <option value="custom">Personalizado</option>
                </select>
            </div>

            <!-- Data Início (para período personalizado) -->
            <div id="custom-dates" class="hidden">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Data Início
                </label>
                <input type="date" id="start-date" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
            </div>

            <!-- Data Fim (para período personalizado) -->
            <div id="custom-dates-end" class="hidden">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Data Fim
                </label>
                <input type="date" id="end-date" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
            </div>

            <!-- Atendente -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Atendente
                </label>
                <select id="attendant-filter" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                    <option value="">Todos</option>
                    <!-- Será preenchido via JavaScript -->
                </select>
            </div>

            <!-- Setor -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Setor
                </label>
                <select id="department-filter" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                    <option value="">Todos</option>
                    <!-- Será preenchido via JavaScript -->
                </select>
            </div>

            <!-- Status -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Status
                </label>
                <select id="status-filter" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                    <option value="">Todos</option>
                    <option value="open">Abertos</option>
                    <option value="in_progress">Em Andamento</option>
                    <option value="resolved">Resolvidos</option>
                    <option value="closed">Fechados</option>
                </select>
            </div>

            <!-- Botão Filtrar -->
            <div class="flex items-end">
                <button onclick="applyFilters()" class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors flex items-center justify-center gap-2">
                    <i class="fas fa-search"></i>
                    Filtrar
                </button>
            </div>

            <!-- Botão Exportar -->
            <div class="flex items-end">
                <button onclick="showExportModal()" class="w-full px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition-colors flex items-center justify-center gap-2">
                    <i class="fas fa-file-excel"></i>
                    Exportar
                </button>
            </div>
        </div>
    </div>

    <!-- Cards de Métricas Gerais -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- Total de Conversas -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total de Conversas</p>
                    <p id="total-conversations" class="text-3xl font-bold text-gray-900 dark:text-white mt-2">-</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-comments text-blue-600 dark:text-blue-400 text-xl"></i>
                </div>
            </div>
            <div id="total-conversations-change" class="mt-4 text-sm">
                <!-- Será preenchido via JavaScript -->
            </div>
        </div>

        <!-- Tempo Médio de Atendimento -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Tempo Médio</p>
                    <p id="avg-response-time" class="text-3xl font-bold text-gray-900 dark:text-white mt-2">-</p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 dark:bg-yellow-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-clock text-yellow-600 dark:text-yellow-400 text-xl"></i>
                </div>
            </div>
            <div id="avg-response-time-change" class="mt-4 text-sm">
                <!-- Será preenchido via JavaScript -->
            </div>
        </div>

        <!-- Taxa de Resolução -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Taxa de Resolução</p>
                    <p id="resolution-rate" class="text-3xl font-bold text-gray-900 dark:text-white mt-2">-</p>
                </div>
                <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-xl"></i>
                </div>
            </div>
            <div id="resolution-rate-change" class="mt-4 text-sm">
                <!-- Será preenchido via JavaScript -->
            </div>
        </div>

        <!-- Satisfação Média -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Satisfação Média</p>
                    <p id="avg-satisfaction" class="text-3xl font-bold text-gray-900 dark:text-white mt-2">-</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-star text-purple-600 dark:text-purple-400 text-xl"></i>
                </div>
            </div>
            <div id="avg-satisfaction-change" class="mt-4 text-sm">
                <!-- Será preenchido via JavaScript -->
            </div>
        </div>
    </div>

    <!-- Tabs de Relatórios -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg mb-6">
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="flex space-x-4 px-6" aria-label="Tabs">
                <button onclick="switchTab('attendants')" id="tab-attendants" class="tab-button active px-4 py-4 text-sm font-medium border-b-2 border-blue-600 text-blue-600">
                    <i class="fas fa-user-headset mr-2"></i>Por Atendente
                </button>
                <button onclick="switchTab('departments')" id="tab-departments" class="tab-button px-4 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                    <i class="fas fa-building mr-2"></i>Por Setor
                </button>
                <button onclick="switchTab('timeline')" id="tab-timeline" class="tab-button px-4 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                    <i class="fas fa-chart-line mr-2"></i>Linha do Tempo
                </button>
                <button onclick="switchTab('performance')" id="tab-performance" class="tab-button px-4 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                    <i class="fas fa-tachometer-alt mr-2"></i>Desempenho
                </button>
                <button onclick="switchTab('peakhours')" id="tab-peakhours" class="tab-button px-4 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                    <i class="fas fa-clock mr-2"></i>Horários de Pico
                </button>
            </nav>
        </div>

        <!-- Conteúdo das Tabs -->
        <div class="refined-container" style="padding: var(--space-6);">
            <!-- Tab: Por Atendente -->
            <div id="content-attendants" class="tab-content">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Desempenho por Atendente</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Atendente</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Conversas</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Resolvidas</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Taxa Resolução</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tempo Médio</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Satisfação</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody id="attendants-table-body" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <!-- Será preenchido via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab: Por Setor -->
            <div id="content-departments" class="tab-content hidden">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Desempenho por Setor</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Setor</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Atendentes</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Conversas</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Resolvidas</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Taxa Resolução</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tempo Médio</th>
                            </tr>
                        </thead>
                        <tbody id="departments-table-body" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <!-- Será preenchido via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab: Linha do Tempo -->
            <div id="content-timeline" class="tab-content hidden">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Evolução no Tempo</h3>
                <div class="h-96">
                    <canvas id="timeline-chart"></canvas>
                </div>
            </div>

            <!-- Tab: Desempenho -->
            <div id="content-performance" class="tab-content hidden">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Análise de Desempenho</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="h-64">
                        <canvas id="status-chart"></canvas>
                    </div>
                    <div class="h-64">
                        <canvas id="satisfaction-chart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Tab: Horários de Pico -->
            <div id="content-peakhours" class="tab-content hidden">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Mapa de Calor de Mensagens</h3>
                <div class="overflow-x-auto">
                    <div id="heatmap-container" class="min-w-full">
                        <!-- Heatmap renderizado via JS -->
                        <div class="flex items-center justify-center h-64 text-gray-400">
                            <i class="fas fa-spinner fa-spin mr-2"></i> Convertendo dados...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="/assets/js/reports.js?v=<?php echo time(); ?>"></script>

<?php include 'includes/footer_spa.php'; ?>
