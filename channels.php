<?php
/**
 * Página de Gerenciamento de Canais de Comunicação
 * Permite configurar e gerenciar múltiplos canais (Telegram, Facebook, etc)
 */

$page_title = 'Canais de Comunicação';
require_once 'includes/header_spa.php';

$user_id = $_SESSION['user_id'];

// Buscar canais ativos do usuário
$stmt = $pdo->prepare("
    SELECT c.*, 
           ct.bot_name as telegram_name,
           cf.page_name as facebook_name,
           ci.username as instagram_name,
           ce.email as email_address
    FROM channels c
    LEFT JOIN channel_telegram ct ON ct.channel_id = c.id
    LEFT JOIN channel_facebook cf ON cf.channel_id = c.id
    LEFT JOIN channel_instagram ci ON ci.channel_id = c.id
    LEFT JOIN channel_email ce ON ce.channel_id = c.id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$user_id]);
$userChannels = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organizar canais por tipo
$channelsByType = [];
foreach ($userChannels as $channel) {
    $channelsByType[$channel['channel_type']] = $channel;
}

// Verificar status dos canais
$telegramActive = isset($channelsByType['telegram']) && $channelsByType['telegram']['status'] === 'active';
$facebookActive = isset($channelsByType['facebook']) && $channelsByType['facebook']['status'] === 'active';
$instagramActive = isset($channelsByType['instagram']) && $channelsByType['instagram']['status'] === 'active';
$emailActive = isset($channelsByType['email']) && $channelsByType['email']['status'] === 'active';

