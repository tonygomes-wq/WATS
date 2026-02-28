<?php
$page_title = 'Disparo em Massa';
require_once 'includes/header_spa.php';
require_once 'templates_data.php';

$userId = $_SESSION['user_id'];

// Listar categorias do usuário
$stmt = $pdo->prepare("
    SELECT c.*, COUNT(cc.contact_id) as contact_count
    FROM categories c
    LEFT JOIN contact_categories cc ON c.id = cc.category_id
    WHERE c.user_id = ?
    GROUP BY c.id
    ORDER BY c.name
");
$stmt->execute([$userId]);
$categories = $stmt->fetchAll();

// Listar todos os contatos
$stmt = $pdo->prepare("SELECT * FROM contacts WHERE user_id = ? ORDER BY name, phone");
$stmt->execute([$userId]);
$allContacts = $stmt->fetchAll();

// Verificar se instância está configurada (sem alertas automáticos)
$stmt = $pdo->prepare("SELECT evolution_instance, evolution_token FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userInstance = $stmt->fetch();
$hasInstance = !empty($userInstance['evolution_instance']) && !empty($userInstance['evolution_token']);

// Modelos do usuário
$userTemplates = [];
$templatesByCategory = [];
try {
    $stmt = $pdo->prepare("SELECT mt.*, mtc.name as category_name, mtc.color as category_color
        FROM message_templates mt
        LEFT JOIN message_template_categories mtc ON mt.category_id = mtc.id
        WHERE mt.user_id = ?
        ORDER BY mtc.name, mt.name");
    $stmt->execute([$userId]);
    $userTemplates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($userTemplates as $template) {
        $catName = $template['category_name'] ?: 'Sem Categoria';
        if (!isset($templatesByCategory[$catName])) {
            $templatesByCategory[$catName] = [
                'color' => $template['category_color'] ?: '#6B7280',
                'templates' => []
            ];
        }
        $templatesByCategory[$catName]['templates'][] = $template;
    }
} catch (PDOException $e) {
    $userTemplates = [];
    $templatesByCategory = [];
}

$systemTemplateList = [];
foreach ($systemTemplates as $categoryName => $categoryData) {
    foreach ($categoryData['templates'] as $template) {
        $systemTemplateList[] = [
            'name' => $template['name'],
            'content' => $template['content'],
            'variables' => $template['variables'],
            'category' => $categoryName,
            'color' => $categoryData['color'],
            'source' => 'system'
        ];
    }
}

$userTemplateList = [];
foreach ($templatesByCategory as $categoryName => $categoryData) {
    foreach ($categoryData['templates'] as $template) {
        $userTemplateList[] = [
            'id' => $template['id'],
            'name' => $template['name'],
            'content' => $template['content'],
            'variables' => json_decode($template['variables'] ?? '[]', true) ?: [],
            'category' => $categoryName,
            'color' => $categoryData['color'] ?? '#6B7280',
            'source' => 'user'
        ];
    }
}
?>

<!-- Estilos refinados do skill.md -->
<style>
    .dispatch-container {
        padding: var(--space-6);
    }
    
    .dispatch-card {
        background: var(--bg-card);
        border: 0.5px solid var(--border);
        border-radius: var(--radius-lg);
        padding: var(--space-6);
        transition: border-color var(--transition-fast);
    }
    
    .dispatch-card:hover {
        border-color: var(--border-emphasis);
    }
    
    .dispatch-title {
        font-size: 24px;
        font-weight: 600;
        letter-spacing: -0.02em;
        color: var(--text-primary);
        margin-bottom: var(--space-6);
        display: flex;
        align-items: center;
        gap: var(--space-3);
    }
    
    .dispatch-title i {
        color: var(--accent-primary);
        font-size: 20px;
    }
    
    .dispatch-section {
        margin-bottom: var(--space-6);
    }
    
    .dispatch-label {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: var(--space-2);
        display: block;
        letter-spacing: -0.01em;
    }
    
    .dispatch-select {
        width: 100%;
        padding: var(--space-3) var(--space-4);
        border: 0.5px solid var(--border);
        border-radius: var(--radius-md);
        font-size: 13px;
        color: var(--text-primary);
        background: var(--bg-card);
        transition: all var(--transition-fast);
    }
    
    .dispatch-select:hover {
        border-color: var(--border-emphasis);
    }
    
    .dispatch-select:focus {
        outline: none;
        border-color: var(--accent-primary);
        box-shadow: 0 0 0 3px var(--accent-subtle);
    }
    
    .interval-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: var(--space-4);
    }
    
    .interval-option {
        position: relative;
        cursor: pointer;
    }
    
    .interval-option input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    
    .interval-card {
        border: 0.5px solid var(--border);
        border-radius: var(--radius-md);
        padding: var(--space-4);
        text-align: center;
        transition: all var(--transition-fast);
        background: var(--bg-card);
    }
    
    .interval-card:hover {
        border-color: var(--border-emphasis);
        transform: translateY(-2px);
    }
    
    .interval-option input:checked + .interval-card {
        border-color: var(--accent-primary);
        background: var(--accent-subtle);
    }
    
    .interval-value {
        font-size: 32px;
        font-weight: 700;
        font-family: 'SF Mono', 'Monaco', 'Courier New', monospace;
        font-variant-numeric: tabular-nums;
        color: var(--text-primary);
        margin-bottom: var(--space-1);
    }
    
    .interval-option input:checked + .interval-card .interval-value {
        color: var(--accent-primary);
    }
    
    .interval-label {
        font-size: 12px;
        font-weight: 500;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .interval-option input:checked + .interval-card .interval-label {
        color: var(--accent-primary);
    }
    
    .dispatch-info {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        padding: var(--space-3);
        background: var(--bg-body);
        border: 0.5px solid var(--border-subtle);
        border-radius: var(--radius-md);
        font-size: 12px;
        color: var(--text-secondary);
        margin-top: var(--space-3);
    }
    
    .dispatch-info i {
        color: var(--accent-primary);
    }
    
    .dispatch-warning {
        display: flex;
        align-items: center;
        gap: var(--space-4);
        padding: var(--space-4);
        background: rgba(234, 179, 8, 0.08);
        border: 0.5px solid rgba(234, 179, 8, 0.2);
        border-radius: var(--radius-md);
        margin-bottom: var(--space-6);
    }
    
    .dispatch-warning i {
        color: #eab308;
        font-size: 20px;
    }
    
    .dispatch-warning-content h3 {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: var(--space-1);
    }
    
    .dispatch-warning-content p {
        font-size: 12px;
        color: var(--text-secondary);
    }
    
    .dispatch-warning-btn {
        padding: var(--space-2) var(--space-4);
        background: #eab308;
        color: white;
        border: none;
        border-radius: var(--radius-md);
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all var(--transition-fast);
        white-space: nowrap;
    }
    
    .dispatch-warning-btn:hover {
        background: #ca8a04;
        transform: translateY(-1px);
    }
    
    .dispatch-radio-group {
        display: flex;
        gap: var(--space-4);
    }
    
    .dispatch-radio-label {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        cursor: pointer;
        font-size: 13px;
        color: var(--text-primary);
        font-weight: 500;
    }
    
    .dispatch-radio-label input[type="radio"] {
        width: 16px;
        height: 16px;
        accent-color: var(--accent-primary);
    }
    
    /* Correção para botões de ícones - remover fundo */
    .message-editor-area button[type="button"] {
        background: none !important;
        border: none !important;
        padding: 4px !important;
    }
    
    .message-editor-area button[type="button"]:hover {
        background: none !important;
    }
    
    /* Correção para textarea - evitar fundo branco no hover */
    #messageText {
        background: transparent !important;
    }
    
    #messageText:hover,
    #messageText:focus {
        background: transparent !important;
    }
    
    /* Correção para placeholder - remover hover branco */
    #messagePlaceholder:hover {
        background: transparent !important;
    }
    
    /* Remover efeito hover branco da lista de contatos */
    .contact-item:hover {
        background: transparent !important;
    }
</style>

