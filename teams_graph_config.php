<?php
/**
 * Configuração Microsoft Teams Graph API
 * Página para configurar credenciais do Azure AD e autenticar
 */

$page_title = 'Configurar Microsoft Teams (Graph API)';
require_once 'includes/header_spa.php';
require_once 'includes/channels/TeamsGraphAPI.php';

$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'] ?? 'user';
$teamsAPI = new TeamsGraphAPI($pdo, $userId);

// Verificar se é atendente (não admin, não supervisor)
$isAttendant = ($userType === 'attendant') && $teamsAPI->isAttendant();
$supervisorId = $isAttendant ? $teamsAPI->getSupervisorId() : null;

// Buscar credenciais salvas
if ($isAttendant && $supervisorId) {
    // Se for atendente, buscar credenciais do supervisor
    $stmt = $pdo->prepare("
        SELECT 
            teams_client_id,
            teams_tenant_id,
            name as supervisor_name
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$supervisorId]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Buscar tokens do próprio atendente
    $stmt = $pdo->prepare("
        SELECT 
            teams_access_token,
            teams_token_expires_at
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $tokens = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $config['teams_access_token'] = $tokens['teams_access_token'] ?? null;
    $config['teams_token_expires_at'] = $tokens['teams_token_expires_at'] ?? null;
} else {
    // Se for supervisor/admin, buscar suas próprias credenciais
    $stmt = $pdo->prepare("
        SELECT 
            teams_client_id,
            teams_tenant_id,
            teams_access_token,
            teams_token_expires_at
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
}

$isConfigured = !empty($config['teams_client_id']) && !empty($config['teams_tenant_id']);
$isAuthenticated = $teamsAPI->isAuthenticated();

// URL de callback
$redirectUri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
               "://{$_SERVER['HTTP_HOST']}/teams_oauth_callback.php";
?>

<div class="main-content">
    <!-- Header -->
    <div class="bg-white border-b border-gray-200 px-6 py-5">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 tracking-tight">
                    <i class="fas fa-users mr-2 text-purple-600"></i>
                    Microsoft Teams - Graph API
                </h1>
                <p class="text-sm text-gray-600 mt-1">Integração completa com chat bidirecional</p>
            </div>
            <div class="flex gap-3">
                <a href="channels.php" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors duration-150 text-sm font-medium text-gray-700">
                    <i class="fas fa-arrow-left mr-2"></i>Voltar
                </a>
            </div>
        </div>
    </div>
    
    <!-- Conteúdo -->
    <div class="p-6 bg-gray-50">
        <div class="max-w-4xl mx-auto space-y-6">
            
            <!-- Status da Integração -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-info-circle mr-2 text-blue-600"></i>
                    Status da Integração
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-lg">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center <?= $isConfigured ? 'bg-green-100' : 'bg-gray-200' ?>">
                            <i class="fas fa-cog text-xl <?= $isConfigured ? 'text-green-600' : 'text-gray-400' ?>"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-700">Configuração</div>
                            <div class="text-xs <?= $isConfigured ? 'text-green-600' : 'text-gray-500' ?>">
                                <?= $isConfigured ? 'Configurado' : 'Não configurado' ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-lg">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center <?= $isAuthenticated ? 'bg-green-100' : 'bg-gray-200' ?>">
                            <i class="fas fa-key text-xl <?= $isAuthenticated ? 'text-green-600' : 'text-gray-400' ?>"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-700">Autenticação</div>
                            <div class="text-xs <?= $isAuthenticated ? 'text-green-600' : 'text-gray-500' ?>">
                                <?= $isAuthenticated ? 'Conectado' : 'Não conectado' ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-lg">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center <?= ($isConfigured && $isAuthenticated) ? 'bg-green-100' : 'bg-gray-200' ?>">
                            <i class="fas fa-check-circle text-xl <?= ($isConfigured && $isAuthenticated) ? 'text-green-600' : 'text-gray-400' ?>"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-700">Status Geral</div>
                            <div class="text-xs <?= ($isConfigured && $isAuthenticated) ? 'text-green-600' : 'text-gray-500' ?>">
                                <?= ($isConfigured && $isAuthenticated) ? 'Ativo' : 'Inativo' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Guia de Configuração -->
            <?php if ($isAttendant && $supervisorId): ?>
            <!-- Aviso para Atendentes -->
            <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-700 rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-3 text-purple-900 dark:text-purple-100">
                    <i class="fas fa-info-circle mr-2"></i>
                    Configuração Herdada do Supervisor
                </h3>
                <div class="space-y-2 text-sm text-purple-800 dark:text-purple-200">
                    <p>
                        <i class="fas fa-check-circle mr-2 text-green-600 dark:text-green-400"></i>
                        Você está usando as credenciais configuradas pelo seu supervisor: <strong><?= htmlspecialchars($config['supervisor_name'] ?? 'Supervisor') ?></strong>
                    </p>
                    <p>
                        <i class="fas fa-shield-alt mr-2 text-blue-600 dark:text-blue-400"></i>
                        Não é necessário configurar Client ID, Client Secret ou Tenant ID.
                    </p>
                    <p>
                        <i class="fas fa-user-check mr-2 text-purple-600 dark:text-purple-400"></i>
                        Você só precisa <strong>conectar sua conta Microsoft</strong> clicando no botão abaixo.
                    </p>
                </div>
            </div>
            <?php else: ?>
            <!-- Guia de Configuração para Supervisores/Admins -->
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-3 text-blue-900 dark:text-blue-100">
                    <i class="fas fa-book mr-2"></i>
                    Como Configurar
                </h3>
                <ol class="space-y-2 text-sm ml-4 list-decimal text-blue-800 dark:text-blue-200">
                    <li><strong>Acesse o Azure Portal:</strong> <a href="https://portal.azure.com" target="_blank" class="underline text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">portal.azure.com</a></li>
                    <li><strong>Registre um aplicativo:</strong> Azure Active Directory → App registrations → New registration</li>
                    <li><strong>Configure permissões:</strong> API permissions → Add: Chat.ReadWrite, ChatMessage.Send, ChannelMessage.Send, User.Read</li>
                    <li><strong>Crie um Client Secret:</strong> Certificates & secrets → New client secret</li>
                    <li><strong>Configure Redirect URI:</strong> Authentication → Add platform → Web → <code class="bg-blue-100 dark:bg-blue-800 px-2 py-1 rounded text-blue-900 dark:text-blue-100"><?= $redirectUri ?></code></li>
                    <li><strong>Copie as credenciais:</strong> Application (client) ID, Directory (tenant) ID e Client Secret</li>
                    <li><strong>Cole abaixo e salve</strong></li>
                </ol>
                <?php if (!$isAttendant): ?>
                <div class="mt-4 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded">
                    <p class="text-sm text-green-800 dark:text-green-200">
                        <i class="fas fa-users mr-2"></i>
                        <strong>Nota:</strong> Ao configurar estas credenciais, todos os atendentes que você criar herdarão automaticamente estas configurações. Eles só precisarão conectar suas próprias contas Microsoft.
                    </p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
                    <li><strong>Cole abaixo e salve</strong></li>
                </ol>
            </div>
            
            <!-- Formulário de Configuração -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    <i class="fas fa-sliders-h mr-2 text-purple-600"></i>
                    <?= $isAttendant ? 'Credenciais (Herdadas do Supervisor)' : 'Credenciais do Azure AD' ?>
                </h2>
                
                <form id="config-form" class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                            Application (client) ID
                        </label>
                        <input 
                            type="text" 
                            id="client-id" 
                            value="<?= htmlspecialchars($config['teams_client_id'] ?? '') ?>"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-white <?= $isAttendant ? 'opacity-60 cursor-not-allowed' : '' ?>"
                            placeholder="00000000-0000-0000-0000-000000000000"
                            <?= $isAttendant ? 'readonly disabled' : '' ?>
                        >
                        <?php if ($isAttendant): ?>
                        <p class="text-xs mt-1 text-gray-500 dark:text-gray-400">
                            <i class="fas fa-lock mr-1"></i>
                            Este campo é gerenciado pelo seu supervisor
                        </p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!$isAttendant): ?>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                            Client Secret (Value)
                        </label>
                        <input 
                            type="password" 
                            id="client-secret" 
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                            placeholder="••••••••••••••••••••••••••••••••"
                        >
                        <p class="text-xs mt-1 text-gray-500 dark:text-gray-400">Por segurança, não mostramos o secret salvo. Deixe em branco para manter o atual.</p>
                    </div>
                    <?php endif; ?>
                    
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                            Directory (tenant) ID
                        </label>
                        <input 
                            type="text" 
                            id="tenant-id" 
                            value="<?= htmlspecialchars($config['teams_tenant_id'] ?? '') ?>"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-white <?= $isAttendant ? 'opacity-60 cursor-not-allowed' : '' ?>"
                            placeholder="00000000-0000-0000-0000-000000000000"
                            <?= $isAttendant ? 'readonly disabled' : '' ?>
                        >
                        <?php if ($isAttendant): ?>
                        <p class="text-xs mt-1 text-gray-500 dark:text-gray-400">
                            <i class="fas fa-lock mr-1"></i>
                            Este campo é gerenciado pelo seu supervisor
                        </p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            <i class="fas fa-link mr-2 text-gray-500 dark:text-gray-400"></i>
                            <strong>Redirect URI:</strong> 
                            <code class="bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded text-xs text-gray-900 dark:text-gray-100"><?= $redirectUri ?></code>
                        </p>
                        <?php if (!$isAttendant): ?>
                        <p class="text-xs mt-2 text-gray-500 dark:text-gray-400">Configure esta URL no Azure Portal em Authentication → Redirect URIs</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex gap-3">
                        <?php if (!$isAttendant): ?>
                        <button 
                            type="submit" 
                            class="px-6 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-lg hover:from-purple-700 hover:to-indigo-700 transition-all"
                        >
                            <i class="fas fa-save mr-2"></i>Salvar Configuração
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($isConfigured && !$isAuthenticated): ?>
                        <button 
                            type="button"
                            onclick="adminConsent()"
                            class="px-6 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-all"
                            title="Use este botão se estiver pedindo aprovação de administrador"
                        >
                            <i class="fas fa-shield-alt mr-2"></i>Consentimento de Admin
                        </button>
                        
                        <button 
                            type="button"
                            onclick="authenticateWithMicrosoft()"
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all"
                        >
                            <i class="fab fa-microsoft mr-2"></i>Conectar com Microsoft
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($isAuthenticated): ?>
                        <button 
                            type="button"
                            onclick="disconnectTeams()"
                            class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-all"
                        >
                            <i class="fas fa-sign-out-alt mr-2"></i>Desconectar
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <?php if ($isAuthenticated): ?>
            <!-- Informações da Conta Conectada -->
            <div class="bg-green-50 border border-green-200 rounded-lg p-6" style="background-color: #f0fdf4 !important;">
                <h3 class="text-lg font-semibold mb-3" style="color: #14532d !important;">
                    <i class="fas fa-check-circle mr-2"></i>
                    Conta Conectada
                </h3>
                <div id="user-info" class="text-sm" style="color: #166534 !important;">
                    Carregando informações...
                </div>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<script>
// Salvar configuração
document.getElementById('config-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const clientId = document.getElementById('client-id').value.trim();
    const clientSecret = document.getElementById('client-secret').value.trim();
    const tenantId = document.getElementById('tenant-id').value.trim();
    
    if (!clientId || !tenantId) {
        alert('Por favor, preencha Client ID e Tenant ID');
        return;
    }
    
    try {
        const response = await fetch('/api/teams_graph_config.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_credentials',
                client_id: clientId,
                client_secret: clientSecret,
                tenant_id: tenantId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Configuração salva com sucesso!');
            location.reload();
        } else {
            alert('❌ Erro: ' + (data.error || 'Erro desconhecido'));
        }
    } catch (error) {
        alert('❌ Erro ao salvar: ' + error.message);
    }
});

