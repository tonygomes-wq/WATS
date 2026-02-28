<?php
/**
 * Editor Visual de Fluxos - Builder tipo Typebot
 * Interface drag-and-drop para criação de chatbots
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$canManageFlows = isAdmin() || isSupervisor();
if (!$canManageFlows) {
    header('Location: dashboard.php');
    exit;
}

$userId = $_SESSION['user_id'];
$flowId = intval($_GET['id'] ?? 0);

if ($flowId <= 0) {
    header('Location: flows.php');
    exit;
}

// Buscar dados do fluxo
$stmt = $pdo->prepare("SELECT * FROM bot_flows WHERE id = ? AND user_id = ?");
$stmt->execute([$flowId, $userId]);
$flow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$flow) {
    header('Location: flows.php');
    exit;
}

$pageTitle = 'Editor: ' . htmlspecialchars($flow['name']);
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?php echo $_COOKIE['theme'] ?? 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - WATS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/flow-builder.css">
</head>
<body class="bg-gray-100 dark:bg-gray-900 overflow-hidden">
    <!-- Header do Builder -->
    <header class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 h-14 flex items-center justify-between px-4 fixed top-0 left-0 right-0 z-50">
        <div class="flex items-center gap-4">
            <a href="flows.php" class="text-gray-600 dark:text-gray-400 hover:text-green-600 transition">
                <i class="fas fa-arrow-left text-xl"></i>
            </a>
            <div>
                <input type="text" id="flowName" value="<?php echo htmlspecialchars($flow['name']); ?>" 
                       class="text-lg font-bold bg-transparent border-none focus:outline-none focus:ring-2 focus:ring-green-500 rounded px-2 py-1 text-gray-800 dark:text-white">
                <span class="flow-status-badge <?php echo $flow['status']; ?>" id="flowStatus">
                    <?php echo $flow['status'] === 'published' ? 'Publicado' : ($flow['status'] === 'paused' ? 'Pausado' : 'Rascunho'); ?>
                </span>
            </div>
        </div>
        
        <div class="flex items-center gap-3">
            <button onclick="saveFlow()" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-lg text-gray-700 dark:text-gray-300 font-medium transition flex items-center gap-2">
                <i class="fas fa-save"></i>
                <span class="hidden sm:inline">Salvar</span>
            </button>
            <button onclick="publishFlow()" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition flex items-center gap-2">
                <i class="fas fa-rocket"></i>
                <span class="hidden sm:inline">Publicar</span>
            </button>
        </div>
    </header>
    
    <!-- Container Principal -->
    <div class="flex h-screen pt-14">
        <!-- Sidebar de Blocos - Estilo Typebot -->
        <aside class="w-72 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 overflow-y-auto flex-shrink-0">
            <!-- Bubbles (Mensagens) -->
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Bubbles</h3>
                <div class="grid grid-cols-2 gap-2">
                    <div class="node-template-mini" draggable="true" data-type="text">
                        <i class="fas fa-align-left text-blue-500"></i>
                        <span>Texto</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="image">
                        <i class="fas fa-image text-blue-500"></i>
                        <span>Imagem</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="video">
                        <i class="fas fa-video text-blue-500"></i>
                        <span>Vídeo</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="embed">
                        <i class="fas fa-code text-blue-500"></i>
                        <span>Embed</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="audio">
                        <i class="fas fa-volume-up text-blue-500"></i>
                        <span>Áudio</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="file">
                        <i class="fas fa-file text-blue-500"></i>
                        <span>Arquivo</span>
                    </div>
                </div>
            </div>
            
            <!-- Inputs (Entradas) -->
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Inputs</h3>
                <div class="grid grid-cols-2 gap-2">
                    <div class="node-template-mini" draggable="true" data-type="input_text">
                        <i class="fas fa-font text-orange-500"></i>
                        <span>Texto</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="input_number">
                        <i class="fas fa-hashtag text-orange-500"></i>
                        <span>Número</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="input_email">
                        <i class="fas fa-envelope text-orange-500"></i>
                        <span>Email</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="input_website">
                        <i class="fas fa-globe text-orange-500"></i>
                        <span>Website</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="input_date">
                        <i class="fas fa-calendar text-orange-500"></i>
                        <span>Data</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="input_phone">
                        <i class="fas fa-phone text-orange-500"></i>
                        <span>Telefone</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="buttons">
                        <i class="fas fa-hand-pointer text-orange-500"></i>
                        <span>Botões</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="picture_choice">
                        <i class="fas fa-images text-orange-500"></i>
                        <span>Escolha Img</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="rating">
                        <i class="fas fa-star text-orange-500"></i>
                        <span>Avaliação</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="file_upload">
                        <i class="fas fa-upload text-orange-500"></i>
                        <span>Upload</span>
                    </div>
                </div>
            </div>
            
            <!-- Logic (Lógica) -->
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Lógica</h3>
                <div class="grid grid-cols-2 gap-2">
                    <div class="node-template-mini" draggable="true" data-type="set_variable">
                        <i class="fas fa-pen text-purple-500"></i>
                        <span>Variável</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="condition">
                        <i class="fas fa-code-branch text-purple-500"></i>
                        <span>Condição</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="redirect">
                        <i class="fas fa-external-link-alt text-purple-500"></i>
                        <span>Redirecionar</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="code">
                        <i class="fas fa-code text-purple-500"></i>
                        <span>Código</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="typebot">
                        <i class="fas fa-robot text-purple-500"></i>
                        <span>Sub-fluxo</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="jump">
                        <i class="fas fa-arrow-right text-purple-500"></i>
                        <span>Pular para</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="wait">
                        <i class="fas fa-clock text-purple-500"></i>
                        <span>Aguardar</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="ab_test">
                        <i class="fas fa-random text-purple-500"></i>
                        <span>Teste A/B</span>
                    </div>
                </div>
            </div>
            
            <!-- Integrations (Integrações) -->
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Integrações</h3>
                <div class="grid grid-cols-2 gap-2">
                    <div class="node-template-mini" draggable="true" data-type="google_sheets">
                        <i class="fas fa-table text-green-500"></i>
                        <span>Sheets</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="google_analytics">
                        <i class="fas fa-chart-line text-yellow-500"></i>
                        <span>Analytics</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="webhook">
                        <i class="fas fa-plug text-gray-500"></i>
                        <span>Webhook</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="email_send">
                        <i class="fas fa-paper-plane text-blue-500"></i>
                        <span>Email</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="zapier">
                        <i class="fas fa-bolt text-orange-500"></i>
                        <span>Zapier</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="make">
                        <i class="fas fa-cogs text-purple-500"></i>
                        <span>Make.com</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="pabbly">
                        <i class="fas fa-link text-blue-500"></i>
                        <span>Pabbly</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="chatwoot">
                        <i class="fas fa-comments text-red-500"></i>
                        <span>Chatwoot</span>
                    </div>
                </div>
            </div>
            
            <!-- WhatsApp -->
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">WhatsApp</h3>
                <div class="grid grid-cols-2 gap-2">
                    <div class="node-template-mini" draggable="true" data-type="whatsapp_list">
                        <i class="fab fa-whatsapp text-green-500"></i>
                        <span>Lista</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="whatsapp_buttons">
                        <i class="fab fa-whatsapp text-green-500"></i>
                        <span>Botões WA</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="transfer">
                        <i class="fas fa-user-friends text-green-500"></i>
                        <span>Transferir</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="end_chat">
                        <i class="fas fa-times-circle text-red-500"></i>
                        <span>Encerrar</span>
                    </div>
                </div>
            </div>
            
            <!-- OpenAI / IA -->
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Inteligência Artificial</h3>
                <div class="grid grid-cols-2 gap-2">
                    <div class="node-template-mini" draggable="true" data-type="openai">
                        <i class="fas fa-brain text-teal-500"></i>
                        <span>OpenAI</span>
                    </div>
                    <div class="node-template-mini" draggable="true" data-type="ai_assistant">
                        <i class="fas fa-robot text-teal-500"></i>
                        <span>Assistente</span>
                    </div>
                </div>
            </div>
            
            <!-- Variáveis -->
            <div class="p-4">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Variáveis do Sistema</h3>
                <div class="flex flex-wrap gap-1">
                    <span class="variable-tag" onclick="copyVariable('{{nome}}')">{nome}</span>
                    <span class="variable-tag" onclick="copyVariable('{{telefone}}')">{telefone}</span>
                    <span class="variable-tag" onclick="copyVariable('{{email}}')">{email}</span>
                    <span class="variable-tag" onclick="copyVariable('{{input}}')">{input}</span>
                    <span class="variable-tag" onclick="copyVariable('{{data}}')">{data}</span>
                    <span class="variable-tag" onclick="copyVariable('{{hora}}')">{hora}</span>
                </div>
            </div>
        </aside>
        
        <!-- Canvas do Fluxo -->
        <main class="flex-1 relative overflow-hidden bg-gray-50 dark:bg-gray-900" id="canvasContainer">
            <div id="canvas" class="absolute inset-0">
                <!-- SVG para conexões -->
                <svg id="connectionsSvg" class="absolute inset-0 w-full h-full pointer-events-none"></svg>
                
                <!-- Nós serão renderizados aqui -->
                <div id="nodesContainer"></div>
            </div>
            
            <!-- Controles de Zoom -->
            <div class="absolute bottom-4 right-4 flex items-center gap-2 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-2">
                <button onclick="zoomOut()" class="w-8 h-8 flex items-center justify-center rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                    <i class="fas fa-minus text-gray-600 dark:text-gray-400"></i>
                </button>
                <span id="zoomLevel" class="text-sm text-gray-600 dark:text-gray-400 w-12 text-center">100%</span>
                <button onclick="zoomIn()" class="w-8 h-8 flex items-center justify-center rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                    <i class="fas fa-plus text-gray-600 dark:text-gray-400"></i>
                </button>
                <div class="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-1"></div>
                <button onclick="fitToScreen()" class="w-8 h-8 flex items-center justify-center rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition" title="Ajustar à tela">
                    <i class="fas fa-expand text-gray-600 dark:text-gray-400"></i>
                </button>
            </div>
            
            <!-- Indicador de salvamento -->
            <div id="saveIndicator" class="absolute top-4 right-4 hidden">
                <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                    <i class="fas fa-check mr-1"></i> Salvo
                </span>
            </div>
        </main>
        
        <!-- Painel de Propriedades -->
        <aside id="propertiesPanel" class="w-80 bg-white dark:bg-gray-800 border-l border-gray-200 dark:border-gray-700 overflow-y-auto flex-shrink-0 hidden">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800 dark:text-white">Propriedades</h3>
                <button onclick="closePropertiesPanel()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="propertiesContent" class="p-4">
                <!-- Conteúdo dinâmico -->
            </div>
        </aside>
    </div>
    
    <!-- Toast de notificação -->
    <div id="toast" class="fixed bottom-4 left-1/2 transform -translate-x-1/2 hidden">
        <div class="bg-gray-800 text-white px-4 py-2 rounded-lg shadow-lg flex items-center gap-2">
            <i class="fas fa-check-circle text-green-400"></i>
            <span id="toastMessage">Mensagem</span>
        </div>
    </div>

    <script>
    // Dados do fluxo
    const flowId = <?php echo $flowId; ?>;
    let flowData = {
        nodes: [],
        edges: []
    };
    
    // Estado do canvas
    let canvas = {
        zoom: 1,
        panX: 0,
        panY: 0,
        isDragging: false,
        dragNode: null,
        dragStartX: 0,
        dragStartY: 0,
        isConnecting: false,
        connectFromNode: null,
        selectedNode: null
    };
    
    // Inicialização
    document.addEventListener('DOMContentLoaded', async function() {
        await loadFlowData();
        initCanvas();
        initDragAndDrop();
        renderNodes();
        renderConnections();
    });
    
    // Carregar dados do fluxo
    async function loadFlowData() {
        try {
            const response = await fetch(`api/bot_flows.php?action=get&id=${flowId}`);
            const data = await response.json();
            
            if (data.success) {
                flowData.nodes = (data.nodes || []).map(n => ({
                    id: n.id,
                    type: n.type,
                    label: n.label,
                    config: n.config ? JSON.parse(n.config) : {},
                    x: parseInt(n.pos_x) || 100,
                    y: parseInt(n.pos_y) || 100
                }));
                
                flowData.edges = (data.edges || []).map(e => ({
                    id: e.id,
                    from: e.from_node_id,
                    to: e.to_node_id,
                    condition: e.condition || {}
                }));
                
                // Se não tem nós, criar nó inicial
                if (flowData.nodes.length === 0) {
                    flowData.nodes.push({
                        id: 'start_' + Date.now(),
                        type: 'start',
                        label: 'Início',
                        config: {},
                        x: 100,
                        y: 100
                    });
                }
            }
        } catch (error) {
            console.error('Erro ao carregar fluxo:', error);
        }
    }
    
    // Inicializar canvas
    function initCanvas() {
        const container = document.getElementById('canvasContainer');
        const canvasEl = document.getElementById('canvas');
        
        // Pan com mouse
        let isPanning = false;
        let startX, startY;
        
        container.addEventListener('mousedown', function(e) {
            if (e.target === container || e.target === canvasEl || e.target.id === 'nodesContainer') {
                isPanning = true;
                startX = e.clientX - canvas.panX;
                startY = e.clientY - canvas.panY;
                container.style.cursor = 'grabbing';
            }
        });
        
        container.addEventListener('mousemove', function(e) {
            if (isPanning) {
                canvas.panX = e.clientX - startX;
                canvas.panY = e.clientY - startY;
                updateCanvasTransform();
            }
        });
        
        container.addEventListener('mouseup', function() {
            isPanning = false;
            container.style.cursor = 'default';
        });
        
        container.addEventListener('mouseleave', function() {
            isPanning = false;
            container.style.cursor = 'default';
        });
        
        // Zoom com scroll
        container.addEventListener('wheel', function(e) {
            e.preventDefault();
            const delta = e.deltaY > 0 ? -0.1 : 0.1;
            const newZoom = Math.max(0.25, Math.min(2, canvas.zoom + delta));
            canvas.zoom = newZoom;
            updateCanvasTransform();
            document.getElementById('zoomLevel').textContent = Math.round(canvas.zoom * 100) + '%';
        });
    }
    
    function updateCanvasTransform() {
        const canvasEl = document.getElementById('canvas');
        canvasEl.style.transform = `translate(${canvas.panX}px, ${canvas.panY}px) scale(${canvas.zoom})`;
    }
    
    // Drag and drop de templates
    function initDragAndDrop() {
        const templates = document.querySelectorAll('.node-template, .node-template-mini');
        
        templates.forEach(template => {
            template.addEventListener('dragstart', function(e) {
                e.dataTransfer.setData('nodeType', this.dataset.type);
            });
        });
        
        const canvasContainer = document.getElementById('canvasContainer');
        
        canvasContainer.addEventListener('dragover', function(e) {
            e.preventDefault();
        });
        
        canvasContainer.addEventListener('drop', function(e) {
            e.preventDefault();
            const nodeType = e.dataTransfer.getData('nodeType');
            if (!nodeType) return;
            
            const rect = canvasContainer.getBoundingClientRect();
            const x = (e.clientX - rect.left - canvas.panX) / canvas.zoom;
            const y = (e.clientY - rect.top - canvas.panY) / canvas.zoom;
            
            addNode(nodeType, x, y);
        });
    }
    
    // Adicionar nó
    function addNode(type, x, y) {
        const nodeLabels = {
            // Bubbles
            'text': 'Texto',
            'image': 'Imagem',
            'video': 'Vídeo',
            'embed': 'Embed',
            'audio': 'Áudio',
            'file': 'Arquivo',
            // Inputs
            'input_text': 'Entrada Texto',
            'input_number': 'Entrada Número',
            'input_email': 'Entrada Email',
            'input_website': 'Entrada Website',
            'input_date': 'Entrada Data',
            'input_phone': 'Entrada Telefone',
            'buttons': 'Botões',
            'picture_choice': 'Escolha Imagem',
            'rating': 'Avaliação',
            'file_upload': 'Upload Arquivo',
            // Logic
            'set_variable': 'Definir Variável',
            'condition': 'Condição',
            'redirect': 'Redirecionar',
            'code': 'Código',
            'typebot': 'Sub-fluxo',
            'jump': 'Pular para',
            'wait': 'Aguardar',
            'ab_test': 'Teste A/B',
            // Integrations
            'google_sheets': 'Google Sheets',
            'google_analytics': 'Analytics',
            'webhook': 'Webhook',
            'email_send': 'Enviar Email',
            'zapier': 'Zapier',
            'make': 'Make.com',
            'pabbly': 'Pabbly',
            'chatwoot': 'Chatwoot',
            // WhatsApp
            'whatsapp_list': 'Lista WhatsApp',
            'whatsapp_buttons': 'Botões WhatsApp',
            'transfer': 'Transferir',
            'end_chat': 'Encerrar Chat',
            // AI
            'openai': 'OpenAI',
            'ai_assistant': 'Assistente IA',
            // Legacy
            'start': 'Início',
            'message': 'Mensagem',
            'input': 'Entrada',
            'delay': 'Delay',
            'api': 'API',
            'end': 'Fim'
        };
        
        const node = {
            id: type + '_' + Date.now(),
            type: type,
            label: nodeLabels[type] || type,
            config: getDefaultConfig(type),
            x: Math.round(x),
            y: Math.round(y)
        };
        
        flowData.nodes.push(node);
        renderNodes();
        selectNode(node.id);
    }
    
    function getDefaultConfig(type) {
        switch(type) {
            // Bubbles
            case 'text':
            case 'message':
                return { text: 'Digite sua mensagem aqui...' };
            case 'image':
                return { url: '', alt: '' };
            case 'video':
                return { url: '', autoplay: false };
            case 'audio':
                return { url: '' };
            case 'file':
                return { url: '', filename: '' };
            case 'embed':
                return { html: '' };
            // Inputs
            case 'input_text':
            case 'input':
                return { variable: 'input', placeholder: 'Digite aqui...', validation: 'text' };
            case 'input_number':
                return { variable: 'numero', placeholder: 'Digite um número', min: 0, max: 100 };
            case 'input_email':
                return { variable: 'email', placeholder: 'seu@email.com' };
            case 'input_website':
                return { variable: 'website', placeholder: 'https://' };
            case 'input_date':
                return { variable: 'data', format: 'DD/MM/YYYY' };
            case 'input_phone':
                return { variable: 'telefone', placeholder: '(00) 00000-0000' };
            case 'buttons':
                return { text: 'Escolha uma opção:', buttons: ['Opção 1', 'Opção 2', 'Opção 3'] };
            case 'picture_choice':
                return { text: 'Escolha uma imagem:', choices: [] };
            case 'rating':
                return { variable: 'avaliacao', max: 5, label: 'Como você avalia?' };
            case 'file_upload':
                return { variable: 'arquivo', accept: '*', maxSize: 10 };
            // Logic
            case 'set_variable':
                return { variable: '', value: '' };
            case 'condition':
                return { variable: 'input', operator: 'equals', value: '' };
            case 'redirect':
                return { url: '', newTab: true };
            case 'code':
                return { code: '// Seu código JavaScript aqui\nreturn result;' };
            case 'typebot':
                return { flowId: '' };
            case 'jump':
                return { targetGroup: '' };
            case 'wait':
            case 'delay':
                return { seconds: 3 };
            case 'ab_test':
                return { groups: [{ name: 'A', weight: 50 }, { name: 'B', weight: 50 }] };
            // Integrations
            case 'google_sheets':
                return { action: 'insert', spreadsheetId: '', sheetName: '', values: {} };
            case 'google_analytics':
                return { event: '', category: '', label: '' };
            case 'webhook':
            case 'api':
                return { url: '', method: 'POST', headers: {}, body: {} };
            case 'email_send':
                return { to: '', subject: '', body: '' };
            case 'zapier':
            case 'make':
            case 'pabbly':
                return { webhookUrl: '' };
            case 'chatwoot':
                return { action: 'create_contact' };
            // WhatsApp
            case 'whatsapp_list':
                return { title: 'Menu', buttonText: 'Ver opções', sections: [] };
            case 'whatsapp_buttons':
                return { text: 'Escolha:', buttons: ['Sim', 'Não'] };
            case 'transfer':
                return { department: '', message: 'Transferindo para um atendente...' };
            case 'end_chat':
                return { message: 'Obrigado pelo contato!' };
            // AI
            case 'openai':
                return { model: 'gpt-3.5-turbo', prompt: '', temperature: 0.7 };
            case 'ai_assistant':
                return { assistantId: '', instructions: '' };
            default:
                return {};
        }
    }
    
    // Renderizar nós
    function renderNodes() {
        const container = document.getElementById('nodesContainer');
        container.innerHTML = '';
        
        flowData.nodes.forEach(node => {
            const nodeEl = createNodeElement(node);
            container.appendChild(nodeEl);
        });
    }
    
    function createNodeElement(node) {
        const div = document.createElement('div');
        div.className = `flow-node flow-node-${node.type}`;
        div.id = `node_${node.id}`;
        div.style.left = node.x + 'px';
        div.style.top = node.y + 'px';
        
        const iconMap = {
            // Bubbles
            'text': 'fa-align-left',
            'image': 'fa-image',
            'video': 'fa-video',
            'embed': 'fa-code',
            'audio': 'fa-volume-up',
            'file': 'fa-file',
            // Inputs
            'input_text': 'fa-font',
            'input_number': 'fa-hashtag',
            'input_email': 'fa-envelope',
            'input_website': 'fa-globe',
            'input_date': 'fa-calendar',
            'input_phone': 'fa-phone',
            'buttons': 'fa-hand-pointer',
            'picture_choice': 'fa-images',
            'rating': 'fa-star',
            'file_upload': 'fa-upload',
            // Logic
            'set_variable': 'fa-pen',
            'condition': 'fa-code-branch',
            'redirect': 'fa-external-link-alt',
            'code': 'fa-code',
            'typebot': 'fa-robot',
            'jump': 'fa-arrow-right',
            'wait': 'fa-clock',
            'ab_test': 'fa-random',
            // Integrations
            'google_sheets': 'fa-table',
            'google_analytics': 'fa-chart-line',
            'webhook': 'fa-plug',
            'email_send': 'fa-paper-plane',
            'zapier': 'fa-bolt',
            'make': 'fa-cogs',
            'pabbly': 'fa-link',
            'chatwoot': 'fa-comments',
            // WhatsApp
            'whatsapp_list': 'fa-list',
            'whatsapp_buttons': 'fa-th-list',
            'transfer': 'fa-user-friends',
            'end_chat': 'fa-times-circle',
            // AI
            'openai': 'fa-brain',
            'ai_assistant': 'fa-robot',
            // Legacy
            'start': 'fa-play',
            'message': 'fa-comment',
            'input': 'fa-keyboard',
            'delay': 'fa-clock',
            'api': 'fa-plug',
            'end': 'fa-stop'
        };
        
        div.innerHTML = `
            <div class="node-header">
                <i class="fas ${iconMap[node.type] || 'fa-circle'}"></i>
                <span>${escapeHtml(node.label)}</span>
            </div>
            <div class="node-body">
                ${getNodePreview(node)}
            </div>
            <div class="node-connectors">
                ${node.type !== 'end' ? '<div class="connector connector-out" data-node="' + node.id + '"></div>' : ''}
                ${node.type !== 'start' ? '<div class="connector connector-in" data-node="' + node.id + '"></div>' : ''}
            </div>
        `;
        
        // Eventos
        div.addEventListener('mousedown', function(e) {
            if (e.target.classList.contains('connector')) return;
            startDragNode(node.id, e);
        });
        
        div.addEventListener('click', function(e) {
            if (!canvas.isDragging) {
                selectNode(node.id);
            }
        });
        
        div.addEventListener('dblclick', function() {
            openNodeEditor(node.id);
        });
        
        // Conectores
        const connectorOut = div.querySelector('.connector-out');
        if (connectorOut) {
            connectorOut.addEventListener('mousedown', function(e) {
                e.stopPropagation();
                startConnection(node.id);
            });
        }
        
        const connectorIn = div.querySelector('.connector-in');
        if (connectorIn) {
            connectorIn.addEventListener('mouseup', function(e) {
                if (canvas.isConnecting) {
                    endConnection(node.id);
                }
            });
        }
        
        return div;
    }
    
    function getNodePreview(node) {
        switch(node.type) {
            case 'message':
                return `<p class="text-xs text-gray-500 truncate">${escapeHtml(node.config.text || '')}</p>`;
            case 'input':
                return `<p class="text-xs text-gray-500">Salvar em: {${node.config.variable || 'input'}}</p>`;
            case 'buttons':
                const btns = node.config.buttons || [];
                return `<p class="text-xs text-gray-500">${btns.length} opções</p>`;
            case 'delay':
                return `<p class="text-xs text-gray-500">${node.config.seconds || 0}s</p>`;
            case 'condition':
                return `<p class="text-xs text-gray-500">Se {${node.config.variable || '?'}}</p>`;
            default:
                return '';
        }
    }
    
    // Drag de nós
    function startDragNode(nodeId, e) {
        canvas.isDragging = true;
        canvas.dragNode = nodeId;
        canvas.dragStartX = e.clientX;
        canvas.dragStartY = e.clientY;
        
        const node = flowData.nodes.find(n => n.id === nodeId);
        if (node) {
            canvas.nodeStartX = node.x;
            canvas.nodeStartY = node.y;
        }
        
        document.addEventListener('mousemove', dragNode);
        document.addEventListener('mouseup', stopDragNode);
    }
    
    function dragNode(e) {
        if (!canvas.isDragging || !canvas.dragNode) return;
        
        const dx = (e.clientX - canvas.dragStartX) / canvas.zoom;
        const dy = (e.clientY - canvas.dragStartY) / canvas.zoom;
        
        const node = flowData.nodes.find(n => n.id === canvas.dragNode);
        if (node) {
            node.x = canvas.nodeStartX + dx;
            node.y = canvas.nodeStartY + dy;
            
            const nodeEl = document.getElementById(`node_${node.id}`);
            if (nodeEl) {
                nodeEl.style.left = node.x + 'px';
                nodeEl.style.top = node.y + 'px';
            }
            
            renderConnections();
        }
    }
    
    function stopDragNode() {
        canvas.isDragging = false;
        canvas.dragNode = null;
        document.removeEventListener('mousemove', dragNode);
        document.removeEventListener('mouseup', stopDragNode);
    }
    
    // Conexões
    function startConnection(fromNodeId) {
        canvas.isConnecting = true;
        canvas.connectFromNode = fromNodeId;
        document.body.style.cursor = 'crosshair';
        
        document.addEventListener('mouseup', cancelConnection);
    }
    
    function endConnection(toNodeId) {
        if (!canvas.isConnecting || !canvas.connectFromNode) return;
        if (canvas.connectFromNode === toNodeId) return;
        
        // Verificar se conexão já existe
        const exists = flowData.edges.some(e => 
            e.from === canvas.connectFromNode && e.to === toNodeId
        );
        
        if (!exists) {
            flowData.edges.push({
                id: 'edge_' + Date.now(),
                from: canvas.connectFromNode,
                to: toNodeId,
                condition: {}
            });
            renderConnections();
        }
        
        canvas.isConnecting = false;
        canvas.connectFromNode = null;
        document.body.style.cursor = 'default';
        document.removeEventListener('mouseup', cancelConnection);
    }
    
    function cancelConnection(e) {
        if (!e.target.classList.contains('connector-in')) {
            canvas.isConnecting = false;
            canvas.connectFromNode = null;
            document.body.style.cursor = 'default';
        }
        document.removeEventListener('mouseup', cancelConnection);
    }
    
    // Renderizar conexões
    function renderConnections() {
        const svg = document.getElementById('connectionsSvg');
        svg.innerHTML = '';
        
        flowData.edges.forEach(edge => {
            const fromNode = flowData.nodes.find(n => n.id === edge.from || n.id == edge.from);
            const toNode = flowData.nodes.find(n => n.id === edge.to || n.id == edge.to);
            
            if (!fromNode || !toNode) return;
            
            const fromX = fromNode.x + 120; // Largura do nó / 2
            const fromY = fromNode.y + 60;  // Altura do nó
            const toX = toNode.x + 120;
            const toY = toNode.y;
            
            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            const midY = (fromY + toY) / 2;
            
            path.setAttribute('d', `M ${fromX} ${fromY} C ${fromX} ${midY}, ${toX} ${midY}, ${toX} ${toY}`);
            path.setAttribute('stroke', '#10B981');
            path.setAttribute('stroke-width', '2');
            path.setAttribute('fill', 'none');
            path.setAttribute('class', 'connection-line');
            
            // Seta
            const arrow = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
            arrow.setAttribute('points', `${toX},${toY} ${toX-6},${toY-10} ${toX+6},${toY-10}`);
            arrow.setAttribute('fill', '#10B981');
            
            svg.appendChild(path);
            svg.appendChild(arrow);
        });
    }
    
    // Selecionar nó
    function selectNode(nodeId) {
        // Remover seleção anterior
        document.querySelectorAll('.flow-node.selected').forEach(el => {
            el.classList.remove('selected');
        });
        
        canvas.selectedNode = nodeId;
        const nodeEl = document.getElementById(`node_${nodeId}`);
        if (nodeEl) {
            nodeEl.classList.add('selected');
        }
        
        showPropertiesPanel(nodeId);
    }
    
    // Painel de propriedades
    function showPropertiesPanel(nodeId) {
        const node = flowData.nodes.find(n => n.id === nodeId);
        if (!node) return;
        
        const panel = document.getElementById('propertiesPanel');
        const content = document.getElementById('propertiesContent');
        
        content.innerHTML = getPropertiesForm(node);
        panel.classList.remove('hidden');
        
        // Bind eventos do form
        const form = content.querySelector('form');
        if (form) {
            form.addEventListener('input', function() {
                updateNodeFromForm(nodeId);
            });
        }
    }
    
    function getPropertiesForm(node) {
        let html = `<form class="space-y-4">`;
        
        // Label
        html += `
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nome</label>
                <input type="text" name="label" value="${escapeHtml(node.label)}" 
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white">
            </div>
        `;
        
        // Campos específicos por tipo
        switch(node.type) {
            case 'message':
                html += `
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Mensagem</label>
                        <textarea name="config.text" rows="4" 
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white">${escapeHtml(node.config.text || '')}</textarea>
                    </div>
                `;
                break;
                
            case 'input':
                html += `
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Salvar em variável</label>
                        <input type="text" name="config.variable" value="${escapeHtml(node.config.variable || 'input')}" 
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Validação</label>
                        <select name="config.validation" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white">
                            <option value="text" ${node.config.validation === 'text' ? 'selected' : ''}>Texto</option>
                            <option value="email" ${node.config.validation === 'email' ? 'selected' : ''}>E-mail</option>
                            <option value="phone" ${node.config.validation === 'phone' ? 'selected' : ''}>Telefone</option>
                            <option value="number" ${node.config.validation === 'number' ? 'selected' : ''}>Número</option>
                        </select>
                    </div>
                `;
                break;
                
            case 'buttons':
                html += `
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Texto</label>
                        <input type="text" name="config.text" value="${escapeHtml(node.config.text || '')}" 
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Botões (um por linha)</label>
                        <textarea name="config.buttons" rows="4" 
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white">${(node.config.buttons || []).join('\n')}</textarea>
                    </div>
                `;
                break;
                
            case 'delay':
                html += `
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Segundos</label>
                        <input type="number" name="config.seconds" value="${node.config.seconds || 3}" min="1" max="300"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white">
                    </div>
                `;
                break;
                
            case 'condition':
                html += `
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Variável</label>
                        <input type="text" name="config.variable" value="${escapeHtml(node.config.variable || '')}" 
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Operador</label>
                        <select name="config.operator" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white">
                            <option value="equals" ${node.config.operator === 'equals' ? 'selected' : ''}>Igual a</option>
                            <option value="contains" ${node.config.operator === 'contains' ? 'selected' : ''}>Contém</option>
                            <option value="starts" ${node.config.operator === 'starts' ? 'selected' : ''}>Começa com</option>
                            <option value="ends" ${node.config.operator === 'ends' ? 'selected' : ''}>Termina com</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Valor</label>
                        <input type="text" name="config.value" value="${escapeHtml(node.config.value || '')}" 
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white">
                    </div>
                `;
                break;
                
            case 'transfer':
                html += `
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Mensagem</label>
                        <textarea name="config.message" rows="2" 
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white">${escapeHtml(node.config.message || '')}</textarea>
                    </div>
                `;
                break;
        }
        
        // Botão excluir
        if (node.type !== 'start') {
            html += `
                <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button type="button" onclick="deleteNode('${node.id}')" 
                            class="w-full px-4 py-2 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg text-sm font-medium transition">
                        <i class="fas fa-trash mr-2"></i> Excluir Bloco
                    </button>
                </div>
            `;
        }
        
        html += `</form>`;
        return html;
    }
    
    function updateNodeFromForm(nodeId) {
        const node = flowData.nodes.find(n => n.id === nodeId);
        if (!node) return;
        
        const form = document.querySelector('#propertiesContent form');
        if (!form) return;
        
        const formData = new FormData(form);
        
        node.label = formData.get('label') || node.label;
        
        // Atualizar config
        for (const [key, value] of formData.entries()) {
            if (key.startsWith('config.')) {
                const configKey = key.replace('config.', '');
                if (configKey === 'buttons') {
                    node.config[configKey] = value.split('\n').filter(b => b.trim());
                } else {
                    node.config[configKey] = value;
                }
            }
        }
        
        // Atualizar visual
        const nodeEl = document.getElementById(`node_${nodeId}`);
        if (nodeEl) {
            const header = nodeEl.querySelector('.node-header span');
            if (header) header.textContent = node.label;
            
            const body = nodeEl.querySelector('.node-body');
            if (body) body.innerHTML = getNodePreview(node);
        }
    }
    
    function closePropertiesPanel() {
        document.getElementById('propertiesPanel').classList.add('hidden');
        canvas.selectedNode = null;
        document.querySelectorAll('.flow-node.selected').forEach(el => {
            el.classList.remove('selected');
        });
    }
    
    function deleteNode(nodeId) {
        if (!confirm('Excluir este bloco?')) return;
        
        // Remover nó
        flowData.nodes = flowData.nodes.filter(n => n.id !== nodeId);
        
        // Remover conexões
        flowData.edges = flowData.edges.filter(e => e.from !== nodeId && e.to !== nodeId);
        
        closePropertiesPanel();
        renderNodes();
        renderConnections();
    }
    
    // Zoom
    function zoomIn() {
        canvas.zoom = Math.min(2, canvas.zoom + 0.1);
        updateCanvasTransform();
        document.getElementById('zoomLevel').textContent = Math.round(canvas.zoom * 100) + '%';
    }
    
    function zoomOut() {
        canvas.zoom = Math.max(0.25, canvas.zoom - 0.1);
        updateCanvasTransform();
        document.getElementById('zoomLevel').textContent = Math.round(canvas.zoom * 100) + '%';
    }
    
    function fitToScreen() {
        canvas.zoom = 1;
        canvas.panX = 0;
        canvas.panY = 0;
        updateCanvasTransform();
        document.getElementById('zoomLevel').textContent = '100%';
    }
    
    // Salvar fluxo
    async function saveFlow() {
        try {
            const name = document.getElementById('flowName').value.trim();
            
            // Preparar dados
            const nodes = flowData.nodes.map((n, idx) => ({
                id: n.id,
                type: n.type,
                label: n.label,
                config: n.config,
                x: Math.round(n.x),
                y: Math.round(n.y)
            }));
            
            const edges = flowData.edges.map(e => ({
                from: e.from,
                to: e.to,
                condition: e.condition
            }));
            
            const response = await fetch('api/bot_flows.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'save_layout',
                    id: flowId,
                    nodes: nodes,
                    edges: edges
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Atualizar IDs dos nós com os novos IDs do banco
                if (data.id_map) {
                    flowData.nodes.forEach(node => {
                        if (data.id_map[node.id]) {
                            node.id = data.id_map[node.id];
                        }
                    });
                }
                
                showToast('Fluxo salvo com sucesso!');
            } else {
                alert('Erro ao salvar: ' + (data.message || 'Erro desconhecido'));
            }
        } catch (error) {
            console.error('Erro:', error);
            alert('Erro ao salvar fluxo');
        }
    }
    
    // Publicar fluxo
    async function publishFlow() {
        await saveFlow();
        
        try {
            const response = await fetch('api/bot_flows.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'publish',
                    id: flowId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('flowStatus').textContent = 'Publicado';
                document.getElementById('flowStatus').className = 'flow-status-badge published';
                showToast('Fluxo publicado! Versão ' + data.version);
            } else {
                alert('Erro ao publicar: ' + (data.message || 'Erro desconhecido'));
            }
        } catch (error) {
            console.error('Erro:', error);
            alert('Erro ao publicar fluxo');
        }
    }
    
    // Toast
    function showToast(message) {
        const toast = document.getElementById('toast');
        document.getElementById('toastMessage').textContent = message;
        toast.classList.remove('hidden');
        
        setTimeout(() => {
            toast.classList.add('hidden');
        }, 3000);
    }
    
    // Copiar variável
    function copyVariable(variable) {
        navigator.clipboard.writeText(variable);
        showToast('Variável copiada!');
    }
    
    // Escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Atalhos de teclado
    document.addEventListener('keydown', function(e) {
        // Ctrl+S = Salvar
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            saveFlow();
        }
        
        // Delete = Excluir nó selecionado
        if (e.key === 'Delete' && canvas.selectedNode) {
            const node = flowData.nodes.find(n => n.id === canvas.selectedNode);
            if (node && node.type !== 'start') {
                deleteNode(canvas.selectedNode);
            }
        }
        
        // Escape = Fechar painel
        if (e.key === 'Escape') {
            closePropertiesPanel();
        }
    });
    </script>
</body>
</html>
