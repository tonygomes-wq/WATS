<?php
/**
 * Página de Gerenciamento de Atendentes (Formato SPA)
 * Permite criar, editar, bloquear e excluir atendentes
 */

session_start();
require_once 'config/database.php';

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Verificar se é supervisor
$stmt = $pdo->prepare("SELECT user_type, max_supervisor_users, supervisor_users_count FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || ($user['user_type'] !== 'supervisor' && $user['user_type'] !== 'admin')) {
    header('Location: dashboard.php');
    exit;
}

// Buscar setores disponíveis
$stmt = $pdo->prepare("SELECT id, name, color FROM departments WHERE supervisor_id = ? AND is_active = 1 ORDER BY name ASC");
$stmt->execute([$user_id]);
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Gerenciar Atendentes";
include 'includes/header_spa.php';
?>

<div class="p-8">
    <!-- Header -->
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h2 class="text-3xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                <i class="fas fa-user-headset text-green-600"></i>
                Gerenciar Atendentes
            </h2>
            <p class="mt-2 text-gray-600 dark:text-gray-400">
                Crie e gerencie os atendentes da sua instância WhatsApp
            </p>
        </div>
        <button onclick="openCreateModal()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg flex items-center gap-2 transition-all shadow-lg hover:shadow-xl">
            <i class="fas fa-plus"></i>
            <span class="font-medium">Novo Atendente</span>
        </button>
    </div>

    <!-- Estatísticas -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Total de Atendentes</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2" id="total-users">0</p>
                </div>
                <div class="bg-blue-100 dark:bg-blue-900 p-4 rounded-full">
                    <i class="fas fa-users text-blue-600 dark:text-blue-300 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Ativos</p>
                    <p class="text-3xl font-bold text-green-600 mt-2" id="active-users">0</p>
                </div>
                <div class="bg-green-100 dark:bg-green-900 p-4 rounded-full">
                    <i class="fas fa-check-circle text-green-600 dark:text-green-300 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-red-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Bloqueados</p>
                    <p class="text-3xl font-bold text-red-600 mt-2" id="blocked-users">0</p>
                </div>
                <div class="bg-red-100 dark:bg-red-900 p-4 rounded-full">
                    <i class="fas fa-ban text-red-600 dark:text-red-300 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Limite do Plano</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">
                        <?php echo $user['max_supervisor_users'] > 0 ? $user['max_supervisor_users'] : '∞'; ?>
                    </p>
                </div>
                <div class="bg-purple-100 dark:bg-purple-900 p-4 rounded-full">
                    <i class="fas fa-crown text-purple-600 dark:text-purple-300 text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    <i class="fas fa-search mr-2"></i>Buscar
                </label>
                <input type="text" id="search-input" placeholder="Nome ou email..." 
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    <i class="fas fa-filter mr-2"></i>Status
                </label>
                <select id="status-filter" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white">
                    <option value="">Todos</option>
                    <option value="active">Ativos</option>
                    <option value="blocked">Bloqueados</option>
                    <option value="inactive">Inativos</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    <i class="fas fa-building mr-2"></i>Setor
                </label>
                <select id="department-filter" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white">
                    <option value="">Todos os setores</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-end">
                <button onclick="loadUsers()" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center justify-center gap-2">
                    <i class="fas fa-sync-alt"></i>
                    Atualizar
                </button>
            </div>
        </div>
    </div>

    <!-- Tabela de Atendentes -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                            Atendente
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                            Setores
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                            Conversas Ativas
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                            Último Acesso
                        </th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                            Ações
                        </th>
                    </tr>
                </thead>
                <tbody id="users-table-body" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <!-- Preenchido via JavaScript -->
                </tbody>
            </table>
        </div>

        <!-- Estado Vazio -->
        <div id="empty-state" class="hidden text-center py-16">
            <div class="inline-block p-8 bg-gray-100 dark:bg-gray-700 rounded-full mb-4">
                <i class="fas fa-users text-gray-400 text-6xl"></i>
            </div>
            <h3 class="text-2xl font-bold text-gray-700 dark:text-gray-300 mb-2">
                Nenhum atendente encontrado
            </h3>
            <p class="text-gray-500 dark:text-gray-400 mb-6">
                Comece criando seu primeiro atendente
            </p>
            <button onclick="openCreateModal()" class="bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded-lg inline-flex items-center gap-2 shadow-lg">
                <i class="fas fa-plus"></i>
                Criar Atendente
            </button>
        </div>

        <!-- Loading -->
        <div id="loading-state" class="text-center py-16">
            <i class="fas fa-spinner fa-spin text-green-600 text-5xl mb-4"></i>
            <p class="text-gray-600 dark:text-gray-400 text-lg">Carregando atendentes...</p>
        </div>
    </div>