// Verificar status do Microsoft Teams (Graph API)
// Teams é configurado diretamente na tabela users, não em channels
$stmt = $pdo->prepare("
    SELECT 
        teams_client_id,
        teams_access_token,
        teams_token_expires_at
    FROM users 
    WHERE id = ?
");
$stmt->execute([$user_id]);
$teamsConfig = $stmt->fetch(PDO::FETCH_ASSOC);

// Teams está ativo se tem credenciais E token válido
$teamsActive = false;
if ($teamsConfig && !empty($teamsConfig['teams_client_id']) && !empty($teamsConfig['teams_access_token'])) {
    // Verificar se o token não expirou
    if ($teamsConfig['teams_token_expires_at']) {
        $teamsActive = strtotime($teamsConfig['teams_token_expires_at']) > time();
    } else {
        $teamsActive = true; // Se não tem data de expiração, considerar ativo
    }
}

require_once 'includes/header_spa.php';
?>

<div class="main-content">
    <!-- Header -->
    <div class="bg-white border-b border-gray-200 px-6 py-5">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 tracking-tight">Canais de Comunicação</h1>
                <p class="text-sm text-gray-600 mt-1">Configure e gerencie seus canais de atendimento</p>
            </div>
            <div class="flex gap-3">
                <button onclick="refreshChannels()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors duration-150 text-sm font-medium text-gray-700">
                    <i class="fas fa-sync-alt mr-2 text-xs"></i>Atualizar
                </button>
            </div>
        </div>
    </div>
    
    <!-- Grid de Canais -->
    <div class="p-6 bg-gray-50">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            
            <!-- WhatsApp (já existe) -->
            <div class="channel-card active">
                <div class="channel-icon" style="background: #25d366;">
                    <i class="fab fa-whatsapp"></i>
                </div>
                <h3>WhatsApp</h3>
                <p>Evolution API</p>
                <span class="status-badge active">ATIVO</span>
                <button onclick="window.location.href='my_instance.php'" class="btn-configure">
                    <i class="fas fa-cog mr-2"></i>Configurar
                </button>
            </div>
            
            <!-- Telegram -->
            <?php
            $telegramActive = isset($channelsByType['telegram']) && $channelsByType['telegram']['status'] === 'active';
            $telegramName = $channelsByType['telegram']['telegram_name'] ?? '';
            ?>
            <div class="channel-card <?= $telegramActive ? 'active' : '' ?>">
                <div class="channel-icon" style="background: #0088cc;">
                    <i class="fab fa-telegram"></i>
                </div>
                <h3>Telegram</h3>
                <p><?= $telegramActive ? $telegramName : 'Bot do Telegram' ?></p>
                <span class="status-badge <?= $telegramActive ? 'active' : 'inactive' ?>">
                    <?= $telegramActive ? 'ATIVO' : 'INATIVO' ?>
                </span>
                <button onclick="openChannelModal('telegram')" class="btn-configure">
                    <i class="fas fa-<?= $telegramActive ? 'cog' : 'plus' ?> mr-2"></i>
                    <?= $telegramActive ? 'Configurar' : 'Conectar' ?>
                </button>
            </div>
            
            <!-- Facebook Messenger -->
            <?php
            $facebookActive = isset($channelsByType['facebook']) && $channelsByType['facebook']['status'] === 'active';
            $facebookName = $channelsByType['facebook']['facebook_name'] ?? '';
            ?>
            <div class="channel-card <?= $facebookActive ? 'active' : '' ?>">
                <div class="channel-icon" style="background: #0084ff;">
                    <i class="fab fa-facebook-messenger"></i>
                </div>
                <h3>Facebook Messenger</h3>
                <p><?= $facebookActive ? $facebookName : 'Conecte sua página do Facebook' ?></p>
                <span class="status-badge <?= $facebookActive ? 'active' : 'inactive' ?>">
                    <?= $facebookActive ? 'ATIVO' : 'INATIVO' ?>
                </span>
                <button onclick="openChannelModal('facebook')" class="btn-configure">
                    <i class="fas fa-<?= $facebookActive ? 'cog' : 'plus' ?> mr-2"></i>
                    <?= $facebookActive ? 'Configurar' : 'Conectar' ?>
                </button>
            </div>
            
            <!-- Instagram DM -->
            <div class="channel-card">
                <div class="channel-icon" style="background: #e1306c;">
                    <i class="fab fa-instagram"></i>
                </div>
                <h3>Instagram DM</h3>
                <p>Mensagens diretas do Instagram</p>
                <span class="status-badge <?= $instagramActive ? 'active' : 'inactive' ?>">
                    <?= $instagramActive ? 'ATIVO' : 'INATIVO' ?>
                </span>
                <button onclick="openChannelModal('instagram')" class="btn-configure">
                    <i class="fas fa-<?= $instagramActive ? 'cog' : 'plus' ?> mr-2"></i>
                    <?= $instagramActive ? 'Configurar' : 'Conectar' ?>
                </button>
            </div>
            
            <!-- Email -->
            <div class="channel-card">
                <div class="channel-icon" style="background: #ea4335;">
                    <i class="fas fa-envelope"></i>
                </div>
                <h3>Email</h3>
                <p>Inbox de email completo</p>
                <span class="status-badge <?= $emailActive ? 'active' : 'inactive' ?>">
                    <?= $emailActive ? 'ATIVO' : 'INATIVO' ?>
                </span>
                <button onclick="openChannelModal('email')" class="btn-configure">
                    <i class="fas fa-<?= $emailActive ? 'cog' : 'plus' ?> mr-2"></i>
                    <?= $emailActive ? 'Configurar' : 'Conectar' ?>
                </button>
            </div>
            
            <!-- Microsoft Teams -->
            <div class="channel-card <?= $teamsActive ? 'active' : '' ?>">
                <div class="channel-icon" style="background: linear-gradient(135deg, #5558AF 0%, #464EB8 100%);">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 2228.833 2073.333" style="width: 36px; height: 36px; fill: white;">
                        <path d="M1554.637 777.5h575.713c54.391 0 98.483 44.092 98.483 98.483v524.398c0 199.901-162.051 361.952-361.952 361.952h-1.711c-199.901.028-361.975-162-362.004-361.901V828.971c.001-28.427 23.045-51.471 51.471-51.471z"/>
                        <circle cx="1943.75" cy="440.583" r="233.25"/>
                        <circle cx="1218.083" cy="336.917" r="336.917"/>
                        <path d="M1667.323 777.5H717.01c-53.743 1.33-96.257 45.931-95.01 99.676v598.105c-7.505 322.519 247.657 590.16 570.167 598.053 322.51-7.893 577.671-275.534 570.167-598.053V877.176c1.245-53.745-41.268-98.346-95.011-99.676z"/>
                        <path opacity=".1" d="M1244 777.5v838.145c-.258 38.435-23.549 72.964-59.09 87.598-11.316 4.787-23.478 7.254-35.765 7.257H667.613c-6.738-17.105-12.958-34.21-18.142-51.833a631.287 631.287 0 0 1-27.472-183.49V877.02c-1.246-53.659 41.198-98.19 94.855-99.52H1244z"/>
                        <path opacity=".2" d="M1192.167 777.5v889.978a91.842 91.842 0 0 1-7.257 35.765c-14.634 35.541-49.163 58.833-87.598 59.09H691.975c-8.812-17.105-17.105-34.21-24.362-51.833-7.257-17.623-12.958-34.21-18.142-51.833a631.282 631.282 0 0 1-27.472-183.49V877.02c-1.246-53.659 41.198-98.19 94.855-99.52h475.313z"/>
                        <path opacity=".2" d="M1192.167 777.5v786.312c-.395 52.223-42.632 94.46-94.855 94.855h-447.84A631.282 631.282 0 0 1 622 1475.177V877.02c-1.246-53.659 41.198-98.19 94.855-99.52h475.312z"/>
                        <path opacity=".2" d="M1140.333 777.5v786.312c-.395 52.223-42.632 94.46-94.855 94.855H649.472A631.282 631.282 0 0 1 622 1475.177V877.02c-1.246-53.659 41.198-98.19 94.855-99.52h423.478z"/>
                        <path opacity=".1" d="M1244 509.522v163.275c-8.812.518-17.105 1.037-25.917 1.037s-17.105-.518-25.917-1.037c-17.496-1.161-34.848-3.937-51.833-8.293a336.92 336.92 0 0 1-233.25-198.003 288.02 288.02 0 0 1-16.587-51.833h258.648c52.305.198 94.657 42.549 94.856 94.854z"/>
                        <path opacity=".2" d="M1192.167 561.355v111.442a284.472 284.472 0 0 1-51.833 8.293c-17.496 1.161-34.848 3.937-51.833 8.293a336.92 336.92 0 0 1-233.25-198.003h242.06c52.305.198 94.657 42.549 94.856 94.975z"/>
                        <path opacity=".2" d="M1192.167 561.355v111.442a284.472 284.472 0 0 1-51.833 8.293c-17.496 1.161-34.848 3.937-51.833 8.293a336.92 336.92 0 0 1-233.25-198.003h242.06c52.305.198 94.657 42.549 94.856 94.975z"/>
                        <path opacity=".2" d="M1140.333 561.355v103.148c-17.496 1.161-34.848 3.937-51.833 8.293a336.92 336.92 0 0 1-233.25-198.003h190.228c52.304.198 94.656 42.55 94.855 94.562z"/>
                        <linearGradient id="a" x1="198.099" x2="942.234" y1="392.261" y2="1681.073" gradientUnits="userSpaceOnUse">
                            <stop offset="0" stop-color="#5a62c3"/>
                            <stop offset=".5" stop-color="#4d55bd"/>
                            <stop offset="1" stop-color="#3940ab"/>
                        </linearGradient>
                        <path fill="url(#a)" d="M95.01 466.5h950.312c52.473 0 95.01 42.538 95.01 95.01v950.312c0 52.473-42.538 95.01-95.01 95.01H95.01c-52.473 0-95.01-42.538-95.01-95.01V561.51c0-52.472 42.538-95.01 95.01-95.01z"/>
                        <path fill="#fff" d="M820.211 828.193H630.241v517.297H509.211V828.193H320.123V727.844h500.088v100.349z"/>
                    </svg>
                </div>
                <h3>Microsoft Teams</h3>
                <p>Chat bidirecional completo</p>
                <span class="status-badge <?= $teamsActive ? 'active' : 'inactive' ?>">
                    <?= $teamsActive ? 'ATIVO' : 'INATIVO' ?>
                </span>
                <button onclick="window.location.href='teams_graph_config.php'" class="btn-configure">
                    <i class="fas fa-cog mr-2"></i>Configurar Graph API
                </button>
            </div>
            
            <!-- Twitter DM -->
            <div class="channel-card coming-soon">
                <div class="channel-icon" style="background: #1da1f2;">
                    <i class="fab fa-twitter"></i>
                </div>
                <h3>Twitter DM</h3>
                <p>Em breve</p>
                <span class="status-badge inactive">EM BREVE</span>
                <button class="btn-configure" disabled>
                    <i class="fas fa-clock mr-2"></i>Em breve
                </button>
            </div>
            
            <!-- Web Widget -->
            <div class="channel-card coming-soon">
                <div class="channel-icon" style="background: #667eea;">
                    <i class="fas fa-comments"></i>
                </div>
                <h3>Chat Widget</h3>
                <p>Em breve</p>
                <span class="status-badge inactive">EM BREVE</span>
                <button class="btn-configure" disabled>
                    <i class="fas fa-clock mr-2"></i>Em breve
                </button>
            </div>
            
        </div>
    </div>
</div>

<!-- Modal Telegram -->
<div id="telegram-modal" class="modal-overlay" style="display: none;">
    <div class="modal-container" style="max-width: 600px;">
        <div class="modal-header">
            <h2><i class="fab fa-telegram mr-2"></i>Configurar Telegram Bot</h2>
            <button onclick="closeModal('telegram-modal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Bot Token</label>
                <input type="text" id="telegram-bot-token" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11">
                <p class="text-xs text-gray-500 mt-1">
                    Obtenha seu token com o <a href="https://t.me/BotFather" target="_blank" class="text-blue-600 hover:underline">@BotFather</a> no Telegram
                </p>
            </div>
            
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                <h4 class="font-semibold text-blue-900 mb-2"><i class="fas fa-info-circle mr-2"></i>Como criar um bot no Telegram:</h4>
                <ol class="text-sm text-blue-800 space-y-1 ml-4 list-decimal">
                    <li>Abra o Telegram e procure por <strong>@BotFather</strong></li>
                    <li>Envie o comando <code class="bg-blue-100 px-2 py-1 rounded">/newbot</code></li>
                    <li>Escolha um nome para seu bot</li>
                    <li>Escolha um username (deve terminar com "bot")</li>
                    <li>Copie o token fornecido e cole acima</li>
                </ol>
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('telegram-modal')" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                Cancelar
            </button>
            <button onclick="saveTelegramChannel()" class="px-6 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:from-green-700 hover:to-green-800">
                <i class="fas fa-save mr-2"></i>Salvar e Conectar
            </button>
        </div>
    </div>
</div>

<!-- Modal Facebook -->
<div id="facebook-modal" class="modal-overlay" style="display: none;">
    <div class="modal-container" style="max-width: 600px;">
        <div class="modal-header">
            <h2><i class="fab fa-facebook-messenger mr-2"></i>Configurar Facebook Messenger</h2>
            <button onclick="closeModal('facebook-modal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                <h4 class="font-semibold text-yellow-900 mb-2"><i class="fas fa-exclamation-triangle mr-2"></i>Configuração Avançada</h4>
                <p class="text-sm text-yellow-800">
                    A integração com Facebook Messenger requer configuração de um Facebook App. 
                    Entre em contato com o suporte para assistência na configuração.
                </p>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Page ID</label>
                <input type="text" id="facebook-page-id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="123456789012345">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Page Access Token</label>
                <textarea id="facebook-page-token" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="EAAxxxxxxxxxxxxx"></textarea>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">User Access Token</label>
                <textarea id="facebook-user-token" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="EAAxxxxxxxxxxxxx"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('facebook-modal')" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                Cancelar
            </button>
            <button onclick="saveFacebookChannel()" class="px-6 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800">
                <i class="fas fa-save mr-2"></i>Salvar e Conectar
            </button>
        </div>
    </div>
</div>

<!-- Modal Instagram -->
<div id="instagram-modal" class="modal-overlay" style="display: none;">
    <div class="modal-container" style="max-width: 600px;">
        <div class="modal-header">
            <h2><i class="fab fa-instagram mr-2"></i>Configurar Instagram DM</h2>
            <button onclick="closeModal('instagram-modal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-4">
                <h4 class="font-semibold text-purple-900 mb-2"><i class="fas fa-info-circle mr-2"></i>Instagram Graph API</h4>
                <p class="text-sm text-purple-800">
                    A integração com Instagram requer uma conta Business conectada a uma Página do Facebook.
                    Configure um Facebook App e obtenha as credenciais necessárias.
                </p>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Instagram Account ID</label>
                <input type="text" id="instagram-account-id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent" placeholder="17841400000000000">
                <p class="text-xs text-gray-500 mt-1">ID da conta Instagram Business</p>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Page ID (Facebook)</label>
                <input type="text" id="instagram-page-id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent" placeholder="123456789012345">
                <p class="text-xs text-gray-500 mt-1">ID da Página do Facebook conectada ao Instagram</p>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Access Token</label>
                <textarea id="instagram-access-token" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent" placeholder="EAAxxxxxxxxxxxxx"></textarea>
                <p class="text-xs text-gray-500 mt-1">Token de acesso da Página do Facebook</p>
            </div>
            
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                <p class="text-xs text-blue-800">
                    <i class="fas fa-lightbulb mr-1"></i>
                    <strong>Dica:</strong> O webhook será configurado automaticamente em: 
                    <code class="bg-blue-100 px-1 rounded"><?= $_SERVER['HTTP_HOST'] ?>/api/webhooks/instagram.php</code>
                </p>
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('instagram-modal')" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                Cancelar
            </button>
            <button onclick="saveInstagramChannel()" class="px-6 py-2 bg-gradient-to-r from-pink-600 to-purple-600 text-white rounded-lg hover:from-pink-700 hover:to-purple-700">
                <i class="fas fa-save mr-2"></i>Salvar e Conectar
            </button>
        </div>
    </div>
</div>

<!-- Modal Email -->
<div id="email-modal" class="modal-overlay" style="display: none;">
    <div class="modal-container" style="max-width: 700px;">
        <div class="modal-header">
            <h2><i class="fas fa-envelope mr-2"></i>Configurar Email</h2>
            <button onclick="closeModal('email-modal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="info-box info mb-4">
                <h4 class="font-semibold mb-2"><i class="fas fa-info-circle mr-2"></i>Configuração de Email</h4>
                <p class="text-sm">
                    Configure seu email corporativo para receber e enviar mensagens diretamente pelo WATS.
                    Suporta Gmail, Outlook, e qualquer servidor IMAP/SMTP.
                </p>
            </div>
            
            <!-- Método de Autenticação -->
            <div class="mb-4">
                <label class="block text-sm font-semibold mb-2">Método de Autenticação</label>
                <div class="grid grid-cols-2 gap-3">
                    <button type="button" onclick="selectAuthMethod('password')" id="auth-method-password" class="auth-method-btn active">
                        <i class="fas fa-key mr-2"></i>
                        <span>Senha / App Password</span>
                    </button>
                    <button type="button" onclick="selectAuthMethod('oauth')" id="auth-method-oauth" class="auth-method-btn">
                        <i class="fab fa-microsoft mr-2"></i>
                        <span>Microsoft OAuth 2.0</span>
                    </button>
                </div>
            </div>
            
            <!-- Configuração com Senha (padrão) -->
            <div id="password-auth-section">
                <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                    <input type="email" id="email-address" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent" placeholder="seu@email.com">
                </div>
                
                <div class="col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Senha / App Password</label>
                    <input type="password" id="email-password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent" placeholder="••••••••">
                    <p class="text-xs text-gray-500 mt-1">Para Gmail, use uma senha de app</p>
                </div>
            </div>
            
            <div class="border-t border-gray-200 pt-4 mb-4">
                <h4 class="font-semibold text-gray-800 mb-3">Configurações IMAP (Receber)</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Host IMAP</label>
                        <input type="text" id="email-imap-host" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent" placeholder="imap.gmail.com">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Porta IMAP</label>
                        <input type="number" id="email-imap-port" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent" placeholder="993" value="993">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Criptografia IMAP</label>
                        <select id="email-imap-encryption" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                            <option value="ssl">SSL</option>
                            <option value="tls">TLS</option>
                            <option value="none">Nenhuma</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-200 pt-4 mb-4">
                <h4 class="font-semibold text-gray-800 mb-3">Configurações SMTP (Enviar)</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Host SMTP</label>
                        <input type="text" id="email-smtp-host" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent" placeholder="smtp.gmail.com">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Porta SMTP</label>
                        <input type="number" id="email-smtp-port" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent" placeholder="587" value="587">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Criptografia SMTP</label>
                        <select id="email-smtp-encryption" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                            <option value="tls">TLS</option>
                            <option value="ssl">SSL</option>
                            <option value="none">Nenhuma</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Nome do Remetente (opcional)</label>
                <input type="text" id="email-from-name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent" placeholder="Suporte WATS">
            </div>
            
            <div class="info-box info">
                <p class="text-xs">
                    <i class="fas fa-lightbulb mr-1"></i>
                    <strong>Configurações comuns:</strong><br>
                    <strong>Gmail:</strong> imap.gmail.com:993 (SSL) / smtp.gmail.com:587 (TLS)<br>
                    <strong>Outlook:</strong> outlook.office365.com:993 (SSL) / smtp.office365.com:587 (TLS)
                </p>
            </div>
            </div>
            
            <!-- Configuração com OAuth 2.0 (Microsoft) -->
            <div id="oauth-auth-section" style="display: none;">
                <div class="info-box warning mb-4">
                    <p class="text-sm">
                        <i class="fas fa-shield-alt mr-2"></i>
                        <strong>Autenticação OAuth 2.0 com Microsoft</strong><br>
                        Mais seguro que senha. Requer configuração de aplicativo no Azure AD.
                    </p>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Email Microsoft</label>
                    <input type="email" id="oauth-email" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="usuario@empresa.com">
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Client ID</label>
                        <input type="text" id="oauth-client-id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Azure App Client ID">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Client Secret</label>
                        <input type="password" id="oauth-client-secret" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Azure App Secret">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Tenant ID</label>
                    <input type="text" id="oauth-tenant-id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Azure Tenant ID">
                </div>
                
                <div class="mb-4">
                    <button type="button" onclick="authenticateWithMicrosoft()" class="w-full px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center justify-center gap-2">
                        <i class="fab fa-microsoft"></i>
                        <span>Autenticar com Microsoft</span>
                    </button>
                </div>
                
                <div id="oauth-status" class="hidden info-box info">
                    <p class="text-sm">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span id="oauth-status-text">Conectado como: <strong id="oauth-connected-email"></strong></span>
                    </p>
                </div>
                
                <div class="info-box info">
                    <p class="text-xs">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>Como configurar:</strong><br>
                        1. Acesse <a href="https://portal.azure.com" target="_blank" class="underline">Azure Portal</a><br>
                        2. Registre um novo aplicativo em "App registrations"<br>
                        3. Configure permissões: Mail.Read, Mail.Send, offline_access<br>
                        4. Adicione Redirect URI: <code class="bg-gray-100 px-1 rounded"><?= $_SERVER['HTTP_HOST'] ?>/api/oauth/microsoft/callback.php</code>
                    </p>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('email-modal')" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                Cancelar
            </button>
            <button id="disconnect-email-btn" onclick="disconnectEmailChannel()" class="px-6 py-2 bg-gradient-to-r from-gray-600 to-gray-700 text-white rounded-lg hover:from-gray-700 hover:to-gray-800" style="display: none;">
                <i class="fas fa-unlink mr-2"></i>Desconectar
            </button>
            <button onclick="saveEmailChannel()" class="px-6 py-2 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg hover:from-red-700 hover:to-red-800">
                <i class="fas fa-save mr-2"></i>Salvar e Conectar
            </button>
        </div>
    </div>
</div>

<!-- Modal Microsoft Teams -->
<div id="teams-modal" class="modal-overlay" style="display: none;">
    <div class="modal-container" style="max-width: 600px;">
        <div class="modal-header">
            <h2><i class="fas fa-users mr-2"></i>Configurar Microsoft Teams</h2>
            <button onclick="closeModal('teams-modal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                <h4 class="font-semibold text-blue-900 mb-2"><i class="fas fa-info-circle mr-2"></i>Como configurar:</h4>
                <ol class="text-sm text-blue-800 space-y-1 ml-4 list-decimal">
                    <li>Abra o Microsoft Teams</li>
                    <li>Vá até o canal onde deseja receber mensagens</li>
                    <li>Clique nos <strong>três pontos (...)</strong> ao lado do nome do canal</li>
                    <li>Selecione <strong>Conectores</strong> (ou <strong>Connectors</strong>)</li>
                    <li>Procure por <strong>Incoming Webhook</strong></li>
                    <li>Clique em <strong>Configurar</strong></li>
                    <li>Dê um nome (ex: "Sistema de Atendimento")</li>
                    <li>Clique em <strong>Criar</strong></li>
                    <li>Copie a URL do webhook e cole abaixo</li>
                </ol>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Nome do Canal</label>
                <input type="text" id="teams-channel-name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="Ex: Suporte Técnico">
                <p class="text-xs text-gray-500 mt-1">Nome descritivo para identificar este canal</p>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">URL do Webhook</label>
                <textarea id="teams-webhook-url" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="https://outlook.office.com/webhook/..."></textarea>
                <p class="text-xs text-gray-500 mt-1">Cole a URL do webhook gerada no Teams</p>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Nome do Time (opcional)</label>
                <input type="text" id="teams-team-name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="Ex: Equipe de Suporte">
                <p class="text-xs text-gray-500 mt-1">Nome da equipe no Teams (opcional)</p>
            </div>
            
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                <p class="text-xs text-yellow-800">
                    <i class="fas fa-lightbulb mr-1"></i>
                    <strong>Dica:</strong> Você pode configurar múltiplos canais do Teams. Cada canal precisa de seu próprio webhook.
                </p>
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('teams-modal')" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                Cancelar
            </button>
            <button onclick="testTeamsConnection()" class="px-4 py-2 border border-purple-600 text-purple-600 rounded-lg hover:bg-purple-50">
                <i class="fas fa-vial mr-2"></i>Testar Conexão
            </button>
            <button onclick="saveTeamsChannel()" class="px-6 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-lg hover:from-purple-700 hover:to-indigo-700">
                <i class="fas fa-save mr-2"></i>Salvar e Conectar
            </button>
        </div>
    </div>
</div>

<style>
.channel-card {
    background: white;
    border: 1px solid rgba(0, 0, 0, 0.08);
    border-radius: 12px;
    padding: 24px;
    text-align: center;
    transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.channel-card:hover {
    border-color: rgba(16, 185, 129, 0.3);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.channel-card.active {
    border-color: #10b981;
    background: rgba(16, 185, 129, 0.02);
}

.channel-card.coming-soon {
    opacity: 0.5;
    background: #fafafa;
}

.channel-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    margin: 0 auto 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 28px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
}

.channel-card h3 {
    font-size: 18px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 4px;
    letter-spacing: -0.01em;
}

.channel-card p {
    font-size: 13px;
    color: #6b7280;
    margin-bottom: 12px;
    min-height: 36px;
    line-height: 1.4;
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    margin-bottom: 16px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.active {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.status-badge.inactive {
    background: #f3f4f6;
    color: #6b7280;
    border: 1px solid #e5e7eb;
}

.btn-configure {
    width: 100%;
    padding: 10px 20px;
    background: #10b981;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: background 0.15s;
    box-shadow: 0 1px 3px rgba(16, 185, 129, 0.2);
}

.btn-configure:hover:not(:disabled) {
    background: #059669;
}

.btn-configure:disabled {
    opacity: 0.4;
    cursor: not-allowed;
    background: #9ca3af;
    box-shadow: none;
}

.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-container {
    background: white;
    border-radius: 12px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
    border: 1px solid rgba(0, 0, 0, 0.08);
}

.modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    font-size: 20px;
    font-weight: 700;
    color: #1f2937;
}

.modal-close {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: none;
    background: #f3f4f6;
    color: #6b7280;
    font-size: 24px;
    cursor: pointer;
    transition: all 0.2s;
}

.modal-close:hover {
    background: #e5e7eb;
    color: #1f2937;
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    padding: 20px 24px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

/* Dark Mode Styles */
:root[data-theme="dark"] .channel-card {
    background: #1f2937;
    border-color: rgba(255, 255, 255, 0.1);
}

:root[data-theme="dark"] .channel-card:hover {
    border-color: rgba(16, 185, 129, 0.5);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

:root[data-theme="dark"] .channel-card.active {
    border-color: #10b981;
    background: rgba(16, 185, 129, 0.1);
}

:root[data-theme="dark"] .channel-card.coming-soon {
    background: #111827;
    opacity: 0.6;
}

:root[data-theme="dark"] .channel-card h3 {
    color: #f9fafb;
}

:root[data-theme="dark"] .channel-card p {
    color: #9ca3af;
}

:root[data-theme="dark"] .status-badge.active {
    background: rgba(16, 185, 129, 0.2);
    color: #6ee7b7;
    border-color: rgba(16, 185, 129, 0.3);
}

:root[data-theme="dark"] .status-badge.inactive {
    background: rgba(107, 114, 128, 0.2);
    color: #9ca3af;
    border-color: rgba(107, 114, 128, 0.3);
}

:root[data-theme="dark"] .modal-container {
    background: #1f2937;
    border-color: rgba(255, 255, 255, 0.1);
}

:root[data-theme="dark"] .modal-header {
    border-bottom-color: rgba(255, 255, 255, 0.1);
}

:root[data-theme="dark"] .modal-header h2 {
    color: #f9fafb;
}

:root[data-theme="dark"] .modal-close {
    color: #9ca3af;
}

:root[data-theme="dark"] .modal-close:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #f9fafb;
}

:root[data-theme="dark"] .modal-body label {
    color: #f3f4f6 !important;
}

:root[data-theme="dark"] .modal-body input,
:root[data-theme="dark"] .modal-body textarea {
    background: #111827;
    border-color: rgba(255, 255, 255, 0.1);
    color: #f9fafb;
}

:root[data-theme="dark"] .modal-body input::placeholder,
:root[data-theme="dark"] .modal-body textarea::placeholder {
    color: #6b7280;
}

:root[data-theme="dark"] .modal-body input:focus,
:root[data-theme="dark"] .modal-body textarea:focus {
    border-color: #10b981;
    background: #1f2937;
}

:root[data-theme="dark"] .modal-footer {
    border-top-color: rgba(255, 255, 255, 0.1);
}

:root[data-theme="dark"] .bg-blue-50 {
    background: rgba(59, 130, 246, 0.1) !important;
    border-color: rgba(59, 130, 246, 0.2) !important;
}

:root[data-theme="dark"] .bg-blue-50 h4,
:root[data-theme="dark"] .bg-blue-50 ol,
:root[data-theme="dark"] .bg-blue-50 p {
    color: #93c5fd !important;
}

:root[data-theme="dark"] .bg-yellow-50 {
    background: rgba(245, 158, 11, 0.1) !important;
    border-color: rgba(245, 158, 11, 0.2) !important;
}

:root[data-theme="dark"] .bg-yellow-50 h4,
:root[data-theme="dark"] .bg-yellow-50 p {
    color: #fcd34d !important;
}

:root[data-theme="dark"] code {
    background: rgba(255, 255, 255, 0.1);
    color: #93c5fd;
}
</style>

<script>
function openChannelModal(type) {
    const modal = document.getElementById(type + '-modal');
    modal.style.display = 'flex';
    
    // Se for o modal de email, verificar se já existe configuração
    if (type === 'email') {
        const emailActive = <?= $emailActive ? 'true' : 'false' ?>;
        const disconnectBtn = document.getElementById('disconnect-email-btn');
        
        if (emailActive && disconnectBtn) {
            disconnectBtn.style.display = 'inline-block';
        } else if (disconnectBtn) {
            disconnectBtn.style.display = 'none';
        }
    }
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function saveTelegramChannel() {
    const botToken = document.getElementById('telegram-bot-token').value.trim();
    
    if (!botToken) {
        showNotification('Por favor, insira o Bot Token', 'error');
        return;
    }
    
    showNotification('Conectando ao Telegram...', 'info');
    
    fetch('/api/channels/telegram/save.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            bot_token: botToken
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Canal Telegram configurado com sucesso!', 'success');
            closeModal('telegram-modal');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.error || 'Erro ao configurar canal', 'error');
        }
    })
    .catch(error => {
        showNotification('Erro ao conectar: ' + error.message, 'error');
    });
}

function saveFacebookChannel() {
    const pageId = document.getElementById('facebook-page-id').value.trim();
    const pageToken = document.getElementById('facebook-page-token').value.trim();
    const userToken = document.getElementById('facebook-user-token').value.trim();
    
    if (!pageId || !pageToken || !userToken) {
        showNotification('Por favor, preencha todos os campos', 'error');
        return;
    }
    
    showNotification('Conectando ao Facebook...', 'info');
    
    fetch('/api/channels/facebook/save.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            page_id: pageId,
            page_access_token: pageToken,
            user_access_token: userToken
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Canal Facebook configurado com sucesso!', 'success');
            closeModal('facebook-modal');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.error || 'Erro ao configurar canal', 'error');
        }
    })
    .catch(error => {
        showNotification('Erro ao conectar: ' + error.message, 'error');
    });
}

