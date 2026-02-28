<?php
// Configurar headers para evitar cache
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar se o usuário está logado
requireLogin();

// Verificar se a página foi especificada
if (!isset($_GET['page'])) {
    http_response_code(400);
    echo '<div class="p-6 text-center"><p class="text-red-600">Erro: Página não especificada</p></div>';
    exit;
}

$page = sanitize($_GET['page']);
$userId = $_SESSION['user_id'];

// Função para incluir conteúdo de página existente
function includePageContent($filePath)
{
    if (!file_exists($filePath)) {
        return false;
    }

    try {
        // Capturar apenas o conteúdo principal da página
        ob_start();
        include $filePath;
        $content = ob_get_clean();

        // Extrair apenas o conteúdo entre <main> e </main> ou todo o body
        if (preg_match('/<main[^>]*>(.*?)<\/main>/s', $content, $matches)) {
            return $matches[1];
        } elseif (preg_match('/<body[^>]*>(.*?)<\/body>/s', $content, $matches)) {
            // Remover header e footer se existirem
            $bodyContent = $matches[1];
            $bodyContent = preg_replace('/<header[^>]*>.*?<\/header>/s', '', $bodyContent);
            $bodyContent = preg_replace('/<footer[^>]*>.*?<\/footer>/s', '', $bodyContent);
            $bodyContent = preg_replace('/<nav[^>]*>.*?<\/nav>/s', '', $bodyContent);
            return $bodyContent;
        }
        return $content;
    } catch (Exception $e) {
        error_log("Erro ao incluir página: " . $e->getMessage());
        return false;
    }
}

