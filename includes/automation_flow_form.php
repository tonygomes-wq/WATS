<?php
/**
 * Formulário de Configuração de Automation Flow
 * Integrado ao Flow Builder V2
 */

// Carregar dados do automation flow se existir
$automationData = [];
if (isset($automationFlow) && $automationFlow) {
    $automationData = [
        'trigger_type' => $automationFlow['trigger_type'] ?? 'keyword',
        'trigger_config' => $automationFlow['trigger_config'] ? json_decode($automationFlow['trigger_config'], true) : [],
        'agent_config' => $automationFlow['agent_config'] ? json_decode($automationFlow['agent_config'], true) : [],
        'actions_config' => $automationFlow['actions_config'] ? json_decode($automationFlow['actions_config'], true) : []
    ];
}
?>

<style>
.automation-form-container {
    flex: 1;
    overflow-y: auto;
    padding: 2rem;
    background: var(--fb-bg);
}

.automation-section {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.automation-section-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: #1F2937;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.automation-section-title i {
    color: #10B981;
}

.form-group {
    margin-bottom: 1rem;
}

.form-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
}

.form-input, .form-select, .form-textarea {
    width: 100%;
    padding: 0.625rem 0.875rem;
    border: 1px solid #D1D5DB;
    border-radius: 8px;
    font-size: 0.875rem;
    transition: all 0.2s;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: #10B981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.form-textarea {
    resize: vertical;
    min-height: 100px;
    font-family: 'Monaco', 'Menlo', 'Courier New', monospace;
}

.btn-add-action {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1.25rem;
    background: #10B981;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-add-action:hover {
    background: #059669;
    transform: translateY(-1px);
}

.action-item {
    background: #F9FAFB;
    border: 1px solid #E5E7EB;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.75rem;
}

.action-item-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.75rem;
}

.action-item-title {
    font-weight: 600;
    color: #1F2937;
}

.btn-remove-action {
    padding: 0.375rem 0.75rem;
    background: #EF4444;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 0.75rem;
    cursor: pointer;
}

.btn-remove-action:hover {
    background: #DC2626;
}

.variable-hint {
    font-size: 0.75rem;
    color: #6B7280;
    margin-top: 0.25rem;
}

.variable-tag {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    background: #DBEAFE;
    color: #1E40AF;
    border-radius: 4px;
    font-size: 0.75rem;
    font-family: monospace;
    margin-right: 0.5rem;
    margin-bottom: 0.5rem;
    cursor: pointer;
}

.variable-tag:hover {
    background: #BFDBFE;
}

:root[data-theme="dark"] .automation-section {
    background: #1F2937;
}

:root[data-theme="dark"] .automation-section-title {
    color: #F3F4F6;
}

:root[data-theme="dark"] .form-label {
    color: #D1D5DB;
}

:root[data-theme="dark"] .form-input,
:root[data-theme="dark"] .form-select,
:root[data-theme="dark"] .form-textarea {
    background: #374151;
    border-color: #4B5563;
    color: #F3F4F6;
}

:root[data-theme="dark"] .action-item {
    background: #374151;
    border-color: #4B5563;
}

:root[data-theme="dark"] .action-item-title {
    color: #F3F4F6;
}

</style>