function saveInstagramChannel() {
    const accountId = document.getElementById('instagram-account-id').value.trim();
    const pageId = document.getElementById('instagram-page-id').value.trim();
    const accessToken = document.getElementById('instagram-access-token').value.trim();
    
    if (!accountId || !accessToken) {
        showNotification('Por favor, preencha Instagram Account ID e Access Token', 'error');
        return;
    }
    
    showNotification('Conectando ao Instagram...', 'info');
    
    fetch('/api/channels/instagram/save.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            instagram_account_id: accountId,
            page_id: pageId,
            access_token: accessToken
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Canal Instagram configurado com sucesso!', 'success');
            closeModal('instagram-modal');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.error || 'Erro ao configurar canal', 'error');
        }
    })
    .catch(error => {
        showNotification('Erro ao conectar: ' + error.message, 'error');
    });
}

// Alternar método de autenticação de email
let currentAuthMethod = 'password';

function selectAuthMethod(method) {
    currentAuthMethod = method;
    
    // Atualizar botões
    document.getElementById('auth-method-password').classList.toggle('active', method === 'password');
    document.getElementById('auth-method-oauth').classList.toggle('active', method === 'oauth');
    
    // Mostrar/ocultar seções
    document.getElementById('password-auth-section').style.display = method === 'password' ? 'block' : 'none';
    document.getElementById('oauth-auth-section').style.display = method === 'oauth' ? 'block' : 'none';
}

