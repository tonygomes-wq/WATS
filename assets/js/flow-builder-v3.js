/**
 * Flow Builder V3 - JavaScript Estilo Typebot
 * Estrutura: Groups > Blocks
 */

let flowId = 0;
let flowData = { groups: [], edges: [] };
let state = { zoom: 1, panX: 50, panY: 50, isPanning: false, isDragging: false, isConnecting: false, connectFrom: null, selectedGroup: null, selectedBlock: null };

const blockTypes = {
    text: { label: 'Texto', icon: 'fa-align-left', category: 'bubble', config: { content: 'Olá! Como posso ajudar?' } },
    image: { label: 'Imagem', icon: 'fa-image', category: 'bubble', config: { url: '' } },
    video: { label: 'Vídeo', icon: 'fa-video', category: 'bubble', config: { url: '' } },
    embed: { label: 'Embed', icon: 'fa-code', category: 'bubble', config: { html: '' } },
    text_input: { label: 'Entrada Texto', icon: 'fa-font', category: 'input', config: { variable: 'resposta', placeholder: 'Digite...' } },
    number: { label: 'Número', icon: 'fa-hashtag', category: 'input', config: { variable: 'numero' } },
    email: { label: 'Email', icon: 'fa-envelope', category: 'input', config: { variable: 'email' } },
    phone: { label: 'Telefone', icon: 'fa-phone', category: 'input', config: { variable: 'telefone' } },
    buttons: { label: 'Botões', icon: 'fa-hand-pointer', category: 'input', config: { variable: 'escolha', items: ['Opção 1', 'Opção 2'] } },
    rating: { label: 'Avaliação', icon: 'fa-star', category: 'input', config: { variable: 'nota', max: 5 } },
    set_variable: { label: 'Variável', icon: 'fa-pen', category: 'logic', config: { variable: '', value: '' } },
    condition: { label: 'Condição', icon: 'fa-code-branch', category: 'logic', config: { variable: '', operator: 'equals', value: '' } },
    wait: { label: 'Aguardar', icon: 'fa-clock', category: 'logic', config: { seconds: 3 } },
    webhook: { label: 'Webhook', icon: 'fa-plug', category: 'integration', config: { url: '', method: 'POST' } },
    openai: { label: 'OpenAI', icon: 'fa-brain', category: 'integration', config: { model: 'gpt-3.5-turbo', prompt: '', variable: 'resposta_ia' } },
    start: { label: 'Start', icon: 'fa-play', category: 'start', config: {} }
};

function initFlowBuilder(id) {
    flowId = id;
    loadFlow().then(() => {
        initCanvas();
        initDragDrop();
        renderAll();
    });
}

async function loadFlow() {
    try {
        const res = await fetch(`api/bot_flows.php?action=get&id=${flowId}`);
        const data = await res.json();
        if (data.success && data.nodes && data.nodes.length > 0) {
            flowData.groups = data.nodes.map(n => ({
                id: 'g_' + n.id,
                title: n.label || 'Grupo',
                x: parseInt(n.pos_x) || 100,
                y: parseInt(n.pos_y) || 100,
                blocks: [{ id: 'b_' + n.id, type: n.type, config: typeof n.config === 'string' ? JSON.parse(n.config || '{}') : (n.config || {}) }]
            }));
            flowData.edges = (data.edges || []).map(e => ({ id: 'e_' + e.id, from: 'g_' + e.from_node_id, to: 'g_' + e.to_node_id }));
        }
    } catch (e) { console.error('Load error:', e); }
    if (flowData.groups.length === 0) {
        flowData.groups.push({ id: 'g_start', title: 'Start', x: 100, y: 100, blocks: [{ id: 'b_start', type: 'start', config: {} }] });
    }
}

function initCanvas() {
    const container = document.getElementById('canvasContainer');
    let startX, startY;
    
    container.addEventListener('mousedown', e => {
        if (e.target === container || e.target.id === 'canvas' || e.target.id === 'groupsContainer' || e.target.tagName === 'svg') {
            state.isPanning = true;
            startX = e.clientX - state.panX;
            startY = e.clientY - state.panY;
            container.style.cursor = 'grabbing';
            deselectAll();
        }
    });
    
    document.addEventListener('mousemove', e => {
        if (state.isPanning) {
            state.panX = e.clientX - startX;
            state.panY = e.clientY - startY;
            updateTransform();
        }
    });
    
    document.addEventListener('mouseup', () => {
        if (state.isPanning) { state.isPanning = false; container.style.cursor = ''; }
        if (state.isConnecting) finishConnection();
    });
    
    container.addEventListener('wheel', e => {
        e.preventDefault();
        state.zoom = Math.max(0.25, Math.min(2, state.zoom + (e.deltaY > 0 ? -0.1 : 0.1)));
        updateTransform();
        document.getElementById('zoomLevel').textContent = Math.round(state.zoom * 100) + '%';
    });
}