<div class="automation-form-container">
    <form id="automationForm" onsubmit="saveAutomationFlow(event)">
        
        <!-- Seção: Trigger -->
        <div class="automation-section">
            <div class="automation-section-title">
                <i class="fas fa-bolt"></i>
                Trigger (Gatilho)
            </div>
            
            <div class="form-group">
                <label class="form-label">Tipo de Trigger</label>
                <select id="triggerType" class="form-select" onchange="updateTriggerConfig()">
                    <option value="keyword" <?php echo ($automationData['trigger_type'] ?? '') === 'keyword' ? 'selected' : ''; ?>>Palavra-chave</option>
                    <option value="first_message" <?php echo ($automationData['trigger_type'] ?? '') === 'first_message' ? 'selected' : ''; ?>>Primeira Mensagem</option>
                    <option value="off_hours" <?php echo ($automationData['trigger_type'] ?? '') === 'off_hours' ? 'selected' : ''; ?>>Fora de Horário</option>
                    <option value="no_response" <?php echo ($automationData['trigger_type'] ?? '') === 'no_response' ? 'selected' : ''; ?>>Sem Resposta</option>
                </select>
            </div>
            
            <div id="triggerConfigContainer">
                <!-- Configuração específica do trigger será inserida aqui -->
            </div>
        </div>
        
        <!-- Seção: Agente de IA -->
        <div class="automation-section">
            <div class="automation-section-title">
                <i class="fas fa-brain"></i>
                Agente de IA
            </div>
            
            <div class="form-group">
                <label class="form-label">Provider de IA</label>
                <select id="aiProvider" class="form-select" onchange="updateAIModels()">
                    <option value="openai" <?php echo ($automationData['agent_config']['provider'] ?? '') === 'openai' ? 'selected' : ''; ?>>OpenAI (GPT)</option>
                    <option value="gemini" <?php echo ($automationData['agent_config']['provider'] ?? '') === 'gemini' ? 'selected' : ''; ?>>Google Gemini</option>
                    <option value="anthropic" <?php echo ($automationData['agent_config']['provider'] ?? '') === 'anthropic' ? 'selected' : ''; ?>>Anthropic (Claude)</option>
                    <option value="groq" <?php echo ($automationData['agent_config']['provider'] ?? '') === 'groq' ? 'selected' : ''; ?>>Groq (Llama)</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">API Key do Provider *</label>
                <input type="password" id="aiApiKey" class="form-input" 
                       placeholder="sk-..." 
                       value="<?php echo htmlspecialchars($automationData['agent_config']['api_key'] ?? ''); ?>">
                <div class="variable-hint">
                    <i class="fas fa-info-circle"></i>
                    Sua chave de API será armazenada de forma segura e criptografada.
                    <a href="#" onclick="toggleApiKeyVisibility(); return false;" class="text-blue-600 hover:underline ml-2">
                        <i class="fas fa-eye" id="toggleApiKeyIcon"></i> Mostrar/Ocultar
                    </a>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Modelo</label>
                <select id="aiModel" class="form-select">
                    <!-- Opções serão preenchidas dinamicamente -->
                </select>
                <div class="variable-hint" id="modelDescription">
                    <!-- Descrição do modelo será exibida aqui -->
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Prompt do Sistema</label>
                <textarea id="aiPrompt" class="form-textarea" rows="8" placeholder="Você é um assistente virtual que ajuda clientes com dúvidas sobre produtos..."><?php echo htmlspecialchars($automationData['agent_config']['prompt'] ?? ''); ?></textarea>
                <div class="variable-hint">
                    Variáveis disponíveis:
                    <span class="variable-tag" onclick="insertVariable('aiPrompt', '{{contact_name}}')">{{contact_name}}</span>
                    <span class="variable-tag" onclick="insertVariable('aiPrompt', '{{contact_phone}}')">{{contact_phone}}</span>
                    <span class="variable-tag" onclick="insertVariable('aiPrompt', '{{message}}')">{{message}}</span>
                    <span class="variable-tag" onclick="insertVariable('aiPrompt', '{{conversation_history}}')">{{conversation_history}}</span>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label">Temperatura (0-2)</label>
                    <input type="number" id="aiTemperature" class="form-input" 
                           min="0" max="2" step="0.1" 
                           value="<?php echo $automationData['agent_config']['temperature'] ?? '0.7'; ?>">
                    <div class="variable-hint">Criatividade: 0 = preciso, 2 = criativo</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Máximo de Tokens</label>
                    <input type="number" id="aiMaxTokens" class="form-input" 
                           min="50" max="4000" step="50" 
                           value="<?php echo $automationData['agent_config']['max_tokens'] ?? '500'; ?>">
                    <div class="variable-hint">Tamanho máximo da resposta</div>
                </div>
            </div>
        </div>
        
        <!-- Seção: Ações -->
        <div class="automation-section">
            <div class="automation-section-title">
                <i class="fas fa-tasks"></i>
                Ações
            </div>
            
            <div id="actionsContainer">
                <!-- Ações serão inseridas aqui -->
            </div>
            
            <button type="button" class="btn-add-action" onclick="showAddActionModal()">
                <i class="fas fa-plus"></i>
                Adicionar Ação
            </button>
        </div>
        
        <!-- Botões de Ação -->
        <div class="automation-section" style="display: flex; gap: 1rem;">
            <button type="button" onclick="window.location.href='flows.php'" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                <i class="fas fa-arrow-left mr-2"></i>
                Voltar
            </button>
            <button type="button" onclick="testAutomationFlow()" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                <i class="fas fa-vial mr-2"></i>
                Testar
            </button>
            <button type="submit" class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                <i class="fas fa-save mr-2"></i>
                Salvar
            </button>
        </div>
        
    </form>