// Autenticar com Microsoft OAuth 2.0
function authenticateWithMicrosoft() {
    const email = document.getElementById('oauth-email').value.trim();
    const clientId = document.getElementById('oauth-client-id').value.trim();
    const clientSecret = document.getElementById('oauth-client-secret').value.trim();
    const tenantId = document.getElementById('oauth-tenant-id').value.trim();
    
    if (!email || !clientId || !clientSecret || !tenantId) {
        showNotification('Preencha todos os campos OAuth', 'error');
        return;
    }
    
    showNotification('Salvando credenciais...', 'info');
    
    // Salvar credenciais na sessão PHP primeiro
    fetch('/api/oauth/microsoft/save-credentials.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            email: email,
            client_id: clientId,
            client_secret: clientSecret,
            tenant_id: tenantId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.error || 'Erro ao salvar credenciais');
        }
        
        // Construir URL de autorização Microsoft
        const redirectUri = encodeURIComponent(window.location.origin + '/api/oauth/microsoft/callback.php');
        const scope = encodeURIComponent('https://graph.microsoft.com/Mail.Read https://graph.microsoft.com/Mail.Send offline_access');
        const state = encodeURIComponent(btoa(JSON.stringify({ email, timestamp: Date.now() })));
        
        const authUrl = `https://login.microsoftonline.com/${tenantId}/oauth2/v2.0/authorize?` +
            `client_id=${clientId}&` +
            `response_type=code&` +
            `redirect_uri=${redirectUri}&` +
            `scope=${scope}&` +
            `state=${state}&` +
            `response_mode=query`;
        
        // Abrir popup de autenticação
        const width = 600;
        const height = 700;
        const left = (screen.width - width) / 2;
        const top = (screen.height - height) / 2;
        
        const popup = window.open(
            authUrl,
            'Microsoft OAuth',
            `width=${width},height=${height},left=${left},top=${top},toolbar=no,menubar=no`
        );
        
        // Monitorar callback
        const messageHandler = function(event) {
            if (event.data.type === 'oauth_success') {
                if (popup && !popup.closed) popup.close();
                document.getElementById('oauth-status').classList.remove('hidden');
                document.getElementById('oauth-connected-email').textContent = email;
                showNotification('Autenticação Microsoft concluída!', 'success');
                window.removeEventListener('message', messageHandler);
            } else if (event.data.type === 'oauth_error') {
                if (popup && !popup.closed) popup.close();
                showNotification('Erro na autenticação: ' + event.data.error, 'error');
                window.removeEventListener('message', messageHandler);
            }
        };
        
        window.addEventListener('message', messageHandler);
    })
    .catch(error => {
        showNotification('Erro: ' + error.message, 'error');
    });
}