function updateTransform() {
    document.getElementById('canvas').style.transform = `translate(${state.panX}px, ${state.panY}px) scale(${state.zoom})`;
}

function initDragDrop() {
    document.querySelectorAll('.block-item').forEach(b => {
        b.addEventListener('dragstart', e => e.dataTransfer.setData('blockType', b.dataset.type));
    });
    const container = document.getElementById('canvasContainer');
    container.addEventListener('dragover', e => e.preventDefault());
    container.addEventListener('drop', e => {
        e.preventDefault();
        const type = e.dataTransfer.getData('blockType');
        if (!type) return;
        const rect = container.getBoundingClientRect();
        createGroup(type, (e.clientX - rect.left - state.panX) / state.zoom, (e.clientY - rect.top - state.panY) / state.zoom);
    });
}

function createGroup(blockType, x, y) {
    const tc = blockTypes[blockType];
    if (!tc) return;
    const gid = 'g_' + Date.now();
    flowData.groups.push({
        id: gid, title: tc.label, x: Math.round(x), y: Math.round(y),
        blocks: [{ id: 'b_' + Date.now(), type: blockType, config: { ...tc.config } }]
    });
    renderAll();
    selectGroup(gid);
    showToast('Grupo criado!');
}

function renderAll() { renderGroups(); renderConnections(); }

function renderGroups() {
    const container = document.getElementById('groupsContainer');
    container.innerHTML = '';
    flowData.groups.forEach(g => container.appendChild(createGroupEl(g)));
}

function createGroupEl(group) {
    const div = document.createElement('div');
    div.className = 'flow-group' + (state.selectedGroup === group.id ? ' selected' : '');
    div.id = 'grp_' + group.id;
    div.style.cssText = `left:${group.x}px;top:${group.y}px`;
    
    div.innerHTML = `
        <div class="group-header">
            <input type="text" class="group-title" value="${esc(group.title)}" onchange="updateGroupTitle('${group.id}',this.value)" onclick="event.stopPropagation()">
            <div class="group-menu" onclick="event.stopPropagation();selectGroup('${group.id}')"><i class="fas fa-play"></i></div>
        </div>
        <div class="group-blocks">${group.blocks.map((b,i) => createBlockHtml(b, group.id, i === group.blocks.length - 1)).join('')}</div>
        ${group.id !== 'g_start' ? '<div class="group-connector in"></div>' : ''}
        <div class="group-connector out"></div>
    `;
    
    // Connector events
    const connOut = div.querySelector('.group-connector.out');
    if (connOut) connOut.addEventListener('mousedown', e => { e.stopPropagation(); startConnection(group.id); });
    
    const connIn = div.querySelector('.group-connector.in');
    if (connIn) {
        connIn.addEventListener('mouseenter', () => { if (state.isConnecting && state.connectFrom !== group.id) connIn.classList.add('active'); });
        connIn.addEventListener('mouseleave', () => connIn.classList.remove('active'));
        connIn.addEventListener('mouseup', e => { e.stopPropagation(); if (state.isConnecting && state.connectFrom !== group.id) createEdge(state.connectFrom, group.id); });
    }
    
    // Block click events
    div.querySelectorAll('.group-block').forEach(b => {
        b.addEventListener('click', e => { e.stopPropagation(); selectBlock(group.id, b.dataset.bid); });
    });
    
    // Drag group
    div.addEventListener('mousedown', e => {
        if (e.target.classList.contains('group-connector') || e.target.classList.contains('group-title') || e.target.closest('.group-block')) return;
        state.isDragging = true;
        const startX = e.clientX, startY = e.clientY, gx = group.x, gy = group.y;
        const onMove = ev => {
            group.x = gx + (ev.clientX - startX) / state.zoom;
            group.y = gy + (ev.clientY - startY) / state.zoom;
            div.style.left = group.x + 'px';
            div.style.top = group.y + 'px';
            renderConnections();
        };
        const onUp = () => { state.isDragging = false; document.removeEventListener('mousemove', onMove); document.removeEventListener('mouseup', onUp); };
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
    });
    
    div.addEventListener('click', e => { if (!state.isDragging && !e.target.closest('.group-block')) selectGroup(group.id); });
    
    return div;
}

