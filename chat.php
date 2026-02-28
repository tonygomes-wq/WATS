<?php
// Iniciar sessão primeiro
if (!isset($_SESSION)) {
    session_start();
}

$page_title = 'Atendimentos';

// Verificar se é requisição SPA (via parâmetro ou constante)
$isSPA = isset($_GET['spa']) || defined('IS_SPA_REQUEST');

if (!$isSPA) {
    require_once 'includes/header_spa.php';
} else {
    // Para requisições SPA, apenas carregar dependências
    require_once 'config/database.php';
    require_once 'includes/functions.php';
    requireLogin();

    // Adicionar indicador visual de modo SPA
    echo '<!-- MODO SPA ATIVO -->';
}

$userId = $_SESSION['user_id'];

// Buscar nome do usuário logado
$userName = $_SESSION['user_name'] ?? '';
if (empty($userName)) {
    $stmtName = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmtName->execute([$userId]);
    $userNameData = $stmtName->fetch(PDO::FETCH_ASSOC);
    $userName = $userNameData['name'] ?? 'Atendente';
}

// Verificar se é supervisor/admin OU atendente para funcionalidades de atendimento
$userType = $_SESSION['user_type'] ?? 'user';
$isAttendant = ($userType === 'attendant');

if ($isAttendant) {
    // Atendente: tem acesso às funcionalidades de atendimento
    $is_supervisor = true;
} else {
    // Usuário normal: verificar se é admin ou supervisor
    $is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
    $is_supervisor_session = isset($_SESSION['is_supervisor']) && $_SESSION['is_supervisor'] == 1;

    // Se não estiver na sessão, buscar do banco
    if (!$is_admin && !$is_supervisor_session) {
        $stmt = $pdo->prepare("SELECT is_admin, is_supervisor FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user_data) {
            $is_admin = ($user_data['is_admin'] == 1);
            $is_supervisor_session = ($user_data['is_supervisor'] == 1);
        }
    }

    // Supervisor tem acesso se for admin OU supervisor
    $is_supervisor = ($is_admin || $is_supervisor_session);
}
?>

<!-- Fotos de Perfil do WhatsApp -->
<link rel="stylesheet" href="/assets/css/profile-pictures.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="/assets/css/chat-modern.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="/assets/css/channel-badges.css?v=<?php echo time(); ?>">

<!-- ⭐ FASE 4: Configuração da API (MVC) - DEVE VIR ANTES DE OUTROS SCRIPTS -->
<script src="/assets/js/api-config.js?v=<?php echo time(); ?>"></script>

<script src="/assets/js/profile-pictures.js"></script>

<!-- Sistema Multi-Canal -->
<script src="/assets/js/chat-multichannel.js?v=<?php echo time(); ?>"></script>

<!-- Dropdown de Canais -->
<script>
    // Toggle do dropdown de canais
    function toggleChannelDropdown() {
        const menu = document.getElementById('channel-dropdown-menu');
        const button = document.getElementById('channel-dropdown-btn');
        
        if (menu.classList.contains('hidden')) {
            // Calcular posição do botão
            const rect = button.getBoundingClientRect();
            
            // Posicionar o menu abaixo do botão
            menu.style.top = (rect.bottom + 8) + 'px';
            menu.style.left = rect.left + 'px';
            
            // Verificar se o menu vai sair da tela pela direita
            const menuWidth = 220; // min-width do menu
            if (rect.left + menuWidth > window.innerWidth) {
                menu.style.left = (window.innerWidth - menuWidth - 20) + 'px';
            }
            
            // Verificar se o menu vai sair da tela por baixo
            const menuMaxHeight = 400;
            if (rect.bottom + menuMaxHeight > window.innerHeight) {
                // Mostrar acima do botão
                menu.style.top = (rect.top - menuMaxHeight - 8) + 'px';
            }
            
            menu.classList.remove('hidden');
        } else {
            menu.classList.add('hidden');
        }
    }

    // Selecionar canal no dropdown
    function selectChannel(channel) {
        // Atualizar itens ativos no dropdown
        document.querySelectorAll('.channel-dropdown-item').forEach(item => {
            item.classList.remove('active');
        });

        const selectedItem = document.querySelector(`.channel-dropdown-item[data-channel="${channel}"]`);
        if (selectedItem) {
            selectedItem.classList.add('active');
        }

        // Atualizar o botão principal
        const channelIcon = document.getElementById('channel-icon');
        const channelLabel = document.getElementById('channel-label');

        const channelData = {
            'all': {
                icon: 'fas fa-globe',
                label: 'Todos os Canais'
            },
            'whatsapp': {
                icon: 'fab fa-whatsapp',
                label: 'WhatsApp'
            },
            'telegram': {
                icon: 'fab fa-telegram',
                label: 'Telegram'
            },
            'facebook': {
                icon: 'fab fa-facebook-messenger',
                label: 'Facebook'
            },
            'instagram': {
                icon: 'fab fa-instagram',
                label: 'Instagram'
            },
            'teams': {
                icon: 'fas fa-users',
                label: 'Microsoft Teams'
            }
        };

        if (channelData[channel]) {
            channelIcon.className = channelData[channel].icon;
            channelLabel.textContent = channelData[channel].label;
        }

        // Fechar o dropdown
        document.getElementById('channel-dropdown-menu').classList.add('hidden');

        // Chamar a função de filtro existente
        filterByChannel(channel);
    }

    // Fechar dropdown ao clicar fora
    document.addEventListener('click', function(event) {
        const dropdown = document.querySelector('.chat-channel-dropdown');
        const menu = document.getElementById('channel-dropdown-menu');

        if (dropdown && menu && !dropdown.contains(event.target)) {
            menu.classList.add('hidden');
        }
    });
    
    // Reposicionar dropdown ao redimensionar janela
    window.addEventListener('resize', function() {
        const menu = document.getElementById('channel-dropdown-menu');
        if (menu && !menu.classList.contains('hidden')) {
            const button = document.getElementById('channel-dropdown-btn');
            const rect = button.getBoundingClientRect();
            menu.style.top = (rect.bottom + 8) + 'px';
            menu.style.left = rect.left + 'px';
        }
    });
    
    // Reposicionar dropdown ao fazer scroll
    window.addEventListener('scroll', function() {
        const menu = document.getElementById('channel-dropdown-menu');
        if (menu && !menu.classList.contains('hidden')) {
            const button = document.getElementById('channel-dropdown-btn');
            const rect = button.getBoundingClientRect();
            menu.style.top = (rect.bottom + 8) + 'px';
            menu.style.left = rect.left + 'px';
        }
    }, true);
</script>

<script>
    // Interceptar erros de imagens do WhatsApp (URLs expiradas) e suprimir do console
    (function() {
        // Lista de domínios do WhatsApp que têm URLs temporárias
        const whatsappDomains = ['pps.whatsapp.net', 'mmg.whatsapp.net', 'web.whatsapp.com', 'whatsapp.net'];

        // Verificar se URL é do WhatsApp
        function isWhatsAppUrl(url) {
            if (!url) return false;
            return whatsappDomains.some(domain => url.includes(domain));
        }

        // Handler global para erros de imagem
        window.addEventListener('error', function(e) {
            if (e.target && e.target.tagName === 'IMG') {
                const src = e.target.src || '';
                // Suprimir erros de URLs do WhatsApp
                if (isWhatsAppUrl(src)) {
                    e.preventDefault();
                    e.stopPropagation();
                    // Esconder a imagem e mostrar fallback
                    e.target.style.display = 'none';
                    const nextEl = e.target.nextElementSibling;
                    if (nextEl) {
                        nextEl.style.display = 'flex';
                    }
                    // Adicionar classe para indicar erro
                    e.target.classList.add('img-load-error');
                    return false;
                }
            }
        }, true);

        // Sobrescrever console.error para filtrar erros de WhatsApp
        const originalConsoleError = console.error;
        console.error = function(...args) {
            const message = args.join(' ');
            if (isWhatsAppUrl(message) || (message.includes('403') && message.includes('whatsapp'))) {
                return; // Suprimir silenciosamente
            }
            originalConsoleError.apply(console, args);
        };

        // Sobrescrever console.warn também
        const originalConsoleWarn = console.warn;
        console.warn = function(...args) {
            const message = args.join(' ');
            if (isWhatsAppUrl(message) || (message.includes('403') && message.includes('whatsapp'))) {
                return;
            }
            originalConsoleWarn.apply(console, args);
        };

        // Interceptar erros de fetch para URLs do WhatsApp
        const originalFetch = window.fetch;
        window.fetch = function(url, options) {
            const urlStr = typeof url === 'string' ? url : (url.url || '');
            if (isWhatsAppUrl(urlStr)) {
                // Para URLs do WhatsApp, retornar silenciosamente em caso de erro
                return originalFetch.apply(this, arguments).catch(err => {
                    // Suprimir erro silenciosamente
                    return new Response(null, {
                        status: 403,
                        statusText: 'Forbidden'
                    });
                });
            }
            return originalFetch.apply(this, arguments);
        };

        // Função para validar URL de imagem antes de usar
        window.isValidProfileUrl = function(url) {
            if (!url) return false;
            // URLs do WhatsApp expiram, verificar se não está vazia
            return url.length > 10 && !url.includes('undefined') && !url.includes('null');
        };

        // Função para verificar se URL é do WhatsApp (exposta globalmente)
        window.isWhatsAppUrl = isWhatsAppUrl;

        // Função para criar avatar com iniciais
        window.createInitialsAvatar = function(name) {
            const initials = name ? name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() : '?';
            return `<span class="initials-avatar">${initials}</span>`;
        };
    })();
</script>