function saveEmailChannel() {
    if (currentAuthMethod === 'oauth') {
        // Salvar com OAuth
        const email = document.getElementById('oauth-email').value.trim();
        const clientId = document.getElementById('oauth-client-id').value.trim();
        const clientSecret = document.getElementById('oauth-client-secret').value.trim();
        const tenantId = document.getElementById('oauth-tenant-id').value.trim();
        
        if (!email || !clientId || !clientSecret || !tenantId) {
            showNotification('Preencha todos os campos OAuth', 'error');
            return;
        }
        
        showNotification('Salvando configuração OAuth...', 'info');
        
        fetch('/api/channels/email/save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                auth_method: 'oauth',
                email: email,
                oauth_client_id: clientId,
                oauth_client_secret: clientSecret,
                oauth_tenant_id: tenantId
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Erro HTTP: ' + response.status);
            }
            return response.text();
        })
        .then(text => {
            console.log('Resposta do servidor:', text);
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    showNotification('Canal Email (OAuth) configurado!', 'success');
                    closeModal('email-modal');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(data.error || 'Erro ao configurar canal', 'error');
                }
            } catch (e) {
                console.error('Erro ao parsear JSON:', e);
                console.error('Resposta recebida:', text);
                showNotification('Erro: Resposta inválida do servidor', 'error');
            }
        })
        .catch(error => {
            console.error('Erro na requisição:', error);
            showNotification('Erro: ' + error.message, 'error');
        });
        
    } else {
        // Salvar com senha (método original)
        const email = document.getElementById('email-address').value.trim();
        const password = document.getElementById('email-password').value.trim();
        const imapHost = document.getElementById('email-imap-host').value.trim();
        const imapPort = document.getElementById('email-imap-port').value.trim();
        const imapEncryption = document.getElementById('email-imap-encryption').value;
        const smtpHost = document.getElementById('email-smtp-host').value.trim();
        const smtpPort = document.getElementById('email-smtp-port').value.trim();
        const smtpEncryption = document.getElementById('email-smtp-encryption').value;
        const fromName = document.getElementById('email-from-name').value.trim();
        
        if (!email || !password || !imapHost || !smtpHost) {
            showNotification('Por favor, preencha email, senha, host IMAP e host SMTP', 'error');
            return;
        }
        
        showNotification('Conectando ao servidor de email...', 'info');
        
        fetch('/api/channels/email/save.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                email: email,
                password: password,
                imap_host: imapHost,
                imap_port: imapPort,
                imap_encryption: imapEncryption,
                smtp_host: smtpHost,
                smtp_port: smtpPort,
                smtp_encryption: smtpEncryption,
                from_name: fromName
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Canal Email configurado com sucesso!', 'success');
                closeModal('email-modal');
                setTimeout(() => location.reload(), 1500);
            } else {
                showNotification(data.error || 'Erro ao configurar canal', 'error');
            }
        })
        .catch(error => {
            showNotification('Erro ao conectar: ' + error.message, 'error');
        });
    }
}