// Roteamento das páginas
switch ($page) {
    case 'dashboard':
        // Retornar conteúdo do dashboard atual
        echo '<div class="dashboard-content">Dashboard em construção para SPA</div>';
        break;

    case 'dispatch':
        // Carregar página de disparo
        echo '
        <div class="p-6">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Disparo de Mensagens</h2>
                <p class="text-gray-600">Envie mensagens em massa para seus contatos.</p>
            </div>
            
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-yellow-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                            Esta página está sendo carregada via SPA. Para acessar todas as funcionalidades, 
                            <a href="/dispatch.php" class="font-medium underline hover:text-yellow-800">clique aqui</a> 
                            para abrir a página completa.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-center py-12">
                    <i class="fas fa-paper-plane text-4xl text-green-500 mb-4"></i>
                    <h4 class="text-lg font-medium text-gray-600 mb-2">Disparo de Mensagens</h4>
                    <p class="text-gray-500 mb-4">Selecione contatos e envie mensagens personalizadas.</p>
                    <a href="/dispatch.php" class="inline-block bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg">
                        <i class="fas fa-external-link-alt mr-2"></i>Abrir Página Completa
                    </a>
                </div>
            </div>
        </div>';
        break;

    case 'categories':
    case 'contacts':
    case 'my_instance':
    case 'setup_2fa':
        // Páginas que devem ser abertas em página completa
        $pageMap = [
            'categories' => ['title' => 'Categorias e Tags', 'url' => '/categories.php', 'icon' => 'fa-tags'],
            'contacts' => ['title' => 'Meus Contatos', 'url' => '/contacts.php', 'icon' => 'fa-users'],
            'my_instance' => ['title' => 'Minha Instância', 'url' => '/my_instance.php', 'icon' => 'fa-cog'],
            'setup_2fa' => ['title' => 'Configurar 2FA', 'url' => '/setup_2fa.php', 'icon' => 'fa-shield-alt']
        ];

        $pageInfo = $pageMap[$page];

        echo '
        <div class="p-6">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-2">' . $pageInfo['title'] . '</h2>
                <p class="text-gray-600">Acesse a página completa para utilizar todas as funcionalidades.</p>
            </div>
            
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            Esta página requer funcionalidades avançadas. 
                            <a href="' . $pageInfo['url'] . '" class="font-medium underline hover:text-blue-800">Clique aqui</a> 
                            para abrir a página completa.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-center py-12">
                    <i class="fas ' . $pageInfo['icon'] . ' text-4xl text-blue-500 mb-4"></i>
                    <h4 class="text-lg font-medium text-gray-600 mb-2">' . $pageInfo['title'] . '</h4>
                    <p class="text-gray-500 mb-4">Acesse todas as funcionalidades desta página.</p>
                    <a href="' . $pageInfo['url'] . '" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg">
                        <i class="fas fa-external-link-alt mr-2"></i>Abrir Página Completa
                    </a>
                </div>
            </div>
        </div>';
        break;

    case 'fields':
        // Página de campos personalizados
        echo '
        <div class="p-6">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Campos Personalizados</h2>
                <p class="text-gray-600">Gerencie campos personalizados para seus contatos.</p>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-800">Seus Campos</h3>
                    <button class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-plus mr-2"></i>Novo Campo
                    </button>
                </div>
                
                <div class="text-center py-12">
                    <i class="fas fa-list text-4xl text-gray-300 mb-4"></i>
                    <h4 class="text-lg font-medium text-gray-600 mb-2">Nenhum campo personalizado</h4>
                    <p class="text-gray-500">Crie campos personalizados para organizar melhor seus contatos.</p>
                </div>
            </div>
        </div>';
        break;

    case 'message_templates':
        if (!defined('IS_SPA_REQUEST')) {
            define('IS_SPA_REQUEST', true);
        }
        ob_start();
        include '../message_templates.php';
        $content = ob_get_clean();
        if (trim($content) === '') {
            echo '<div class="p-6 text-center text-gray-600">Conteúdo indisponível no momento.</div>';
        } else {
            echo $content;
        }
        break;

    case 'templates':
        // Página de modelos de mensagem
        echo '
        <div class="p-6">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Modelos de Mensagem</h2>
                <p class="text-gray-600">Crie e gerencie modelos de mensagem reutilizáveis.</p>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-800">Seus Modelos</h3>
                    <button class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-plus mr-2"></i>Novo Modelo
                    </button>
                </div>
                
                <div class="text-center py-12">
                    <i class="fas fa-envelope text-4xl text-gray-300 mb-4"></i>
                    <h4 class="text-lg font-medium text-gray-600 mb-2">Nenhum modelo criado</h4>
                    <p class="text-gray-500">Crie modelos de mensagem para agilizar seus disparos.</p>
                </div>
            </div>
        </div>';
        break;

    case 'appointments':
        // Página de agendamentos
        echo '
        <div class="p-6">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Agendamentos</h2>
                <p class="text-gray-600">Gerencie seus agendamentos de mensagens.</p>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-800">Mensagens Agendadas</h3>
                    <button class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-clock mr-2"></i>Novo Agendamento
                    </button>
                </div>
                
                <div class="text-center py-12">
                    <i class="fas fa-calendar text-4xl text-gray-300 mb-4"></i>
                    <h4 class="text-lg font-medium text-gray-600 mb-2">Nenhum agendamento</h4>
                    <p class="text-gray-500">Agende mensagens para serem enviadas automaticamente.</p>
                </div>
            </div>
        </div>';
        break;

    case 'chat':
        // Carregar página completa do chat
        if (!defined('IS_SPA_REQUEST')) {
            define('IS_SPA_REQUEST', true);
        }
        ob_start();
        include '../chat.php';
        $content = ob_get_clean();

        // Extrair apenas o conteúdo principal (remover header/footer)
        if (preg_match('/<main[^>]*>(.*?)<\/main>/s', $content, $matches)) {
            echo $matches[1];
        } elseif (preg_match('/<body[^>]*>(.*?)<\/body>/s', $content, $matches)) {
            // Remover elementos de navegação
            $bodyContent = $matches[1];
            $bodyContent = preg_replace('/<header[^>]*>.*?<\/header>/s', '', $bodyContent);
            $bodyContent = preg_replace('/<footer[^>]*>.*?<\/footer>/s', '', $bodyContent);
            $bodyContent = preg_replace('/<nav[^>]*>.*?<\/nav>/s', '', $bodyContent);
            $bodyContent = preg_replace('/<div[^>]*class="[^"]*sidebar[^"]*"[^>]*>.*?<\/div>/s', '', $bodyContent);
            echo $bodyContent;
        } else {
            // Se não encontrar body, retornar todo o conteúdo
            echo $content;
        }
        break;

    case 'subscription':
        // Página de assinatura
        echo '
        <div class="p-6">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Assinatura</h2>
                <p class="text-gray-600">Gerencie sua assinatura e planos.</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-2">Plano Atual</h3>
                    <p class="text-3xl font-bold text-green-600 mb-2">Gratuito</p>
                    <p class="text-gray-600 mb-4">Até 100 mensagens/mês</p>
                    <button class="w-full bg-green-600 hover:bg-green-700 text-white py-2 rounded-lg">
                        Fazer Upgrade
                    </button>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-2">Plano Básico</h3>
                    <p class="text-3xl font-bold text-blue-600 mb-2">R$ 29,90</p>
                    <p class="text-gray-600 mb-4">Até 1.000 mensagens/mês</p>
                    <button class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg">
                        Assinar
                    </button>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-2">Plano Pro</h3>
                    <p class="text-3xl font-bold text-purple-600 mb-2">R$ 59,90</p>
                    <p class="text-gray-600 mb-4">Mensagens ilimitadas</p>
                    <button class="w-full bg-purple-600 hover:bg-purple-700 text-white py-2 rounded-lg">
                        Assinar
                    </button>
                </div>
            </div>
        </div>';
        break;

    case 'flows':
        echo <<<'HTML'
        <div class="p-6" id="automationFlowsApp">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">Fluxos de Automação</h2>
                    <p class="text-gray-600">Construa fluxos conversacionais (tipo Typebot) e publique para uso no WhatsApp.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <button id="btnCreateFlow" class="bg-green-600 hover:bg-green-700 text-white px-5 py-3 rounded-xl shadow flex items-center gap-2">
                        <i class="fas fa-plus"></i>
                        Novo Fluxo
                    </button>
                    <button id="btnRefreshFlows" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-5 py-3 rounded-xl flex items-center gap-2">
                        <i class="fas fa-sync"></i>
                        Atualizar
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow overflow-hidden mb-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 p-6 border-b border-gray-100">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Seus Fluxos</h3>
                        <p class="text-sm text-gray-500">Gerencie rascunhos e versões publicadas.</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <select id="selectStatusFilter" class="border border-gray-200 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-green-500">
                            <option value="">Todos os status</option>
                            <option value="draft">Rascunhos</option>
                            <option value="published">Publicados</option>
                            <option value="paused">Pausados</option>
                        </select>
                    </div>
                </div>

                <div id="flowsList" class="divide-y divide-gray-100">
                    <div class="py-12 text-center text-gray-500">
                        <i class="fas fa-project-diagram text-4xl text-gray-300 mb-3"></i>
                        <p class="text-lg font-medium">Carregando fluxos...</p>
                    </div>
                </div>
            </div>

            <!-- Editor -->
            <div id="flowEditor" class="hidden">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-sm text-gray-500">Editando fluxo</p>
                        <h3 id="editorFlowName" class="text-xl font-semibold text-gray-900">Fluxo</h3>
                    </div>
                    <div class="flex gap-2 flex-wrap">
                        <button id="btnBackList" class="px-4 py-2 rounded-lg border border-gray-200 text-gray-700">Voltar</button>
                        <button id="btnSaveDraft" class="px-4 py-2 rounded-lg bg-gray-800 text-white">Salvar rascunho</button>
                        <button id="btnPublish" class="px-4 py-2 rounded-lg bg-green-600 text-white">Publicar</button>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                    <div class="bg-white rounded-2xl shadow p-4 lg:col-span-1">
                        <h4 class="text-sm font-semibold text-gray-800 mb-3">Blocos</h4>
                        <div class="space-y-2" id="blockLibrary">
                            <button data-block-type="start" class="w-full text-left px-3 py-2 rounded-lg border border-gray-200 hover:border-green-500 flex items-center gap-2"><i class="fas fa-play text-green-500"></i>Start</button>
                            <button data-block-type="message" class="w-full text-left px-3 py-2 rounded-lg border border-gray-200 hover:border-green-500 flex items-center gap-2"><i class="fas fa-comment-dots text-blue-500"></i>Mensagem</button>
                            <button data-block-type="question" class="w-full text-left px-3 py-2 rounded-lg border border-gray-200 hover:border-green-500 flex items-center gap-2"><i class="fas fa-question-circle text-amber-500"></i>Pergunta (texto)</button>
                            <button data-block-type="choice" class="w-full text-left px-3 py-2 rounded-lg border border-gray-200 hover-border-green-500 flex items-center gap-2"><i class="fas fa-list text-purple-500"></i>Opções</button>
                            <button data-block-type="condition" class="w-full text-left px-3 py-2 rounded-lg border border-gray-200 hover-border-green-500 flex items-center gap-2"><i class="fas fa-code-branch text-gray-600"></i>Condição</button>
                            <button data-block-type="http" class="w-full text-left px-3 py-2 rounded-lg border border-gray-200 hover-border-green-500 flex items-center gap-2"><i class="fas fa-cloud text-cyan-500"></i>HTTP</button>
                            <button data-block-type="end" class="w-full text-left px-3 py-2 rounded-lg border border-gray-200 hover-border-green-500 flex items-center gap-2"><i class="fas fa-stop text-red-500"></i>Fim</button>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow p-0 lg:col-span-2 relative">
                        <div class="flex items-center justify-between px-4 py-2 border-b border-gray-100">
                            <div class="flex items-center gap-2">
                                <button id="btnZoomOut" class="px-2 py-1 border border-gray-200 rounded">-</button>
                                <button id="btnZoomIn" class="px-2 py-1 border border-gray-200 rounded">+</button>
                                <button id="btnResetView" class="px-2 py-1 border border-gray-200 rounded">Reset</button>
                                <button id="btnConnectMode" class="px-3 py-1 border border-gray-200 rounded text-sm">Modo conexão: off</button>
                            </div>
                            <span class="text-sm text-gray-500" id="zoomLevel">100%</span>
                        </div>
                        <div id="canvasArea" class="relative h-[520px] overflow-auto bg-slate-50">
                            <div id="canvasInner" class="relative min-w-[1200px] min-h-[800px] bg-[radial-gradient(circle_at_1px_1px,#e5e7eb_1px,transparent_0)] [background-size:40px_40px] transition-transform origin-top-left">
                                <div id="edgesLayerWrapper" class="absolute inset-0">
                                    <svg id="edgesLayer" class="absolute inset-0 w-full h-full overflow-visible"></svg>
                                </div>
                                <div id="nodesLayer" class="absolute inset-0"></div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow p-4 lg:col-span-1">
                        <h4 class="text-sm font-semibold text-gray-800 mb-3">Propriedades</h4>
                        <div id="propertyPanel" class="space-y-3 text-sm text-gray-700">
                            <p class="text-gray-500">Selecione um bloco para editar.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
            (function() {
                const apiBase = '/api/bot_flows.php';

                const flowsList = document.getElementById('flowsList');
                const statusFilter = document.getElementById('selectStatusFilter');
                const btnCreateFlow = document.getElementById('btnCreateFlow');
                const btnRefreshFlows = document.getElementById('btnRefreshFlows');
                const flowEditor = document.getElementById('flowEditor');
                const btnBackList = document.getElementById('btnBackList');
                const btnSaveDraft = document.getElementById('btnSaveDraft');
                const btnPublish = document.getElementById('btnPublish');
                const blockLibrary = document.getElementById('blockLibrary');
                const canvasInner = document.getElementById('canvasInner');
                const nodesLayer = document.getElementById('nodesLayer') || canvasInner; // fallback
                const edgesLayer = document.getElementById('edgesLayer');
                const propertyPanel = document.getElementById('propertyPanel');
                const editorFlowName = document.getElementById('editorFlowName');
                const zoomLevel = document.getElementById('zoomLevel');
                const btnZoomIn = document.getElementById('btnZoomIn');
                const btnZoomOut = document.getElementById('btnZoomOut');
                const btnResetView = document.getElementById('btnResetView');
                const btnConnectMode = document.getElementById('btnConnectMode');

                let editingFlow = null;
                let nodes = [];
                let edges = [];
                let scale = 1;
                let selectedNodeId = null;
                let connectMode = false;
                let connectFrom = null;

                const nodeDefaults = {
                    start: { label: 'Start', config: { text: 'Início' } },
                    message: { label: 'Mensagem', config: { text: 'Sua mensagem aqui' } },
                    question: { label: 'Pergunta', config: { prompt: 'Pergunta ao cliente' } },
                    choice: { label: 'Opções', config: { options: ['Opção 1', 'Opção 2'] } },
                    condition: { label: 'Condição', config: { expression: '' } },
                    http: { label: 'HTTP', config: { url: '', method: 'GET', body: '' } },
                    end: { label: 'Fim', config: { text: 'Encerrar' } },
                };

                const randomPos = () => ({
                    x: 100 + Math.floor(Math.random() * 400),
                    y: 100 + Math.floor(Math.random() * 300),
                });

                const renderFlows = (flows) => {
                    if (!flows.length) {
                        flowsList.innerHTML = '<div class="py-12 text-center text-gray-500">Nenhum fluxo criado ainda.</div>';
                        return;
                    }
                    const listHtml = flows.map(flow => `
                        <div class="py-4 px-4 flex items-center justify-between hover:bg-gray-50">
                            <div>
                                <h4 class="text-lg font-medium text-gray-800">${flow.name}</h4>
                                <p class="text-sm text-gray-500">${flow.description || ''}</p>
                                <p class="text-xs text-gray-400">Status: ${flow.status || 'draft'}${flow.published_version ? ` • v${flow.published_version}` : ''}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button class="px-3 py-2 rounded-lg border border-gray-200 text-gray-700" onclick="window._openFlow(${flow.id})">Editar</button>
                                <button class="px-3 py-2 rounded-lg border border-gray-200 text-gray-700" onclick="window._publishFlow(${flow.id})">Publicar</button>
                                <button class="px-3 py-2 rounded-lg border border-gray-200 text-gray-700" onclick="window._deleteFlow(${flow.id})">Excluir</button>
                            </div>
                        </div>
                    `).join('');
                    flowsList.innerHTML = listHtml;
                };

                const fetchFlows = () => {
                    const params = new URLSearchParams();
                    if (statusFilter.value) params.append('status', statusFilter.value);
                    fetch(`${apiBase}?action=list&${params.toString()}`)
                        .then(res => res.json())
                        .then(data => {
                            if (!data.success) throw new Error(data.message || 'Erro ao carregar fluxos');
                            renderFlows(data.flows || []);
                        })
                        .catch(err => {
                            flowsList.innerHTML = `<div class="py-12 text-center text-red-500">${err.message}</div>`;
                        });
                };

                const selectNode = (id) => {
                    selectedNodeId = id;
                    const node = nodes.find(n => n.id === id);
                    if (!node) {
                        propertyPanel.innerHTML = '<p class="text-gray-500">Selecione um bloco para editar.</p>';
                        return;
                    }
                    propertyPanel.innerHTML = `
                        <div class="space-y-2">
                            <label class="text-xs text-gray-500">Título</label>
                            <input id="propLabel" class="w-full border border-gray-200 rounded-lg px-3 py-2" value="${node.label || ''}">
                            <label class="text-xs text-gray-500">Texto/Prompt</label>
                            <textarea id="propText" class="w-full border border-gray-200 rounded-lg px-3 py-2" rows="3">${(node.config?.text || node.config?.prompt || '')}</textarea>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-xs text-gray-500">X</label>
                                    <input id="propX" type="number" class="w-full border border-gray-200 rounded-lg px-3 py-2" value="${node.x}">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Y</label>
                                    <input id="propY" type="number" class="w-full border border-gray-200 rounded-lg px-3 py-2" value="${node.y}">
                                </div>
                            </div>
                            <button id="propSave" class="w-full mt-2 bg-green-600 text-white rounded-lg px-3 py-2">Atualizar</button>
                        </div>
                    `;
                    document.getElementById('propSave').onclick = () => {
                        node.label = document.getElementById('propLabel').value;
                        const txt = document.getElementById('propText').value;
                        if (node.type === 'message' || node.type === 'end' || node.type === 'start') {
                            node.config.text = txt;
                        } else if (node.type === 'question') {
                            node.config.prompt = txt;
                        }
                        node.x = parseInt(document.getElementById('propX').value, 10) || 0;
                        node.y = parseInt(document.getElementById('propY').value, 10) || 0;
                        renderNodes();
                    };
                };

                const renderNodes = () => {
                    nodesLayer.innerHTML = '';
                    nodes.forEach(node => {
                        const el = document.createElement('div');
                        const isSelected = selectedNodeId === node.id;
                        const isConnectFrom = connectFrom === node.id;
                        el.className = `absolute bg-white rounded-xl shadow border p-3 w-52 cursor-pointer transition ${isSelected ? 'border-green-500 ring-2 ring-green-200' : 'border-gray-200'} ${isConnectFrom ? 'bg-green-50' : ''}`;
                        el.style.left = `${node.x}px`;
                        el.style.top = `${node.y}px`;
                        el.innerHTML = `
                            <div class="text-xs text-gray-400">${node.type}</div>
                            <div class="font-semibold text-gray-800">${node.label || nodeDefaults[node.type]?.label || node.type}</div>
                            <div class="text-sm text-gray-600 line-clamp-3">${node.config?.text || node.config?.prompt || ''}</div>
                        `;
                        el.onclick = () => selectNode(node.id);
                        nodesLayer.appendChild(el);
                    });
                    renderEdges();
                };

                const renderEdges = () => {
                    if (!edgesLayer) return;
                    edgesLayer.innerHTML = '';
                    edges.forEach((edge, idx) => {
                        const fromNode = nodes.find(n => n.id === edge.from);
                        const toNode = nodes.find(n => n.id === edge.to);
                        if (!fromNode || !toNode) return;
                        const x1 = fromNode.x + 100;
                        const y1 = fromNode.y + 30;
                        const x2 = toNode.x + 100;
                        const y2 = toNode.y + 30;
                        
                        const isSelected = selectedEdgeIdx === idx;
                        
                        // Criar grupo SVG para edge
                        const group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
                        group.setAttribute('class', 'edge-group cursor-pointer');
                        
                        // Path principal
                        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                        const dx = (x2 - x1) * 0.3;
                        const d = `M ${x1} ${y1} C ${x1 + dx} ${y1}, ${x2 - dx} ${y2}, ${x2} ${y2}`;
                        path.setAttribute('d', d);
                        path.setAttribute('stroke', isSelected ? '#10b981' : '#34d399');
                        path.setAttribute('stroke-width', isSelected ? '4' : '2');
                        path.setAttribute('fill', 'none');
                        path.setAttribute('class', 'transition-all duration-200');
                        path.setAttribute('filter', isSelected ? 'drop-shadow(0 0 8px rgba(16, 185, 129, 0.5))' : '');
                        
                        // Path invisível mais largo para facilitar clique
                        const hitPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                        hitPath.setAttribute('d', d);
                        hitPath.setAttribute('stroke', 'transparent');
                        hitPath.setAttribute('stroke-width', '20');
                        hitPath.setAttribute('fill', 'none');
                        hitPath.setAttribute('class', 'cursor-pointer');
                        
                        // Seta no final
                        const arrowSize = 8;
                        const angle = Math.atan2(y2 - y1, x2 - x1);
                        const arrowX = x2 - Math.cos(angle) * 10;
                        const arrowY = y2 - Math.sin(angle) * 10;
                        const arrow = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
                        const p1x = arrowX - arrowSize * Math.cos(angle - Math.PI / 6);
                        const p1y = arrowY - arrowSize * Math.sin(angle - Math.PI / 6);
                        const p2x = arrowX - arrowSize * Math.cos(angle + Math.PI / 6);
                        const p2y = arrowY - arrowSize * Math.sin(angle + Math.PI / 6);
                        arrow.setAttribute('points', `${x2},${y2} ${p1x},${p1y} ${p2x},${p2y}`);
                        arrow.setAttribute('fill', isSelected ? '#10b981' : '#34d399');
                        arrow.setAttribute('class', 'transition-all duration-200');
                        
                        // Label de condição (se existir)
                        if (edge.condition && edge.condition.label) {
                            const midX = (x1 + x2) / 2;
                            const midY = (y1 + y2) / 2;
                            const label = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                            label.setAttribute('x', midX);
                            label.setAttribute('y', midY - 5);
                            label.setAttribute('text-anchor', 'middle');
                            label.setAttribute('class', 'text-xs fill-gray-600 font-medium');
                            label.setAttribute('style', 'pointer-events: none;');
                            label.textContent = edge.condition.label;
                            
                            const labelBg = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                            const bbox = label.getBBox ? label.getBBox() : { x: midX - 20, y: midY - 15, width: 40, height: 20 };
                            labelBg.setAttribute('x', bbox.x - 4);
                            labelBg.setAttribute('y', bbox.y - 2);
                            labelBg.setAttribute('width', bbox.width + 8);
                            labelBg.setAttribute('height', bbox.height + 4);
                            labelBg.setAttribute('fill', 'white');
                            labelBg.setAttribute('rx', '4');
                            labelBg.setAttribute('class', 'stroke-gray-200');
                            labelBg.setAttribute('stroke-width', '1');
                            labelBg.setAttribute('style', 'pointer-events: none;');
                            
                            group.appendChild(labelBg);
                            group.appendChild(label);
                        }
                        
                        // Eventos
                        const selectEdge = () => {
                            selectedEdgeIdx = idx;
                            selectedNodeId = null;
                            renderNodes();
                            showEdgeProperties(idx);
                        };
                        
                        hitPath.addEventListener('mouseenter', () => {
                            if (selectedEdgeIdx !== idx) {
                                path.setAttribute('stroke-width', '3');
                                path.setAttribute('stroke', '#10b981');
                            }
                        });
                        
                        hitPath.addEventListener('mouseleave', () => {
                            if (selectedEdgeIdx !== idx) {
                                path.setAttribute('stroke-width', '2');
                                path.setAttribute('stroke', '#34d399');
                            }
                        });
                        
                        hitPath.addEventListener('click', (e) => {
                            e.stopPropagation();
                            selectEdge();
                        });
                        
                        group.appendChild(path);
                        group.appendChild(arrow);
                        group.appendChild(hitPath);
                        edgesLayer.appendChild(group);
                    });
                };
                
                const showEdgeProperties = (idx) => {
                    const edge = edges[idx];
                    if (!edge) return;
                    
                    const fromNode = nodes.find(n => n.id === edge.from);
                    const toNode = nodes.find(n => n.id === edge.to);
                    
                    propertyPanel.innerHTML = `
                        <div class="space-y-3">
                            <div class="flex items-center justify-between mb-3">
                                <h5 class="font-semibold text-gray-800">Conexão</h5>
                                <button id="btnDeleteEdge" class="text-red-600 hover:text-red-700 text-sm">
                                    <i class="fas fa-trash mr-1"></i>Deletar
                                </button>
                            </div>
                            
                            <div class="text-sm text-gray-600 bg-gray-50 p-3 rounded-lg">
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fas fa-arrow-right text-green-500"></i>
                                    <span><strong>De:</strong> ${fromNode?.label || 'N/A'}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-arrow-right text-green-500"></i>
                                    <span><strong>Para:</strong> ${toNode?.label || 'N/A'}</span>
                                </div>
                            </div>
                            
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Label da Condição</label>
                                <input id="edgeConditionLabel" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" 
                                       placeholder="Ex: Se resposta = sim" 
                                       value="${edge.condition?.label || ''}">
                            </div>
                            
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Tipo de Condição</label>
                                <select id="edgeConditionType" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                                    <option value="">Sempre (sem condição)</option>
                                    <option value="equals" ${edge.condition?.type === 'equals' ? 'selected' : ''}>Igual a</option>
                                    <option value="contains" ${edge.condition?.type === 'contains' ? 'selected' : ''}>Contém</option>
                                    <option value="regex" ${edge.condition?.type === 'regex' ? 'selected' : ''}>Regex</option>
                                </select>
                            </div>
                            
                            <div id="conditionValueDiv" class="${edge.condition?.type ? '' : 'hidden'}">
                                <label class="text-xs text-gray-500 block mb-1">Valor da Condição</label>
                                <input id="edgeConditionValue" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" 
                                       placeholder="Valor a comparar" 
                                       value="${edge.condition?.value || ''}">
                            </div>
                            
                            <button id="btnSaveEdge" class="w-full bg-green-600 hover:bg-green-700 text-white rounded-lg px-3 py-2 text-sm font-medium">
                                <i class="fas fa-save mr-2"></i>Salvar Condição
                            </button>
                        </div>
                    `;
                    
                    // Eventos
                    document.getElementById('btnDeleteEdge').onclick = () => {
                        if (confirm('Deseja remover esta conexão?')) {
                            edges.splice(idx, 1);
                            selectedEdgeIdx = null;
                            propertyPanel.innerHTML = '<p class="text-gray-500">Selecione um bloco ou conexão para editar.</p>';
                            renderNodes();
                        }
                    };
                    
                    document.getElementById('edgeConditionType').onchange = (e) => {
                        const valueDiv = document.getElementById('conditionValueDiv');
                        valueDiv.classList.toggle('hidden', !e.target.value);
                    };
                    
                    document.getElementById('btnSaveEdge').onclick = () => {
                        const label = document.getElementById('edgeConditionLabel').value;
                        const type = document.getElementById('edgeConditionType').value;
                        const value = document.getElementById('edgeConditionValue').value;
                        
                        edge.condition = {
                            label: label || '',
                            type: type || '',
                            value: value || ''
                        };
                        
                        renderNodes();
                        alert('Condição salva!');
                    };
                };

                const loadFlow = (id) => {
                    fetch(`${apiBase}?action=get&id=${id}`)
                        .then(res => res.json())
                        .then(data => {
                            if (!data.success) throw new Error(data.message || 'Erro ao carregar fluxo');
                            editingFlow = data.flow;
                            nodes = (data.nodes || []).map(n => ({
                                id: n.id,
                                type: n.type,
                                label: n.label,
                                config: n.config ? JSON.parse(n.config) : {},
                                x: parseInt(n.pos_x, 10) || 0,
                                y: parseInt(n.pos_y, 10) || 0,
                            }));
                            edges = (data.edges || []).map(e => ({
                                id: e.id,
                                from: e.from_node_id,
                                to: e.to_node_id,
                                condition: e.condition || {}
                            }));
                            editorFlowName.textContent = editingFlow.name;
                            flowEditor.classList.remove('hidden');
                            renderNodes();
                        })
                        .catch(err => alert(err.message));
                };

                const saveLayout = () => {
                    if (!editingFlow) return;
                    const payload = {
                        action: 'save_layout',
                        id: editingFlow.id,
                        nodes,
                        edges: edges.map((e, idx) => ({
                            id: e.id || idx,
                            from: e.from,
                            to: e.to,
                            condition: e.condition || {}
                        })),
                    };
                    return fetch(apiBase, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    }).then(res => res.json());
                };

                const saveDraft = () => {
                    if (!editingFlow) return;
                    btnSaveDraft.disabled = true;
                    saveLayout()
                        .then(data => {
                            if (!data.success) throw new Error(data.message || 'Erro ao salvar');
                            alert('Rascunho salvo');
                        })
                        .catch(err => alert(err.message))
                        .finally(() => btnSaveDraft.disabled = false);
                };

                const publishFlow = (id) => {
                    const targetId = id || (editingFlow && editingFlow.id);
                    if (!targetId) return;
                    btnPublish.disabled = true;
                    saveLayout()
                        .then(() => fetch(apiBase, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'publish', id: targetId })
                        }))
                        .then(res => res.json())
                        .then(data => {
                            if (!data.success) throw new Error(data.message || 'Erro ao publicar');
                            alert('Fluxo publicado');
                            fetchFlows();
                        })
                        .catch(err => alert(err.message))
                        .finally(() => btnPublish.disabled = false);
                };

                const createFlow = () => {
                    const name = prompt('Nome do fluxo');
                    if (!name) return;
                    fetch(apiBase, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'create', name })
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (!data.success) throw new Error(data.message || 'Erro ao criar');
                            fetchFlows();
                            loadFlow(data.flow_id);
                        })
                        .catch(err => alert(err.message));
                };

                const deleteFlow = (id) => {
                    if (!confirm('Deseja excluir este fluxo?')) return;
                    fetch(apiBase, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'delete', id })
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (!data.success) throw new Error(data.message || 'Erro ao excluir');
                            fetchFlows();
                            flowEditor.classList.add('hidden');
                        })
                        .catch(err => alert(err.message));
                };

                // Canvas zoom
                const updateZoom = () => {
                    canvasInner.style.transform = `scale(${scale})`;
                    zoomLevel.textContent = `${Math.round(scale * 100)}%`;
                };
                btnZoomIn.onclick = () => { scale = Math.min(2, scale + 0.1); updateZoom(); };
                btnZoomOut.onclick = () => { scale = Math.max(0.5, scale - 0.1); updateZoom(); };
                btnResetView.onclick = () => { scale = 1; updateZoom(); };
                updateZoom();

                // Library
                blockLibrary.querySelectorAll('button[data-block-type]').forEach(btn => {
                    btn.addEventListener('click', () => {
                        if (!editingFlow) {
                            alert('Abra ou crie um fluxo antes de adicionar blocos.');
                            return;
                        }
                        const type = btn.getAttribute('data-block-type');
                        const pos = randomPos();
                        const def = nodeDefaults[type] || { label: type, config: {} };
                        const newNode = {
                            id: Date.now() + Math.floor(Math.random() * 1000),
                            type,
                            label: def.label,
                            config: JSON.parse(JSON.stringify(def.config)),
                            x: pos.x,
                            y: pos.y
                        };
                        nodes.push(newNode);
                        renderNodes();
                        selectNode(newNode.id);
                    });
                });

                const toggleConnectMode = () => {
                    connectMode = !connectMode;
                    connectFrom = null;
                    btnConnectMode.textContent = `Modo conexão: ${connectMode ? 'on' : 'off'}`;
                    btnConnectMode.classList.toggle('bg-green-50', connectMode);
                    btnConnectMode.classList.toggle('text-green-700', connectMode);
                };
                if (btnConnectMode) {
                    btnConnectMode.addEventListener('click', toggleConnectMode);
                }

                const handleNodeClick = (nodeId) => {
                    if (!connectMode) {
                        selectNode(nodeId);
                        return;
                    }
                    if (!connectFrom) {
                        connectFrom = nodeId;
                        renderNodes();
                        return;
                    }
                    if (connectFrom === nodeId) {
                        connectFrom = null;
                        renderNodes();
                        return;
                    }
                    // evitar duplicadas
                    const exists = edges.find(e => e.from === connectFrom && e.to === nodeId);
                    if (!exists) {
                        edges.push({ from: connectFrom, to: nodeId, condition: {} });
                    }
                    connectFrom = null;
                    renderNodes();
                };

                // Override click to support connect mode
                const renderNodesWithHandler = () => {
                    nodesLayer.innerHTML = '';
                    nodes.forEach(node => {
                        const el = document.createElement('div');
                        el.className = 'absolute bg-white rounded-xl shadow border border-gray-200 p-3 w-52 cursor-pointer';
                        el.style.left = `${node.x}px`;
                        el.style.top = `${node.y}px`;
                        el.innerHTML = `
                            <div class="text-xs text-gray-400">${node.type}</div>
                            <div class="font-semibold text-gray-800">${node.label || nodeDefaults[node.type]?.label || node.type}</div>
                            <div class="text-sm text-gray-600 line-clamp-3">${node.config?.text || node.config?.prompt || ''}</div>
                        `;
                        el.onclick = () => handleNodeClick(node.id);
                        nodesLayer.appendChild(el);
                    });
                    renderEdges();
                };
                renderNodes = renderNodesWithHandler;

                // Public helpers for buttons in list
                window._openFlow = loadFlow;
                window._deleteFlow = deleteFlow;
                window._publishFlow = publishFlow;

                // Events
                statusFilter.addEventListener('change', fetchFlows);
                btnRefreshFlows.addEventListener('click', fetchFlows);
                btnCreateFlow.addEventListener('click', createFlow);
                btnBackList.addEventListener('click', () => {
                    flowEditor.classList.add('hidden');
                    editingFlow = null;
                    nodes = [];
                    edges = [];
                    selectedNodeId = null;
                });
                btnSaveDraft.addEventListener('click', saveDraft);
                btnPublish.addEventListener('click', () => publishFlow());

                // Init
                fetchFlows();
            })();
        </script>