<style>
    /* Estilos complementares - O CSS principal está em chat-modern.css */

    /* Avatar com iniciais (fallback para imagens que não carregam) */
    .initials-avatar {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
        font-weight: 600;
        font-size: 0.875rem;
        color: white;
        background: linear-gradient(135deg, #10B981, #059669);
        border-radius: 50%;
    }

    /* Dark mode helpers para elementos Tailwind */
    :root[data-theme="dark"] .bg-white {
        background-color: #1f2937 !important;
    }

    :root[data-theme="dark"] .text-gray-800 {
        color: #f3f4f6 !important;
    }

    :root[data-theme="dark"] .text-gray-600 {
        color: #d1d5db !important;
    }

    :root[data-theme="dark"] .border-gray-200 {
        border-color: #374151 !important;
    }

    /* Menu dropdown de setores */
    #departments-menu {
        max-height: 300px;
        overflow-y: auto;
    }

    #departments-menu button {
        padding: 0.5rem 1rem;
        text-align: left;
        width: 100%;
        transition: background-color 0.2s;
    }

    #departments-menu button:hover {
        background-color: #f3f4f6;
    }

    :root[data-theme="dark"] #departments-menu {
        background-color: #1f2937;
        border-color: #374151;
    }

    :root[data-theme="dark"] #departments-menu button:hover {
        background-color: #374151;
    }

    /* Correções adicionais de modo escuro */
    :root[data-theme="dark"] label {
        color: #f3f4f6 !important;
    }

    :root[data-theme="dark"] .text-gray-500,
    :root[data-theme="dark"] .text-gray-400 {
        color: #9ca3af !important;
    }

    :root[data-theme="dark"] h3,
    :root[data-theme="dark"] h4,
    :root[data-theme="dark"] .font-medium,
    :root[data-theme="dark"] .font-semibold {
        color: #f3f4f6 !important;
    }

    :root[data-theme="dark"] .fixed .bg-white {
        background-color: #1f2937 !important;
    }

    :root[data-theme="dark"] .fixed .bg-white label,
    :root[data-theme="dark"] .fixed .bg-white h3,
    :root[data-theme="dark"] .fixed .bg-white p,
    :root[data-theme="dark"] .fixed .bg-white span {
        color: #f3f4f6 !important;
    }

    :root[data-theme="dark"] select,
    :root[data-theme="dark"] input[type="text"],
    :root[data-theme="dark"] input[type="email"],
    :root[data-theme="dark"] input[type="number"],
    :root[data-theme="dark"] textarea {
        background-color: #1f2937 !important;
        color: #f3f4f6 !important;
        border-color: #374151 !important;
    }

    :root[data-theme="dark"] select option {
        background-color: #1f2937 !important;
        color: #f3f4f6 !important;
    }

    :root[data-theme="dark"] input::placeholder,
    :root[data-theme="dark"] textarea::placeholder {
        color: #9ca3af !important;
    }

    /* Esconder footer na página de chat */
    footer,
    .footer,
    #footer {
        display: none !important;
    }

    /* Esconder header superior do sistema para chat ocupar tela toda */
    .main-content>.bg-white.border-b {
        display: none !important;
    }

    /* Ajustar container principal para ocupar toda altura */
    .flex-1.overflow-y-auto.bg-gray-50 {
        padding: 0 !important;
        overflow: hidden !important;
        height: 100% !important;
    }

    .chat-page-wrapper {
        height: calc(100vh - 0px) !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .chat-main-container {
        height: 100% !important;
        max-height: 100% !important;
    }

    /* Forçar main-content a não cortar o chat */
    .main-content {
        height: 100vh !important;
        overflow: hidden !important;
        padding: 0 !important;
    }

    /* Indicador de gravação de áudio */
    .recording-dot {
        width: 10px;
        height: 10px;
        background: white;
        border-radius: 50%;
        animation: pulse-recording 1s infinite;
    }

    @keyframes pulse-recording {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.3;
        }
    }

    #record-audio-btn.recording {
        background: #ef4444 !important;
        color: white !important;
        animation: pulse-recording 1s infinite;
    }

    /* Player de áudio estilizado */
    .chat-audio-message audio {
        border-radius: 20px;
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    }

    .chat-audio-message audio::-webkit-media-controls-panel {
        background: transparent;
    }

    /* Estabilizar containers de vídeo/GIF para evitar pulos no layout */
    .chat-message-video-container {
        position: relative !important;
        max-width: 280px !important;
        min-height: 150px !important;
        max-height: 200px !important;
        border-radius: 12px !important;
        overflow: hidden !important;
        background: rgba(0, 0, 0, 0.05) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    .chat-video-preview {
        width: 100% !important;
        height: auto !important;
        max-height: 200px !important;
        object-fit: contain !important;
        display: block !important;
        cursor: pointer !important;
    }

    .video-play-overlay {
        position: absolute !important;
        top: 50% !important;
        left: 50% !important;
        transform: translate(-50%, -50%) !important;
        width: 60px !important;
        height: 60px !important;
        background: rgba(0, 0, 0, 0.7) !important;
        border-radius: 50% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        cursor: pointer !important;
        transition: all 0.3s !important;
        z-index: 10 !important;
    }

    .video-play-overlay:hover {
        background: rgba(0, 0, 0, 0.8) !important;
        transform: translate(-50%, -50%) scale(1.1) !important;
    }

    /* Garantir que vídeos não causem scroll horizontal */
    video {
        max-width: 100% !important;
        height: auto !important;
    }

    /* Prevenir qualquer mudança de layout durante carregamento de mídia */
    .chat-message-bubble {
        min-height: auto !important;
        contain: layout style !important;
    }

    .chat-message-video-container {
        contain: layout style !important;
        will-change: auto !important;
    }

    /* Forçar estabilidade absoluta para todos os elementos de mídia */
    .chat-message-image img,
    .chat-message-video-container video,
    .chat-audio-message audio {
        contain: layout !important;
        max-width: 100% !important;
        height: auto !important;
    }

    /* Prevenir pulos durante carregamento */
    @keyframes stabilizeLayout {
        0% {
            opacity: 0.8;
        }

        100% {
            opacity: 1;
        }
    }

    .chat-message-video-container.loading {
        animation: stabilizeLayout 0.3s ease-out;
    }

    /* Container específico para GIFs estáticos */
    .chat-message-gif-container {
        position: relative !important;
        max-width: 280px !important;
        min-height: 150px !important;
        max-height: 200px !important;
        border-radius: 12px !important;
        overflow: hidden !important;
        background: rgba(0, 0, 0, 0.05) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        contain: layout style !important;
        will-change: auto !important;
    }

    .chat-gif-static {
        width: 100% !important;
        height: auto !important;
        max-height: 200px !important;
        object-fit: contain !important;
        display: block !important;
        cursor: pointer !important;
        contain: layout !important;
        pointer-events: auto !important;
    }

    .gif-indicator {
        position: absolute !important;
        top: 8px !important;
        right: 8px !important;
        background: rgba(0, 0, 0, 0.6) !important;
        color: white !important;
        padding: 4px 8px !important;
        border-radius: 12px !important;
        font-size: 11px !important;
        font-weight: 600 !important;
        pointer-events: none !important;
        z-index: 5 !important;
    }

    /* Forçar que NENHUM GIF tenha animação ou autoplay */
    img[src$=".gif"],
    .chat-gif-static {
        animation: none !important;
        transition: none !important;
    }

    /* BLOQUEIO TOTAL DE GIFS - Override final */
    img[src*=".gif"],
    img[src$=".GIF"],
    .chat-message img[src*="gif"],
    .chat-message img[src*="GIF"],
    .gif-forced-static {
        animation-play-state: paused !important;
        animation: none !important;
        transition: none !important;
        transform: none !important;
        filter: none !important;
        -webkit-animation-play-state: paused !important;
        -moz-animation-play-state: paused !important;
        -o-animation-play-state: paused !important;
    }

    /* Prevenir qualquer hover effect em GIFs */
    img[src*=".gif"]:hover,
    img[src*=".gif"]:active,
    img[src*=".gif"]:focus {
        transform: none !important;
        animation: none !important;
        transition: none !important;
    }
</style>

<div class="chat-page-wrapper">
    <!-- Menu dropdown de setores (posição absoluta) -->
    <div id="departments-menu" class="hidden absolute top-16 right-4 bg-white border border-gray-200 rounded-lg shadow-lg z-50 min-w-[200px]">
        <!-- Preenchido via JavaScript -->
    </div>

    <!-- Modal Encaminhar (Estilo WhatsApp) -->
    <div id="forward-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-hidden flex flex-col">
            <!-- Header -->
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-xl font-bold text-gray-800 dark:text-white">Encaminhar para...</h3>
                <button onclick="closeForwardModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" style="background: none !important; border: none !important; border-radius: 0 !important; box-shadow: none !important; cursor: pointer !important; color: #9ca3af !important; font-size: 20px !important; padding: 4px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; transition: color 0.2s !important;">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <!-- Message Preview -->
            <div id="forward-message-preview" class="p-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-12 h-12 bg-green-100 dark:bg-green-900 rounded flex items-center justify-center">
                        <i class="fas fa-share text-green-600 dark:text-green-400"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Adicione uma mensagem</p>
                        <p id="forward-preview-text" class="text-sm text-gray-700 dark:text-gray-300 truncate"></p>
                    </div>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="text"
                        id="forward-search"
                        placeholder="Pesquisar"
                        class="w-full pl-10 pr-4 py-2 bg-gray-100 dark:bg-gray-700 border-0 rounded-lg text-gray-800 dark:text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
            </div>

            <!-- Contacts List Container -->
            <div class="flex-1 overflow-y-auto">
                <!-- Frequent Contacts Section -->
                <div id="forward-frequent-section" class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 mb-3">Contatos frequentes</h4>
                    <div id="forward-frequent-list" class="flex gap-4 overflow-x-auto pb-2">
                        <!-- Frequent contacts will be rendered here -->
                    </div>
                </div>

                <!-- All Contacts Section -->
                <div class="p-4">
                    <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 mb-3">Recentes</h4>
                    <div id="forward-list" class="space-y-1">
                        <!-- All contacts/conversations will be rendered here -->
                    </div>
                </div>
            </div>

            <!-- Footer with selected count and send button -->
            <div class="p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 flex items-center justify-between">
                <span id="forward-selected-count" class="text-sm text-gray-600 dark:text-gray-400">0 selecionado(s)</span>
                <button type="button"
                    onclick="forwardMessages()"
                    class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-full transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                    id="forward-submit-btn"
                    disabled>
                    <i class="fas fa-paper-plane"></i>
                    <span>Enviar</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Overlay para fechar menu de contexto -->
    <div id="chat-context-overlay" class="hidden fixed inset-0 z-[9998]"></div>

    <!-- Menu de Contexto do Chat (Clique Direito) -->
    <div id="chat-context-menu" class="hidden fixed z-[9999] bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 py-2 min-w-[200px]" role="menu" aria-hidden="true">
        <!-- Reações Rápidas -->
        <div class="flex justify-around px-4 py-2 border-b border-gray-200 dark:border-gray-700">
            <button class="text-2xl hover:scale-125 transition-transform" data-emoji="👍" title="Curtir">👍</button>
            <button class="text-2xl hover:scale-125 transition-transform" data-emoji="❤️" title="Amei">❤️</button>
            <button class="text-2xl hover:scale-125 transition-transform" data-emoji="😂" title="Haha">😂</button>
            <button class="text-2xl hover:scale-125 transition-transform" data-emoji="😮" title="Uau">😮</button>
            <button class="text-2xl hover:scale-125 transition-transform" data-emoji="😢" title="Triste">😢</button>
            <button class="text-2xl hover:scale-125 transition-transform" data-emoji="🙏" title="Amém">🙏</button>
        </div>

        <!-- Opções do Menu -->
        <button class="chat-context-item w-full text-left px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-3 text-gray-700 dark:text-white" data-action="reply">
            <i class="fas fa-reply w-5 text-center"></i>
            <span>Responder</span>
        </button>
        <button class="chat-context-item w-full text-left px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-3 text-gray-700 dark:text-white" data-action="copy">
            <i class="fas fa-copy w-5 text-center"></i>
            <span>Copiar Texto</span>
        </button>
        <button class="chat-context-item w-full text-left px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-3 text-gray-700 dark:text-white" data-action="forward">
            <i class="fas fa-share w-5 text-center"></i>
            <span>Encaminhar</span>
        </button>
        <button class="chat-context-item w-full text-left px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-3 text-gray-700 dark:text-white" data-action="save_media">
            <i class="fas fa-download w-5 text-center"></i>
            <span>Salvar Mídia</span>
        </button>
        <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
        <button class="chat-context-item w-full text-left px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-3 text-red-600" data-action="delete">
            <i class="fas fa-trash w-5 text-center"></i>
            <span>Apagar</span>
        </button>
    </div>

    <!-- Container Principal do Chat -->
    <div class="chat-main-container rounded-lg">
        <!-- Sidebar de Conversas -->
        <div class="chat-sidebar">
            <!-- Header da Sidebar (Vazio - Botões movidos para barra de filtros) -->
            <div class="chat-sidebar-header" style="display: none;">
            </div>

            <!-- Lista de Conversas -->
            <div class="chat-conversations-list" id="conversations-list">
                <!-- Lista de conversas (preenchida via JS) -->
                <div id="conversations-container"></div>

                <!-- Loading e Empty ocultos -->
                <div id="conversations-loading" class="hidden"></div>
                <div id="conversations-empty" class="hidden"></div>
            </div>

            <!-- Contador -->
            <div class="chat-conversations-count">
                <span id="conversations-count-text">Mostrando 0 de 0 chats</span>
            </div>
        </div>

        <!-- Área Principal do Chat -->
        <div class="chat-main-area">
            <!-- Filtros no Topo da Área Principal -->
            <div class="chat-main-filters">
                <!-- Filtros de Status -->
                <div class="chat-filters-bar-horizontal">
                    <button onclick="filterConversations('inbox')" data-filter="inbox" class="chat-filter-btn active">
                        <i class="fas fa-inbox"></i>
                        <span>Inbox</span>
                        <span class="chat-filter-count" id="inbox-count-main">0</span>
                    </button>
                    <?php if ($is_supervisor): ?>
                        <button onclick="filterConversations('my_chats')" data-filter="my_chats" class="chat-filter-btn">
                            <i class="fas fa-headset"></i>
                            <span>Meus</span>
                            <span class="chat-filter-count" id="my-chats-count-main">0</span>
                        </button>
                    <?php endif; ?>
                    <button onclick="filterConversations('resolved')" data-filter="resolved" class="chat-filter-btn">
                        <i class="fas fa-check"></i>
                        <span class="chat-filter-count" id="resolved-count-main">0</span>
                    </button>
                    <button onclick="filterConversations('closed')" data-filter="closed" class="chat-filter-btn">
                        <i class="fas fa-lock"></i>
                        <span class="chat-filter-count" id="closed-count-main">0</span>
                    </button>
                    <button onclick="filterConversations('history')" data-filter="history" class="chat-filter-btn">
                        <i class="fas fa-history"></i>
                        <span>Histórico</span>
                    </button>

                    <!-- Dropdown de Canais -->
                    <div class="chat-channel-dropdown">
                        <button onclick="toggleChannelDropdown()" class="chat-filter-btn" id="channel-dropdown-btn">
                            <i class="fas fa-globe" id="channel-icon"></i>
                            <span id="channel-label">Todos os Canais</span>
                            <i class="fas fa-chevron-down" style="margin-left: 4px; font-size: 10px;"></i>
                        </button>
                        <div id="channel-dropdown-menu" class="channel-dropdown-menu hidden">
                            <button onclick="selectChannel('all')" data-channel="all" class="channel-dropdown-item active">
                                <i class="fas fa-globe"></i>
                                <span>Todos os Canais</span>
                                <i class="fas fa-check channel-check"></i>
                            </button>
                            <button onclick="selectChannel('whatsapp')" data-channel="whatsapp" class="channel-dropdown-item">
                                <i class="fab fa-whatsapp"></i>
                                <span>WhatsApp</span>
                                <i class="fas fa-check channel-check"></i>
                            </button>
                            <button onclick="selectChannel('telegram')" data-channel="telegram" class="channel-dropdown-item">
                                <i class="fab fa-telegram"></i>
                                <span>Telegram</span>
                                <i class="fas fa-check channel-check"></i>
                            </button>
                            <button onclick="selectChannel('facebook')" data-channel="facebook" class="channel-dropdown-item">
                                <i class="fab fa-facebook-messenger"></i>
                                <span>Facebook</span>
                                <i class="fas fa-check channel-check"></i>
                            </button>
                            <button onclick="selectChannel('instagram')" data-channel="instagram" class="channel-dropdown-item">
                                <i class="fab fa-instagram"></i>
                                <span>Instagram</span>
                                <i class="fas fa-check channel-check"></i>
                            </button>
                            <button onclick="selectChannel('teams')" data-channel="teams" class="channel-dropdown-item">
                                <i class="fas fa-users"></i>
                                <span>Microsoft Teams</span>
                                <i class="fas fa-check channel-check"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Campo de Busca (Movido para cá) -->
                    <div class="chat-search-box-inline">
                        <input type="text" id="search-conversations" placeholder="Buscar...">
                        <i class="fas fa-search"></i>
                    </div>

                    <!-- Botão Nova Conversa (Movido para cá) -->
                    <button onclick="showNewChatModal()" class="chat-new-btn-inline">
                        <i class="fas fa-plus"></i>
                        <span>Nova</span>
                    </button>
                </div>
            </div>

            <!-- Estado: Nenhuma conversa selecionada -->
            <div id="no-chat-selected" class="chat-empty-state">
                <i class="fas fa-comment-dots"></i>
                <h3>Selecione uma conversa</h3>
                <p>Escolha uma conversa na lista ao lado para começar</p>
            </div>

            <!-- Área do Chat (oculta inicialmente) -->
            <div id="chat-area" class="chat-area-container">
                <!-- Header do Contato -->
                <div class="chat-contact-header">
                    <div class="chat-contact-info">
                        <div class="chat-contact-avatar" id="chat-avatar-container">
                            <span>--</span>
                        </div>
                        <div class="chat-contact-details">
                            <h3 id="chat-contact-name">--</h3>
                            <p id="chat-contact-phone">--</p>
                        </div>
                    </div>
                    <div class="chat-action-buttons">
                        <button onclick="openEditContactModal()" class="chat-action-btn edit" title="Editar Contato" style="background: none !important; border: none !important; border-radius: 0 !important; box-shadow: none !important; cursor: pointer !important; color: #60a5fa !important; font-size: 18px !important; padding: 8px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important;">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="refreshMessages()" class="chat-action-btn refresh" title="Atualizar" style="background: none !important; border: none !important; border-radius: 0 !important; box-shadow: none !important; cursor: pointer !important; color: #10b981 !important; font-size: 18px !important; padding: 8px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important;">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <button onclick="deleteConversation()" class="chat-action-btn close" title="Deletar" style="background: none !important; border: none !important; border-radius: 0 !important; box-shadow: none !important; cursor: pointer !important; color: #f87171 !important; font-size: 18px !important; padding: 8px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important;">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>

                <?php if ($is_supervisor): ?>
                    <!-- Botões de Ação do Supervisor -->
                    <div class="chat-supervisor-actions">
                        <!-- Botão ATENDER - Visível apenas para conversas não atendidas -->
                        <button id="btn-atender" onclick="atenderConversa()" class="chat-supervisor-btn atender" style="display: none;">
                            <i class="fas fa-headset"></i>
                            ATENDER
                        </button>
                        <button onclick="openInternalNoteModal()" class="chat-supervisor-btn interno">
                            <i class="fas fa-sticky-note"></i>
                            INTERNO
                        </button>
                        <button onclick="markAsResolved()" class="chat-supervisor-btn resolvido">
                            <i class="fas fa-check-circle"></i>
                            RESOLVIDO
                        </button>
                        <button onclick="openTransferModal()" class="chat-supervisor-btn transferir">
                            <i class="fas fa-exchange-alt"></i>
                            TRANSFERIR
                        </button>
                        <button onclick="closeConversation()" class="chat-supervisor-btn encerrar">
                            <i class="fas fa-lock"></i>
                            ENCERRAR
                        </button>
                        <button onclick="openKanbanModal()" class="chat-supervisor-btn kanban" style="background: linear-gradient(135deg, #10b981, #059669);">
                            <i class="fas fa-columns"></i>
                            KANBAN
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Área de Mensagens -->
                <div id="chat-messages-container" class="chat-messages-area">
                    <!-- Botão Carregar Mais Antigas (oculto por padrão) -->
                    <div id="load-more-messages" class="load-more-messages hidden" style="text-align: center; padding: 15px; background: #f0f2f5; border-bottom: 1px solid #e0e0e0;">
                        <button onclick="loadMoreOlderMessages()" style="background: #25d366; color: white; border: none; padding: 10px 20px; border-radius: 20px; cursor: pointer; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fas fa-arrow-up"></i>
                            <span id="load-more-text">Carregar mensagens mais antigas</span>
                            <span id="load-more-count" style="background: rgba(255,255,255,0.3); padding: 2px 8px; border-radius: 10px; font-size: 12px;"></span>
                        </button>
                    </div>

                    <div id="messages-loading" class="chat-empty-state">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                </div>

                <!-- Input de Mensagem - Novo Estilo -->
                <div id="chat-footer" class="chat-input-area">
                    <div id="chat-reply-preview" class="chat-reply-preview hidden">
                        <div class="chat-reply-preview-content">
                            <span id="reply-preview-author" class="chat-reply-author">Contato</span>
                            <p id="reply-preview-text" class="chat-reply-text">Mensagem selecionada</p>
                        </div>
                        <button type="button" id="reply-preview-cancel" class="chat-reply-cancel" title="Cancelar resposta">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <!-- Emoji Picker Estilo WhatsApp -->
                    <div id="emoji-picker" class="hidden absolute bottom-20 left-4 z-50" style="width: 340px;">
                        <div class="emoji-picker-container">
                            <!-- Header com abas de categorias -->
                            <div class="emoji-picker-header">
                                <button type="button" class="emoji-category-btn active" data-category="recent" title="Recentes">
                                    <i class="fas fa-clock"></i>
                                </button>
                                <button type="button" class="emoji-category-btn" data-category="smileys" title="Smileys e pessoas">
                                    <i class="fas fa-smile"></i>
                                </button>
                                <button type="button" class="emoji-category-btn" data-category="animals" title="Animais e natureza">
                                    <i class="fas fa-paw"></i>
                                </button>
                                <button type="button" class="emoji-category-btn" data-category="food" title="Comida e bebida">
                                    <i class="fas fa-utensils"></i>
                                </button>
                                <button type="button" class="emoji-category-btn" data-category="activities" title="Atividades">
                                    <i class="fas fa-futbol"></i>
                                </button>
                                <button type="button" class="emoji-category-btn" data-category="travel" title="Viagens e lugares">
                                    <i class="fas fa-car"></i>
                                </button>
                                <button type="button" class="emoji-category-btn" data-category="objects" title="Objetos">
                                    <i class="fas fa-lightbulb"></i>
                                </button>
                                <button type="button" class="emoji-category-btn" data-category="symbols" title="Símbolos">
                                    <i class="fas fa-heart"></i>
                                </button>
                            </div>

                            <!-- Campo de busca -->
                            <div class="emoji-picker-search">
                                <i class="fas fa-search"></i>
                                <input type="text" id="emoji-search-input" placeholder="Pesquisar emoji">
                            </div>

                            <!-- Área de emojis -->
                            <div class="emoji-picker-content" id="emoji-content">
                                <div class="emoji-category-title" id="emoji-category-title">Recentes</div>
                                <div id="emoji-grid" class="emoji-grid"></div>
                            </div>

                            <!-- Footer com abas Emoji/GIF/Stickers -->
                            <div class="emoji-picker-footer">
                                <button type="button" class="emoji-footer-btn active" data-tab="emoji">
                                    <i class="fas fa-smile"></i>
                                </button>
                                <button type="button" class="emoji-footer-btn" data-tab="gif" onclick="toggleGifPicker()">
                                    GIF
                                </button>
                                <button type="button" class="emoji-footer-btn" data-tab="sticker" title="Em breve">
                                    <i class="fas fa-sticky-note"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- GIF Picker -->
                    <div id="gif-picker" class="hidden absolute bottom-20 left-4 z-50" style="width: 340px;">
                        <div class="emoji-picker-container">
                            <div class="emoji-picker-search">
                                <i class="fas fa-search"></i>
                                <input type="text" id="gif-search" placeholder="Buscar GIFs no Tenor...">
                            </div>
                            <div class="emoji-picker-content" style="height: 320px;">
                                <div id="gif-grid" class="gif-grid"></div>
                            </div>
                            <div class="emoji-picker-footer">
                                <button type="button" class="emoji-footer-btn" data-tab="emoji" onclick="toggleEmojiPicker()">
                                    <i class="fas fa-smile"></i>
                                </button>
                                <button type="button" class="emoji-footer-btn active" data-tab="gif">
                                    GIF
                                </button>
                                <button type="button" class="emoji-footer-btn" data-tab="sticker" title="Em breve">
                                    <i class="fas fa-sticky-note"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <form id="send-message-form" class="chat-input-container">
                        <!-- Botões de Anexo -->
                        <div class="chat-input-actions">
                            <button type="button" onclick="toggleEmojiPicker()" class="chat-input-action" title="Emojis">
                                <i class="fas fa-smile"></i>
                            </button>

                            <div class="relative" id="attachment-menu-container">
                                <button type="button" onclick="toggleAttachmentMenu()" class="chat-input-action" title="Anexar" id="attachment-btn">
                                    <i class="fas fa-paperclip"></i>
                                </button>

                                <!-- Menu Dropdown -->
                                <div id="attachment-menu" class="hidden absolute bottom-full left-0 mb-2 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 py-2 min-w-[200px] z-50">
                                    <button type="button" onclick="toggleGifPicker(); closeAttachmentMenu();" class="w-full px-4 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-3 text-gray-700 dark:text-gray-300">
                                        <i class="fas fa-film text-purple-500"></i>
                                        <span>GIFs</span>
                                    </button>
                                    <button type="button" onclick="document.getElementById('image-input').click(); closeAttachmentMenu();" class="w-full px-4 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-3 text-gray-700 dark:text-gray-300">
                                        <i class="fas fa-image text-blue-500"></i>
                                        <span>Imagem</span>
                                    </button>
                                    <button type="button" onclick="document.getElementById('document-input').click(); closeAttachmentMenu();" class="w-full px-4 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-3 text-gray-700 dark:text-gray-300">
                                        <i class="fas fa-file text-red-500"></i>
                                        <span>Documento</span>
                                    </button>
                                    <button type="button" onclick="document.getElementById('audio-input').click(); closeAttachmentMenu();" class="w-full px-4 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-3 text-gray-700 dark:text-gray-300">
                                        <i class="fas fa-file-audio text-green-500"></i>
                                        <span>Áudio (arquivo)</span>
                                    </button>
                                </div>
                            </div>

                            <button type="button" id="record-audio-btn" onclick="toggleAudioRecording()" class="chat-input-action" title="Gravar Áudio">
                                <i class="fas fa-microphone"></i>
                            </button>
                        </div>

                        <!-- Indicador de gravação -->
                        <div id="recording-indicator" class="hidden items-center gap-2 px-3 py-1 bg-red-500 text-white rounded-full text-sm">
                            <span class="recording-dot"></span>
                            <span id="recording-time">00:00</span>
                            <button type="button" onclick="stopAndSendAudio()" class="ml-2 hover:text-red-200" title="Parar e Enviar">
                                <i class="fas fa-stop"></i>
                            </button>
                            <button type="button" onclick="cancelAudioRecording()" class="ml-1 hover:text-red-200" title="Cancelar">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <!-- Inputs de arquivo ocultos -->
                        <input type="file" id="image-input" accept="image/*" class="hidden" onchange="handleFileSelect(this, 'image')">
                        <input type="file" id="document-input" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.rar" class="hidden" onchange="handleFileSelect(this, 'document')">
                        <input type="file" id="audio-input" accept="audio/*,.mp3,.wav,.ogg,.m4a" class="hidden" onchange="handleFileSelect(this, 'audio')">

                        <input type="text" id="message-input" class="chat-input-field" placeholder="Insira sua mensagem aqui..." required>
                        <button type="submit" id="send-button" class="chat-send-btn" title="Enviar">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Menu de contexto personalizado (clique direito em mensagens) -->
<div id="chat-context-overlay" class="chat-context-overlay hidden"></div>
<div id="chat-context-menu" class="chat-context-menu hidden" role="menu" aria-hidden="true">
    <div class="chat-context-reactions" role="group" aria-label="Reações rápidas">
        <button type="button" data-emoji="👍">👍</button>
        <button type="button" data-emoji="❤️">❤️</button>
        <button type="button" data-emoji="😂">😂</button>
        <button type="button" data-emoji="😮">😮</button>
        <button type="button" data-emoji="😢">😢</button>
        <button type="button" data-emoji="🙏">🙏</button>
        <button type="button" class="more-emoji" onclick="openEmojiPickerForReaction()" title="Mais emojis"><i class="fas fa-plus"></i></button>
    </div>
    <div class="chat-context-items">
        <button type="button" class="chat-context-item" data-action="copy">
            <i class="fas fa-copy"></i>
            <span>Copiar</span>
        </button>
        <button type="button" class="chat-context-item" data-action="save">
            <i class="fas fa-download"></i>
            <span>Salvar como…</span>
        </button>
        <button type="button" class="chat-context-item" data-action="reply">
            <i class="fas fa-reply"></i>
            <span>Responder em particular</span>
        </button>
        <button type="button" class="chat-context-item" data-action="forward">
            <i class="fas fa-share"></i>
            <span>Encaminhar</span>
        </button>
        <button type="button" class="chat-context-item" data-action="delete">
            <i class="fas fa-trash"></i>
            <span>Apagar para mim</span>
        </button>
        <button type="button" class="chat-context-item" data-action="select">
            <i class="fas fa-check-square"></i>
            <span>Selecionar</span>
        </button>
        <button type="button" class="chat-context-item" data-action="share">
            <i class="fas fa-share-alt"></i>
            <span>Compartilhar</span>
        </button>
    </div>
</div>

<!-- Modal Nova Conversa -->
<div id="new-chat-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-hidden flex flex-col">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-xl font-bold text-gray-800">Nova Conversa</h3>
        </div>

        <div class="flex-1 overflow-y-auto">
            <!-- Botão para carregar contatos -->
            <div class="mb-4">
                <button type="button" onclick="loadInstanceContacts()" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition">
                    <i class="fas fa-address-book mr-2"></i>Carregar Meus Contatos
                </button>
            </div>

            <!-- Lista de contatos -->
            <div id="contacts-list" class="mb-4 hidden">
                <div class="flex items-center justify-between mb-2">
                    <label class="text-sm font-medium text-gray-700">Selecione contato(s):</label>
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" id="select-all-contacts" onchange="toggleSelectAll()" class="w-4 h-4 text-blue-600 rounded">
                        <span class="text-sm text-gray-600">Selecionar Todos</span>
                    </label>
                </div>
                <div class="border border-gray-300 rounded-lg max-h-64 overflow-y-auto">
                    <div id="contacts-container" class="divide-y divide-gray-200">
                        <!-- Contatos serão carregados aqui -->
                    </div>
                </div>
                <div class="mt-3 flex items-center justify-between">
                    <span id="selected-count" class="text-sm text-gray-600">0 selecionado(s)</span>
                    <button type="button" onclick="createMultipleChats()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed" id="create-multiple-btn" disabled>
                        <i class="fas fa-comments mr-2"></i>Criar Conversas
                    </button>
                </div>
            </div>

            <!-- Separador -->
            <div class="relative my-4">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-2 bg-white text-gray-500">ou digite manualmente</span>
                </div>
            </div>

            <!-- Formulário manual -->
            <form id="new-chat-form">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Número do WhatsApp</label>
                    <input type="text" id="new-chat-phone" placeholder="Ex: 11999887766" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500" required>
                    <p class="text-xs text-gray-500 mt-1">Digite apenas números (DDD + número)</p>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nome (opcional)</label>
                    <input type="text" id="new-chat-name" placeholder="Nome do contato" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500">
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeNewChatModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition">
                        Cancelar
                    </button>
                    <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition">
                        <i class="fas fa-plus mr-2"></i>Criar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>



<!-- Modal Editar Nome do Contato -->
<div id="edit-contact-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-bold text-gray-800">
                    <i class="fas fa-edit text-green-600 mr-2"></i>Editar Nome do Contato
                </h3>
                <button onclick="closeEditContactModal()" class="text-gray-400 hover:text-gray-600" style="background: none !important; border: none !important; border-radius: 0 !important; box-shadow: none !important; cursor: pointer !important; color: #9ca3af !important; font-size: 20px !important; padding: 4px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; transition: color 0.2s !important;">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        <div class="p-6">
            <form id="edit-contact-form" onsubmit="saveContactName(event)">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Telefone
                    </label>
                    <input type="text" id="edit-contact-phone" readonly class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-600">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Nome do Contato
                    </label>
                    <input type="text" id="edit-contact-name-input" required placeholder="Digite o nome do contato" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500">
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeEditContactModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition">
                        Cancelar
                    </button>
                    <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition">
                        <i class="fas fa-save mr-2"></i>Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let currentConversationId = null;
    let conversations = [];
    let messages = [];
    let autoRefreshInterval = null;
    let profilePicturesCache = {}; // Cache global de fotos de perfil
    let messagesCache = {}; // Cache de mensagens por conversa para troca rápida
    let currentContextMessage = null; // Dados da mensagem usada no menu de contexto
    let currentReplyMessage = null; // Dados da mensagem que está sendo respondida
    let kanbanBoards = [];
    let kanbanColumns = [];

    // ============================================
    // FUNÇÕES DOS MODAIS - ESCOPO GLOBAL
    // ============================================
    
    // Abrir modal do Kanban
    async function openKanbanModal() {
        console.log('openKanbanModal chamado (escopo global)');
        console.log('currentConversationId:', currentConversationId);
        
        if (!currentConversationId) {
            alert('Selecione uma conversa primeiro');
            return;
        }

        const modal = document.getElementById('kanban-modal');
        console.log('Modal encontrado:', modal);
        
        if (!modal) {
            console.error('Modal kanban-modal não encontrado no DOM');
            alert('Erro: Modal não encontrado');
            return;
        }
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');

        // Carregar quadros
        await loadKanbanBoards();
    }

    // Fechar modal do Kanban
    function closeKanbanModal() {
        const modal = document.getElementById('kanban-modal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    // Abrir modal de Transferir
    function openTransferModal() {
        console.log('openTransferModal chamado (escopo global)');
        
        if (!currentConversationId) {
            alert('Selecione uma conversa primeiro');
            return;
        }

        const modal = document.getElementById('transfer-modal');
        if (!modal) {
            console.error('Modal transfer-modal não encontrado no DOM');
            alert('Erro: Modal não encontrado');
            return;
        }
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    // Fechar modal de Transferir
    function closeTransferModal() {
        const modal = document.getElementById('transfer-modal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    // Abrir modal de Nota Interna
    function openInternalNoteModal() {
        console.log('openInternalNoteModal chamado (escopo global)');
        
        if (!currentConversationId) {
            alert('Selecione uma conversa primeiro');
            return;
        }

        const modal = document.getElementById('internal-note-modal');
        if (!modal) {
            console.error('Modal internal-note-modal não encontrado no DOM');
            alert('Erro: Modal não encontrado');
            return;
        }
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    // Fechar modal de Nota Interna
    function closeInternalNoteModal() {
        const modal = document.getElementById('internal-note-modal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    // Carregar quadros do Kanban
    async function loadKanbanBoards() {
        try {
            const response = await fetch('api/kanban/boards.php');
            const data = await response.json();

            const select = document.getElementById('kanban-board');

            if (data.success && data.boards.length > 0) {
                kanbanBoards = data.boards;
                select.innerHTML = data.boards.map(board =>
                    `<option value="${board.id}" ${board.is_default ? 'selected' : ''}>${board.name}</option>`
                ).join('');

                // Carregar colunas do primeiro quadro
                loadKanbanColumns();
            } else {
                select.innerHTML = '<option value="">Nenhum quadro encontrado</option>';
                document.getElementById('kanban-column').innerHTML = '<option value="">Crie um quadro primeiro</option>';
            }
        } catch (error) {
            console.error('Erro ao carregar quadros:', error);
            document.getElementById('kanban-board').innerHTML = '<option value="">Erro ao carregar</option>';
        }
    }

    // Carregar colunas do quadro selecionado
    async function loadKanbanColumns() {
        const boardId = document.getElementById('kanban-board').value;
        const select = document.getElementById('kanban-column');

        if (!boardId) {
            select.innerHTML = '<option value="">Selecione um quadro</option>';
            return;
        }

        try {
            const response = await fetch(`api/kanban/columns.php?board_id=${boardId}`);
            const data = await response.json();

            if (data.success && data.columns.length > 0) {
                kanbanColumns = data.columns;
                select.innerHTML = data.columns.map(col =>
                    `<option value="${col.id}">${col.name}</option>`
                ).join('');
            } else {
                select.innerHTML = '<option value="">Nenhuma coluna encontrada</option>';
            }
        } catch (error) {
            console.error('Erro ao carregar colunas:', error);
            select.innerHTML = '<option value="">Erro ao carregar</option>';
        }
    }
    
    const forwardModalState = {
        selectedIds: new Set(),
        search: '',
        sourceType: 'text'
    };
    const currentUserName = <?= json_encode($userName) ?>; // Nome do atendente logado

    // Função utilitária - Escape HTML (definida no início para estar disponível em todo o script)
    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        return String(text).replace(/[&<>"']/g, function(m) {
            switch (m) {
                case '&':
                    return '&amp;';
                case '<':
                    return '&lt;';
                case '>':
                    return '&gt;';
                case '"':
                    return '&quot;';
                case "'":
                    return '&#039;';
            }
            return m;
        });
    }

    function escapeHtmlAttribute(text) {
        if (!text) return '';
        return escapeHtml(text).replace(/"/g, '&quot;').replace(/\n/g, '&#10;');
    }

    function decodeHtmlEntities(text) {
        if (!text) return '';
        const textarea = document.createElement('textarea');
        textarea.innerHTML = text;
        return textarea.value;
    }

    // ===============================
    // HELPER FUNCTIONS
    // ===============================
    function markMessageAsDeleted(messageElement) {
        if (!messageElement) return;
        messageElement.classList.add('deleted');
        const bubble = messageElement.querySelector('.chat-message-bubble');
        if (!bubble) return;
        bubble.querySelectorAll('.chat-message-image, .chat-audio-message, video, audio, .chat-reply-tag, a').forEach(el => el.remove());
        const textEl = bubble.querySelector('.chat-message-text');
        if (textEl) {
            textEl.textContent = '[Mensagem apagada]';
            textEl.classList.add('deleted-text');
        } else {
            const paragraph = document.createElement('p');
            paragraph.className = 'chat-message-text deleted-text';
            paragraph.textContent = '[Mensagem apagada]';
            const timeEl = bubble.querySelector('.chat-message-time');
            if (timeEl) {
                bubble.insertBefore(paragraph, timeEl);
            } else {
                bubble.appendChild(paragraph);
            }
        }
    }

    // ===============================
    // RESPOSTAS / QUOTED MESSAGES
    // ===============================
    function setupReplyPreview() {
        const cancelBtn = document.getElementById('reply-preview-cancel');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', clearReplyContext);
        }
        renderReplyPreview();
    }

    function setReplyContext(messageData) {
        currentReplyMessage = messageData || null;
        renderReplyPreview();
    }

    function clearReplyContext() {
        currentReplyMessage = null;
        renderReplyPreview();
    }

    function renderReplyPreview() {
        const preview = document.getElementById('chat-reply-preview');
        if (!preview) return;
        if (currentReplyMessage) {
            const author = document.getElementById('reply-preview-author');
            const text = document.getElementById('reply-preview-text');
            if (author) {
                author.textContent = currentReplyMessage.fromMe ? 'Você' : 'Contato';
            }
            if (text) {
                text.textContent = currentReplyMessage.text || '[Mensagem]';
            }
            preview.classList.remove('hidden');
        } else {
            preview.classList.add('hidden');
        }
    }

    function getMessageSummary(message) {
        if (!message) return '[Mensagem]';
        if (message.text && message.text.trim() !== '') {
            return message.text.trim();
        }
        switch (message.type) {
            case 'image':
                return '[Imagem]';
            case 'audio':
                return '[Áudio]';
            case 'video':
                return '[Vídeo]';
            case 'document':
                return message.mediaUrl ? `[Documento] ${message.mediaUrl.split('/').pop()}` : '[Documento]';
            default:
                return '[Mensagem]';
        }
    }

    // Controle de scroll inteligente - só auto-scroll se usuário estiver no final
    let userIsScrolling = false;
    let lastScrollPosition = 0;

    function isUserNearBottom() {
        const container = document.getElementById('chat-messages-container');
        if (!container) return true;

        const threshold = 150; // pixels de tolerância
        const distanceFromBottom = container.scrollHeight - container.scrollTop - container.clientHeight;
        return distanceFromBottom < threshold;
    }

    function scrollToBottom(force = false) {
        const container = document.getElementById('chat-messages-container');
        if (!container) return;

        // Só rolar se o usuário estiver perto do final OU se for forçado
        if (force || isUserNearBottom()) {
            container.scrollTop = container.scrollHeight;
        }
    }

    // Desativar autoplay de vídeos e áudios
    function disableMediaAutoplay() {
        // Pausar todos os vídeos
        const videos = document.querySelectorAll('video');
        videos.forEach(video => {
            video.pause();
            video.autoplay = false;
            video.preload = 'metadata'; // Só carregar metadados, não o vídeo inteiro
            if (!video.hasAttribute('controls')) {
                video.setAttribute('controls', 'true');
            }
        });

        // Pausar todos os áudios
        const audios = document.querySelectorAll('audio');
        audios.forEach(audio => {
            audio.pause();
            audio.autoplay = false;
            if (!audio.hasAttribute('controls')) {
                audio.setAttribute('controls', 'true');
            }
        });

        // Pausar GIFs convertendo para imagem estática (opcional - descomente se necessário)
        // const gifs = document.querySelectorAll('img[src$=".gif"]');
        // gifs.forEach(gif => {
        //     gif.style.animationPlayState = 'paused';
        // });
    }

    // Mesclar conversas duplicadas automaticamente
    async function mergeConversations() {
        try {
            const response = await fetch('api/merge_conversations.php');
            const data = await response.json();
            if (data.success && data.conversations_merged > 0) {
                console.log('[MERGE] Conversas mescladas:', data.conversations_merged);
                // Recarregar lista após mesclagem
                await loadConversations();
            }
        } catch (error) {
            console.error('[MERGE] Erro ao mesclar:', error);
        }
    }

    // Inicializar ao carregar página
    document.addEventListener('DOMContentLoaded', function() {
        // Variável global para rastrear última mensagem enviada
        window.lastMessageSentTime = 0;

        // ✅ Variável para controlar se polling está em execução
        window.pollingInProgress = false;

        // Verificar se deve abrir conversa após reload
        const conversationToOpen = localStorage.getItem('openConversationAfterReload');
        if (conversationToOpen) {
            console.log('[INIT] Conversa para abrir após reload:', conversationToOpen);
            localStorage.removeItem('openConversationAfterReload');
            
            // Aguardar conversas carregarem e abrir
            setTimeout(() => {
                const convId = parseInt(conversationToOpen);
                const conv = conversations.find(c => c.id === convId);
                if (conv) {
                    console.log('[INIT] Abrindo conversa:', convId);
                    openConversation(convId);
                } else {
                    console.error('[INIT] Conversa não encontrada:', convId);
                    console.log('[INIT] Total de conversas carregadas:', conversations.length);
                    console.log('[INIT] IDs das conversas:', conversations.map(c => c.id));
                    console.log('[INIT] Teste direto: /api/test_conversation.php?id=' + convId);
                    
                    // Mostrar erro ao usuário
                    showError('Conversa criada mas não apareceu na lista. Recarregue a página manualmente.');
                }
            }, 2000);
        }

        // Mesclar duplicadas primeiro, depois carregar
        mergeConversations().then(() => {
            loadConversations();
        });
        setupSearchConversations();
        setupSendMessage();
        setupChatContextMenu();
        setupReplyPreview();
        disableMediaAutoplay(); // Desativar autoplay de mídias apenas na inicialização

        // Observer DESABILITADO - estava causando AbortError ao pausar áudios iniciados manualmente
        // const chatContainer = document.getElementById('chat-messages-container');
        // if (chatContainer) {
        //     const mediaObserver = new MutationObserver(function(mutations) {
        //         disableMediaAutoplay();
        //     });
        //     mediaObserver.observe(chatContainer, { childList: true, subtree: true });
        // }

        // Polling automático - atualiza conversas e mensagens a cada 5 segundos
        // Intervalo otimizado para tempo real (WhatsApp Web usa 3-5s)
        // 5s = 12 req/min por usuário (aceitável com cache de imagens)
        let pollingInterval = setInterval(async () => {
            // ✅ Pular se já houver polling em execução
            if (window.pollingInProgress) {
                console.log('[POLLING] Pulando - polling anterior ainda em execução');
                return;
            }

            // Pular polling se estiver enviando mídia
            if (window.sendingMedia) {
                console.log('[POLLING] Pulando - enviando mídia');
                return;
            }

            // ✅ Marcar que polling está em execução
            window.pollingInProgress = true;

            try {
                console.log('[POLLING] Iniciando atualização...');

                // Adicionar timestamp para evitar cache
                const timestamp = Date.now();

                // Atualizar lista de conversas (com timestamp para evitar cache)
                const convResponse = await fetch(`api/chat_conversations.php?t=${timestamp}`);

                // ✅ Verificar se resposta é válida
                if (!convResponse.ok) {
                    console.warn('[POLLING] Resposta HTTP inválida ao buscar conversas:', convResponse.status);
                    return; // Sair silenciosamente, não mostrar erro
                }

                const convData = await convResponse.json();

                if (convData.success && convData.conversations) {
                    // Detectar novas mensagens (APENAS após primeira carga)
                    if (previousConversations.length > 0 && !isFirstLoad) {
                        checkForNewMessages(convData.conversations);
                    }

                    previousConversations = JSON.parse(JSON.stringify(convData.conversations));
                    conversations = convData.conversations;

                    // Renderizar conversas APENAS se não estiver enviando mídia
                    if (!window.sendingMedia) {
                        const searchInput = document.getElementById('search-conversations');
                        const searchQuery = searchInput ? searchInput.value.trim() : '';

                        if (searchQuery === '') {
                            renderConversations(conversations);
                        }
                    } else {
                        console.log('[POLLING] Pulando renderConversations - enviando mídia');
                    }

                    console.log('[POLLING] Conversas atualizadas:', conversations.length);
                }

                // Se há uma conversa aberta, atualizar mensagens também
                if (currentConversationId && currentConversationId > 0) {
                    console.log('[POLLING] Atualizando mensagens da conversa:', currentConversationId);

                    // Buscar últimas 50 mensagens (performance excelente)
                    // Botão "Carregar mais" permite buscar mensagens antigas
                    const msgResponse = await fetch(`api/chat_messages.php?conversation_id=${currentConversationId}&limit=50&t=${timestamp}`);

                    // ✅ Verificar se resposta é válida
                    if (!msgResponse.ok) {
                        console.warn('[POLLING] Resposta HTTP inválida:', msgResponse.status);
                        return; // Sair silenciosamente, não mostrar erro
                    }

                    const msgData = await msgResponse.json();

                    if (msgData.success && msgData.messages) {
                        // Verificar se há mensagens temporárias na tela
                        const hasTempMessages = document.querySelector('.temp-message') !== null;

                        // Verificar se uma mensagem foi enviada recentemente (últimos 10 segundos - OTIMIZADO)
                        const timeSinceLastSent = Date.now() - (window.lastMessageSentTime || 0);
                        const recentlySent = timeSinceLastSent < 10000; // 10 segundos (reduzido para tempo real)

                        // ✅ LOG DETALHADO para debug
                        console.log('[POLLING] Verificação de proteção:', {
                            hasTempMessages,
                            lastMessageSentTime: window.lastMessageSentTime,
                            timeSinceLastSent: Math.round(timeSinceLastSent / 1000) + 's',
                            recentlySent,
                            shouldProtect: hasTempMessages || recentlySent
                        });

                        // Se há mensagens temporárias OU mensagem enviada recentemente, não re-renderizar
                        if (hasTempMessages || recentlySent) {
                            if (hasTempMessages) {
                                console.log('[POLLING] Mensagens temporárias detectadas, atualizando apenas cache');
                            }
                            if (recentlySent) {
                                console.log('[POLLING] Mensagem enviada recentemente (' + Math.round(timeSinceLastSent / 1000) + 's atrás), atualizando apenas cache');
                            }

                            // ✅ PROTEÇÃO INTELIGENTE: Mesclar mensagens antigas com novas
                            // Garante que mensagens recém-enviadas não sejam perdidas
                            const oldMessages = messages || [];
                            const newMessages = msgData.messages || [];

                            // Criar mapa de IDs das novas mensagens
                            const newMessageIds = new Set(newMessages.map(m => m.id));

                            // Manter mensagens antigas que não estão nas novas (mensagens recém-enviadas)
                            const recentMessages = oldMessages.filter(m => {
                                // Manter se:
                                // 1. É mensagem temporária (ID começa com 'temp_')
                                // 2. Não está nas novas mensagens (foi enviada recentemente e ainda não está no banco)
                                // 3. Foi enviada nos últimos 30 segundos (otimizado para tempo real)
                                const isTemp = String(m.id).startsWith('temp_');
                                const notInNew = !newMessageIds.has(m.id);
                                const isRecent = m.timestamp && (Date.now() / 1000 - m.timestamp) < 30; // 30 segundos

                                return isTemp || (notInNew && isRecent);
                            });

                            // Mesclar: novas mensagens + mensagens recentes que não estão nas novas
                            const mergedMessages = [...newMessages, ...recentMessages];

                            // Ordenar por timestamp
                            mergedMessages.sort((a, b) => {
                                const timeA = a.timestamp || 0;
                                const timeB = b.timestamp || 0;
                                return timeA - timeB;
                            });

                            // ✅ IMPORTANTE: Atualizar tanto o cache quanto o array local
                            messagesCache[currentConversationId] = mergedMessages;
                            messages = mergedMessages;

                            console.log('[POLLING] Mensagens mescladas:', {
                                novas: newMessages.length,
                                recentes: recentMessages.length,
                                total: mergedMessages.length
                            });

                            // ✅ NÃO re-renderizar, apenas atualizar cache
                            // As mensagens já estão na tela
                        } else {
                            // Comparar mensagens para detectar mudanças
                            const oldMessages = messages || [];
                            const newMessages = msgData.messages || [];

                            const oldCount = oldMessages.filter(m => !String(m.id).startsWith('temp_')).length;
                            const newCount = newMessages.length;

                            console.log('[POLLING] Comparando - antigas:', oldCount, 'novas:', newCount);

                            // ✅ VERIFICAR SE HÁ MENSAGENS RECENTES QUE NÃO ESTÃO NO SERVIDOR
                            const newMessageIds = new Set(newMessages.map(m => m.id));
                            const recentNotInServer = oldMessages.filter(m => {
                                const notTemp = !String(m.id).startsWith('temp_');
                                const notInNew = !newMessageIds.has(m.id);
                                const isRecent = m.timestamp && (Date.now() / 1000 - m.timestamp) < 30; // 30 segundos
                                return notTemp && notInNew && isRecent;
                            });

                            console.log('[POLLING] Mensagens recentes não no servidor:', recentNotInServer.length);
                            
                            // ✅ VERIFICAR SE HÁ MENSAGENS NOVAS QUE NÃO ESTÃO NA TELA
                            const currentMessageIds = new Set(oldMessages.map(m => m.id));
                            const hasNewMessages = newMessages.some(m => !currentMessageIds.has(m.id));
                            
                            if (hasNewMessages) {
                                console.log('[POLLING] ✨ Novas mensagens detectadas! Re-renderizando...');
                                messagesCache[currentConversationId] = newMessages;
                                messages = newMessages;
                                renderMessages(newMessages, false);
                            }

                            // Se há mensagens recentes que não estão no servidor, MESCLAR em vez de substituir
                            if (recentNotInServer.length > 0) {
                                console.log('[POLLING] Mesclando mensagens recentes com novas do servidor');

                                const mergedMessages = [...newMessages, ...recentNotInServer];

                                // Ordenar por timestamp
                                mergedMessages.sort((a, b) => {
                                    const timeA = a.timestamp || 0;
                                    const timeB = b.timestamp || 0;
                                    return timeA - timeB;
                                });

                                messagesCache[currentConversationId] = mergedMessages;
                                messages = mergedMessages;

                                // Re-renderizar com mensagens mescladas
                                renderMessages(mergedMessages, false);

                                console.log('[POLLING] Mensagens mescladas e re-renderizadas:', {
                                    servidor: newMessages.length,
                                    recentes: recentNotInServer.length,
                                    total: mergedMessages.length
                                });
                            }
                            // SEMPRE re-renderizar se quantidade diferente E não há mensagens recentes
                            else if (newCount !== oldCount) {
                                console.log('[POLLING] Quantidade diferente! Re-renderizando...');
                                messagesCache[currentConversationId] = newMessages;
                                messages = newMessages;
                                renderMessages(newMessages, false);
                            } else if (newCount > 0) {
                                // Mesma quantidade - verificar se há mudanças de status
                                let hasStatusChange = false;
                                const oldMessagesMap = {};
                                oldMessages.forEach(m => {
                                    if (m.id && !String(m.id).startsWith('temp_')) {
                                        oldMessagesMap[m.id] = {
                                            status: m.status,
                                            read_at: m.read_at
                                        };
                                    }
                                });

                                for (const newMsg of newMessages) {
                                    const oldMsg = oldMessagesMap[newMsg.id];
                                    if (!oldMsg) {
                                        // Mensagem nova que não existia antes
                                        console.log('[POLLING] Nova mensagem encontrada:', newMsg.id);
                                        messagesCache[currentConversationId] = newMessages;
                                        messages = newMessages;
                                        renderMessages(newMessages, false);
                                        hasStatusChange = true;
                                        break;
                                    } else if (oldMsg.status !== newMsg.status || oldMsg.read_at !== newMsg.read_at) {
                                        hasStatusChange = true;
                                    }
                                }

                                // Atualizar status visual sem re-renderizar tudo
                                // ✅ SEMPRE atualizar quando houver mudança de status
                                if (hasStatusChange) {
                                    console.log('[POLLING] 🔄 Atualizando status das mensagens...');
                                    newMessages.forEach(msg => {
                                        if (msg.from_me) {
                                            const msgElement = document.querySelector(`[data-message-id="${msg.id}"]`);
                                            if (msgElement) {
                                                const statusSpan = msgElement.querySelector('.chat-message-status');
                                                if (statusSpan) {
                                                    const status = (msg.status || '').toLowerCase();
                                                    if (msg.read_at || status === 'read' || status === 'played') {
                                                        statusSpan.className = 'chat-message-status read';
                                                        statusSpan.textContent = '✓✓';
                                                    } else if (status === 'delivered' || status === 'received') {
                                                        statusSpan.className = 'chat-message-status';
                                                        statusSpan.textContent = '✓✓';
                                                    }
                                                }
                                            }
                                        }
                                    });
                                    messagesCache[currentConversationId] = newMessages;
                                    messages = newMessages;
                                }
                            } else {
                                console.log('[POLLING] Nenhuma mudança');
                            }
                        }
                    }
                }

            } catch (error) {
                console.error('[POLLING] Erro:', error);
                // ✅ Não mostrar erro ao usuário durante polling
                // Erros de rede são comuns e não devem interromper a experiência
                // Apenas logar no console para debug
            } finally {
                // ✅ Liberar polling para próxima execução
                window.pollingInProgress = false;
            }
        }, 5000); // A cada 5 segundos (tempo real - WhatsApp Web usa 3-5s)

        // Limpar intervalo quando sair da página
        window.addEventListener('beforeunload', () => {
            if (pollingInterval) {
                clearInterval(pollingInterval);
            }
        });
    });

    // Variável para rastrear conversas anteriores
    let previousConversations = [];
    let isFirstLoad = true; // Flag para primeira carga

    // Carregar conversas
    async function loadConversations() {
        try {
            // ⭐ FASE 4: Usando novo sistema de configuração
            const response = await API_CONFIG.request('conversations');
            const data = await response.json();

            if (data.success) {
                const newConversations = data.conversations;

                // Detectar novas mensagens (APENAS após primeira carga)
                if (previousConversations.length > 0 && !isFirstLoad) {
                    checkForNewMessages(newConversations);
                }

                // Marcar que primeira carga foi concluída
                if (isFirstLoad) {
                    isFirstLoad = false;
                    console.log('[CHAT] Primeira carga concluída - notificações ativadas');
                }

                previousConversations = JSON.parse(JSON.stringify(newConversations));
                conversations = newConversations;

                // Verificar se há busca ativa - NÃO sobrescrever resultados da busca
                const searchInput = document.getElementById('search-conversations');
                const searchQuery = searchInput ? searchInput.value.trim() : '';

                if (searchQuery === '') {
                    // Só renderizar se não há busca ativa
                    renderConversations(conversations);
                } else if (lastSearchResults) {
                    // Se há busca ativa, re-filtrar com os novos dados
                    const query = searchQuery.toLowerCase();
                    const filteredConversations = conversations.filter(conv =>
                        (conv.display_name && conv.display_name.toLowerCase().includes(query)) ||
                        (conv.phone && conv.phone.includes(query))
                    );
                    // Manter os contatos sugeridos do cache
                    renderConversationsWithSuggestions(filteredConversations, lastSearchResults.contacts || []);
                }

                // Atualizar contadores dos filtros
                if (typeof updateFilterCounts === 'function') {
                    updateFilterCounts();
                }
            } else {
                showError('Erro ao carregar conversas: ' + (data.error || 'Erro desconhecido'));
            }
        } catch (error) {
            console.error('Erro:', error);
            showError('Erro ao conectar com o servidor');
        }
    }

    // Verificar novas mensagens e mostrar notificações
    function checkForNewMessages(newConversations) {
        newConversations.forEach(newConv => {
            const oldConv = previousConversations.find(c => c.id === newConv.id);

            // IMPORTANTE: Só notificar se a conversa JÁ EXISTIA antes (oldConv existe)
            // E se o unread_count AUMENTOU (nova mensagem chegou)
            // Isso evita notificações de mensagens antigas ao carregar a página
            if (oldConv && newConv.unread_count > 0 && newConv.unread_count > (oldConv.unread_count || 0)) {
                // Não mostrar notificação se a conversa atual está aberta
                if (currentConversationId !== newConv.id) {
                    console.log('[NOTIF] Nova mensagem detectada:', newConv.display_name, '| Msg:', newConv.last_message_text);
                    showNewMessageNotification(newConv);
                } else {
                    console.log('[NOTIF] Nova mensagem na conversa aberta (sem popup):', newConv.display_name);
                }
            }
        });
    }

    // Mostrar notificação de nova mensagem
    function showNewMessageNotification(conversation) {
        console.log('[NOTIF] Preparando notificação para:', conversation.display_name);

        // Verificar se chatNotifications está disponível
        if (typeof chatNotifications !== 'undefined' && chatNotifications.isInitialized) {
            console.log('[NOTIF] chatNotifications disponível, mostrando popup');
            chatNotifications.show({
                contactName: conversation.display_name || conversation.contact_name || 'Contato',
                contactPhone: conversation.phone || '',
                message: conversation.last_message_text || 'Nova mensagem',
                profilePic: conversation.profile_pic_url || conversation.cached_profile_pic || null,
                conversationId: conversation.id
            });
        } else if (typeof chatNotifications !== 'undefined') {
            // chatNotifications existe mas não está inicializado ainda
            console.log('[NOTIF] chatNotifications existe mas não inicializado, adicionando à fila');
            chatNotifications.show({
                contactName: conversation.display_name || conversation.contact_name || 'Contato',
                contactPhone: conversation.phone || '',
                message: conversation.last_message_text || 'Nova mensagem',
                profilePic: conversation.profile_pic_url || conversation.cached_profile_pic || null,
                conversationId: conversation.id
            });
        } else {
            console.error('[NOTIF] chatNotifications não está definido! Verifique se chat_notifications.js foi carregado.');

            // Fallback: usar notificação do navegador diretamente
            if ('Notification' in window && Notification.permission === 'granted') {
                try {
                    new Notification(conversation.display_name || 'Nova mensagem', {
                        body: conversation.last_message_text || 'Você recebeu uma nova mensagem',
                        icon: '/assets/images/logo.png',
                        tag: 'chat-' + conversation.id
                    });
                } catch (e) {
                    console.error('[NOTIF] Erro ao criar notificação do navegador:', e);
                }
            }
        }
    }

    // Renderizar lista de conversas
    // Usa atualização incremental para evitar "piscando" das fotos
    function renderConversations(convList) {
        const container = document.getElementById('conversations-container');
        const loading = document.getElementById('conversations-loading');
        const empty = document.getElementById('conversations-empty');

        if (!container || !loading || !empty) {
            console.error('Elementos do DOM não encontrados');
            return;
        }

        loading.classList.add('hidden');

        // Aplicar filtro de canal (se a função existir)
        const filteredList = (typeof applyChannelFilter === 'function') ? applyChannelFilter(convList) : convList;

        if (filteredList.length === 0) {
            empty.classList.remove('hidden');
            container.innerHTML = '';
            return;
        }

        empty.classList.add('hidden');

        // Verificar se já existem elementos - fazer atualização incremental
        const existingItems = container.querySelectorAll('.chat-conversation-item');

        // Se já tem elementos E a quantidade é a mesma, fazer atualização incremental
        if (existingItems.length > 0 && existingItems.length === filteredList.length) {
            let allExist = true;
            filteredList.forEach(conv => {
                if (!container.querySelector(`[data-conversation-id="${conv.id}"]`)) {
                    allExist = false;
                }
            });

            if (allExist) {
                filteredList.forEach(conv => {
                    const existingItem = container.querySelector(`[data-conversation-id="${conv.id}"]`);
                    if (existingItem) {
                        // Atualizar apenas os dados que mudam (não a foto!)
                        const nameEl = existingItem.querySelector('.chat-conversation-name');
                        const timeEl = existingItem.querySelector('.chat-conversation-time');
                        const previewEl = existingItem.querySelector('.chat-conversation-preview span');
                        const unreadEl = existingItem.querySelector('.chat-unread-count');

                        if (nameEl) nameEl.textContent = conv.display_name;
                        if (timeEl) timeEl.textContent = conv.last_message_time_formatted || '';
                        if (previewEl) previewEl.textContent = conv.last_message_text || 'Sem mensagens';

                        // Atualizar badge de não lidas
                        if (conv.unread_count > 0) {
                            if (unreadEl) {
                                unreadEl.textContent = conv.unread_count;
                            } else {
                                const preview = existingItem.querySelector('.chat-conversation-preview');
                                if (preview) {
                                    preview.insertAdjacentHTML('beforeend', `<span class="chat-unread-count">${conv.unread_count}</span>`);
                                }
                            }
                        } else if (unreadEl) {
                            unreadEl.remove();
                        }

                        // Atualizar classe active
                        if (conv.id === currentConversationId) {
                            existingItem.classList.add('active');
                        } else {
                            existingItem.classList.remove('active');
                        }
                    }
                });

                // Reordenar se necessário (mover conversa com nova mensagem para o topo)
                const firstConvId = filteredList[0]?.id;
                const firstItem = container.querySelector(`[data-conversation-id="${firstConvId}"]`);
                if (firstItem && firstItem !== container.firstElementChild) {
                    container.insertBefore(firstItem, container.firstElementChild);
                }

                return; // Não recriar tudo
            }
        }

        // Atualizar contador
        const countText = document.getElementById('conversations-count-text');
        if (countText) {
            countText.textContent = `Mostrando ${filteredList.length} de ${conversations.length} chats`;
        }

        container.innerHTML = filteredList.map(conv => {
            // Criar chave única para cache baseada no canal e ID da conversa
            // Para Teams: usar ID da conversa (único)
            // Para WhatsApp/Email: usar contact_number (compatibilidade)
            const cacheKey = conv.channel_type === 'teams' ? `teams_${conv.id}` : conv.contact_number;

            // Prioridade de foto: foto local da API > cache local > cache do banco
            let photoUrl = conv.profile_picture_url ||
                profilePicturesCache[cacheKey] ||
                conv.cached_profile_pic ||
                conv.profile_pic_url;

            // Salvar no cache local se veio do banco
            if (photoUrl && !profilePicturesCache[cacheKey]) {
                profilePicturesCache[cacheKey] = photoUrl;
            }

            // Adicionar versioning para forçar reload (especialmente importante para Teams)
            if (photoUrl && !photoUrl.includes('?v=')) {
                photoUrl += '?v=' + Date.now();
            }

            const initials = getInitials(conv.display_name);
            const unreadBadge = conv.unread_count > 0 ?
                `<span class="chat-unread-count">${conv.unread_count}</span>` :
                '';

            // Avatar com foto ou iniciais
            const avatarContent = photoUrl ?
                `<img src="${photoUrl}" alt="${escapeHtml(conv.display_name)}" onerror="this.parentElement.innerHTML='<span>${initials}</span>'">` :
                `<span>${initials}</span>`;

            // Badge de status/setor
            let statusBadge = '';
            if (conv.status === 'resolved') {
                statusBadge = '<span class="chat-conversation-badge geral">Resolvido</span>';
            } else if (conv.department_name) {
                statusBadge = `<span class="chat-conversation-badge geral">${escapeHtml(conv.department_name)}</span>`;
            }

            // Badge de canal (ícone pequeno no canto do avatar)
            const source = (conv.channel_type || conv.source || 'whatsapp').toLowerCase();
            let channelBadge = '';
            if (source === 'teams') {
                channelBadge = '<span class="channel-badge teams"><i class="fab fa-microsoft"></i></span>';
            } else if (source === 'telegram') {
                channelBadge = '<span class="channel-badge telegram"><i class="fab fa-telegram"></i></span>';
            } else if (source === 'facebook' || source === 'messenger') {
                channelBadge = '<span class="channel-badge facebook"><i class="fab fa-facebook-messenger"></i></span>';
            } else if (source === 'instagram') {
                channelBadge = '<span class="channel-badge instagram"><i class="fab fa-instagram"></i></span>';
            } else if (source === 'email') {
                channelBadge = '<span class="channel-badge email"><i class="fas fa-envelope"></i></span>';
            } else if (source === 'whatsapp') {
                channelBadge = '<span class="channel-badge whatsapp"><i class="fab fa-whatsapp"></i></span>';
            }

            return `
            <div class="chat-conversation-item ${conv.id === currentConversationId ? 'active' : ''}" 
                 data-conversation-id="${conv.id}"
                 data-phone="${conv.contact_number}"
                 data-source="${source}">
                <div class="chat-conversation-avatar">
                    ${avatarContent}
                    ${channelBadge}
                </div>
                <div class="chat-conversation-info">
                    <div class="chat-conversation-header">
                        <span class="chat-conversation-name">${escapeHtml(conv.display_name)}</span>
                        <span class="chat-conversation-time">${conv.last_message_time_formatted || ''}</span>
                    </div>
                    <div class="chat-conversation-preview">
                        <span>${escapeHtml(conv.last_message_text || 'Sem mensagens')}</span>
                        ${statusBadge}
                        ${unreadBadge}
                    </div>
                </div>
            </div>
        `;
        }).join('');

        // Adicionar event listeners para cada conversa
        container.querySelectorAll('.chat-conversation-item').forEach(item => {
            item.addEventListener('click', function() {
                const conversationId = parseInt(this.getAttribute('data-conversation-id'));
                openConversation(conversationId, this);
            });
        });

        // Buscar fotos de perfil apenas dos contatos que não têm cache no banco
        setTimeout(() => {
            // Filtrar apenas conversas que precisam buscar foto
            const needsPhoto = convList.filter(conv =>
                !conv.cached_profile_pic &&
                !conv.profile_picture_url &&
                !conv.profile_pic_url &&
                conv.photo_cache_status !== 'not_found' &&
                conv.photo_cache_status !== 'found'
            ); // Sem limite - buscar todas

            // Adicionar todas à fila de busca
            needsPhoto.forEach((conv) => {
                if (typeof queuePhotoFetch === 'function') {
                    queuePhotoFetch(conv.contact_number || conv.phone);
                }
            });
        }, 500); // Iniciar mais cedo

        // Pré-carregar mensagens de TODAS as conversas visíveis
        preloadMessages(convList);
    }

    // Pré-carregar mensagens em background para troca rápida
    function preloadMessages(convList) {
        // Carregar todas em paralelo para ser mais rápido
        convList.forEach((conv) => {
            if (!messagesCache[conv.id]) {
                fetch(`api/chat_messages.php?conversation_id=${conv.id}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            messagesCache[conv.id] = data.messages;
                        }
                    })
                    .catch(() => {}); // Ignorar erros silenciosamente
            }
        });
    }

    // Abrir conversa (síncrono para ser instantâneo)
    function openConversation(conversationId, eventTarget = null, forceScroll = true, showLoading = true) {
        currentConversationId = conversationId;
        
        // Limpar busca ao abrir conversa
        const searchInput = document.getElementById('search-conversations');
        if (searchInput && searchInput.value.trim() !== '') {
            searchInput.value = '';
            lastSearchResults = null;
            // Restaurar lista completa de conversas
            renderConversations(conversations);
        }

        // Atualizar UI - Novo Layout
        const noChatSelected = document.getElementById('no-chat-selected');
        const chatArea = document.getElementById('chat-area');

        if (noChatSelected) {
            noChatSelected.style.display = 'none';
        }
        if (chatArea) {
            chatArea.style.display = 'flex';
        }

        // Buscar informações da conversa
        const conv = conversations.find(c => c.id === conversationId);
        if (!conv) {
            console.error('Conversa não encontrada:', conversationId);
            return;
        }

        // Atualizar header do chat com informações do contato
        const contactName = document.getElementById('chat-contact-name');
        const contactPhone = document.getElementById('chat-contact-phone');
        const chatAvatar = document.getElementById('chat-avatar-container');

        if (contactName) {
            contactName.textContent = conv.display_name || conv.contact_name || conv.phone;
        }
        if (contactPhone) {
            contactPhone.textContent = conv.phone || '';
        }

        // Atualizar avatar do header
        if (chatAvatar) {
            // Criar chave única para cache
            const cacheKey = conv.channel_type === 'teams' ? `teams_${conv.id}` : conv.contact_number;

            let photoUrl = conv.profile_picture_url ||
                profilePicturesCache[cacheKey] ||
                conv.cached_profile_pic ||
                conv.profile_pic_url;

            // Adicionar versioning para forçar reload
            if (photoUrl && !photoUrl.includes('?v=')) {
                photoUrl += '?v=' + Date.now();
            }

            const initials = getInitials(conv.display_name);
            if (photoUrl) {
                chatAvatar.innerHTML = `<img src="${photoUrl}" alt="${conv.display_name}" onerror="this.parentElement.innerHTML='<span>${initials}</span>'">`;
            } else {
                chatAvatar.innerHTML = `<span>${initials}</span>`;
            }
        }

        // Marcar conversa como ativa na sidebar
        document.querySelectorAll('.chat-conversation-item').forEach(item => {
            item.classList.remove('active');
        });
        if (eventTarget) {
            eventTarget.classList.add('active');
        } else {
            const activeItem = document.querySelector(`[data-conversation-id="${conversationId}"]`);
            if (activeItem) activeItem.classList.add('active');
        }

        // Se tem cache, mostrar IMEDIATAMENTE enquanto busca atualização
        if (messagesCache[conversationId]) {
            messages = messagesCache[conversationId];
            renderMessages(messages, forceScroll);
        } else if (showLoading) {
            const messagesLoading = document.getElementById('messages-loading');
            if (messagesLoading) messagesLoading.classList.remove('hidden');
        }

        // SEMPRE buscar do servidor para garantir dados atualizados
        fetchMessagesFromServer(conversationId, true, forceScroll);
    }

    // Buscar mensagens do servidor
    async function fetchMessagesFromServer(conversationId, updateUI = true, forceScroll = false) {
        try {
            console.log('[FETCH_MSG] Buscando mensagens para conversa:', conversationId);
            // ⭐ FASE 4: Usando novo sistema de configuração
            const response = await fetch(`${API_CONFIG.getEndpoint('messages')}?conversation_id=${conversationId}&limit=50`);
            console.log('[FETCH_MSG] Resposta recebida, status:', response.status);

            // ✅ Verificar se resposta é válida
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            console.log('[FETCH_MSG] Dados:', data);

            if (data.success) {
                console.log('[FETCH_MSG] Sucesso! Total de mensagens:', data.messages.length);
                console.log('[FETCH_MSG] Total no banco:', data.total);

                // Salvar no cache
                messagesCache[conversationId] = data.messages;

                // Verificar se há mais mensagens antigas
                updateLoadMoreButton(data.messages.length, data.total);

                // Só atualizar UI se ainda estiver na mesma conversa
                if (currentConversationId === conversationId) {
                    messages = data.messages;
                    if (updateUI) {
                        console.log('[FETCH_MSG] Renderizando mensagens...');
                        renderMessages(messages, forceScroll);
                    } else {
                        // Verificar se há novas mensagens
                        const currentCount = messages.length;
                        const newCount = data.messages.length;
                        if (newCount > currentCount) {
                            console.log('[FETCH_MSG] Novas mensagens detectadas, re-renderizando');
                            renderMessages(data.messages, false); // Não forçar scroll em novas mensagens recebidas
                        }
                    }
                } else {
                    console.log('[FETCH_MSG] Conversa mudou, não atualizando UI');
                }
            } else {
                console.error('[FETCH_MSG] Erro na resposta:', data.error);
                // ✅ Só mostrar erro se updateUI for true (não durante polling)
                if (updateUI) {
                    showError('Erro ao carregar mensagens: ' + (data.error || 'Erro desconhecido'));
                }
            }
        } catch (error) {
            console.error('[FETCH_MSG] Exceção:', error);
            // ✅ Só mostrar erro se updateUI for true (não durante polling)
            if (updateUI) {
                showError('Erro ao conectar com o servidor');
            }
        }
    }

    // Carregar mensagens mais antigas
    async function loadMoreOlderMessages() {
        if (!currentConversationId) {
            showError('Nenhuma conversa selecionada');
            return;
        }

        const button = document.querySelector('#load-more-messages button');
        const buttonText = document.getElementById('load-more-text');
        const originalText = buttonText.textContent;

        try {
            // Desabilitar botão e mostrar loading
            button.disabled = true;
            buttonText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Carregando...';

            console.log('[LOAD_MORE] Carregando mais mensagens antigas...');
            console.log('[LOAD_MORE] Mensagens atuais:', messages.length);

            // Buscar ID da mensagem mais antiga
            const oldestMessageId = messages.length > 0 ? Math.min(...messages.map(m => m.id)) : 0;
            console.log('[LOAD_MORE] ID da mensagem mais antiga:', oldestMessageId);

            // Buscar mais 50 mensagens antes da mais antiga
            // ⭐ FASE 4: Usando novo sistema de configuração
            const response = await fetch(`${API_CONFIG.getEndpoint('messages')}?conversation_id=${currentConversationId}&limit=50&before_id=${oldestMessageId}`);
            const data = await response.json();

            if (data.success && data.messages.length > 0) {
                console.log('[LOAD_MORE] Carregadas', data.messages.length, 'mensagens antigas');

                // Salvar posição de scroll atual
                const container = document.getElementById('chat-messages-container');
                const scrollHeightBefore = container.scrollHeight;
                const scrollTopBefore = container.scrollTop;

                // Adicionar mensagens antigas no início do array
                messages = [...data.messages, ...messages];
                messagesCache[currentConversationId] = messages;

                // Re-renderizar todas as mensagens
                renderMessages(messages, false);

                // Restaurar posição de scroll (manter usuário na mesma posição visual)
                setTimeout(() => {
                    const scrollHeightAfter = container.scrollHeight;
                    const scrollDiff = scrollHeightAfter - scrollHeightBefore;
                    container.scrollTop = scrollTopBefore + scrollDiff;
                    console.log('[LOAD_MORE] Scroll ajustado:', scrollDiff);
                }, 100);

                // Atualizar botão
                updateLoadMoreButton(messages.length, data.total);

                showSuccess(`${data.messages.length} mensagens antigas carregadas`);
            } else {
                console.log('[LOAD_MORE] Nenhuma mensagem antiga encontrada');
                showInfo('Não há mais mensagens antigas');
                document.getElementById('load-more-messages').classList.add('hidden');
            }
        } catch (error) {
            console.error('[LOAD_MORE] Erro:', error);
            showError('Erro ao carregar mensagens antigas');
        } finally {
            // Reabilitar botão
            button.disabled = false;
            buttonText.textContent = originalText;
        }
    }

    // Atualizar botão "Carregar Mais"
    function updateLoadMoreButton(currentCount, totalCount) {
        const loadMoreDiv = document.getElementById('load-more-messages');
        const loadMoreCount = document.getElementById('load-more-count');

        // ✅ Verificar se elementos existem antes de acessar
        if (!loadMoreDiv || !loadMoreCount) {
            console.warn('[LOAD_MORE] Elementos não encontrados no DOM');
            return;
        }

        if (currentCount < totalCount) {
            const remaining = totalCount - currentCount;
            loadMoreDiv.classList.remove('hidden');
            loadMoreCount.textContent = `+${remaining}`;
            console.log('[LOAD_MORE] Botão visível:', remaining, 'mensagens restantes');
        } else {
            loadMoreDiv.classList.add('hidden');
            console.log('[LOAD_MORE] Botão oculto: todas as mensagens carregadas');
        }
    }

    // Renderizar mensagens
    // forceScroll = true força scroll para o final (nova conversa), false = scroll inteligente
    function renderMessages(msgList, forceScroll = false) {
        const container = document.getElementById('chat-messages-container');
        const messagesLoading = document.getElementById('messages-loading');

        if (messagesLoading) messagesLoading.classList.add('hidden');

        if (!container) {
            console.error('Container de mensagens não encontrado');
            return;
        }

        if (msgList.length === 0) {
            container.innerHTML = '<div class="chat-empty-state"><i class="fas fa-comments"></i><p>Nenhuma mensagem ainda</p></div>';
            return;
        }

        // IMPORTANTE: Salvar posição do scroll ANTES de re-renderizar
        const scrollPosBefore = container.scrollTop;
        const scrollHeightBefore = container.scrollHeight;
        const wasAtBottom = (scrollHeightBefore - scrollPosBefore - container.clientHeight) < 150;

        container.innerHTML = msgList.map(msg => {
            const messageClass = msg.from_me ? 'sent' : 'received';
            const attrTextSource = msg.message_text || msg.caption || '';
            const attrSafeText = escapeHtmlAttribute(attrTextSource);
            const attrSafeMediaUrl = escapeHtmlAttribute(msg.media_url || '');
            const attrSafeMediaName = escapeHtmlAttribute(msg.media_filename || '');
            const attrSafeCaption = escapeHtmlAttribute(msg.caption || '');
            const messageTypeAttr = msg.message_type || 'text';

            // Renderizar conteúdo baseado no tipo de mensagem
            let messageContent = '';

            // IMAGEM - mostrar preview
            if (msg.message_type === 'image' && msg.media_url) {
                messageContent = `
                <div class="chat-message-image">
                    <img src="${msg.media_url}" alt="Imagem" 
                         loading="lazy"
                         style="min-height: 150px; object-fit: contain; background: rgba(0,0,0,0.05);"
                         onclick="window.open('${msg.media_url}', '_blank')"
                         onerror="this.onerror=null; this.src=''; this.alt='Imagem não disponível'; this.style.display='none';">
                </div>
                ${msg.caption ? '<p class="chat-message-text">' + escapeHtml(msg.caption) + '</p>' : ''}
            `;
            }
            // STICKER (figurinha) - mostrar como imagem sem fundo
            else if (msg.message_type === 'sticker' && msg.media_url) {
                messageContent = `
                <div class="chat-message-sticker" style="max-width: 180px;">
                    <img src="${msg.media_url}" alt="Sticker" 
                         loading="lazy"
                         style="width: 100%; height: auto; max-height: 180px; object-fit: contain;"
                         onclick="window.open('${msg.media_url}', '_blank')"
                         onerror="this.onerror=null; this.parentElement.innerHTML='<span style=\\'color:#667781;font-size:13px;\\'><i class=\\'fas fa-sticky-note mr-1\\'></i>Figurinha</span>';">
                </div>
            `;
            }
            // DOCUMENTO - mostrar link para download
            else if (msg.message_type === 'document' && msg.media_url) {
                const fileName = msg.media_filename || 'Documento';
                messageContent = `
                <a href="${msg.media_url}" target="_blank" class="flex items-center gap-2 p-2 bg-white/50 rounded-lg hover:bg-white/80">
                    <i class="fas fa-file-alt text-2xl text-blue-500"></i>
                    <div>
                        <p class="font-medium text-sm">${escapeHtml(fileName)}</p>
                        <p class="text-xs opacity-70">${msg.media_size_formatted || 'Documento'}</p>
                    </div>
                </a>
                ${msg.caption ? '<p class="chat-message-text mt-1">' + escapeHtml(msg.caption) + '</p>' : ''}
            `;
            }
            // ÁUDIO - layout exato WhatsApp Web
            else if (msg.message_type === 'audio' && msg.media_url) {
                const audioId = 'audio-' + msg.id;
                messageContent = `
                <div class="chat-message-audio" style="position: relative; max-width: 280px; background: #e5ddd5; border-radius: 7.5px; padding: 6px 12px 6px 8px;">
                    <div class="flex items-center gap-3">
                        <button onclick="toggleAudioPlay('${audioId}')" class="w-8 h-8 flex items-center justify-center bg-green-500 text-white rounded-full hover:bg-green-600 transition-shadow" id="btn-${audioId}" style="box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                            <i class="fas fa-play text-xs" style="margin-left: 2px;"></i>
                        </button>
                        <div class="flex-1 flex items-center gap-2">
                            <div class="flex-1 flex items-center">
                                <!-- Waveform visual -->
                                <div class="flex items-center gap-0.5" style="height: 20px;">
                                    ${Array(15).fill(0).map((_, i) => `
                                        <div style="width: 2px; background: #667781; border-radius: 1px; opacity: ${0.3 + (Math.random() * 0.7)}; height: ${4 + Math.random() * 12}px;"></div>
                                    `).join('')}
                                </div>
                            </div>
                            <div class="flex items-center gap-1">
                                <span id="time-${audioId}" class="text-xs text-gray-600" style="font-family: monospace; min-width: 32px;">0:00</span>
                                <div class="w-1 h-1 bg-gray-400 rounded-full"></div>
                                <span class="text-xs text-gray-500">KB/s</span>
                            </div>
                        </div>
                    </div>
                    <!-- Progress bar sutil -->
                    <div id="progress-container-${audioId}" class="absolute bottom-0 left-0 right-0 h-0.5 bg-gray-300 rounded-full" style="display: none;">
                        <div id="progress-${audioId}" class="h-full bg-green-500 rounded-full transition-all" style="width: 0%"></div>
                    </div>
                    <!-- Audio escondido -->
                    <audio id="${audioId}" class="hidden" preload="none">
                        <source src="${msg.media_url}" type="audio/ogg; codecs=opus">
                        <source src="${msg.media_url}" type="audio/ogg">
                        <source src="${msg.media_url}" type="audio/mpeg">
                        <source src="${msg.media_url}" type="audio/wav">
                    </audio>
                </div>
                <div class="text-xs mt-1 flex gap-2" style="color: #667781;">
                    <a href="${msg.media_url}" download class="hover:underline"><i class="fas fa-download mr-1"></i>Baixar</a>
                    <a href="${msg.media_url}" target="_blank" class="hover:underline"><i class="fas fa-external-link-alt mr-1"></i>Abrir</a>
                </div>
            `;
            }
            // VÍDEO/GIF - solução definitiva: GIF estático, vídeo com ícone genérico
            else if (msg.message_type === 'video' && msg.media_url) {
                // Se for GIF verdadeiro - mantém thumbnail estático
                if (msg.media_mimetype === 'image/gif' || msg.media_url.toLowerCase().endsWith('.gif')) {
                    messageContent = `
                    <div class="chat-message-gif-static" style="position: relative; max-width: 280px; height: 200px; border-radius: 12px; overflow: hidden; background: #1a1a1a; display: flex; align-items: center; justify-content: center;">
                        <img src="${msg.media_url}" 
                             alt="GIF" 
                             style="max-width: 100%; max-height: 100%; object-fit: contain; display: block; cursor: pointer;"
                             loading="lazy"
                             onclick="window.open('${msg.media_url}', '_blank')"
                             onerror="this.onerror=null; this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjgwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDI4MCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyODAiIGhlaWdodD0iMjAwIiBmaWxsPSIjMWEyMjI4Ii8+CjxjaXJjbGUgY3g9IjE0MCIgY3k9IjEwMCIgcj0iMjAiIGZpbGw9IiMzNDM2NDAiLz4KPHBhdGggZD0iTTEzNSA5MEwxNDUgMTAwTDEzNSAxMTBMMTM1IDkwWiIgZmlsbD0iI2ZmZiIvPgo8L3N2Zz4=';">
                        <div style="position: absolute; top: 8px; right: 8px; background: rgba(0,0,0,0.8); color: white; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase;">GIF</div>
                    </div>
                    ${msg.caption ? '<p class="chat-message-text mt-1">' + escapeHtml(msg.caption) + '</p>' : ''}
                    <div class="text-xs mt-1 flex gap-2" style="color: #667781;">
                        <a href="${msg.media_url}" download class="hover:underline"><i class="fas fa-download mr-1"></i>Baixar</a>
                        <a href="${msg.media_url}" target="_blank" class="hover:underline"><i class="fas fa-external-link-alt mr-1"></i>Abrir</a>
                    </div>
                `;
                } else {
                    // Vídeo normal - mostrar com thumbnail real igual WhatsApp
                    messageContent = `
                    <div class="chat-video-container" style="position: relative; max-width: 280px; border-radius: 12px; overflow: hidden; background: #000;">
                        <video 
                            src="${msg.media_url}" 
                            style="width: 100%; max-height: 300px; display: block; object-fit: contain;"
                            preload="metadata"
                            onclick="this.paused ? this.play() : this.pause()"
                            onloadedmetadata="this.currentTime = 0.1;"
                        ></video>
                        <div class="video-play-overlay" onclick="this.style.display='none'; this.previousElementSibling.play();" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.3); cursor: pointer;">
                            <div style="width: 60px; height: 60px; background: rgba(0,0,0,0.6); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-play" style="color: white; font-size: 24px; margin-left: 4px;"></i>
                            </div>
                        </div>
                        <div style="position: absolute; bottom: 8px; left: 8px; background: rgba(0,0,0,0.6); color: white; padding: 2px 6px; border-radius: 4px; font-size: 11px; display: flex; align-items: center; gap: 4px;">
                            <i class="fas fa-video" style="font-size: 10px;"></i>
                            <span class="video-duration"></span>
                        </div>
                    </div>
                    ${msg.caption ? '<p class="chat-message-text mt-1">' + escapeHtml(msg.caption) + '</p>' : ''}
                `;
                }
            }
            // TEXTO ou outros
            else if (msg.message_text && msg.message_text.trim() !== '' && !msg.message_text.startsWith('[')) {
                messageContent = `<p class="chat-message-text">${escapeHtml(msg.message_text)}</p>`;
            }
            // Fallback para tipos sem URL
            else if (msg.message_type === 'contact') {
                // Extrair nome e telefone do contato da mensagem
                let contactName = 'Contato';
                let contactPhone = '';

                if (msg.message_text) {
                    const text = msg.message_text.replace('[Contato compartilhado]', '').trim();
                    // Formato: "Nome|Telefone"
                    if (text.includes('|')) {
                        const parts = text.split('|');
                        contactName = parts[0].trim() || 'Contato';
                        contactPhone = parts[1].trim();
                    } else {
                        contactName = text || 'Contato';
                    }
                }

                // Usar data attributes ao invés de onclick inline
                const dataAttrs = contactPhone ? `data-contact-phone="${escapeHtmlAttribute(contactPhone)}" data-contact-name="${escapeHtmlAttribute(contactName)}" class="shared-contact-card"` : '';

                messageContent = `
                <div ${dataAttrs} class="flex items-center gap-3 p-4 rounded-lg ${contactPhone ? 'cursor-pointer hover:opacity-90' : ''} transition-opacity" style="background: linear-gradient(135deg, #25d366 0%, #128c7e 100%); max-width: 280px;">
                    <div class="flex-shrink-0 w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-user" style="font-size: 20px; color: white;"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-white text-sm mb-0.5">${escapeHtml(contactName)}</p>
                        <p class="text-xs text-white/80">${contactPhone ? 'Conversar' : 'Contato do WhatsApp'}</p>
                    </div>
                    <div class="flex-shrink-0">
                        <i class="fas fa-chevron-right text-white/60" style="font-size: 12px;"></i>
                    </div>
                </div>`;
            } else if (msg.message_type === 'location') {
                messageContent = `
                <div class="flex items-center gap-3 p-3 bg-white/50 rounded-lg">
                    <div class="flex-shrink-0 w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-map-marker-alt text-red-600"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="chat-message-text font-medium">${escapeHtml(msg.message_text || '[Localização]')}</p>
                        <p class="text-xs text-gray-500">Localização compartilhada</p>
                    </div>
                </div>`;
            } else if (msg.message_type === 'image') {
                messageContent = '<p class="chat-message-text"><i class="fas fa-image mr-2 opacity-50"></i>[Imagem enviada]</p>';
            } else if (msg.message_type === 'sticker') {
                messageContent = '<p class="chat-message-text"><i class="fas fa-sticky-note mr-2 opacity-50"></i>[Figurinha]</p>';
            } else if (msg.message_type === 'document') {
                messageContent = '<p class="chat-message-text"><i class="fas fa-file mr-2 opacity-50"></i>[Documento enviado]</p>';
            } else if (msg.message_type === 'audio') {
                messageContent = '<p class="chat-message-text"><i class="fas fa-microphone mr-2 opacity-50"></i>[Áudio enviado]</p>';
            } else if (msg.caption && msg.caption.trim() !== '') {
                messageContent = `<p class="chat-message-text">${escapeHtml(msg.caption)}</p>`;
            } else {
                messageContent = `<p class="chat-message-text">${escapeHtml(msg.message_text || '[Mensagem]')}</p>`;
            }

            // Adicionar status de leitura para mensagens enviadas (igual WhatsApp)
            let statusIcon = '';
            if (msg.from_me) {
                const status = (msg.status || '').toLowerCase();

                // READ ou PLAYED = lido (✓✓ azul)
                if (msg.read_at || status === 'read' || status === 'played') {
                    statusIcon = '<span class="chat-message-status read">✓✓</span>';
                }
                // DELIVERED = entregue (✓✓ cinza)
                else if (status === 'delivered' || status === 'received') {
                    statusIcon = '<span class="chat-message-status">✓✓</span>';
                }
                // SENT ou SERVER_ACK = enviado (✓ único)
                else if (status === 'sent' || status === 'server_ack') {
                    statusIcon = '<span class="chat-message-status">✓</span>';
                }
                // Pendente (relógio)
                else if (status === 'pending') {
                    statusIcon = '<span class="chat-message-status">🕐</span>';
                }
                // Fallback: se não tem status definido, mostrar enviado
                else {
                    statusIcon = '<span class="chat-message-status">✓</span>';
                }
            }

            // Nome do atendente (apenas para mensagens enviadas)
            const senderName = msg.from_me && msg.sender_name ?
                `<div class="chat-message-sender">${escapeHtml(msg.sender_name)}</div>` : '';

            // Garantir que SEMPRE tenha data e horário (tanto enviadas quanto recebidas)
            let messageTime = '';

            // Usar time_formatted do backend (já vem formatado: "Hoje às 09:45", "Ontem às 14:30", etc)
            if (msg.time_formatted) {
                messageTime = msg.time_formatted;
            }
            // Fallback: formatar a partir de created_at
            else if (msg.created_at) {
                const date = new Date(msg.created_at);
                const today = new Date();
                const yesterday = new Date(today);
                yesterday.setDate(yesterday.getDate() - 1);

                // Verificar se é hoje
                if (date.toDateString() === today.toDateString()) {
                    messageTime = 'Hoje às ' + date.toLocaleTimeString('pt-BR', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                }
                // Verificar se é ontem
                else if (date.toDateString() === yesterday.toDateString()) {
                    messageTime = 'Ontem às ' + date.toLocaleTimeString('pt-BR', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                }
                // Outras datas
                else {
                    messageTime = date.toLocaleDateString('pt-BR', {
                        day: '2-digit',
                        month: '2-digit'
                    }) + ' às ' + date.toLocaleTimeString('pt-BR', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                }
            }
            // Fallback final: timestamp
            else if (msg.timestamp) {
                const date = new Date(msg.timestamp * 1000);
                messageTime = 'Hoje às ' + date.toLocaleTimeString('pt-BR', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }

            return `
            <div class="chat-message ${messageClass}"
                 data-message-id="${msg.id}"
                 data-message-type="${messageTypeAttr}"
                 data-media-url="${attrSafeMediaUrl}"
                 data-media-filename="${attrSafeMediaName}"
                 data-caption="${attrSafeCaption}"
                 data-message-text="${attrSafeText}"
                 data-from-me="${msg.from_me ? '1' : '0'}">
                <div class="chat-message-bubble">
                    ${senderName}
                    ${messageContent}
                    <div class="chat-message-time">
                        <span>${messageTime}</span>
                        ${statusIcon}
                    </div>
                </div>
            </div>
        `;
        }).join('');

        // Processar links do Google Maps em mensagens de texto
        const textMessages = container.querySelectorAll('.chat-message-text');
        textMessages.forEach(textEl => {
            const text = textEl.textContent;
            const mapsRegex = /(https?:\/\/(maps\.app\.goo\.gl|maps\.google\.com|goo\.gl\/maps)[^\s]+)/gi;
            const matches = text.match(mapsRegex);

            if (matches && matches.length > 0) {
                const mapsUrl = matches[0];
                // Extrair coordenadas do URL se possível
                const coordMatch = mapsUrl.match(/[?&]q=([^&]+)/);
                const coords = coordMatch ? decodeURIComponent(coordMatch[1]) : '';

                textEl.parentElement.innerHTML = `
                    <div class="flex flex-col gap-1 max-w-xs">
                        <div class="relative rounded-lg overflow-hidden cursor-pointer" onclick="window.open('${escapeHtmlAttribute(mapsUrl)}', '_blank')" style="background: #e5ddd5;">
                            <img src="https://maps.googleapis.com/maps/api/staticmap?center=${encodeURIComponent(coords || 'location')}&zoom=15&size=300x150&markers=color:red%7C${encodeURIComponent(coords || 'location')}&key=AIzaSyBFw0Qbyq9zTFTd-tUY6dZWTgaQzuU17R8" 
                                 alt="Mapa" 
                                 style="width: 100%; height: 120px; object-fit: cover;"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div style="display: none; width: 100%; height: 120px; align-items: center; justify-content: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <i class="fas fa-map-marked-alt" style="font-size: 48px; color: white; opacity: 0.9;"></i>
                            </div>
                            <div style="position: absolute; bottom: 8px; left: 8px; background: rgba(0,0,0,0.7); color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px;">
                                <i class="fas fa-map-marker-alt mr-1"></i>Localização
                            </div>
                        </div>
                        <a href="${escapeHtmlAttribute(mapsUrl)}" target="_blank" class="text-xs hover:underline break-all px-1" style="color: #25d366;">
                            ${escapeHtml(mapsUrl)}
                        </a>
                    </div>
                `;
            }
        });

        // Prevenir scroll jumping quando imagens/GIFs carregam
        const images = container.querySelectorAll('img');
        images.forEach(img => {
            // Se a imagem ainda não carregou, adicionar handler para estabilizar scroll
            if (!img.complete) {
                const scrollContainer = container;
                const scrollPosBefore = scrollContainer.scrollTop;
                const scrollHeightBefore = scrollContainer.scrollHeight;

                img.addEventListener('load', function() {
                    // Calcular diferença de altura após carregamento
                    const scrollHeightAfter = scrollContainer.scrollHeight;
                    const heightDiff = scrollHeightAfter - scrollHeightBefore;

                    // Se não estiver no final, manter posição de scroll
                    const isAtBottom = (scrollHeightBefore - scrollPosBefore - scrollContainer.clientHeight) < 100;
                    if (!isAtBottom && heightDiff > 0) {
                        scrollContainer.scrollTop = scrollPosBefore + heightDiff;
                    }
                }, {
                    once: true
                });
            }
        });

        // Scroll inteligente - evitar "pulo" ao re-renderizar
        // Se forceScroll = true (nova conversa) -> vai para o final
        // Se estava no final antes -> vai para o final
        // Se estava no meio -> mantém posição relativa
        if (forceScroll || wasAtBottom) {
            // Usar requestAnimationFrame para garantir que o DOM foi atualizado
            requestAnimationFrame(() => {
                container.scrollTop = container.scrollHeight;
            });
        } else {
            // Manter posição relativa do scroll
            requestAnimationFrame(() => {
                const scrollHeightAfter = container.scrollHeight;
                const heightDiff = scrollHeightAfter - scrollHeightBefore;
                container.scrollTop = scrollPosBefore + heightDiff;
            });
        }
        
        // ✅ Configurar event listeners para contatos compartilhados
        setupSharedContactListeners();
    }
    
    /**
     * Configura event listeners para cards de contato compartilhado
     */
    function setupSharedContactListeners() {
        const contactCards = document.querySelectorAll('.shared-contact-card');
        contactCards.forEach(card => {
            // Remover listener antigo se existir
            card.replaceWith(card.cloneNode(true));
        });
        
        // Adicionar novos listeners
        document.querySelectorAll('.shared-contact-card').forEach(card => {
            card.addEventListener('click', function() {
                const phone = this.getAttribute('data-contact-phone');
                const name = this.getAttribute('data-contact-name');
                
                if (phone && name) {
                    openContactConversation(phone, name);
                }
            });
        });
    }

    /**
     * Adiciona uma mensagem diretamente ao chat sem recarregar todas
     * @param {Object} message - Objeto da mensagem retornado pela API
     */
    function appendMessageToChat(message) {
        if (!message || !message.id) {
            console.error('[APPEND_MSG] Mensagem inválida:', message);
            return false;
        }

        const container = document.getElementById('chat-messages-container');
        if (!container) {
            console.error('[APPEND_MSG] Container não encontrado');
            return false;
        }

        console.log('[APPEND_MSG] Adicionando mensagem:', message.id, message.message_type);

        // Verificar se mensagem já existe (evitar duplicatas)
        const existingMsg = container.querySelector(`[data-message-id="${message.id}"]`);
        if (existingMsg) {
            console.log('[APPEND_MSG] Mensagem já existe, ignorando');
            return false;
        }

        // Renderizar HTML da mensagem usando a mesma lógica de renderMessages()
        const messageHtml = renderSingleMessage(message);

        // Adicionar ao final do container
        container.insertAdjacentHTML('beforeend', messageHtml);
        
        // ✅ Configurar event listener se for contato compartilhado
        setupSharedContactListeners();

        // Scroll para o final
        setTimeout(() => {
            container.scrollTop = container.scrollHeight;
        }, 100);

        // ✅ CRÍTICO: Atualizar AMBOS os arrays (cache E global)
        // Isso garante que a mensagem não desapareça durante auto-refresh
        if (currentConversationId) {
            // Atualizar cache de mensagens
            if (messagesCache[currentConversationId]) {
                messagesCache[currentConversationId].push(message);
            } else {
                messagesCache[currentConversationId] = [message];
            }

            // ✅ CORREÇÃO: Também atualizar array global de mensagens
            // Isso é ESSENCIAL para evitar que a mensagem desapareça
            messages.push(message);
        }

        console.log('[APPEND_MSG] ✅ Mensagem adicionada com sucesso');
        console.log('[APPEND_MSG] Total de mensagens no cache:', messagesCache[currentConversationId]?.length);
        console.log('[APPEND_MSG] Total de mensagens no array global:', messages.length);
        return true;
    }

    /**
     * Renderiza HTML de uma única mensagem
     * @param {Object} msg - Objeto da mensagem
     * @returns {string} HTML da mensagem
     */
    function renderSingleMessage(msg) {
        const messageClass = msg.from_me ? 'sent' : 'received';
        const attrTextSource = msg.message_text || msg.caption || '';
        const attrSafeText = escapeHtmlAttribute(attrTextSource);
        const attrSafeMediaUrl = escapeHtmlAttribute(msg.media_url || '');
        const attrSafeMediaName = escapeHtmlAttribute(msg.media_filename || msg.file_name || '');
        const attrSafeCaption = escapeHtmlAttribute(msg.caption || '');
        const messageTypeAttr = msg.message_type || 'text';

        // Renderizar conteúdo baseado no tipo de mensagem
        let messageContent = '';

        // IMAGEM
        if (msg.message_type === 'image' && msg.media_url) {
            messageContent = `
            <div class="chat-message-image">
                <img src="${msg.media_url}" alt="Imagem" 
                     loading="lazy"
                     style="min-height: 150px; object-fit: contain; background: rgba(0,0,0,0.05);"
                     onclick="window.open('${msg.media_url}', '_blank')"
                     onerror="this.onerror=null; this.src=''; this.alt='Imagem não disponível'; this.style.display='none';">
            </div>
            ${msg.caption ? '<p class="chat-message-text">' + escapeHtml(msg.caption) + '</p>' : ''}
        `;
        }
        // DOCUMENTO
        else if (msg.message_type === 'document' && msg.media_url) {
            const fileName = msg.media_filename || msg.file_name || 'Documento';
            const fileSize = msg.media_size_formatted || formatFileSize(msg.media_size || msg.file_size);
            messageContent = `
            <a href="${msg.media_url}" target="_blank" class="flex items-center gap-2 p-2 bg-white/50 rounded-lg hover:bg-white/80">
                <i class="fas fa-file-alt text-2xl text-blue-500"></i>
                <div>
                    <p class="font-medium text-sm">${escapeHtml(fileName)}</p>
                    <p class="text-xs opacity-70">${fileSize}</p>
                </div>
            </a>
            ${msg.caption ? '<p class="chat-message-text mt-1">' + escapeHtml(msg.caption) + '</p>' : ''}
        `;
        }
        // ÁUDIO
        else if (msg.message_type === 'audio' && msg.media_url) {
            const audioId = 'audio-' + msg.id;
            messageContent = `
            <div class="chat-message-audio" style="position: relative; max-width: 280px; background: #e5ddd5; border-radius: 7.5px; padding: 6px 12px 6px 8px;">
                <div class="flex items-center gap-3">
                    <button onclick="toggleAudioPlay('${audioId}')" class="w-8 h-8 flex items-center justify-center bg-green-500 text-white rounded-full hover:bg-green-600 transition-shadow" id="btn-${audioId}" style="box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                        <i class="fas fa-play text-xs" style="margin-left: 2px;"></i>
                    </button>
                    <div class="flex-1 flex items-center gap-2">
                        <div class="flex-1 flex items-center">
                            <div class="flex items-center gap-0.5" style="height: 20px;">
                                ${Array(15).fill(0).map((_, i) => `
                                    <div style="width: 2px; background: #667781; border-radius: 1px; opacity: ${0.3 + (Math.random() * 0.7)}; height: ${4 + Math.random() * 12}px;"></div>
                                `).join('')}
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <span id="time-${audioId}" class="text-xs text-gray-600" style="font-family: monospace; min-width: 32px;">0:00</span>
                            <div class="w-1 h-1 bg-gray-400 rounded-full"></div>
                            <span class="text-xs text-gray-500">KB/s</span>
                        </div>
                    </div>
                </div>
                <audio id="${audioId}" class="hidden" preload="none">
                    <source src="${msg.media_url}" type="audio/ogg; codecs=opus">
                    <source src="${msg.media_url}" type="audio/ogg">
                    <source src="${msg.media_url}" type="audio/mpeg">
                    <source src="${msg.media_url}" type="audio/wav">
                </audio>
            </div>
            <div class="text-xs mt-1 flex gap-2" style="color: #667781;">
                <a href="${msg.media_url}" download class="hover:underline"><i class="fas fa-download mr-1"></i>Baixar</a>
                <a href="${msg.media_url}" target="_blank" class="hover:underline"><i class="fas fa-external-link-alt mr-1"></i>Abrir</a>
            </div>
        `;
        }
        // VÍDEO/GIF
        else if (msg.message_type === 'video' && msg.media_url) {
            if (msg.media_mimetype === 'image/gif' || msg.media_url.toLowerCase().endsWith('.gif')) {
                messageContent = `
                <div class="chat-message-gif-static" style="position: relative; max-width: 280px; height: 200px; border-radius: 12px; overflow: hidden; background: #1a1a1a; display: flex; align-items: center; justify-content: center;">
                    <img src="${msg.media_url}" alt="GIF" 
                         style="max-width: 100%; max-height: 100%; object-fit: contain; display: block; cursor: pointer;"
                         loading="lazy"
                         onclick="window.open('${msg.media_url}', '_blank')">
                    <div style="position: absolute; top: 8px; right: 8px; background: rgba(0,0,0,0.8); color: white; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">GIF</div>
                </div>
                ${msg.caption ? '<p class="chat-message-text mt-1">' + escapeHtml(msg.caption) + '</p>' : ''}
            `;
            } else {
                messageContent = `
                <div class="chat-video-container" style="position: relative; max-width: 280px; border-radius: 12px; overflow: hidden; background: #000;">
                    <video src="${msg.media_url}" style="width: 100%; max-height: 300px; display: block; object-fit: contain;" preload="metadata" onclick="this.paused ? this.play() : this.pause()"></video>
                    <div class="video-play-overlay" onclick="this.style.display='none'; this.previousElementSibling.play();" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.3); cursor: pointer;">
                        <div style="width: 60px; height: 60px; background: rgba(0,0,0,0.6); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-play" style="color: white; font-size: 24px; margin-left: 4px;"></i>
                        </div>
                    </div>
                </div>
                ${msg.caption ? '<p class="chat-message-text mt-1">' + escapeHtml(msg.caption) + '</p>' : ''}
            `;
            }
        }
        // TEXTO
        else if (msg.message_text && msg.message_text.trim() !== '') {
            messageContent = `<p class="chat-message-text">${escapeHtml(msg.message_text)}</p>`;
        }

        // Status de leitura para mensagens enviadas
        let statusIcon = '';
        if (msg.from_me) {
            statusIcon = '<span class="chat-message-status">✓</span>';
        }

        // Timestamp
        const timeFormatted = msg.time_formatted || new Date().toLocaleTimeString('pt-BR', {
            hour: '2-digit',
            minute: '2-digit'
        });

        return `
            <div class="chat-message ${messageClass}" 
                 data-message-id="${msg.id}"
                 data-message-type="${messageTypeAttr}"
                 data-media-url="${attrSafeMediaUrl}"
                 data-media-filename="${attrSafeMediaName}"
                 data-caption="${attrSafeCaption}"
                 data-message-text="${attrSafeText}"
                 data-from-me="${msg.from_me ? '1' : '0'}">
                <div class="chat-message-bubble">
                    ${messageContent}
                    <div class="chat-message-time">
                        <span>${timeFormatted}</span>
                        ${statusIcon}
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Formatar tamanho de arquivo
     */
    function formatFileSize(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    /**
     * Handler para seleção de arquivos (imagem, documento, áudio)
     * Chamado pelos inputs: image-input, document-input, audio-input
     */
    async function handleFileSelect(input, mediaType) {
        if (!input.files || input.files.length === 0) {
            return;
        }

        // ✅ DEBUG: Log detalhado do currentConversationId
        console.log('[FILE_SELECT] DEBUG - currentConversationId:', currentConversationId);
        console.log('[FILE_SELECT] DEBUG - typeof:', typeof currentConversationId);
        console.log('[FILE_SELECT] DEBUG - is null?', currentConversationId === null);
        console.log('[FILE_SELECT] DEBUG - is undefined?', currentConversationId === undefined);

        if (!currentConversationId) {
            console.error('[FILE_SELECT] ERRO - currentConversationId está vazio!');
            showError('Selecione uma conversa primeiro');
            input.value = '';
            return;
        }

        const file = input.files[0];
        console.log('[FILE_SELECT] Arquivo selecionado:', file.name, file.type, file.size);
        console.log('[FILE_SELECT] Enviando para conversa ID:', currentConversationId);

        // Validar tamanho
        const maxSizes = {
            'image': 5 * 1024 * 1024, // 5MB
            'audio': 16 * 1024 * 1024, // 16MB
            'document': 100 * 1024 * 1024 // 100MB
        };

        if (file.size > (maxSizes[mediaType] || maxSizes['document'])) {
            showError(`Arquivo muito grande. Máximo: ${formatFileSize(maxSizes[mediaType])}`);
            input.value = '';
            return;
        }

        // Mostrar loading
        const typeLabels = {
            'image': 'Enviando imagem...',
            'audio': 'Enviando audio...',
            'document': 'Enviando documento...'
        };
        showSuccess(typeLabels[mediaType] || 'Enviando arquivo...');

        try {
            // ✅ GARANTIR que conversation_id é um número válido
            const conversationIdToSend = parseInt(currentConversationId, 10);
            
            if (isNaN(conversationIdToSend) || conversationIdToSend <= 0) {
                throw new Error('ID da conversa inválido: ' + currentConversationId);
            }
            
            console.log('[FILE_SELECT] Conversation ID validado:', conversationIdToSend);

            // Preparar FormData
            const formData = new FormData();
            formData.append('conversation_id', conversationIdToSend);
            formData.append('file', file);
            formData.append('media_type', mediaType);
            formData.append('type', mediaType);
            
            // ✅ DEBUG: Verificar FormData
            console.log('[FILE_SELECT] FormData preparado:');
            for (let pair of formData.entries()) {
                console.log('  -', pair[0], ':', pair[1]);
            }

            // Enviar para API
            const response = await fetch('api/send_media.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            console.log('[FILE_SELECT] Resposta da API:', data);

            if (data.success) {
                const successLabel = (typeLabels[mediaType] || 'Enviando arquivo...').replace('Enviando', 'Enviado');
                showSuccess(`${successLabel}!`);

                // ✅ CORREÇÃO: Adicionar mensagem diretamente ao chat
                if (data.message) {
                    const added = appendMessageToChat(data.message);
                    if (!added) {
                        // Fallback: se não conseguiu adicionar, recarregar
                        console.log('[FILE_SELECT] Fallback: recarregando mensagens');
                        await fetchMessagesFromServer(currentConversationId, true, false);
                    }
                } else {
                    // Fallback: se não retornou mensagem, recarregar
                    console.log('[FILE_SELECT] Sem mensagem retornada, recarregando');
                    await fetchMessagesFromServer(currentConversationId, true, false);
                }
            } else {
                showError('Erro: ' + (data.error || 'Erro desconhecido'));
            }
        } catch (error) {
            console.error('[FILE_SELECT] Erro:', error);
            showError('Erro ao enviar arquivo');
        } finally {
            // Limpar input
            input.value = '';
        }
    }

    // Atualizar mensagens
    async function refreshMessages(showFeedback = true) {
        if (!currentConversationId) return;

        if (showFeedback) {
            showSuccess('Atualizando...');
        }

        // false, false = não mostrar loading, não forçar scroll (respeitar posição do usuário)
        await fetchMessagesFromServer(currentConversationId, false, false);
        await loadConversations(); // Atualizar lista também
    }

    // Abrir conversa com contato compartilhado
    window.openContactConversation = async function(phone, name) {
        try {
            // Verificar se já existe uma conversa com este número
            const existingConv = conversations.find(conv => conv.phone === phone);

            if (existingConv) {
                // Se já existe, abrir a conversa
                await fetchMessagesFromServer(existingConv.id, true, true);
                showSuccess(`Abrindo conversa com ${name}`);
            } else {
                // Se não existe, criar nova conversa
                showSuccess(`Iniciando conversa com ${name}...`);

                const response = await fetch('api/chat_conversations.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        phone: phone,
                        contact_name: name
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Recarregar lista de conversas
                    await loadConversations();
                    // Aguardar um pouco para garantir que as conversas foram renderizadas
                    setTimeout(async () => {
                        await fetchMessagesFromServer(data.conversation_id, true, true);
                        showSuccess(`Conversa iniciada com ${name}`);
                    }, 100);
                } else {
                    showError('Erro ao criar conversa: ' + (data.error || 'Erro desconhecido'));
                }
            }
        } catch (error) {
            console.error('Erro ao abrir conversa:', error);
            showError('Erro ao abrir conversa com o contato');
        }
    }

    // Função auxiliar para formatar tamanho de arquivo
    function formatFileSize(bytes) {
        if (!bytes) return '';
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    // Configurar envio de mensagem
    function setupSendMessage() {
        console.log('[SETUP] setupSendMessage() chamado');
        const form = document.getElementById('send-message-form');
        if (!form) {
            console.error('[SETUP] Formulário send-message-form NÃO encontrado!');
            return;
        }
        console.log('[SETUP] Formulário encontrado, registrando event listener');

        form.addEventListener('submit', async function(e) {
            console.log('[SEND] Event submit disparado');
            e.preventDefault();

            console.log('[SEND] currentConversationId:', currentConversationId);
            if (!currentConversationId) {
                console.error('[SEND] currentConversationId está NULL/undefined');
                showError('Selecione uma conversa primeiro');
                return;
            }

            const input = document.getElementById('message-input');
            const message = input.value.trim();
            console.log('[SEND] Mensagem:', message);

            if (!message) {
                console.log('[SEND] Mensagem vazia, abortando');
                return;
            }

            const sendButton = document.getElementById('send-button');

            // Limpar input imediatamente (UX instantânea)
            input.value = '';

            // Adicionar mensagem temporária na UI (Optimistic UI)
            const tempId = 'temp_' + Date.now();
            const replySnapshot = currentReplyMessage ? {
                ...currentReplyMessage
            } : null;
            console.log('[SEND] Adicionando mensagem temporária, ID:', tempId);
            addTemporaryMessage(tempId, message, replySnapshot);

            // Desabilitar botão brevemente
            sendButton.disabled = true;

            try {
                const payload = {
                    conversation_id: currentConversationId,
                    message: message
                };
                if (currentReplyMessage?.id) {
                    payload.quoted_message_id = currentReplyMessage.id;
                }

                console.log('[SEND] Enviando requisição para API:', payload);
                // ⭐ FASE 4: Usando novo sistema de configuração
                const response = await API_CONFIG.request('send', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                console.log('[SEND] Resposta recebida, status:', response.status);
                const data = await response.json();
                console.log('[SEND] Dados da resposta:', data);

                if (data.success) {
                    console.log('[SEND] Mensagem enviada com sucesso, substituindo temporária pela real');
                    console.log('[SEND] Dados da mensagem retornada:', data.message);

                    // Remover mensagem temporária
                    const tempMsg = document.getElementById(tempId);
                    if (tempMsg) {
                        tempMsg.remove();
                    }

                    // Adicionar mensagem real retornada pela API (com dados completos)
                    if (data.message) {
                        const container = document.getElementById('chat-messages-container');
                        if (container && messages) {
                            // Marcar timestamp de envio
                            window.lastMessageSentTime = Date.now();
                            console.log('[SEND] Timestamp de envio registrado:', window.lastMessageSentTime);

                            // ✅ VERIFICAR SE MENSAGEM JÁ EXISTE (evitar duplicação)
                            const messageExists = messages.some(m => m.id === data.message.id);
                            const messageInDOM = document.querySelector(`[data-message-id="${data.message.id}"]`);

                            if (messageExists || messageInDOM) {
                                console.log('[SEND] Mensagem já existe, não adicionando novamente');
                            } else {
                                // Adicionar ao array de mensagens
                                messages.push(data.message);

                                // DEBUG: Verificar dados da mensagem
                                console.log('[RENDER] Renderizando mensagem:', {
                                    id: data.message.id,
                                    message_type: data.message.message_type,
                                    media_url: data.message.media_url,
                                    message_text: data.message.message_text,
                                    file_name: data.message.file_name
                                });

                                // Renderizar apenas a nova mensagem
                                const messageHtml = renderSingleMessage(data.message);
                                console.log('[RENDER] HTML gerado:', messageHtml.substring(0, 200) + '...');
                                container.insertAdjacentHTML('beforeend', messageHtml);
                                scrollToBottom(true);
                            }
                        }
                    }

                    // Atualizar lista de conversas
                    loadConversations();
                    clearReplyContext();
                } else {
                    // Marcar mensagem como erro
                    markMessageError(tempId, data.error || 'Erro ao enviar');
                    showError('Erro ao enviar: ' + (data.error || 'Erro desconhecido'));
                }
            } catch (error) {
                console.error('Erro:', error);
                markMessageError(tempId, 'Erro de conexão');
                showError('Erro ao enviar mensagem');
            } finally {
                sendButton.disabled = false;
            }
        });
    }

    // Adicionar mensagem temporária na UI (Optimistic UI)
    function addTemporaryMessage(tempId, message, replyMessage = null) {
        const container = document.getElementById('chat-messages-container');
        if (!container) return;

        const now = new Date();
        const timeStr = now.toLocaleTimeString('pt-BR', {
            hour: '2-digit',
            minute: '2-digit'
        });

        // Incluir nome do atendente
        const senderNameHtml = currentUserName ?
            `<div class="chat-message-sender">${escapeHtml(currentUserName)}</div>` : '';
        const replyHtml = replyMessage ? `
        <div class="chat-reply-tag">
            <span class="chat-reply-author">${replyMessage.fromMe ? 'Você' : 'Contato'}</span>
            <p class="chat-reply-text">${escapeHtml(replyMessage.text || '[Mensagem]')}</p>
        </div>
    ` : '';

        const messageHtml = `
        <div id="${tempId}" class="chat-message sent temp-message">
            <div class="chat-message-bubble">
                ${senderNameHtml}
                ${replyHtml}
                <p class="chat-message-text">${escapeHtml(message)}</p>
                <div class="chat-message-time">
                    <span>${timeStr}</span>
                    <i class="fas fa-clock sending-indicator" style="font-size: 10px;"></i>
                </div>
            </div>
        </div>
    `;

        container.insertAdjacentHTML('beforeend', messageHtml);
        scrollToBottom(true); // true = forçar scroll quando USUÁRIO envia mensagem
    }

    // Confirmar mensagem enviada com sucesso
    function confirmTemporaryMessage(tempId) {
        const tempMsg = document.getElementById(tempId);
        if (tempMsg) {
            const indicator = tempMsg.querySelector('.sending-indicator');
            if (indicator) {
                indicator.classList.remove('fa-clock');
                indicator.classList.add('fa-check');
            }
            tempMsg.classList.remove('temp-message');

            // Agendar remoção da mensagem temporária e atualização suave após 2 segundos
            setTimeout(() => {
                if (tempMsg && tempMsg.parentNode) {
                    // Buscar mensagens atualizadas do servidor
                    if (currentConversationId) {
                        fetchMessagesFromServer(currentConversationId, true, false);
                    }
                }
            }, 2000);
        }
    }

    // Marcar mensagem com erro
    function markMessageError(tempId, errorMsg) {
        const tempMsg = document.getElementById(tempId);
        if (tempMsg) {
            const indicator = tempMsg.querySelector('.sending-indicator');
            if (indicator) {
                indicator.classList.remove('fa-clock');
                indicator.classList.add('fa-exclamation-circle', 'text-red-400');
                indicator.title = errorMsg;
            }
            tempMsg.classList.add('message-error');
        }
    }

    // Buscar conversas e contatos
    let lastSearchResults = null; // Cache dos últimos resultados de busca

    function setupSearchConversations() {
        const searchInput = document.getElementById('search-conversations');
        let searchTimeout;
        let isSearching = false;

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.toLowerCase().trim();

            // Se o campo está vazio, mostrar todas as conversas
            if (query === '') {
                lastSearchResults = null;
                renderConversations(conversations);
                return;
            }

            // Debounce maior (500ms) para dar tempo de digitar
            searchTimeout = setTimeout(async () => {
                if (isSearching) return;
                isSearching = true;

                // Filtrar conversas existentes
                const filteredConversations = conversations.filter(conv =>
                    (conv.display_name && conv.display_name.toLowerCase().includes(query)) ||
                    (conv.phone && conv.phone.includes(query))
                );

                // Se tiver query, buscar também nos contatos salvos
                if (query.length >= 2) {
                    try {
                        const response = await fetch(`/api/search_contacts.php?q=${encodeURIComponent(query)}`);
                        const data = await response.json();

                        if (data.success && data.contacts && data.contacts.length > 0) {
                            // Filtrar contatos que JÁ têm conversa
                            const existingPhones = new Set(conversations.map(c => (c.phone || '').replace(/\D/g, '')));

                            const newContacts = data.contacts.filter(contact => {
                                const cleanPhone = (contact.phone || '').replace(/\D/g, '');
                                return !existingPhones.has(cleanPhone);
                            });

                            // Salvar resultados e renderizar
                            lastSearchResults = {
                                convs: filteredConversations,
                                contacts: newContacts
                            };
                            renderConversationsWithSuggestions(filteredConversations, newContacts);
                            isSearching = false;
                            return;
                        }
                    } catch (error) {
                        console.error('Erro ao buscar contatos:', error);
                    }
                }

                // Renderizar apenas conversas
                lastSearchResults = {
                    convs: filteredConversations,
                    contacts: []
                };
                renderConversations(filteredConversations);
                isSearching = false;
            }, 500); // Aumentado para 500ms
        });

        // Ao perder foco, manter os resultados visíveis por um momento
        searchInput.addEventListener('blur', function() {
            // Pequeno delay para permitir clique nos resultados
            setTimeout(() => {
                // Se o campo ainda está vazio após perder foco, restaurar lista completa
                if (this.value.trim() === '' && lastSearchResults === null) {
                    renderConversations(conversations);
                }
            }, 200);
        });
    }

    // Renderizar conversas com sugestões de contatos
    function renderConversationsWithSuggestions(convs, contacts) {
        const container = document.getElementById('conversations-container');

        // Renderizar conversas (mesma lógica de renderConversations)
        let html = convs.map(conv => {
            // Criar chave única para cache baseada no canal e ID da conversa
            // Para Teams: usar ID da conversa (único)
            // Para WhatsApp/Email: usar contact_number (compatibilidade)
            const cacheKey = conv.channel_type === 'teams' ? `teams_${conv.id}` : conv.contact_number;

            // Prioridade de foto: cache local > cache do banco > profile_pic_url
            let photoUrl = profilePicturesCache[cacheKey] ||
                conv.cached_profile_pic ||
                conv.profile_picture_url ||
                conv.profile_pic_url;

            // Adicionar versioning para forçar reload
            if (photoUrl && !photoUrl.includes('?v=')) {
                photoUrl += '?v=' + Date.now();
            }

            const initials = getInitials(conv.display_name);
            const unreadBadge = conv.unread_count > 0 ?
                `<span class="chat-unread-count">${conv.unread_count}</span>` :
                '';

            // Avatar com foto ou iniciais
            const avatarContent = photoUrl ?
                `<img src="${photoUrl}" alt="${escapeHtml(conv.display_name)}" onerror="this.parentElement.innerHTML='<span>${initials}</span>'">` :
                `<span>${initials}</span>`;

            // Badge de status/setor
            let statusBadge = '';
            if (conv.status === 'resolved') {
                statusBadge = '<span class="chat-conversation-badge geral">Resolvido</span>';
            } else if (conv.department_name) {
                statusBadge = `<span class="chat-conversation-badge geral">${escapeHtml(conv.department_name)}</span>`;
            }

            return `
            <div class="chat-conversation-item ${conv.id === currentConversationId ? 'active' : ''}" 
                 data-conversation-id="${conv.id}"
                 data-phone="${conv.contact_number || conv.phone}">
                <div class="chat-conversation-avatar">
                    ${avatarContent}
                </div>
                <div class="chat-conversation-info">
                    <div class="chat-conversation-header">
                        <span class="chat-conversation-name">${escapeHtml(conv.display_name)}</span>
                        <span class="chat-conversation-time">${conv.last_message_time_formatted || ''}</span>
                    </div>
                    <div class="chat-conversation-preview">
                        <span>${escapeHtml(conv.last_message_text || 'Sem mensagens')}</span>
                        ${statusBadge}
                        ${unreadBadge}
                    </div>
                </div>
            </div>
        `;
        }).join('');

        // Adicionar seção de contatos sugeridos se houver
        if (contacts && contacts.length > 0) {
            html += `
            <div class="contacts-suggestions-header">
                <i class="fas fa-address-book"></i>
                <span>Contatos Salvos</span>
            </div>
        `;

            contacts.forEach(contact => {
                const initials = getInitials(contact.name || contact.phone);
                html += `
                <div class="chat-conversation-item contact-suggestion" data-contact-phone="${escapeHtmlAttribute(contact.phone)}" data-contact-name="${escapeHtmlAttribute(contact.name || '')}">
                    <div class="chat-conversation-avatar">
                        ${contact.profile_picture_url 
                            ? `<img src="${contact.profile_picture_url}" alt="Avatar" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                               <span style="display:none">${initials}</span>`
                            : `<span>${initials}</span>`
                        }
                    </div>
                    <div class="chat-conversation-content">
                        <div class="chat-conversation-name">
                            ${escapeHtml(contact.name || contact.phone)}
                            <span class="contact-badge"><i class="fas fa-user-plus"></i></span>
                        </div>
                        <div class="chat-conversation-preview">${escapeHtml(contact.phone)}</div>
                    </div>
                </div>
            `;
            });
        }

        container.innerHTML = html;

        // Adicionar event listeners para conversas existentes
        container.querySelectorAll('.chat-conversation-item:not(.contact-suggestion)').forEach(item => {
            item.addEventListener('click', function() {
                const conversationId = parseInt(this.getAttribute('data-conversation-id'));
                
                // Limpar busca ao clicar em conversa
                const searchInput = document.getElementById('search-conversations');
                if (searchInput) {
                    searchInput.value = '';
                    lastSearchResults = null;
                }
                
                openConversation(conversationId, this);
            });
        });
        
        // Adicionar event listeners para contatos sugeridos (SEM conversa)
        container.querySelectorAll('.contact-suggestion').forEach(item => {
            item.addEventListener('click', function() {
                const phone = this.getAttribute('data-contact-phone');
                const name = this.getAttribute('data-contact-name');
                startChatWithContact(phone, name);
            });
        });

        // Atualizar contador
        const countText = document.getElementById('conversations-count-text');
        if (countText) {
            const totalCount = convs.length + (contacts ? contacts.length : 0);
            countText.textContent = `Mostrando ${totalCount} resultados`;
        }
    }

    // Iniciar chat com contato da sugestão
    async function startChatWithContact(phone, name) {
        console.log('[startChatWithContact] Iniciando com:', { phone, name });
        
        try {
            // Validar telefone
            if (!phone || phone.trim() === '') {
                showError('Número de telefone inválido');
                return;
            }
            
            // Verificar se já existe conversa com este número
            const cleanPhone = phone.replace(/\D/g, '');
            const existingConv = conversations.find(c => {
                const convPhone = (c.phone || '').replace(/\D/g, '');
                return convPhone === cleanPhone || 
                       convPhone === '55' + cleanPhone || 
                       '55' + convPhone === cleanPhone;
            });
            
            if (existingConv) {
                console.log('[startChatWithContact] Conversa já existe, abrindo:', existingConv.id);
                // Limpar busca
                const searchInput = document.getElementById('search-conversations');
                if (searchInput) {
                    searchInput.value = '';
                    lastSearchResults = null;
                }
                renderConversations(conversations);
                openConversation(existingConv.id);
                return;
            }
            
            console.log('[startChatWithContact] Criando nova conversa...');
            
            const response = await fetch('/api/chat_conversations.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    phone: phone.trim(),
                    contact_name: name ? name.trim() : ''
                })
            });

            const data = await response.json();
            
            console.log('[startChatWithContact] Resposta da API:', data);

            if (data.success && data.conversation_id) {
                console.log('[startChatWithContact] Conversa criada com ID:', data.conversation_id);
                
                // Se a API retornou os dados da conversa, adicionar diretamente na lista
                if (data.conversation) {
                    console.log('[startChatWithContact] Adicionando conversa diretamente na lista');
                    
                    // Formatar a conversa para o formato esperado
                    const newConv = {
                        id: data.conversation_id,
                        phone: data.conversation.phone,
                        contact_name: data.conversation.contact_name,
                        display_name: data.conversation.contact_name || data.conversation.phone,
                        last_message_text: data.conversation.last_message_text || 'Conversa iniciada',
                        last_message_time: data.conversation.last_message_time,
                        last_message_time_formatted: 'Agora',
                        unread_count: 0,
                        is_pinned: false,
                        is_archived: false,
                        status: data.conversation.status || 'open',
                        channel_type: 'whatsapp',
                        channel_name: 'WhatsApp',
                        profile_picture_url: data.conversation.profile_picture_url || null,
                        owner_user_id: data.conversation.user_id
                    };
                    
                    // Adicionar no início da lista (topo)
                    conversations.unshift(newConv);
                    
                    // Limpar busca
                    const searchInput = document.getElementById('search-conversations');
                    if (searchInput) {
                        searchInput.value = '';
                        lastSearchResults = null;
                    }
                    
                    // Re-renderizar lista
                    renderConversations(conversations);
                    
                    // Abrir conversa
                    console.log('[startChatWithContact] Abrindo conversa criada');
                    openConversation(data.conversation_id);
                } else {
                    // Fallback: buscar a conversa específica da API
                    console.log('[startChatWithContact] Buscando conversa específica da API');
                    
                    const convResponse = await fetch(`/api/chat_conversations.php?conversation_id=${data.conversation_id}`);
                    const convData = await convResponse.json();
                    
                    if (convData.success && convData.conversation) {
                        // Adicionar no início da lista
                        conversations.unshift(convData.conversation);
                        
                        // Limpar busca
                        const searchInput = document.getElementById('search-conversations');
                        if (searchInput) {
                            searchInput.value = '';
                            lastSearchResults = null;
                        }
                        
                        // Re-renderizar lista
                        renderConversations(conversations);
                        
                        // Abrir conversa
                        openConversation(data.conversation_id);
                    } else {
                        console.error('[startChatWithContact] Erro ao buscar conversa criada');
                        showError('Conversa criada mas não foi possível abrir. Recarregue a página.');
                    }
                }
            } else {
                showError(data.error || 'Erro ao criar conversa');
            }
        } catch (error) {
            console.error('[startChatWithContact] Erro:', error);
            showError('Erro ao criar conversa: ' + error.message);
        }
    }

    // Modal nova conversa
    function showNewChatModal() {
        document.getElementById('new-chat-modal').classList.remove('hidden');
    }

    function closeNewChatModal() {
        document.getElementById('new-chat-modal').classList.add('hidden');
        document.getElementById('new-chat-form').reset();
    }

    // Modal editar contato
    function openEditContactModal() {
        if (!currentConversationId) {
            showError('Nenhuma conversa selecionada');
            return;
        }

        const conv = conversations.find(c => c.id === currentConversationId);
        if (!conv) {
            showError('Conversa não encontrada');
            return;
        }

        // Preencher campos
        document.getElementById('edit-contact-phone').value = conv.phone || '';
        document.getElementById('edit-contact-name-input').value = conv.display_name || conv.phone || '';

        // Mostrar modal
        document.getElementById('edit-contact-modal').classList.remove('hidden');
    }

    function closeEditContactModal() {
        document.getElementById('edit-contact-modal').classList.add('hidden');
        document.getElementById('edit-contact-form').reset();
    }

    async function saveContactName(event) {
        event.preventDefault();

        if (!currentConversationId) {
            showError('Nenhuma conversa selecionada');
            return;
        }

        const conv = conversations.find(c => c.id === currentConversationId);
        if (!conv) {
            showError('Conversa não encontrada');
            return;
        }

        const newName = document.getElementById('edit-contact-name-input').value.trim();
        const phone = conv.phone;

        if (!newName) {
            showError('Por favor, informe o nome do contato');
            return;
        }

        try {
            // Salvar no banco via API
            const response = await fetch('/api/update_contact_name.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    phone: phone,
                    name: newName
                })
            });

            const data = await response.json();

            if (data.success) {
                // Atualizar na interface
                conv.display_name = newName;
                conv.contact_name = newName;

                // Atualizar cabeçalho do chat
                const contactNameEl = document.getElementById('chat-contact-name');
                if (contactNameEl) {
                    contactNameEl.textContent = newName;
                }

                // Atualizar lista de conversas
                await loadConversations();

                // Fechar modal
                closeEditContactModal();

                showSuccess('Nome do contato atualizado com sucesso!');
            } else {
                showError('Erro ao atualizar nome: ' + (data.error || 'Erro desconhecido'));
            }

        } catch (error) {
            console.error('Erro ao salvar nome:', error);
            showError('Erro ao salvar nome do contato');
        }
    }

    // Fechar modal ao clicar fora
    document.getElementById('edit-contact-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeEditContactModal();
        }
    });

    document.getElementById('new-chat-form').addEventListener('submit', async function(e) {
        e.preventDefault();

        const phone = document.getElementById('new-chat-phone').value.trim();
        const name = document.getElementById('new-chat-name').value.trim();

        try {
            const response = await fetch('api/chat_conversations.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    phone,
                    contact_name: name
                })
            });

            const data = await response.json();

            if (data.success) {
                closeNewChatModal();
                await loadConversations();
                // Aguardar um pouco para garantir que as conversas foram renderizadas
                setTimeout(() => {
                    openConversation(data.conversation_id);
                }, 100);
                showSuccess('Conversa criada com sucesso!');
            } else {
                showError('Erro: ' + (data.error || 'Erro desconhecido'));
            }
        } catch (error) {
            console.error('Erro:', error);
            showError('Erro ao criar conversa');
        }
    });

    // Utilitários
    function getInitials(name) {
        if (!name) return '??';
        const parts = name.trim().split(' ');
        if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    function showSuccess(message) {

        // Criar notificação visual
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
        notification.innerHTML = `<i class="fas fa-check-circle mr-2"></i>${message}`;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 3000);
    }

    function showError(message) {
        console.error('Error:', message);

        // Criar notificação visual de erro
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
        notification.innerHTML = `<i class="fas fa-exclamation-circle mr-2"></i>${message}`;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 5000);
    }

    function showInfo(message) {
        console.log('Info:', message);

        // Criar notificação visual de informação
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 bg-blue-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
        notification.innerHTML = `<i class="fas fa-info-circle mr-2"></i>${message}`;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 3000);
    }

    // ===============================
    // MENU DE CONTEXTO DAS MENSAGENS
    // ===============================
    function setupChatContextMenu() {
        const container = document.getElementById('chat-messages-container');
        const menu = document.getElementById('chat-context-menu');
        const overlay = document.getElementById('chat-context-overlay');
        if (!container || !menu || !overlay) return;

        container.addEventListener('contextmenu', (event) => {
            const messageElement = event.target.closest('.chat-message');
            if (!messageElement) return;
            event.preventDefault();

            currentContextMessage = {
                id: messageElement.dataset.messageId,
                type: messageElement.dataset.messageType || 'text',
                mediaUrl: decodeHtmlEntities(messageElement.dataset.mediaUrl || ''),
                mediaFilename: decodeHtmlEntities(messageElement.dataset.mediaFilename || ''),
                caption: decodeHtmlEntities(messageElement.dataset.caption || ''),
                text: decodeHtmlEntities(messageElement.dataset.messageText || ''),
                fromMe: messageElement.dataset.fromMe === '1',
                element: messageElement
            };

            showChatContextMenu(event.pageX, event.pageY);
        });

        overlay.addEventListener('click', hideChatContextMenu);
        document.addEventListener('scroll', hideChatContextMenu, true);
        window.addEventListener('resize', hideChatContextMenu);
        document.addEventListener('keyup', (event) => {
            if (event.key === 'Escape') hideChatContextMenu();
        });

        menu.addEventListener('click', (event) => {
            const actionButton = event.target.closest('.chat-context-item');
            if (actionButton) {
                const action = actionButton.dataset.action;
                handleContextMenuAction(action);
                return;
            }

            const reactionButton = event.target.closest('[data-emoji]');
            if (reactionButton) {
                handleContextReaction(reactionButton.dataset.emoji);
            }
        });
    }

    function showChatContextMenu(pageX, pageY) {
        const menu = document.getElementById('chat-context-menu');
        const overlay = document.getElementById('chat-context-overlay');
        if (!menu || !overlay) return;

        // Ocultar opção "Apagar" se a mensagem não for enviada pelo usuário
        const deleteButton = menu.querySelector('[data-action="delete"]');
        if (deleteButton) {
            if (currentContextMessage && !currentContextMessage.fromMe) {
                deleteButton.style.display = 'none';
            } else {
                deleteButton.style.display = '';
            }
        }

        menu.style.left = '-9999px';
        menu.style.top = '-9999px';
        menu.classList.remove('hidden');
        overlay.classList.remove('hidden');
        menu.setAttribute('aria-hidden', 'false');

        requestAnimationFrame(() => {
            const rect = menu.getBoundingClientRect();
            const padding = 12;
            let left = pageX;
            let top = pageY;

            if (left + rect.width > window.innerWidth) {
                left = window.innerWidth - rect.width - padding;
            }
            if (top + rect.height > window.innerHeight) {
                top = window.innerHeight - rect.height - padding;
            }

            menu.style.left = `${Math.max(padding, left)}px`;
            menu.style.top = `${Math.max(padding, top)}px`;
        });
    }

    function hideChatContextMenu() {
        const menu = document.getElementById('chat-context-menu');
        const overlay = document.getElementById('chat-context-overlay');
        if (!menu || !overlay) return;
        menu.classList.add('hidden');
        overlay.classList.add('hidden');
        menu.setAttribute('aria-hidden', 'true');
    }

    function handleContextMenuAction(action) {
        if (!currentContextMessage) return;

        const actions = {
            copy: copyContextMessageText,
            save: saveContextMessageMedia,
            reply: replyToContextMessage,
            forward: forwardContextMessage,
            delete: deleteContextMessage,
            select: toggleSelectContextMessage,
            share: shareContextMessage
        };

        if (actions[action]) {
            actions[action]();
        }

        hideChatContextMenu();
    }

    async function copyContextMessageText() {
        if (!currentContextMessage || !currentContextMessage.text) {
            showError('Não há texto para copiar.');
            return;
        }

        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(currentContextMessage.text);
            } else {
                const textarea = document.createElement('textarea');
                textarea.value = currentContextMessage.text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                textarea.remove();
            }
            showSuccess('Mensagem copiada!');
        } catch (error) {
            console.error('Erro ao copiar:', error);
            showError('Não foi possível copiar o texto.');
        }
    }

    function saveContextMessageMedia() {
        if (currentContextMessage && currentContextMessage.mediaUrl) {
            window.open(currentContextMessage.mediaUrl, '_blank');
            showSuccess('Abrindo mídia para download...');
        } else {
            showError('Esta mensagem não possui mídia para salvar.');
        }
    }

    function replyToContextMessage() {
        if (!currentContextMessage) {
            showError('Selecione uma mensagem para responder.');
            return;
        }
        setReplyContext({
            id: currentContextMessage.id,
            text: getMessageSummary(currentContextMessage),
            fromMe: currentContextMessage.fromMe
        });
        const input = document.getElementById('message-input');
        if (input) {
            input.focus();
        }
        showSuccess('Respondendo à mensagem selecionada.');
    }

    function forwardContextMessage() {
        openForwardModal();
    }

    function deleteContextMessage() {
        if (!currentContextMessage) {
            showError('Selecione uma mensagem para apagar.');
            return;
        }
        if (!currentContextMessage.fromMe) {
            showError('Só é possível apagar mensagens enviadas por você.');
            return;
        }
        const messageId = parseInt(currentContextMessage.id, 10);
        if (!messageId) {
            showError('Mensagem inválida.');
            return;
        }
        if (!confirm('Deseja apagar esta mensagem?')) {
            return;
        }
        fetch('/api/chat_delete_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    message_id: messageId
                })
            })
            .then(response => response.json().then(data => ({
                response,
                data
            })))
            .then(({
                response,
                data
            }) => {
                if (response.ok && data.success) {
                    markMessageAsDeleted(currentContextMessage.element);
                    currentContextMessage.text = '[Mensagem apagada]';
                    showSuccess('Mensagem apagada.');
                } else {
                    showError(data.error || 'Não foi possível apagar a mensagem.');
                }
            })
            .catch(error => {
                console.error('Erro ao apagar mensagem:', error);
                showError('Erro ao apagar mensagem.');
            });
    }

    function toggleSelectContextMessage() {
        if (!currentContextMessage || !currentContextMessage.element) return;
        currentContextMessage.element.classList.toggle('selected');
        const isSelected = currentContextMessage.element.classList.contains('selected');
        showSuccess(isSelected ? 'Mensagem selecionada.' : 'Mensagem desmarcada.');
    }

    async function shareContextMessage() {
        if (!currentContextMessage) return;
        const shareData = {
            text: currentContextMessage.text || 'Mensagem do atendimento',
            url: currentContextMessage.mediaUrl || undefined
        };
        try {
            if (navigator.share) {
                await navigator.share(shareData);
            } else {
                await copyContextMessageText();
                showSuccess('Conteúdo copiado. Cole onde deseja compartilhar.');
            }
        } catch (error) {
            console.error('Erro ao compartilhar:', error);
            showError('Não foi possível compartilhar a mensagem.');
        }
    }

    // Carregar contatos da instância
    let loadedContacts = [];

    async function loadInstanceContacts() {
        try {
            const button = event.target;
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Carregando...';

            const response = await fetch('api/fetch_contacts.php');
            const data = await response.json();

            if (data.success && data.contacts.length > 0) {
                loadedContacts = data.contacts;
                const container = document.getElementById('contacts-container');
                container.innerHTML = '';

                data.contacts.forEach((contact, index) => {
                    const div = document.createElement('div');
                    div.className = 'p-3 hover:bg-gray-50 transition';
                    div.innerHTML = `
                    <div class="flex items-center space-x-3">
                        <input type="checkbox" 
                               class="contact-checkbox w-4 h-4 text-green-600 rounded" 
                               data-phone="${contact.phone}" 
                               data-name="${escapeHtml(contact.name)}"
                               onchange="updateSelectedCount()">
                        <div class="w-10 h-10 rounded-full bg-green-500 flex items-center justify-center text-white font-bold text-sm">
                            ${getInitials(contact.name)}
                        </div>
                        <div class="flex-1 cursor-pointer" onclick="this.parentElement.querySelector('.contact-checkbox').click()">
                            <p class="font-medium text-gray-800">${escapeHtml(contact.name)}</p>
                            <p class="text-sm text-gray-500">${contact.phone}</p>
                        </div>
                    </div>
                `;
                    container.appendChild(div);
                });

                document.getElementById('contacts-list').classList.remove('hidden');
                document.getElementById('select-all-contacts').checked = false;
                updateSelectedCount();
                showSuccess(`${data.total} contatos carregados`);
            } else {
                showError('Nenhum contato encontrado');
            }

            button.disabled = false;
            button.innerHTML = '<i class="fas fa-address-book mr-2"></i>Carregar Meus Contatos';
        } catch (error) {
            console.error('Erro:', error);
            showError('Erro ao carregar contatos');
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-address-book mr-2"></i>Carregar Meus Contatos';
        }
    }

    function selectContact(phone, name) {
        document.getElementById('new-chat-phone').value = phone;
        document.getElementById('new-chat-name').value = name;
        document.getElementById('contacts-list').classList.add('hidden');
    }

    // Selecionar/Desselecionar todos
    function toggleSelectAll() {
        const selectAll = document.getElementById('select-all-contacts');
        const checkboxes = document.querySelectorAll('.contact-checkbox');

        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAll.checked;
        });

        updateSelectedCount();
    }

    // Atualizar contador de selecionados
    function updateSelectedCount() {
        const checkboxes = document.querySelectorAll('.contact-checkbox:checked');
        const count = checkboxes.length;
        const countElement = document.getElementById('selected-count');
        const createBtn = document.getElementById('create-multiple-btn');

        countElement.textContent = `${count} selecionado(s)`;
        createBtn.disabled = count === 0;

        // Atualizar estado do "Selecionar Todos"
        const allCheckboxes = document.querySelectorAll('.contact-checkbox');
        const selectAllCheckbox = document.getElementById('select-all-contacts');
        selectAllCheckbox.checked = count === allCheckboxes.length && count > 0;
    }

    // Criar múltiplas conversas
    async function createMultipleChats() {
        const checkboxes = document.querySelectorAll('.contact-checkbox:checked');

        if (checkboxes.length === 0) {
            showError('Nenhum contato selecionado');
            return;
        }

        const confirmMsg = `Deseja criar conversas com ${checkboxes.length} contato(s)?`;
        if (!confirm(confirmMsg)) {
            return;
        }

        const button = document.getElementById('create-multiple-btn');
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Criando...';

        let created = 0;
        let errors = 0;

        for (const checkbox of checkboxes) {
            const phone = checkbox.dataset.phone;
            const name = checkbox.dataset.name;

            try {
                const response = await fetch('api/chat_conversations.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        phone,
                        contact_name: name
                    })
                });

                const data = await response.json();

                if (data.success) {
                    created++;
                } else {
                    errors++;
                }
            } catch (error) {
                console.error('Erro ao criar conversa:', error);
                errors++;
            }
        }

        // Fechar modal e atualizar lista
        closeNewChatModal();
        await loadConversations();

        // Mostrar resultado
        if (errors === 0) {
            showSuccess(`${created} conversa(s) criada(s) com sucesso!`);
        } else {
            showError(`${created} criada(s), ${errors} erro(s)`);
        }

        button.disabled = false;
        button.innerHTML = '<i class="fas fa-comments mr-2"></i>Criar Conversas';
    }

    // Deletar conversa
    async function deleteConversation() {
        if (!currentConversationId) {
            showError('Nenhuma conversa selecionada');
            return;
        }

        if (!confirm('Tem certeza que deseja deletar esta conversa? Esta ação não pode ser desfeita.')) {
            return;
        }

        try {
            const response = await fetch('api/delete_conversation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    conversation_id: currentConversationId
                })
            });

            const data = await response.json();

            if (data.success) {
                showSuccess('Conversa deletada com sucesso');
                currentConversationId = null;
                document.getElementById('chat-selected').classList.add('hidden');
                document.getElementById('no-chat-selected').classList.remove('hidden');
                await loadConversations();
            } else {
                showError('Erro: ' + (data.error || 'Erro desconhecido'));
            }
        } catch (error) {
            console.error('Erro:', error);
            showError('Erro ao deletar conversa');
        }
    }

    // ========================================
    // SISTEMA DE ATENDIMENTO
    // ========================================

    // Atender conversa
    async function atenderConversa() {
        if (!currentConversationId) {
            showError('Selecione uma conversa primeiro');
            return;
        }

        try {
            const response = await fetch('api/chat_attend.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'attend',
                    conversation_id: currentConversationId
                })
            });

            const data = await response.json();

            if (data.success) {
                showSuccess('Conversa atendida! Agora ela é sua.');

                // Esconder botão ATENDER
                const btnAtender = document.getElementById('btn-atender');
                if (btnAtender) btnAtender.style.display = 'none';

                // Atualizar lista de conversas
                await loadConversations();
            } else {
                showError(data.error || 'Erro ao atender conversa');
            }
        } catch (error) {
            console.error('Erro:', error);
            showError('Erro ao atender conversa');
        }
    }

    // Verificar status de atendimento ao abrir conversa
    async function checkAttendanceStatus(conversationId) {
        try {
            const response = await fetch(`api/chat_attend.php?conversation_id=${conversationId}`);
            const data = await response.json();

            if (data.success) {
                const btnAtender = document.getElementById('btn-atender');

                if (data.can_attend) {
                    // Conversa disponível para atender
                    if (btnAtender) btnAtender.style.display = 'inline-flex';
                } else if (data.is_attended_by_me) {
                    // Já estou atendendo
                    if (btnAtender) btnAtender.style.display = 'none';
                } else if (data.is_attended) {
                    // Outro atendente está atendendo
                    if (btnAtender) btnAtender.style.display = 'none';
                    showError(`Esta conversa está sendo atendida por: ${data.attended_by_name}`);
                } else if (data.is_closed) {
                    // Conversa encerrada (histórico)
                    if (btnAtender) btnAtender.style.display = 'none';
                }

                return data;
            }
        } catch (error) {
            console.error('Erro ao verificar status:', error);
        }
        return null;
    }

    // Liberar conversa (devolver para fila)
    async function liberarConversa() {
        if (!currentConversationId) return;

        if (!confirm('Deseja devolver esta conversa para a fila geral?')) {
            return;
        }

        try {
            const response = await fetch('api/chat_attend.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'release',
                    conversation_id: currentConversationId
                })
            });

            const data = await response.json();

            if (data.success) {
                showSuccess('Conversa liberada para a fila geral');
                await loadConversations();
            } else {
                showError(data.error || 'Erro ao liberar conversa');
            }
        } catch (error) {
            console.error('Erro:', error);
            showError('Erro ao liberar conversa');
        }
    }

    // ========================================
    // FUNÇÃO DUPLICADA - COMENTADA (usar a primeira versão)
    // ========================================
    /*
    async function handleFileSelect(input, mediaType) {
        const file = input.files[0];
        if (!file) return;

        if (!currentConversationId) {
            showError('Selecione uma conversa primeiro');
            input.value = '';
            return;
        }

        const conversation = conversations.find(c => c.id == currentConversationId);
        if (!conversation) {
            showError('Conversa não encontrada');
            input.value = '';
            return;
        }

        // Validar tamanho
        const maxSizes = {
            image: 5 * 1024 * 1024, // 5MB
            audio: 16 * 1024 * 1024, // 16MB
            document: 100 * 1024 * 1024 // 100MB
        };

        if (file.size > maxSizes[mediaType]) {
            const maxMB = maxSizes[mediaType] / (1024 * 1024);
            showError(`Arquivo muito grande. Máximo: ${maxMB}MB`);
            input.value = '';
            return;
        }

        try {
            // Bloquear polling durante envio
            window.sendingMedia = true;

            // Criar preview de carregamento
            const container = document.getElementById('chat-messages-container');
            const tempId = 'temp_' + Date.now();

            // Determinar tipo de preview baseado no tipo de mídia
            let previewHtml = '';
            const fileName = file.name;
            const fileSize = (file.size / 1024).toFixed(1) + ' KB';

            if (mediaType === 'image') {
                // Preview de imagem com thumbnail
                const reader = new FileReader();
                reader.onload = function(e) {
                    const imgPreview = document.querySelector(`[data-message-id="${tempId}"] .media-preview-img`);
                    if (imgPreview) {
                        imgPreview.src = e.target.result;
                    }
                };
                reader.readAsDataURL(file);

                previewHtml = '<div class="chat-message sent temp-message" data-message-id="' + tempId + '" style="opacity: 0.7;">' +
                    '<div class="chat-message-content">' +
                    '<div class="media-container" style="position: relative;">' +
                    '<img class="media-preview-img" src="" alt="Enviando..." style="max-width: 200px; border-radius: 8px; filter: blur(1px);">' +
                    '<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0,0,0,0.6); padding: 8px 12px; border-radius: 20px; color: white; font-size: 12px;">' +
                    '<i class="fas fa-spinner fa-spin"></i> Enviando...' +
                    '</div>' +
                    '</div>' +
                    '</div>' +
                    '</div>';
            } else if (mediaType === 'audio') {
                previewHtml = '<div class="chat-message sent temp-message" data-message-id="' + tempId + '" style="opacity: 0.7;">' +
                    '<div class="chat-message-content">' +
                    '<div class="audio-message-container" style="display: flex; align-items: center; gap: 10px; padding: 10px; background: rgba(0,0,0,0.05); border-radius: 8px;">' +
                    '<i class="fas fa-microphone" style="font-size: 24px; color: #25d366;"></i>' +
                    '<div style="flex: 1;">' +
                    '<div style="font-weight: 500;">Áudio</div>' +
                    '<div style="font-size: 12px; color: #666;"><i class="fas fa-spinner fa-spin"></i> Enviando...</div>' +
                    '</div>' +
                    '</div>' +
                    '</div>' +
                    '</div>';
            } else {
                // Preview de documento
                const icon = mediaType === 'document' ? 'fa-file-pdf' : 'fa-file';
                previewHtml = '<div class="chat-message sent temp-message" data-message-id="' + tempId + '" style="opacity: 0.7;">' +
                    '<div class="chat-message-content">' +
                    '<div class="document-message-container" style="display: flex; align-items: center; gap: 10px; padding: 10px; background: rgba(0,0,0,0.05); border-radius: 8px;">' +
                    '<i class="fas ' + icon + '" style="font-size: 32px; color: #dc3545;"></i>' +
                    '<div style="flex: 1;">' +
                    '<div style="font-weight: 500; font-size: 14px;">' + fileName + '</div>' +
                    '<div style="font-size: 12px; color: #666;">' + fileSize + ' • <i class="fas fa-spinner fa-spin"></i> Enviando...</div>' +
                    '</div>' +
                    '</div>' +
                    '</div>' +
                    '</div>';
            }

            // Adicionar preview ao container
            if (container) {
                container.insertAdjacentHTML('beforeend', previewHtml);
                scrollToBottom(true);
                console.log('[SEND_MEDIA] Preview de carregamento adicionado');
            }

            const formData = new FormData();
            formData.append('file', file);
            formData.append('conversation_id', currentConversationId);
            formData.append('phone', conversation.phone);
            formData.append('media_type', mediaType);
            formData.append('caption', ''); // Pode adicionar campo para legenda depois

            showSuccess('Enviando arquivo...');

            const response = await fetch('api/send_media.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                showSuccess('Arquivo enviado com sucesso!');

                console.log('[SEND_MEDIA] Resposta da API:', data);
                console.log('[SEND_MEDIA] Mensagem retornada:', data.message);

                // Remover preview de carregamento
                const tempMessage = document.querySelector(`[data-message-id="${tempId}"]`);
                if (tempMessage) {
                    tempMessage.remove();
                    console.log('[SEND_MEDIA] Preview de carregamento removido');
                }

                // Se a API retornou a mensagem, adicionar diretamente
                if (data.message) {
                    const container = document.getElementById('chat-messages-container');
                    if (container && messages) {
                        console.log('[SEND_MEDIA] Adicionando mensagem ao array');

                        // ✅ Marcar timestamp de envio (proteção contra polling)
                        window.lastMessageSentTime = Date.now();
                        console.log('[SEND_MEDIA] Timestamp de envio registrado:', window.lastMessageSentTime);

                        // ✅ Garantir que timestamp está na mensagem
                        if (!data.message.timestamp) {
                            data.message.timestamp = Math.floor(Date.now() / 1000);
                        }

                        // ✅ VERIFICAR SE MENSAGEM JÁ EXISTE (evitar duplicação)
                        const messageExists = messages.some(m => m.id === data.message.id);
                        const messageInDOM = document.querySelector(`[data-message-id="${data.message.id}"]`);

                        if (messageExists || messageInDOM) {
                            console.log('[SEND_MEDIA] Mensagem já existe, não adicionando novamente');
                        } else {
                            // Adicionar ao array de mensagens
                            messages.push(data.message);

                            // ✅ IMPORTANTE: Atualizar cache também para evitar que polling remova
                            if (!messagesCache[currentConversationId]) {
                                messagesCache[currentConversationId] = [];
                            }
                            messagesCache[currentConversationId].push(data.message);

                            console.log('[SEND_MEDIA] Chamando renderSingleMessage');
                            console.log('[SEND_MEDIA] Dados da mensagem:', {
                                id: data.message.id,
                                message_type: data.message.message_type,
                                media_url: data.message.media_url,
                                file_name: data.message.file_name,
                                timestamp: data.message.timestamp
                            });

                            // Renderizar apenas a nova mensagem
                            const messageHtml = renderSingleMessage(data.message);
                            console.log('[SEND_MEDIA] HTML gerado (primeiros 300 chars):', messageHtml.substring(0, 300));
                            container.insertAdjacentHTML('beforeend', messageHtml);
                            scrollToBottom(true);
                            console.log('[SEND_MEDIA] Mensagem adicionada ao DOM e ao cache');
                        }
                    } else {
                        console.error('[SEND_MEDIA] Container ou messages não encontrado');
                    }
                } else {
                    console.warn('[SEND_MEDIA] API não retornou mensagem, usando fallback');
                    // Fallback: recarregar todas as mensagens
                    delete messagesCache[currentConversationId];
                    await fetchMessagesFromServer(currentConversationId, true);
                }

                // Atualizar lista de conversas
                loadConversations();
            } else {
                // Remover preview em caso de erro
                const tempMessage = document.querySelector(`[data-message-id="${tempId}"]`);
                if (tempMessage) {
                    tempMessage.remove();
                }
                showError('Erro: ' + (data.error || 'Erro desconhecido'));
            }
        } catch (error) {
            console.error('Erro:', error);
            showError('Erro ao enviar arquivo');
        } finally {
            input.value = ''; // Limpar input
            // Liberar polling após 15 segundos (tempo do intervalo de polling - CRÍTICO)
            // Garante que polling não interfere durante salvamento no banco
            setTimeout(() => {
                console.log('[SEND_MEDIA] Liberando polling após envio de mídia');
                window.sendingMedia = false;
            }, 15000); // 15 segundos (mesmo tempo do intervalo de polling)
        }
    }
    */
    // FIM DA FUNÇÃO DUPLICADA COMENTADA

    // Fechar modal ao clicar fora
    document.getElementById('new-chat-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeNewChatModal();
        }
    });

    // ========================================
    // GRAVAÇÃO DE ÁUDIO
    // ========================================

    let mediaRecorder = null;
    let audioChunks = [];
    let recordingStartTime = null;
    let recordingTimer = null;

    async function toggleAudioRecording() {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            stopAndSendAudio();
        } else {
            startAudioRecording();
        }
    }

    async function startAudioRecording() {
        if (!currentConversationId) {
            showError('Selecione uma conversa primeiro');
            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                audio: true
            });

            // Tentar usar formato compatível com WhatsApp
            const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus') ?
                'audio/webm;codecs=opus' :
                'audio/webm';

            mediaRecorder = new MediaRecorder(stream, {
                mimeType
            });
            audioChunks = [];

            mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    audioChunks.push(event.data);
                }
            };

            mediaRecorder.onstop = () => {
                stream.getTracks().forEach(track => track.stop());
            };

            mediaRecorder.start();
            recordingStartTime = Date.now();

            // Atualizar UI
            document.getElementById('record-audio-btn').classList.add('recording');
            document.getElementById('recording-indicator').classList.remove('hidden');
            document.getElementById('recording-indicator').classList.add('flex');

            // Timer de gravação
            recordingTimer = setInterval(updateRecordingTime, 1000);

        } catch (error) {
            console.error('Erro ao acessar microfone:', error);
            showError('Não foi possível acessar o microfone. Verifique as permissões.');
        }
    }

    function updateRecordingTime() {
        if (!recordingStartTime) return;

        const elapsed = Math.floor((Date.now() - recordingStartTime) / 1000);
        const minutes = Math.floor(elapsed / 60).toString().padStart(2, '0');
        const seconds = (elapsed % 60).toString().padStart(2, '0');

        document.getElementById('recording-time').textContent = `${minutes}:${seconds}`;
    }

    async function stopAndSendAudio() {
        if (!mediaRecorder || mediaRecorder.state !== 'recording') return;

        mediaRecorder.stop();
        clearInterval(recordingTimer);

        // Aguardar dados finais
        await new Promise(resolve => {
            mediaRecorder.onstop = () => {
                mediaRecorder.stream.getTracks().forEach(track => track.stop());
                resolve();
            };
        });

        // Resetar UI
        resetRecordingUI();

        // Criar blob e enviar
        const audioBlob = new Blob(audioChunks, {
            type: 'audio/webm'
        });

        if (audioBlob.size < 1000) {
            showError('Áudio muito curto');
            return;
        }

        // Enviar áudio
        await sendRecordedAudio(audioBlob);
    }

    function cancelAudioRecording() {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            mediaRecorder.stop();
            mediaRecorder.stream.getTracks().forEach(track => track.stop());
        }

        clearInterval(recordingTimer);
        audioChunks = [];
        resetRecordingUI();
    }

    function resetRecordingUI() {
        document.getElementById('record-audio-btn').classList.remove('recording');
        document.getElementById('recording-indicator').classList.add('hidden');
        document.getElementById('recording-indicator').classList.remove('flex');
        document.getElementById('recording-time').textContent = '00:00';
        recordingStartTime = null;
    }

    async function sendRecordedAudio(audioBlob) {
        // Buscar conversa atual
        const conv = conversations.find(c => c.id === currentConversationId);
        
        if (!conv) {
            showError('Conversa não encontrada');
            return;
        }

        const formData = new FormData();
        formData.append('file', audioBlob, 'audio_' + Date.now() + '.webm');
        formData.append('conversation_id', currentConversationId); // ✅ Usar conversation_id ao invés de phone
        formData.append('media_type', 'audio');

        try {
            showSuccess('Enviando áudio...');

            const response = await fetch('/api/send_media.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                showSuccess('Áudio enviado com sucesso!');

                // Se a API retornou a mensagem, adicionar diretamente
                if (data.message) {
                    const container = document.getElementById('chat-messages-container');
                    if (container && messages) {
                        // Adicionar ao array de mensagens
                        messages.push(data.message);

                        // Renderizar apenas a nova mensagem
                        const messageHtml = renderSingleMessage(data.message);
                        container.insertAdjacentHTML('beforeend', messageHtml);
                        scrollToBottom(true);
                    }
                } else {
                    // Fallback: recarregar todas as mensagens
                    delete messagesCache[currentConversationId];
                    await fetchMessagesFromServer(currentConversationId, true);
                }

                // Atualizar lista de conversas
                loadConversations();
            } else {
                showError('Erro: ' + (data.error || 'Erro ao enviar áudio'));
            }
        } catch (error) {
            console.error('Erro ao enviar áudio:', error);
            showError('Erro ao enviar áudio');
        }
    }

    // ========================================
    // PLAYER DE ÁUDIO CUSTOMIZADO
    // ========================================

    let currentPlayingAudio = null;
    let currentPlayingVideo = null;

    // Controlar reprodução de vídeos/GIFs
    function toggleVideoPlay(videoId) {
        const video = document.getElementById(videoId);
        const overlay = document.getElementById('play-overlay-' + videoId);

        if (!video) return;

        // Pausar outro vídeo se estiver tocando
        if (currentPlayingVideo && currentPlayingVideo !== video) {
            currentPlayingVideo.pause();
            currentPlayingVideo.currentTime = 0;
            const otherOverlay = document.getElementById('play-overlay-' + currentPlayingVideo.id);
            if (otherOverlay) otherOverlay.style.display = 'flex';
        }

        if (video.paused) {
            video.play().then(() => {
                if (overlay) overlay.style.display = 'none';
                currentPlayingVideo = video;
            }).catch(e => {
                console.error('Erro ao reproduzir vídeo:', e);
                video.controls = true;
                if (overlay) overlay.style.display = 'none';
            });
        } else {
            video.pause();
            if (overlay) overlay.style.display = 'flex';
            currentPlayingVideo = null;
        }
    }

    // Função para controlar play/pause de áudios (WhatsApp Web style)
    function toggleAudioPlay(audioId) {
        const audio = document.getElementById(audioId);
        const button = document.getElementById('btn-' + audioId);
        const progressContainer = document.getElementById('progress-container-' + audioId);
        const progress = document.getElementById('progress-' + audioId);
        const timeDisplay = document.getElementById('time-' + audioId);

        if (!audio || !button) return;

        try {
            if (audio.paused) {
                // Marcar que este áudio foi iniciado pelo usuário
                audio.setAttribute('data-user-playing', 'true');

                // Garantir que o áudio tenha volume
                audio.volume = 1.0;
                audio.muted = false;

                // Adicionar event listeners apenas quando o usuário clicar
                if (!audio.hasAttribute('data-listeners-added')) {
                    audio.setAttribute('data-listeners-added', 'true');

                    // Event listener para atualizar progresso
                    audio.addEventListener('timeupdate', function() {
                        updateAudioProgress(audioId);
                    });

                    // Event listener para quando terminar
                    audio.addEventListener('ended', function() {
                        resetAudioPlayer(audioId);
                    });

                    // Event listener para quando carregar metadata
                    audio.addEventListener('loadedmetadata', function() {
                        updateAudioDuration(audioId);
                    });
                }

                // Mostrar progress bar
                if (progressContainer) {
                    progressContainer.style.display = 'block';
                }

                // Reproduzir áudio diretamente
                audio.play().then(() => {
                    // Atualizar botão para pause
                    button.innerHTML = '<i class="fas fa-pause text-xs"></i>';
                    button.classList.remove('bg-green-500', 'hover:bg-green-600');
                    button.classList.add('bg-red-500', 'hover:bg-red-600');

                }).catch(error => {
                    console.error('Erro ao reproduzir áudio:', error);
                    // Tentar com load() como fallback
                    audio.load();
                    setTimeout(() => {
                        audio.play().then(() => {
                            button.innerHTML = '<i class="fas fa-pause text-xs"></i>';
                            button.classList.remove('bg-green-500', 'hover:bg-green-600');
                            button.classList.add('bg-red-500', 'hover:bg-red-600');
                        }).catch(e => {
                            console.error('Fallback também falhou:', e);
                            // Remover marcador se falhar
                            audio.removeAttribute('data-user-playing');
                            // Esconder progress bar se falhar
                            if (progressContainer) {
                                progressContainer.style.display = 'none';
                            }
                            // Abrir em nova janela como último recurso
                            window.open(audio.src, '_blank');
                        });
                    }, 500);
                });
            } else {
                // Pausar áudio
                audio.pause();
                // Remover marcador quando usuário pausar manualmente
                audio.removeAttribute('data-user-playing');
                button.innerHTML = '<i class="fas fa-play text-xs" style="margin-left: 2px;"></i>';
                button.classList.remove('bg-red-500', 'hover:bg-red-600');
                button.classList.add('bg-green-500', 'hover:bg-green-600');
            }
        } catch (error) {
            console.error('Erro no toggleAudioPlay:', error);
            // Remover marcador em caso de erro
            audio.removeAttribute('data-user-playing');
            // Fallback: abrir em nova janela
            if (audio && audio.src) {
                window.open(audio.src, '_blank');
            }
        }
    }

    // Função para atualizar duração do áudio
    function updateAudioDuration(audioId) {
        const audio = document.getElementById(audioId);
        const timeDisplay = document.getElementById('time-' + audioId);

        if (!audio || !timeDisplay) return;

        try {
            const duration = audio.duration;
            if (!isNaN(duration) && isFinite(duration)) {
                const minutes = Math.floor(duration / 60);
                const seconds = Math.floor(duration % 60);
                timeDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            }
        } catch (error) {
            console.log('Erro ao atualizar duração:', error);
        }
    }

    // Função para atualizar progresso do áudio
    function updateAudioProgress(audioId) {
        const audio = document.getElementById(audioId);
        const progress = document.getElementById('progress-' + audioId);
        const timeDisplay = document.getElementById('time-' + audioId);

        if (!audio || !progress || !timeDisplay) return;

        try {
            if (audio.duration && !isNaN(audio.duration)) {
                const percent = (audio.currentTime / audio.duration) * 100;
                progress.style.width = percent + '%';

                const currentMinutes = Math.floor(audio.currentTime / 60);
                const currentSeconds = Math.floor(audio.currentTime % 60);

                timeDisplay.textContent = `${currentMinutes}:${currentSeconds.toString().padStart(2, '0')}`;
            }
        } catch (error) {
            console.log('Erro ao atualizar progresso:', error);
        }
    }

    // Função para resetar player de áudio
    function resetAudioPlayer(audioId) {
        const audio = document.getElementById(audioId);
        const button = document.getElementById('btn-' + audioId);
        const progress = document.getElementById('progress-' + audioId);
        const progressContainer = document.getElementById('progress-container-' + audioId);
        const timeDisplay = document.getElementById('time-' + audioId);

        if (!audio || !button) return;

        try {
            // Remover marcador de reprodução manual
            audio.removeAttribute('data-user-playing');

            // Resetar para estado inicial
            audio.currentTime = 0;
            if (progress) progress.style.width = '0%';
            if (timeDisplay) timeDisplay.textContent = '0:00';

            // Esconder progress bar
            if (progressContainer) {
                progressContainer.style.display = 'none';
            }

            // Voltar botão para play
            button.innerHTML = '<i class="fas fa-play text-xs" style="margin-left: 2px;"></i>';
            button.classList.remove('bg-red-500', 'hover:bg-red-600');
            button.classList.add('bg-green-500', 'hover:bg-green-600');
        } catch (error) {
            console.log('Erro ao resetar player:', error);
        }
    }

    // Função para atualizar duração do áudio
    function updateAudioDuration(audioId) {
        const audio = document.getElementById(audioId);
        const timeDisplay = document.getElementById('time-' + audioId);

        if (!audio || !timeDisplay) return;

        try {
            const duration = audio.duration;
            if (!isNaN(duration) && isFinite(duration)) {
                const minutes = Math.floor(duration / 60);
                const seconds = Math.floor(duration % 60);
                timeDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            }
        } catch (error) {
            console.log('Erro ao atualizar duração:', error);
        }
    }

    // Função para atualizar progresso do áudio
    function updateAudioProgress(audioId) {
        const audio = document.getElementById(audioId);
        const progress = document.getElementById('progress-' + audioId);
        const timeDisplay = document.getElementById('time-' + audioId);

        if (!audio || !progress || !timeDisplay) return;

        try {
            if (audio.duration && !isNaN(audio.duration)) {
                const percent = (audio.currentTime / audio.duration) * 100;
                progress.style.width = percent + '%';

                const currentMinutes = Math.floor(audio.currentTime / 60);
                const currentSeconds = Math.floor(audio.currentTime % 60);
                const durationMinutes = Math.floor(audio.duration / 60);
                const durationSeconds = Math.floor(audio.duration % 60);

                timeDisplay.textContent = `${currentMinutes}:${currentSeconds.toString().padStart(2, '0')} / ${durationMinutes}:${durationSeconds.toString().padStart(2, '0')}`;
            }
        } catch (error) {
            console.log('Erro ao atualizar progresso:', error);
        }
    }

    // Função para resetar player de áudio
    function resetAudioPlayer(audioId) {
        const audio = document.getElementById(audioId);
        const button = document.getElementById('btn-' + audioId);
        const progress = document.getElementById('progress-' + audioId);
        const timeDisplay = document.getElementById('time-' + audioId);

        if (!audio || !button) return;

        try {
            // Resetar para estado inicial
            audio.currentTime = 0;
            progress.style.width = '0%';
            timeDisplay.textContent = '0:00';

            button.innerHTML = '<i class="fas fa-play text-xs"></i>';
            button.classList.remove('bg-red-500', 'hover:bg-red-600');
            button.classList.add('bg-green-500', 'hover:bg-green-600');
        } catch (error) {
            console.log('Erro ao resetar player:', error);
        }
    }

    function formatAudioTime(seconds) {
        if (isNaN(seconds)) return '0:00';
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }

    // ========================================
    // COLAR IMAGEM (Ctrl+V / Paste)
    // ========================================

    // Listener para colar imagem no campo de mensagem
    document.addEventListener('DOMContentLoaded', function() {
        const messageInput = document.getElementById('message-input');
        const chatArea = document.getElementById('chat-area');

        // Listener no input de mensagem
        if (messageInput) {
            messageInput.addEventListener('paste', handlePasteImage);
        }

        // Listener na área do chat inteira (para colar mesmo sem foco no input)
        if (chatArea) {
            chatArea.addEventListener('paste', handlePasteImage);
        }
    });

    // Função para processar imagem colada
    async function handlePasteImage(e) {
        const items = e.clipboardData?.items;
        if (!items) return;

        for (let i = 0; i < items.length; i++) {
            const item = items[i];

            // Verificar se é uma imagem
            if (item.type.indexOf('image') !== -1) {
                e.preventDefault();

                const file = item.getAsFile();
                if (!file) continue;

                // Verificar se tem conversa selecionada
                if (!currentConversationId) {
                    showError('Selecione uma conversa primeiro para colar a imagem');
                    return;
                }

                // Mostrar preview e confirmar envio
                showPasteImagePreview(file);
                return;
            }
        }
    }

    // Mostrar preview da imagem colada
    function showPasteImagePreview(file) {
        // Criar URL temporária para preview
        const imageUrl = URL.createObjectURL(file);

        // Criar modal de preview
        const modal = document.createElement('div');
        modal.id = 'paste-image-modal';
        modal.className = 'fixed inset-0 bg-black bg-opacity-70 z-50 flex items-center justify-center';
        modal.innerHTML = `
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-lg w-full mx-4 overflow-hidden" onclick="event.stopPropagation()">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white">
                    <i class="fas fa-image text-green-600 mr-2"></i>
                    Enviar Imagem
                </h3>
                <button type="button" onclick="closePasteImageModal()" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white" style="background: none !important; border: none !important; border-radius: 0 !important; box-shadow: none !important; cursor: pointer !important; color: #9ca3af !important; font-size: 20px !important; padding: 4px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; transition: color 0.2s !important;">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-4">
                <div class="mb-4 flex justify-center">
                    <img src="${imageUrl}" alt="Preview" class="max-h-64 rounded-lg shadow-md" style="max-width: 100%;">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Legenda (opcional)</label>
                    <input type="text" id="paste-image-caption" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="Digite uma legenda...">
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="closePasteImageModal()" class="flex-1 px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-500 text-gray-800 dark:text-white rounded-lg transition">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </button>
                    <button type="button" onclick="enviarImagemColada()" class="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition">
                        <i class="fas fa-paper-plane mr-2"></i>Enviar
                    </button>
                </div>
            </div>
        </div>
    `;

        document.body.appendChild(modal);

        // Armazenar arquivo para envio
        window.pastedImageFile = file;

        // Focar no campo de legenda
        setTimeout(() => {
            document.getElementById('paste-image-caption')?.focus();
        }, 100);

        // Fechar ao clicar no fundo escuro
        modal.onclick = function(e) {
            if (e.target === modal) {
                closePasteImageModal();
            }
        };

        // Enviar com Enter
        document.getElementById('paste-image-caption')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                enviarImagemColada();
            }
        });
    }

    // Fechar modal de preview
    function closePasteImageModal() {
        const modal = document.getElementById('paste-image-modal');
        if (modal) {
            modal.remove();
        }
        window.pastedImageFile = null;
    }

    // Função para enviar imagem (nome diferente para evitar conflitos)
    async function enviarImagemColada() {
        // Capturar dados ANTES de fechar o modal
        const file = window.pastedImageFile;
        const captionInput = document.getElementById('paste-image-caption');
        const caption = captionInput ? captionInput.value : '';

        // FORÇAR fechamento do modal de todas as formas possíveis
        try {
            const modal = document.getElementById('paste-image-modal');
            if (modal) {
                modal.style.display = 'none';
                modal.parentNode.removeChild(modal);
            }
        } catch (e) {
            console.error('Erro ao fechar modal:', e);
        }

        // Remover qualquer modal que possa ter ficado
        document.querySelectorAll('#paste-image-modal').forEach(el => el.remove());

        if (!file) {
            showError('Nenhuma imagem para enviar');
            window.pastedImageFile = null;
            return;
        }

        if (!currentConversationId) {
            showError('Selecione uma conversa primeiro');
            window.pastedImageFile = null;
            return;
        }

        const conversation = conversations.find(c => c.id == currentConversationId);
        if (!conversation) {
            showError('Conversa não encontrada');
            window.pastedImageFile = null;
            return;
        }

        // Validar tamanho (5MB para imagens)
        const maxSize = 5 * 1024 * 1024;
        if (file.size > maxSize) {
            showError('Imagem muito grande. Máximo: 5MB');
            window.pastedImageFile = null;
            return;
        }

        // Limpar referência
        window.pastedImageFile = null;

        // Criar URL local para preview imediato
        const localImageUrl = URL.createObjectURL(file);

        // Criar ID único para o placeholder
        const placeholderId = 'sending-image-' + Date.now();

        // Adicionar placeholder visual na área de mensagens
        const container = document.getElementById('chat-messages-container');
        if (container) {
            const placeholderHtml = `
                <div id="${placeholderId}" class="chat-message sent" style="opacity: 0.8;">
                    <div class="chat-message-bubble" style="position: relative;">
                        <div class="chat-message-image" style="position: relative;">
                            <img src="${localImageUrl}" alt="Enviando..." 
                                 style="min-height: 150px; max-height: 280px; object-fit: contain; filter: brightness(0.7);">
                            <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; background: rgba(0,0,0,0.4); border-radius: 12px;">
                                <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: white; margin-bottom: 8px;"></i>
                                <span style="color: white; font-size: 13px; font-weight: 500;">Enviando...</span>
                            </div>
                        </div>
                        ${caption ? '<p class="chat-message-text">' + caption + '</p>' : ''}
                        <div class="chat-message-time">
                            <span>Enviando</span>
                            <span class="chat-message-status">🕐</span>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', placeholderHtml);

            // Scroll para o final
            container.scrollTop = container.scrollHeight;
        }

        try {
            const formData = new FormData();
            formData.append('file', file, 'pasted_image.png');
            formData.append('conversation_id', currentConversationId);
            formData.append('phone', conversation.phone);
            formData.append('media_type', 'image');
            formData.append('caption', caption);

            const response = await fetch('api/send_media.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            // Remover placeholder
            const placeholder = document.getElementById(placeholderId);
            if (placeholder) {
                placeholder.remove();
            }

            // Liberar URL do objeto
            URL.revokeObjectURL(localImageUrl);

            if (data.success) {
                showSuccess('Imagem enviada com sucesso!');

                // Se a API retornou a mensagem, adicionar diretamente
                if (data.message) {
                    const container = document.getElementById('chat-messages-container');
                    if (container && messages) {
                        // Adicionar ao array de mensagens
                        messages.push(data.message);

                        // Renderizar apenas a nova mensagem
                        const messageHtml = renderSingleMessage(data.message);
                        container.insertAdjacentHTML('beforeend', messageHtml);
                        scrollToBottom(true);
                    }
                } else {
                    // Fallback: recarregar todas as mensagens
                    delete messagesCache[currentConversationId];
                    await fetchMessagesFromServer(currentConversationId, true, true);
                }

                // Atualizar lista de conversas
                loadConversations();
            } else {
                showError('Erro: ' + (data.error || 'Erro desconhecido'));
            }
        } catch (error) {
            // Remover placeholder em caso de erro
            const placeholder = document.getElementById(placeholderId);
            if (placeholder) {
                placeholder.remove();
            }
            URL.revokeObjectURL(localImageUrl);

            console.error('Erro:', error);
            showError('Erro ao enviar imagem');
        }
    }

    // Manter compatibilidade com nome antigo
    function sendPastedImage() {
        enviarImagemColada();
    }

    // ========================================
    // EMOJI E GIF PICKER - ESTILO WHATSAPP
    // ========================================

    // Emojis organizados por categoria
    const emojiCategories = {
        recent: [], // Será preenchido com localStorage
        smileys: [
            '😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '🙃', '😉', '😊', '😇', '🥰', '😍', '🤩',
            '😘', '😗', '😚', '😙', '🥲', '😋', '😛', '😜', '🤪', '😝', '🤑', '🤗', '🤭', '🤫', '🤔', '🤐',
            '🤨', '😐', '😑', '😶', '😏', '😒', '🙄', '😬', '🤥', '😌', '😔', '😪', '🤤', '😴', '😷', '🤒',
            '🤕', '🤢', '🤮', '🤧', '🥵', '🥶', '🥴', '😵', '🤯', '🤠', '🥳', '🥸', '😎', '🤓', '🧐', '😕',
            '😟', '🙁', '☹️', '😮', '😯', '😲', '😳', '🥺', '😦', '😧', '😨', '😰', '😥', '😢', '😭', '😱',
            '😖', '😣', '😞', '😓', '😩', '😫', '🥱', '😤', '😡', '😠', '🤬', '😈', '👿', '💀', '☠️', '💩',
            '🤡', '👹', '👺', '👻', '👽', '👾', '🤖', '😺', '😸', '😹', '😻', '😼', '😽', '🙀', '😿', '😾'
        ],
        animals: [
            '🐶', '🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼', '🐻‍❄️', '🐨', '🐯', '🦁', '🐮', '🐷', '🐽', '🐸',
            '🐵', '🙈', '🙉', '🙊', '🐒', '🐔', '🐧', '🐦', '🐤', '🐣', '🐥', '🦆', '🦅', '🦉', '🦇', '🐺',
            '🐗', '🐴', '🦄', '🐝', '🪱', '🐛', '🦋', '🐌', '🐞', '🐜', '🪰', '🪲', '🪳', '🦟', '🦗', '🕷️',
            '🦂', '🐢', '🐍', '🦎', '🦖', '🦕', '🐙', '🦑', '🦐', '🦞', '🦀', '🐡', '🐠', '🐟', '🐬', '🐳',
            '🐋', '🦈', '🐊', '🐅', '🐆', '🦓', '🦍', '🦧', '🦣', '🐘', '🦛', '🦏', '🐪', '🐫', '🦒', '🦘',
            '🌸', '💮', '🏵️', '🌹', '🥀', '🌺', '🌻', '🌼', '🌷', '🌱', '🪴', '🌲', '🌳', '🌴', '🌵', '🌾'
        ],
        food: [
            '🍏', '🍎', '🍐', '🍊', '🍋', '🍌', '🍉', '🍇', '🍓', '🫐', '🍈', '🍒', '🍑', '🥭', '🍍', '🥥',
            '🥝', '🍅', '🍆', '🥑', '🥦', '🥬', '🥒', '🌶️', '🫑', '🌽', '🥕', '🫒', '🧄', '🧅', '🥔', '🍠',
            '🥐', '🥯', '🍞', '🥖', '🥨', '🧀', '🥚', '🍳', '🧈', '🥞', '🧇', '🥓', '🥩', '🍗', '🍖', '🦴',
            '🌭', '🍔', '🍟', '🍕', '🫓', '🥪', '🥙', '🧆', '🌮', '🌯', '🫔', '🥗', '🥘', '🫕', '🍝', '🍜',
            '🍲', '🍛', '🍣', '🍱', '🥟', '🦪', '🍤', '🍙', '🍚', '🍘', '🍥', '🥠', '🥮', '🍢', '🍡', '🍧',
            '🍨', '🍦', '🥧', '🧁', '🍰', '🎂', '🍮', '🍭', '🍬', '🍫', '🍿', '🍩', '🍪', '🌰', '🥜', '🍯'
        ],
        activities: [
            '⚽', '🏀', '🏈', '⚾', '🥎', '🎾', '🏐', '🏉', '🥏', '🎱', '🪀', '🏓', '🏸', '🏒', '🏑', '🥍',
            '🏏', '🪃', '🥅', '⛳', '🪁', '🏹', '🎣', '🤿', '🥊', '🥋', '🎽', '🛹', '🛼', '🛷', '⛸️', '🥌',
            '🎿', '⛷️', '🏂', '🪂', '🏋️', '🤼', '🤸', '🤺', '⛹️', '🤾', '🏌️', '🏇', '🧘', '🏄', '🏊', '🤽',
            '🚣', '🧗', '🚵', '🚴', '🏆', '🥇', '🥈', '🥉', '🏅', '🎖️', '🏵️', '🎗️', '🎫', '🎟️', '🎪', '🎭',
            '🎨', '🎬', '🎤', '🎧', '🎼', '🎹', '🥁', '🪘', '🎷', '🎺', '🪗', '🎸', '🪕', '🎻', '🎲', '♟️',
            '🎯', '🎳', '🎮', '🎰', '🧩', '🎴', '🀄', '🃏', '🪄', '🎩', '🪅', '🪆', '🖼️', '🎭', '🧵', '🪡'
        ],
        travel: [
            '🚗', '🚕', '🚙', '🚌', '🚎', '🏎️', '🚓', '🚑', '🚒', '🚐', '🛻', '🚚', '🚛', '🚜', '🦯', '🦽',
            '🦼', '🛴', '🚲', '🛵', '🏍️', '🛺', '🚨', '🚔', '🚍', '🚘', '🚖', '🚡', '🚠', '🚟', '🚃', '🚋',
            '🚞', '🚝', '🚄', '🚅', '🚈', '🚂', '🚆', '🚇', '🚊', '🚉', '✈️', '🛫', '🛬', '🛩️', '💺', '🛰️',
            '🚀', '🛸', '🚁', '🛶', '⛵', '🚤', '🛥️', '🛳️', '⛴️', '🚢', '⚓', '🪝', '⛽', '🚧', '🚦', '🚥',
            '🗺️', '🗿', '🗽', '🗼', '🏰', '🏯', '🏟️', '🎡', '🎢', '🎠', '⛲', '⛱️', '🏖️', '🏝️', '🏜️', '🌋',
            '⛰️', '🏔️', '🗻', '🏕️', '⛺', '🛖', '🏠', '🏡', '🏘️', '🏚️', '🏗️', '🏭', '🏢', '🏬', '🏣', '🏤'
        ],
        objects: [
            '⌚', '📱', '📲', '💻', '⌨️', '🖥️', '🖨️', '🖱️', '🖲️', '🕹️', '🗜️', '💽', '💾', '💿', '📀', '📼',
            '📷', '📸', '📹', '🎥', '📽️', '🎞️', '📞', '☎️', '📟', '📠', '📺', '📻', '🎙️', '🎚️', '🎛️', '🧭',
            '⏱️', '⏲️', '⏰', '🕰️', '⌛', '⏳', '📡', '🔋', '🔌', '💡', '🔦', '🕯️', '🪔', '🧯', '🛢️', '💸',
            '💵', '💴', '💶', '💷', '🪙', '💰', '💳', '💎', '⚖️', '🪜', '🧰', '🪛', '🔧', '🔨', '⚒️', '🛠️',
            '⛏️', '🪚', '🔩', '⚙️', '🪤', '🧱', '⛓️', '🧲', '🔫', '💣', '🧨', '🪓', '🔪', '🗡️', '⚔️', '🛡️',
            '🚬', '⚰️', '🪦', '⚱️', '🏺', '🔮', '📿', '🧿', '💈', '⚗️', '🔭', '🔬', '🕳️', '🩹', '🩺', '💊'
        ],
        symbols: [
            '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔', '❣️', '💕', '💞', '💓', '💗', '💖',
            '💘', '💝', '💟', '☮️', '✝️', '☪️', '🕉️', '☸️', '✡️', '🔯', '🕎', '☯️', '☦️', '🛐', '⛎', '♈',
            '♉', '♊', '♋', '♌', '♍', '♎', '♏', '♐', '♑', '♒', '♓', '🆔', '⚛️', '🉑', '☢️', '☣️',
            '📴', '📳', '🈶', '🈚', '🈸', '🈺', '🈷️', '✴️', '🆚', '💮', '🉐', '㊙️', '㊗️', '🈴', '🈵', '🈹',
            '🈲', '🅰️', '🅱️', '🆎', '🆑', '🅾️', '🆘', '❌', '⭕', '🛑', '⛔', '📛', '🚫', '💯', '💢', '♨️',
            '🚷', '🚯', '🚳', '🚱', '🔞', '📵', '🚭', '❗', '❕', '❓', '❔', '‼️', '⁉️', '🔅', '🔆', '〽️'
        ],
        gestures: [
            '👋', '🤚', '🖐️', '✋', '🖖', '👌', '🤌', '🤏', '✌️', '🤞', '🤟', '🤘', '🤙', '👈', '👉', '👆',
            '🖕', '👇', '☝️', '👍', '👎', '✊', '👊', '🤛', '🤜', '👏', '🙌', '👐', '🤲', '🤝', '🙏', '✍️',
            '💅', '🤳', '💪', '🦾', '🦿', '🦵', '🦶', '👂', '🦻', '👃', '🧠', '🫀', '🫁', '🦷', '🦴', '👀',
            '👁️', '👅', '👄', '👶', '🧒', '👦', '👧', '🧑', '👱', '👨', '🧔', '👩', '🧓', '👴', '👵', '🙍'
        ]
    };

    // Nomes das categorias em português
    const categoryNames = {
        recent: 'Recentes',
        smileys: 'Smileys e pessoas',
        animals: 'Animais e natureza',
        food: 'Comida e bebida',
        activities: 'Atividades',
        travel: 'Viagens e lugares',
        objects: 'Objetos',
        symbols: 'Símbolos',
        gestures: 'Gestos'
    };

    // Categoria atual
    let currentEmojiCategory = 'recent';

    // Carregar emojis recentes do localStorage
    function loadRecentEmojis() {
        try {
            const recent = localStorage.getItem('recentEmojis');
            if (recent) {
                emojiCategories.recent = JSON.parse(recent);
            }
        } catch (e) {
            emojiCategories.recent = [];
        }

        // Se não houver recentes, mostrar alguns populares
        if (emojiCategories.recent.length === 0) {
            emojiCategories.recent = ['😀', '😂', '❤️', '👍', '🙏', '😊', '🔥', '✨'];
        }
    }

    // Salvar emoji nos recentes
    function saveRecentEmoji(emoji) {
        // Remover se já existe
        const index = emojiCategories.recent.indexOf(emoji);
        if (index > -1) {
            emojiCategories.recent.splice(index, 1);
        }

        // Adicionar no início
        emojiCategories.recent.unshift(emoji);

        // Manter apenas os últimos 32
        if (emojiCategories.recent.length > 32) {
            emojiCategories.recent = emojiCategories.recent.slice(0, 32);
        }

        // Salvar no localStorage
        try {
            localStorage.setItem('recentEmojis', JSON.stringify(emojiCategories.recent));
        } catch (e) {}
    }

    // Toggle Emoji Picker
    function toggleEmojiPicker() {
        const picker = document.getElementById('emoji-picker');
        const gifPicker = document.getElementById('gif-picker');

        // Fechar GIF picker se estiver aberto
        gifPicker.classList.add('hidden');

        // Toggle emoji picker
        if (picker.classList.contains('hidden')) {
            picker.classList.remove('hidden');
            loadRecentEmojis();
            loadEmojiCategory('recent');
            setupEmojiPickerEvents();
        } else {
            picker.classList.add('hidden');
        }
    }

    // Carregar emojis de uma categoria
    function loadEmojiCategory(category) {
        currentEmojiCategory = category;
        const grid = document.getElementById('emoji-grid');
        const title = document.getElementById('emoji-category-title');
        const emojis = emojiCategories[category] || [];

        // Atualizar título
        title.textContent = categoryNames[category] || category;

        // Atualizar botões de categoria
        document.querySelectorAll('.emoji-category-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.category === category);
        });

        // Renderizar emojis
        if (emojis.length === 0) {
            grid.innerHTML = `
                <div class="emoji-empty-state" style="grid-column: 1 / -1;">
                    <i class="fas fa-clock"></i>
                    <p>Nenhum emoji recente</p>
                </div>
            `;
        } else {
            grid.innerHTML = emojis.map(emoji => `
                <button type="button" onclick="insertEmoji('${emoji}')">${emoji}</button>
            `).join('');
        }
    }

    // Buscar emojis
    function searchEmojis(query) {
        if (!query) {
            loadEmojiCategory(currentEmojiCategory);
            return;
        }

        const grid = document.getElementById('emoji-grid');
        const title = document.getElementById('emoji-category-title');

        // Buscar em todas as categorias
        let results = [];
        Object.values(emojiCategories).forEach(emojis => {
            emojis.forEach(emoji => {
                if (!results.includes(emoji)) {
                    results.push(emoji);
                }
            });
        });

        // Filtrar (por enquanto, mostrar todos - busca semântica seria mais complexa)
        title.textContent = `Resultados para "${query}"`;

        if (results.length === 0) {
            grid.innerHTML = `
                <div class="emoji-empty-state" style="grid-column: 1 / -1;">
                    <i class="fas fa-search"></i>
                    <p>Nenhum emoji encontrado</p>
                </div>
            `;
        } else {
            grid.innerHTML = results.slice(0, 64).map(emoji => `
                <button type="button" onclick="insertEmoji('${emoji}')">${emoji}</button>
            `).join('');
        }
    }

    // Configurar eventos do emoji picker
    function setupEmojiPickerEvents() {
        // Eventos de categoria
        document.querySelectorAll('.emoji-category-btn').forEach(btn => {
            btn.onclick = () => loadEmojiCategory(btn.dataset.category);
        });

        // Evento de busca
        const searchInput = document.getElementById('emoji-search-input');
        if (searchInput) {
            searchInput.oninput = (e) => {
                const query = e.target.value.trim().toLowerCase();
                if (query.length > 0) {
                    searchEmojis(query);
                } else {
                    loadEmojiCategory(currentEmojiCategory);
                }
            };
        }
    }

    // Inserir emoji no input
    function insertEmoji(emoji) {
        const input = document.getElementById('message-input');
        if (input) {
            const start = input.selectionStart;
            const end = input.selectionEnd;
            const text = input.value;
            input.value = text.substring(0, start) + emoji + text.substring(end);
            input.selectionStart = input.selectionEnd = start + emoji.length;
            input.focus();

            // Salvar nos recentes
            saveRecentEmoji(emoji);
        }
    }

    // Funções para controlar menu de anexos
    function toggleAttachmentMenu() {
        const menu = document.getElementById('attachment-menu');
        const emojiPicker = document.getElementById('emoji-picker');
        const gifPicker = document.getElementById('gif-picker');

        // Fechar outros pickers
        emojiPicker.classList.add('hidden');
        gifPicker.classList.add('hidden');

        // Toggle menu
        menu.classList.toggle('hidden');
    }

    function closeAttachmentMenu() {
        const menu = document.getElementById('attachment-menu');
        menu.classList.add('hidden');
    }

    // Fechar menu ao clicar fora
    document.addEventListener('click', function(event) {
        const menu = document.getElementById('attachment-menu');
        const btn = document.getElementById('attachment-btn');
        const container = document.getElementById('attachment-menu-container');

        if (menu && btn && container && !container.contains(event.target)) {
            menu.classList.add('hidden');
        }
    });

    // Carregar emojis (compatibilidade)
    function loadEmojis() {
        loadEmojiCategory('recent');
    }

    // Toggle GIF Picker
    function toggleGifPicker() {
        const picker = document.getElementById('gif-picker');
        const emojiPicker = document.getElementById('emoji-picker');

        // Fechar emoji picker se estiver aberto
        emojiPicker.classList.add('hidden');

        // Toggle GIF picker
        if (picker.classList.contains('hidden')) {
            picker.classList.remove('hidden');
            loadTrendingGifs();
            setupGifSearch();
        } else {
            picker.classList.add('hidden');
        }
    }

    // Carregar GIFs em alta (usando GIPHY API)
    let gifSearchTimeout;
    async function loadTrendingGifs() {
        const grid = document.getElementById('gif-grid');
        grid.innerHTML = '<div class="col-span-2 text-center py-4"><i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i></div>';

        try {
            // GIPHY API Key pública (beta)
            const apiKey = 'sXpGFDGZs0Dv1mmNFvYaGUvYwKX0PWIh';
            const response = await fetch(`https://api.giphy.com/v1/gifs/trending?api_key=${apiKey}&limit=20&rating=g`);
            const data = await response.json();

            if (data.data) {
                displayGifs(data.data);
            } else {
                throw new Error('Resposta inválida da API');
            }
        } catch (error) {
            console.error('Erro ao carregar GIFs:', error);
            grid.innerHTML = '<div class="col-span-2 text-center py-4 text-red-500">Erro ao carregar GIFs. Tente novamente.</div>';
        }
    }

    // Buscar GIFs
    async function searchGifs(query) {
        const grid = document.getElementById('gif-grid');
        grid.innerHTML = '<div class="col-span-2 text-center py-4"><i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i></div>';

        try {
            const apiKey = 'sXpGFDGZs0Dv1mmNFvYaGUvYwKX0PWIh';
            const response = await fetch(`https://api.giphy.com/v1/gifs/search?api_key=${apiKey}&q=${encodeURIComponent(query)}&limit=20&rating=g`);
            const data = await response.json();

            if (data.data) {
                displayGifs(data.data);
            } else {
                throw new Error('Resposta inválida da API');
            }
        } catch (error) {
            console.error('Erro ao buscar GIFs:', error);
            grid.innerHTML = '<div class="col-span-2 text-center py-4 text-red-500">Erro ao buscar GIFs. Tente novamente.</div>';
        }
    }

    // Exibir GIFs
    function displayGifs(gifs) {
        const grid = document.getElementById('gif-grid');

        if (!gifs || gifs.length === 0) {
            grid.innerHTML = '<div class="col-span-2 text-center py-4 text-gray-500">Nenhum GIF encontrado</div>';
            return;
        }

        grid.innerHTML = gifs.map(gif => {
            // GIPHY retorna URLs diferentes
            const url = gif.images.fixed_height.url;
            const preview = gif.images.fixed_height_small.url;
            return `
            <button type="button" onclick="insertGif('${url}')" class="relative overflow-hidden rounded-lg hover:opacity-80 transition">
                <img src="${preview}" alt="GIF" class="w-full h-32 object-cover">
            </button>
        `;
        }).join('');
    }

    // Configurar busca de GIFs
    function setupGifSearch() {
        const searchInput = document.getElementById('gif-search');

        searchInput.addEventListener('input', function(e) {
            clearTimeout(gifSearchTimeout);
            const query = e.target.value.trim();

            if (query.length === 0) {
                loadTrendingGifs();
            } else if (query.length >= 2) {
                gifSearchTimeout = setTimeout(() => {
                    searchGifs(query);
                }, 500);
            }
        });
    }

    // Inserir GIF (enviar como imagem)
    async function insertGif(gifUrl) {
        if (!currentConversationId) {
            showError('Selecione uma conversa primeiro');
            return;
        }

        // Fechar picker
        document.getElementById('gif-picker').classList.add('hidden');

        showSuccess('Enviando GIF...');

        try {
            // Baixar GIF e enviar como imagem
            const response = await fetch(gifUrl);
            const blob = await response.blob();

            const formData = new FormData();
            formData.append('conversation_id', currentConversationId);
            formData.append('file', blob, 'gif.gif');
            formData.append('type', 'image');

            const sendResponse = await fetch('api/send_media.php', {
                method: 'POST',
                body: formData
            });

            const data = await sendResponse.json();

            if (data.success) {
                showSuccess('GIF enviado com sucesso!');

                // ✅ CORREÇÃO: Adicionar mensagem diretamente ao chat
                if (data.message) {
                    const added = appendMessageToChat(data.message);
                    if (!added) {
                        // Fallback: se não conseguiu adicionar, recarregar
                        console.log('[GIF] Fallback: recarregando mensagens');
                        await fetchMessagesFromServer(currentConversationId, true, false);
                    }
                } else {
                    // Fallback: se não retornou mensagem, recarregar
                    console.log('[GIF] Sem mensagem retornada, recarregando');
                    await fetchMessagesFromServer(currentConversationId, true, false);
                }
            } else {
                showError('Erro: ' + (data.error || 'Erro desconhecido'));
            }
        } catch (error) {
            console.error('Erro:', error);
            showError('Erro ao enviar GIF');
        }
    }

    // Fechar pickers ao clicar fora
    document.addEventListener('click', function(e) {
        const emojiPicker = document.getElementById('emoji-picker');
        const gifPicker = document.getElementById('gif-picker');
        const emojiButton = e.target.closest('[onclick="toggleEmojiPicker()"]');
        const gifButton = e.target.closest('[onclick="toggleGifPicker()"]');

        if (!emojiButton && !emojiPicker.contains(e.target)) {
            emojiPicker.classList.add('hidden');
        }

        if (!gifButton && !gifPicker.contains(e.target)) {
            gifPicker.classList.add('hidden');
        }
    });

    // Variável para filtro atual (definida globalmente para evitar conflitos)
    // Variável para filtro atual (definida globalmente para evitar conflitos)
    window.currentFilter = window.currentFilter || 'inbox';

    // Filtrar conversas por categoria (função base para todos os usuários)
    function filterConversations(filter) {
        window.currentFilter = filter;

        // Atualizar UI dos filtros - Novo estilo
        document.querySelectorAll('.chat-filter-btn').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.filter === filter) {
                btn.classList.add('active');
            }
        });

        const currentUserId = <?php echo $userId; ?>;

        // Filtrar e renderizar conversas
        let filteredConversations = conversations;

        switch (filter) {
            case 'inbox':
                // Conversas não atendidas (fila geral) - excluir encerradas
                filteredConversations = conversations.filter(c =>
                    !c.attended_by && c.status !== 'closed' && !c.is_archived
                );
                break;
            case 'resolved':
                // Conversas resolvidas (status = resolved)
                filteredConversations = conversations.filter(c => c.status === 'resolved');
                break;
            case 'closed':
                // Conversas encerradas/arquivadas (não histórico)
                filteredConversations = conversations.filter(c => c.is_archived && c.status !== 'closed');
                break;
            case 'my_chats':
                // Minhas conversas (que estou atendendo)
                filteredConversations = conversations.filter(c =>
                    c.attended_by == currentUserId && c.status !== 'closed'
                );
                break;
            case 'history':
                // Histórico (conversas encerradas por qualquer atendente)
                filteredConversations = conversations.filter(c => c.status === 'closed');
                break;
            default:
                // Todas: não atendidas + minhas (exclui atendidas por outros)
                filteredConversations = conversations.filter(c =>
                    (!c.attended_by || c.attended_by == currentUserId) && c.status !== 'closed'
                );
        }

        renderConversations(filteredConversations);
        updateFilterCounts();
    }

    // Atualizar contadores dos filtros
    function updateFilterCounts() {
        const currentUserId = <?php echo $userId; ?>;

        // Inbox: conversas não atendidas (fila geral)
        const inboxCount = conversations.filter(c =>
            !c.attended_by && c.status !== 'closed' && !c.is_archived
        ).length;

        // Meus atendimentos: conversas que estou atendendo
        const myChatsCount = conversations.filter(c =>
            c.attended_by == currentUserId && c.status !== 'closed'
        ).length;

        // Resolvidos: status = resolved
        const resolvedCount = conversations.filter(c => c.status === 'resolved').length;

        // Encerrados: arquivados (não histórico)
        const closedCount = conversations.filter(c => c.is_archived && c.status !== 'closed').length;

        // Histórico: conversas encerradas
        const historyCount = conversations.filter(c => c.status === 'closed').length;

        // Atualizar elementos
        const inboxEl = document.getElementById('inbox-count');
        const myChatsEl = document.getElementById('my-chats-count');
        const resolvedEl = document.getElementById('resolved-count');
        const closedEl = document.getElementById('closed-count');

        if (inboxEl) inboxEl.textContent = inboxCount;
        if (myChatsEl) myChatsEl.textContent = myChatsCount;
        if (resolvedEl) resolvedEl.textContent = resolvedCount;
        if (closedEl) closedEl.textContent = closedCount;
    }

    // ===============================
    // MODAL DE ENCAMINHAMENTO
    // ===============================
    function openForwardModal() {
        const modal = document.getElementById('forward-modal');
        if (!modal) return;

        console.log('Opening forward modal...');
        console.log('Conversations available:', conversations.length);

        modal.classList.remove('hidden');
        modal.classList.add('flex');

        // Resetar estado
        forwardModalState.selectedIds.clear();
        forwardModalState.search = '';

        // Limpar input de busca (NÃO dar foco)
        const searchInput = document.getElementById('forward-search');
        if (searchInput) {
            searchInput.value = '';
            searchInput.blur(); // Garantir que não está focado
        }

        // Atualizar preview da mensagem
        const previewText = document.getElementById('forward-preview-text');
        if (previewText && currentContextMessage) {
            if (currentContextMessage.type === 'text') {
                previewText.textContent = currentContextMessage.text || 'Mensagem de texto';
            } else {
                const typeLabels = {
                    'image': '📷 Imagem',
                    'video': '🎥 Vídeo',
                    'audio': '🎵 Áudio',
                    'document': '📄 Documento'
                };
                previewText.textContent = typeLabels[currentContextMessage.type] || '📎 Mídia';
            }
        }

        // IMPORTANTE: Renderizar imediatamente com TUDO que está disponível
        console.log('Rendering frequent contacts...');
        renderFrequentContacts();

        console.log('Rendering forward list...');
        renderForwardList();

        // Carregar contatos em background (não bloqueia)
        loadForwardContacts(true);
    }

    function closeForwardModal() {
        const modal = document.getElementById('forward-modal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    function renderFrequentContacts() {
        const container = document.getElementById('forward-frequent-list');
        if (!container) return;

        const activeChats = Array.isArray(conversations) ? conversations : [];

        // Pegar os 5 contatos mais recentes com mensagens
        const frequent = activeChats
            .filter(c => c.last_message_time)
            .sort((a, b) => new Date(b.last_message_time) - new Date(a.last_message_time))
            .slice(0, 5);

        if (frequent.length === 0) {
            container.innerHTML = '<p class="text-xs text-gray-400">Nenhum contato frequente</p>';
            return;
        }

        container.innerHTML = frequent.map(c => {
            const name = c.name || c.display_name || c.contact_name || 'Sem nome';
            const profilePic = c.profile_picture_url || null;
            const isSelected = forwardModalState.selectedIds.has(c.id);

            console.log('Frequent contact:', name, 'Photo URL:', profilePic);

            return `
            <div class="flex flex-col items-center gap-2 cursor-pointer" onclick="toggleForwardSelection('${c.id}')">
                <div class="relative">
                    <div class="w-14 h-14 rounded-full ${isSelected ? 'ring-4 ring-green-500' : ''} overflow-hidden bg-gray-200 flex items-center justify-center">
                        ${profilePic ? `<img src="${profilePic}" class="w-full h-full object-cover" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                      <i class="fas fa-user text-gray-400 text-xl" style="display:none;"></i>` 
                                    : '<i class="fas fa-user text-gray-400 text-xl"></i>'}
                    </div>
                    ${isSelected ? '<div class="absolute -bottom-1 -right-1 w-6 h-6 bg-green-500 rounded-full flex items-center justify-center"><i class="fas fa-check text-white text-xs"></i></div>' : ''}
                </div>
                <p class="text-xs text-center text-gray-700 dark:text-gray-300 max-w-[60px] truncate">${escapeHtml(name)}</p>
            </div>
        `;
        }).join('');
    }



    let loadedForwardContacts = []; // Store loaded contacts

    async function loadForwardContacts(silent = false) {
        // Se já carregou, não carrega de novo para economizar requisição
        if (loadedForwardContacts.length > 0) {
            renderForwardList();
            return;
        }

        let btn = null;
        let originalContent = '';

        if (!silent) {
            // Fallback para caso seja chamado manualmente (embora botão tenha sido removido)
            btn = event?.target?.closest('button');
            if (btn) {
                originalContent = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Carregando...';
            }
        }

        try {
            const response = await fetch('api/fetch_contacts.php');
            const data = await response.json();

            if (data.success && data.contacts.length > 0) {
                const newContacts = data.contacts.filter(contact => {
                    const hasChat = conversations.some(c => c.phone === contact.phone);
                    return !hasChat;
                }).map(contact => ({
                    id: contact.phone,
                    name: contact.name,
                    phone: contact.phone,
                    profile_pic_url: null,
                    is_contact: true
                }));

                loadedForwardContacts = newContacts;
                renderForwardList();
                // Não mostrar toast de sucesso se for carregamento automático silencioso
                if (!silent) showSuccess(`${newContacts.length} novos contatos carregados`);
            }
        } catch (error) {
            console.error('Erro ao carregar contatos:', error);
            if (!silent) showError('Erro ao carregar contatos');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalContent;
            }
        }
    }

    function renderForwardList() {
        const container = document.getElementById('forward-list');
        if (!container) {
            console.error('Forward list container not found!');
            return;
        }

        console.log('Rendering forward list...');
        console.log('Conversations:', conversations);
        console.log('Loaded contacts:', loadedForwardContacts);

        // Garantir que conversations é um array
        const activeChats = Array.isArray(conversations) ? conversations : [];
        const savedContacts = Array.isArray(loadedForwardContacts) ? loadedForwardContacts : [];

        console.log('Active chats count:', activeChats.length);
        console.log('Saved contacts count:', savedContacts.length);

        // Combinar conversas e contatos carregados
        // Usar Map para evitar duplicatas por telefone
        const uniqueItems = new Map();

        // Adicionar chats primeiro (prioridade)
        activeChats.forEach(c => {
            if (c.phone) {
                uniqueItems.set(c.phone, {
                    ...c,
                    is_contact: false
                });
            } else if (c.id) {
                // Se não tem phone, usar o ID como chave
                uniqueItems.set(c.id, {
                    ...c,
                    is_contact: false
                });
            }
        });

        // Adicionar contatos se não existirem
        savedContacts.forEach(c => {
            if (c.phone && !uniqueItems.has(c.phone)) {
                uniqueItems.set(c.phone, {
                    ...c,
                    is_contact: true
                });
            }
        });

        const allItems = Array.from(uniqueItems.values());
        console.log('Total unique items:', allItems.length);

        // Filtrar
        const filtered = allItems.filter(c => {
            if (!forwardModalState.search) return true;
            const term = forwardModalState.search.toLowerCase();
            const name = c.name || c.display_name || c.contact_name || '';
            const phone = c.phone || '';
            return name.toLowerCase().includes(term) || phone.includes(term);
        });

        console.log('Filtered items:', filtered.length);

        // Ordenar: Conversas recentes primeiro, depois contatos (alfabético)
        filtered.sort((a, b) => {
            if (a.is_contact && !b.is_contact) return 1;
            if (!a.is_contact && b.is_contact) return -1;

            const nameA = a.name || a.display_name || a.contact_name || '';
            const nameB = b.name || b.display_name || b.contact_name || '';
            return nameA.localeCompare(nameB);
        });

        if (filtered.length === 0) {
            console.log('No items to display, showing empty state');
            container.innerHTML = `
            <div class="p-8 text-center text-gray-500">
                <i class="fas fa-inbox text-4xl mb-3 text-gray-300"></i>
                <p class="font-medium">Nenhuma conversa disponível</p>
                <p class="text-sm mt-2">Inicie uma conversa primeiro</p>
            </div>
        `;
            return;
        }

        console.log('Rendering', filtered.length, 'items...');

        container.innerHTML = filtered.map(c => {
            const name = c.name || c.display_name || c.contact_name || 'Sem nome';
            const phone = c.phone || 'Sem número';
            const profilePic = c.profile_picture_url || null;
            const isSelected = forwardModalState.selectedIds.has(c.id);
            const lastMessage = c.last_message_text || c.last_message || '';

            return `
        <div class="flex items-center justify-between p-3 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg cursor-pointer transition ${isSelected ? 'bg-green-50 dark:bg-green-900' : ''}"
             onclick="toggleForwardSelection('${c.id}')">
            <div class="flex items-center gap-3 flex-1 min-w-0">
                <div class="w-12 h-12 rounded-full ${c.is_contact ? 'bg-blue-100 text-blue-600' : 'bg-gray-200'} flex items-center justify-center overflow-hidden flex-shrink-0">
                    ${profilePic ? 
                        `<img src="${profilePic}" class="w-full h-full object-cover" alt="${escapeHtml(name)}" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                         <i class="fas fa-user text-gray-400" style="display:none;"></i>` 
                        : (c.is_contact ? '<i class="fas fa-address-book"></i>' : '<i class="fas fa-user text-gray-400"></i>')}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <p class="font-medium text-gray-900 dark:text-white truncate">
                            ${escapeHtml(name)}
                        </p>
                        ${c.is_contact ? '<span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full flex-shrink-0">Contato</span>' : ''}
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 truncate">${lastMessage ? escapeHtml(lastMessage.substring(0, 40)) + (lastMessage.length > 40 ? '...' : '') : phone}</p>
                </div>
            </div>
            <div class="w-5 h-5 rounded-full border-2 ${isSelected ? 'bg-green-500 border-green-500' : 'border-gray-300'} flex items-center justify-center flex-shrink-0">
                ${isSelected ? '<i class="fas fa-check text-white text-xs"></i>' : ''}
            </div>
        </div>
    `
        }).join('');

        console.log('Render complete!');
    }


    function toggleForwardSelection(chatId) {
        if (forwardModalState.selectedIds.has(chatId)) {
            forwardModalState.selectedIds.delete(chatId);
        } else {
            forwardModalState.selectedIds.add(chatId);
        }

        // Atualizar ambas as seções
        renderFrequentContacts();
        renderForwardList();

        // Atualizar contador e botão
        const countSpan = document.getElementById('forward-selected-count');
        const btn = document.getElementById('forward-submit-btn');
        const count = forwardModalState.selectedIds.size;

        if (countSpan) {
            countSpan.textContent = `${count} selecionado(s)`;
        }

        if (btn) {
            btn.disabled = count === 0;
        }
    }


    // Setup search listener for forward modal
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('forward-search');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                forwardModalState.search = e.target.value;
                renderForwardList();
            });
        }
    });


    async function ensureConversation(phone, name) {
        // Verificar se já existe conversa localmente
        const existingChat = conversations.find(c => c.phone === phone || c.id === phone);
        if (existingChat) return existingChat.id;

        // Tentar criar ou obter conversa existente do backend
        try {
            const formData = new FormData();
            formData.append('action', 'create');
            formData.append('phone', phone);
            formData.append('name', name || phone);

            const response = await fetch('api/chat_actions.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (data.success && data.conversation_id) {
                return data.conversation_id;
            }
        } catch (error) {
            console.error('Erro ao garantir conversa:', error);
        }
        return null;
    }

    async function handleManualForward(event) {
        event.preventDefault();
        const phoneInput = document.getElementById('forward-phone');
        if (!phoneInput) return;

        const phone = phoneInput.value.replace(/\D/g, '');
        if (phone.length < 10) {
            showError('Número de telefone inválido');
            return;
        }

        const tempId = `temp_${phone}`;
        forwardModalState.selectedIds.add(tempId);

        try {
            const conversationId = await ensureConversation(phone, phone);

            if (conversationId) {
                forwardModalState.selectedIds.delete(tempId);
                forwardModalState.selectedIds.add(conversationId);
                await forwardMessages();
                closeForwardModal();
                phoneInput.value = '';
            } else {
                forwardModalState.selectedIds.delete(tempId);
                showError('Não foi possível validar o número para envio.');
            }

        } catch (error) {
            console.error('Erro no encaminhamento manual:', error);
            showError('Erro ao processar número');
            forwardModalState.selectedIds.delete(tempId);
        }
    }

    async function forwardMessages() {
        if (forwardModalState.selectedIds.size === 0) {
            showError('Selecione pelo menos um destinatário');
            return;
        }

        if (!currentContextMessage) {
            showError('Nenhuma mensagem selecionada para encaminhar');
            return;
        }

        const btn = document.getElementById('forward-submit-btn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Enviando...';
        }

        try {
            const selectedIds = Array.from(forwardModalState.selectedIds);
            let successCount = 0;

            for (const id of selectedIds) {
                if (id.startsWith('temp_')) continue;

                let targetChatId = id;

                // Se for um contato (verificar se está em loadedForwardContacts e não em conversations)
                const isContact = loadedForwardContacts.find(c => c.id === id);
                if (isContact) {
                    const realId = await ensureConversation(isContact.phone, isContact.name);
                    if (realId) {
                        targetChatId = realId;
                    } else {
                        console.error('Falha ao criar conversa para contato:', isContact.name);
                        continue;
                    }
                }

                const formData = new FormData();
                formData.append('action', 'send_message');
                formData.append('conversation_id', targetChatId);

                // Preparar conteúdo
                if (currentContextMessage.type === 'text') {
                    formData.append('type', 'text');
                    formData.append('message', currentContextMessage.text);
                } else {
                    formData.append('type', currentContextMessage.type);
                    if (currentContextMessage.text) formData.append('caption', currentContextMessage.text);
                    if (currentContextMessage.mediaUrl) formData.append('media_url', currentContextMessage.mediaUrl);
                }

                const response = await fetch('api/chat_send_message.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    successCount++;
                }
            }

            if (successCount > 0) {
                showSuccess(`Mensagem encaminhada para ${successCount} conversa(s)`);
                closeForwardModal();
            } else {
                showError('Falha ao encaminhar mensagem');
            }

        } catch (error) {
            console.error('Erro ao encaminhar:', error);
            showError('Erro ao encaminhar mensagem');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-share mr-2"></i>Encaminhar';
            }
        }
    }