function disconnectEmailChannel() {
    if (!confirm('Tem certeza que deseja desconectar o canal de Email? Todas as configurações serão removidas.')) {
        return;
    }
    
    showNotification('Desconectando canal de Email...', 'info');
    
    fetch('/api/channels/email/disconnect.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Canal Email desconectado com sucesso!', 'success');
            closeModal('email-modal');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.error || 'Erro ao desconectar canal', 'error');
        }
    })
    .catch(error => {
        showNotification('Erro ao desconectar: ' + error.message, 'error');
    });
}

function testTeamsConnection() {
    const webhookUrl = document.getElementById('teams-webhook-url').value.trim();
    
    if (!webhookUrl) {
        showNotification('Por favor, insira a URL do webhook', 'error');
        return;
    }
    
    if (!webhookUrl.startsWith('https://')) {
        showNotification('A URL do webhook deve começar com https://', 'error');
        return;
    }
    
    showNotification('Testando conexão com o Teams...', 'info');
    
    // Enviar teste direto para o webhook do Teams
    const testPayload = {
        "@type": "MessageCard",
        "@context": "https://schema.org/extensions",
        "summary": "Teste de Conexão",
        "themeColor": "28A745",
        "title": "✅ Teste de Conexão",
        "text": "Conexão com o sistema WATS estabelecida com sucesso! Se você está vendo esta mensagem, o webhook está funcionando corretamente."
    };
    
    fetch(webhookUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(testPayload)
    })
    .then(response => {
        if (response.ok || response.status === 200) {
            showNotification('✅ Conexão testada com sucesso! Verifique o canal no Teams.', 'success');
        } else {
            return response.text().then(text => {
                throw new Error(`Erro HTTP ${response.status}: ${text}`);
            });
        }
    })
    .catch(error => {
        console.error('Erro ao testar webhook:', error);
        showNotification('Erro ao testar: Verifique se a URL do webhook está correta', 'error');
    });
}

function saveTeamsChannel() {
    const channelName = document.getElementById('teams-channel-name').value.trim();
    const webhookUrl = document.getElementById('teams-webhook-url').value.trim();
    const teamName = document.getElementById('teams-team-name').value.trim();
    
    if (!channelName || !webhookUrl) {
        showNotification('Por favor, preencha o nome do canal e a URL do webhook', 'error');
        return;
    }
    
    if (!webhookUrl.startsWith('https://')) {
        showNotification('A URL do webhook deve começar com https://', 'error');
        return;
    }
    
    showNotification('Salvando canal do Teams...', 'info');
    
    fetch('/api/teams_channels.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'create',
            channel_name: channelName,
            webhook_url: webhookUrl,
            team_name: teamName
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Canal Teams configurado com sucesso!', 'success');
            closeModal('teams-modal');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.error || 'Erro ao configurar canal', 'error');
        }
    })
    .catch(error => {
        showNotification('Erro ao salvar: ' + error.message, 'error');
    });
}

function refreshChannels() {
    location.reload();
}

function showNotification(message, type) {
    // Usar sistema de notificação existente do WATS
    if (typeof showToast === 'function') {
        showToast(message, type);
    } else {
        alert(message);
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
