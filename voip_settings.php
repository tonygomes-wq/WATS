<?php
/**
 * Configurações VoIP - Provedor e Credenciais
 * Página para configurar servidor FreeSWITCH e credenciais SIP
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? 'user';
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
$is_supervisor = isset($_SESSION['is_supervisor']) && $_SESSION['is_supervisor'] == 1;

// Apenas Admin pode configurar provedor VoIP
if (!$is_admin) {
    header('Location: chat.php');
    exit;
}

// Buscar configurações VoIP globais
$stmt = $pdo->prepare("
    SELECT * FROM voip_provider_settings 
    WHERE id = 1
");
$stmt->execute();
$provider_config = $stmt->fetch(PDO::FETCH_ASSOC);

// Buscar configurações do usuário
$stmt = $pdo->prepare("
    SELECT * FROM voip_users 
    WHERE user_id = ?
");
$stmt->execute([$user_id]);
$user_voip = $stmt->fetch(PDO::FETCH_ASSOC);

// Verificar status de conexão
$is_configured = !empty($provider_config['server_host']);
$has_user_account = !empty($user_voip);

$page_title = 'Configurações VoIP';
include 'includes/header_spa.php';
?>

<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                <i class="fas fa-phone-volume text-purple-600"></i>
                Configurações VoIP
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">
                Configure o provedor VoIP e credenciais de telefonia
            </p>
        </div>
        <button onclick="testVoIPConnection()" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium transition-colors flex items-center gap-2">
            <i class="fas fa-plug"></i>
            Testar Conexão
        </button>
    </div>

    <!-- Status Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <!-- Provedor Configurado -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-full flex items-center justify-center <?= $is_configured ? 'bg-green-100' : 'bg-gray-200' ?>">
                    <i class="fas fa-server text-xl <?= $is_configured ? 'text-green-600' : 'text-gray-400' ?>"></i>
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Provedor</div>
                    <div class="text-xs <?= $is_configured ? 'text-green-600' : 'text-gray-500' ?>">
                        <?= $is_configured ? 'Configurado' : 'Não configurado' ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Conta do Usuário -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-full flex items-center justify-center <?= $has_user_account ? 'bg-blue-100' : 'bg-gray-200' ?>">
                    <i class="fas fa-user text-xl <?= $has_user_account ? 'text-blue-600' : 'text-gray-400' ?>"></i>
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Minha Conta</div>
                    <div class="text-xs <?= $has_user_account ? 'text-blue-600' : 'text-gray-500' ?>">
                        <?= $has_user_account ? 'Ramal: ' . $user_voip['sip_extension'] : 'Não criada' ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Geral -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-full flex items-center justify-center <?= ($is_configured && $has_user_account) ? 'bg-green-100' : 'bg-gray-200' ?>">
                    <i class="fas fa-check-circle text-xl <?= ($is_configured && $has_user_account) ? 'text-green-600' : 'text-gray-400' ?>"></i>
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Status</div>
                    <div class="text-xs <?= ($is_configured && $has_user_account) ? 'text-green-600' : 'text-gray-500' ?>">
                        <?= ($is_configured && $has_user_account) ? 'Pronto para usar' : 'Configuração pendente' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Guia de Configuração -->
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-6 mb-6">
        <h3 class="text-lg font-semibold mb-3 text-blue-900 dark:text-blue-100">
            <i class="fas fa-book mr-2"></i>
            Como Configurar
        </h3>
        <ol class="space-y-2 text-sm ml-4 list-decimal text-blue-800 dark:text-blue-200">
            <li><strong>Instale o FreeSWITCH:</strong> No servidor, instale o FreeSWITCH seguindo o guia de instalação</li>
            <li><strong>Configure o mod_verto:</strong> Habilite o módulo WebRTC no FreeSWITCH</li>
            <li><strong>Configure SSL/TLS:</strong> Gere certificados SSL para conexão segura (WSS)</li>
            <li><strong>Preencha os dados abaixo:</strong> Host, porta, domínio e senha ESL</li>
            <li><strong>Teste a conexão:</strong> Clique em "Testar Conexão" para verificar</li>
            <li><strong>Crie sua conta VoIP:</strong> Após configurar o provedor, crie seu ramal</li>
        </ol>
    </div>

    <!-- Formulário de Configuração do Provedor -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white flex items-center gap-2">
            <i class="fas fa-server text-purple-600"></i>
            Configuração do Provedor VoIP
        </h2>

        <form id="provider-form" class="space-y-4">
            <!-- Tipo de Provedor -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Tipo de Provedor
                </label>
                <select name="provider_type" id="provider_type" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white">
                    <option value="freeswitch" <?= ($provider_config['provider_type'] ?? '') == 'freeswitch' ? 'selected' : '' ?>>FreeSWITCH (Recomendado)</option>
                    <option value="asterisk" <?= ($provider_config['provider_type'] ?? '') == 'asterisk' ? 'selected' : '' ?>>Asterisk</option>
                    <option value="kamailio" <?= ($provider_config['provider_type'] ?? '') == 'kamailio' ? 'selected' : '' ?>>Kamailio</option>
                    <option value="custom" <?= ($provider_config['provider_type'] ?? '') == 'custom' ? 'selected' : '' ?>>Outro (SIP Genérico)</option>
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Host do Servidor -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Host do Servidor *
                    </label>
                    <input type="text" name="server_host" id="server_host" 
                           value="<?= htmlspecialchars($provider_config['server_host'] ?? '') ?>"
                           placeholder="voip.macip.com.br ou 192.168.1.100"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white"
                           required>
                </div>

                <!-- Porta WebSocket -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Porta WebSocket Secure (WSS) *
                    </label>
                    <input type="number" name="wss_port" id="wss_port" 
                           value="<?= htmlspecialchars($provider_config['wss_port'] ?? '8083') ?>"
                           placeholder="8083"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white"
                           required>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Domínio SIP -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Domínio SIP *
                    </label>
                    <input type="text" name="sip_domain" id="sip_domain" 
                           value="<?= htmlspecialchars($provider_config['sip_domain'] ?? 'wats.macip.com.br') ?>"
                           placeholder="wats.macip.com.br"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white"
                           required>
                </div>

                <!-- Porta ESL -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Porta ESL (Event Socket)
                    </label>
                    <input type="number" name="esl_port" id="esl_port" 
                           value="<?= htmlspecialchars($provider_config['esl_port'] ?? '8021') ?>"
                           placeholder="8021"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white">
                </div>
            </div>

            <!-- Senha ESL -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Senha ESL (Event Socket)
                </label>
                <input type="password" name="esl_password" id="esl_password" 
                       value="<?= htmlspecialchars($provider_config['esl_password'] ?? '') ?>"
                       placeholder="ClueCon"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white">
            </div>

            <!-- Servidor STUN -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Servidor STUN (Opcional)
                </label>
                <input type="text" name="stun_server" id="stun_server" 
                       value="<?= htmlspecialchars($provider_config['stun_server'] ?? 'stun:stun.l.google.com:19302') ?>"
                       placeholder="stun:stun.l.google.com:19302"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white">
            </div>

            <!-- Botões -->
            <div class="flex gap-3">
                <button type="submit" class="px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium transition-colors flex items-center gap-2">
                    <i class="fas fa-save"></i>
                    Salvar Configurações
                </button>
                <button type="button" onclick="resetForm()" class="px-6 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg font-medium transition-colors">
                    Cancelar
                </button>
            </div>
        </form>
    </div>

    <!-- Minha Conta VoIP -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
        <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white flex items-center gap-2">
            <i class="fas fa-user-circle text-blue-600"></i>
            Minha Conta VoIP
        </h2>

        <?php if ($has_user_account): ?>
            <!-- Conta Existente -->
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ramal</label>
                        <div class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg font-mono">
                            <?= htmlspecialchars($user_voip['sip_extension']) ?>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nome de Exibição</label>
                        <div class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg">
                            <?= htmlspecialchars($user_voip['display_name']) ?>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                    <div class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full <?= $user_voip['status'] == 'online' ? 'bg-green-500' : 'bg-gray-400' ?>"></span>
                        <?= ucfirst($user_voip['status']) ?>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button onclick="regeneratePassword()" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg font-medium transition-colors flex items-center gap-2">
                        <i class="fas fa-key"></i>
                        Regenerar Senha SIP
                    </button>
                    <button onclick="deleteAccount()" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition-colors flex items-center gap-2">
                        <i class="fas fa-trash"></i>
                        Excluir Conta
                    </button>
                </div>
            </div>
        <?php else: ?>
            <!-- Criar Conta -->
            <div class="text-center py-8">
                <i class="fas fa-user-plus text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>
                <p class="text-gray-600 dark:text-gray-400 mb-4">
                    Você ainda não possui uma conta VoIP. Crie uma para começar a fazer chamadas.
                </p>
                <button onclick="createVoIPAccount()" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors flex items-center gap-2 mx-auto">
                    <i class="fas fa-plus"></i>
                    Criar Minha Conta VoIP
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="/assets/js/voip-settings.js?v=<?= time() ?>"></script>

<?php include 'includes/footer.php'; ?>