</script>

<?php if ($is_supervisor): ?>
    <!-- Modal Nota Interna -->
    <div id="internal-note-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-2xl w-full">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <i class="fas fa-sticky-note text-blue-600"></i>
                        Adicionar Nota Interna
                    </h3>
                    <button onclick="closeInternalNoteModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" style="background: none !important; border: none !important; border-radius: 0 !important; box-shadow: none !important; cursor: pointer !important; color: #9ca3af !important; font-size: 24px !important; padding: 4px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; transition: color 0.2s !important;">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Esta nota não será visível para o cliente. Use para registrar informações importantes sobre o atendimento.
                </p>
                <textarea id="internal-note-text" rows="5"
                    class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                    placeholder="Digite sua nota aqui..."></textarea>
                <div class="mt-4 flex justify-end gap-3">
                    <button onclick="closeInternalNoteModal()"
                        class="px-6 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Cancelar
                    </button>
                    <button onclick="saveInternalNote()"
                        class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                        <i class="fas fa-save mr-2"></i>Salvar Nota
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Transferir Conversa -->
    <div id="transfer-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-2xl w-full">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <i class="fas fa-exchange-alt text-purple-600"></i>
                        Transferir Conversa
                    </h3>
                    <button onclick="closeTransferModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" style="background: none !important; border: none !important; border-radius: 0 !important; box-shadow: none !important; cursor: pointer !important; color: #9ca3af !important; font-size: 24px !important; padding: 4px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; transition: color 0.2s !important;">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Transferir para:
                    </label>
                    <select id="transfer-type" onchange="toggleTransferType()"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white">
                        <option value="user">Atendente Específico</option>
                        <option value="department">Setor</option>
                    </select>
                </div>

                <div id="transfer-user-div">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Selecione o Atendente:
                    </label>
                    <select id="transfer-user"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white">
                        <option value="">Carregando...</option>
                    </select>
                </div>

                <div id="transfer-department-div" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Selecione o Setor:
                    </label>
                    <select id="transfer-department"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white">
                        <option value="">Selecione...</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Motivo da Transferência (opcional):
                    </label>
                    <textarea id="transfer-reason" rows="3"
                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white"
                        placeholder="Ex: Cliente solicitou falar com setor financeiro..."></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <button onclick="closeTransferModal()"
                        class="px-6 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Cancelar
                    </button>
                    <button onclick="executeTransfer()"
                        class="px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors">
                        <i class="fas fa-exchange-alt mr-2"></i>Transferir
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Enviar para Kanban -->
    <div id="kanban-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-lg w-full">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <i class="fas fa-columns text-purple-600"></i>
                        Enviar para Kanban
                    </h3>
                    <button onclick="closeKanbanModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" style="background: none !important; border: none !important; border-radius: 0 !important; box-shadow: none !important; cursor: pointer !important; color: #9ca3af !important; font-size: 24px !important; padding: 4px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; transition: color 0.2s !important;">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Quadro:
                    </label>
                    <select id="kanban-board" onchange="loadKanbanColumns()"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white">
                        <option value="">Carregando...</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Coluna:
                    </label>
                    <select id="kanban-column"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white">
                        <option value="">Selecione um quadro primeiro</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Título do Card:
                    </label>
                    <input type="text" id="kanban-title"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white"
                        placeholder="Título será gerado automaticamente se vazio">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Valor (R$):
                        </label>
                        <input type="number" id="kanban-value" step="0.01" min="0"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white"
                            placeholder="0,00">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Prioridade:
                        </label>
                        <select id="kanban-priority"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white">
                            <option value="low">🟢 Baixa</option>
                            <option value="normal" selected>🟡 Normal</option>
                            <option value="high">🟠 Alta</option>
                            <option value="urgent">🔴 Urgente</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Data de Vencimento:
                    </label>
                    <input type="date" id="kanban-due-date"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white">
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" id="kanban-copy-notes" checked class="w-4 h-4 text-purple-600 rounded">
                    <label for="kanban-copy-notes" class="text-sm text-gray-700 dark:text-gray-300">
                        Copiar notas internas para o card
                    </label>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <button onclick="closeKanbanModal()"
                        class="px-6 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Cancelar
                    </button>
                    <button onclick="transferToKanban()"
                        class="px-6 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white rounded-lg transition-colors">
                        <i class="fas fa-columns mr-2"></i>Criar Card
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ============================================
        // FUNÇÕES DO KANBAN E MODAIS DE SUPERVISOR
        // ============================================
        
        console.log('Script de supervisor carregado');
        console.log('Tipo de usuário:', '<?php echo $userType; ?>');
        console.log('É supervisor:', <?php echo $is_supervisor ? 'true' : 'false'; ?>);

        let kanbanBoards = [];
        let kanbanColumns = [];

        // Abrir modal do Kanban
        async function openKanbanModal() {
            console.log('openKanbanModal chamado');
            console.log('currentConversationId:', currentConversationId);
            
            if (!currentConversationId) {
                alert('Selecione uma conversa primeiro');
                return;
            }

            const modal = document.getElementById('kanban-modal');
            console.log('Modal encontrado:', modal);
            
            if (!modal) {
                console.error('Modal kanban-modal não encontrado no DOM');
                alert('Erro: Modal não encontrado');
                return;
            }
            
            modal.classList.remove('hidden');
            modal.classList.add('flex');

            // Carregar quadros
            await loadKanbanBoards();
        }

        // Fechar modal do Kanban
        function closeKanbanModal() {
            const modal = document.getElementById('kanban-modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // Carregar opções de transferência (atendentes/departamentos)
        async function loadTransferOptions() {
            try {
                // Aqui você pode carregar a lista de atendentes disponíveis
                console.log('Carregando opções de transferência...');
                // TODO: Implementar carregamento de atendentes via API
            } catch (error) {
                console.error('Erro ao carregar opções de transferência:', error);
            }
        }

        // Transferir conversa para o Kanban
        async function transferToKanban() {
            const boardId = document.getElementById('kanban-board').value;
            const columnId = document.getElementById('kanban-column').value;
            const title = document.getElementById('kanban-title').value;
            const value = document.getElementById('kanban-value').value;
            const priority = document.getElementById('kanban-priority').value;
            const dueDate = document.getElementById('kanban-due-date').value;
            const copyNotes = document.getElementById('kanban-copy-notes').checked;

            if (!boardId) {
                alert('Selecione um quadro');
                return;
            }

            if (!columnId) {
                alert('Selecione uma coluna');
                return;
            }

            try {
                const response = await fetch('api/kanban/transfer_from_chat.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        conversation_id: currentConversationId,
                        board_id: boardId,
                        column_id: columnId,
                        title: title,
                        value: value || null,
                        priority: priority,
                        due_date: dueDate || null,
                        copy_notes: copyNotes
                    })
                });

                const data = await response.json();

                if (data.success) {
                    closeKanbanModal();

                    // Mostrar mensagem de sucesso com link
                    const goToKanban = confirm(`${data.message}\n\nDeseja ir para o Kanban agora?`);

                    if (goToKanban) {
                        window.location.href = data.kanban_url;
                    }
                } else {
                    alert(data.error || 'Erro ao transferir para o Kanban');
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao conectar com o servidor');
            }
        }

        // Desativar autoplay de vídeos e áquivos de mídia (versão simplificada)
        function disableMediaAutoplay() {
            // Apenas garante que áudios não toquem automaticamente
            // MAS não interfere com áudios que o usuário iniciou manualmente
            const audios = document.querySelectorAll('audio');
            audios.forEach(audio => {
                try {
                    // Só pausa se o áudio não foi iniciado pelo usuário
                    // Verifica se tem o atributo que indica que foi iniciado manualmente
                    if (!audio.hasAttribute('data-user-playing') && !audio.paused) {
                        audio.pause();
                        audio.currentTime = 0;
                    }
                } catch (error) {
                    console.log('Erro ao pausar áudio:', error);
                }
            });
        }

        // Função para carregar mensagens do chat (mantida para compatibilidade)
        function loadChatMessages() {

            // Remover todos os atributos de autoplay
            video.autoplay = false;
            video.muted = true;
            video.preload = 'metadata';

            // Remover eventos de play automático
            video.removeEventListener('play', autoPlayHandler);

            // Adicionar controle se não tiver
            if (!video.hasAttribute('controls')) {
                video.setAttribute('controls', 'true');
            }

            // Forçar dimensões estáveis
            video.style.maxWidth = '100%';
            video.style.height = 'auto';
            video.style.display = 'block';

            // Adicionar classe de estabilidade
            video.classList.add('media-stabilized');

        } catch (error) {
            console.log('Erro ao desativar vídeo:', error);
        }
        });

        // Pausar todos os áudios (mas manter preload="none" para não carregar automaticamente)
        const audios = document.querySelectorAll('audio');
        audios.forEach(audio => {
            try {
                audio.pause();
                audio.autoplay = false;
                audio.preload = 'none'; // Não carregar automaticamente
                if (!audio.hasAttribute('controls')) {
                    audio.setAttribute('controls', 'true');
                }
                audio.classList.add('media-stabilized');
            } catch (error) {
                console.log('Erro ao desativar áudio:', error);
            }
        });

        // Forçar estabilidade em containers de mídia
        const mediaContainers = document.querySelectorAll('.chat-message-video-container, .chat-message-image, .chat-message-gif-container');
        mediaContainers.forEach(container => {
            container.classList.add('media-stabilized');
            container.style.minHeight = container.style.minHeight || '150px';
            container.style.maxHeight = container.style.maxHeight || '200px';
        });

        // NÃO forçar GIFs estáticos - deixar comportamento natural do WhatsApp Web
        }

        // Função para tratar play automático
        function autoPlayHandler(event) {
            event.preventDefault();
            event.target.pause();
            return false;
        }

        // DESABILITADO - Chamar disableMediaAutoplay após renderizar mensagens estava causando AbortError
        // const originalRenderMessages = renderMessages;
        // if (typeof originalRenderMessages === 'function') {
        //     window.renderMessages = function(msgList, forceScroll = false) {
        //         const result = originalRenderMessages.call(this, msgList, forceScroll);
        //         // Desativar autoplay após renderizar
        //         setTimeout(disableMediaAutoplay, 100);
        //         return result;
        //     };
        // }
    </script>