<div class="dispatch-container">
<div class="dispatch-card">
    <h1 class="dispatch-title">
        <i class="fas fa-rocket"></i>Disparo em Massa
    </h1>
    
    <?php if (!$hasInstance): ?>
    <div class="dispatch-warning">
        <i class="fas fa-exclamation-triangle"></i>
        <div class="dispatch-warning-content" style="flex: 1;">
            <h3>Instância WhatsApp Não Configurada</h3>
            <p>Para enviar mensagens, você precisa configurar sua instância WhatsApp primeiro.</p>
        </div>
        <a href="/my_instance.php" class="dispatch-warning-btn">
            <i class="fas fa-cog" style="margin-right: 8px;"></i>Configurar Agora
        </a>
    </div>
    <?php endif; ?>
    
    <div class="dispatch-section">
        <label class="dispatch-label">Modo de Disparo</label>
        <div class="dispatch-radio-group">
            <label class="dispatch-radio-label">
                <input type="radio" name="dispatch_mode" value="category" checked onchange="toggleDispatchMode()">
                <span>Por Categoria</span>
            </label>
            <label class="dispatch-radio-label">
                <input type="radio" name="dispatch_mode" value="individual" onchange="toggleDispatchMode()">
                <span>Individual</span>
            </label>
        </div>
    </div>
    
    <div id="categoryMode" class="dispatch-section">
        <label class="dispatch-label">Selecione a Categoria</label>
        <select id="selectedCategory" class="dispatch-select" onchange="loadCategoryContacts()">
            <option value="">Selecione uma categoria...</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?php echo $cat['id']; ?>" data-count="<?php echo $cat['contact_count']; ?>">
                <?php echo htmlspecialchars($cat['name']); ?> (<?php echo $cat['contact_count']; ?> contatos)
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="dispatch-section">
        <label class="dispatch-label">
            <i class="fas fa-clock" style="margin-right: 8px;"></i>Intervalo entre Mensagens
        </label>
        <div class="interval-grid">
            <label class="interval-option">
                <input type="radio" name="message_interval" value="10" checked>
                <div class="interval-card">
                    <div class="interval-value">10s</div>
                    <div class="interval-label">Rápido</div>
                </div>
            </label>
            
            <label class="interval-option">
                <input type="radio" name="message_interval" value="20">
                <div class="interval-card">
                    <div class="interval-value">20s</div>
                    <div class="interval-label">Moderado</div>
                </div>
            </label>
            
            <label class="interval-option">
                <input type="radio" name="message_interval" value="30">
                <div class="interval-card">
                    <div class="interval-value">30s</div>
                    <div class="interval-label">Seguro</div>
                </div>
            </label>
        </div>
        <div class="dispatch-info">
            <i class="fas fa-info-circle"></i>
            <span>Intervalos maiores reduzem o risco de bloqueio pelo WhatsApp</span>
        </div>
    </div>
    
    <!-- Seleção Individual -->
    <div id="individualMode" class="mb-6 hidden">
        <div class="flex items-center justify-between mb-4">
            <label class="block text-gray-700 text-sm font-bold">Selecione os Contatos</label>
            <div class="flex gap-2">
                <button type="button" onclick="selectAllContacts()" class="text-xs bg-blue-100 hover:bg-blue-200 text-blue-800 px-3 py-1 rounded">
                    <i class="fas fa-check-double mr-1"></i>Selecionar Todos
                </button>
                <button type="button" onclick="clearAllContacts()" class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-800 px-3 py-1 rounded">
                    <i class="fas fa-times mr-1"></i>Limpar Seleção
                </button>
            </div>
        </div>
        
        <!-- Barra de pesquisa -->
        <div class="mb-4">
            <div class="relative">
                <input 
                    type="text" 
                    id="contactSearch" 
                    placeholder="Pesquisar contatos por nome ou telefone..." 
                    class="w-full px-4 py-2 pl-10 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                    onkeyup="filterContacts()"
                >
                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
            </div>
        </div>
        
        <!-- Lista de contatos com checkboxes -->
        <div class="border rounded-lg max-h-60 overflow-y-auto bg-white">
            <?php if (empty($allContacts)): ?>
            <div class="p-4 text-center text-gray-500">
                <i class="fas fa-users text-2xl mb-2"></i>
                <p>Nenhum contato cadastrado</p>
                <a href="/contacts.php" class="text-green-600 hover:text-green-700 text-sm">
                    <i class="fas fa-plus mr-1"></i>Adicionar contatos
                </a>
            </div>
            <?php else: ?>
            <?php foreach ($allContacts as $contact): ?>
            <div class="contact-item border-b border-gray-100 p-3 cursor-pointer" onclick="toggleContact(<?php echo $contact['id']; ?>)">
                <label class="flex items-center cursor-pointer">
                    <input 
                        type="checkbox" 
                        class="contact-checkbox mr-3" 
                        value="<?php echo $contact['id']; ?>"
                        data-name="<?php echo htmlspecialchars($contact['name'] ?: 'Sem nome'); ?>"
                        data-phone="<?php echo $contact['phone']; ?>"
                        onchange="updateSelectedContacts()"
                    >
                    <div class="flex-1">
                        <div class="font-medium text-gray-800 contact-name">
                            <?php echo htmlspecialchars($contact['name'] ?: 'Sem nome'); ?>
                        </div>
                        <div class="text-sm text-gray-600 contact-phone">
                            <?php echo $contact['phone']; ?>
                        </div>
                    </div>
                    <i class="fas fa-user text-gray-400"></i>
                </label>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Contador de selecionados -->
        <div class="mt-2 text-sm text-gray-600">
            <span id="selectedCount">0</span> contato(s) selecionado(s)
        </div>
    </div>
    
    <!-- Lista de Contatos Selecionados -->
    <div id="contactsList" class="mb-6 hidden">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="font-bold text-gray-800 mb-2">
                <i class="fas fa-users mr-2"></i>Contatos Selecionados: <span id="contactCount">0</span>
            </h3>
            <div id="contactsPreview" class="max-h-40 overflow-y-auto text-sm text-gray-700"></div>
        </div>
    </div>
    
    <!-- Editor de Mensagem -->
    <div class="mb-6">
        <!-- WhatsApp Style Message Box -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
            <!-- Header -->
            <div class="flex items-center justify-between px-4 py-3 bg-gray-50 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-800">Mensagem A</h3>
                <button type="button" onclick="clearMessage()" class="text-red-500 hover:text-red-700 transition-colors" title="Limpar mensagem">
                    <i class="fas fa-trash text-sm"></i>
                </button>
            </div>
            
            <!-- Message Area -->
            <div class="relative min-h-[300px] flex flex-col message-editor-area">
                <!-- Empty State / Placeholder -->
                <div id="messagePlaceholder" class="flex-1 flex items-center justify-center text-center p-8 cursor-pointer transition-colors">
                    <div>
                        <p class="text-gray-600 text-sm mb-2">Escreva a mensagem a ser enviada.</p>
                        <div class="text-gray-400">
                            <i class="fas fa-arrow-down text-lg"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Textarea (hidden initially) -->
                <textarea 
                    id="messageText" 
                    class="hidden flex-1 w-full p-4 border-none outline-none resize-none text-sm"
                    style="background: transparent !important;"
                    placeholder="Digite sua mensagem..."
                    rows="10"
                ></textarea>
            </div>
            
            <!-- Footer with tools -->
            <div class="flex items-center justify-between px-4 py-3 bg-gray-50 border-t border-gray-200">
                <!-- Left side tools -->
                <div class="flex items-center space-x-3">
                    <button type="button" onclick="insertMacro('{nome}')" class="text-gray-600 hover:text-blue-600 transition-colors p-1" title="Inserir nome do contato" style="background: none !important; border: none !important;">
                        <i class="fas fa-user text-lg"></i>
                    </button>
                    <button type="button" onclick="insertMacro('{telefone}')" class="text-gray-600 hover:text-blue-600 transition-colors p-1" title="Inserir telefone do contato" style="background: none !important; border: none !important;">
                        <i class="fas fa-phone text-lg"></i>
                    </button>
                    <button type="button" onclick="openTemplateModal('system')" class="text-gray-600 hover:text-blue-600 transition-colors p-1" title="Inserir modelo de mensagem" style="background: none !important; border: none !important;">
                        <i class="fas fa-envelope-open-text text-lg"></i>
                    </button>
                    <button type="button" onclick="showAttachmentOptions()" class="text-gray-600 hover:text-blue-600 transition-colors p-1" title="Anexar arquivo" style="background: none !important; border: none !important;">
                        <i class="fas fa-paperclip text-lg"></i>
                    </button>
                    <button type="button" onclick="showEmojiPicker()" class="text-gray-600 hover:text-blue-600 transition-colors p-1" title="Inserir emoji" style="background: none !important; border: none !important;">
                        <i class="fas fa-smile text-lg"></i>
                    </button>
                    <button type="button" onclick="selectFileByType('image/*')" class="text-gray-600 hover:text-blue-600 transition-colors p-1" title="Tirar foto ou selecionar da galeria" style="background: none !important; border: none !important;">
                        <i class="fas fa-camera text-lg"></i>
                    </button>
                    <button type="button" onclick="selectFileByType('image/*')" class="text-gray-600 hover:text-blue-600 transition-colors p-1" title="Selecionar imagem" style="background: none !important; border: none !important;">
                        <i class="fas fa-image text-lg"></i>
                    </button>
                    <button type="button" onclick="insertLinkPrompt()" class="text-gray-600 hover:text-blue-600 transition-colors p-1" title="Inserir link/URL" style="background: none !important; border: none !important;">
                        <i class="fas fa-link text-lg"></i>
                    </button>
                </div>
                
                <!-- Right side -->
                <div class="flex items-center space-x-3">
                    <span class="text-xs text-gray-500">Digite @ para utilizar os campos</span>
                    <button type="button" onclick="generateWithAI()" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center transition-colors">
                        <i class="fas fa-magic mr-2"></i>Gerar com IA
                    </button>
                    <button type="button" onclick="startVoiceRecording()" class="text-gray-600 hover:text-red-600 transition-colors p-1" title="Gravar áudio (em breve)" style="background: none !important; border: none !important;">
                        <i class="fas fa-microphone text-lg"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <p class="text-xs text-gray-500 mt-2">
            <i class="fas fa-info-circle"></i> Use as macros {nome} e {telefone} para personalizar a mensagem
        </p>
    </div>
    
    <!-- Preview da Mensagem -->
    <div id="messagePreview" class="mb-6 hidden">
        <label class="block text-gray-700 text-sm font-bold mb-2">Preview da Mensagem</label>
        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <div class="bg-white rounded-lg p-3 shadow-sm">
                <p id="previewText" class="text-sm whitespace-pre-wrap"></p>
            </div>
        </div>
    </div>
    
    <!-- Botão de Disparo -->
    <div class="flex justify-center">
        <button 
            id="startDispatchBtn"
            onclick="startDispatch()" 
            class="bg-green-600 hover:bg-green-700 text-white font-bold px-8 py-3 rounded-lg transition disabled:bg-gray-400 disabled:cursor-not-allowed"
            disabled
        >
            <i class="fas fa-rocket mr-2"></i>Iniciar Disparo
        </button>
    </div>