function createBlockHtml(block, groupId, isLast) {
    const tc = blockTypes[block.type] || { label: block.type, icon: 'fa-cube', category: 'other' };
    return `
        <div class="group-block" data-bid="${block.id}">
            <div class="block-icon ${tc.category}"><i class="fas ${tc.icon}"></i></div>
            <div class="block-content">
                <div class="block-type">${tc.label}</div>
                <div class="block-preview">${getPreview(block)}</div>
                ${block.config.variable ? `<div class="block-variable">Collect ${block.config.variable}</div>` : ''}
            </div>
        </div>
    `;
}

function getPreview(block) {
    const c = block.config || {};
    switch(block.type) {
        case 'text': return esc(c.content || 'Clique para editar...');
        case 'image': return c.url ? 'Imagem configurada' : 'Adicionar imagem';
        case 'text_input': case 'number': case 'email': case 'phone': return c.placeholder || 'Digite...';
        case 'buttons': return (c.items || []).join(' | ') || 'Adicionar opções';
        case 'condition': return c.variable ? `Se ${c.variable} ${c.operator} ${c.value}` : 'Configurar';
        case 'wait': return `Aguardar ${c.seconds || 0}s`;
        case 'webhook': return c.url || 'Configurar URL';
        case 'openai': return c.prompt ? c.prompt.substring(0, 30) + '...' : 'Configurar prompt';
        case 'start': return 'Início do fluxo';
        default: return 'Clique para configurar';
    }
}

// Connections
function startConnection(fromId) {
    state.isConnecting = true;
    state.connectFrom = fromId;
    document.body.style.cursor = 'crosshair';
    
    const svg = document.getElementById('connectionsSvg');
    const temp = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    temp.id = 'tempConn';
    temp.className = 'connection-path temp';
    svg.appendChild(temp);
    
    const fromG = flowData.groups.find(g => g.id === fromId);
    const updateTemp = e => {
        if (!state.isConnecting || !fromG) return;
        const rect = document.getElementById('canvasContainer').getBoundingClientRect();
        const mx = (e.clientX - rect.left - state.panX) / state.zoom;
        const my = (e.clientY - rect.top - state.panY) / state.zoom;
        const fx = fromG.x + 290, fy = fromG.y + 50;
        temp.setAttribute('d', `M ${fx} ${fy} C ${fx+60} ${fy}, ${mx-60} ${my}, ${mx} ${my}`);
    };
    document.addEventListener('mousemove', updateTemp);
    document.addEventListener('mouseup', () => { document.removeEventListener('mousemove', updateTemp); const t = document.getElementById('tempConn'); if(t) t.remove(); }, { once: true });
}

function finishConnection() { state.isConnecting = false; state.connectFrom = null; document.body.style.cursor = ''; }

function createEdge(from, to) {
    if (flowData.edges.some(e => e.from === from && e.to === to)) return;
    flowData.edges.push({ id: 'e_' + Date.now(), from, to });
    renderConnections();
    showToast('Conexão criada!');
}

function renderConnections() {
    const svg = document.getElementById('connectionsSvg');
    const temp = document.getElementById('tempConn');
    svg.innerHTML = '';
    if (temp) svg.appendChild(temp);
    
    flowData.edges.forEach(e => {
        const fg = flowData.groups.find(g => g.id === e.from);
        const tg = flowData.groups.find(g => g.id === e.to);
        if (!fg || !tg) return;
        const fx = fg.x + 290, fy = fg.y + 50, tx = tg.x, ty = tg.y + 50;
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.className = 'connection-path';
        path.setAttribute('d', `M ${fx} ${fy} C ${fx+60} ${fy}, ${tx-60} ${ty}, ${tx} ${ty}`);
        svg.appendChild(path);
    });
}

// Selection
function selectGroup(gid) {
    deselectAll();
    state.selectedGroup = gid;
    const el = document.getElementById('grp_' + gid);
    if (el) el.classList.add('selected');
    showGroupProps(gid);
}

function selectBlock(gid, bid) {
    deselectAll();
    state.selectedGroup = gid;
    state.selectedBlock = bid;
    const gel = document.getElementById('grp_' + gid);
    if (gel) gel.classList.add('selected');
    const bel = document.querySelector(`[data-bid="${bid}"]`);
    if (bel) bel.classList.add('selected');
    showBlockProps(gid, bid);
}