<?php endif; ?>

<!-- Scripts de Atendimento (sempre carregados) -->
<script src="/assets/js/chat_supervisor.js"></script>

<!-- Sistema de Notificações Popup -->
<script src="/assets/js/chat_notifications.js"></script>

<!-- CSS FINAL PARA SOBRESCREVER chat-modern.css - FORÇAR GIF ESTÁTICO -->
<style>
    /* SOBRESCRITURA FORÇADA - maior especificidade que chat-modern.css */
    body .chat-page-wrapper .chat-main-container .chat-area-container .chat-messages-area .chat-message .chat-message-bubble .chat-message-video-container,
    body .chat-page-wrapper .chat-main-container .chat-area-container .chat-messages-area .chat-message .chat-message-bubble .chat-message-gif-container {
        position: relative !important;
        max-width: 280px !important;
        min-height: 150px !important;
        max-height: 200px !important;
        border-radius: 12px !important;
        overflow: hidden !important;
        background: rgba(0, 0, 0, 0.05) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        contain: layout style !important;
        will-change: auto !important;
    }

    /* FORÇAR IMG em vez de VIDEO para GIFs */
    body .chat-page-wrapper .chat-main-container .chat-area-container .chat-messages-area .chat-message .chat-message-bubble .chat-message-video-container video,
    body .chat-page-wrapper .chat-main-container .chat-area-container .chat-messages-area .chat-message .chat-message-bubble .chat-video-preview {
        display: none !important;
    }

    /* BLOQUEAR COMPLETAMENTE QUALQUER VIDEO EM GIF */
    body .chat-page-wrapper .chat-main-container .chat-area-container .chat-messages-area .chat-message .chat-message-bubble[src*=".gif"] video,
    body .chat-page-wrapper .chat-main-container .chat-area-container .chat-messages-area .chat-message .chat-message-bubble[src*="gif"] video {
        display: none !important;
        visibility: hidden !important;
        opacity: 0 !important;
        width: 0 !important;
        height: 0 !important;
    }

    /* FORÇAR IMAGEM ESTÁTICA PARA GIFS */
    body .chat-page-wrapper .chat-main-container .chat-area-container .chat-messages-area .chat-message .chat-message-bubble .chat-message-gif-container img,
    body .chat-page-wrapper .chat-main-container .chat-area-container .chat-messages-area .chat-message .chat-message-bubble img[src*=".gif"] {
        display: block !important;
        width: 100% !important;
        height: auto !important;
        max-height: 200px !important;
        object-fit: contain !important;
        cursor: pointer !important;
        animation: none !important;
        transition: none !important;
        animation-play-state: paused !important;
    }

    /* ESCONDER OVERLAY DE PLAY EM GIFS */
    body .chat-page-wrapper .chat-main-container .chat-area-container .chat-messages-area .chat-message .chat-message-bubble .video-play-overlay,
    body .chat-page-wrapper .chat-main-container .chat-area-container .chat-messages-area .chat-message .chat-message-bubble .video-play-overlay {
        display: none !important;
        visibility: hidden !important;
        opacity: 0 !important;
    }

    /* FORÇAR INDICADOR GIF VISÍVEL */
    body .chat-page-wrapper .chat-main-container .chat-area-container .chat-messages-area .chat-message .chat-message-bubble .gif-indicator {
        position: absolute !important;
        top: 8px !important;
        right: 8px !important;
        background: rgba(0, 0, 0, 0.6) !important;
        color: white !important;
        padding: 4px 8px !important;
        border-radius: 12px !important;
        font-size: 11px !important;
        font-weight: 600 !important;
        pointer-events: none !important;
        z-index: 5 !important;
        display: block !important;
        visibility: visible !important;
    }