</div>

<!-- Modal Criar/Editar Atendente -->
<div id="user-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800 z-10">
            <div class="flex items-center justify-between">
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-3" id="modal-title">
                    <i class="fas fa-user-plus text-green-600"></i>
                    Novo Atendente
                </h3>
                <button onclick="closeUserModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>

        <form id="user-form" class="p-6">
            <input type="hidden" id="user-id" name="user_id">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Nome Completo <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="user-name" name="name" required
                           class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white"
                           placeholder="João Silva">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Email <span class="text-red-500">*</span>
                    </label>
                    <input type="email" id="user-email" name="email" required
                           class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white"
                           placeholder="joao@empresa.com">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Telefone
                    </label>
                    <input type="tel" id="user-phone" name="phone"
                           class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white"
                           placeholder="(11) 99999-9999">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Senha <span class="text-red-500" id="password-required">*</span>
                    </label>
                    <div class="relative">
                        <input type="password" id="user-password" name="password"
                               class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white pr-12"
                               placeholder="Mínimo 6 caracteres">
                        <button type="button" onclick="togglePassword()" class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                            <i class="fas fa-eye" id="password-icon"></i>
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 hidden" id="password-hint">
                        Deixe em branco para manter a senha atual
                    </p>
                </div>
            </div>

            <!-- Setores -->
            <div class="mt-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                    Setores <span class="text-red-500">*</span>
                </label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 max-h-64 overflow-y-auto border border-gray-300 dark:border-gray-600 rounded-lg p-4">
                    <?php if (empty($departments)): ?>
                        <p class="text-gray-500 dark:text-gray-400 col-span-2 text-center py-4">
                            Nenhum setor disponível. <a href="departments.php" class="text-green-600 hover:underline font-medium">Criar setores</a>
                        </p>
                    <?php else: ?>
                        <?php foreach ($departments as $dept): ?>
                            <label class="flex items-center space-x-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-3 rounded-lg transition-colors">
                                <input type="checkbox" name="departments[]" value="<?php echo $dept['id']; ?>" 
                                       class="w-5 h-5 text-green-600 border-gray-300 rounded focus:ring-green-500">
                                <span class="flex items-center gap-2 flex-1">
                                    <span class="w-4 h-4 rounded-full flex-shrink-0" style="background-color: <?php echo $dept['color']; ?>"></span>
                                    <span class="text-gray-700 dark:text-gray-300 font-medium"><?php echo htmlspecialchars($dept['name']); ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Permissões de Menu - Apenas no modo de edição -->
            <div id="permissions-section" class="mt-6 hidden">
                <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                    <div class="mb-4">
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <i class="fas fa-list-check text-blue-600"></i>
                            Permissões de Menu
                        </h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            Selecione quais menus este atendente pode visualizar
                        </p>
                    </div>
                    
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 space-y-3">
                        <!-- Atendimentos (sempre ativo) -->
                        <label class="flex items-center space-x-3 cursor-not-allowed opacity-75">
                            <input type="checkbox" name="menu_chat" value="1" checked disabled
                                   class="w-5 h-5 text-green-600 border-gray-300 rounded focus:ring-green-500">
                            <div class="flex-1">
                                <span class="font-medium text-gray-900 dark:text-white">
                                    <i class="fas fa-headset text-green-600 mr-2"></i>Atendimentos
                                </span>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Conversas e suporte (obrigatório)</p>
                            </div>
                        </label>
                        
                        <!-- Dashboard -->
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" name="menu_dashboard" value="1"
                                   class="w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <div class="flex-1">
                                <span class="font-medium text-gray-900 dark:text-white">
                                    <i class="fas fa-chart-bar text-blue-600 mr-2"></i>Dashboard
                                </span>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Indicadores e gráficos</p>
                            </div>
                        </label>
                        
                        <!-- Disparo -->
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" name="menu_dispatch" value="1"
                                   class="w-5 h-5 text-orange-600 border-gray-300 rounded focus:ring-orange-500">
                            <div class="flex-1">
                                <span class="font-medium text-gray-900 dark:text-white">
                                    <i class="fas fa-paper-plane text-orange-600 mr-2"></i>Disparo
                                </span>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Envio de mensagens em massa</p>
                            </div>
                        </label>
                        
                        <!-- Contatos -->
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" name="menu_contacts" value="1"
                                   class="w-5 h-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                            <div class="flex-1">
                                <span class="font-medium text-gray-900 dark:text-white">
                                    <i class="fas fa-users text-purple-600 mr-2"></i>Contatos
                                </span>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Gerenciar contatos</p>
                            </div>
                        </label>
                        
                        <!-- Kanban -->
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" name="menu_kanban" value="1"
                                   class="w-5 h-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                            <div class="flex-1">
                                <span class="font-medium text-gray-900 dark:text-white">
                                    <i class="fas fa-columns text-purple-600 mr-2"></i>Kanban
                                </span>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Pipeline de vendas e leads</p>
                            </div>
                        </label>
                        
                        <!-- Automação/Fluxos -->
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" name="can_manage_flows" id="can_manage_flows" value="1"
                                   class="w-5 h-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                            <div class="flex-1">
                                <span class="font-medium text-gray-900 dark:text-white">
                                    <i class="fas fa-robot text-indigo-600 mr-2"></i>Automação/Fluxos
                                </span>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Criar e gerenciar fluxos de automação próprios</p>
                            </div>
                        </label>
                        
                        <!-- Meu Perfil (sempre ativo) -->
                        <label class="flex items-center space-x-3 cursor-not-allowed opacity-75">
                            <input type="checkbox" name="menu_profile" value="1" checked disabled
                                   class="w-5 h-5 text-green-600 border-gray-300 rounded focus:ring-green-500">
                            <div class="flex-1">
                                <span class="font-medium text-gray-900 dark:text-white">
                                    <i class="fas fa-user-circle text-green-600 mr-2"></i>Meu Perfil
                                </span>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Perfil do usuário (obrigatório)</p>
                            </div>
                        </label>
                    </div>
                    
                    <div class="mt-3 bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-lg p-3">
                        <p class="text-sm text-blue-800 dark:text-blue-200">
                            <i class="fas fa-info-circle mr-2"></i>
                            <strong>Dica:</strong> Os menus "Atendimentos" e "Meu Perfil" são obrigatórios e sempre estarão visíveis.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Configuração de Instância WhatsApp -->
            <div id="instance-section" class="mt-6">
                <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                    <div class="mb-4">
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <i class="fab fa-whatsapp text-green-600"></i>
                            Instância WhatsApp
                        </h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            Defina se o atendente usará sua própria instância ou a do supervisor
                        </p>
                    </div>
                    
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 space-y-4">
                        <!-- Opção: Usar instância do supervisor -->
                        <label class="flex items-start space-x-3 cursor-pointer p-3 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors border-2 border-transparent has-[:checked]:border-green-500 has-[:checked]:bg-green-50 dark:has-[:checked]:bg-green-900/20">
                            <input type="radio" name="instance_type" id="instance_supervisor" value="supervisor" checked
                                   class="w-5 h-5 text-green-600 border-gray-300 focus:ring-green-500 mt-0.5"
                                   onchange="toggleInstanceOptions()">
                            <div class="flex-1">
                                <span class="font-medium text-gray-900 dark:text-white flex items-center gap-2">
                                    <i class="fas fa-link text-blue-600"></i>
                                    Usar instância do Supervisor
                                </span>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    O atendente utilizará a mesma conexão WhatsApp do supervisor. Todas as mensagens serão enviadas pelo número do supervisor.
                                </p>
                            </div>
                        </label>
                        
                        <!-- Opção: Instância própria -->
                        <label class="flex items-start space-x-3 cursor-pointer p-3 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors border-2 border-transparent has-[:checked]:border-green-500 has-[:checked]:bg-green-50 dark:has-[:checked]:bg-green-900/20">
                            <input type="radio" name="instance_type" id="instance_own" value="own"
                                   class="w-5 h-5 text-green-600 border-gray-300 focus:ring-green-500 mt-0.5"
                                   onchange="toggleInstanceOptions()">
                            <div class="flex-1">
                                <span class="font-medium text-gray-900 dark:text-white flex items-center gap-2">
                                    <i class="fas fa-mobile-alt text-purple-600"></i>
                                    Instância Própria do Atendente
                                </span>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    O atendente terá sua própria conexão WhatsApp. Ele precisará escanear um QR Code para conectar seu número.
                                </p>
                            </div>
                        </label>
                        
                        <!-- Opções adicionais para instância própria -->
                        <div id="own-instance-options" class="hidden ml-8 mt-2 p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-800">
                            <div class="flex items-center gap-2 mb-3">
                                <i class="fas fa-cog text-purple-600"></i>
                                <span class="font-medium text-purple-900 dark:text-purple-100">Configurações da Instância</span>
                            </div>
                            
                            <label class="flex items-center space-x-3 cursor-pointer">
                                <input type="checkbox" name="instance_config_allowed" id="instance_config_allowed" value="1" checked
                                       class="w-5 h-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                                <div>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                                        Permitir que o atendente configure sua instância
                                    </span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        O atendente poderá ver o QR Code e reconectar quando necessário
                                    </p>
                                </div>
                            </label>
                            
                            <div class="mt-3 p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded border border-yellow-200 dark:border-yellow-700">
                                <p class="text-xs text-yellow-800 dark:text-yellow-200">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    <strong>Importante:</strong> Apenas você (supervisor) poderá desconectar a instância do atendente.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Status da instância (apenas no modo edição) -->
                    <div id="instance-status-section" class="hidden mt-4">
                        <div id="instance-status-content" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                            <!-- Será preenchido via JavaScript -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Autenticação de Dois Fatores (2FA) - Apenas no modo de edição -->
            <div id="2fa-section" class="mt-6 hidden">
                <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                <i class="fas fa-shield-alt text-green-600"></i>
                                Autenticação de Dois Fatores (2FA)
                            </h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Adicione uma camada extra de segurança ao login deste atendente
                            </p>
                        </div>
                        <div id="2fa-status-badge" class="hidden">
                            <!-- Badge será inserido via JavaScript -->
                        </div>
                    </div>

                    <!-- Status do 2FA -->
                    <div id="2fa-disabled-state" class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-gray-200 dark:bg-gray-600 rounded-full flex items-center justify-center">
                                    <i class="fas fa-shield-alt text-gray-500 dark:text-gray-400 text-xl"></i>
                                </div>
                            </div>
                            <div class="flex-1">
                                <h5 class="font-medium text-gray-900 dark:text-white mb-1">2FA Desativado</h5>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                    Este atendente não possui autenticação de dois fatores configurada.
                                </p>
                                <div class="flex gap-3">
                                    <button type="button" onclick="enable2FA(false)" 
                                            class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium transition-colors">
                                        <i class="fas fa-shield-alt mr-2"></i>
                                        Ativar 2FA
                                    </button>
                                    <button type="button" onclick="enable2FA(true)" 
                                            class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium transition-colors">
                                        <i class="fas fa-lock mr-2"></i>
                                        Ativar 2FA (Obrigatório)
                                    </button>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    <strong>Obrigatório:</strong> O atendente não poderá desativar o 2FA sozinho
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- 2FA Ativo -->
                    <div id="2fa-enabled-state" class="hidden">
                        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-green-100 dark:bg-green-800 rounded-full flex items-center justify-center">
                                        <i class="fas fa-shield-check text-green-600 dark:text-green-400 text-xl"></i>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between mb-1">
                                        <h5 class="font-medium text-green-900 dark:text-green-100">2FA Ativo</h5>
                                        <span id="2fa-forced-badge" class="hidden px-2 py-1 bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 text-xs font-medium rounded">
                                            <i class="fas fa-lock mr-1"></i>Obrigatório
                                        </span>
                                    </div>
                                    <p class="text-sm text-green-700 dark:text-green-300 mb-3">
                                        Este atendente possui autenticação de dois fatores configurada.
                                    </p>
                                    <div class="flex gap-3">
                                        <button type="button" onclick="regenerateBackupCodes()" 
                                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition-colors">
                                            <i class="fas fa-sync mr-2"></i>
                                            Regenerar Códigos de Backup
                                        </button>
                                        <button type="button" onclick="disable2FA()" 
                                                class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium transition-colors">
                                            <i class="fas fa-times mr-2"></i>
                                            Desativar 2FA
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botões -->
            <div class="mt-8 flex justify-end gap-3">
                <button type="button" onclick="closeUserModal()" 
                        class="px-6 py-3 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors font-medium">
                    <i class="fas fa-times mr-2"></i>
                    Cancelar
                </button>
                <button type="submit" 
                        class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors font-medium shadow-lg">
                    <i class="fas fa-save mr-2"></i>
                    Salvar Atendente
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal QR Code 2FA -->
<div id="qrcode-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-lg w-full">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                    <i class="fas fa-shield-check text-green-600"></i>
                    2FA Ativado com Sucesso!
                </h3>
                <button onclick="closeQRCodeModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>

        <div class="refined-container">
            <div class="text-center mb-6">
                <p class="text-gray-600 dark:text-gray-400 mb-4">
                    <i class="fas fa-mobile-alt mr-2"></i>
                    Escaneie este QR Code no <strong>Google Authenticator</strong> ou app compatível:
                </p>
                <div class="bg-white p-4 rounded-lg inline-block border-2 border-gray-200">
                    <img id="qrcode-image" src="" alt="QR Code 2FA" class="w-64 h-64">
                </div>
            </div>

            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-6">
                <h4 class="font-semibold text-yellow-900 dark:text-yellow-100 mb-2 flex items-center gap-2">
                    <i class="fas fa-key"></i>
                    Códigos de Backup
                </h4>
                <p class="text-sm text-yellow-800 dark:text-yellow-200 mb-3">
                    Guarde estes códigos em local seguro. Use-os se perder acesso ao app:
                </p>
                <div id="backup-codes-list" class="bg-white dark:bg-gray-900 rounded p-3 font-mono text-sm space-y-1 max-h-48 overflow-y-auto">
                    <!-- Códigos serão inseridos aqui -->
                </div>
                <button onclick="copyBackupCodes()" class="mt-3 w-full px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg text-sm font-medium transition-colors">
                    <i class="fas fa-copy mr-2"></i>
                    Copiar Códigos
                </button>
            </div>

            <div class="flex gap-3">
                <button onclick="printQRCode()" class="flex-1 px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                    <i class="fas fa-print mr-2"></i>
                    Imprimir
                </button>
                <button onclick="closeQRCodeModal()" class="flex-1 px-4 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition-colors">
                    <i class="fas fa-check mr-2"></i>
                    Concluir
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Códigos de Backup -->
<div id="backup-codes-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                    <i class="fas fa-key text-blue-600"></i>
                    Novos Códigos de Backup
                </h3>
                <button onclick="closeBackupCodesModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>

        <div class="refined-container">
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-4">
                <p class="text-sm text-red-800 dark:text-red-200">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong>Atenção:</strong> Os códigos antigos foram invalidados. Guarde estes novos códigos em local seguro.
                </p>
            </div>

            <div id="new-backup-codes-list" class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 font-mono text-sm space-y-1 max-h-64 overflow-y-auto mb-4">
                <!-- Códigos serão inseridos aqui -->
            </div>

            <div class="flex gap-3">
                <button onclick="copyNewBackupCodes()" class="flex-1 px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                    <i class="fas fa-copy mr-2"></i>
                    Copiar Códigos
                </button>
                <button onclick="closeBackupCodesModal()" class="flex-1 px-4 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition-colors">
                    <i class="fas fa-check mr-2"></i>
                    Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal QR Code Instância WhatsApp -->