function deselectAll() {
    state.selectedGroup = null;
    state.selectedBlock = null;
    document.querySelectorAll('.flow-group.selected, .group-block.selected').forEach(el => el.classList.remove('selected'));
    closeProperties();
}

// Properties
function showGroupProps(gid) {
    const g = flowData.groups.find(x => x.id === gid);
    if (!g) return;
    document.getElementById('propertiesTitle').textContent = 'Grupo';
    document.getElementById('propertiesContent').innerHTML = `
        <div class="prop-group"><label class="prop-label">Nome</label><input type="text" class="prop-input" value="${esc(g.title)}" onchange="updateGroupTitle('${gid}',this.value)"></div>
        ${gid !== 'g_start' ? `<button class="btn-delete" onclick="deleteGroup('${gid}')"><i class="fas fa-trash"></i> Excluir Grupo</button>` : ''}
    `;
    document.getElementById('propertiesPanel').classList.add('open');
}

function showBlockProps(gid, bid) {
    const g = flowData.groups.find(x => x.id === gid);
    if (!g) return;
    const b = g.blocks.find(x => x.id === bid);
    if (!b) return;
    const tc = blockTypes[b.type] || { label: b.type };
    document.getElementById('propertiesTitle').textContent = tc.label;
    document.getElementById('propertiesContent').innerHTML = generateBlockForm(b, gid);
    document.querySelectorAll('#propertiesContent input, #propertiesContent textarea, #propertiesContent select').forEach(inp => {
        inp.addEventListener('input', () => updateBlockConfig(gid, bid));
        inp.addEventListener('change', () => updateBlockConfig(gid, bid));
    });
    document.getElementById('propertiesPanel').classList.add('open');
}

function generateBlockForm(b, gid) {
    const c = b.config || {};
    let html = '';
    switch(b.type) {
        case 'text':
            html = `<div class="prop-group"><label class="prop-label">Mensagem</label><textarea class="prop-input prop-textarea" name="content" placeholder="Digite...">${esc(c.content||'')}</textarea></div><div class="prop-hint"><i class="fas fa-lightbulb"></i> Use {{nome}}, {{telefone}} para variáveis</div>`;
            break;
        case 'image':
            html = `<div class="prop-group"><label class="prop-label">URL da Imagem</label><input type="text" class="prop-input" name="url" value="${esc(c.url||'')}" placeholder="https://..."></div>`;
            break;
        case 'text_input': case 'number': case 'email': case 'phone':
            html = `<div class="prop-group"><label class="prop-label">Salvar em</label><input type="text" class="prop-input" name="variable" value="${esc(c.variable||'')}"></div><div class="prop-group"><label class="prop-label">Placeholder</label><input type="text" class="prop-input" name="placeholder" value="${esc(c.placeholder||'')}"></div>`;
            break;
        case 'buttons':
            html = `<div class="prop-group"><label class="prop-label">Salvar em</label><input type="text" class="prop-input" name="variable" value="${esc(c.variable||'')}"></div><div class="prop-group"><label class="prop-label">Opções (uma por linha)</label><textarea class="prop-input prop-textarea" name="items">${(c.items||[]).join('\n')}</textarea></div>`;
            break;
        case 'condition':
            html = `<div class="prop-group"><label class="prop-label">Variável</label><input type="text" class="prop-input" name="variable" value="${esc(c.variable||'')}"></div><div class="prop-group"><label class="prop-label">Operador</label><select class="prop-input" name="operator"><option value="equals" ${c.operator==='equals'?'selected':''}>Igual a</option><option value="not_equals" ${c.operator==='not_equals'?'selected':''}>Diferente</option><option value="contains" ${c.operator==='contains'?'selected':''}>Contém</option></select></div><div class="prop-group"><label class="prop-label">Valor</label><input type="text" class="prop-input" name="value" value="${esc(c.value||'')}"></div>`;
            break;
        case 'wait':
            html = `<div class="prop-group"><label class="prop-label">Segundos</label><input type="number" class="prop-input" name="seconds" value="${c.seconds||3}" min="1" max="60"></div>`;
            break;
        case 'webhook':
            html = `<div class="prop-group"><label class="prop-label">URL</label><input type="text" class="prop-input" name="url" value="${esc(c.url||'')}"></div><div class="prop-group"><label class="prop-label">Método</label><select class="prop-input" name="method"><option value="GET" ${c.method==='GET'?'selected':''}>GET</option><option value="POST" ${c.method==='POST'?'selected':''}>POST</option></select></div>`;
            break;
        case 'openai':
            html = `<div class="prop-group"><label class="prop-label">Modelo</label><select class="prop-input" name="model"><option value="gpt-4o" ${c.model==='gpt-4o'?'selected':''}>GPT-4o</option><option value="gpt-4o-mini" ${c.model==='gpt-4o-mini'?'selected':''}>GPT-4o Mini</option><option value="gpt-3.5-turbo" ${c.model==='gpt-3.5-turbo'?'selected':''}>GPT-3.5</option></select></div><div class="prop-group"><label class="prop-label">Prompt</label><textarea class="prop-input prop-textarea" name="prompt">${esc(c.prompt||'')}</textarea></div><div class="prop-group"><label class="prop-label">Salvar em</label><input type="text" class="prop-input" name="variable" value="${esc(c.variable||'')}"></div>`;
            break;
        case 'start':
            html = `<div class="prop-hint"><i class="fas fa-info-circle"></i> Ponto de início do fluxo</div>`;
            break;
        default:
            html = `<div class="prop-hint"><i class="fas fa-cog"></i> Configure este bloco</div>`;
    }
    if (b.type !== 'start') html += `<button class="btn-delete" onclick="deleteBlock('${gid}','${b.id}')"><i class="fas fa-trash"></i> Excluir</button>`;
    return html;
}