HTML;
        break;

    case 'users':
        // Verificar se é admin
        if (!isAdmin()) {
            http_response_code(403);
?>
            <div class="flex items-center justify-center py-12">
                <div class="text-center">
                    <i class="fas fa-lock text-4xl text-red-500 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-800 mb-2">Acesso Negado</h3>
                    <p class="text-gray-600 mb-4">Você não tem permissão para acessar esta página.</p>
                    <button onclick="loadPage('dashboard')" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                        Voltar ao Dashboard
                    </button>
                </div>
            </div>
        <?php
            break;
        }

        // Carregar página de usuários
        $content = includePageContent('../users.php');
        if ($content) {
            echo $content;
        } else {
            // Criar interface básica de usuários
            $stmt = $pdo->prepare("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC");
            $stmt->execute();
            $users = $stmt->fetchAll();

            echo '
            <div class="p-6">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">Gerenciar Usuários</h2>
                    <p class="text-gray-600">Gerencie os usuários do sistema.</p>
                </div>
                
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium text-gray-800">Usuários Cadastrados</h3>
                            <a href="/register.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                                <i class="fas fa-plus mr-2"></i>Novo Usuário
                            </a>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Função</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cadastro</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">';

            foreach ($users as $user) {
                $roleLabel = $user['role'] === 'admin' ? '<span class="px-2 py-1 text-xs bg-purple-100 text-purple-800 rounded">Admin</span>' : '<span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">Usuário</span>';
                $date = date('d/m/Y', strtotime($user['created_at']));

                echo '
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">' . htmlspecialchars($user['name']) . '</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">' . htmlspecialchars($user['email']) . '</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">' . $roleLabel . '</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">' . $date . '</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <button class="text-blue-600 hover:text-blue-800 mr-3">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="text-red-600 hover:text-red-800">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>';
            }

            echo '
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>';
        }
        break;

    case 'backups':
        // Página de Backups de Conversas
        $userType = $_SESSION['user_type'] ?? 'user';

        // Verificar permissão
        if (!in_array($userType, ['admin', 'supervisor', 'user'])) {
            echo '<div class="p-6 text-center text-red-600">Sem permissão para acessar esta página.</div>';
            break;
        }

        ?>
        <style>
            /* Design System - Backups Page */
            .backup-card {
                background: var(--bg-card);
                border: 0.5px solid var(--border);
                border-radius: var(--radius-lg);
                padding: var(--space-4);
                transition: all var(--transition-base);
            }
            .backup-card:hover {
                border-color: var(--border-emphasis);
            }
            .backup-stat-icon {
                width: 40px;
                height: 40px;
                border-radius: var(--radius-md);
                display: flex;
                align-items: center;
                justify-content: center;
                border: 0.5px solid;
            }
            .backup-stat-icon.blue {
                background: rgba(59, 130, 246, 0.08);
                border-color: rgba(59, 130, 246, 0.2);
                color: #3b82f6;
            }
            .backup-stat-icon.green {
                background: var(--accent-subtle);
                border-color: rgba(16, 185, 129, 0.2);
                color: var(--accent-primary);
            }
            .backup-stat-icon.purple {
                background: rgba(139, 92, 246, 0.08);
                border-color: rgba(139, 92, 246, 0.2);
                color: #8b5cf6;
            }
            .backup-stat-icon.yellow {
                background: rgba(234, 179, 8, 0.08);
                border-color: rgba(234, 179, 8, 0.2);
                color: #eab308;
            }
            .backup-stat-label {
                font-size: 11px;
                font-weight: 500;
                letter-spacing: 0.02em;
                text-transform: uppercase;
                color: var(--text-muted);
            }
            .backup-stat-value {
                font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
                font-size: 20px;
                font-weight: 700;
                color: var(--text-primary);
                font-variant-numeric: tabular-nums;
            }
            .backup-btn {
                padding: var(--space-2) var(--space-3);
                border-radius: var(--radius-md);
                font-size: 13px;
                font-weight: 500;
                transition: all var(--transition-fast);
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }
            .backup-btn-primary {
                background: var(--accent-primary);
                color: white;
                border: none;
            }
            .backup-btn-primary:hover {
                background: var(--accent-hover);
            }
            .backup-btn-secondary {
                background: var(--bg-sidebar-hover);
                color: var(--text-primary);
                border: 0.5px solid var(--border);
            }
            .backup-btn-secondary:hover {
                border-color: var(--border-emphasis);
            }
            .backup-btn-danger {
                background: rgba(239, 68, 68, 0.08);
                color: #ef4444;
                border: 0.5px solid rgba(239, 68, 68, 0.2);
            }
            .backup-btn-danger:hover {
                background: rgba(239, 68, 68, 0.15);
            }
            .backup-table th {
                font-size: 11px;
                font-weight: 600;
                letter-spacing: 0.02em;
                text-transform: uppercase;
                color: var(--text-muted);
                background: var(--bg-sidebar-hover);
                padding: var(--space-3) var(--space-4);
                border-bottom: 0.5px solid var(--border);
            }
            .backup-table td {
                padding: var(--space-3) var(--space-4);
                font-size: 13px;
                color: var(--text-secondary);
                border-bottom: 0.5px solid var(--border-subtle);
            }
            .backup-table tr:hover td {
                background: var(--bg-sidebar-hover);
            }
            .backup-badge {
                padding: 2px 8px;
                border-radius: var(--radius-sm);
                font-size: 11px;
                font-weight: 600;
            }
            .backup-badge-success {
                background: var(--accent-subtle);
                color: var(--accent-primary);
            }
            .backup-badge-warning {
                background: rgba(234, 179, 8, 0.08);
                color: #eab308;
            }
            .backup-badge-error {
                background: rgba(239, 68, 68, 0.08);
                color: #ef4444;
            }
            .backup-badge-info {
                background: rgba(59, 130, 246, 0.08);
                color: #3b82f6;
            }
        </style>
        
        <div style="padding: var(--space-6);">
            <!-- Header -->
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-6);">
                <div>
                    <h2 style="font-size: 18px; font-weight: 600; letter-spacing: -0.02em; color: var(--text-primary); margin-bottom: var(--space-1); display: flex; align-items: center; gap: var(--space-2);">
                        <i class="fas fa-database" style="color: var(--accent-primary);"></i>
                        Backup de Conversas
                    </h2>
                    <p style="font-size: 13px; color: var(--text-muted);">Gerencie backups das suas conversas do WhatsApp.</p>
                </div>
                <div style="display: flex; gap: var(--space-3);">
                    <button id="btn-download-all-conversations" class="backup-btn backup-btn-secondary">
                        <i class="fas fa-download"></i>Baixar Todas Conversas
                    </button>
                    <button id="btn-import-backup" class="backup-btn backup-btn-secondary" onclick="openImportBackupModal()">
                        <i class="fas fa-upload"></i>Importar Backup
                    </button>
                    <button id="btn-open-config" class="backup-btn backup-btn-secondary">
                        <i class="fas fa-cog"></i>Configurar
                    </button>
                    <button id="btn-create-backup" class="backup-btn backup-btn-primary">
                        <i class="fas fa-plus"></i>Novo Backup
                    </button>
                </div>
            </div>

            <!-- Cards de Estatísticas -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6);" id="backup-stats">
                <div class="backup-card">
                    <div style="display: flex; align-items: center; gap: var(--space-3);">
                        <div class="backup-stat-icon blue">
                            <i class="fas fa-archive"></i>
                        </div>
                        <div>
                            <p class="backup-stat-label">Total de Backups</p>
                            <p class="backup-stat-value" id="stat-total">0</p>
                        </div>
                    </div>
                </div>
                <div class="backup-card">
                    <div style="display: flex; align-items: center; gap: var(--space-3);">
                        <div class="backup-stat-icon green">
                            <i class="fas fa-hdd"></i>
                        </div>
                        <div>
                            <p class="backup-stat-label">Espaço Usado</p>
                            <p class="backup-stat-value" id="stat-size">0 KB</p>
                        </div>
                    </div>
                </div>
                <div class="backup-card">
                    <div style="display: flex; align-items: center; gap: var(--space-3);">
                        <div class="backup-stat-icon purple">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <p class="backup-stat-label">Último Backup</p>
                            <p class="backup-stat-value" style="font-size: 14px;" id="stat-last">Nunca</p>
                        </div>
                    </div>
                </div>
                <div class="backup-card">
                    <div style="display: flex; align-items: center; gap: var(--space-3);">
                        <div class="backup-stat-icon yellow">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <div>
                            <p class="backup-stat-label">Próximo Agendado</p>
                            <p class="backup-stat-value" style="font-size: 14px;" id="stat-next">Não agendado</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabela de Backups -->
            <div class="backup-card">
                <div style="padding: 0 0 var(--space-4) 0; border-bottom: 0.5px solid var(--border); margin-bottom: var(--space-4);">
                    <h3 style="font-size: 14px; font-weight: 600; color: var(--text-primary);">Histórico de Backups</h3>
                </div>
                <div style="overflow-x: auto;">
                    <table class="backup-table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="text-align: left;">Arquivo</th>
                                <th style="text-align: left;">Tamanho</th>
                                <th style="text-align: left;">Conversas</th>
                                <th style="text-align: left;">Mensagens</th>
                                <th style="text-align: left;">Status</th>
                                <th style="text-align: left;">Data</th>
                                <th style="text-align: left;">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="backups-table-body">
                            <tr>
                                <td colspan="7" style="text-align: center; padding: var(--space-8); color: var(--text-muted);">
                                    <i class="fas fa-spinner fa-spin" style="margin-right: var(--space-2);"></i>Carregando backups...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal Criar Backup -->
        <div id="backup-create-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
            <div style="background: var(--bg-card); border-radius: var(--radius-lg); border: 0.5px solid var(--border); max-width: 480px; width: 100%; margin: var(--space-4);">
                <div style="padding: var(--space-4); border-bottom: 0.5px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                    <h3 style="font-size: 16px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: var(--space-2);">
                        <i class="fas fa-plus" style="color: var(--accent-primary);"></i>Novo Backup
                    </h3>
                    <button onclick="closeBackupCreateModal()" style="color: var(--text-muted); background: none; border: none; cursor: pointer; padding: var(--space-1);">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div style="padding: var(--space-4);">
                    <form id="backup-create-form" onsubmit="submitBackupCreate(event)">
                        <div style="margin-bottom: var(--space-4);">
                            <label style="display: block; font-size: 12px; font-weight: 500; color: var(--text-secondary); margin-bottom: var(--space-2);">Formato</label>
                            <select id="backup-format" style="width: 100%; padding: var(--space-2) var(--space-3); border: 0.5px solid var(--border); border-radius: var(--radius-md); background: var(--bg-card); color: var(--text-primary); font-size: 13px;">
                                <option value="json">JSON (Estruturado)</option>
                                <option value="csv">CSV (Planilha)</option>
                            </select>
                        </div>
                        <div style="margin-bottom: var(--space-4);">
                            <label style="display: block; font-size: 12px; font-weight: 500; color: var(--text-secondary); margin-bottom: var(--space-2);">Período (opcional)</label>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3);">
                                <input type="date" id="backup-date-from" style="padding: var(--space-2) var(--space-3); border: 0.5px solid var(--border); border-radius: var(--radius-md); background: var(--bg-card); color: var(--text-primary); font-size: 13px;">
                                <input type="date" id="backup-date-to" style="padding: var(--space-2) var(--space-3); border: 0.5px solid var(--border); border-radius: var(--radius-md); background: var(--bg-card); color: var(--text-primary); font-size: 13px;">
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Deixe em branco para incluir todas as conversas.</p>
                        </div>
                        <div class="mb-4">
                            <label class="flex items-center mb-2">
                                <input type="checkbox" id="backup-include-media" class="w-4 h-4 text-green-600 rounded">
                                <span class="ml-2 text-sm text-gray-700">Incluir URLs de mídia</span>
                            </label>
                            <label class="flex items-center mb-2">
                                <input type="checkbox" id="backup-compress" checked class="w-4 h-4 text-green-600 rounded">
                                <span class="ml-2 text-sm text-gray-700">Comprimir backup (ZIP) - Economia de 60-80%</span>
                            </label>
                            <label class="flex items-center mb-2">
                                <input type="checkbox" id="backup-incremental" class="w-4 h-4 text-green-600 rounded">
                                <span class="ml-2 text-sm text-gray-700">Backup incremental (apenas mensagens novas)</span>
                            </label>
                            <label class="flex items-center mb-2">
                                <input type="checkbox" id="backup-encrypt" class="w-4 h-4 text-green-600 rounded">
                                <span class="ml-2 text-sm text-gray-700">Criptografar backup (AES-256)</span>
                            </label>
                        </div>
                        <div class="mb-4 hidden" id="backup-encrypt-password-field">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-lock mr-1"></i>Senha de Criptografia
                            </label>
                            <input type="password" id="backup-encrypt-password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500" placeholder="Digite uma senha forte">
                            <p class="text-xs text-yellow-600 mt-1"><i class="fas fa-exclamation-triangle mr-1"></i>Guarde esta senha! Sem ela não será possível abrir o backup.</p>
                        </div>
                        <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" id="backup-send-email" checked class="w-4 h-4 text-green-600 rounded">
                                <span class="ml-2 text-sm text-gray-700">Enviar email quando concluir</span>
                            </label>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeBackupCreateModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">
                                Cancelar
                            </button>
                            <button type="submit" id="backup-create-btn" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg">
                                <i class="fas fa-download mr-2"></i>Criar Backup
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Configuração -->
        <div id="backup-config-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
            <div class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-bold text-gray-800">
                            <i class="fas fa-cog text-gray-600 mr-2"></i>Configurações de Backup
                        </h3>
                        <button onclick="closeBackupConfigModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                <div class="p-6">
                    <form id="backup-config-form" onsubmit="submitBackupConfig(event)">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Destino do Backup</label>
                            <select id="config-destination" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500">
                                <option value="ftp">Servidor FTP/SFTP</option>
                                <option value="network">Servidor de Rede (SMB/NFS)</option>
                                <option value="google_drive">Google Drive</option>
                                <option value="onedrive">OneDrive</option>
                                <option value="dropbox">Dropbox</option>
                                <option value="s3">Amazon S3</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1"><i class="fas fa-info-circle mr-1"></i>Backups não são armazenados no servidor do sistema.</p>

                            <!-- Configurações FTP -->
                            <div id="ftp-config" class="mt-3 p-3 bg-gray-50 rounded-lg hidden">
                                <h4 class="text-sm font-medium text-gray-700 mb-2"><i class="fas fa-server mr-1"></i>Configuração FTP/SFTP</h4>
                                <div class="grid grid-cols-2 gap-2 mb-2">
                                    <input type="text" id="ftp-host" placeholder="Servidor (ex: ftp.empresa.com)" class="px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-green-500">
                                    <input type="number" id="ftp-port" placeholder="Porta (21 ou 22)" value="21" class="px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-green-500">
                                </div>
                                <div class="grid grid-cols-2 gap-2 mb-2">
                                    <input type="text" id="ftp-user" placeholder="Usuário" class="px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-green-500">
                                    <input type="password" id="ftp-pass" placeholder="Senha" class="px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-green-500">
                                </div>
                                <input type="text" id="ftp-path" placeholder="Pasta remota (ex: /backups/wats)" class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-green-500 mb-2">
                                <label class="flex items-center text-sm">
                                    <input type="checkbox" id="ftp-ssl" class="w-4 h-4 text-green-600 rounded mr-2">
                                    <span class="text-gray-600">Usar SFTP (conexão segura)</span>
                                </label>
                                <button type="button" onclick="testFtpConnection()" class="mt-2 text-sm text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-plug mr-1"></i>Testar Conexão
                                </button>
                            </div>

                            <!-- Configurações Rede -->
                            <div id="network-config" class="mt-3 p-3 bg-gray-50 rounded-lg hidden">
                                <h4 class="text-sm font-medium text-gray-700 mb-2"><i class="fas fa-network-wired mr-1"></i>Configuração de Rede</h4>
                                <input type="text" id="network-path" placeholder="Caminho de rede (ex: \\\\servidor\\backups ou /mnt/backup)" class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-green-500 mb-2">
                                <div class="grid grid-cols-2 gap-2 mb-2">
                                    <input type="text" id="network-user" placeholder="Usuário (opcional)" class="px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-green-500">
                                    <input type="password" id="network-pass" placeholder="Senha (opcional)" class="px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-green-500">
                                </div>
                                <p class="text-xs text-yellow-600"><i class="fas fa-exclamation-triangle mr-1"></i>O servidor deve ter acesso à rede especificada.</p>
                            </div>

                            <!-- Google Drive Config -->
                            <div id="google-drive-config" class="mt-3 p-3 bg-gray-50 rounded-lg hidden">
                                <h4 class="text-sm font-medium text-gray-700 mb-2"><i class="fab fa-google-drive mr-1"></i>Configuração Google Drive</h4>
                                <p class="text-xs text-gray-500 mb-3">Configure suas próprias credenciais OAuth do Google Cloud Console.</p>
                                <div class="mb-2">
                                    <input type="text" id="google-client-id" placeholder="Client ID" class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-green-500 mb-2">
                                    <input type="password" id="google-client-secret" placeholder="Client Secret" class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-green-500">
                                </div>
                                <div id="google-drive-status" class="mt-2 hidden">
                                    <span class="text-xs text-green-600"><i class="fas fa-check-circle mr-1"></i>Google Drive conectado</span>
                                </div>
                                <button type="button" id="btn-connect-google" onclick="connectGoogleDrive()" class="mt-2 text-sm text-blue-600 hover:text-blue-800">
                                    <i class="fab fa-google mr-1"></i>Conectar Google Drive
                                </button>
                                <p class="text-xs text-gray-400 mt-2"><i class="fas fa-info-circle mr-1"></i><a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="underline">Criar credenciais no Google Cloud Console</a></p>
                            </div>

                            <!-- OneDrive Config -->
                            <div id="onedrive-config" class="mt-3 p-3 bg-gray-50 rounded-lg hidden">
                                <h4 class="text-sm font-medium text-gray-700 mb-2"><i class="fab fa-microsoft mr-1"></i>Configuração OneDrive</h4>
                                <p class="text-xs text-gray-500 mb-3">Configure suas credenciais do Azure AD.</p>
                                <div class="mb-2">
                                    <input type="text" id="onedrive-client-id" placeholder="Application (client) ID" class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-green-500 mb-2">
                                    <input type="password" id="onedrive-client-secret" placeholder="Client Secret" class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-green-500 mb-2">
                                    <input type="text" id="onedrive-tenant-id" placeholder="Tenant ID (opcional, padrão: common)" class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-green-500">
                                </div>
                                <div id="onedrive-status" class="mt-2 hidden">
                                    <span class="text-xs text-green-600"><i class="fas fa-check-circle mr-1"></i>OneDrive conectado</span>
                                </div>
                                <button type="button" id="btn-connect-onedrive" onclick="connectOneDrive()" class="mt-2 text-sm text-blue-600 hover:text-blue-800">
                                    <i class="fab fa-microsoft mr-1"></i>Conectar OneDrive
                                </button>
                                <p class="text-xs text-gray-400 mt-2"><i class="fas fa-info-circle mr-1"></i><a href="https://portal.azure.com/#blade/Microsoft_AAD_RegisteredApps/ApplicationsListBlade" target="_blank" class="underline">Criar app no Azure Portal</a></p>
                            </div>

                            <!-- Dropbox Config -->
                            <div id="dropbox-config" class="mt-3 p-3 bg-gray-50 rounded-lg hidden">
                                <h4 class="text-sm font-medium text-gray-700 mb-2"><i class="fab fa-dropbox mr-1"></i>Configuração Dropbox</h4>
                                <p class="text-xs text-gray-500 mb-3">Configure suas credenciais do Dropbox App Console.</p>
                                <div class="mb-2">
                                    <input type="text" id="dropbox-app-key" placeholder="App Key" class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-green-500 mb-2">
                                    <input type="password" id="dropbox-app-secret" placeholder="App Secret" class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-green-500">
                                </div>
                                <div id="dropbox-status" class="mt-2 hidden">
                                    <span class="text-xs text-green-600"><i class="fas fa-check-circle mr-1"></i>Dropbox conectado</span>
                                </div>
                                <button type="button" id="btn-connect-dropbox" onclick="connectDropbox()" class="mt-2 text-sm text-blue-600 hover:text-blue-800">
                                    <i class="fab fa-dropbox mr-1"></i>Conectar Dropbox
                                </button>
                                <p class="text-xs text-gray-400 mt-2"><i class="fas fa-info-circle mr-1"></i><a href="https://www.dropbox.com/developers/apps" target="_blank" class="underline">Criar app no Dropbox Developers</a></p>
                            </div>

                            <!-- Amazon S3 Config -->
                            <div id="s3-config" class="mt-3 p-3 bg-gray-50 rounded-lg hidden">
                                <h4 class="text-sm font-medium text-gray-700 mb-2"><i class="fab fa-aws mr-1"></i>Configuração Amazon S3</h4>
                                <p class="text-xs text-gray-500 mb-3">Configure suas credenciais AWS IAM.</p>
                                <div class="mb-2">
                                    <input type="text" id="s3-access-key" placeholder="Access Key ID" class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-green-500 mb-2">
                                    <input type="password" id="s3-secret-key" placeholder="Secret Access Key" class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-green-500 mb-2">
                                    <input type="text" id="s3-bucket" placeholder="Bucket Name" class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-green-500 mb-2">
                                    <select id="s3-region" class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:border-green-500">
                                        <option value="us-east-1">US East (N. Virginia)</option>
                                        <option value="us-east-2">US East (Ohio)</option>
                                        <option value="us-west-1">US West (N. California)</option>
                                        <option value="us-west-2">US West (Oregon)</option>
                                        <option value="sa-east-1">South America (São Paulo)</option>
                                        <option value="eu-west-1">EU (Ireland)</option>
                                        <option value="eu-central-1">EU (Frankfurt)</option>
                                        <option value="ap-southeast-1">Asia Pacific (Singapore)</option>
                                        <option value="ap-northeast-1">Asia Pacific (Tokyo)</option>
                                    </select>
                                </div>
                                <button type="button" id="btn-test-s3" onclick="testS3Connection()" class="mt-2 text-sm text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-plug mr-1"></i>Testar Conexão S3
                                </button>
                                <p class="text-xs text-gray-400 mt-2"><i class="fas fa-info-circle mr-1"></i><a href="https://console.aws.amazon.com/iam/" target="_blank" class="underline">Criar credenciais no AWS IAM</a></p>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Agendamento</label>
                            <select id="config-schedule" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500">
                                <option value="manual">Manual</option>
                                <option value="daily">Diário</option>
                                <option value="weekly">Semanal</option>
                                <option value="monthly">Mensal</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Horário do Backup</label>
                            <input type="time" id="config-schedule-time" value="03:00" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Retenção (dias)</label>
                            <input type="number" id="config-retention" value="30" min="1" max="365" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500">
                            <p class="text-xs text-gray-500 mt-1">Backups mais antigos serão removidos automaticamente.</p>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Formato Padrão</label>
                            <select id="config-format" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500">
                                <option value="json">JSON</option>
                                <option value="csv">CSV</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" id="config-include-media" class="w-4 h-4 text-green-600 rounded">
                                <span class="ml-2 text-sm text-gray-700">Incluir URLs de mídia por padrão</span>
                            </label>
                        </div>
                        <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" id="config-is-active" checked class="w-4 h-4 text-green-600 rounded">
                                <span class="ml-2 text-sm text-gray-700">Backup automático ativo</span>
                            </label>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeBackupConfigModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">
                                Cancelar
                            </button>
                            <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg">
                                <i class="fas fa-save mr-2"></i>Salvar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Visualizar Backup -->
        <div id="backup-view-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
            <div class="bg-white rounded-lg shadow-xl max-w-6xl w-full mx-4 max-h-[90vh] flex flex-col">
                <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-eye text-green-600 mr-2"></i>Visualizar Backup
                    </h3>
                    <button onclick="closeBackupViewModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="p-4 border-b border-gray-200 bg-gray-50">
                    <div id="backup-view-info" class="flex flex-wrap gap-4 text-sm">
                        <!-- Info do backup será inserida aqui -->
                    </div>
                </div>
                <div class="flex-1 overflow-hidden flex">
                    <!-- Lista de conversas -->
                    <div class="w-1/3 border-r border-gray-200 overflow-y-auto">
                        <div class="p-3 bg-gray-100 border-b border-gray-200">
                            <h4 class="font-medium text-gray-700"><i class="fas fa-comments mr-2"></i>Conversas</h4>
                        </div>
                        <div id="backup-conversations-list" class="divide-y divide-gray-100">
                            <!-- Lista de conversas será inserida aqui -->
                        </div>
                    </div>
                    <!-- Mensagens da conversa selecionada -->
                    <div class="w-2/3 flex flex-col">
                        <div class="p-3 bg-gray-100 border-b border-gray-200">
                            <h4 id="backup-messages-title" class="font-medium text-gray-700"><i class="fas fa-envelope mr-2"></i>Mensagens</h4>
                        </div>
                        <div id="backup-messages-container" class="flex-1 overflow-y-auto p-4 bg-gray-50" style="max-height: 50vh;">
                            <div class="text-center text-gray-500 py-8">
                                <i class="fas fa-arrow-left text-4xl mb-2"></i>
                                <p>Selecione uma conversa para ver as mensagens</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Importar Backup -->
        <div id="backup-import-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
            <div style="background: var(--bg-card); border-radius: var(--radius-lg); border: 0.5px solid var(--border); max-width: 32rem; width: 100%; margin: var(--space-4);">
                <div style="padding: var(--space-4); border-bottom: 0.5px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                    <h3 style="font-size: 18px; font-weight: 600; letter-spacing: -0.02em; color: var(--text-primary); display: flex; align-items: center; gap: var(--space-2);">
                        <i class="fas fa-upload" style="color: var(--accent-primary);"></i>Importar Backup Local
                    </h3>
                    <button onclick="closeImportBackupModal()" style="color: var(--text-muted); background: none; border: none; cursor: pointer; padding: var(--space-1); font-size: 18px;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div style="padding: var(--space-6);">
                    <div style="margin-bottom: var(--space-4);">
                        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: var(--space-4);">
                            Selecione um arquivo de backup (.json.gz) que você baixou anteriormente para visualizar seu conteúdo.
                        </p>
                        <div style="background: rgba(59, 130, 246, 0.08); border-left: 3px solid #3b82f6; padding: var(--space-3); margin-bottom: var(--space-4); border-radius: var(--radius-sm);">
                            <div style="display: flex;">
                                <div style="flex-shrink: 0;">
                                    <i class="fas fa-info-circle" style="color: #3b82f6;"></i>
                                </div>
                                <div style="margin-left: var(--space-3);">
                                    <p style="font-size: 13px; color: var(--text-secondary);">
                                        O arquivo será processado localmente no seu navegador. Nenhum dado será enviado ao servidor.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <label style="display: block; font-size: 12px; font-weight: 500; color: var(--text-secondary); margin-bottom: var(--space-2);">
                            Arquivo de Backup
                        </label>
                        <input 
                            type="file" 
                            id="backup-import-file" 
                            accept=".gz,.json,.json.gz"
                            style="width: 100%; padding: var(--space-2) var(--space-3); border: 0.5px solid var(--border); border-radius: var(--radius-md); background: var(--bg-card); color: var(--text-primary); font-size: 13px;"
                        />
                    </div>
                    <div id="backup-import-status" class="hidden" style="margin-bottom: var(--space-4); padding: var(--space-3); border-radius: var(--radius-md);">
                        <div style="display: flex; align-items: center; gap: var(--space-2);">
                            <i class="fas fa-spinner fa-spin"></i>
                            <span id="backup-import-status-text">Processando arquivo...</span>
                        </div>
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: var(--space-3);">
                        <button type="button" onclick="closeImportBackupModal()" style="padding: var(--space-2) var(--space-4); color: var(--text-secondary); background: transparent; border: 0.5px solid var(--border); border-radius: var(--radius-md); cursor: pointer; font-size: 13px; transition: var(--transition-fast);" onmouseover="this.style.background='var(--bg-sidebar-hover)'" onmouseout="this.style.background='transparent'">
                            Cancelar
                        </button>
                        <button type="button" onclick="processImportedBackup()" id="backup-import-btn" style="padding: var(--space-2) var(--space-4); background: var(--accent-primary); color: white; border: none; border-radius: var(--radius-md); cursor: pointer; font-size: 13px; font-weight: 500; transition: var(--transition-fast);" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                            <i class="fas fa-eye" style="margin-right: var(--space-2);"></i>Visualizar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/pako/2.1.0/pako.min.js"></script>
        <script>
            // Estado dos backups
            let backupsData = [];
            let backupConfig = null;
            let currentBackupData = null;

            // Carregar dados ao iniciar
            document.addEventListener("DOMContentLoaded", function() {
                try {
                    loadBackups();
                    setupEventListeners();
                } catch (error) {
                    console.error('Error in DOMContentLoaded:', error);
                }
            });

            // Carregar imediatamente se já estiver no DOM
            if (document.readyState === "complete" || document.readyState === "interactive") {
                try {
                    loadBackups();
                    setupEventListeners();
                } catch (error) {
                    console.error('Error in immediate initialization:', error);
                }
            }

            // Configurar event listeners dos botões
            function setupEventListeners() {
                // Botão Baixar Todas Conversas
                const btnDownloadAll = document.getElementById('btn-download-all-conversations');
                if (btnDownloadAll) {
                    btnDownloadAll.addEventListener('click', downloadAllConversations);
                }

                // Botão Configurar
                const btnOpenConfig = document.getElementById('btn-open-config');
                if (btnOpenConfig) {
                    btnOpenConfig.addEventListener('click', openBackupConfigModal);
                }

                // Botão Criar Backup
                const btnCreateBackup = document.getElementById('btn-create-backup');
                if (btnCreateBackup) {
                    btnCreateBackup.addEventListener('click', createBackupNow);
                }
            }

            async function loadBackups() {
                try {
                    const response = await fetch("/api/backup_list.php");
                    const data = await response.json();

                    if (data.success) {
                        backupsData = data.backups;
                        backupConfig = data.config;

                        // Atualizar estatísticas
                        document.getElementById("stat-total").textContent = data.stats.total_backups;
                        document.getElementById("stat-size").textContent = data.stats.total_size_formatted;
                        document.getElementById("stat-last").textContent = data.stats.last_backup ? formatDate(data.stats.last_backup) : "Nunca";
                        document.getElementById("stat-next").textContent = data.stats.next_scheduled ? formatDate(data.stats.next_scheduled) : "Não agendado";

                        // Renderizar tabela
                        renderBackupsTable();

                        // Preencher config se existir
                        if (backupConfig) {
                            // Converter local/download antigo para ftp
                            let dest = backupConfig.destination || "ftp";
                            if (dest === "local" || dest === "download") dest = "ftp";

                            document.getElementById("config-destination").value = dest;
                            document.getElementById("config-schedule").value = backupConfig.schedule || "manual";
                            document.getElementById("config-schedule-time").value = backupConfig.schedule_time || "03:00";
                            document.getElementById("config-retention").value = backupConfig.retention_days || 30;
                            document.getElementById("config-format").value = backupConfig.format || "json";
                            document.getElementById("config-include-media").checked = backupConfig.include_media || false;
                            document.getElementById("config-is-active").checked = backupConfig.is_active !== false;

                            // Preencher configurações FTP se existirem
                            if (backupConfig.ftp_config) {
                                document.getElementById("ftp-host").value = backupConfig.ftp_config.host || "";
                                document.getElementById("ftp-port").value = backupConfig.ftp_config.port || 21;
                                document.getElementById("ftp-user").value = backupConfig.ftp_config.user || "";
                                document.getElementById("ftp-path").value = backupConfig.ftp_config.path || "";
                                document.getElementById("ftp-ssl").checked = backupConfig.ftp_config.ssl || false;
                            }

                            // Preencher configurações de Rede se existirem
                            if (backupConfig.network_config) {
                                document.getElementById("network-path").value = backupConfig.network_config.path || "";
                                document.getElementById("network-user").value = backupConfig.network_config.user || "";
                            }

                            // Preencher configurações Google Drive se existirem
                            if (backupConfig.google_config) {
                                document.getElementById("google-client-id").value = backupConfig.google_config.client_id || "";
                                // Não preencher secret por segurança, mas indicar que existe
                                if (backupConfig.google_config.has_secret) {
                                    document.getElementById("google-client-secret").placeholder = "••••••••••••••••";
                                }
                            }

                            // Preencher configurações OneDrive se existirem
                            if (backupConfig.onedrive_config) {
                                document.getElementById("onedrive-client-id").value = backupConfig.onedrive_config.client_id || "";
                                document.getElementById("onedrive-tenant-id").value = backupConfig.onedrive_config.tenant_id || "";
                                if (backupConfig.onedrive_config.has_secret) {
                                    document.getElementById("onedrive-client-secret").placeholder = "••••••••••••••••";
                                }
                            }

                            // Preencher configurações Dropbox se existirem
                            if (backupConfig.dropbox_config) {
                                document.getElementById("dropbox-app-key").value = backupConfig.dropbox_config.app_key || "";
                                if (backupConfig.dropbox_config.has_secret) {
                                    document.getElementById("dropbox-app-secret").placeholder = "••••••••••••••••";
                                }
                            }

                            // Preencher configurações S3 se existirem
                            if (backupConfig.s3_config) {
                                document.getElementById("s3-access-key").value = backupConfig.s3_config.access_key || "";
                                document.getElementById("s3-bucket").value = backupConfig.s3_config.bucket || "";
                                document.getElementById("s3-region").value = backupConfig.s3_config.region || "us-east-1";
                                if (backupConfig.s3_config.has_secret) {
                                    document.getElementById("s3-secret-key").placeholder = "••••••••••••••••";
                                }
                            }

                            // Disparar evento change para mostrar campos corretos
                            document.getElementById("config-destination").dispatchEvent(new Event("change"));
                        }
                    } else {
                        showError("Erro ao carregar backups: " + (data.error || "Erro desconhecido"));
                    }
                } catch (error) {
                    console.error("Erro:", error);
                    showError("Erro ao conectar com o servidor");
                }
            }

            function renderBackupsTable() {
                const tbody = document.getElementById("backups-table-body");

                if (backupsData.length === 0) {
                    tbody.innerHTML = `
                    <tr>
                        <td colspan="7" style="text-align: center; padding: var(--space-8); color: var(--text-muted);">
                            <i class="fas fa-archive" style="font-size: 32px; opacity: 0.3; display: block; margin-bottom: var(--space-3);"></i>
                            <p style="margin-bottom: var(--space-3);">Nenhum backup encontrado.</p>
                            <button onclick="createBackupNow()" class="backup-btn backup-btn-primary">
                                <i class="fas fa-plus"></i>Criar Primeiro Backup
                            </button>
                        </td>
                    </tr>
                `;
                    return;
                }

                tbody.innerHTML = backupsData.map(backup => {
                    const statusBadge = getStatusBadge(backup.status);
                    const canDownload = backup.can_download;
                    const isCloud = ['google_drive', 'onedrive', 'dropbox', 's3'].includes(backup.destination);
                    const destIcon = getDestinationIcon(backup.destination);

                    return `
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: var(--space-2);">
                                <i class="fas fa-file-archive" style="color: var(--text-muted);"></i>
                                <span style="font-weight: 500; color: var(--text-primary);">${escapeHtml(backup.filename)}</span>
                            </div>
                        </td>
                        <td style="font-family: monospace; font-variant-numeric: tabular-nums;">${backup.size_formatted}</td>
                        <td style="font-family: monospace; font-variant-numeric: tabular-nums;">${backup.conversations_count}</td>
                        <td style="font-family: monospace; font-variant-numeric: tabular-nums;">${backup.messages_count}</td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                ${statusBadge}
                                ${destIcon}
                            </div>
                        </td>
                        <td>${formatDate(backup.created_at)}</td>
                        <td>
                            <div style="display: flex; align-items: center; gap: var(--space-2);">
                                <button onclick="viewBackup(${backup.id})" class="backup-btn backup-btn-secondary" title="Visualizar"><i class="fas fa-eye"></i></button>
                                ${canDownload ? `<a href="/api/backup_download.php?id=${backup.id}" class="backup-btn backup-btn-secondary" title="Download"><i class="fas fa-download"></i></a>` : ""}
                                ${backup.remote_url ? `<a href="${backup.remote_url}" target="_blank" class="backup-btn backup-btn-secondary" title="Abrir na nuvem"><i class="fas fa-external-link-alt"></i></a>` : ""}
                                <button onclick="deleteBackup(${backup.id})" class="backup-btn backup-btn-danger" title="Excluir"><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                `;
                }).join("");
            }
            
            function getDestinationIcon(dest) {
                const icons = {
                    'download': '<span class="backup-badge backup-badge-info" title="Download Local"><i class="fas fa-download"></i></span>',
                    'ftp': '<span class="backup-badge backup-badge-info" title="FTP"><i class="fas fa-server"></i></span>',
                    'network': '<span class="backup-badge backup-badge-info" title="Rede"><i class="fas fa-network-wired"></i></span>',
                    'google_drive': '<span class="backup-badge" style="background: rgba(66, 133, 244, 0.1); color: #4285f4;" title="Google Drive"><i class="fab fa-google-drive"></i></span>',
                    'onedrive': '<span class="backup-badge" style="background: rgba(0, 120, 212, 0.1); color: #0078d4;" title="OneDrive"><i class="fab fa-microsoft"></i></span>',
                    'dropbox': '<span class="backup-badge" style="background: rgba(0, 126, 229, 0.1); color: #007ee5;" title="Dropbox"><i class="fab fa-dropbox"></i></span>',
                    's3': '<span class="backup-badge" style="background: rgba(255, 153, 0, 0.1); color: #ff9900;" title="Amazon S3"><i class="fab fa-aws"></i></span>'
                };
                return icons[dest] || '';
            }

            function getStatusBadge(status) {
                const badges = {
                    pending: '<span class="backup-badge backup-badge-warning">Pendente</span>',
                    running: '<span class="backup-badge backup-badge-info"><i class="fas fa-spinner fa-spin" style="margin-right: 4px;"></i>Processando</span>',
                    completed: '<span class="backup-badge backup-badge-success">Concluído</span>',
                    failed: '<span class="backup-badge backup-badge-error">Falhou</span>',
                    deleted: '<span class="backup-badge" style="background: var(--bg-sidebar-hover); color: var(--text-muted);">Excluído</span>'
                };
                return badges[status] || status;
            }

            function formatDate(dateStr) {
                if (!dateStr) return "-";
                const date = new Date(dateStr);
                return date.toLocaleDateString("pt-BR") + " " + date.toLocaleTimeString("pt-BR", {
                    hour: "2-digit",
                    minute: "2-digit"
                });
            }

            function escapeHtml(text) {
                if (!text) return "";
                const div = document.createElement("div");
                div.textContent = text;
                return div.innerHTML;
            }

            // Modal Criar Backup
            function createBackupNow() {
                document.getElementById("backup-create-modal").classList.remove("hidden");
            }

            function closeBackupCreateModal() {
                document.getElementById("backup-create-modal").classList.add("hidden");
            }

            // Baixar todas as conversas
            async function downloadAllConversations(event) {
                const btn = event ? event.target : document.getElementById('btn-download-all-conversations');
                if (!btn) {
                    console.error('Botão não encontrado');
                    return;
                }
                
                const originalHTML = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparando...';

                try {
                    const response = await fetch("/api/backup_create.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify({
                            format: "json",
                            include_media: true,
                            compress: true,
                            incremental: false,
                            encrypt: false,
                            send_email: false,
                            force_local: true  // Forçar criação local para download
                        })
                    });

                    const data = await response.json();

                    if (data.success && data.backup_id) {
                        showSuccess(`Backup criado! ${data.conversations || 0} conversas, ${data.messages || 0} mensagens. Iniciando download...`);
                        
                        // Aguardar 2 segundos para o backup ser finalizado
                        setTimeout(() => {
                            // Iniciar download usando o backup_id
                            window.location.href = `/api/backup_download.php?id=${data.backup_id}`;
                            
                            // Recarregar lista de backups após o download
                            setTimeout(() => {
                                loadBackups();
                            }, 2000);
                        }, 2000);
                    } else {
                        showError("Erro ao criar backup: " + (data.error || "Erro desconhecido"));
                        btn.disabled = false;
                        btn.innerHTML = originalHTML;
                    }
                } catch (error) {
                    console.error("Erro:", error);
                    showError("Erro ao baixar conversas: " + error.message);
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                }
            }

            window.submitBackupCreate = async function(event) {
                event.preventDefault();

                const btn = document.getElementById("backup-create-btn");
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Criando...';

                const payload = {
                    format: document.getElementById("backup-format").value,
                    date_from: document.getElementById("backup-date-from").value || null,
                    date_to: document.getElementById("backup-date-to").value || null,
                    include_media: document.getElementById("backup-include-media").checked,
                    compress: document.getElementById("backup-compress").checked,
                    incremental: document.getElementById("backup-incremental").checked,
                    encrypt: document.getElementById("backup-encrypt").checked,
                    encrypt_password: document.getElementById("backup-encrypt").checked ? document.getElementById("backup-encrypt-password").value : null,
                    send_email: document.getElementById("backup-send-email").checked
                };

                // Validar senha de criptografia se habilitada
                if (payload.encrypt && (!payload.encrypt_password || payload.encrypt_password.length < 6)) {
                    showError("Senha de criptografia deve ter no mínimo 6 caracteres");
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-download mr-2"></i>Criar Backup';
                    return;
                }

                try {
                    const response = await fetch("/api/backup_create.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify(payload)
                    });

                    const data = await response.json();

                    if (data.success) {
                        showSuccess(data.message || `Backup criado com sucesso! ${data.conversations} conversas, ${data.messages} mensagens.`);
                        closeBackupCreateModal();
                        loadBackups();
                    } else {
                        showError("Erro ao criar backup: " + (data.error || "Erro desconhecido"));
                    }
                } catch (error) {
                    console.error("Erro:", error);
                    showError("Erro ao criar backup");
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-download mr-2"></i>Criar Backup';
                }
            }

            // Modal Configuração
            function openBackupConfigModal() {
                document.getElementById("backup-config-modal").classList.remove("hidden");
            }

            function closeBackupConfigModal() {
                document.getElementById("backup-config-modal").classList.add("hidden");
            }

            async function submitBackupConfig(event) {
                event.preventDefault();

                const destination = document.getElementById("config-destination").value;

                const payload = {
                    destination: destination,
                    schedule: document.getElementById("config-schedule").value,
                    schedule_time: document.getElementById("config-schedule-time").value,
                    retention_days: parseInt(document.getElementById("config-retention").value),
                    format: document.getElementById("config-format").value,
                    include_media: document.getElementById("config-include-media").checked,
                    is_active: document.getElementById("config-is-active").checked
                };

                // Adicionar configurações FTP se selecionado
                if (destination === "ftp") {
                    payload.ftp_config = {
                        host: document.getElementById("ftp-host").value,
                        port: parseInt(document.getElementById("ftp-port").value) || 21,
                        user: document.getElementById("ftp-user").value,
                        pass: document.getElementById("ftp-pass").value,
                        path: document.getElementById("ftp-path").value,
                        ssl: document.getElementById("ftp-ssl").checked
                    };

                    if (!payload.ftp_config.host || !payload.ftp_config.user || !payload.ftp_config.pass) {
                        showError("Preencha todos os campos obrigatórios do FTP");
                        return;
                    }
                }

                // Adicionar configurações de Rede se selecionado
                if (destination === "network") {
                    payload.network_config = {
                        path: document.getElementById("network-path").value,
                        user: document.getElementById("network-user").value,
                        pass: document.getElementById("network-pass").value
                    };

                    if (!payload.network_config.path) {
                        showError("Informe o caminho de rede");
                        return;
                    }
                }

                // Adicionar configurações Google Drive se selecionado
                if (destination === "google_drive") {
                    const clientId = document.getElementById("google-client-id").value.trim();
                    const clientSecret = document.getElementById("google-client-secret").value.trim();
                    if (clientId && clientSecret) {
                        payload.google_config = {
                            client_id: clientId,
                            client_secret: clientSecret
                        };
                    }
                }

                // Adicionar configurações OneDrive se selecionado
                if (destination === "onedrive") {
                    const clientId = document.getElementById("onedrive-client-id").value.trim();
                    const clientSecret = document.getElementById("onedrive-client-secret").value.trim();
                    const tenantId = document.getElementById("onedrive-tenant-id").value.trim() || "common";
                    if (clientId && clientSecret) {
                        payload.onedrive_config = {
                            client_id: clientId,
                            client_secret: clientSecret,
                            tenant_id: tenantId
                        };
                    }
                }

                // Adicionar configurações Dropbox se selecionado
                if (destination === "dropbox") {
                    const appKey = document.getElementById("dropbox-app-key").value.trim();
                    const appSecret = document.getElementById("dropbox-app-secret").value.trim();
                    if (appKey && appSecret) {
                        payload.dropbox_config = {
                            app_key: appKey,
                            app_secret: appSecret
                        };
                    }
                }

                // Adicionar configurações S3 se selecionado
                if (destination === "s3") {
                    payload.s3_config = {
                        access_key: document.getElementById("s3-access-key").value.trim(),
                        secret_key: document.getElementById("s3-secret-key").value.trim(),
                        bucket: document.getElementById("s3-bucket").value.trim(),
                        region: document.getElementById("s3-region").value
                    };

                    if (!payload.s3_config.access_key || !payload.s3_config.secret_key || !payload.s3_config.bucket) {
                        showError("Preencha todos os campos obrigatórios do S3");
                        return;
                    }
                }

                try {
                    const response = await fetch("/api/backup_config.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify(payload)
                    });

                    const data = await response.json();

                    if (data.success) {
                        showSuccess("Configurações salvas com sucesso!");
                        closeBackupConfigModal();
                        loadBackups();
                    } else {
                        showError("Erro ao salvar: " + (data.error || "Erro desconhecido"));
                    }
                } catch (error) {
                    console.error("Erro:", error);
                    showError("Erro ao salvar configurações");
                }
            }

            // Excluir backup
            window.deleteBackup = async function(backupId) {
                if (!confirm("Deseja realmente excluir este backup?")) return;

                try {
                    const response = await fetch("/api/backup_delete.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify({
                            backup_id: backupId
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        showSuccess("Backup excluído com sucesso!");
                        loadBackups();
                    } else {
                        showError("Erro ao excluir: " + (data.error || "Erro desconhecido"));
                    }
                } catch (error) {
                    console.error("Erro:", error);
                    showError("Erro ao excluir backup");
                }
            }

            // Notificações
            function showSuccess(message) {
                const notification = document.createElement("div");
                notification.className = "fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50";
                notification.innerHTML = '<i class="fas fa-check-circle mr-2"></i>' + message;
                document.body.appendChild(notification);
                setTimeout(() => notification.remove(), 3000);
            }

            function showError(message) {
                const notification = document.createElement("div");
                notification.className = "fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50";
                notification.innerHTML = '<i class="fas fa-exclamation-circle mr-2"></i>' + message;
                document.body.appendChild(notification);
                setTimeout(() => notification.remove(), 5000);
            }

            // Google Drive - Salvar credenciais e conectar
            async function connectGoogleDrive() {
                const clientId = document.getElementById("google-client-id").value.trim();
                const clientSecret = document.getElementById("google-client-secret").value.trim();

                if (!clientId || !clientSecret) {
                    showError("Preencha o Client ID e Client Secret do Google");
                    return;
                }

                // Salvar credenciais primeiro
                try {
                    const response = await fetch("/api/backup_config.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({
                            save_cloud_credentials: true,
                            provider: "google_drive",
                            client_id: clientId,
                            client_secret: clientSecret
                        })
                    });
                    const data = await response.json();
                    if (data.success) {
                        // Redirecionar para OAuth
                        window.location.href = "/api/backup_oauth_google.php";
                    } else {
                        showError("Erro ao salvar credenciais: " + (data.error || "Erro desconhecido"));
                    }
                } catch (error) {
                    console.error("Erro:", error);
                    showError("Erro ao salvar credenciais");
                }
            }

            // OneDrive - Salvar credenciais e conectar
            async function connectOneDrive() {
                const clientId = document.getElementById("onedrive-client-id").value.trim();
                const clientSecret = document.getElementById("onedrive-client-secret").value.trim();
                const tenantId = document.getElementById("onedrive-tenant-id").value.trim() || "common";

                if (!clientId || !clientSecret) {
                    showError("Preencha o Client ID e Client Secret do OneDrive");
                    return;
                }

                try {
                    const response = await fetch("/api/backup_config.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({
                            save_cloud_credentials: true,
                            provider: "onedrive",
                            client_id: clientId,
                            client_secret: clientSecret,
                            tenant_id: tenantId
                        })
                    });
                    const data = await response.json();
                    if (data.success) {
                        window.location.href = "/api/backup_oauth_onedrive.php";
                    } else {
                        showError("Erro ao salvar credenciais: " + (data.error || "Erro desconhecido"));
                    }
                } catch (error) {
                    console.error("Erro:", error);
                    showError("Erro ao salvar credenciais");
                }
            }

            // Dropbox - Salvar credenciais e conectar
            async function connectDropbox() {
                const appKey = document.getElementById("dropbox-app-key").value.trim();
                const appSecret = document.getElementById("dropbox-app-secret").value.trim();

                if (!appKey || !appSecret) {
                    showError("Preencha o App Key e App Secret do Dropbox");
                    return;
                }

                try {
                    const response = await fetch("/api/backup_config.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({
                            save_cloud_credentials: true,
                            provider: "dropbox",
                            app_key: appKey,
                            app_secret: appSecret
                        })
                    });
                    const data = await response.json();
                    if (data.success) {
                        window.location.href = "/api/backup_oauth_dropbox.php";
                    } else {
                        showError("Erro ao salvar credenciais: " + (data.error || "Erro desconhecido"));
                    }
                } catch (error) {
                    console.error("Erro:", error);
                    showError("Erro ao salvar credenciais");
                }
            }

            // Amazon S3 - Testar conexão
            async function testS3Connection() {
                const accessKey = document.getElementById("s3-access-key").value.trim();
                const secretKey = document.getElementById("s3-secret-key").value.trim();
                const bucket = document.getElementById("s3-bucket").value.trim();
                const region = document.getElementById("s3-region").value;

                if (!accessKey || !secretKey || !bucket) {
                    showError("Preencha todos os campos do S3");
                    return;
                }

                try {
                    const response = await fetch("/api/backup_test_s3.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({
                            access_key: accessKey,
                            secret_key: secretKey,
                            bucket: bucket,
                            region: region
                        })
                    });
                    const data = await response.json();
                    if (data.success) {
                        showSuccess("Conexão S3 estabelecida com sucesso!");
                    } else {
                        showError("Falha na conexão S3: " + (data.error || "Erro desconhecido"));
                    }
                } catch (error) {
                    console.error("Erro:", error);
                    showError("Erro ao testar conexão S3");
                }
            }

            // Upload para nuvem
            async function uploadToCloud(backupId) {
                if (!confirm("Deseja enviar este backup para o Google Drive?")) return;

                try {
                    const response = await fetch("/api/backup_upload_cloud.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify({
                            backup_id: backupId
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        showSuccess("Backup enviado para o Google Drive!");
                        loadBackups();
                    } else {
                        showError("Erro ao enviar: " + (data.error || "Erro desconhecido"));
                    }
                } catch (error) {
                    console.error("Erro:", error);
                    showError("Erro ao enviar backup para nuvem");
                }
            }

            // Visualizar backup
            window.viewBackup = async function(backupId, password = null) {
                document.getElementById("backup-view-modal").classList.remove("hidden");
                document.getElementById("backup-view-info").innerHTML = "<div class=\"text-center w-full\"><i class=\"fas fa-spinner fa-spin mr-2\"></i>Carregando backup...</div>";
                document.getElementById("backup-conversations-list").innerHTML = "";
                document.getElementById("backup-messages-container").innerHTML = "<div class=\"text-center text-gray-500 py-8\"><i class=\"fas fa-spinner fa-spin text-4xl mb-2\"></i><p>Carregando...</p></div>";

                try {
                    let url = `/api/backup_view.php?id=${backupId}`;
                    if (password) {
                        url += `&password=${encodeURIComponent(password)}`;
                    }
                    
                    const response = await fetch(url);
                    const data = await response.json();

                    if (data.success) {
                        currentBackupData = data;
                        renderBackupView(data);
                    } else if (data.encrypted) {
                        // Backup criptografado - pedir senha
                        showPasswordPrompt(backupId);
                    } else if (data.can_fetch_from_cloud) {
                        // Backup na nuvem - mostrar opção de buscar
                        document.getElementById("backup-view-info").innerHTML = `
                            <div class="w-full text-center">
                                <i class="fas fa-cloud text-4xl text-blue-500 mb-3"></i>
                                <p class="text-gray-700 mb-3">${data.error || "Este backup está armazenado na nuvem."}</p>
                                <p class="text-sm text-gray-500 mb-4">Destino: <strong>${getDestinationLabel(data.destination)}</strong></p>
                                <button onclick="fetchCloudBackup(${backupId})" class="backup-btn backup-btn-primary">
                                    <i class="fas fa-cloud-download-alt mr-2"></i>Buscar da Nuvem
                                </button>
                            </div>
                        `;
                        document.getElementById("backup-conversations-list").innerHTML = "";
                        document.getElementById("backup-messages-container").innerHTML = "";
                    } else {
                        document.getElementById("backup-view-info").innerHTML = `<div class="text-red-600 w-full"><i class="fas fa-exclamation-circle mr-2"></i>${data.error || "Erro ao carregar backup"}</div>`;
                    }
                } catch (error) {
                    console.error("Erro:", error);
                    document.getElementById("backup-view-info").innerHTML = "<div class=\"text-red-600 w-full\"><i class=\"fas fa-exclamation-circle mr-2\"></i>Erro ao carregar backup</div>";
                }
            }
            
            function showPasswordPrompt(backupId) {
                document.getElementById("backup-view-info").innerHTML = `
                    <div class="w-full">
                        <div class="text-center mb-4">
                            <i class="fas fa-lock text-4xl text-yellow-500 mb-2"></i>
                            <p class="text-gray-700">Este backup está criptografado.</p>
                        </div>
                        <div class="flex items-center gap-2 max-w-md mx-auto">
                            <input type="password" id="backup-decrypt-password" placeholder="Digite a senha" 
                                class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500">
                            <button onclick="decryptAndView(${backupId})" class="backup-btn backup-btn-primary">
                                <i class="fas fa-unlock mr-1"></i>Abrir
                            </button>
                        </div>
                    </div>
                `;
                document.getElementById("backup-conversations-list").innerHTML = "";
                document.getElementById("backup-messages-container").innerHTML = "";
                
                // Focus no campo de senha
                setTimeout(() => {
                    document.getElementById("backup-decrypt-password")?.focus();
                }, 100);
            }
            
            function decryptAndView(backupId) {
                const password = document.getElementById("backup-decrypt-password")?.value;
                if (!password) {
                    showError("Digite a senha para abrir o backup");
                    return;
                }
                viewBackup(backupId, password);
            }
            
            async function fetchCloudBackup(backupId) {
                document.getElementById("backup-view-info").innerHTML = "<div class=\"text-center w-full\"><i class=\"fas fa-spinner fa-spin mr-2\"></i>Buscando backup da nuvem...</div>";
                
                // Tentar novamente - o backend vai buscar da nuvem
                viewBackup(backupId);
            }
            
            function getDestinationLabel(dest) {
                const labels = {
                    'download': 'Download Local',
                    'ftp': 'Servidor FTP',
                    'network': 'Servidor de Rede',
                    'google_drive': 'Google Drive',
                    'onedrive': 'OneDrive',
                    'dropbox': 'Dropbox',
                    's3': 'Amazon S3'
                };
                return labels[dest] || dest;
            }

            window.closeBackupViewModal = function() {
                document.getElementById("backup-view-modal").classList.add("hidden");
                currentBackupData = null;
            }

            // Funções para importar backup local
            function openImportBackupModal() {
                document.getElementById("backup-import-modal").classList.remove("hidden");
                document.getElementById("backup-import-file").value = "";
                document.getElementById("backup-import-status").classList.add("hidden");
            }

            function closeImportBackupModal() {
                document.getElementById("backup-import-modal").classList.add("hidden");
            }

            // Função para carregar pako.js dinamicamente
            function loadPako() {
                return new Promise((resolve, reject) => {
                    // Verificar se pako já está carregado
                    if (typeof pako !== 'undefined') {
                        resolve();
                        return;
                    }

                    // Carregar script
                    const script = document.createElement('script');
                    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pako/2.1.0/pako.min.js';
                    script.onload = () => resolve();
                    script.onerror = () => reject(new Error('Erro ao carregar biblioteca de descompressão'));
                    document.head.appendChild(script);
                });
            }

            async function processImportedBackup() {
                const fileInput = document.getElementById("backup-import-file");
                const file = fileInput.files[0];

                if (!file) {
                    showError("Selecione um arquivo de backup");
                    return;
                }

                const statusDiv = document.getElementById("backup-import-status");
                const statusText = document.getElementById("backup-import-status-text");
                const btn = document.getElementById("backup-import-btn");

                // Mostrar status de processamento
                statusDiv.classList.remove("hidden");
                statusDiv.style.background = "rgba(59, 130, 246, 0.08)";
                statusDiv.style.color = "var(--text-secondary)";
                statusText.textContent = "Lendo arquivo...";
                btn.disabled = true;

                try {
                    // Verificar se é arquivo comprimido e carregar pako se necessário
                    if (file.name.endsWith('.gz')) {
                        statusText.textContent = "Carregando biblioteca de descompressão...";
                        await loadPako();
                    }

                    statusText.textContent = "Lendo arquivo...";

                    // Ler o arquivo
                    const arrayBuffer = await file.arrayBuffer();
                    const uint8Array = new Uint8Array(arrayBuffer);

                    let jsonData;

                    // Verificar se é um arquivo comprimido (.gz)
                    if (file.name.endsWith('.gz')) {
                        statusText.textContent = "Descomprimindo arquivo...";
                        
                        // Descomprimir usando pako
                        try {
                            const decompressed = pako.ungzip(uint8Array, { to: 'string' });
                            jsonData = JSON.parse(decompressed);
                        } catch (error) {
                            console.error("Erro ao descomprimir:", error);
                            throw new Error("Erro ao descomprimir arquivo. Verifique se o arquivo está correto.");
                        }
                    } else {
                        // Arquivo JSON não comprimido
                        statusText.textContent = "Processando JSON...";
                        const textDecoder = new TextDecoder('utf-8');
                        const jsonString = textDecoder.decode(uint8Array);
                        jsonData = JSON.parse(jsonString);
                    }

                    statusText.textContent = "Validando dados...";

                    // Validar estrutura do backup (aceitar tanto 'backup' quanto 'backup_info')
                    const backupInfo = jsonData.backup || jsonData.backup_info;
                    if (!backupInfo || !jsonData.conversations) {
                        throw new Error("Formato de backup inválido. O arquivo deve conter 'backup' e 'conversations'.");
                    }

                    statusText.textContent = "Preparando visualização...";

                    // Processar conversas para o formato esperado pelo visualizador
                    const processedConversations = jsonData.conversations.map(item => {
                        const conv = item.conversation || {};
                        const messages = item.messages || [];
                        
                        return {
                            phone: conv.phone || conv.contact_phone || 'Desconhecido',
                            contact_name: conv.contact_name || conv.name || 'Sem nome',
                            messages_count: messages.length,
                            messages: messages
                        };
                    });

                    // Preparar dados para visualização
                    const backupData = {
                        success: true,
                        backup: {
                            filename: file.name,
                            created_at: backupInfo.created_at || new Date().toISOString(),
                            conversations_count: processedConversations.length,
                            messages_count: processedConversations.reduce((sum, conv) => sum + conv.messages_count, 0),
                            size_bytes: file.size
                        },
                        conversations: processedConversations,
                        source: 'imported'
                    };

                    // Fechar modal de importação
                    closeImportBackupModal();

                    // Abrir modal de visualização com os dados importados
                    currentBackupData = backupData;
                    document.getElementById("backup-view-modal").classList.remove("hidden");
                    renderBackupView(backupData);

                    showSuccess(`Backup importado com sucesso! ${backupData.backup.conversations_count} conversas, ${backupData.backup.messages_count} mensagens.`);

                } catch (error) {
                    console.error("Erro ao processar backup:", error);
                    statusDiv.style.background = "rgba(239, 68, 68, 0.08)";
                    statusDiv.style.color = "#ef4444";
                    statusText.innerHTML = `<i class="fas fa-exclamation-circle mr-2"></i>${error.message || "Erro ao processar arquivo"}`;
                    btn.disabled = false;
                }
            }

            function renderBackupView(data) {
                const backup = data.backup;
                const conversations = data.conversations || [];
                const source = data.source || 'local';

                // Info do backup com indicador de origem
                let sourceIcon = '<i class="fas fa-hdd text-gray-500"></i>';
                let sourceLabel = 'Local';
                
                if (source === 'imported') {
                    sourceIcon = '<i class="fas fa-file-import text-purple-500"></i>';
                    sourceLabel = 'Importado';
                } else if (source === 'google_drive') {
                    sourceIcon = '<i class="fab fa-google-drive text-blue-500"></i>';
                    sourceLabel = 'Google Drive';
                } else if (source === 'onedrive') {
                    sourceIcon = '<i class="fab fa-microsoft text-blue-600"></i>';
                    sourceLabel = 'OneDrive';
                } else if (source === 'dropbox') {
                    sourceIcon = '<i class="fab fa-dropbox text-blue-500"></i>';
                    sourceLabel = 'Dropbox';
                } else if (source === 's3') {
                    sourceIcon = '<i class="fab fa-aws text-orange-500"></i>';
                    sourceLabel = 'Amazon S3';
                }

                document.getElementById("backup-view-info").innerHTML = `
                <div class="flex flex-wrap gap-4 items-center">
                    <div><strong>Arquivo:</strong> ${escapeHtml(backup.filename)}</div>
                    <div><strong>Data:</strong> ${formatDate(backup.created_at)}</div>
                    <div><strong>Conversas:</strong> ${backup.conversations_count}</div>
                    <div><strong>Mensagens:</strong> ${backup.messages_count}</div>
                    <div><strong>Tamanho:</strong> ${formatBytes(backup.size_bytes)}</div>
                    <div class="flex items-center gap-1"><strong>Origem:</strong> ${sourceIcon} ${sourceLabel}</div>
                </div>
            `;

                // Lista de conversas
                if (conversations.length === 0) {
                    document.getElementById("backup-conversations-list").innerHTML = "<div class=\"p-4 text-center text-gray-500\">Nenhuma conversa no backup</div>";
                } else {
                    document.getElementById("backup-conversations-list").innerHTML = conversations.map((conv, index) => `
                    <div class="p-3 hover:bg-gray-50 cursor-pointer transition-colors" onclick="selectBackupConversation(${index})" data-conv-index="${index}">
                        <div class="font-medium text-gray-800">${escapeHtml(conv.contact_name || "Sem nome")}</div>
                        <div class="text-sm text-gray-500">${escapeHtml(conv.phone)}</div>
                        <div class="text-xs text-gray-400">${conv.messages_count || conv.messages?.length || 0} mensagens</div>
                    </div>
                `).join("");
                }

                // Mensagem inicial
                document.getElementById("backup-messages-container").innerHTML = `
                <div class="text-center text-gray-500 py-8">
                    <i class="fas fa-arrow-left text-4xl mb-2"></i>
                    <p>Selecione uma conversa para ver as mensagens</p>
                </div>
            `;
            }

            function selectBackupConversation(index) {
                if (!currentBackupData || !currentBackupData.conversations) return;

                const conv = currentBackupData.conversations[index];
                if (!conv) return;

                // Destacar conversa selecionada
                document.querySelectorAll("#backup-conversations-list > div").forEach(el => {
                    el.classList.remove("bg-green-50", "border-l-4", "border-green-500");
                });
                const selectedEl = document.querySelector(`[data-conv-index="${index}"]`);
                if (selectedEl) {
                    selectedEl.classList.add("bg-green-50", "border-l-4", "border-green-500");
                }

                // Atualizar título
                document.getElementById("backup-messages-title").innerHTML = `<i class="fas fa-envelope mr-2"></i>${escapeHtml(conv.contact_name || conv.phone)} - ${conv.messages_count} mensagens`;

                // Renderizar mensagens
                const container = document.getElementById("backup-messages-container");

                if (!conv.messages || conv.messages.length === 0) {
                    container.innerHTML = "<div class=\"text-center text-gray-500 py-8\">Nenhuma mensagem nesta conversa</div>";
                    return;
                }

                container.innerHTML = conv.messages.map(msg => {
                    const isMe = msg.from_me;
                    const alignClass = isMe ? "justify-end" : "justify-start";
                    const bgClass = isMe ? "bg-green-100" : "bg-white";
                    const textColor = isMe ? "text-green-800" : "text-gray-800";

                    let content = escapeHtml(msg.message_text || "");
                    if (msg.message_type !== "text" && msg.message_type) {
                        content = `<span class="text-gray-500 italic">[${msg.message_type}]</span> ${content}`;
                    }
                    if (msg.media_url && msg.media_url !== "[MEDIA_REMOVED]") {
                        content += `<br><a href="${msg.media_url}" target="_blank" class="text-blue-600 text-xs"><i class="fas fa-paperclip"></i> Ver mídia</a>`;
                    }

                    return `
                    <div class="flex ${alignClass} mb-2">
                        <div class="${bgClass} ${textColor} px-3 py-2 rounded-lg max-w-[80%] shadow-sm">
                            <div class="text-sm">${content || '<span class="text-gray-400 italic">Mensagem vazia</span>'}</div>
                            <div class="text-xs text-gray-400 mt-1">${formatDate(msg.created_at)}</div>
                        </div>
                    </div>
                `;
                }).join("");

                // Scroll para o topo
                container.scrollTop = 0;
            }

            function formatBytes(bytes) {
                if (!bytes) return "0 B";
                const k = 1024;
                const sizes = ["B", "KB", "MB", "GB"];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
            }

            // Atualizar UI quando destino muda
            document.getElementById("config-destination").addEventListener("change", function() {
                const dest = this.value;
                const ftpConfig = document.getElementById("ftp-config");
                const networkConfig = document.getElementById("network-config");
                const googleConfig = document.getElementById("google-drive-config");
                const onedriveConfig = document.getElementById("onedrive-config");
                const dropboxConfig = document.getElementById("dropbox-config");
                const s3Config = document.getElementById("s3-config");

                // Ocultar todas as configurações extras
                ftpConfig.classList.add("hidden");
                networkConfig.classList.add("hidden");
                googleConfig.classList.add("hidden");
                onedriveConfig.classList.add("hidden");
                dropboxConfig.classList.add("hidden");
                s3Config.classList.add("hidden");

                // Mostrar configuração específica do destino
                if (dest === "ftp") {
                    ftpConfig.classList.remove("hidden");
                } else if (dest === "network") {
                    networkConfig.classList.remove("hidden");
                } else if (dest === "google_drive") {
                    googleConfig.classList.remove("hidden");
                    // Verificar se já está conectado
                    if (backupConfig && backupConfig.google_connected) {
                        document.getElementById("google-drive-status").classList.remove("hidden");
                    }
                } else if (dest === "onedrive") {
                    onedriveConfig.classList.remove("hidden");
                    if (backupConfig && backupConfig.onedrive_connected) {
                        document.getElementById("onedrive-status").classList.remove("hidden");
                    }
                } else if (dest === "dropbox") {
                    dropboxConfig.classList.remove("hidden");
                    if (backupConfig && backupConfig.dropbox_connected) {
                        document.getElementById("dropbox-status").classList.remove("hidden");
                    }
                } else if (dest === "s3") {
                    s3Config.classList.remove("hidden");
                }
            });

            // Função para testar conexão FTP
            async function testFtpConnection() {
                const host = document.getElementById("ftp-host").value;
                const port = document.getElementById("ftp-port").value;
                const user = document.getElementById("ftp-user").value;
                const pass = document.getElementById("ftp-pass").value;
                const useSsl = document.getElementById("ftp-ssl").checked;

                if (!host || !user || !pass) {
                    showError("Preencha todos os campos de conexão FTP");
                    return;
                }

                try {
                    const response = await fetch("/api/backup_test_ftp.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify({
                            host,
                            port,
                            user,
                            pass,
                            ssl: useSsl
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        showSuccess("Conexão FTP estabelecida com sucesso!");
                    } else {
                        showError("Falha na conexão: " + (data.error || "Erro desconhecido"));
                    }
                } catch (error) {
                    console.error("Erro:", error);
                    showError("Erro ao testar conexão FTP");
                }
            }

            // Verificar parâmetros da URL (callback OAuth)
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get("success") === "google_connected") {
                showSuccess("Google Drive conectado com sucesso!");
            }
            if (urlParams.get("error")) {
                showError("Erro: " + urlParams.get("error"));
            }

            // Mostrar/ocultar campo de senha de criptografia
            document.getElementById("backup-encrypt").addEventListener("change", function() {
                const passwordField = document.getElementById("backup-encrypt-password-field");
                if (this.checked) {
                    passwordField.classList.remove("hidden");
                } else {
                    passwordField.classList.add("hidden");
                }
            });

            // Desabilitar período de datas se backup incremental estiver marcado
            document.getElementById("backup-incremental").addEventListener("change", function() {
                const dateFrom = document.getElementById("backup-date-from");
                const dateTo = document.getElementById("backup-date-to");
                if (this.checked) {
                    dateFrom.disabled = true;
                    dateTo.disabled = true;
                    dateFrom.value = "";
                    dateTo.value = "";
                } else {
                    dateFrom.disabled = false;
                    dateTo.disabled = false;
                }
            });
        </script>
    <?php
        break;

    case 'profile':
        // Incluir conteúdo da página de perfil
        $profilePath = '../profile.php';
        if (file_exists($profilePath)) {
            // Capturar o conteúdo da página
            ob_start();
            include $profilePath;
            $content = ob_get_clean();
            
            // Remover header e footer
            $content = preg_replace('/<\?php.*?require_once.*?header_spa\.php.*?\?>/s', '', $content);
            $content = preg_replace('/<\?php.*?require_once.*?footer_spa\.php.*?\?>/s', '', $content);
            
            echo $content;
        } else {
            echo '<div class="p-6 text-center text-red-600">Erro: Arquivo profile.php não encontrado</div>';
        }
        break;

    default:
        http_response_code(404);
    ?>
        <div class="flex items-center justify-center py-12">
            <div class="text-center">
                <i class="fas fa-exclamation-triangle text-4xl text-yellow-500 mb-4"></i>
                <h3 class="text-lg font-medium text-gray-800 mb-2">Página não encontrada</h3>
                <p class="text-gray-600 mb-4">A página solicitada não existe.</p>
                <button onclick="loadPage('dashboard')" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                    Voltar ao Dashboard
                </button>
            </div>
        </div>
<?php
        break;
}
?>