<div id="whatsapp-qr-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-lg w-full">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                    <i class="fab fa-whatsapp text-green-600"></i>
                    Conectar WhatsApp
                </h3>
                <button onclick="closeWhatsAppQRModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>

        <div class="refined-container">
            <div id="qr-loading" class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-green-600 text-4xl mb-4"></i>
                <p class="text-gray-600 dark:text-gray-400">Gerando QR Code...</p>
            </div>
            
            <div id="qr-display" class="hidden text-center">
                <p class="text-gray-600 dark:text-gray-400 mb-4">
                    <i class="fas fa-mobile-alt mr-2"></i>
                    Escaneie o QR Code com o WhatsApp do atendente:
                </p>
                <div class="bg-white p-4 rounded-lg inline-block border-2 border-green-200">
                    <img id="whatsapp-qr-image" src="" alt="QR Code WhatsApp" class="w-64 h-64">
                </div>
                <p class="text-xs text-gray-500 mt-3">
                    <i class="fas fa-clock mr-1"></i>
                    O QR Code expira em <span id="qr-countdown">60</span> segundos
                </p>
            </div>
            
            <div id="qr-connected" class="hidden text-center py-8">
                <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-check text-green-600 text-4xl"></i>
                </div>
                <h4 class="text-xl font-bold text-green-600 mb-2">Conectado com Sucesso!</h4>
                <p class="text-gray-600 dark:text-gray-400" id="connected-phone-info"></p>
            </div>
            
            <div id="qr-error" class="hidden text-center py-8">
                <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-times text-red-600 text-4xl"></i>
                </div>
                <h4 class="text-xl font-bold text-red-600 mb-2">Erro ao Conectar</h4>
                <p class="text-gray-600 dark:text-gray-400" id="qr-error-message"></p>
                <button onclick="generateWhatsAppQR()" class="mt-4 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg">
                    <i class="fas fa-sync mr-2"></i>Tentar Novamente
                </button>
            </div>
        </div>
        
        <div class="p-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3">
            <button onclick="closeWhatsAppQRModal()" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg">
                Fechar
            </button>
        </div>
    </div>