</div>

<!-- Fila de Disparo -->
<div id="dispatchQueue" class="hidden bg-white rounded-lg shadow-lg p-6 mt-6">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">
        <i class="fas fa-list mr-2 text-green-600"></i>Fila de Disparo
    </h2>
    
    <div class="mb-4">
        <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-semibold">Progresso Geral</span>
            <span class="text-sm font-semibold"><span id="progressCount">0</span> / <span id="totalCount">0</span></span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-4">
            <div id="overallProgress" class="bg-green-600 h-4 rounded-full transition-all duration-300" style="width: 0%"></div>
        </div>
    </div>
    
    <div id="queueItems" class="space-y-3 max-h-96 overflow-y-auto"></div>
    
    <div id="completionMessage" class="hidden text-center py-8">
        <i class="fas fa-check-circle text-6xl text-green-600 mb-4"></i>
        <h3 class="text-2xl font-bold text-gray-800 mb-2">Parabéns! 🎉</h3>
        <p class="text-gray-600 mb-6">Todas as mensagens foram enviadas com sucesso!</p>
        
        <!-- Estatísticas -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6 max-w-3xl mx-auto">
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="text-3xl font-bold text-green-600" id="stat_success">0</div>
                <div class="text-sm text-gray-600">Enviadas</div>
            </div>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="text-3xl font-bold text-red-600" id="stat_failed">0</div>
                <div class="text-sm text-gray-600">Falhas</div>
            </div>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="text-3xl font-bold text-blue-600" id="stat_total">0</div>
                <div class="text-sm text-gray-600">Total</div>
            </div>
            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                <div class="text-3xl font-bold text-purple-600" id="stat_duration">0s</div>
                <div class="text-sm text-gray-600">Duração</div>
            </div>
        </div>
        
        <div class="flex gap-3 justify-center flex-wrap">
            <button onclick="showDetailedReport()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium">
                <i class="fas fa-chart-bar mr-2"></i>Ver Relatório
            </button>
            <button onclick="resetDispatch()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium">
                <i class="fas fa-redo mr-2"></i>Novo Disparo
            </button>
        </div>
    </div>
</div>

<!-- Modal de Modelos de Mensagem -->
<div id="templateModal" class="hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm z-50 flex items-center justify-center p-4" onclick="closeTemplateModal(event)">
    <div class="bg-white rounded-2xl shadow-2xl max-w-5xl w-full max-h-[90vh] flex flex-col overflow-hidden" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-green-600 to-green-500 text-white">
            <div>
                <p class="text-sm uppercase tracking-wide opacity-80">Modelos de Mensagem</p>
                <h2 class="text-2xl font-bold">Escolha um template pronto</h2>
            </div>
            <div class="flex items-center space-x-2">
                <button data-template-tab="system" onclick="switchTemplateTab('system')" class="template-tab px-4 py-2 rounded-full text-sm font-semibold border border-white/50 text-white">Sistema</button>
                <button data-template-tab="user" onclick="switchTemplateTab('user')" class="template-tab px-4 py-2 rounded-full text-sm font-semibold border border-transparent text-white/70">Meus modelos (<?php echo count($userTemplateList); ?>)</button>
                <button data-close-modal class="text-white hover:text-gray-200 text-2xl leading-none">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="overflow-y-auto flex-1 bg-gray-50" id="templateListWrapper">
            <div id="templateList" class="p-6"></div>
        </div>
    </div>
</div>

