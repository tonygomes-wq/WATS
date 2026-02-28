<?php
/**
 * Flow Builder V2 - Editor Visual Avançado Estilo Typebot
 * WATS - Sistema de Automação WhatsApp
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$canManageFlows = isAdmin();
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

$stmt = $pdo->prepare("SELECT * FROM bot_flows WHERE id = ? AND user_id = ?");
$stmt->execute([$flowId, $userId]);
$flow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$flow) {
    header('Location: flows.php');
    exit;
}

$flowType = $flow['flow_type'] ?? 'conversational';
$isAutomation = ($flowType === 'automation');

// Se for automation, carregar dados do automation_flows
$automationFlow = null;
if ($isAutomation) {
    $stmtAuto = $pdo->prepare("SELECT * FROM automation_flows WHERE bot_flow_id = ? AND user_id = ?");
    $stmtAuto->execute([$flowId, $userId]);
    $automationFlow = $stmtAuto->fetch(PDO::FETCH_ASSOC);
}

$pageTitle = htmlspecialchars($flow['name']);
$theme = $_COOKIE['theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Flow Builder</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/flow-builder-v2.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Header -->
    <header class="flow-header">
        <div class="flow-header-left">
            <a href="flows.php" class="zoom-btn" title="Voltar">
                <i class="fas fa-arrow-left"></i>
            </a>
            <input type="text" id="flowName" value="<?php echo $pageTitle; ?>" class="flow-name-input" placeholder="Nome do fluxo">
            <span class="flow-status-badge <?php echo $flow['status']; ?>" id="flowStatus">
                <?php echo $flow['status'] === 'published' ? 'Publicado' : ($flow['status'] === 'paused' ? 'Pausado' : 'Rascunho'); ?>
            </span>
        </div>
        
        <div class="flow-header-center">
            <div class="flow-tabs">
                <button class="flow-tab active" data-tab="flow">Flow</button>
                <button class="flow-tab" data-tab="theme">Tema</button>
                <button class="flow-tab" data-tab="settings">Config</button>
            </div>
        </div>
        
        <div class="flow-header-right">
            <button class="btn-preview" onclick="previewFlow()">
                <i class="fas fa-eye"></i>
                Preview
            </button>
            <button class="btn-publish" onclick="publishFlow()">
                <i class="fas fa-rocket"></i>
                Publicar
            </button>
        </div>
    </header>
    
    <!-- Container Principal -->
    <div class="flow-builder-container">
        <?php if ($isAutomation): ?>
            <!-- Formulário de Automação -->
            <?php include 'includes/automation_flow_form.php'; ?>
        <?php else: ?>
        <!-- Sidebar de Blocos -->
        <aside class="flow-sidebar" id="sidebar">
            <!-- Bubbles -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">Bubbles</div>
                <div class="sidebar-blocks-grid">
                    <div class="block-item bubble" draggable="true" data-type="text" data-category="bubble">
                        <i class="fas fa-align-left"></i>
                        <span>Texto</span>
                    </div>
                    <div class="block-item bubble" draggable="true" data-type="image" data-category="bubble">
                        <i class="fas fa-image"></i>
                        <span>Imagem</span>
                    </div>
                    <div class="block-item bubble" draggable="true" data-type="video" data-category="bubble">
                        <i class="fas fa-video"></i>
                        <span>Vídeo</span>
                    </div>
                    <div class="block-item bubble" draggable="true" data-type="audio" data-category="bubble">
                        <i class="fas fa-volume-up"></i>
                        <span>Áudio</span>
                    </div>
                    <div class="block-item bubble" draggable="true" data-type="embed" data-category="bubble">
                        <i class="fas fa-code"></i>
                        <span>Embed</span>
                    </div>
                    <div class="block-item bubble" draggable="true" data-type="file" data-category="bubble">
                        <i class="fas fa-file"></i>
                        <span>Arquivo</span>
                    </div>
                </div>
            </div>
            
            <!-- Inputs -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">Inputs</div>
                <div class="sidebar-blocks-grid">
                    <div class="block-item input" draggable="true" data-type="input_text" data-category="input">
                        <i class="fas fa-font"></i>
                        <span>Texto</span>
                    </div>
                    <div class="block-item input" draggable="true" data-type="input_number" data-category="input">
                        <i class="fas fa-hashtag"></i>
                        <span>Número</span>
                    </div>
                    <div class="block-item input" draggable="true" data-type="input_email" data-category="input">
                        <i class="fas fa-envelope"></i>
                        <span>Email</span>
                    </div>
                    <div class="block-item input" draggable="true" data-type="input_phone" data-category="input">
                        <i class="fas fa-phone"></i>
                        <span>Telefone</span>
                    </div>
                    <div class="block-item input" draggable="true" data-type="input_date" data-category="input">
                        <i class="fas fa-calendar"></i>
                        <span>Data</span>
                    </div>
                    <div class="block-item input" draggable="true" data-type="buttons" data-category="input">
                        <i class="fas fa-hand-pointer"></i>
                        <span>Botões</span>
                    </div>
                    <div class="block-item input" draggable="true" data-type="rating" data-category="input">
                        <i class="fas fa-star"></i>
                        <span>Avaliação</span>
                    </div>
                    <div class="block-item input" draggable="true" data-type="file_upload" data-category="input">
                        <i class="fas fa-upload"></i>
                        <span>Upload</span>
                    </div>
                </div>
            </div>
            
            <!-- Logic -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">Lógica</div>
                <div class="sidebar-blocks-grid">
                    <div class="block-item logic" draggable="true" data-type="set_variable" data-category="logic">
                        <i class="fas fa-pen"></i>
                        <span>Variável</span>
                    </div>
                    <div class="block-item logic" draggable="true" data-type="condition" data-category="logic">
                        <i class="fas fa-code-branch"></i>
                        <span>Condição</span>
                    </div>
                    <div class="block-item logic" draggable="true" data-type="redirect" data-category="logic">
                        <i class="fas fa-external-link-alt"></i>
                        <span>Redirecionar</span>
                    </div>
                    <div class="block-item logic" draggable="true" data-type="code" data-category="logic">
                        <i class="fas fa-code"></i>
                        <span>Código</span>
                    </div>
                    <div class="block-item logic" draggable="true" data-type="wait" data-category="logic">
                        <i class="fas fa-clock"></i>
                        <span>Aguardar</span>
                    </div>
                    <div class="block-item logic" draggable="true" data-type="jump" data-category="logic">
                        <i class="fas fa-arrow-right"></i>
                        <span>Pular para</span>
                    </div>
                    <div class="block-item logic" draggable="true" data-type="ab_test" data-category="logic">
                        <i class="fas fa-random"></i>
                        <span>Teste A/B</span>
                    </div>
                    <div class="block-item logic" draggable="true" data-type="typebot" data-category="logic">
                        <i class="fas fa-robot"></i>
                        <span>Sub-fluxo</span>
                    </div>
                </div>
            </div>
            
            <!-- Integrations -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">Integrações</div>
                <div class="sidebar-blocks-grid">
                    <div class="block-item integration" draggable="true" data-type="webhook" data-category="integration">
                        <i class="fas fa-plug"></i>
                        <span>Webhook</span>
                    </div>
                    <div class="block-item integration" draggable="true" data-type="google_sheets" data-category="integration">
                        <i class="fas fa-table"></i>
                        <span>Sheets</span>
                    </div>
                    <div class="block-item integration" draggable="true" data-type="email_send" data-category="integration">
                        <i class="fas fa-paper-plane"></i>
                        <span>Email</span>
                    </div>
                    <div class="block-item integration" draggable="true" data-type="openai" data-category="integration">
                        <i class="fas fa-brain"></i>
                        <span>OpenAI</span>
                    </div>
                </div>
            </div>
            
            <!-- WhatsApp -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">WhatsApp</div>
                <div class="sidebar-blocks-grid">
                    <div class="block-item whatsapp" draggable="true" data-type="whatsapp_buttons" data-category="whatsapp">
                        <i class="fab fa-whatsapp"></i>
                        <span>Botões</span>
                    </div>
                    <div class="block-item whatsapp" draggable="true" data-type="whatsapp_list" data-category="whatsapp">
                        <i class="fas fa-list"></i>
                        <span>Lista</span>
                    </div>
                    <div class="block-item whatsapp" draggable="true" data-type="transfer" data-category="whatsapp">
                        <i class="fas fa-user-friends"></i>
                        <span>Transferir</span>
                    </div>
                    <div class="block-item whatsapp" draggable="true" data-type="end_chat" data-category="whatsapp">
                        <i class="fas fa-times-circle"></i>
                        <span>Encerrar</span>
                    </div>
                </div>
            </div>
            
            <!-- Variáveis -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">Variáveis</div>
                <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                    <span class="variable-tag" onclick="copyVariable('{{nome}}')">{nome}</span>
                    <span class="variable-tag" onclick="copyVariable('{{telefone}}')">{telefone}</span>
                    <span class="variable-tag" onclick="copyVariable('{{email}}')">{email}</span>
                    <span class="variable-tag" onclick="copyVariable('{{input}}')">{input}</span>
                    <span class="variable-tag" onclick="copyVariable('{{data}}')">{data}</span>
                </div>
            </div>
        </aside>
        
        <!-- Canvas -->
        <main class="flow-canvas" id="canvasContainer">
            <div class="canvas-inner" id="canvas">
                <svg id="connectionsSvg" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 1;"></svg>
                <div id="nodesContainer" style="position: relative; z-index: 2;"></div>
            </div>
            
            <!-- Zoom Controls -->
            <div class="zoom-controls">
                <button class="zoom-btn" onclick="zoomOut()" title="Diminuir zoom">
                    <i class="fas fa-minus"></i>
                </button>
                <span class="zoom-level" id="zoomLevel">100%</span>
                <button class="zoom-btn" onclick="zoomIn()" title="Aumentar zoom">
                    <i class="fas fa-plus"></i>
                </button>
                <button class="zoom-btn" onclick="fitToScreen()" title="Ajustar à tela">
                    <i class="fas fa-expand"></i>
                </button>
            </div>
        </main>
        
        <!-- Painel de Propriedades -->
        <aside class="properties-panel" id="propertiesPanel" style="display: none;">
            <div class="properties-header">
                <span class="properties-title" id="propertiesTitle">Propriedades</span>
                <div class="properties-close" onclick="closePropertiesPanel()">
                    <i class="fas fa-times"></i>
                </div>
            </div>
            <div class="properties-content" id="propertiesContent">
            </div>
        </aside>
        <?php endif; ?>
    </div>
    
    <!-- Toast -->
    <div id="toast" class="toast" style="display: none;">
        <i class="fas fa-check-circle"></i>
        <span id="toastMessage">Mensagem</span>
    </div>
    
    <!-- Modal de Preview -->
    <div id="previewModal" class="preview-modal" style="display: none;">
        <div class="preview-container">
            <div class="preview-header">
                <div class="preview-header-left">
                    <div class="preview-avatar">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                    <div class="preview-info">
                        <span class="preview-name">Preview do Fluxo</span>
                        <span class="preview-status">Simulação</span>
                    </div>
                </div>
                <div class="preview-header-right">
                    <button class="preview-btn" onclick="restartPreview()" title="Reiniciar">
                        <i class="fas fa-redo"></i>
                    </button>
                    <button class="preview-btn" onclick="closePreview()" title="Fechar">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="preview-messages" id="previewMessages">
                <!-- Mensagens serão inseridas aqui -->
            </div>
            <div class="preview-input-area" id="previewInputArea" style="display: none;">
                <input type="text" class="preview-input" id="previewInput" placeholder="Digite sua resposta..." onkeypress="if(event.key==='Enter')sendPreviewMessage()">
                <button class="preview-send" onclick="sendPreviewMessage()">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
            <div class="preview-buttons-area" id="previewButtonsArea" style="display: none;">
                <!-- Botões de escolha serão inseridos aqui -->
            </div>
        </div>
    </div>

<script>
<?php if (!$isAutomation): ?>
// ===== DADOS DO FLUXO =====
const flowId = <?php echo $flowId; ?>;
let flowData = { nodes: [], edges: [] };
let history = [];
let historyIndex = -1;

// ===== ESTADO DO CANVAS =====
let canvas = {
    zoom: 1,
    panX: 50,
    panY: 80,
    isDragging: false,
    isPanning: false,
    dragNode: null,
    isConnecting: false,
    connectFromNode: null,
    selectedNode: null
};

// ===== CONFIGURAÇÃO DOS BLOCOS =====
const blockConfig = {
    // Bubbles
    text: { label: 'Texto', icon: 'fa-align-left', category: 'bubble', config: { text: 'Olá! Como posso ajudar?' } },
    image: { label: 'Imagem', icon: 'fa-image', category: 'bubble', config: { url: '', alt: '' } },
    video: { label: 'Vídeo', icon: 'fa-video', category: 'bubble', config: { url: '' } },
    audio: { label: 'Áudio', icon: 'fa-volume-up', category: 'bubble', config: { url: '' } },
    embed: { label: 'Embed', icon: 'fa-code', category: 'bubble', config: { html: '' } },
    file: { label: 'Arquivo', icon: 'fa-file', category: 'bubble', config: { url: '', filename: '' } },
    
    // Inputs
    input_text: { label: 'Entrada Texto', icon: 'fa-font', category: 'input', config: { variable: 'resposta', placeholder: 'Digite aqui...' } },
    input_number: { label: 'Entrada Número', icon: 'fa-hashtag', category: 'input', config: { variable: 'numero', min: 0, max: 100 } },
    input_email: { label: 'Entrada Email', icon: 'fa-envelope', category: 'input', config: { variable: 'email', placeholder: 'seu@email.com' } },
    input_phone: { label: 'Entrada Telefone', icon: 'fa-phone', category: 'input', config: { variable: 'telefone', placeholder: '(00) 00000-0000' } },
    input_date: { label: 'Entrada Data', icon: 'fa-calendar', category: 'input', config: { variable: 'data' } },
    buttons: { label: 'Botões', icon: 'fa-hand-pointer', category: 'input', config: { text: 'Escolha uma opção:', buttons: ['Opção 1', 'Opção 2'] } },
    rating: { label: 'Avaliação', icon: 'fa-star', category: 'input', config: { variable: 'avaliacao', max: 5 } },
    file_upload: { label: 'Upload', icon: 'fa-upload', category: 'input', config: { variable: 'arquivo' } },
    
    // Logic
    set_variable: { label: 'Definir Variável', icon: 'fa-pen', category: 'logic', config: { variable: '', value: '' } },
    condition: { label: 'Condição', icon: 'fa-code-branch', category: 'logic', config: { variable: '', operator: 'equals', value: '' } },
    redirect: { label: 'Redirecionar', icon: 'fa-external-link-alt', category: 'logic', config: { url: '' } },
    code: { label: 'Código', icon: 'fa-code', category: 'logic', config: { code: '' } },
    wait: { label: 'Aguardar', icon: 'fa-clock', category: 'logic', config: { seconds: 3 } },
    jump: { label: 'Pular para', icon: 'fa-arrow-right', category: 'logic', config: { targetGroup: '' } },
    ab_test: { label: 'Teste A/B', icon: 'fa-random', category: 'logic', config: { groups: [] } },
    typebot: { label: 'Sub-fluxo', icon: 'fa-robot', category: 'logic', config: { flowId: '' } },
    
    // Integrations
    webhook: { label: 'Webhook', icon: 'fa-plug', category: 'integration', config: { url: '', method: 'POST' } },
    google_sheets: { label: 'Google Sheets', icon: 'fa-table', category: 'integration', config: { spreadsheetId: '' } },
    email_send: { label: 'Enviar Email', icon: 'fa-paper-plane', category: 'integration', config: { to: '', subject: '', body: '' } },
    openai: { label: 'OpenAI', icon: 'fa-brain', category: 'integration', config: { model: 'gpt-3.5-turbo', prompt: '' } },
    
    // WhatsApp
    whatsapp_buttons: { label: 'Botões WA', icon: 'fa-hand-pointer', category: 'whatsapp', config: { text: '', buttons: [] } },
    whatsapp_list: { label: 'Lista WA', icon: 'fa-list', category: 'whatsapp', config: { title: '', sections: [] } },
    transfer: { label: 'Transferir', icon: 'fa-user-friends', category: 'whatsapp', config: { message: 'Transferindo...' } },
    end_chat: { label: 'Encerrar', icon: 'fa-times-circle', category: 'whatsapp', config: { message: 'Obrigado!' } },
    
    // Special
    start: { label: 'Início', icon: 'fa-play', category: 'start', config: {} },
    end: { label: 'Fim', icon: 'fa-stop', category: 'end', config: {} }
};

// ===== INICIALIZAÇÃO =====
document.addEventListener('DOMContentLoaded', async function() {
    await loadFlowData();
    initCanvas();
    initDragAndDrop();
    initKeyboardShortcuts();
    renderAll();
});

// ===== CARREGAR DADOS =====
async function loadFlowData() {
    try {
        const response = await fetch(`api/bot_flows.php?action=get&id=${flowId}`);
        const data = await response.json();
        
        if (data.success) {
            flowData.nodes = (data.nodes || []).map(n => ({
                id: n.id,
                type: n.type,
                label: n.label || blockConfig[n.type]?.label || n.type,
                config: n.config ? (typeof n.config === 'string' ? JSON.parse(n.config) : n.config) : {},
                x: parseInt(n.pos_x) || 100,
                y: parseInt(n.pos_y) || 100
            }));
            
            flowData.edges = (data.edges || []).map(e => ({
                id: e.id,
                from: e.from_node_id,
                to: e.to_node_id
            }));
            
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
            
            saveToHistory();
        }
    } catch (error) {
        console.error('Erro ao carregar fluxo:', error);
    }
}

// ===== CANVAS =====
function initCanvas() {
    const container = document.getElementById('canvasContainer');
    let startX, startY;
    
    container.addEventListener('mousedown', function(e) {
        if (e.target === container || e.target.id === 'canvas' || e.target.id === 'nodesContainer') {
            canvas.isPanning = true;
            startX = e.clientX - canvas.panX;
            startY = e.clientY - canvas.panY;
            container.style.cursor = 'grabbing';
            
            // Deselecionar nó
            if (canvas.selectedNode) {
                deselectNode();
            }
        }
    });
    
    document.addEventListener('mousemove', function(e) {
        if (canvas.isPanning) {
            canvas.panX = e.clientX - startX;
            canvas.panY = e.clientY - startY;
            updateCanvasTransform();
        }
    });
    
    document.addEventListener('mouseup', function() {
        if (canvas.isPanning) {
            canvas.isPanning = false;
            container.style.cursor = 'default';
        }
    });
    
    container.addEventListener('wheel', function(e) {
        e.preventDefault();
        const delta = e.deltaY > 0 ? -0.1 : 0.1;
        canvas.zoom = Math.max(0.25, Math.min(2, canvas.zoom + delta));
        updateCanvasTransform();
        document.getElementById('zoomLevel').textContent = Math.round(canvas.zoom * 100) + '%';
    });
}

function updateCanvasTransform() {
    const canvasEl = document.getElementById('canvas');
    canvasEl.style.transform = `translate(${canvas.panX}px, ${canvas.panY}px) scale(${canvas.zoom})`;
}

// ===== DRAG AND DROP =====
function initDragAndDrop() {
    const blocks = document.querySelectorAll('.block-item');
    const container = document.getElementById('canvasContainer');
    
    blocks.forEach(block => {
        block.addEventListener('dragstart', function(e) {
            e.dataTransfer.setData('nodeType', this.dataset.type);
            e.dataTransfer.setData('category', this.dataset.category);
        });
    });
    
    container.addEventListener('dragover', e => e.preventDefault());
    
    container.addEventListener('drop', function(e) {
        e.preventDefault();
        const nodeType = e.dataTransfer.getData('nodeType');
        if (!nodeType) return;
        
        const rect = container.getBoundingClientRect();
        const x = (e.clientX - rect.left - canvas.panX) / canvas.zoom;
        const y = (e.clientY - rect.top - canvas.panY) / canvas.zoom;
        
        addNode(nodeType, x, y);
    });
}

// ===== ADICIONAR NÓ =====
function addNode(type, x, y) {
    const config = blockConfig[type] || { label: type, icon: 'fa-circle', category: 'other', config: {} };
    
    const node = {
        id: type + '_' + Date.now(),
        type: type,
        label: config.label,
        config: { ...config.config },
        x: Math.round(x),
        y: Math.round(y)
    };
    
    flowData.nodes.push(node);
    saveToHistory();
    renderAll();
    selectNode(node.id);
}

// ===== RENDERIZAR =====
function renderAll() {
    renderNodes();
    renderConnections();
}

function renderNodes() {
    const container = document.getElementById('nodesContainer');
    container.innerHTML = '';
    
    flowData.nodes.forEach(node => {
        const el = createNodeElement(node);
        container.appendChild(el);
    });
}

function createNodeElement(node) {
    const config = blockConfig[node.type] || { icon: 'fa-circle', category: 'other' };
    const category = config.category;
    
    const div = document.createElement('div');
    div.className = `flow-node ${category}`;
    div.id = `node_${node.id}`;
    div.style.left = node.x + 'px';
    div.style.top = node.y + 'px';
    
    if (canvas.selectedNode === node.id) {
        div.classList.add('selected');
    }
    
    div.innerHTML = `
        <div class="node-header">
            <div class="node-header-icon">
                <i class="fas ${config.icon}"></i>
            </div>
            <span class="node-header-title">${escapeHtml(node.label)}</span>
            <div class="node-header-menu" onclick="event.stopPropagation(); showNodeMenu('${node.id}')">
                <i class="fas fa-ellipsis-h"></i>
            </div>
        </div>
        <div class="node-body">
            ${getNodeBodyContent(node)}
        </div>
        ${node.type !== 'end' && node.type !== 'end_chat' ? `<div class="node-connector out" data-node="${node.id}"></div>` : ''}
        ${node.type !== 'start' ? `<div class="node-connector in" data-node="${node.id}"></div>` : ''}
    `;
    
    // Eventos de drag do nó
    div.addEventListener('mousedown', function(e) {
        if (e.target.classList.contains('node-connector')) return;
        if (e.target.closest('.node-header-menu')) return;
        
        canvas.isDragging = true;
        canvas.dragNode = node.id;
        canvas.dragStartX = e.clientX;
        canvas.dragStartY = e.clientY;
        canvas.nodeStartX = node.x;
        canvas.nodeStartY = node.y;
        
        div.classList.add('dragging');
        
        const onMouseMove = (e) => {
            if (!canvas.isDragging) return;
            const dx = (e.clientX - canvas.dragStartX) / canvas.zoom;
            const dy = (e.clientY - canvas.dragStartY) / canvas.zoom;
            node.x = canvas.nodeStartX + dx;
            node.y = canvas.nodeStartY + dy;
            div.style.left = node.x + 'px';
            div.style.top = node.y + 'px';
            renderConnections();
        };
        
        const onMouseUp = () => {
            canvas.isDragging = false;
            canvas.dragNode = null;
            div.classList.remove('dragging');
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
            saveToHistory();
        };
        
        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
    });
    
    div.addEventListener('click', function(e) {
        if (!canvas.isDragging) {
            selectNode(node.id);
        }
    });
    
    // Conectores - Sistema de conexão melhorado
    const connectorOut = div.querySelector('.node-connector.out');
    if (connectorOut) {
        connectorOut.addEventListener('mousedown', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Iniciando conexão do nó:', node.id);
            canvas.isConnecting = true;
            canvas.connectFromNode = node.id;
            document.body.style.cursor = 'crosshair';
            
            // Destacar conectores de entrada disponíveis
            document.querySelectorAll('.node-connector.in').forEach(conn => {
                if (conn.dataset.node !== node.id) {
                    conn.classList.add('available');
                }
            });
            
            // Criar linha de conexão temporária
            createTempConnectionLine(node);
        });
    }
    
    const connectorIn = div.querySelector('.node-connector.in');
    if (connectorIn) {
        connectorIn.addEventListener('mouseenter', function(e) {
            console.log('Mouse entrou no conector de entrada do nó:', node.id, 'isConnecting:', canvas.isConnecting);
            if (canvas.isConnecting && canvas.connectFromNode !== node.id) {
                this.classList.add('connector-hover');
            }
        });
        
        connectorIn.addEventListener('mouseleave', function(e) {
            this.classList.remove('connector-hover');
        });
        
        connectorIn.addEventListener('mouseup', function(e) {
            console.log('Mouse up no conector de entrada do nó:', node.id, 'isConnecting:', canvas.isConnecting, 'connectFromNode:', canvas.connectFromNode);
            e.stopPropagation();
            if (canvas.isConnecting && canvas.connectFromNode && canvas.connectFromNode !== node.id) {
                console.log('Criando conexão de', canvas.connectFromNode, 'para', node.id);
                addConnection(canvas.connectFromNode, node.id);
            }
            finishConnection();
        });
    }
    
    return div;
}

// Criar linha temporária durante conexão
function createTempConnectionLine(fromNode) {
    const svg = document.getElementById('connectionsSvg');
    
    // Remover linha temporária anterior se existir
    const oldTemp = document.getElementById('tempConnection');
    if (oldTemp) oldTemp.remove();
    
    const tempLine = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    tempLine.setAttribute('id', 'tempConnection');
    tempLine.setAttribute('class', 'connection-line temp');
    tempLine.setAttribute('stroke', '#FF8B1A');
    tempLine.setAttribute('stroke-width', '2');
    tempLine.setAttribute('stroke-dasharray', '5,5');
    tempLine.setAttribute('fill', 'none');
    svg.appendChild(tempLine);
    
    // Obter altura real do nó de origem
    const fromEl = document.getElementById(`node_${fromNode.id}`);
    const fromHeight = fromEl ? fromEl.offsetHeight : 100;
    
    // Atualizar linha conforme mouse move
    const updateTempLine = (e) => {
        if (!canvas.isConnecting) return;
        
        const container = document.getElementById('canvasContainer');
        const rect = container.getBoundingClientRect();
        const mouseX = (e.clientX - rect.left - canvas.panX) / canvas.zoom;
        const mouseY = (e.clientY - rect.top - canvas.panY) / canvas.zoom;
        
        // Conector de saída está na parte inferior central
        const fromX = fromNode.x + 140;
        const fromY = fromNode.y + fromHeight;
        
        const midY = (fromY + mouseY) / 2;
        tempLine.setAttribute('d', `M ${fromX} ${fromY} C ${fromX} ${midY}, ${mouseX} ${midY}, ${mouseX} ${mouseY}`);
    };
    
    document.addEventListener('mousemove', updateTempLine);
    
    // Limpar ao terminar
    const cleanup = () => {
        document.removeEventListener('mousemove', updateTempLine);
        const temp = document.getElementById('tempConnection');
        if (temp) temp.remove();
    };
    
    document.addEventListener('mouseup', cleanup, { once: true });
}

function finishConnection() {
    console.log('Finalizando conexão');
    canvas.isConnecting = false;
    canvas.connectFromNode = null;
    document.body.style.cursor = 'default';
    
    // Remover destaque dos conectores
    document.querySelectorAll('.node-connector.in.available').forEach(conn => {
        conn.classList.remove('available');
    });
    document.querySelectorAll('.node-connector.in.connector-hover').forEach(conn => {
        conn.classList.remove('connector-hover');
    });
    
    const temp = document.getElementById('tempConnection');
    if (temp) temp.remove();
}

function getNodeBodyContent(node) {
    const config = node.config || {};
    
    switch(node.type) {
        case 'text':
            return `<div class="node-body-text">${escapeHtml(config.text || 'Clique para editar...')}</div>`;
        case 'buttons':
            const buttons = config.buttons || [];
            return `
                <div class="node-body-text">${escapeHtml(config.text || '')}</div>
                <div class="node-buttons">
                    ${buttons.map(b => `<div class="node-button-item"><i class="fas fa-hand-pointer"></i> ${escapeHtml(b)}</div>`).join('')}
                </div>
            `;
        case 'input_text':
        case 'input_email':
        case 'input_phone':
        case 'input_number':
        case 'input_date':
            return `<div class="node-body-item"><i class="fas fa-save"></i> Salvar em: <strong>{${config.variable || 'input'}}</strong></div>`;
        case 'condition':
            return `<div class="node-body-item"><i class="fas fa-question"></i> Se {${config.variable || '?'}} ${config.operator || '='} "${config.value || ''}"</div>`;
        case 'wait':
            return `<div class="node-body-item"><i class="fas fa-hourglass-half"></i> Aguardar ${config.seconds || 0} segundos</div>`;
        case 'webhook':
            return `<div class="node-body-item"><i class="fas fa-globe"></i> ${config.method || 'POST'}: ${config.url || 'URL não definida'}</div>`;
        case 'transfer':
            return `<div class="node-body-text">${escapeHtml(config.message || 'Transferindo...')}</div>`;
        case 'start':
            return `<div class="node-body-text" style="text-align: center; color: var(--fb-green);">Início do fluxo</div>`;
        case 'end_chat':
            return `<div class="node-body-text">${escapeHtml(config.message || 'Fim da conversa')}</div>`;
        default:
            return `<div class="node-body-text" style="color: var(--fb-text-muted);">Clique para configurar</div>`;
    }
}

// ===== CONEXÕES =====
function addConnection(fromId, toId) {
    // Verificar se já existe usando comparação flexível
    const exists = flowData.edges.some(e => String(e.from) === String(fromId) && String(e.to) === String(toId));
    if (exists) {
        console.log('Conexão já existe:', fromId, '->', toId);
        return;
    }
    
    flowData.edges.push({
        id: 'edge_' + Date.now(),
        from: fromId,
        to: toId
    });
    
    console.log('Conexão criada:', fromId, '->', toId);
    saveToHistory();
    renderConnections();
    showToast('Conexão criada!');
}

function renderConnections() {
    const svg = document.getElementById('connectionsSvg');
    // Preservar linha temporária se existir
    const tempLine = document.getElementById('tempConnection');
    svg.innerHTML = '';
    if (tempLine) svg.appendChild(tempLine);
    
    flowData.edges.forEach(edge => {
        const fromNode = flowData.nodes.find(n => String(n.id) === String(edge.from));
        const toNode = flowData.nodes.find(n => String(n.id) === String(edge.to));
        if (!fromNode || !toNode) return;
        
        // Obter elementos DOM para calcular altura real
        const fromEl = document.getElementById(`node_${fromNode.id}`);
        const toEl = document.getElementById(`node_${toNode.id}`);
        
        const fromHeight = fromEl ? fromEl.offsetHeight : 100;
        const toHeight = toEl ? toEl.offsetHeight : 100;
        
        // Conector de saída está na parte inferior central do nó
        const fromX = fromNode.x + 140; // Centro horizontal (280px / 2)
        const fromY = fromNode.y + fromHeight; // Parte inferior
        
        // Conector de entrada está na parte superior central do nó
        const toX = toNode.x + 140;
        const toY = toNode.y; // Parte superior
        
        const midY = (fromY + toY) / 2;
        
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', `M ${fromX} ${fromY} C ${fromX} ${midY}, ${toX} ${midY}, ${toX} ${toY}`);
        path.setAttribute('class', 'connection-line');
        
        const arrow = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
        arrow.setAttribute('points', `${toX},${toY} ${toX-6},${toY-10} ${toX+6},${toY-10}`);
        arrow.setAttribute('class', 'connection-arrow');
        
        svg.appendChild(path);
        svg.appendChild(arrow);
    });
}

// ===== SELEÇÃO =====
function selectNode(nodeId) {
    deselectNode();
    canvas.selectedNode = nodeId;
    
    const el = document.getElementById(`node_${nodeId}`);
    if (el) el.classList.add('selected');
    
    showPropertiesPanel(nodeId);
}

function deselectNode() {
    if (canvas.selectedNode) {
        const el = document.getElementById(`node_${canvas.selectedNode}`);
        if (el) el.classList.remove('selected');
    }
    canvas.selectedNode = null;
    closePropertiesPanel();
}

// ===== PAINEL DE PROPRIEDADES =====
function showPropertiesPanel(nodeId) {
    // Buscar nó com comparação flexível (string ou número)
    const node = flowData.nodes.find(n => String(n.id) === String(nodeId));
    if (!node) {
        console.error('Nó não encontrado:', nodeId, 'Nós disponíveis:', flowData.nodes.map(n => n.id));
        return;
    }
    
    console.log('Abrindo painel para nó:', node);
    
    const panel = document.getElementById('propertiesPanel');
    const title = document.getElementById('propertiesTitle');
    const content = document.getElementById('propertiesContent');
    
    title.textContent = node.label;
    
    try {
        const formHtml = generatePropertiesForm(node);
        console.log('HTML do formulário gerado, tamanho:', formHtml.length);
        content.innerHTML = formHtml;
    } catch (error) {
        console.error('Erro ao gerar formulário:', error);
        content.innerHTML = '<div class="property-hint" style="color: red;">Erro ao carregar propriedades</div>';
    }
    
    panel.style.display = 'block';
    
    // Bind eventos para inputs, textareas e selects
    content.querySelectorAll('input, textarea, select').forEach(input => {
        input.addEventListener('input', () => updateNodeFromForm(nodeId));
        input.addEventListener('change', () => updateNodeFromForm(nodeId));
    });
}

function generatePropertiesForm(node) {
    let html = '';
    const config = node.config || {};
    
    console.log('Gerando formulário para nó:', node.id, 'tipo:', node.type, 'config:', config);
    
    // Nome do bloco
    html += `
        <div class="property-group">
            <label class="property-label">Nome do bloco</label>
            <input type="text" class="property-input" name="label" value="${escapeHtml(node.label)}">
        </div>
    `;
    
    // Campos específicos por tipo
    switch(node.type) {
        // ===== BUBBLES =====
        case 'text':
            html += `
                <div class="property-group">
                    <label class="property-label">Mensagem</label>
                    <textarea class="property-input property-textarea" name="config.text" placeholder="Digite sua mensagem aqui... Use {{variavel}} para inserir variáveis">${escapeHtml(config.text || '')}</textarea>
                </div>
                <div class="property-hint">
                    <i class="fas fa-lightbulb"></i> Use {{nome}}, {{telefone}} para variáveis
                </div>
            `;
            break;
            
        case 'image':
            html += `
                <div class="property-group">
                    <label class="property-label">URL da Imagem</label>
                    <input type="text" class="property-input" name="config.url" value="${escapeHtml(config.url || '')}" placeholder="https://exemplo.com/imagem.jpg">
                </div>
                <div class="property-group">
                    <label class="property-label">Texto alternativo (alt)</label>
                    <input type="text" class="property-input" name="config.alt" value="${escapeHtml(config.alt || '')}" placeholder="Descrição da imagem">
                </div>
                <div class="property-group">
                    <label class="property-label">Link ao clicar (opcional)</label>
                    <input type="text" class="property-input" name="config.clickLink" value="${escapeHtml(config.clickLink || '')}" placeholder="https://...">
                </div>
            `;
            break;
            
        case 'video':
            html += `
                <div class="property-group">
                    <label class="property-label">URL do Vídeo</label>
                    <input type="text" class="property-input" name="config.url" value="${escapeHtml(config.url || '')}" placeholder="https://youtube.com/watch?v=... ou URL direta">
                </div>
                <div class="property-group">
                    <label class="property-label">Tipo</label>
                    <select class="property-input property-select" name="config.type">
                        <option value="url" ${config.type === 'url' ? 'selected' : ''}>URL Direta</option>
                        <option value="youtube" ${config.type === 'youtube' ? 'selected' : ''}>YouTube</option>
                        <option value="vimeo" ${config.type === 'vimeo' ? 'selected' : ''}>Vimeo</option>
                    </select>
                </div>
                <div class="property-group">
                    <label class="property-label">
                        <input type="checkbox" name="config.autoplay" ${config.autoplay ? 'checked' : ''}> Reproduzir automaticamente
                    </label>
                </div>
            `;
            break;
            
        case 'audio':
            html += `
                <div class="property-group">
                    <label class="property-label">URL do Áudio</label>
                    <input type="text" class="property-input" name="config.url" value="${escapeHtml(config.url || '')}" placeholder="https://exemplo.com/audio.mp3">
                </div>
                <div class="property-group">
                    <label class="property-label">
                        <input type="checkbox" name="config.autoplay" ${config.autoplay ? 'checked' : ''}> Reproduzir automaticamente
                    </label>
                </div>
            `;
            break;
            
        case 'embed':
            html += `
                <div class="property-group">
                    <label class="property-label">Código HTML/Embed</label>
                    <textarea class="property-input property-textarea" name="config.html" placeholder="<iframe src='...'></iframe>">${escapeHtml(config.html || '')}</textarea>
                </div>
                <div class="property-group">
                    <label class="property-label">Altura (px)</label>
                    <input type="number" class="property-input" name="config.height" value="${config.height || 400}" min="100" max="1000">
                </div>
            `;
            break;
            
        case 'file':
            html += `
                <div class="property-group">
                    <label class="property-label">URL do Arquivo</label>
                    <input type="text" class="property-input" name="config.url" value="${escapeHtml(config.url || '')}" placeholder="https://exemplo.com/documento.pdf">
                </div>
                <div class="property-group">
                    <label class="property-label">Nome do arquivo</label>
                    <input type="text" class="property-input" name="config.filename" value="${escapeHtml(config.filename || '')}" placeholder="documento.pdf">
                </div>
            `;
            break;
            
        // ===== INPUTS =====
        case 'input_text':
            html += `
                <div class="property-group">
                    <label class="property-label">Salvar resposta em variável</label>
                    <input type="text" class="property-input" name="config.variable" value="${escapeHtml(config.variable || 'resposta')}" placeholder="nome_variavel">
                </div>
                <div class="property-group">
                    <label class="property-label">Placeholder</label>
                    <input type="text" class="property-input" name="config.placeholder" value="${escapeHtml(config.placeholder || '')}" placeholder="Digite aqui...">
                </div>
                <div class="property-group">
                    <label class="property-label">Texto do botão</label>
                    <input type="text" class="property-input" name="config.buttonLabel" value="${escapeHtml(config.buttonLabel || 'Enviar')}">
                </div>
                <div class="property-group">
                    <label class="property-label">
                        <input type="checkbox" name="config.isLong" ${config.isLong ? 'checked' : ''}> Campo de texto longo (textarea)
                    </label>
                </div>
            `;
            break;
            
        case 'input_number':
            html += `
                <div class="property-group">
                    <label class="property-label">Salvar resposta em variável</label>
                    <input type="text" class="property-input" name="config.variable" value="${escapeHtml(config.variable || 'numero')}" placeholder="nome_variavel">
                </div>
                <div class="property-group">
                    <label class="property-label">Placeholder</label>
                    <input type="text" class="property-input" name="config.placeholder" value="${escapeHtml(config.placeholder || '')}" placeholder="Digite um número...">
                </div>
                <div class="property-group">
                    <label class="property-label">Valor mínimo</label>
                    <input type="number" class="property-input" name="config.min" value="${config.min || ''}">
                </div>
                <div class="property-group">
                    <label class="property-label">Valor máximo</label>
                    <input type="number" class="property-input" name="config.max" value="${config.max || ''}">
                </div>
            `;
            break;
            
        case 'input_email':
            html += `
                <div class="property-group">
                    <label class="property-label">Salvar resposta em variável</label>
                    <input type="text" class="property-input" name="config.variable" value="${escapeHtml(config.variable || 'email')}" placeholder="nome_variavel">
                </div>
                <div class="property-group">
                    <label class="property-label">Placeholder</label>
                    <input type="text" class="property-input" name="config.placeholder" value="${escapeHtml(config.placeholder || 'seu@email.com')}">
                </div>
                <div class="property-group">
                    <label class="property-label">Mensagem de erro</label>
                    <input type="text" class="property-input" name="config.errorMessage" value="${escapeHtml(config.errorMessage || 'Por favor, digite um email válido')}">
                </div>
            `;
            break;
            
        case 'input_phone':
            html += `
                <div class="property-group">
                    <label class="property-label">Salvar resposta em variável</label>
                    <input type="text" class="property-input" name="config.variable" value="${escapeHtml(config.variable || 'telefone')}" placeholder="nome_variavel">
                </div>
                <div class="property-group">
                    <label class="property-label">Placeholder</label>
                    <input type="text" class="property-input" name="config.placeholder" value="${escapeHtml(config.placeholder || '(00) 00000-0000')}">
                </div>
                <div class="property-group">
                    <label class="property-label">País padrão</label>
                    <select class="property-input property-select" name="config.defaultCountry">
                        <option value="BR" ${config.defaultCountry === 'BR' ? 'selected' : ''}>Brasil (+55)</option>
                        <option value="US" ${config.defaultCountry === 'US' ? 'selected' : ''}>EUA (+1)</option>
                        <option value="PT" ${config.defaultCountry === 'PT' ? 'selected' : ''}>Portugal (+351)</option>
                    </select>
                </div>
            `;
            break;
            
        case 'input_date':
            html += `
                <div class="property-group">
                    <label class="property-label">Salvar resposta em variável</label>
                    <input type="text" class="property-input" name="config.variable" value="${escapeHtml(config.variable || 'data')}" placeholder="nome_variavel">
                </div>
                <div class="property-group">
                    <label class="property-label">Formato</label>
                    <select class="property-input property-select" name="config.format">
                        <option value="DD/MM/YYYY" ${config.format === 'DD/MM/YYYY' ? 'selected' : ''}>DD/MM/YYYY</option>
                        <option value="MM/DD/YYYY" ${config.format === 'MM/DD/YYYY' ? 'selected' : ''}>MM/DD/YYYY</option>
                        <option value="YYYY-MM-DD" ${config.format === 'YYYY-MM-DD' ? 'selected' : ''}>YYYY-MM-DD</option>
                    </select>
                </div>
                <div class="property-group">
                    <label class="property-label">
                        <input type="checkbox" name="config.hasTime" ${config.hasTime ? 'checked' : ''}> Incluir hora
                    </label>
                </div>
            `;
            break;
            
        case 'input_website':
            html += `
                <div class="property-group">
                    <label class="property-label">Salvar resposta em variável</label>
                    <input type="text" class="property-input" name="config.variable" value="${escapeHtml(config.variable || 'website')}" placeholder="nome_variavel">
                </div>
                <div class="property-group">
                    <label class="property-label">Placeholder</label>
                    <input type="text" class="property-input" name="config.placeholder" value="${escapeHtml(config.placeholder || 'https://...')}">
                </div>
            `;
            break;
            
        case 'buttons':
        case 'whatsapp_buttons':
            html += `
                <div class="property-group">
                    <label class="property-label">Texto da pergunta</label>
                    <textarea class="property-input property-textarea" name="config.text" placeholder="Escolha uma opção:">${escapeHtml(config.text || '')}</textarea>
                </div>
                <div class="property-group">
                    <label class="property-label">Botões (um por linha, máx 3 para WhatsApp)</label>
                    <textarea class="property-input property-textarea" name="config.buttons" placeholder="Opção 1&#10;Opção 2&#10;Opção 3">${(config.buttons || []).join('\n')}</textarea>
                </div>
                <div class="property-group">
                    <label class="property-label">Salvar escolha em variável</label>
                    <input type="text" class="property-input" name="config.variable" value="${escapeHtml(config.variable || 'escolha')}">
                </div>
                <div class="property-group">
                    <label class="property-label">
                        <input type="checkbox" name="config.isMultiple" ${config.isMultiple ? 'checked' : ''}> Permitir múltipla escolha
                    </label>
                </div>
            `;
            break;
            
        case 'whatsapp_list':
            html += `
                <div class="property-group">
                    <label class="property-label">Título do menu</label>
                    <input type="text" class="property-input" name="config.title" value="${escapeHtml(config.title || 'Menu')}" placeholder="Menu">
                </div>
                <div class="property-group">
                    <label class="property-label">Texto do botão</label>
                    <input type="text" class="property-input" name="config.buttonText" value="${escapeHtml(config.buttonText || 'Ver opções')}">
                </div>
                <div class="property-group">
                    <label class="property-label">Itens (um por linha)</label>
                    <textarea class="property-input property-textarea" name="config.items" placeholder="Item 1&#10;Item 2&#10;Item 3">${(config.items || []).join('\n')}</textarea>
                </div>
                <div class="property-group">
                    <label class="property-label">Salvar escolha em variável</label>
                    <input type="text" class="property-input" name="config.variable" value="${escapeHtml(config.variable || 'escolha_lista')}">
                </div>
            `;
            break;
            
        case 'rating':
            html += `
                <div class="property-group">
                    <label class="property-label">Pergunta</label>
                    <input type="text" class="property-input" name="config.label" value="${escapeHtml(config.label || 'Como você avalia nosso atendimento?')}">
                </div>
                <div class="property-group">
                    <label class="property-label">Salvar em variável</label>
                    <input type="text" class="property-input" name="config.variable" value="${escapeHtml(config.variable || 'avaliacao')}">
                </div>
                <div class="property-group">
                    <label class="property-label">Nota máxima</label>
                    <select class="property-input property-select" name="config.max">
                        <option value="5" ${config.max == 5 ? 'selected' : ''}>5 estrelas</option>
                        <option value="10" ${config.max == 10 ? 'selected' : ''}>10 pontos</option>
                    </select>
                </div>
                <div class="property-group">
                    <label class="property-label">Tipo</label>
                    <select class="property-input property-select" name="config.ratingType">
                        <option value="stars" ${config.ratingType === 'stars' ? 'selected' : ''}>Estrelas ⭐</option>
                        <option value="numbers" ${config.ratingType === 'numbers' ? 'selected' : ''}>Números</option>
                        <option value="thumbs" ${config.ratingType === 'thumbs' ? 'selected' : ''}>Polegar 👍👎</option>
                    </select>
                </div>
            `;
            break;
            
        case 'file_upload':
            html += `
                <div class="property-group">
                    <label class="property-label">Salvar em variável</label>
                    <input type="text" class="property-input" name="config.variable" value="${escapeHtml(config.variable || 'arquivo')}">
                </div>
                <div class="property-group">
                    <label class="property-label">Tipos aceitos</label>
                    <input type="text" class="property-input" name="config.accept" value="${escapeHtml(config.accept || '*')}" placeholder="image/*,application/pdf">
                </div>
                <div class="property-group">
                    <label class="property-label">Tamanho máximo (MB)</label>
                    <input type="number" class="property-input" name="config.maxSize" value="${config.maxSize || 10}" min="1" max="100">
                </div>
                <div class="property-group">
                    <label class="property-label">
                        <input type="checkbox" name="config.multiple" ${config.multiple ? 'checked' : ''}> Permitir múltiplos arquivos
                    </label>
                </div>
            `;
            break;
            
        case 'picture_choice':
            html += `
                <div class="property-group">
                    <label class="property-label">Pergunta</label>
                    <input type="text" class="property-input" name="config.text" value="${escapeHtml(config.text || 'Escolha uma imagem:')}">
                </div>
                <div class="property-group">
                    <label class="property-label">Salvar em variável</label>
                    <input type="text" class="property-input" name="config.variable" value="${escapeHtml(config.variable || 'escolha_imagem')}">
                </div>
                <div class="property-group">
                    <label class="property-label">Opções (URL|Título, uma por linha)</label>
                    <textarea class="property-input property-textarea" name="config.choices" placeholder="https://img1.jpg|Opção 1&#10;https://img2.jpg|Opção 2">${(config.choices || []).join('\n')}</textarea>
                </div>
            `;
            break;
            
        // ===== LÓGICA =====
        case 'set_variable':
            html += `
                <div class="property-group">
                    <label class="property-label">Nome da variável</label>
                    <input type="text" class="property-input" name="config.variable" value="${escapeHtml(config.variable || '')}" placeholder="minha_variavel">
                </div>
                <div class="property-group">
                    <label class="property-label">Tipo de valor</label>
                    <select class="property-input property-select" name="config.valueType" onchange="toggleValueType(this.value)">
                        <option value="custom" ${config.valueType === 'custom' || !config.valueType ? 'selected' : ''}>Valor personalizado</option>
                        <option value="now" ${config.valueType === 'now' ? 'selected' : ''}>Data/hora atual</option>
                        <option value="random" ${config.valueType === 'random' ? 'selected' : ''}>Número aleatório</option>
                        <option value="empty" ${config.valueType === 'empty' ? 'selected' : ''}>Vazio</option>
                    </select>
                </div>
                <div class="property-group" id="customValueGroup">
                    <label class="property-label">Valor</label>
                    <textarea class="property-input property-textarea" name="config.value" placeholder="Valor ou expressão JavaScript">${escapeHtml(config.value || '')}</textarea>
                </div>
                <div class="property-hint">
                    <i class="fas fa-code"></i> Use JavaScript: {{variavel}} + " texto"
                </div>
            `;
            break;
            
        case 'condition':
            html += `
                <div class="property-group">
                    <label class="property-label">Variável a comparar</label>
                    <input type="text" class="property-input" name="config.variable" value="${escapeHtml(config.variable || '')}" placeholder="nome_variavel">
                </div>
                <div class="property-group">
                    <label class="property-label">Operador</label>
                    <select class="property-input property-select" name="config.operator">
                        <option value="equals" ${config.operator === 'equals' ? 'selected' : ''}>Igual a</option>
                        <option value="not_equals" ${config.operator === 'not_equals' ? 'selected' : ''}>Diferente de</option>
                        <option value="contains" ${config.operator === 'contains' ? 'selected' : ''}>Contém</option>
                        <option value="not_contains" ${config.operator === 'not_contains' ? 'selected' : ''}>Não contém</option>
                        <option value="starts" ${config.operator === 'starts' ? 'selected' : ''}>Começa com</option>
                        <option value="ends" ${config.operator === 'ends' ? 'selected' : ''}>Termina com</option>
                        <option value="greater" ${config.operator === 'greater' ? 'selected' : ''}>Maior que</option>
                        <option value="less" ${config.operator === 'less' ? 'selected' : ''}>Menor que</option>
                        <option value="empty" ${config.operator === 'empty' ? 'selected' : ''}>Está vazio</option>
                        <option value="not_empty" ${config.operator === 'not_empty' ? 'selected' : ''}>Não está vazio</option>
                    </select>
                </div>
                <div class="property-group">
                    <label class="property-label">Valor para comparar</label>
                    <input type="text" class="property-input" name="config.value" value="${escapeHtml(config.value || '')}">
                </div>
                <div class="property-hint">
                    <i class="fas fa-info-circle"></i> Conecte saídas "Verdadeiro" e "Falso" a diferentes blocos
                </div>
            `;
            break;
            
        case 'redirect':
            html += `
                <div class="property-group">
                    <label class="property-label">URL de redirecionamento</label>
                    <input type="text" class="property-input" name="config.url" value="${escapeHtml(config.url || '')}" placeholder="https://...">
                </div>
                <div class="property-group">
                    <label class="property-label">
                        <input type="checkbox" name="config.newTab" ${config.newTab !== false ? 'checked' : ''}> Abrir em nova aba
                    </label>
                </div>
            `;
            break;
            
        case 'code':
            html += `
                <div class="property-group">
                    <label class="property-label">Código JavaScript</label>
                    <textarea class="property-input property-textarea" name="config.code" style="font-family: monospace; min-height: 150px;" placeholder="// Seu código aqui&#10;return resultado;">${escapeHtml(config.code || '')}</textarea>
                </div>
                <div class="property-group">
                    <label class="property-label">Salvar resultado em variável</label>
                    <input type="text" class="property-input" name="config.resultVariable" value="${escapeHtml(config.resultVariable || '')}">
                </div>
                <div class="property-hint">
                    <i class="fas fa-exclamation-triangle"></i> Cuidado: código é executado no servidor
                </div>
            `;
            break;
            
        case 'typebot':
            html += `
                <div class="property-group">
                    <label class="property-label">ID do Sub-fluxo</label>
                    <input type="text" class="property-input" name="config.flowId" value="${escapeHtml(config.flowId || '')}" placeholder="ID do fluxo">
                </div>
                <div class="property-hint">
                    <i class="fas fa-info-circle"></i> Execute outro fluxo e retorne ao atual
                </div>
            `;
            break;
            
        case 'jump':
            html += `
                <div class="property-group">
                    <label class="property-label">Pular para grupo</label>
                    <input type="text" class="property-input" name="config.targetGroup" value="${escapeHtml(config.targetGroup || '')}" placeholder="Nome do grupo">
                </div>
            `;
            break;
            
        case 'wait':
            html += `
                <div class="property-group">
                    <label class="property-label">Tempo de espera (segundos)</label>
                    <input type="number" class="property-input" name="config.seconds" value="${config.seconds || 3}" min="1" max="300">
                </div>
                <div class="property-group">
                    <label class="property-label">
                        <input type="checkbox" name="config.showTyping" ${config.showTyping !== false ? 'checked' : ''}> Mostrar "digitando..."
                    </label>
                </div>
            `;
            break;
            
        case 'ab_test':
            html += `
                <div class="property-group">
                    <label class="property-label">Grupo A - Porcentagem</label>
                    <input type="number" class="property-input" name="config.groupAPercent" value="${config.groupAPercent || 50}" min="0" max="100">
                </div>
                <div class="property-group">
                    <label class="property-label">Grupo B - Porcentagem</label>
                    <input type="number" class="property-input" name="config.groupBPercent" value="${config.groupBPercent || 50}" min="0" max="100">
                </div>
                <div class="property-hint">
                    <i class="fas fa-random"></i> Conecte saídas A e B a diferentes caminhos
                </div>
            `;
            break;
            
        // ===== INTEGRAÇÕES =====
        case 'webhook':
            html += `
                <div class="property-group">
                    <label class="property-label">URL do Webhook</label>
                    <input type="text" class="property-input" name="config.url" value="${escapeHtml(config.url || '')}" placeholder="https://api.exemplo.com/webhook">
                </div>
                <div class="property-group">
                    <label class="property-label">Método HTTP</label>
                    <select class="property-input property-select" name="config.method">
                        <option value="POST" ${config.method === 'POST' ? 'selected' : ''}>POST</option>
                        <option value="GET" ${config.method === 'GET' ? 'selected' : ''}>GET</option>
                        <option value="PUT" ${config.method === 'PUT' ? 'selected' : ''}>PUT</option>
                        <option value="PATCH" ${config.method === 'PATCH' ? 'selected' : ''}>PATCH</option>
                        <option value="DELETE" ${config.method === 'DELETE' ? 'selected' : ''}>DELETE</option>
                    </select>
                </div>
                <div class="property-group">
                    <label class="property-label">Headers (JSON)</label>
                    <textarea class="property-input property-textarea" name="config.headers" placeholder='{"Authorization": "Bearer token"}'>${escapeHtml(config.headers || '')}</textarea>
                </div>
                <div class="property-group">
                    <label class="property-label">Body (JSON)</label>
                    <textarea class="property-input property-textarea" name="config.body" placeholder='{"nome": "{{nome}}"}'>${escapeHtml(config.body || '')}</textarea>
                </div>
                <div class="property-group">
                    <label class="property-label">Salvar resposta em variável</label>
                    <input type="text" class="property-input" name="config.responseVariable" value="${escapeHtml(config.responseVariable || '')}">
                </div>
            `;
            break;
            
        case 'google_sheets':
            html += `
                <div class="property-group">
                    <label class="property-label">Ação</label>
                    <select class="property-input property-select" name="config.action">
                        <option value="insert" ${config.action === 'insert' ? 'selected' : ''}>Inserir linha</option>
                        <option value="update" ${config.action === 'update' ? 'selected' : ''}>Atualizar linha</option>
                        <option value="get" ${config.action === 'get' ? 'selected' : ''}>Buscar dados</option>
                    </select>
                </div>
                <div class="property-group">
                    <label class="property-label">ID da Planilha</label>
                    <input type="text" class="property-input" name="config.spreadsheetId" value="${escapeHtml(config.spreadsheetId || '')}" placeholder="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms">
                </div>
                <div class="property-group">
                    <label class="property-label">Nome da Aba</label>
                    <input type="text" class="property-input" name="config.sheetName" value="${escapeHtml(config.sheetName || 'Sheet1')}">
                </div>
                <div class="property-group">
                    <label class="property-label">Mapeamento de colunas (JSON)</label>
                    <textarea class="property-input property-textarea" name="config.mapping" placeholder='{"A": "{{nome}}", "B": "{{email}}"}'>${escapeHtml(config.mapping || '')}</textarea>
                </div>
            `;
            break;
            
        case 'google_analytics':
            html += `
                <div class="property-group">
                    <label class="property-label">Nome do Evento</label>
                    <input type="text" class="property-input" name="config.event" value="${escapeHtml(config.event || '')}" placeholder="form_submit">
                </div>
                <div class="property-group">
                    <label class="property-label">Categoria</label>
                    <input type="text" class="property-input" name="config.category" value="${escapeHtml(config.category || '')}">
                </div>
                <div class="property-group">
                    <label class="property-label">Label</label>
                    <input type="text" class="property-input" name="config.label" value="${escapeHtml(config.label || '')}">
                </div>
                <div class="property-group">
                    <label class="property-label">Valor</label>
                    <input type="number" class="property-input" name="config.value" value="${config.value || ''}">
                </div>
            `;
            break;
            
        case 'email_send':
            html += `
                <div class="property-group">
                    <label class="property-label">Para (email)</label>
                    <input type="text" class="property-input" name="config.to" value="${escapeHtml(config.to || '')}" placeholder="destino@email.com ou {{email}}">
                </div>
                <div class="property-group">
                    <label class="property-label">Assunto</label>
                    <input type="text" class="property-input" name="config.subject" value="${escapeHtml(config.subject || '')}">
                </div>
                <div class="property-group">
                    <label class="property-label">Corpo do email</label>
                    <textarea class="property-input property-textarea" name="config.body" placeholder="Olá {{nome}},&#10;&#10;Sua mensagem...">${escapeHtml(config.body || '')}</textarea>
                </div>
                <div class="property-group">
                    <label class="property-label">CC (opcional)</label>
                    <input type="text" class="property-input" name="config.cc" value="${escapeHtml(config.cc || '')}">
                </div>
            `;
            break;
            
        case 'zapier':
        case 'make':
        case 'pabbly':
            const integrationName = node.type === 'zapier' ? 'Zapier' : (node.type === 'make' ? 'Make.com' : 'Pabbly');
            html += `
                <div class="property-group">
                    <label class="property-label">URL do Webhook ${integrationName}</label>
                    <input type="text" class="property-input" name="config.webhookUrl" value="${escapeHtml(config.webhookUrl || '')}" placeholder="https://hooks.${node.type}.com/...">
                </div>
                <div class="property-group">
                    <label class="property-label">Dados a enviar (JSON)</label>
                    <textarea class="property-input property-textarea" name="config.data" placeholder='{"nome": "{{nome}}", "email": "{{email}}"}'>${escapeHtml(config.data || '')}</textarea>
                </div>
                <div class="property-hint">
                    <i class="fas fa-external-link-alt"></i> Configure o webhook no ${integrationName} primeiro
                </div>
            `;
            break;
            
        case 'chatwoot':
            html += `
                <div class="property-group">
                    <label class="property-label">Ação</label>
                    <select class="property-input property-select" name="config.action">
                        <option value="create_contact" ${config.action === 'create_contact' ? 'selected' : ''}>Criar contato</option>
                        <option value="update_contact" ${config.action === 'update_contact' ? 'selected' : ''}>Atualizar contato</option>
                        <option value="add_label" ${config.action === 'add_label' ? 'selected' : ''}>Adicionar label</option>
                        <option value="assign_agent" ${config.action === 'assign_agent' ? 'selected' : ''}>Atribuir agente</option>
                    </select>
                </div>
                <div class="property-group">
                    <label class="property-label">URL do Chatwoot</label>
                    <input type="text" class="property-input" name="config.chatwootUrl" value="${escapeHtml(config.chatwootUrl || '')}" placeholder="https://app.chatwoot.com">
                </div>
                <div class="property-group">
                    <label class="property-label">API Token</label>
                    <input type="password" class="property-input" name="config.apiToken" value="${escapeHtml(config.apiToken || '')}">
                </div>
            `;
            break;
            
        // ===== AI =====
        case 'openai':
            html += `
                <div class="property-group">
                    <label class="property-label">Provider de IA</label>
                    <select class="property-input property-select" name="config.provider" onchange="updateAIProviderModels(this)">
                        <option value="openai" ${!config.provider || config.provider === 'openai' ? 'selected' : ''}>OpenAI (GPT)</option>
                        <option value="gemini" ${config.provider === 'gemini' ? 'selected' : ''}>Google Gemini</option>
                        <option value="anthropic" ${config.provider === 'anthropic' ? 'selected' : ''}>Anthropic (Claude)</option>
                        <option value="groq" ${config.provider === 'groq' ? 'selected' : ''}>Groq (Llama)</option>
                    </select>
                </div>
                <div class="property-group">
                    <label class="property-label">API Key *</label>
                    <input type="password" class="property-input" name="config.apiKey" value="${escapeHtml(config.apiKey || '')}" placeholder="sk-..." id="aiApiKeyInput">
                    <small style="display: block; margin-top: 4px; color: #6B7280; font-size: 0.75rem;">
                        <i class="fas fa-lock"></i> Será armazenada de forma segura
                        <a href="#" onclick="toggleAIApiKey(); return false;" style="color: #3B82F6; margin-left: 8px;">
                            <i class="fas fa-eye" id="toggleAIApiKeyIcon"></i> Mostrar
                        </a>
                    </small>
                </div>
                <div class="property-group">
                    <label class="property-label">Modelo</label>
                    <select class="property-input property-select" name="config.model" id="aiModelSelect">
                        ${getAIModelsOptions(config.provider || 'openai', config.model)}
                    </select>
                </div>
                <div class="property-group">
                    <label class="property-label">Prompt do Sistema</label>
                    <textarea class="property-input property-textarea" name="config.systemPrompt" placeholder="Você é um assistente útil...">${escapeHtml(config.systemPrompt || '')}</textarea>
                </div>
                <div class="property-group">
                    <label class="property-label">Mensagem do Usuário</label>
                    <textarea class="property-input property-textarea" name="config.prompt" placeholder="{{mensagem}} ou texto fixo">${escapeHtml(config.prompt || '')}</textarea>
                </div>
                <div class="property-group">
                    <label class="property-label">Salvar resposta em variável</label>
                    <input type="text" class="property-input" name="config.responseVariable" value="${escapeHtml(config.responseVariable || 'ai_response')}">
                </div>
                <div class="property-group">
                    <label class="property-label">Temperatura (0-2)</label>
                    <input type="number" class="property-input" name="config.temperature" value="${config.temperature || 0.7}" min="0" max="2" step="0.1">
                    <small style="display: block; margin-top: 4px; color: #6B7280; font-size: 0.75rem;">0 = preciso, 2 = criativo</small>
                </div>
                <div class="property-group">
                    <label class="property-label">Máximo de tokens</label>
                    <input type="number" class="property-input" name="config.maxTokens" value="${config.maxTokens || 500}" min="1" max="4000">
                </div>
            `;
            break;
            
        case 'ai_assistant':
            html += `
                <div class="property-group">
                    <label class="property-label">ID do Assistente OpenAI</label>
                    <input type="text" class="property-input" name="config.assistantId" value="${escapeHtml(config.assistantId || '')}" placeholder="asst_...">
                </div>
                <div class="property-group">
                    <label class="property-label">Instruções adicionais</label>
                    <textarea class="property-input property-textarea" name="config.instructions" placeholder="Instruções específicas para esta conversa...">${escapeHtml(config.instructions || '')}</textarea>
                </div>
                <div class="property-group">
                    <label class="property-label">Salvar resposta em variável</label>
                    <input type="text" class="property-input" name="config.responseVariable" value="${escapeHtml(config.responseVariable || 'assistant_response')}">
                </div>
            `;
            break;
            
        // ===== WHATSAPP ESPECÍFICOS =====
        case 'transfer':
            html += `
                <div class="property-group">
                    <label class="property-label">Mensagem de transferência</label>
                    <textarea class="property-input property-textarea" name="config.message" placeholder="Aguarde, estou transferindo você para um atendente...">${escapeHtml(config.message || 'Transferindo para um atendente...')}</textarea>
                </div>
                <div class="property-group">
                    <label class="property-label">Departamento (opcional)</label>
                    <input type="text" class="property-input" name="config.department" value="${escapeHtml(config.department || '')}" placeholder="Vendas, Suporte...">
                </div>
                <div class="property-group">
                    <label class="property-label">Prioridade</label>
                    <select class="property-input property-select" name="config.priority">
                        <option value="normal" ${config.priority === 'normal' || !config.priority ? 'selected' : ''}>Normal</option>
                        <option value="high" ${config.priority === 'high' ? 'selected' : ''}>Alta</option>
                        <option value="urgent" ${config.priority === 'urgent' ? 'selected' : ''}>Urgente</option>
                    </select>
                </div>
            `;
            break;
            
        case 'end_chat':
            html += `
                <div class="property-group">
                    <label class="property-label">Mensagem de encerramento</label>
                    <textarea class="property-input property-textarea" name="config.message" placeholder="Obrigado pelo contato! Até mais.">${escapeHtml(config.message || 'Obrigado pelo contato!')}</textarea>
                </div>
                <div class="property-group">
                    <label class="property-label">
                        <input type="checkbox" name="config.saveTranscript" ${config.saveTranscript ? 'checked' : ''}> Salvar transcrição
                    </label>
                </div>
            `;
            break;
            
        // ===== INÍCIO =====
        case 'start':
            html += `
                <div class="property-group">
                    <label class="property-label">Gatilho de Ativação</label>
                    <select class="property-input property-select" name="config.trigger_type" onchange="toggleTriggerOptions(this.value)">
                        <option value="manual" ${config.trigger_type === 'manual' || !config.trigger_type ? 'selected' : ''}>Manual (não automático)</option>
                        <option value="keyword" ${config.trigger_type === 'keyword' ? 'selected' : ''}>Palavra-chave</option>
                        <option value="first_message" ${config.trigger_type === 'first_message' ? 'selected' : ''}>Primeira mensagem</option>
                        <option value="all" ${config.trigger_type === 'all' ? 'selected' : ''}>Todas as mensagens</option>
                    </select>
                </div>
                <div class="property-group" id="keywordsGroup" style="display: ${config.trigger_type === 'keyword' ? 'block' : 'none'}">
                    <label class="property-label">Palavras-chave (uma por linha)</label>
                    <textarea class="property-input property-textarea" name="config.keywords" placeholder="oi&#10;olá&#10;bom dia&#10;menu">${(config.keywords || []).join('\n')}</textarea>
                </div>
                <div class="property-hint" style="margin-top: 12px;">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Dica:</strong> Configure o gatilho para iniciar o fluxo automaticamente quando um contato enviar mensagem.
                </div>
            `;
            break;
            
        default:
            html += `
                <div class="property-hint">
                    <i class="fas fa-cog"></i> Configure este bloco conectando-o a outros blocos no fluxo.
                </div>
            `;
    }
    
    // Botão excluir (exceto para start)
    if (node.type !== 'start') {
        html += `
            <div class="property-group" style="margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--fb-sidebar-border);">
                <button onclick="deleteNode('${node.id}')" class="btn-delete-node">
                    <i class="fas fa-trash"></i> Excluir bloco
                </button>
            </div>
        `;
    }
    
    return html;
}

function updateNodeFromForm(nodeId) {
    const node = flowData.nodes.find(n => String(n.id) === String(nodeId));
    if (!node) {
        console.error('Nó não encontrado para atualização:', nodeId);
        return;
    }
    
    console.log('Atualizando nó:', nodeId, 'tipo:', node.type);
    
    const content = document.getElementById('propertiesContent');
    const inputs = content.querySelectorAll('input, textarea, select');
    
    inputs.forEach(input => {
        const name = input.name;
        if (!name) return;
        
        // Tratar checkboxes
        if (input.type === 'checkbox') {
            if (name.startsWith('config.')) {
                const key = name.replace('config.', '');
                node.config[key] = input.checked;
            }
            return;
        }
        
        const value = input.value;
        
        if (name === 'label') {
            node.label = value;
        } else if (name.startsWith('config.')) {
            const key = name.replace('config.', '');
            // Arrays (um item por linha)
            if (['buttons', 'keywords', 'items', 'choices'].includes(key)) {
                node.config[key] = value.split('\n').filter(b => b.trim());
            } 
            // Números
            else if (['seconds', 'max', 'min', 'height', 'maxSize', 'maxTokens', 'groupAPercent', 'groupBPercent'].includes(key)) {
                node.config[key] = value ? parseFloat(value) : null;
            }
            // Temperatura (float)
            else if (key === 'temperature') {
                node.config[key] = value ? parseFloat(value) : 0.7;
            }
            // Strings normais
            else {
                node.config[key] = value;
            }
        }
    });
    
    renderNodes();
    renderConnections();
}

function closePropertiesPanel() {
    document.getElementById('propertiesPanel').style.display = 'none';
}

function toggleTriggerOptions(value) {
    const keywordsGroup = document.getElementById('keywordsGroup');
    if (keywordsGroup) {
        keywordsGroup.style.display = value === 'keyword' ? 'block' : 'none';
    }
}

function deleteNode(nodeId) {
    if (!confirm('Excluir este bloco?')) return;
    
    flowData.nodes = flowData.nodes.filter(n => String(n.id) !== String(nodeId));
    flowData.edges = flowData.edges.filter(e => String(e.from) !== String(nodeId) && String(e.to) !== String(nodeId));
    
    closePropertiesPanel();
    saveToHistory();
    renderAll();
}

// ===== ZOOM =====
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
    canvas.panX = 50;
    canvas.panY = 80;
    updateCanvasTransform();
    document.getElementById('zoomLevel').textContent = '100%';
}

// ===== HISTÓRICO (UNDO/REDO) =====
function saveToHistory() {
    history = history.slice(0, historyIndex + 1);
    history.push(JSON.stringify(flowData));
    historyIndex = history.length - 1;
    
    if (history.length > 50) {
        history.shift();
        historyIndex--;
    }
}

function undo() {
    if (historyIndex > 0) {
        historyIndex--;
        flowData = JSON.parse(history[historyIndex]);
        renderAll();
        showToast('Ação desfeita');
    }
}

function redo() {
    if (historyIndex < history.length - 1) {
        historyIndex++;
        flowData = JSON.parse(history[historyIndex]);
        renderAll();
        showToast('Ação refeita');
    }
}

// ===== ATALHOS DE TECLADO =====
function initKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            saveFlow();
        }
        if (e.ctrlKey && e.key === 'z') {
            e.preventDefault();
            undo();
        }
        if (e.ctrlKey && e.key === 'y') {
            e.preventDefault();
            redo();
        }
        if (e.key === 'Delete' && canvas.selectedNode) {
            const node = flowData.nodes.find(n => n.id === canvas.selectedNode);
            if (node && node.type !== 'start') {
                deleteNode(canvas.selectedNode);
            }
        }
        if (e.key === 'Escape') {
            deselectNode();
        }
    });
}

// ===== SALVAR =====
async function saveFlow() {
    try {
        const nodes = flowData.nodes.map(n => ({
            id: n.id,
            type: n.type,
            label: n.label,
            config: n.config,
            x: Math.round(n.x),
            y: Math.round(n.y)
        }));
        
        const edges = flowData.edges.map(e => ({
            from: e.from,
            to: e.to
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
            if (data.id_map) {
                flowData.nodes.forEach(node => {
                    if (data.id_map[node.id]) {
                        node.id = data.id_map[node.id];
                    }
                });
            }
            showToast('Fluxo salvo!', 'success');
        } else {
            showToast('Erro ao salvar', 'error');
        }
    } catch (error) {
        console.error('Erro:', error);
        showToast('Erro ao salvar', 'error');
    }
}

async function publishFlow() {
    await saveFlow();
    
    try {
        const response = await fetch('api/bot_flows.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'publish', id: flowId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('flowStatus').textContent = 'Publicado';
            document.getElementById('flowStatus').className = 'flow-status-badge published';
            showToast('Fluxo publicado! v' + data.version, 'success');
        } else {
            showToast('Erro ao publicar', 'error');
        }
    } catch (error) {
        showToast('Erro ao publicar', 'error');
    }
}

// ===== PREVIEW DO FLUXO =====
let previewState = {
    currentNodeId: null,
    variables: {},
    isRunning: false,
    waitingForInput: false,
    inputType: null,
    inputConfig: null
};

function previewFlow() {
    // Verificar se há nós no fluxo
    if (flowData.nodes.length === 0) {
        showToast('Adicione blocos ao fluxo primeiro', 'error');
        return;
    }
    
    // Encontrar nó de início
    const startNode = flowData.nodes.find(n => n.type === 'start');
    if (!startNode) {
        showToast('Adicione um bloco de Início', 'error');
        return;
    }
    
    // Resetar estado
    previewState = {
        currentNodeId: startNode.id,
        variables: {
            nome: 'Usuário Teste',
            telefone: '11999999999',
            email: 'teste@email.com'
        },
        isRunning: true,
        waitingForInput: false,
        inputType: null,
        inputConfig: null
    };
    
    // Mostrar modal
    document.getElementById('previewModal').style.display = 'flex';
    document.getElementById('previewMessages').innerHTML = '';
    document.getElementById('previewInputArea').style.display = 'none';
    document.getElementById('previewButtonsArea').style.display = 'none';
    
    // Adicionar mensagem de sistema
    addPreviewSystemMessage('Simulação iniciada');
    
    // Executar fluxo a partir do início
    setTimeout(() => executePreviewNode(startNode.id), 500);
}

function closePreview() {
    document.getElementById('previewModal').style.display = 'none';
    previewState.isRunning = false;
}

function restartPreview() {
    closePreview();
    setTimeout(() => previewFlow(), 100);
}

function executePreviewNode(nodeId) {
    if (!previewState.isRunning) return;
    
    const node = flowData.nodes.find(n => String(n.id) === String(nodeId));
    if (!node) {
        addPreviewSystemMessage('Fluxo finalizado');
        return;
    }
    
    previewState.currentNodeId = nodeId;
    const config = node.config || {};
    
    console.log('Executando nó:', node.type, node.label);
    
    switch(node.type) {
        case 'start':
            // Apenas avança para o próximo
            goToNextNode(nodeId);
            break;
            
        case 'text':
            showTypingIndicator();
            setTimeout(() => {
                removeTypingIndicator();
                const text = replaceVariables(config.text || 'Mensagem de texto');
                addPreviewMessage(text, 'bot');
                goToNextNode(nodeId);
            }, 1000);
            break;
            
        case 'image':
            showTypingIndicator();
            setTimeout(() => {
                removeTypingIndicator();
                if (config.url) {
                    addPreviewImage(config.url, 'bot');
                } else {
                    addPreviewMessage('[Imagem não configurada]', 'bot');
                }
                goToNextNode(nodeId);
            }, 800);
            break;
            
        case 'video':
            showTypingIndicator();
            setTimeout(() => {
                removeTypingIndicator();
                addPreviewMessage('🎬 [Vídeo: ' + (config.url || 'não configurado') + ']', 'bot');
                goToNextNode(nodeId);
            }, 800);
            break;
            
        case 'audio':
            showTypingIndicator();
            setTimeout(() => {
                removeTypingIndicator();
                addPreviewMessage('🎵 [Áudio: ' + (config.url || 'não configurado') + ']', 'bot');
                goToNextNode(nodeId);
            }, 800);
            break;
            
        case 'file':
            showTypingIndicator();
            setTimeout(() => {
                removeTypingIndicator();
                addPreviewMessage('📎 [Arquivo: ' + (config.filename || 'documento') + ']', 'bot');
                goToNextNode(nodeId);
            }, 800);
            break;
            
        case 'input_text':
        case 'input_number':
        case 'input_email':
        case 'input_phone':
        case 'input_date':
            showTypingIndicator();
            setTimeout(() => {
                removeTypingIndicator();
                if (config.placeholder) {
                    addPreviewMessage(config.placeholder, 'bot');
                }
                waitForTextInput(node.type, config);
            }, 800);
            break;
            
        case 'buttons':
            showTypingIndicator();
            setTimeout(() => {
                removeTypingIndicator();
                if (config.text) {
                    addPreviewMessage(replaceVariables(config.text), 'bot');
                }
                showButtonChoices(config.buttons || [], config);
            }, 800);
            break;
            
        case 'rating':
            showTypingIndicator();
            setTimeout(() => {
                removeTypingIndicator();
                addPreviewMessage('Por favor, avalie de 1 a ' + (config.max || 5), 'bot');
                showRatingInput(config);
            }, 800);
            break;
            
        case 'condition':
            evaluateCondition(node);
            break;
            
        case 'set_variable':
            if (config.variable && config.value) {
                previewState.variables[config.variable] = replaceVariables(config.value);
            }
            goToNextNode(nodeId);
            break;
            
        case 'wait':
            const seconds = config.seconds || 3;
            addPreviewSystemMessage('Aguardando ' + seconds + ' segundos...');
            setTimeout(() => {
                goToNextNode(nodeId);
            }, seconds * 1000);
            break;
            
        case 'redirect':
            addPreviewSystemMessage('Redirecionando para: ' + (config.url || 'URL não configurada'));
            goToNextNode(nodeId);
            break;
            
        case 'webhook':
            addPreviewSystemMessage('Executando webhook: ' + (config.method || 'POST') + ' ' + (config.url || ''));
            setTimeout(() => goToNextNode(nodeId), 500);
            break;
            
        case 'openai':
            showTypingIndicator();
            setTimeout(() => {
                removeTypingIndicator();
                addPreviewMessage('[Resposta simulada da IA: Esta é uma resposta de teste do modelo ' + (config.model || 'GPT') + ']', 'bot');
                if (config.variable) {
                    previewState.variables[config.variable] = 'Resposta simulada da IA';
                }
                goToNextNode(nodeId);
            }, 1500);
            break;
            
        case 'transfer':
            addPreviewSystemMessage('Transferindo para atendente...');
            addPreviewMessage(config.message || 'Você será transferido para um atendente.', 'bot');
            break;
            
        case 'end_chat':
            addPreviewMessage(config.message || 'Obrigado pelo contato!', 'bot');
            addPreviewSystemMessage('Conversa encerrada');
            break;
            
        case 'end':
            addPreviewSystemMessage('Fluxo finalizado');
            break;
            
        default:
            addPreviewSystemMessage('Bloco: ' + node.label);
            goToNextNode(nodeId);
    }
}

function goToNextNode(currentNodeId) {
    if (!previewState.isRunning) return;
    
    // Encontrar conexão de saída
    const edge = flowData.edges.find(e => String(e.from) === String(currentNodeId));
    
    if (edge) {
        setTimeout(() => executePreviewNode(edge.to), 300);
    } else {
        addPreviewSystemMessage('Fluxo finalizado');
    }
}

function waitForTextInput(inputType, config) {
    previewState.waitingForInput = true;
    previewState.inputType = inputType;
    previewState.inputConfig = config;
    
    document.getElementById('previewInputArea').style.display = 'flex';
    document.getElementById('previewButtonsArea').style.display = 'none';
    
    const input = document.getElementById('previewInput');
    input.placeholder = config.placeholder || 'Digite sua resposta...';
    input.value = '';
    input.focus();
}

function sendPreviewMessage() {
    if (!previewState.waitingForInput) return;
    
    const input = document.getElementById('previewInput');
    const value = input.value.trim();
    
    if (!value) return;
    
    // Adicionar mensagem do usuário
    addPreviewMessage(value, 'user');
    
    // Salvar na variável
    if (previewState.inputConfig && previewState.inputConfig.variable) {
        previewState.variables[previewState.inputConfig.variable] = value;
    }
    
    // Esconder input
    document.getElementById('previewInputArea').style.display = 'none';
    previewState.waitingForInput = false;
    
    // Continuar fluxo
    goToNextNode(previewState.currentNodeId);
}

function showButtonChoices(buttons, config) {
    previewState.waitingForInput = true;
    previewState.inputType = 'buttons';
    previewState.inputConfig = config;
    
    document.getElementById('previewInputArea').style.display = 'none';
    const buttonsArea = document.getElementById('previewButtonsArea');
    buttonsArea.style.display = 'flex';
    buttonsArea.innerHTML = '';
    
    buttons.forEach(btn => {
        const button = document.createElement('button');
        button.className = 'preview-choice-btn';
        button.textContent = btn;
        button.onclick = () => selectButtonChoice(btn);
        buttonsArea.appendChild(button);
    });
}

function selectButtonChoice(choice) {
    // Adicionar mensagem do usuário
    addPreviewMessage(choice, 'user');
    
    // Salvar na variável
    if (previewState.inputConfig && previewState.inputConfig.variable) {
        previewState.variables[previewState.inputConfig.variable] = choice;
    }
    
    // Esconder botões
    document.getElementById('previewButtonsArea').style.display = 'none';
    previewState.waitingForInput = false;
    
    // Continuar fluxo
    goToNextNode(previewState.currentNodeId);
}

function showRatingInput(config) {
    previewState.waitingForInput = true;
    previewState.inputType = 'rating';
    previewState.inputConfig = config;
    
    document.getElementById('previewInputArea').style.display = 'none';
    const buttonsArea = document.getElementById('previewButtonsArea');
    buttonsArea.style.display = 'flex';
    buttonsArea.innerHTML = '';
    
    const ratingDiv = document.createElement('div');
    ratingDiv.className = 'preview-rating';
    
    const max = config.max || 5;
    for (let i = 1; i <= max; i++) {
        const star = document.createElement('span');
        star.className = 'preview-rating-star';
        star.innerHTML = '★';
        star.onclick = () => selectRating(i);
        star.onmouseenter = () => highlightStars(i);
        star.onmouseleave = () => highlightStars(0);
        star.dataset.value = i;
        ratingDiv.appendChild(star);
    }
    
    buttonsArea.appendChild(ratingDiv);
}

function highlightStars(upTo) {
    document.querySelectorAll('.preview-rating-star').forEach(star => {
        star.classList.toggle('active', parseInt(star.dataset.value) <= upTo);
    });
}

function selectRating(value) {
    // Adicionar mensagem do usuário
    addPreviewMessage('⭐'.repeat(value), 'user');
    
    // Salvar na variável
    if (previewState.inputConfig && previewState.inputConfig.variable) {
        previewState.variables[previewState.inputConfig.variable] = value;
    }
    
    // Esconder rating
    document.getElementById('previewButtonsArea').style.display = 'none';
    previewState.waitingForInput = false;
    
    // Continuar fluxo
    goToNextNode(previewState.currentNodeId);
}

function evaluateCondition(node) {
    const config = node.config || {};
    const variable = previewState.variables[config.variable] || '';
    const value = config.value || '';
    const operator = config.operator || 'equals';
    
    let result = false;
    
    switch(operator) {
        case 'equals':
            result = String(variable).toLowerCase() === String(value).toLowerCase();
            break;
        case 'not_equals':
            result = String(variable).toLowerCase() !== String(value).toLowerCase();
            break;
        case 'contains':
            result = String(variable).toLowerCase().includes(String(value).toLowerCase());
            break;
        case 'greater':
            result = parseFloat(variable) > parseFloat(value);
            break;
        case 'less':
            result = parseFloat(variable) < parseFloat(value);
            break;
        case 'empty':
            result = !variable || variable === '';
            break;
        case 'not_empty':
            result = variable && variable !== '';
            break;
    }
    
    addPreviewSystemMessage('Condição: ' + config.variable + ' ' + operator + ' ' + value + ' = ' + (result ? 'Verdadeiro' : 'Falso'));
    
    // TODO: Implementar múltiplas saídas de condição
    // Por enquanto, apenas continua para o próximo nó
    goToNextNode(node.id);
}

function addPreviewMessage(text, sender) {
    const container = document.getElementById('previewMessages');
    const msg = document.createElement('div');
    msg.className = 'preview-message ' + sender;
    
    const time = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    msg.innerHTML = text + '<span class="time">' + time + '</span>';
    
    container.appendChild(msg);
    container.scrollTop = container.scrollHeight;
}

function addPreviewImage(url, sender) {
    const container = document.getElementById('previewMessages');
    const msg = document.createElement('div');
    msg.className = 'preview-message ' + sender;
    
    const time = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    msg.innerHTML = '<img src="' + url + '" alt="Imagem" onerror="this.src=\'https://via.placeholder.com/200x150?text=Imagem\'"><span class="time">' + time + '</span>';
    
    container.appendChild(msg);
    container.scrollTop = container.scrollHeight;
}

function addPreviewSystemMessage(text) {
    const container = document.getElementById('previewMessages');
    const msg = document.createElement('div');
    msg.className = 'preview-system-msg';
    msg.textContent = text;
    
    container.appendChild(msg);
    container.scrollTop = container.scrollHeight;
}

function showTypingIndicator() {
    const container = document.getElementById('previewMessages');
    const typing = document.createElement('div');
    typing.className = 'preview-message typing';
    typing.id = 'typingIndicator';
    typing.innerHTML = '<div class="typing-indicator"><span></span><span></span><span></span></div>';
    
    container.appendChild(typing);
    container.scrollTop = container.scrollHeight;
}

function removeTypingIndicator() {
    const typing = document.getElementById('typingIndicator');
    if (typing) typing.remove();
}

function replaceVariables(text) {
    if (!text) return '';
    
    return text.replace(/\{\{(\w+)\}\}/g, (match, varName) => {
        return previewState.variables[varName] || match;
    });
}

// ===== UTILITÁRIOS =====
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toastMessage');
    
    toast.className = 'toast toast-' + type;
    toastMessage.textContent = message;
    toast.style.display = 'flex';
    
    setTimeout(() => {
        toast.style.display = 'none';
    }, 3000);
}

function copyVariable(variable) {
    navigator.clipboard.writeText(variable);
    showToast('Variável copiada!');
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showNodeMenu(nodeId) {
    // TODO: Implementar menu de contexto
    selectNode(nodeId);
}

// ===== FUNÇÕES PARA PROVIDERS DE IA =====
function getAIModelsOptions(provider, selectedModel) {
    const models = {
        openai: [
            { value: 'gpt-4o', label: 'GPT-4o (mais recente - multimodal)' },
            { value: 'gpt-4o-mini', label: 'GPT-4o Mini (rápido e econômico)' },
            { value: 'gpt-4-turbo', label: 'GPT-4 Turbo (128K contexto)' },
            { value: 'gpt-4', label: 'GPT-4 (clássico)' },
            { value: 'gpt-3.5-turbo', label: 'GPT-3.5 Turbo (econômico)' },
            { value: 'gpt-3.5-turbo-16k', label: 'GPT-3.5 Turbo 16K (contexto maior)' },
            { value: 'o1-preview', label: 'O1 Preview (raciocínio avançado)' },
            { value: 'o1-mini', label: 'O1 Mini (raciocínio rápido)' }
        ],
        gemini: [
            { value: 'gemini-2.0-flash', label: 'Gemini 2.0 Flash (mais recente - rápido)' },
            { value: 'gemini-1.5-pro', label: 'Gemini 1.5 Pro (1M tokens contexto)' },
            { value: 'gemini-1.5-flash', label: 'Gemini 1.5 Flash (rápido)' },
            { value: 'gemini-pro', label: 'Gemini Pro (clássico)' }
        ],
        anthropic: [
            { value: 'claude-3-7-sonnet-20250219', label: 'Claude 3.7 Sonnet (mais recente - híbrido)' },
            { value: 'claude-3-5-sonnet-20241022', label: 'Claude 3.5 Sonnet (melhor custo-benefício)' },
            { value: 'claude-3-opus-20240229', label: 'Claude 3 Opus (mais inteligente)' },
            { value: 'claude-3-sonnet-20240229', label: 'Claude 3 Sonnet (equilibrado)' },
            { value: 'claude-3-haiku-20240307', label: 'Claude 3 Haiku (mais rápido)' }
        ],
        groq: [
            { value: 'llama-3.3-70b-versatile', label: 'Llama 3.3 70B (mais recente - versátil)' },
            { value: 'llama-3.2-90b-vision-preview', label: 'Llama 3.2 90B (com visão)' },
            { value: 'llama-3.1-70b-versatile', label: 'Llama 3.1 70B (versátil)' },
            { value: 'llama-3.1-8b-instant', label: 'Llama 3.1 8B (instantâneo)' },
            { value: 'mixtral-8x7b-32768', label: 'Mixtral 8x7B (32K contexto)' },
            { value: 'gemma-2-9b-it', label: 'Gemma 2 9B (Google)' }
        ]
    };
    
    const providerModels = models[provider] || models.openai;
    return providerModels.map(m => 
        `<option value="${m.value}" ${m.value === selectedModel ? 'selected' : ''}>${m.label}</option>`
    ).join('');
}

function updateAIProviderModels(selectElement) {
    const provider = selectElement.value;
    const modelSelect = document.getElementById('aiModelSelect');
    if (modelSelect) {
        modelSelect.innerHTML = getAIModelsOptions(provider, '');
    }
}

function toggleAIApiKey() {
    const input = document.getElementById('aiApiKeyInput');
    const icon = document.getElementById('toggleAIApiKeyIcon');
    
    if (input && icon) {
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
}

// Cancelar conexão ao soltar fora de um conector
document.addEventListener('mouseup', function(e) {
    if (canvas.isConnecting) {
        // Verificar se soltou em um conector de entrada
        if (!e.target.classList.contains('node-connector') || !e.target.classList.contains('in')) {
            finishConnection();
        }
    }
});
<?php endif; ?>
</script>
</body>
</html>