</div>

<!-- Modal Gerenciar Instância -->
<div id="manage-instance-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800">
            <div class="flex items-center justify-between">
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                    <i class="fab fa-whatsapp text-green-600"></i>
                    Gerenciar Instância
                </h3>
                <button onclick="closeManageInstanceModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>

        <div class="refined-container">
            <div id="instance-info" class="mb-6">
                <!-- Preenchido via JavaScript -->
            </div>
            
            <div id="instance-stats" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <!-- Estatísticas -->
            </div>
            
            <div id="instance-logs" class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                <h4 class="font-semibold text-gray-900 dark:text-white mb-3">
                    <i class="fas fa-history mr-2"></i>Histórico de Conexões
                </h4>
                <div id="logs-list" class="space-y-2 max-h-48 overflow-y-auto">
                    <!-- Logs -->
                </div>
            </div>
        </div>
        
        <div class="p-4 border-t border-gray-200 dark:border-gray-700 flex justify-between">
            <button onclick="disconnectAttendantInstance()" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg">
                <i class="fas fa-unlink mr-2"></i>Desconectar
            </button>
            <button onclick="closeManageInstanceModal()" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg">
                Fechar
            </button>
        </div>
    </div>
</div>

<script src="/assets/js/supervisor_users.js?v=<?php echo time(); ?>"></script>
<script>
// ==========================================
// GERENCIAMENTO DE INSTÂNCIAS WHATSAPP
// ==========================================