</style>

<!-- JavaScript FINAL PARA FORÇAR GIF ESTÁTICO E CONFIGURAR VÍDEOS -->
<script>
    // FORÇAR SUBSTITUIÇÃO DE VÍDEOS POR IMAGENS ESTÁTICAS (apenas GIFs)
    function forceGifAsStaticImage() {
        // Encontrar todos os vídeos em mensagens que possam ser GIFs
        const videos = document.querySelectorAll('.chat-message video, .chat-video-preview');

        videos.forEach(video => {
            try {
                // Verificar se o vídeo pode ser um GIF
                const src = video.src || video.querySelector('source')?.src || '';
                const isGif = src.toLowerCase().includes('.gif') || video.poster?.toLowerCase().includes('.gif');

                if (isGif) {
                    // Criar imagem estática para substituir o vídeo GIF
                    const img = document.createElement('img');
                    img.src = src;
                    img.alt = 'GIF';
                    img.className = 'chat-gif-static forced-replacement';
                    img.style.cssText = `
                    width: 100% !important;
                    height: auto !important;
                    max-height: 200px !important;
                    object-fit: contain !important;
                    display: block !important;
                    cursor: pointer !important;
                    animation: none !important;
                    transition: none !important;
                `;
                    img.onclick = () => window.open(src, '_blank');

                    // Criar container com indicador GIF
                    const container = document.createElement('div');
                    container.className = 'chat-message-gif-container forced-replacement';
                    container.style.cssText = `
                    position: relative !important;
                    max-width: 280px !important;
                    min-height: 150px !important;
                    max-height: 200px !important;
                    border-radius: 12px !important;
                    overflow: hidden !important;
                    background: rgba(0,0,0,0.05) !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                `;

                    // Adicionar indicador GIF
                    const indicator = document.createElement('div');
                    indicator.className = 'gif-indicator';
                    indicator.textContent = 'GIF';
                    indicator.style.cssText = `
                    position: absolute !important;
                    top: 8px !important;
                    right: 8px !important;
                    background: rgba(0,0,0,0.6) !important;
                    color: white !important;
                    padding: 4px 8px !important;
                    border-radius: 12px !important;
                    font-size: 11px !important;
                    font-weight: 600 !important;
                    pointer-events: none !important;
                    z-index: 5 !important;
                `;

                    container.appendChild(img);
                    container.appendChild(indicator);

                    // Substituir o vídeo pelo container da imagem
                    const videoContainer = video.closest('.chat-message-video-container') || video.parentElement;
                    if (videoContainer) {
                        videoContainer.replaceWith(container);
                    } else {
                        video.replaceWith(container);
                    }
                }
                // Vídeos normais (não GIF) - NÃO esconder, deixar funcionar
            } catch (error) {
                console.log('Erro ao processar vídeo:', error);
            }
        });
    }

    // Configurar vídeos normais com duração e controles
    function setupVideoPlayers() {
        const videoContainers = document.querySelectorAll('.chat-video-container');

        videoContainers.forEach(container => {
            if (container.dataset.setup) return; // Já configurado
            container.dataset.setup = 'true';

            const video = container.querySelector('video');
            const durationSpan = container.querySelector('.video-duration');
            const playOverlay = container.querySelector('.video-play-overlay');

            if (video && durationSpan) {
                video.addEventListener('loadedmetadata', function() {
                    const duration = Math.floor(video.duration);
                    const minutes = Math.floor(duration / 60);
                    const seconds = duration % 60;
                    durationSpan.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                });

                // Esconder overlay quando vídeo começa a tocar
                video.addEventListener('play', function() {
                    if (playOverlay) playOverlay.style.display = 'none';
                });

                // Mostrar overlay quando vídeo pausa
                video.addEventListener('pause', function() {
                    if (playOverlay && video.currentTime < video.duration) {
                        playOverlay.style.display = 'flex';
                    }
                });

                // Mostrar overlay quando vídeo termina
                video.addEventListener('ended', function() {
                    if (playOverlay) playOverlay.style.display = 'flex';
                    video.currentTime = 0;
                });
            }
        });
    }

    // Executar imediatamente e periodicamente
    forceGifAsStaticImage();
    setupVideoPlayers();
    setInterval(() => {
        forceGifAsStaticImage();
        setupVideoPlayers();
    }, 1000);

    // Também executar quando novas mensagens forem adicionadas
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length > 0) {
                setTimeout(() => {
                    forceGifAsStaticImage();
                    setupVideoPlayers();
                }, 100);
            }
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

    console.log('✅ Forçando GIFs como imagens estáticas - Sistema ativado');