</div>

<!-- Modal Adicionar Ação -->
<div id="addActionModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="display: none;">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md mx-4 p-6">
        <h3 class="text-xl font-bold mb-4">Adicionar Ação</h3>
        
        <div class="space-y-2">
            <button onclick="addAction('send_message')" class="w-full px-4 py-3 text-left border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                <i class="fas fa-comment mr-2 text-blue-600"></i>
                <strong>Enviar Mensagem</strong>
                <p class="text-xs text-gray-500">Enviar uma mensagem para o contato</p>
            </button>
            <button onclick="addAction('assign_attendant')" class="w-full px-4 py-3 text-left border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                <i class="fas fa-user-tie mr-2 text-green-600"></i>
                <strong>Atribuir Atendente</strong>
                <p class="text-xs text-gray-500">Transferir para um atendente humano</p>
            </button>
            <button onclick="addAction('add_tag')" class="w-full px-4 py-3 text-left border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                <i class="fas fa-tag mr-2 text-yellow-600"></i>
                <strong>Adicionar Tag</strong>
                <p class="text-xs text-gray-500">Adicionar uma tag à conversa</p>
            </button>
            <button onclick="addAction('create_task')" class="w-full px-4 py-3 text-left border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                <i class="fas fa-clipboard-check mr-2 text-purple-600"></i>
                <strong>Criar Tarefa</strong>
                <p class="text-xs text-gray-500">Criar tarefa no kanban</p>
            </button>
            <button onclick="addAction('call_webhook')" class="w-full px-4 py-3 text-left border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                <i class="fas fa-plug mr-2 text-indigo-600"></i>
                <strong>Chamar Webhook</strong>
                <p class="text-xs text-gray-500">Enviar dados para URL externa</p>
            </button>
            <button onclick="addAction('update_field')" class="w-full px-4 py-3 text-left border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                <i class="fas fa-edit mr-2 text-red-600"></i>
                <strong>Atualizar Campo</strong>
                <p class="text-xs text-gray-500">Atualizar campo customizado do contato</p>
            </button>
        </div>
        
        <button onclick="closeAddActionModal()" class="w-full mt-4 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
            Cancelar
        </button>
    </div>
</div>

<script>
const flowId = <?php echo $flowId; ?>;
const automationFlowId = <?php echo $automationFlow['id'] ?? 'null'; ?>;
let actions = <?php echo json_encode($automationData['actions_config'] ?? []); ?>;

// Inicializar formulário
document.addEventListener('DOMContentLoaded', function() {
    updateTriggerConfig();
    updateAIModels();
    renderActions();
    
    // Listener para mudança de modelo
    document.getElementById('aiModel').addEventListener('change', updateModelDescription);
});