let currentInstanceAttendantId = null;
let qrCheckInterval = null;
let qrCountdownInterval = null;

function toggleInstanceOptions() {
    const instanceType = document.querySelector('input[name="instance_type"]:checked').value;
    const ownOptions = document.getElementById('own-instance-options');
    
    if (instanceType === 'own') {
        ownOptions.classList.remove('hidden');
    } else {
        ownOptions.classList.add('hidden');
    }
}

function openWhatsAppQRModal(attendantId) {
    currentInstanceAttendantId = attendantId;
    document.getElementById('whatsapp-qr-modal').classList.remove('hidden');
    document.getElementById('whatsapp-qr-modal').classList.add('flex');
    
    // Reset states
    document.getElementById('qr-loading').classList.remove('hidden');
    document.getElementById('qr-display').classList.add('hidden');
    document.getElementById('qr-connected').classList.add('hidden');
    document.getElementById('qr-error').classList.add('hidden');
    
    generateWhatsAppQR();
}

function closeWhatsAppQRModal() {
    document.getElementById('whatsapp-qr-modal').classList.add('hidden');
    document.getElementById('whatsapp-qr-modal').classList.remove('flex');
    
    if (qrCheckInterval) clearInterval(qrCheckInterval);
    if (qrCountdownInterval) clearInterval(qrCountdownInterval);
    
    currentInstanceAttendantId = null;
    loadUsers(); // Recarregar lista
}