function updateBlockConfig(gid, bid) {
    const g = flowData.groups.find(x => x.id === gid);
    if (!g) return;
    const b = g.blocks.find(x => x.id === bid);
    if (!b) return;
    document.querySelectorAll('#propertiesContent input, #propertiesContent textarea, #propertiesContent select').forEach(inp => {
        if (!inp.name) return;
        let val = inp.value;
        if (inp.name === 'items') val = val.split('\n').filter(v => v.trim());
        else if (inp.type === 'number') val = parseFloat(val) || 0;
        b.config[inp.name] = val;
    });
    renderGroups();
}

function updateGroupTitle(gid, title) { const g = flowData.groups.find(x => x.id === gid); if (g) g.title = title; }
function closeProperties() { document.getElementById('propertiesPanel').classList.remove('open'); }

function deleteGroup(gid) {
    if (gid === 'g_start') { showToast('Não pode excluir Start'); return; }
    if (!confirm('Excluir grupo?')) return;
    flowData.groups = flowData.groups.filter(g => g.id !== gid);
    flowData.edges = flowData.edges.filter(e => e.from !== gid && e.to !== gid);
    closeProperties();
    renderAll();
}

function deleteBlock(gid, bid) {
    const g = flowData.groups.find(x => x.id === gid);
    if (!g) return;
    if (g.blocks.length <= 1) { deleteGroup(gid); return; }
    g.blocks = g.blocks.filter(b => b.id !== bid);
    closeProperties();
    renderAll();
}

// Zoom
function zoomIn() { state.zoom = Math.min(2, state.zoom + 0.1); updateTransform(); document.getElementById('zoomLevel').textContent = Math.round(state.zoom * 100) + '%'; }
function zoomOut() { state.zoom = Math.max(0.25, state.zoom - 0.1); updateTransform(); document.getElementById('zoomLevel').textContent = Math.round(state.zoom * 100) + '%'; }

// Save
async function saveFlow() {
    const nodes = flowData.groups.map((g, i) => ({
        id: g.id.replace('g_', ''),
        type: g.blocks[0]?.type || 'text',
        label: g.title,
        config: JSON.stringify(g.blocks[0]?.config || {}),
        pos_x: Math.round(g.x),
        pos_y: Math.round(g.y),
        sort_order: i
    }));
    const edges = flowData.edges.map((e, i) => ({
        from_node_id: e.from.replace('g_', ''),
        to_node_id: e.to.replace('g_', ''),
        sort_order: i
    }));
    try {
        const res = await fetch('api/bot_flows.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'save_nodes', id: flowId, nodes, edges })
        });
        const data = await res.json();
        showToast(data.success ? 'Salvo!' : 'Erro ao salvar');
    } catch (e) { showToast('Erro ao salvar'); }
}

async function publishFlow() {
    await saveFlow();
    try {
        const res = await fetch('api/bot_flows.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'publish', id: flowId })
        });
        const data = await res.json();
        showToast(data.success ? 'Publicado!' : 'Erro');
    } catch (e) { showToast('Erro'); }
}

function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2500);
}

function esc(str) { if (!str) return ''; const d = document.createElement('div'); d.textContent = str; return d.innerHTML; }
