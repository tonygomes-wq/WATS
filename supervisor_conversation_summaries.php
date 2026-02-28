<?php
/**
 * Página de Resumo de Conversas para Supervisores
 * Sistema de análise de atendimentos com IA
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

// Verificar permissão
if (!isAdmin() && !isSupervisor()) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Resumo de Conversas';
include 'includes/header_spa.php';
?>

<div class="p-6 max-w-7xl mx-auto" id="conversationSummariesApp">
    <!-- Header -->
    <div class="mb-6 flex items-start justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                <i class="fas fa-file-alt text-green-600 mr-2"></i>
                Resumo de Conversas
            </h1>
            <p class="text-gray-600 dark:text-gray-300">
                Gere resumos automáticos das conversas dos atendentes usando inteligência artificial
            </p>
        </div>
        <button onclick="checkSystemStatus()" 
                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-medium transition flex items-center gap-2"
                title="Verificar status do sistema">
            <i class="fas fa-cog"></i>
            <span>Status</span>
        </button>
    </div>

    <!-- Tabs -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="flex -mb-px">
                <button onclick="switchTab('conversations')" 
                        id="tab-conversations"
                        class="tab-button active px-6 py-4 text-sm font-medium border-b-2 border-green-500 text-green-600 dark:text-green-400">
                    <i class="fas fa-comments mr-2"></i>
                    Conversas
                </button>
                <button onclick="switchTab('summaries')" 
                        id="tab-summaries"
                        class="tab-button px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 dark:hover:border-gray-600">
                    <i class="fas fa-list mr-2"></i>
                    Resumos Gerados
                </button>
                <button onclick="switchTab('stats')" 
                        id="tab-stats"
                        class="tab-button px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 dark:hover:border-gray-600">
                    <i class="fas fa-chart-bar mr-2"></i>
                    Estatísticas
                </button>
            </nav>
        </div>
    </div>

    <!-- Tab: Conversas -->
    <div id="content-conversations" class="tab-content">
        <!-- Filtros -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                <i class="fas fa-filter mr-2"></i>Filtros
            </h3>
            
            <!-- Campo de Busca por Palavra-chave -->
            <div class="mb-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    <i class="fas fa-search mr-2"></i>Buscar no Conteúdo da Conversa
                </label>
                <div class="relative">
                    <input 
                        type="text" 
                        id="filter-keyword" 
                        placeholder="Digite palavras-chave para buscar nas mensagens (ex: problema, pedido, reclamação...)"
                        class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg pl-10 pr-4 py-3 focus:ring-2 focus:ring-green-500 focus:border-transparent"
                    >
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                    <i class="fas fa-info-circle mr-1"></i>
                    Busca por palavras ou frases no conteúdo das mensagens trocadas entre atendente e cliente
                </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Atendente</label>
                    <select id="filter-attendant" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-4 py-2 focus:ring-2 focus:ring-green-500">
                        <option value="">Todos os atendentes</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Data Inicial</label>
                    <input type="date" id="filter-date-from" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-4 py-2 focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Data Final</label>
                    <input type="date" id="filter-date-to" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-4 py-2 focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</label>
                    <select id="filter-status" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-4 py-2 focus:ring-2 focus:ring-green-500">
                        <option value="">Todos os status</option>
                        <option value="open">Aberto</option>
                        <option value="in_progress">Em andamento</option>
                        <option value="closed">Finalizado</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Resumo</label>
                    <select id="filter-has-summary" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-4 py-2 focus:ring-2 focus:ring-green-500">
                        <option value="">Todos</option>
                        <option value="0">Sem resumo</option>
                        <option value="1">Com resumo</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button onclick="applyFilters()" class="w-full bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-medium transition">
                        <i class="fas fa-search mr-2"></i>Aplicar Filtros
                    </button>
                </div>
            </div>
            
            <!-- Botão Limpar Filtros -->
            <div class="mt-4 flex justify-end">
                <button onclick="clearFilters()" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 flex items-center">
                    <i class="fas fa-times-circle mr-2"></i>
                    Limpar todos os filtros
                </button>
            </div>
        </div>

        <!-- Lista de Conversas -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Conversas Disponíveis</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1" id="conversations-count">Carregando...</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="selectAllConversations()" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm">
                        <i class="fas fa-check-square mr-2"></i>Selecionar Todas
                    </button>
                    <button onclick="generateBatchSummaries()" id="btn-batch-generate" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium" disabled>
                        <i class="fas fa-magic mr-2"></i>Gerar Resumos em Lote
                    </button>
                </div>
            </div>
            <div id="conversations-list" class="divide-y divide-gray-200 dark:divide-gray-700">
                <!-- Conversas serão carregadas aqui -->
            </div>
        </div>
    </div>

    <!-- Tab: Resumos Gerados -->
    <div id="content-summaries" class="tab-content hidden">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Resumos Gerados</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Histórico de resumos criados</p>
            </div>
            <div id="summaries-list" class="divide-y divide-gray-200 dark:divide-gray-700">
                <!-- Resumos serão carregados aqui -->
            </div>
        </div>
    </div>

    <!-- Tab: Estatísticas -->
    <div id="content-stats" class="tab-content hidden">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Total de Resumos</p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2" id="stat-total">0</p>
                    </div>
                    <div class="bg-green-100 dark:bg-green-900/30 rounded-full p-3">
                        <i class="fas fa-file-alt text-green-600 dark:text-green-400 text-2xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Tempo Médio</p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2" id="stat-avg-time">0s</p>
                    </div>
                    <div class="bg-blue-100 dark:bg-blue-900/30 rounded-full p-3">
                        <i class="fas fa-clock text-blue-600 dark:text-blue-400 text-2xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Sentimento Positivo</p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2" id="stat-positive">0%</p>
                    </div>
                    <div class="bg-green-100 dark:bg-green-900/30 rounded-full p-3">
                        <i class="fas fa-smile text-green-600 dark:text-green-400 text-2xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Sentimento Negativo</p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2" id="stat-negative">0%</p>
                    </div>
                    <div class="bg-red-100 dark:bg-red-900/30 rounded-full p-3">
                        <i class="fas fa-frown text-red-600 dark:text-red-400 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Top 5 Atendentes</h3>
                <div id="top-attendants" class="space-y-3">
                    <!-- Lista será carregada aqui -->
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Palavras-chave Mais Frequentes</h3>
                <div id="top-keywords" class="flex flex-wrap gap-2">
                    <!-- Tags serão carregadas aqui -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Visualização de Resumo -->
<div id="modal-summary" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between sticky top-0 bg-white dark:bg-gray-800">
            <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-file-alt text-green-600 mr-2"></i>
                Resumo da Conversa
            </h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        <div id="modal-summary-content" class="p-6">
            <!-- Conteúdo será carregado aqui -->
        </div>
    </div>
</div>

<!-- Modal de Progresso em Lote -->
<div id="modal-batch-progress" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-lg max-w-2xl w-full">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-magic text-green-600 mr-2"></i>
                Gerando Resumos em Lote
            </h3>
        </div>
        <div class="p-6">
            <div class="mb-4">
                <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-2">
                    <span>Progresso</span>
                    <span id="batch-progress-text">0/0</span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                    <div id="batch-progress-bar" class="bg-green-600 h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
            </div>
            <div id="batch-progress-list" class="space-y-2 max-h-64 overflow-y-auto">
                <!-- Lista de progresso -->
            </div>
            <div class="mt-6 flex justify-end">
                <button onclick="closeBatchModal()" class="px-6 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg font-medium">
                    Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="/assets/css/conversation-summaries.css">
<script src="/assets/js/conversation_summaries.js"></script>

<?php include 'includes/footer_spa.php'; ?>
