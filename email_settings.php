<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$user_type = $_SESSION['user_type'] ?? 'user';
$is_supervisor = isset($_SESSION['is_supervisor']) && $_SESSION['is_supervisor'] == 1;
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;

// Apenas Admin pode acessar
if (!$is_admin) {
    header('Location: chat.php');
    exit;
}

// Verificar mensagens OAuth
$oauth_success = isset($_GET['oauth_success']);
$oauth_email = $_GET['email'] ?? '';
$oauth_error = $_GET['oauth_error'] ?? '';

// Verificar status OAuth atual
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT oauth_provider, from_email FROM email_settings WHERE user_id = ?");
$stmt->execute([$user_id]);
$email_config = $stmt->fetch(PDO::FETCH_ASSOC);
$is_oauth_connected = !empty($email_config['oauth_provider']);
$connected_email = $email_config['from_email'] ?? '';

$page_title = 'Notificações Email';
include 'includes/header_spa.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                <i class="fas fa-envelope text-blue-600"></i>
                Notificações por Email
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">
                Configure o envio de notificações automáticas por email
            </p>
        </div>
        <button onclick="testEmail()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors flex items-center gap-2">
            <i class="fas fa-paper-plane"></i>
            Testar Email
        </button>
    </div>

    <!-- Alertas OAuth -->
    <?php if ($oauth_success): ?>
    <div class="mb-6 p-4 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-600 rounded-lg">
        <div class="flex items-center">
            <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-xl mr-3"></i>
            <div>
                <p class="font-semibold text-green-800 dark:text-green-200">Conta Microsoft conectada com sucesso!</p>
                <p class="text-sm text-green-700 dark:text-green-300">Email: <?php echo htmlspecialchars($oauth_email); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($oauth_error): ?>
    <div class="mb-6 p-4 bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-600 rounded-lg">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle text-red-600 dark:text-red-400 text-xl mr-3"></i>
            <div>
                <p class="font-semibold text-red-800 dark:text-red-200">Erro ao conectar conta Microsoft</p>
                <p class="text-sm text-red-700 dark:text-red-300"><?php echo htmlspecialchars($oauth_error); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Card Microsoft 365 OAuth -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                    <i class="fab fa-microsoft text-blue-600 dark:text-blue-400 text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Microsoft 365 / Outlook</h3>
                    <?php if ($is_oauth_connected): ?>
                    <p class="text-sm text-green-600 dark:text-green-400">
                        <i class="fas fa-check-circle mr-1"></i>
                        Conectado: <?php echo htmlspecialchars($connected_email); ?>
                    </p>
                    <?php else: ?>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Conecte sua conta Microsoft para enviar emails</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <?php if ($is_oauth_connected): ?>
                <button onclick="disconnectMicrosoft()" class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg font-medium transition-colors inline-flex items-center gap-2">
                    <i class="fas fa-unlink"></i>
                    Desconectar
                </button>
                <a href="/oauth_authorize.php" class="px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg font-medium transition-colors inline-flex items-center gap-2">
                    <i class="fas fa-sync-alt"></i>
                    Reconectar
                </a>
                <?php else: ?>
                <a href="/oauth_authorize.php" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors inline-flex items-center gap-2">
                    <i class="fab fa-microsoft"></i>
                    Conectar Microsoft 365
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Cards de Estatísticas -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Enviados</p>
                    <p id="total-sent" class="text-3xl font-bold text-gray-900 dark:text-white mt-2">-</p>
                </div>
                <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Falhados</p>
                    <p id="total-failed" class="text-3xl font-bold text-gray-900 dark:text-white mt-2">-</p>
                </div>
                <div class="w-12 h-12 bg-red-100 dark:bg-red-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-times-circle text-red-600 dark:text-red-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Hoje</p>
                    <p id="sent-today" class="text-3xl font-bold text-gray-900 dark:text-white mt-2">-</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-calendar-day text-blue-600 dark:text-blue-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Taxa Sucesso</p>
                    <p id="success-rate" class="text-3xl font-bold text-gray-900 dark:text-white mt-2">-</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-percentage text-purple-600 dark:text-purple-400 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg mb-6">
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="flex space-x-4 px-6" aria-label="Tabs">
                <button onclick="switchTab('smtp')" id="tab-smtp" class="tab-button active px-4 py-4 text-sm font-medium border-b-2 border-blue-600 text-blue-600">
                    <i class="fas fa-server mr-2"></i>Configurações SMTP
                </button>
                <button onclick="switchTab('preferences')" id="tab-preferences" class="tab-button px-4 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                    <i class="fas fa-cog mr-2"></i>Preferências
                </button>
                <button onclick="switchTab('templates')" id="tab-templates" class="tab-button px-4 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                    <i class="fas fa-file-alt mr-2"></i>Templates
                </button>
                <button onclick="switchTab('logs')" id="tab-logs" class="tab-button px-4 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                    <i class="fas fa-history mr-2"></i>Logs
                </button>
            </nav>
        </div>

        <div class="p-6">
            <!-- Tab: SMTP -->
            <div id="content-smtp" class="tab-content">
                <form id="smtp-form" onsubmit="saveSettings(event)">
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Host SMTP *</label>
                            <input type="text" id="smtp-host" name="smtp_host" required
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="smtp.gmail.com">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Porta *</label>
                            <input type="number" id="smtp-port" name="smtp_port" required
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="587">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Usuário *</label>
                            <input type="text" id="smtp-username" name="smtp_username" required
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="seu-email@gmail.com">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Senha *</label>
                            <input type="password" id="smtp-password" name="smtp_password"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="••••••••">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Criptografia</label>
                            <select id="smtp-encryption" name="smtp_encryption"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="tls">TLS</option>
                                <option value="ssl">SSL</option>
                                <option value="none">Nenhuma</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email Remetente</label>
                            <input type="email" id="from-email" name="from_email"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="noreply@empresa.com">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Nome Remetente</label>
                        <input type="text" id="from-name" name="from_name"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                               placeholder="Sistema de Atendimento">
                    </div>

                    <div class="mb-6">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" id="is-enabled" name="is_enabled" checked
                                   class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Notificações habilitadas</span>
                        </label>
                    </div>

                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                        <i class="fas fa-save mr-2"></i>Salvar Configurações
                    </button>
                </form>
            </div>

            <!-- Tab: Preferências -->
            <div id="content-preferences" class="tab-content hidden">
                <form id="preferences-form" onsubmit="savePreferences(event)">
                    <h3 class="text-lg font-semibold mb-4">Notificações Instantâneas</h3>
                    <div class="space-y-2 mb-6">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="notify_new_conversation" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Nova conversa</span>
                        </label>
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="notify_conversation_assigned" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Conversa atribuída</span>
                        </label>
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="notify_sla_warning" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Alerta de SLA</span>
                        </label>
                    </div>

                    <h3 class="text-lg font-semibold mb-4">Resumos Periódicos</h3>
                    <div class="space-y-4 mb-6">
                        <div class="flex items-center gap-4">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" name="daily_summary" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Resumo diário às</span>
                            </label>
                            <input type="time" name="daily_summary_time" value="18:00"
                                   class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="weekly_summary" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Resumo semanal</span>
                        </label>
                    </div>

                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                        <i class="fas fa-save mr-2"></i>Salvar Preferências
                    </button>
                </form>
            </div>

            <!-- Tab: Templates -->
            <div id="content-templates" class="tab-content hidden">
                <div id="templates-list"></div>
            </div>

            <!-- Tab: Logs -->
            <div id="content-logs" class="tab-content hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Data/Hora</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Destinatário</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Assunto</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody id="logs-table-body" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/email_settings.js?v=<?php echo time(); ?>"></script>

<?php include 'includes/footer_spa.php'; ?>