async function generateWhatsAppQR() {
    document.getElementById('qr-loading').classList.remove('hidden');
    document.getElementById('qr-display').classList.add('hidden');
    document.getElementById('qr-error').classList.add('hidden');
    
    try {
        const formData = new FormData();
        formData.append('action', 'generate_qr');
        formData.append('attendant_id', currentInstanceAttendantId);
        
        const response = await fetch('/api/attendant_instance.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success && data.qr_code) {
            document.getElementById('qr-loading').classList.add('hidden');
            document.getElementById('qr-display').classList.remove('hidden');
            document.getElementById('whatsapp-qr-image').src = data.qr_code;
            
            // Iniciar countdown
            let countdown = 60;
            document.getElementById('qr-countdown').textContent = countdown;
            
            qrCountdownInterval = setInterval(() => {
                countdown--;
                document.getElementById('qr-countdown').textContent = countdown;
                if (countdown <= 0) {
                    clearInterval(qrCountdownInterval);
                    generateWhatsAppQR(); // Regenerar
                }
            }, 1000);
            
            // Verificar conexão a cada 3 segundos
            qrCheckInterval = setInterval(checkWhatsAppConnection, 3000);
        } else {
            throw new Error(data.error || 'Erro ao gerar QR Code');
        }
    } catch (error) {
        document.getElementById('qr-loading').classList.add('hidden');
        document.getElementById('qr-error').classList.remove('hidden');
        document.getElementById('qr-error-message').textContent = error.message;
    }
}

