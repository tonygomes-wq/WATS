<?php
/**
 * Gerenciamento de Respostas Rápidas/Templates
 * Permite supervisores criarem templates para atendentes
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

$page_title = 'Respostas Rápidas';
require_once 'includes/header_spa.php';
?>

<div class="container mx-auto px-4 py-6">
    <!-- Cabeçalho -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                <i class="fas fa-bolt text-yellow-500"></i>
                Respostas Rápidas
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">
                Crie templates de mensagens para agilizar o atendimento
            </p>
        </div>
        <button onclick="openCreateModal()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors flex items-center gap-2">
            <i class="fas fa-plus"></i>
            Nova Resposta Rápida
        </button>
    </div>

    <!-- Cards de Estatísticas -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total de Templates</p>
                    <p id="total-templates" class="text-3xl font-bold text-gray-900 dark:text-white mt-2">-</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-file-alt text-blue-600 dark:text-blue-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Templates Ativos</p>
                    <p id="active-templates" class="text-3xl font-bold text-gray-900 dark:text-white mt-2">-</p>
                </div>
                <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total de Usos</p>
                    <p id="total-uses" class="text-3xl font-bold text-gray-900 dark:text-white mt-2">-</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-chart-bar text-purple-600 dark:text-purple-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Categorias</p>
                    <p id="total-categories" class="text-3xl font-bold text-gray-900 dark:text-white mt-2">-</p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 dark:bg-yellow-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-folder text-yellow-600 dark:text-yellow-400 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <input type="text" id="search-input" placeholder="Buscar por nome ou atalho..." 
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                       oninput="filterTemplates()">
            </div>
            <div>
                <select id="category-filter" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                        onchange="filterTemplates()">
                    <option value="">Todas as Categorias</option>
                    <!-- Será preenchido via JavaScript -->
                </select>
            </div>
            <div>
                <select id="status-filter" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                        onchange="filterTemplates()">
                    <option value="">Todos os Status</option>
                    <option value="1">Ativos</option>
                    <option value="0">Inativos</option>
                </select>
            </div>
            <div>
                <select id="sort-filter" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                        onchange="filterTemplates()">
                    <option value="name">Nome (A-Z)</option>
                    <option value="usage">Mais Usados</option>
                    <option value="recent">Mais Recentes</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Lista de Templates -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nome / Atalho</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Mensagem</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Categoria</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Usos</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody id="templates-table-body" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <!-- Será preenchido via JavaScript -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal de Criar/Editar Template -->
<div id="template-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-lg bg-white dark:bg-gray-800">
        <div class="flex justify-between items-center mb-4">
            <h3 id="modal-title" class="text-xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-bolt text-yellow-500 mr-2"></i>Nova Resposta Rápida
            </h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form id="template-form" onsubmit="handleSubmit(event)">
            <input type="hidden" id="template-id" name="id">

            <!-- Nome -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Nome do Template *
                </label>
                <input type="text" id="template-name" name="name" required
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                       placeholder="Ex: Saudação Inicial">
            </div>

            <!-- Atalho -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Atalho *
                </label>
                <div class="flex items-center gap-2">
                    <span class="text-gray-500 dark:text-gray-400 font-mono">/</span>
                    <input type="text" id="template-shortcut" name="shortcut" required
                           class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white font-mono"
                           placeholder="ola"
                           pattern="[a-z0-9]+"
                           title="Apenas letras minúsculas e números, sem espaços">
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Digite apenas letras minúsculas e números. Ex: /ola, /bomdia, /aguarde
                </p>
            </div>

            <!-- Categoria -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Categoria
                </label>
                <input type="text" id="template-category" name="category"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                       placeholder="Ex: Saudações, Despedidas, Suporte"
                       list="categories-list">
                <datalist id="categories-list">
                    <!-- Será preenchido via JavaScript -->
                </datalist>
            </div>

            <!-- Mensagem -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Mensagem *
                </label>
                <textarea id="template-message" name="message" required rows="6"
                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                          placeholder="Digite a mensagem do template..."></textarea>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Use variáveis: {nome}, {telefone}, {email}, {empresa}, {atendente}, {data}, {hora}
                </p>
            </div>

            <!-- Preview -->
            <div class="mb-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    <i class="fas fa-eye mr-2"></i>Preview:
                </p>
                <div id="message-preview" class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-wrap">
                    Digite uma mensagem para ver o preview...
                </div>
            </div>

            <!-- Status -->
            <div class="mb-6">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" id="template-active" name="is_active" checked
                           class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Template ativo</span>
                </label>
            </div>

            <!-- Botões -->
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeModal()" 
                        class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-lg font-medium transition-colors">
                    Cancelar
                </button>
                <button type="submit" 
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                    <i class="fas fa-save mr-2"></i>Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<script src="/assets/js/quick_replies.js?v=<?php echo time(); ?>"></script>

<?php include 'includes/footer_spa.php'; ?>