// Autenticar com Microsoft
function authenticateWithMicrosoft() {
    window.location.href = '/api/teams_graph_config.php?action=authorize';
}

// Consentimento de Administrador
function adminConsent() {
    if (confirm('Você será redirecionado para conceder consentimento de administrador. Certifique-se de estar logado como administrador do Azure AD.')) {
        window.location.href = '/api/teams_graph_config.php?action=admin_consent';
    }
}

// Desconectar
async function disconnectTeams() {
    if (!confirm('Deseja desconectar sua conta do Microsoft Teams?')) {
        return;
    }
    
    try {
        const response = await fetch('/api/teams_graph_config.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'disconnect' })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Desconectado com sucesso!');
            location.reload();
        } else {
            alert('❌ Erro: ' + (data.error || 'Erro desconhecido'));
        }
    } catch (error) {
        alert('❌ Erro ao desconectar: ' + error.message);
    }
}

// Carregar informações do usuário conectado
<?php if ($isAuthenticated): ?>
(async function() {
    try {
        const response = await fetch('/api/teams_graph_config.php?action=get_user_info');
        const data = await response.json();
        
        if (data.success && data.user) {
            document.getElementById('user-info').innerHTML = `
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-green-200 flex items-center justify-center">
                        <i class="fas fa-user text-green-700 text-xl"></i>
                    </div>
                    <div>
                        <div class="font-semibold">${data.user.displayName || 'Usuário'}</div>
                        <div class="text-xs">${data.user.mail || data.user.userPrincipalName || ''}</div>
                    </div>
                </div>
            `;
        }
    } catch (error) {
        console.error('Erro ao carregar informações do usuário:', error);
    }
})();
<?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>