async function checkWhatsAppConnection() {
    try {
        const response = await fetch(`/api/attendant_instance.php?action=check_connection&attendant_id=${currentInstanceAttendantId}`);
        const data = await response.json();
        
        if (data.success && data.status === 'connected') {
            clearInterval(qrCheckInterval);
            clearInterval(qrCountdownInterval);
            
            document.getElementById('qr-display').classList.add('hidden');
            document.getElementById('qr-connected').classList.remove('hidden');
            document.getElementById('connected-phone-info').textContent = 
                `Número: ${data.phone_number || 'N/A'} - ${data.phone_name || ''}`;
            
            setTimeout(closeWhatsAppQRModal, 3000);
        }
    } catch (error) {
        console.error('Erro ao verificar conexão:', error);
    }
}

function openManageInstanceModal(attendantId) {
    currentInstanceAttendantId = attendantId;
    document.getElementById('manage-instance-modal').classList.remove('hidden');
    document.getElementById('manage-instance-modal').classList.add('flex');
    
    loadInstanceDetails(attendantId);
}

function closeManageInstanceModal() {
    document.getElementById('manage-instance-modal').classList.add('hidden');
    document.getElementById('manage-instance-modal').classList.remove('flex');
    currentInstanceAttendantId = null;
}

async function loadInstanceDetails(attendantId) {
    try {
        // Carregar status
        const statusRes = await fetch(`/api/attendant_instance.php?action=check_connection&attendant_id=${attendantId}`);
        const statusData = await statusRes.json();
        
        // Carregar logs
        const logsRes = await fetch(`/api/attendant_instance.php?action=get_connection_logs&attendant_id=${attendantId}&limit=10`);
        const logsData = await logsRes.json();
        
        // Renderizar info
        const infoHtml = `
            <div class="flex items-center gap-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <div class="w-16 h-16 rounded-full flex items-center justify-center ${statusData.status === 'connected' ? 'bg-green-100' : 'bg-gray-200'}">
                    <i class="fab fa-whatsapp text-3xl ${statusData.status === 'connected' ? 'text-green-600' : 'text-gray-400'}"></i>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-lg">${statusData.phone_number || 'Não conectado'}</span>
                        <span class="px-2 py-1 text-xs rounded-full ${statusData.status === 'connected' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'}">
                            ${statusData.status === 'connected' ? 'Conectado' : 'Desconectado'}
                        </span>
                    </div>
                    <p class="text-sm text-gray-500">${statusData.phone_name || ''}</p>
                </div>
                ${statusData.status !== 'connected' ? `
                    <button onclick="openWhatsAppQRModal(${attendantId})" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg">
                        <i class="fas fa-qrcode mr-2"></i>Conectar
                    </button>
                ` : ''}
            </div>
        `;
        document.getElementById('instance-info').innerHTML = infoHtml;
        
        // Renderizar logs
        if (logsData.success && logsData.logs.length > 0) {
            const logsHtml = logsData.logs.map(log => `
                <div class="flex items-center gap-3 p-2 bg-white dark:bg-gray-800 rounded text-sm">
                    <i class="fas ${getLogIcon(log.action)} ${getLogColor(log.action)}"></i>
                    <span class="flex-1">${getLogText(log.action)}</span>
                    <span class="text-xs text-gray-500">${formatDate(log.created_at)}</span>
                </div>
            `).join('');
            document.getElementById('logs-list').innerHTML = logsHtml;
        } else {
            document.getElementById('logs-list').innerHTML = '<p class="text-gray-500 text-sm">Nenhum log disponível</p>';
        }
        
    } catch (error) {
        console.error('Erro ao carregar detalhes:', error);
    }
}