<script>
let selectedContacts = [];
let currentMessage = '';
let isDispatching = false;
let attachedFile = null;
let dispatchStats = {
    success: 0,
    failed: 0,
    total: 0,
    duration: 0,
    startTime: null
};
let dispatchResults = [];
const templateData = {
    system: <?php echo json_encode($systemTemplateList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    user: <?php echo json_encode($userTemplateList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
};
let currentTemplateTab = 'system';

function toggleDispatchMode() {
    const mode = document.querySelector('input[name="dispatch_mode"]:checked').value;
    const categoryMode = document.getElementById('categoryMode');
    const individualMode = document.getElementById('individualMode');
    
    if (mode === 'category') {
        categoryMode.classList.remove('hidden');
        individualMode.classList.add('hidden');
        loadCategoryContacts();
    } else {
        categoryMode.classList.add('hidden');
        individualMode.classList.remove('hidden');
        selectedContacts = [];
        updateSelectedContacts();
        updateDispatchButton();
    }
}

function loadCategoryContacts() {
    const categoryId = document.getElementById('selectedCategory').value;
    
    if (!categoryId) {
        document.getElementById('contactsList').classList.add('hidden');
        selectedContacts = [];
        updateDispatchButton();
        return;
    }
    
    fetch(`/api/get_category_contacts.php?category_id=${categoryId}`)
        .then(response => response.json())
        .then(data => {
            selectedContacts = data.contacts || [];
            displayContacts();
            updateDispatchButton();
        })
        .catch(error => {
            console.error('Erro ao carregar contatos:', error);
            alert('Erro ao carregar contatos da categoria');
        });
}

function displayContacts() {
    const contactsList = document.getElementById('contactsList');
    const contactsPreview = document.getElementById('contactsPreview');
    const contactCount = document.getElementById('contactCount');
    
    if (selectedContacts.length === 0) {
        contactsList.classList.add('hidden');
        return;
    }
    
    contactsList.classList.remove('hidden');
    contactCount.textContent = selectedContacts.length;
    
    contactsPreview.innerHTML = selectedContacts.map(contact => 
        `<div class="py-1"><i class="fas fa-user mr-2"></i>${contact.name || 'Sem nome'} - ${contact.phone}</div>`
    ).join('');
}

function insertMacro(macro) {
    const textarea = document.getElementById('messageText');
    
    // Mostrar textarea se estiver escondido
    showMessageTextarea();
    
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    
    textarea.value = text.substring(0, start) + macro + text.substring(end);
    textarea.focus();
    textarea.selectionStart = textarea.selectionEnd = start + macro.length;
    
    updatePreview();
}

// Função para mostrar o textarea e esconder o placeholder
function showMessageTextarea() {
    const placeholder = document.getElementById('messagePlaceholder');
    const textarea = document.getElementById('messageText');
    
    placeholder.classList.add('hidden');
    textarea.classList.remove('hidden');
    textarea.focus();
}

// Função para mostrar o placeholder se textarea estiver vazio
function checkMessageEmpty() {
    const placeholder = document.getElementById('messagePlaceholder');
    const textarea = document.getElementById('messageText');
    
    if (textarea.value.trim() === '') {
        placeholder.classList.remove('hidden');
        textarea.classList.add('hidden');
    }
}

// Função para mostrar notificações
function showMessage(type, message) {
    // Criar notificação toast simples
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 max-w-sm ${
        type === 'success' ? 'bg-green-500 text-white' :
        type === 'error' ? 'bg-red-500 text-white' :
        type === 'warning' ? 'bg-yellow-500 text-white' :
        'bg-blue-500 text-white'
    }`;
    toast.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${
                type === 'success' ? 'fa-check-circle' :
                type === 'error' ? 'fa-exclamation-circle' :
                type === 'warning' ? 'fa-exclamation-triangle' :
                'fa-info-circle'
            } mr-2"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Remover após 4 segundos
    setTimeout(() => {
        toast.remove();
    }, 4000);
}

// Função para mostrar opções de anexo
function showAttachmentOptions() {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
    modal.onclick = (e) => { if (e.target === modal) modal.remove(); };
    
    modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-paperclip mr-2 text-blue-600"></i>Anexar Arquivo
                </h3>
                <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Upload Area -->
            <div class="mb-6">
                <div id="dropZone" class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-400 transition-colors cursor-pointer" 
                     onclick="document.getElementById('fileInput').click()"
                     ondragover="handleDragOver(event)" 
                     ondragenter="handleDragEnter(event)"
                     ondragleave="handleDragLeave(event)"
                     ondrop="handleDrop(event)">
                    <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                    <p class="text-gray-600 mb-1">Clique para selecionar arquivo</p>
                    <p class="text-xs text-gray-500">Ou arraste e solte aqui</p>
                </div>
                <input type="file" id="fileInput" class="hidden" onchange="handleFileSelect(this)" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.txt">
            </div>
            
            <!-- Quick Options -->
            <div class="space-y-3">
                <h4 class="text-sm font-medium text-gray-700 mb-3">Opções Rápidas:</h4>
                
                <button onclick="selectFileByType('.pdf,.doc,.docx,.txt,.xls,.xlsx'); this.closest('.fixed').remove();" 
                        class="w-full flex items-center p-3 text-left hover:bg-gray-50 rounded-lg transition-colors">
                    <i class="fas fa-file-alt text-blue-600 mr-3"></i>
                    <div>
                        <div class="font-medium">Documento</div>
                        <div class="text-xs text-gray-500">PDF, DOC, TXT</div>
                    </div>
                </button>
                
                <button onclick="selectFileByType('image/*'); this.closest('.fixed').remove();" 
                        class="w-full flex items-center p-3 text-left hover:bg-gray-50 rounded-lg transition-colors">
                    <i class="fas fa-image text-green-600 mr-3"></i>
                    <div>
                        <div class="font-medium">Imagem</div>
                        <div class="text-xs text-gray-500">JPG, PNG, GIF</div>
                    </div>
                </button>
                
                <button onclick="selectFileByType('video/*'); this.closest('.fixed').remove();" 
                        class="w-full flex items-center p-3 text-left hover:bg-gray-50 rounded-lg transition-colors">
                    <i class="fas fa-video text-red-600 mr-3"></i>
                    <div>
                        <div class="font-medium">Vídeo</div>
                        <div class="text-xs text-gray-500">MP4, AVI, MOV</div>
                    </div>
                </button>
                
                <button onclick="selectFileByType('audio/*'); this.closest('.fixed').remove();" 
                        class="w-full flex items-center p-3 text-left hover:bg-gray-50 rounded-lg transition-colors">
                    <i class="fas fa-music text-purple-600 mr-3"></i>
                    <div>
                        <div class="font-medium">Áudio</div>
                        <div class="text-xs text-gray-500">MP3, WAV, OGG</div>
                    </div>
                </button>
                
                <button onclick="showLocationPicker(); this.closest('.fixed').remove();" 
                        class="w-full flex items-center p-3 text-left hover:bg-gray-50 rounded-lg transition-colors">
                    <i class="fas fa-map-marker-alt text-orange-600 mr-3"></i>
                    <div>
                        <div class="font-medium">Localização</div>
                        <div class="text-xs text-gray-500">Compartilhar endereço</div>
                    </div>
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}

// Função para mostrar seletor de emoji
function showEmojiPicker() {
    const emojis = ['😀', '😊', '😍', '🤔', '😢', '😡', '👍', '👎', '❤️', '🔥', '💯', '🎉', '🚀', '💪', '🙏', '✅', '❌', '⚠️', '📱', '💰'];
    
    // Criar modal simples para emojis
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Escolha um emoji</h3>
                <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="grid grid-cols-5 gap-2">
                ${emojis.map(emoji => `
                    <button onclick="insertEmoji('${emoji}'); this.closest('.fixed').remove();" 
                            class="text-2xl p-2 hover:bg-gray-100 rounded transition-colors">
                        ${emoji}
                    </button>
                `).join('')}
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}

// Função para inserir emoji
function insertEmoji(emoji) {
    showMessageTextarea();
    const textarea = document.getElementById('messageText');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    
    textarea.value = text.substring(0, start) + emoji + text.substring(end);
    textarea.focus();
    textarea.selectionStart = textarea.selectionEnd = start + emoji.length;
    updatePreview();
}

// Função para inserir templates
function insertTemplate(type) {
    let template = '';
    
    switch(type) {
        case 'camera':
            template = '📸 Confira essa foto que tirei para você!';
            break;
        case 'image':
            template = '🖼️ Veja essa imagem interessante:';
            break;
        case 'link':
            template = '🔗 Acesse este link: https://';
            break;
    }
    
    if (template) {
        showMessageTextarea();
        const textarea = document.getElementById('messageText');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;
        
        // Adicionar quebra de linha se já houver texto
        const prefix = text.trim() ? '\n\n' : '';
        const fullTemplate = prefix + template;
        
        textarea.value = text.substring(0, start) + fullTemplate + text.substring(end);
        textarea.focus();
        textarea.selectionStart = textarea.selectionEnd = start + fullTemplate.length;
        updatePreview();
    }
}

function openTemplateModal(source = 'system') {
    currentTemplateTab = source;
    document.getElementById('templateModal').classList.remove('hidden');
    switchTemplateTab(source);
    document.getElementById('templateModal').dataset.open = 'true';
}

function closeTemplateModal(event) {
    if (!event || event.target.id === 'templateModal' || event.target.closest('[data-close-modal]')) {
        document.getElementById('templateModal').classList.add('hidden');
        document.getElementById('templateModal').dataset.open = 'false';
    }
}

function renderTemplateList(source) {
    const listWrapper = document.getElementById('templateList');
    const templates = templateData[source] || [];
    listWrapper.innerHTML = '';

    if (!templates.length) {
        listWrapper.innerHTML = '<div class="text-center text-gray-500 py-10">Nenhum modelo disponível.</div>';
        return;
    }

    const grouped = templates.reduce((acc, tpl) => {
        acc[tpl.category] = acc[tpl.category] || { color: tpl.color || '#6B7280', items: [] };
        acc[tpl.category].items.push(tpl);
        return acc;
    }, {});

    Object.keys(grouped).forEach(category => {
        const group = grouped[category];
        const section = document.createElement('div');
        section.className = 'mb-6';
        section.innerHTML = `
            <div class="flex items-center mb-3">
                <div class="w-1 h-6 rounded-full mr-3" style="background:${group.color}"></div>
                <h3 class="text-lg font-semibold text-gray-800">${category}</h3>
                <span class="ml-3 text-xs text-gray-500">${group.items.length} modelos</span>
            </div>
        `;

        const grid = document.createElement('div');
        grid.className = 'grid grid-cols-1 md:grid-cols-2 gap-4';
        group.items.forEach(item => {
            const card = document.createElement('div');
            card.className = 'border border-gray-200 rounded-lg p-4 bg-white shadow-sm hover:border-green-500 transition';
            const variables = (item.variables || []).map(v => `<span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-mono">{{${v}}}</span>`).join(' ');
            card.innerHTML = `
                <div class="flex items-start justify-between mb-2">
                    <div>
                        <p class="font-semibold text-gray-800">${item.name}</p>
                        <p class="text-xs text-gray-500">${item.source === 'system' ? 'Sistema' : 'Meu modelo'}</p>
                    </div>
                    <button class="text-gray-400 hover:text-green-500" title="Visualizar" onclick='viewTemplateFromModal(${JSON.stringify(item)})'>
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <p class="text-sm text-gray-600 mb-3" style="display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;">${item.content.replace(/\n/g, '<br>')}</p>
                <div class="flex flex-wrap gap-1 mb-3">${variables}</div>
                <button class="w-full bg-green-600 hover:bg-green-700 text-white rounded-lg py-2 text-sm font-medium" onclick='applyTemplateFromModal(${JSON.stringify(item)})'>
                    <i class="fas fa-copy mr-1"></i>Usar modelo
                </button>
            `;
            grid.appendChild(card);
        });
        section.appendChild(grid);
        listWrapper.appendChild(section);
    });
}