</script>

<!-- Badge de Email para Avatares Multi-Canal -->
<script src="/assets/js/email-badge.js"></script>

<!-- Sincronização Microsoft Teams -->
<script src="/assets/js/teams-sync.js"></script>

<!-- Polling em Tempo Real do Teams -->
<script src="/assets/js/teams-realtime.js?v=<?php echo time(); ?>"></script>

<!-- CSS INLINE PARA FORÇAR ESTILO DOS BOTÕES DE AÇÃO - RESETAR TUDO -->
<style>
    /* RESET COMPLETO dos botões de ação - remover TODOS os estilos */
    .chat-contact-header .chat-action-buttons button.chat-action-btn,
    .chat-contact-header .chat-action-buttons button.chat-action-btn.edit,
    .chat-contact-header .chat-action-buttons button.chat-action-btn.refresh,
    .chat-contact-header .chat-action-buttons button.chat-action-btn.close {
        all: unset !important;
        cursor: pointer !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: auto !important;
        height: auto !important;
        padding: 8px !important;
        margin: 0 4px !important;
        font-size: 18px !important;
        transition: opacity 0.2s ease, transform 0.2s ease !important;
    }

    /* Cores específicas para cada botão */
    .chat-contact-header .chat-action-buttons button.chat-action-btn.edit {
        color: #60a5fa !important;
    }

    .chat-contact-header .chat-action-buttons button.chat-action-btn.refresh {
        color: #10b981 !important;
    }

    .chat-contact-header .chat-action-buttons button.chat-action-btn.close {
        color: #f87171 !important;
    }

    /* Hover - apenas opacidade e leve escala */
    .chat-contact-header .chat-action-buttons button.chat-action-btn:hover {
        opacity: 0.7 !important;
        transform: scale(1.1) !important;
    }

    /* Garantir que o ícone dentro do botão não tenha estilos extras */
    .chat-contact-header .chat-action-buttons button.chat-action-btn i {
        font-size: inherit !important;
        color: inherit !important;
    }
</style>

<?php
if (!$isSPA) {
    require_once 'includes/footer_spa.php';
}
?>