async function disconnectAttendantInstance() {
    if (!confirm('Tem certeza que deseja desconectar a instância deste atendente?')) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'disconnect_attendant');
        formData.append('attendant_id', currentInstanceAttendantId);
        
        const response = await fetch('/api/attendant_instance.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Instância desconectada com sucesso', 'success');
            closeManageInstanceModal();
            loadUsers();
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        showNotification(error.message, 'error');
    }
}

async function createAttendantInstance(attendantId) {
    try {
        const formData = new FormData();
        formData.append('action', 'create_instance');
        formData.append('attendant_id', attendantId);
        
        const response = await fetch('/api/attendant_instance.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Instância criada! Agora conecte o WhatsApp.', 'success');
            openWhatsAppQRModal(attendantId);
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        showNotification(error.message, 'error');
    }
}

function getLogIcon(action) {
    const icons = {
        'connect': 'fa-plug',
        'disconnect': 'fa-unlink',
        'qr_generated': 'fa-qrcode',
        'qr_scanned': 'fa-check-circle',
        'error': 'fa-exclamation-triangle',
        'reconnect': 'fa-sync'
    };
    return icons[action] || 'fa-circle';
}

function getLogColor(action) {
    const colors = {
        'connect': 'text-green-600',
        'disconnect': 'text-red-600',
        'qr_generated': 'text-blue-600',
        'qr_scanned': 'text-green-600',
        'error': 'text-red-600',
        'reconnect': 'text-yellow-600'
    };
    return colors[action] || 'text-gray-600';
}

function getLogText(action) {
    const texts = {
        'connect': 'Conexão iniciada',
        'disconnect': 'Desconectado',
        'qr_generated': 'QR Code gerado',
        'qr_scanned': 'QR Code escaneado',
        'error': 'Erro na conexão',
        'reconnect': 'Reconexão'
    };
    return texts[action] || action;
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
}

function showNotification(message, type) {
    // Usar função existente ou criar toast simples
    if (typeof showToast === 'function') {
        showToast(message, type);
    } else {
        alert(message);
    }
}
</script>

<?php include 'includes/footer_spa.php'; ?>