// Atualizar configuração do trigger
function updateTriggerConfig() {
    const triggerType = document.getElementById('triggerType').value;
    const container = document.getElementById('triggerConfigContainer');
    const triggerConfig = <?php echo json_encode($automationData['trigger_config'] ?? []); ?>;
    
    let html = '';
    
    switch(triggerType) {
        case 'keyword':
            html = `
                <div class="form-group">
                    <label class="form-label">Palavras-chave (separadas por vírgula)</label>
                    <textarea class="form-textarea" id="triggerKeywords" rows="3" placeholder="oi, olá, bom dia, boa tarde">${(triggerConfig.keywords || []).join(', ')}</textarea>
                    <div class="variable-hint">Digite as palavras-chave que ativarão este fluxo</div>
                </div>
            `;
            break;
            
        case 'first_message':
            html = `
                <div class="form-group">
                    <label class="form-label">Janela de tempo (segundos)</label>
                    <input type="number" class="form-input" id="triggerWindowSeconds" value="${triggerConfig.window_seconds || 300}" min="0">
                    <div class="variable-hint">Tempo para considerar como primeira mensagem</div>
                </div>
            `;
            break;
            
        case 'off_hours':
            html = `
                <div class="form-group">
                    <label class="form-label">Horário de Início</label>
                    <input type="time" class="form-input" id="triggerStartTime" value="${triggerConfig.start_time || '18:00'}">
                </div>
                <div class="form-group">
                    <label class="form-label">Horário de Fim</label>
                    <input type="time" class="form-input" id="triggerEndTime" value="${triggerConfig.end_time || '09:00'}">
                </div>
                <div class="form-group">
                    <label class="form-label">Timezone</label>
                    <input type="text" class="form-input" id="triggerTimezone" value="${triggerConfig.timezone || 'America/Sao_Paulo'}">
                </div>
            `;
            break;
            
        case 'no_response':
            html = `
                <div class="form-group">
                    <label class="form-label">Tempo sem resposta (minutos)</label>
                    <input type="number" class="form-input" id="triggerMinutes" value="${triggerConfig.minutes || 30}" min="1">
                    <div class="variable-hint">Tempo de espera antes de acionar o fluxo</div>
                </div>
            `;
            break;
    }
    
    container.innerHTML = html;
}