function switchTemplateTab(tab) {
    currentTemplateTab = tab;
    document.querySelectorAll('[data-template-tab]').forEach(btn => {
        const isActive = btn.dataset.templateTab === tab;
        btn.classList.toggle('border-white/50', isActive && btn.classList.contains('template-tab'));
        btn.classList.toggle('border-white/10', !isActive && btn.classList.contains('template-tab'));
        btn.classList.toggle('text-white', isActive);
        btn.classList.toggle('text-white/70', !isActive);
    });
    renderTemplateList(tab);
}

function applyTemplateFromModal(template) {
    showMessageTextarea();
    const textarea = document.getElementById('messageText');
    textarea.value = template.content;
    textarea.focus();
    updatePreview();
    closeTemplateModal();
    showMessage('success', 'Modelo aplicado à mensagem.');
}

function viewTemplateFromModal(template) {
    const preview = document.createElement('div');
    preview.className = 'fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 p-4 template-preview';
    preview.innerHTML = `
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-green-600 to-green-500 text-white flex items-center justify-between">
                <div>
                    <p class="text-xs uppercase tracking-wide opacity-80">Visualizar modelo</p>
                    <h3 class="text-xl font-bold">${template.name}</h3>
                </div>
                <button class="text-white text-2xl" onclick="closeTemplatePreview(this)">&times;</button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <p class="text-sm font-semibold text-gray-600 mb-2">Conteúdo</p>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-gray-800 whitespace-pre-wrap">${template.content}</div>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-600 mb-2">Variáveis</p>
                    <div class="flex flex-wrap gap-2">
                        ${(template.variables || []).map(v => `<span class=\"px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-mono\">{{${v}}}</span>`).join('') || '<span class="text-gray-400 text-sm">Nenhuma variável</span>'}
                    </div>
                </div>
            </div>
        </div>`;
    document.body.appendChild(preview);
}

function closeTemplatePreview(button) {
    const preview = button ? button.closest('.template-preview') : document.querySelector('.template-preview');
    if (preview) preview.remove();
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        const modal = document.getElementById('templateModal');
        if (modal.dataset.open === 'true') {
            closeTemplateModal();
        }
        closeTemplatePreview();
    }
});

// Função para gerar mensagem com IA (simulada)
function generateWithAI() {
    const templates = [
        // Templates comerciais
        "Olá {nome}! 👋\n\nEspero que esteja tudo bem com você!\n\nEntrando em contato para compartilhar uma oportunidade especial que pode te interessar.\n\nPodemos conversar?",
        
        "Oi {nome}! 😊\n\nTudo bem?\n\nTenho uma novidade interessante para te contar e lembrei de você.\n\nTem um minutinho para conversarmos?",
        
        "Olá {nome}! ✨\n\nComo você está?\n\nEstou entrando em contato porque tenho algo que pode ser muito útil para você.\n\nVamos bater um papo?",
        
        // Templates informativos
        "Oi {nome}! 📢\n\nPassando para te informar sobre uma novidade importante.\n\nEspero que seja do seu interesse!\n\nQualquer dúvida, me chama! 😉",
        
        "Olá {nome}! 🎯\n\nVi que você pode se interessar por esta informação.\n\nDá uma olhada e me fala o que achou!\n\nAbraços! 🤗",
        
        // Templates de relacionamento
        "Oi {nome}! 💙\n\nHá um tempo que não conversamos!\n\nComo estão as coisas por aí?\n\nEspero que tudo esteja correndo bem! 😊",
        
        "Olá {nome}! 🌟\n\nPassando para dar um oi e saber como você está.\n\nSempre bom manter contato! 😄\n\nComo tem passado?",
        
        // Templates promocionais
        "Oi {nome}! 🔥\n\nTenho uma promoção especial que pode te interessar!\n\nÉ por tempo limitado, então corre que ainda dá tempo! ⏰\n\nVamos conversar?",
        
        "Olá {nome}! 💰\n\nOportunidade imperdível chegando!\n\nPensando em você, separei essa oferta especial.\n\nInteresse? Me chama! 🚀"
    ];
    
    const randomTemplate = templates[Math.floor(Math.random() * templates.length)];
    
    showMessageTextarea();
    const textarea = document.getElementById('messageText');
    textarea.value = randomTemplate;
    textarea.focus();
    updatePreview();
    
    showMessage('success', 'Mensagem gerada com IA! Você pode editá-la conforme necessário.');
}

// Função para gravação de voz (placeholder)
function startVoiceRecording() {
    showMessage('info', 'Funcionalidade de gravação de voz será implementada em breve!');
}

// Função para limpar mensagem
function clearMessage() {
    if (confirm('Tem certeza que deseja limpar a mensagem?')) {
        const textarea = document.getElementById('messageText');
        const placeholder = document.getElementById('messagePlaceholder');
        
        textarea.value = '';
        placeholder.classList.remove('hidden');
        textarea.classList.add('hidden');
        
        updatePreview();
        showMessage('success', 'Mensagem limpa com sucesso!');
    }
}

// Função para lidar com seleção de arquivo
function handleFileSelect(input) {
    const file = input.files[0];
    if (file) {
        const fileName = file.name;
        const fileSize = (file.size / 1024 / 1024).toFixed(2); // MB
        const fileType = file.type;
        
        // Determinar tipo de arquivo
        let attachmentType = 'document';
        if (fileType.startsWith('image/')) attachmentType = 'image';
        else if (fileType.startsWith('video/')) attachmentType = 'video';
        else if (fileType.startsWith('audio/')) attachmentType = 'audio';
        
        // Inserir template com informações do arquivo
        insertAttachmentTemplate(attachmentType, fileName, fileSize);
        
        // Fechar modal
        document.querySelector('.fixed').remove();
        
        showMessage('success', `Arquivo "${fileName}" (${fileSize}MB) adicionado à mensagem!`);
    }
}

// Funções para drag and drop
function handleDragOver(event) {
    event.preventDefault();
    event.stopPropagation();
}

function handleDragEnter(event) {
    event.preventDefault();
    event.stopPropagation();
    const dropZone = event.target.closest('#dropZone');
    if (dropZone) {
        dropZone.classList.add('border-blue-500', 'bg-blue-50');
        dropZone.classList.remove('border-gray-300');
    }
}

function handleDragLeave(event) {
    event.preventDefault();
    event.stopPropagation();
    const dropZone = event.target.closest('#dropZone');
    if (dropZone && !dropZone.contains(event.relatedTarget)) {
        dropZone.classList.remove('border-blue-500', 'bg-blue-50');
        dropZone.classList.add('border-gray-300');
    }
}

function handleDrop(event) {
    event.preventDefault();
    event.stopPropagation();
    
    const dropZone = event.target.closest('#dropZone');
    if (dropZone) {
        dropZone.classList.remove('border-blue-500', 'bg-blue-50');
        dropZone.classList.add('border-gray-300');
    }
    
    const files = event.dataTransfer.files;
    if (files.length > 0) {
        const file = files[0];
        // Simular seleção de arquivo
        const mockInput = { files: [file] };
        handleFileSelect(mockInput);
    }
}

// Função para inserir templates de anexo
function insertAttachmentTemplate(type, fileName = null, fileSize = null) {
    let template = '';
    
    switch(type) {
        case 'document':
            if (fileName) {
                template = `📄 *Documento anexado:*\n${fileName} (${fileSize}MB)\n\nConfira o arquivo que enviei para você!`;
            } else {
                template = '📄 *Documento anexado*\n\nConfira o arquivo que enviei para você!\n\nQualquer dúvida, me chama! 😊';
            }
            break;
            
        case 'image':
            if (fileName) {
                template = `🖼️ *Imagem anexada:*\n${fileName} (${fileSize}MB)\n\nDá uma olhada nessa imagem!`;
            } else {
                template = '🖼️ *Imagem anexada*\n\nDá uma olhada nessa imagem que separei para você! 📸\n\nO que achou?';
            }
            break;
            
        case 'video':
            if (fileName) {
                template = `🎥 *Vídeo anexado:*\n${fileName} (${fileSize}MB)\n\nAssista esse vídeo interessante!`;
            } else {
                template = '🎥 *Vídeo anexado*\n\nAssista esse vídeo que achei interessante! 🍿\n\nTenho certeza que vai gostar!';
            }
            break;
            
        case 'audio':
            if (fileName) {
                template = `🎵 *Áudio anexado:*\n${fileName} (${fileSize}MB)\n\nEscute essa mensagem de áudio!`;
            } else {
                template = '🎵 *Áudio anexado*\n\nEscute essa mensagem de áudio que gravei para você! 🎧\n\nÉ mais fácil explicar falando!';
            }
            break;
            
        case 'location':
            template = '📍 *Localização compartilhada*\n\nEstou enviando minha localização para você!\n\nNos encontramos aqui? 🗺️';
            break;
            
        default:
            template = '📎 *Arquivo anexado*\n\nConfira o arquivo que enviei!\n\nQualquer dúvida, me avisa! 😊';
    }
    
    if (template) {
        showMessageTextarea();
        const textarea = document.getElementById('messageText');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;
        
        // Adicionar quebra de linha se já houver texto
        const prefix = text.trim() ? '\n\n' : '';
        const fullTemplate = prefix + template;
        
        textarea.value = text.substring(0, start) + fullTemplate + text.substring(end);
        textarea.focus();
        textarea.selectionStart = textarea.selectionEnd = start + fullTemplate.length;
        updatePreview();
    }
}

function updatePreview() {
    const message = document.getElementById('messageText').value;
    currentMessage = message;
    
    // Verificar se tem mensagem OU imagem anexada
    const hasMessage = message.trim() !== '';
    const hasAttachment = attachedFile && attachedFile.base64;
    
    if (!hasMessage && !hasAttachment) {
        document.getElementById('messagePreview').classList.add('hidden');
        updateDispatchButton();
        return;
    }
    
    document.getElementById('messagePreview').classList.remove('hidden');
    
    // Preview com primeiro contato
    let previewText = message;
    if (selectedContacts.length > 0) {
        const firstContact = selectedContacts[0];
        previewText = message
            .replace(/{nome}/g, firstContact.name || 'Cliente')
            .replace(/{telefone}/g, firstContact.phone);
    } else {
        previewText = message
            .replace(/{nome}/g, 'João Silva')
            .replace(/{telefone}/g, '11999887766');
    }
    
    // Construir preview com imagem se houver
    let previewHtml = '';
    
    if (hasAttachment && attachedFile.dataUrl) {
        previewHtml += `<div class="mb-2"><img src="${attachedFile.dataUrl}" alt="Imagem anexada" class="max-w-xs max-h-32 rounded-lg border"></div>`;
    } else if (hasAttachment) {
        previewHtml += `<div class="mb-2 text-sm text-gray-500"><i class="fas fa-paperclip mr-1"></i>${attachedFile.name}</div>`;
    }
    
    if (hasMessage) {
        previewHtml += `<div>${previewText}</div>`;
    }
    
    document.getElementById('previewText').innerHTML = previewHtml;
    updateDispatchButton();
}

function updateDispatchButton() {
    const mode = document.querySelector('input[name="dispatch_mode"]:checked').value;
    const btn = document.getElementById('startDispatchBtn');
    
    let canDispatch = false;
    
    if (mode === 'category') {
        canDispatch = selectedContacts.length > 0 && currentMessage.trim() !== '';
    } else {
        // Modo individual agora também usa selectedContacts
        canDispatch = selectedContacts.length > 0 && currentMessage.trim() !== '';
    }
    
    btn.disabled = !canDispatch;
}

async function startDispatch() {
    if (isDispatching) return;
    
    const mode = document.querySelector('input[name="dispatch_mode"]:checked').value;
    let contacts = [];
    
    // Ambos os modos agora usam selectedContacts
    contacts = selectedContacts;
    
    if (contacts.length === 0) {
        alert('Nenhum contato selecionado');
        return;
    }
    
    if (currentMessage.trim() === '') {
        alert('Digite uma mensagem');
        return;
    }
    
    if (!confirm(`Deseja enviar mensagem para ${contacts.length} contato(s)?`)) {
        return;
    }
    
    // Criar campanha automaticamente
    let campaignId = null;
    try {
        const categoryId = mode === 'category' ? document.getElementById('selectedCategory').value : null;
        const campaignName = `Disparo ${new Date().toLocaleString('pt-BR')}`;
        const campaignDescription = `Disparo em massa para ${contacts.length} contatos`;
        
        const campaignResponse = await fetch('api/dispatch_campaigns.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'create',
                name: campaignName,
                description: campaignDescription,
                category_id: categoryId || '',
                total_contacts: contacts.length
            })
        });
        
        const campaignResult = await campaignResponse.json();
        if (campaignResult.success) {
            campaignId = campaignResult.campaign_id;
            console.log('Campanha criada:', campaignId);
        }
    } catch (error) {
        console.error('Erro ao criar campanha:', error);
    }
    
    // Inicializar estatísticas
    dispatchStats.startTime = Date.now();
    dispatchStats.total = contacts.length;
    dispatchStats.success = 0;
    dispatchStats.failed = 0;
    dispatchStats.campaignId = campaignId;
    dispatchResults = [];
    
    isDispatching = true;
    document.getElementById('startDispatchBtn').disabled = true;
    document.getElementById('dispatchQueue').classList.remove('hidden');
    document.getElementById('totalCount').textContent = contacts.length;
    document.getElementById('progressCount').textContent = 0;
    
    // Criar itens da fila
    const queueItems = document.getElementById('queueItems');
    queueItems.innerHTML = '';
    
    contacts.forEach((contact, index) => {
        const item = document.createElement('div');
        item.id = `queue-item-${index}`;
        item.className = 'border rounded-lg p-4 bg-gray-50';
        item.innerHTML = `
            <div class="flex items-center justify-between mb-2">
                <div>
                    <span class="font-semibold">${contact.name || 'Sem nome'}</span>
                    <span class="text-sm text-gray-600 ml-2">${contact.phone}</span>
                </div>
                <span id="status-${index}" class="text-sm">
                    <i class="fas fa-clock text-gray-400"></i> Aguardando
                </span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div id="progress-${index}" class="bg-blue-600 h-2 rounded-full progress-bar" style="width: 0%"></div>
            </div>
        `;
        queueItems.appendChild(item);
    });
    
    // Scroll para a fila
    document.getElementById('dispatchQueue').scrollIntoView({ behavior: 'smooth' });
    
    // Iniciar processamento
    processQueue(contacts, 0);
}

async function processQueue(contacts, index) {
    if (index >= contacts.length) {
        // Completou todos
        dispatchStats.duration = Date.now() - dispatchStats.startTime;
        document.getElementById('stat_success').textContent = dispatchStats.success;
        document.getElementById('stat_failed').textContent = dispatchStats.failed;
        document.getElementById('stat_total').textContent = dispatchStats.total;
        document.getElementById('stat_duration').textContent = Math.round(dispatchStats.duration / 1000) + 's';
        
        // Finalizar campanha
        if (dispatchStats.campaignId) {
            try {
                await fetch('api/dispatch_campaigns.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'complete',
                        id: dispatchStats.campaignId
                    })
                });
            } catch (error) {
                console.error('Erro ao finalizar campanha:', error);
            }
        }
        
        document.getElementById('completionMessage').classList.remove('hidden');
        createConfetti();
        isDispatching = false;
        return;
    }
    
    const contact = contacts[index];
    const statusEl = document.getElementById(`status-${index}`);
    const progressEl = document.getElementById(`progress-${index}`);
    const itemEl = document.getElementById(`queue-item-${index}`);
    
    // Marcar como processando
    statusEl.innerHTML = '<i class="fas fa-spinner fa-spin text-blue-600"></i> Enviando...';
    itemEl.classList.remove('bg-gray-50');
    itemEl.classList.add('bg-blue-50');
    
    // Usar intervalo selecionado
    const waitTime = getMessageInterval();
    const startTime = Date.now();
    
    // Animar barra de progresso
    const progressInterval = setInterval(() => {
        const elapsed = Date.now() - startTime;
        const progress = Math.min((elapsed / waitTime) * 100, 100);
        progressEl.style.width = progress + '%';
    }, 100);
    
    // Aguardar tempo aleatório
    await new Promise(resolve => setTimeout(resolve, waitTime));
    clearInterval(progressInterval);
    progressEl.style.width = '100%';
    
    // Enviar mensagem
    const message = currentMessage
        .replace(/{nome}/g, contact.name || 'Cliente')
        .replace(/{telefone}/g, contact.phone);
    
    // Preparar dados para envio
    const sendData = {
        contact_id: contact.id,
        phone: contact.phone,
        contact_name: contact.name || '',
        message: message,
        campaign_id: dispatchStats.campaignId || null
    };
    
    // Incluir imagem se houver anexo
    if (attachedFile && attachedFile.base64) {
        sendData.media = {
            base64: attachedFile.base64,
            mimetype: attachedFile.mimeType || attachedFile.type,
            filename: attachedFile.name
        };
        sendData.has_attachment = true;
        sendData.attachment_type = attachedFile.type ? attachedFile.type.split('/')[0] : 'document';
    }
    
    try {
        const response = await fetch('api/send_message.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(sendData)
        });
        
        // Primeiro pegar o texto da resposta
        const responseText = await response.text();
        
        // Tentar parsear como JSON
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Resposta não é JSON válido:', responseText.substring(0, 500));
            throw new Error('Resposta inválida do servidor');
        }
        
        if (result.success) {
            dispatchStats.success++;
            dispatchResults.push({
                name: contact.name || 'Sem nome',
                phone: contact.phone,
                success: true,
                timestamp: new Date().toLocaleTimeString()
            });
            
            statusEl.innerHTML = '<i class="fas fa-check-circle text-green-600"></i> Enviado';
            itemEl.classList.remove('bg-blue-50');
            itemEl.classList.add('bg-green-50');
            progressEl.classList.remove('bg-blue-600');
            progressEl.classList.add('bg-green-600');
        } else {
            throw new Error(result.error || 'Erro desconhecido');
        }
    } catch (error) {
        console.error('Erro ao enviar:', error);
        
        dispatchStats.failed++;
        dispatchResults.push({
            name: contact.name || 'Sem nome',
            phone: contact.phone,
            success: false,
            timestamp: new Date().toLocaleTimeString()
        });
        
        statusEl.innerHTML = '<i class="fas fa-times-circle text-red-600"></i> Falhou';
        itemEl.classList.remove('bg-blue-50');
        itemEl.classList.add('bg-red-50');
        progressEl.classList.remove('bg-blue-600');
        progressEl.classList.add('bg-red-600');
    }
    
    // Atualizar progresso geral
    const progressCount = index + 1;
    document.getElementById('progressCount').textContent = progressCount;
    const overallProgress = (progressCount / contacts.length) * 100;
    document.getElementById('overallProgress').style.width = overallProgress + '%';
    
    // Processar próximo
    setTimeout(() => processQueue(contacts, index + 1), 1000);
}

// Funções para seleção múltipla no modo individual
function toggleContact(contactId) {
    const checkbox = document.querySelector(`input[value="${contactId}"]`);
    if (checkbox) {
        checkbox.checked = !checkbox.checked;
        updateSelectedContacts();
    }
}

function updateSelectedContacts() {
    const checkboxes = document.querySelectorAll('.contact-checkbox:checked');
    selectedContacts = [];
    
    checkboxes.forEach(checkbox => {
        selectedContacts.push({
            id: checkbox.value,
            name: checkbox.dataset.name,
            phone: checkbox.dataset.phone
        });
    });
    
    // Atualizar contador
    document.getElementById('selectedCount').textContent = selectedContacts.length;
    
    // Mostrar/esconder lista de selecionados
    displayContacts();
    updateDispatchButton();
}

function selectAllContacts() {
    const checkboxes = document.querySelectorAll('.contact-checkbox');
    checkboxes.forEach(checkbox => {
        if (!checkbox.checked) {
            checkbox.checked = true;
        }
    });
    updateSelectedContacts();
}

function clearAllContacts() {
    const checkboxes = document.querySelectorAll('.contact-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    updateSelectedContacts();
}

function filterContacts() {
    const searchTerm = document.getElementById('contactSearch').value.toLowerCase();
    const contactItems = document.querySelectorAll('.contact-item');
    
    contactItems.forEach(item => {
        const name = item.querySelector('.contact-name').textContent.toLowerCase();
        const phone = item.querySelector('.contact-phone').textContent.toLowerCase();
        
        if (name.includes(searchTerm) || phone.includes(searchTerm)) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
}

function resetDispatch() {
    isDispatching = false;
    document.getElementById('dispatchQueue').classList.add('hidden');
    document.getElementById('completionMessage').classList.add('hidden');
    document.getElementById('startDispatchBtn').disabled = false;
    
    // Limpar seleção
    clearAllContacts();
    document.getElementById('messageText').value = '';
    currentMessage = '';
    
    // Resetar estado do placeholder
    document.getElementById('messagePlaceholder').classList.remove('hidden');
    document.getElementById('messageText').classList.add('hidden');
    
    updatePreview();
}

// Funções de Upload e Anexo
async function handleFileUpload(input) {
    const file = input.files[0];
    if (!file) return;
    
    const maxSize = 16 * 1024 * 1024; // 16MB
    if (file.size > maxSize) {
        showMessage('error', 'Arquivo muito grande! Máximo: 16MB');
        return;
    }
    
    attachedFile = file;
    
    const reader = new FileReader();
    reader.onload = function(e) {
        const base64Data = e.target.result;
        attachedFile.base64 = base64Data.split(',')[1];
        attachedFile.mimeType = file.type;
        attachedFile.dataUrl = base64Data; // Guardar URL completa para preview
        showAttachmentPreview(file);
        updatePreview(); // Atualizar preview da mensagem
        showMessage('success', `Arquivo "${file.name}" anexado!`);
    };
    reader.readAsDataURL(file);
}

function showAttachmentPreview(file) {
    const existing = document.getElementById('attachmentPreview');
    if (existing) existing.remove();
    
    const preview = document.createElement('div');
    preview.id = 'attachmentPreview';
    preview.className = 'mb-4 bg-blue-50 border border-blue-200 rounded-lg p-4';
    
    // Verificar se é imagem para mostrar preview
    const isImage = file.type.startsWith('image/');
    let imagePreview = '';
    
    if (isImage && attachedFile && attachedFile.base64) {
        imagePreview = `
            <div class="mt-3">
                <img src="data:${file.type};base64,${attachedFile.base64}" 
                     alt="Preview" 
                     class="max-w-xs max-h-48 rounded-lg border border-gray-200 shadow-sm">
            </div>
        `;
    } else if (isImage) {
        // Se ainda não tem base64, criar preview com FileReader
        const reader = new FileReader();
        reader.onload = function(e) {
            const imgContainer = document.getElementById('imagePreviewContainer');
            if (imgContainer) {
                imgContainer.innerHTML = `
                    <img src="${e.target.result}" 
                         alt="Preview" 
                         class="max-w-xs max-h-48 rounded-lg border border-gray-200 shadow-sm">
                `;
            }
        };
        reader.readAsDataURL(file);
        imagePreview = '<div id="imagePreviewContainer" class="mt-3"><i class="fas fa-spinner fa-spin"></i> Carregando preview...</div>';
    }
    
    const icon = isImage ? 'fa-image text-green-600' : 'fa-paperclip text-blue-600';
    
    preview.innerHTML = `
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas ${icon} mr-3 text-xl"></i>
                <div>
                    <div class="font-medium text-gray-800">${file.name}</div>
                    <div class="text-sm text-gray-600">${(file.size / 1024 / 1024).toFixed(2)} MB</div>
                </div>
            </div>
            <button onclick="removeAttachment()" class="text-red-500 hover:text-red-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        ${imagePreview}
    `;
    
    const messageEditor = document.querySelector('.bg-white.border.border-gray-200');
    if (messageEditor && messageEditor.parentNode) {
        messageEditor.parentNode.insertBefore(preview, messageEditor);
    } else {
        // Fallback: inserir antes do botão de disparo
        const dispatchBtn = document.querySelector('button[onclick*="startDispatch"]');
        if (dispatchBtn) {
            dispatchBtn.parentNode.insertBefore(preview, dispatchBtn);
        }
    }
}

function removeAttachment() {
    attachedFile = null;
    document.getElementById('attachmentPreview')?.remove();
    updatePreview(); // Atualizar preview da mensagem
    showMessage('info', 'Anexo removido');
}

function getMessageInterval() {
    const checkedInput = document.querySelector('input[name="message_interval"]:checked');
    const interval = checkedInput ? checkedInput.value : '5'; // Default 5 segundos
    return parseInt(interval) * 1000;
}

function triggerFileType(accept) {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = accept;
    input.onchange = (e) => {
        handleFileUpload(e.target);
        document.querySelector('.fixed')?.remove();
    };
    input.click();
}

// Função para selecionar arquivo por tipo (usada nos botões de opções rápidas)
function selectFileByType(acceptType) {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = acceptType;
    input.onchange = (e) => {
        if (e.target.files && e.target.files[0]) {
            handleFileUpload(e.target);
        }
    };
    input.click();
}

// Função para mostrar seletor de localização
function showLocationPicker() {
    showMessage('info', 'Funcionalidade de localização em desenvolvimento');
}

// Função para colar imagem do clipboard
function handlePasteImage(event) {
    const items = event.clipboardData?.items;
    if (!items) return;
    
    for (let i = 0; i < items.length; i++) {
        if (items[i].type.indexOf('image') !== -1) {
            event.preventDefault();
            const file = items[i].getAsFile();
            if (file) {
                // Processar a imagem colada
                processClipboardImage(file);
            }
            break;
        }
    }
}

// Função para processar imagem colada do clipboard
function processClipboardImage(file) {
    const maxSize = 16 * 1024 * 1024; // 16MB
    if (file.size > maxSize) {
        showMessage('error', 'Imagem muito grande! Máximo: 16MB');
        return;
    }
    
    // Criar nome para o arquivo
    const timestamp = new Date().getTime();
    const fileName = `imagem_colada_${timestamp}.png`;
    
    // Criar um novo File com nome
    const namedFile = new File([file], fileName, { type: file.type });
    
    attachedFile = namedFile;
    
    const reader = new FileReader();
    reader.onload = function(e) {
        const base64Data = e.target.result;
        attachedFile.base64 = base64Data.split(',')[1];
        attachedFile.mimeType = namedFile.type;
        attachedFile.dataUrl = base64Data; // Guardar URL completa para preview
        
        // Mostrar preview com a imagem real
        showClipboardImagePreview(namedFile, base64Data);
        
        // Atualizar preview da mensagem para incluir a imagem
        updatePreview();
        
        showMessage('success', 'Imagem colada com sucesso!');
    };
    reader.readAsDataURL(namedFile);
}

// Função para mostrar preview de imagem colada
function showClipboardImagePreview(file, dataUrl) {
    const existing = document.getElementById('attachmentPreview');
    if (existing) existing.remove();
    
    const preview = document.createElement('div');
    preview.id = 'attachmentPreview';
    preview.className = 'mb-4 bg-green-50 border border-green-200 rounded-lg p-4';
    
    preview.innerHTML = `
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center">
                <i class="fas fa-image text-green-600 mr-3 text-xl"></i>
                <div>
                    <div class="font-medium text-gray-800">${file.name}</div>
                    <div class="text-sm text-gray-600">${(file.size / 1024).toFixed(2)} KB</div>
                </div>
            </div>
            <button onclick="removeAttachment()" class="text-red-500 hover:text-red-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="mt-2">
            <img src="${dataUrl}" alt="Preview" class="max-w-xs max-h-48 rounded-lg border border-gray-200 shadow-sm">
        </div>
    `;
    
    // Inserir antes do editor de mensagem
    const messageBox = document.querySelector('.bg-white.border.border-gray-200.rounded-lg');
    if (messageBox && messageBox.parentNode) {
        messageBox.parentNode.insertBefore(preview, messageBox);
    }
}

// Função para inserir link via prompt
function insertLinkPrompt() {
    const url = prompt('Digite a URL do link:');
    if (url) {
        showMessageTextarea();
        const textarea = document.getElementById('messageText');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;
        textarea.value = text.substring(0, start) + url + text.substring(end);
        textarea.selectionStart = textarea.selectionEnd = start + url.length;
        textarea.focus();
        updatePreview();
    }
}

// Adicionar listener de paste ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    // Listener para colar imagem no campo de mensagem
    const messageArea = document.querySelector('.message-editor-area');
    if (messageArea) {
        messageArea.addEventListener('paste', handlePasteImage);
    }
    
    // Também adicionar ao documento inteiro como fallback
    document.addEventListener('paste', function(e) {
        // Verificar se o foco está na área de mensagem
        const activeElement = document.activeElement;
        if (activeElement && (activeElement.id === 'messageText' || activeElement.closest('.message-editor-area'))) {
            handlePasteImage(e);
        }
    });
});

// Função de Relatório Detalhado
function showDetailedReport() {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
    modal.onclick = (e) => { if (e.target === modal) modal.remove(); };
    
    modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white border-b px-6 py-4 flex justify-between items-center">
                <h3 class="text-2xl font-bold">
                    <i class="fas fa-chart-line mr-2 text-blue-600"></i>Relatório Detalhado
                </h3>
                <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700 text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-gradient-to-br from-green-500 to-green-600 text-white rounded-lg p-4">
                        <div class="text-3xl font-bold">${dispatchStats.success}</div>
                        <div class="text-sm opacity-90">Enviadas</div>
                    </div>
                    <div class="bg-gradient-to-br from-red-500 to-red-600 text-white rounded-lg p-4">
                        <div class="text-3xl font-bold">${dispatchStats.failed}</div>
                        <div class="text-sm opacity-90">Falhas</div>
                    </div>
                    <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-lg p-4">
                        <div class="text-3xl font-bold">${dispatchStats.total}</div>
                        <div class="text-sm opacity-90">Total</div>
                    </div>
                    <div class="bg-gradient-to-br from-purple-500 to-purple-600 text-white rounded-lg p-4">
                        <div class="text-3xl font-bold">${Math.round(dispatchStats.duration / 1000)}s</div>
                        <div class="text-sm opacity-90">Duração</div>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold">#</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Contato</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Telefone</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Status</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Horário</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            ${dispatchResults.map((result, index) => `
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm">${index + 1}</td>
                                    <td class="px-4 py-3 text-sm font-medium">${result.name}</td>
                                    <td class="px-4 py-3 text-sm">${result.phone}</td>
                                    <td class="px-4 py-3">
                                        ${result.success ? 
                                            '<span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs"><i class="fas fa-check mr-1"></i>Enviada</span>' :
                                            '<span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs"><i class="fas fa-times mr-1"></i>Falha</span>'
                                        }
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">${result.timestamp}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-6 flex gap-3 justify-end">
                    <button onclick="exportReportCSV()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-download mr-2"></i>Exportar CSV
                    </button>
                    <button onclick="this.closest('.fixed').remove()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}

function exportReportCSV() {
    let csv = 'Contato,Telefone,Status,Horário\n';
    dispatchResults.forEach(result => {
        csv += `"${result.name}","${result.phone}","${result.success ? 'Enviada' : 'Falha'}","${result.timestamp}"\n`;
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `relatorio_disparo_${new Date().getTime()}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
}

// Eventos
document.getElementById('messageText').addEventListener('input', function() {
    updatePreview();
    checkMessageEmpty();
});

// Evento para clicar no placeholder e mostrar textarea
document.getElementById('messagePlaceholder').addEventListener('click', showMessageTextarea);

// Evento para quando o textarea perde o foco e está vazio
document.getElementById('messageText').addEventListener('blur', checkMessageEmpty);

// Bloquear alertas automáticos de status da instância
const originalAlert = window.alert;
const originalConfirm = window.confirm;

window.alert = function(message) {
    // Bloquear alertas relacionados ao WhatsApp/instância
    if (message && (
        message.includes('WhatsApp não está conectado') ||
        message.includes('Gere um novo QR Code') ||
        message.includes('instância não está conectada') ||
        message.includes('Minha Instância')
    )) {
        console.log('Alerta bloqueado:', message);
        return;
    }
    return originalAlert.call(this, message);
};

window.confirm = function(message) {
    // Bloquear confirms relacionados ao WhatsApp/instância
    if (message && (
        message.includes('WhatsApp não está conectado') ||
        message.includes('Gere um novo QR Code') ||
        message.includes('Deseja ir para') ||
        message.includes('Minha Instância')
    )) {
        console.log('Confirm bloqueado:', message);
        return false;
    }
    return originalConfirm.call(this, message);
};

// Função de confetti para celebração
function createConfetti() {
    const colors = ['#10B981', '#3B82F6', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899'];
    const confettiCount = 100;
    
    for (let i = 0; i < confettiCount; i++) {
        const confetti = document.createElement('div');
        confetti.style.cssText = `
            position: fixed;
            width: 10px;
            height: 10px;
            background: ${colors[Math.floor(Math.random() * colors.length)]};
            left: ${Math.random() * 100}vw;
            top: -10px;
            opacity: ${Math.random() + 0.5};
            transform: rotate(${Math.random() * 360}deg);
            z-index: 9999;
            pointer-events: none;
            border-radius: ${Math.random() > 0.5 ? '50%' : '0'};
        `;
        document.body.appendChild(confetti);
        
        const animation = confetti.animate([
            { top: '-10px', transform: `rotate(0deg) translateX(0)` },
            { top: '100vh', transform: `rotate(${Math.random() * 720}deg) translateX(${(Math.random() - 0.5) * 200}px)` }
        ], {
            duration: Math.random() * 2000 + 2000,
            easing: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)'
        });
        
        animation.onfinish = () => confetti.remove();
    }
}

// Inicializar
updateDispatchButton();
</script>

</div>
<?php require_once 'includes/footer_spa.php'; ?>