// Atualizar modelos de IA
function updateAIModels() {
    const provider = document.getElementById('aiProvider').value;
    const modelSelect = document.getElementById('aiModel');
    const descriptionDiv = document.getElementById('modelDescription');
    const agentConfig = <?php echo json_encode($automationData['agent_config'] ?? []); ?>;
    
    let models = [];
    let providerInfo = '';
    
    switch(provider) {
        case 'openai':
            models = [
                { value: 'gpt-4o', label: 'GPT-4o', desc: 'Mais recente - Multimodal (texto, imagem, áudio)' },
                { value: 'gpt-4o-mini', label: 'GPT-4o Mini', desc: 'Rápido e econômico - Ideal para tarefas simples' },
                { value: 'gpt-4-turbo', label: 'GPT-4 Turbo', desc: '128K contexto - Conversas longas' },
                { value: 'gpt-4', label: 'GPT-4', desc: 'Clássico - Tarefas complexas' },
                { value: 'gpt-3.5-turbo', label: 'GPT-3.5 Turbo', desc: 'Econômico - Tarefas simples' },
                { value: 'gpt-3.5-turbo-16k', label: 'GPT-3.5 Turbo 16K', desc: '16K contexto' },
                { value: 'o1-preview', label: 'O1 Preview', desc: 'Raciocínio avançado - Problemas complexos' },
                { value: 'o1-mini', label: 'O1 Mini', desc: 'Raciocínio rápido - Matemática e código' }
            ];
            providerInfo = '<i class="fas fa-link"></i> Obtenha sua API Key em: <a href="https://platform.openai.com/api-keys" target="_blank" class="text-blue-600 hover:underline">platform.openai.com</a>';
            break;
            
        case 'gemini':
            models = [
                { value: 'gemini-2.0-flash', label: 'Gemini 2.0 Flash', desc: 'Mais recente - Rápido e eficiente' },
                { value: 'gemini-1.5-pro', label: 'Gemini 1.5 Pro', desc: '1M tokens contexto - Documentos longos' },
                { value: 'gemini-1.5-flash', label: 'Gemini 1.5 Flash', desc: 'Rápido - Respostas instantâneas' },
                { value: 'gemini-pro', label: 'Gemini Pro', desc: 'Clássico - Versão anterior estável' }
            ];
            providerInfo = '<i class="fas fa-link"></i> Obtenha sua API Key em: <a href="https://makersuite.google.com/app/apikey" target="_blank" class="text-blue-600 hover:underline">Google AI Studio</a>';
            break;
            
        case 'anthropic':
            models = [
                { value: 'claude-3-7-sonnet-20250219', label: 'Claude 3.7 Sonnet', desc: 'Mais recente - Raciocínio híbrido' },
                { value: 'claude-3-5-sonnet-20241022', label: 'Claude 3.5 Sonnet', desc: 'Melhor custo-benefício' },
                { value: 'claude-3-opus-20240229', label: 'Claude 3 Opus', desc: 'Mais inteligente - Tarefas complexas' },
                { value: 'claude-3-sonnet-20240229', label: 'Claude 3 Sonnet', desc: 'Equilibrado' },
                { value: 'claude-3-haiku-20240307', label: 'Claude 3 Haiku', desc: 'Mais rápido e econômico' }
            ];
            providerInfo = '<i class="fas fa-link"></i> Obtenha sua API Key em: <a href="https://console.anthropic.com/settings/keys" target="_blank" class="text-blue-600 hover:underline">console.anthropic.com</a>';
            break;
            
        case 'groq':
            models = [
                { value: 'llama-3.3-70b-versatile', label: 'Llama 3.3 70B', desc: 'Mais recente - Versátil e rápido' },
                { value: 'llama-3.2-90b-vision-preview', label: 'Llama 3.2 90B', desc: 'Com visão - Análise de imagens' },
                { value: 'llama-3.1-70b-versatile', label: 'Llama 3.1 70B', desc: 'Versátil - Boa qualidade' },
                { value: 'llama-3.1-8b-instant', label: 'Llama 3.1 8B', desc: 'Instantâneo - Ultra rápido' },
                { value: 'mixtral-8x7b-32768', label: 'Mixtral 8x7B', desc: '32K contexto - Documentos longos' },
                { value: 'gemma-2-9b-it', label: 'Gemma 2 9B', desc: 'Google - Open source' }
            ];
            providerInfo = '<i class="fas fa-link"></i> Obtenha sua API Key em: <a href="https://console.groq.com/keys" target="_blank" class="text-blue-600 hover:underline">console.groq.com</a>';
            break;
    }
    
    modelSelect.innerHTML = models.map(m => 
        `<option value="${m.value}" ${agentConfig.model === m.value ? 'selected' : ''} data-desc="${m.desc}">${m.label}</option>`
    ).join('');
    
    // Atualizar descrição do modelo selecionado
    updateModelDescription();
    
    // Atualizar info do provider
    if (descriptionDiv) {
        descriptionDiv.innerHTML = providerInfo;
    }
}

// Atualizar descrição do modelo
function updateModelDescription() {
    const modelSelect = document.getElementById('aiModel');
    const selectedOption = modelSelect.options[modelSelect.selectedIndex];
    const desc = selectedOption?.getAttribute('data-desc') || '';
    
    const descDiv = document.getElementById('modelDescription');
    if (descDiv && desc) {
        descDiv.innerHTML = '<i class="fas fa-info-circle text-blue-600"></i> ' + desc;
    }
}

// Toggle visibilidade da API Key
function toggleApiKeyVisibility() {
    const input = document.getElementById('aiApiKey');
    const icon = document.getElementById('toggleApiKeyIcon');
    
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

// Inserir variável no textarea
function insertVariable(textareaId, variable) {
    const textarea = document.getElementById(textareaId);
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    textarea.value = text.substring(0, start) + variable + text.substring(end);
    textarea.focus();
    textarea.selectionStart = textarea.selectionEnd = start + variable.length;
}

// Modal de ações
function showAddActionModal() {
    document.getElementById('addActionModal').style.display = 'flex';
}

function closeAddActionModal() {
    document.getElementById('addActionModal').style.display = 'none';
}

// Adicionar ação
function addAction(type) {
    const action = {
        type: type,
        config: getDefaultActionConfig(type)
    };
    actions.push(action);
    renderActions();
    closeAddActionModal();
}

function getDefaultActionConfig(type) {
    const defaults = {
        send_message: { message: '' },
        assign_attendant: { attendant_id: '' },
        add_tag: { tag: '' },
        remove_tag: { tag: '' },
        create_task: { title: '', description: '', priority: 'medium' },
        call_webhook: { url: '', method: 'POST', headers: {}, body: {} },
        update_field: { field_name: '', field_value: '' }
    };
    return defaults[type] || {};
}

// Remover ação
function removeAction(index) {
    if (confirm('Remover esta ação?')) {
        actions.splice(index, 1);
        renderActions();
    }
}

// Renderizar ações
function renderActions() {
    const container = document.getElementById('actionsContainer');
    
    if (actions.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-sm">Nenhuma ação configurada. Clique em "Adicionar Ação" para começar.</p>';
        return;
    }
    
    container.innerHTML = actions.map((action, index) => {
        return `
            <div class="action-item">
                <div class="action-item-header">
                    <span class="action-item-title">${index + 1}. ${getActionLabel(action.type)}</span>
                    <button type="button" class="btn-remove-action" onclick="removeAction(${index})">
                        <i class="fas fa-trash"></i> Remover
                    </button>
                </div>
                ${renderActionConfig(action, index)}
            </div>
        `;
    }).join('');
}

function getActionLabel(type) {
    const labels = {
        send_message: 'Enviar Mensagem',
        assign_attendant: 'Atribuir Atendente',
        add_tag: 'Adicionar Tag',
        remove_tag: 'Remover Tag',
        create_task: 'Criar Tarefa',
        call_webhook: 'Chamar Webhook',
        update_field: 'Atualizar Campo'
    };
    return labels[type] || type;
}

function renderActionConfig(action, index) {
    const config = action.config;
    
    switch(action.type) {
        case 'send_message':
            return `
                <div class="form-group">
                    <label class="form-label">Mensagem</label>
                    <textarea class="form-textarea" rows="4" onchange="updateActionConfig(${index}, 'message', this.value)">${config.message || ''}</textarea>
                    <div class="variable-hint">
                        <span class="variable-tag" onclick="insertIntoAction(${index}, '{{contact_name}}')">{{contact_name}}</span>
                        <span class="variable-tag" onclick="insertIntoAction(${index}, '{{ai_response}}')">{{ai_response}}</span>
                    </div>
                </div>
            `;
            
        case 'assign_attendant':
            return `
                <div class="form-group">
                    <label class="form-label">ID do Atendente</label>
                    <input type="number" class="form-input" value="${config.attendant_id || ''}" onchange="updateActionConfig(${index}, 'attendant_id', this.value)">
                </div>
            `;
            
        case 'add_tag':
        case 'remove_tag':
            return `
                <div class="form-group">
                    <label class="form-label">Tag</label>
                    <input type="text" class="form-input" value="${config.tag || ''}" onchange="updateActionConfig(${index}, 'tag', this.value)">
                </div>
            `;
            
        case 'create_task':
            return `
                <div class="form-group">
                    <label class="form-label">Título</label>
                    <input type="text" class="form-input" value="${config.title || ''}" onchange="updateActionConfig(${index}, 'title', this.value)">
                </div>
                <div class="form-group">
                    <label class="form-label">Descrição</label>
                    <textarea class="form-textarea" rows="3" onchange="updateActionConfig(${index}, 'description', this.value)">${config.description || ''}</textarea>
                </div>
            `;
            
        case 'call_webhook':
            return `
                <div class="form-group">
                    <label class="form-label">URL</label>
                    <input type="url" class="form-input" value="${config.url || ''}" onchange="updateActionConfig(${index}, 'url', this.value)">
                </div>
                <div class="form-group">
                    <label class="form-label">Método</label>
                    <select class="form-select" onchange="updateActionConfig(${index}, 'method', this.value)">
                        <option value="POST" ${config.method === 'POST' ? 'selected' : ''}>POST</option>
                        <option value="GET" ${config.method === 'GET' ? 'selected' : ''}>GET</option>
                        <option value="PUT" ${config.method === 'PUT' ? 'selected' : ''}>PUT</option>
                    </select>
                </div>
            `;
            
        case 'update_field':
            return `
                <div class="form-group">
                    <label class="form-label">Nome do Campo</label>
                    <input type="text" class="form-input" value="${config.field_name || ''}" onchange="updateActionConfig(${index}, 'field_name', this.value)">
                </div>
                <div class="form-group">
                    <label class="form-label">Valor</label>
                    <input type="text" class="form-input" value="${config.field_value || ''}" onchange="updateActionConfig(${index}, 'field_value', this.value)">
                </div>
            `;
            
        default:
            return '<p class="text-gray-500 text-sm">Configuração não disponível</p>';
    }
}

function updateActionConfig(index, key, value) {
    actions[index].config[key] = value;
}

function insertIntoAction(index, variable) {
    // Encontrar o textarea da ação
    const actionItem = document.querySelectorAll('.action-item')[index];
    const textarea = actionItem.querySelector('textarea');
    if (textarea) {
        insertVariable(textarea.id || 'temp', variable);
    }
}

// Salvar automation flow
async function saveAutomationFlow(event) {
    event.preventDefault();
    
    // Validar API Key
    const apiKey = document.getElementById('aiApiKey').value.trim();
    if (!apiKey) {
        alert('Por favor, preencha a API Key do provider de IA.');
        document.getElementById('aiApiKey').focus();
        return;
    }
    
    // Coletar dados do formulário
    const triggerType = document.getElementById('triggerType').value;
    const triggerConfig = getTriggerConfig(triggerType);
    
    const agentConfig = {
        provider: document.getElementById('aiProvider').value,
        api_key: apiKey,
        model: document.getElementById('aiModel').value,
        prompt: document.getElementById('aiPrompt').value,
        temperature: parseFloat(document.getElementById('aiTemperature').value),
        max_tokens: parseInt(document.getElementById('aiMaxTokens').value)
    };
    
    const data = {
        action: 'save_automation',
        flow_id: flowId,
        automation_flow_id: automationFlowId,
        trigger_type: triggerType,
        trigger_config: triggerConfig,
        agent_config: agentConfig,
        actions_config: actions
    };
    
    try {
        const response = await fetch('api/bot_flows.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('✅ Automation Flow salvo com sucesso!');
            window.location.href = 'flows.php';
        } else {
            alert('❌ Erro ao salvar: ' + (result.message || 'Erro desconhecido'));
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('❌ Erro ao salvar automation flow');
    }
}

function getTriggerConfig(triggerType) {
    switch(triggerType) {
        case 'keyword':
            const keywords = document.getElementById('triggerKeywords').value;
            return { keywords: keywords.split(',').map(k => k.trim()).filter(k => k) };
            
        case 'first_message':
            return { window_seconds: parseInt(document.getElementById('triggerWindowSeconds').value) };
            
        case 'off_hours':
            return {
                start_time: document.getElementById('triggerStartTime').value,
                end_time: document.getElementById('triggerEndTime').value,
                timezone: document.getElementById('triggerTimezone').value
            };
            
        case 'no_response':
            return { minutes: parseInt(document.getElementById('triggerMinutes').value) };
            
        default:
            return {};
    }
}

// Testar automation flow
async function testAutomationFlow() {
    alert('Funcionalidade de teste em desenvolvimento. Por enquanto, salve o flow e teste via webhook real.');
}
</script>
