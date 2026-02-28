<?php
/**
 * SISTEMA KANBAN - WATS
 * Interface visual para gestão de leads/atendimentos
 * Integrado ao Chat WhatsApp
 * 
 * @author MAC-IP TECNOLOGIA
 * @version 1.0
 * @date 26/11/2025
 */

$page_title = 'Kanban';
require_once 'includes/header_spa.php';

$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'] ?? 'user';
$isAttendant = ($userType === 'attendant');

// Verificar se as tabelas do Kanban existem
$tablesExist = true;
$setupMessage = '';
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'kanban_boards'");
    if ($stmt->rowCount() == 0) {
        $tablesExist = false;
        $setupMessage = 'As tabelas do Kanban ainda não foram criadas. Execute o script database_kanban.sql no banco de dados.';
    }
} catch (Exception $e) {
    $tablesExist = false;
    $setupMessage = 'Erro ao verificar tabelas: ' . $e->getMessage();
}

$boards = [];
$columns = [];
$labels = [];
$attendants = [];
$currentBoardId = 0;
$ownerId = $userId;

if ($tablesExist) {
    // Buscar dados do usuário
    if ($isAttendant) {
        $stmt = $pdo->prepare("SELECT su.*, u.id as supervisor_id 
                               FROM supervisor_users su 
                               LEFT JOIN users u ON su.supervisor_id = u.id 
                               WHERE su.id = ?");
        $stmt->execute([$userId]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        $ownerId = $userData['supervisor_id'] ?? $userId;
    } else {
        $ownerId = $userId;
    }

    // Buscar quadros do usuário
    $stmt = $pdo->prepare("SELECT * FROM kanban_boards WHERE user_id = ? ORDER BY is_default DESC, name ASC");
    $stmt->execute([$ownerId]);
    $boards = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Se não tem quadros, criar o padrão
if (empty($boards)) {
    // Criar quadro padrão
    $stmt = $pdo->prepare("INSERT INTO kanban_boards (user_id, name, description, icon, color, is_default) 
                           VALUES (?, 'Pipeline de Vendas', 'Acompanhamento de leads e oportunidades', 'fa-funnel-dollar', '#10B981', 1)");
    $stmt->execute([$ownerId]);
    $boardId = $pdo->lastInsertId();
    
    // Criar colunas padrão
    $defaultColumns = [
        ['Novos Leads', '#6366F1', 'fa-inbox', 0],
        ['Em Contato', '#F59E0B', 'fa-comments', 1],
        ['Proposta Enviada', '#3B82F6', 'fa-file-invoice', 2],
        ['Negociação', '#8B5CF6', 'fa-handshake', 3],
        ['Fechado/Ganho', '#10B981', 'fa-check-circle', 4],
        ['Perdido', '#EF4444', 'fa-times-circle', 5]
    ];
    
    $stmtCol = $pdo->prepare("INSERT INTO kanban_columns (board_id, name, color, icon, position, is_final) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($defaultColumns as $col) {
        $isFinal = ($col[0] === 'Fechado/Ganho' || $col[0] === 'Perdido') ? 1 : 0;
        $stmtCol->execute([$boardId, $col[0], $col[1], $col[2], $col[3], $isFinal]);
    }
    
    // Criar labels padrão
    $defaultLabels = [
        ['Quente', '#EF4444'],
        ['Morno', '#F59E0B'],
        ['Frio', '#3B82F6'],
        ['VIP', '#8B5CF6'],
        ['Empresa', '#10B981'],
        ['Pessoa Física', '#6B7280']
    ];
    
    $stmtLabel = $pdo->prepare("INSERT INTO kanban_labels (board_id, name, color) VALUES (?, ?, ?)");
    foreach ($defaultLabels as $label) {
        $stmtLabel->execute([$boardId, $label[0], $label[1]]);
    }
    
    // Recarregar quadros
    $stmt = $pdo->prepare("SELECT * FROM kanban_boards WHERE user_id = ? ORDER BY is_default DESC, name ASC");
    $stmt->execute([$ownerId]);
    $boards = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    $currentBoardId = $_GET['board'] ?? ($boards[0]['id'] ?? 0);

    // Buscar colunas do quadro atual
    $stmt = $pdo->prepare("SELECT * FROM kanban_columns WHERE board_id = ? ORDER BY position ASC");
    $stmt->execute([$currentBoardId]);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar labels do quadro
    $stmt = $pdo->prepare("SELECT * FROM kanban_labels WHERE board_id = ? ORDER BY name ASC");
    $stmt->execute([$currentBoardId]);
    $labels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar atendentes para atribuição
    $stmt = $pdo->prepare("SELECT id, name FROM supervisor_users WHERE supervisor_id = ? AND status = 'active' ORDER BY name");
    $stmt->execute([$ownerId]);
    $attendants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} // Fim do if ($tablesExist)
?>

<?php if (!$tablesExist): ?>
<!-- Mensagem de Setup Necessário -->
<div class="p-8">
    <div class="max-w-2xl mx-auto bg-yellow-50 border border-yellow-200 rounded-xl p-8 text-center">
        <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-database text-yellow-600 text-2xl"></i>
        </div>
        <h2 class="text-xl font-bold text-yellow-800 mb-2">Configuração Necessária</h2>
        <p class="text-yellow-700 mb-4"><?php echo htmlspecialchars($setupMessage); ?></p>
        <div class="bg-white rounded-lg p-4 text-left">
            <p class="text-sm text-gray-600 mb-2"><strong>Para configurar o Kanban:</strong></p>
            <ol class="text-sm text-gray-600 list-decimal list-inside space-y-1">
                <li>Acesse o phpMyAdmin ou seu gerenciador de banco de dados</li>
                <li>Selecione o banco de dados do WATS</li>
                <li>Execute o script <code class="bg-gray-100 px-1 rounded">database_kanban.sql</code></li>
                <li>Recarregue esta página</li>
            </ol>
        </div>
        <button onclick="location.reload()" class="mt-4 px-6 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg transition">
            <i class="fas fa-sync-alt mr-2"></i>Verificar Novamente
        </button>
    </div>
</div>
<?php else: ?>
<!-- CSS do Kanban -->
<link rel="stylesheet" href="assets/css/kanban.css">

<!-- SortableJS para drag-and-drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<div class="kanban-page">
    <!-- Header do Kanban -->
    <div class="kanban-header">
        <div class="kanban-header-left">
            <div class="kanban-board-selector">
                <select id="board-selector" onchange="changeBoard(this.value)">
                    <?php foreach ($boards as $board): ?>
                    <option value="<?= $board['id'] ?>" <?= $board['id'] == $currentBoardId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($board['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button onclick="openBoardModal()" class="kanban-btn-icon" title="Gerenciar Quadros">
                    <i class="fas fa-cog"></i>
                </button>
            </div>
            
            <div class="kanban-filters">
                <div class="kanban-search">
                    <i class="fas fa-search"></i>
                    <input type="text" id="kanban-search" placeholder="Buscar cards..." onkeyup="filterCards()">
                </div>
                
                <select id="filter-assignee" onchange="filterCards()" class="kanban-filter-select">
                    <option value="">Todos os responsáveis</option>
                    <?php foreach ($attendants as $att): ?>
                    <option value="<?= $att['id'] ?>"><?= htmlspecialchars($att['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                
                <select id="filter-priority" onchange="filterCards()" class="kanban-filter-select">
                    <option value="">Todas as prioridades</option>
                    <option value="urgent">🔴 Urgente</option>
                    <option value="high">🟠 Alta</option>
                    <option value="normal">🟡 Normal</option>
                    <option value="low">🟢 Baixa</option>
                </select>
                
                <select id="filter-label" onchange="filterCards()" class="kanban-filter-select">
                    <option value="">Todas as etiquetas</option>
                    <?php foreach ($labels as $label): ?>
                    <option value="<?= $label['id'] ?>"><?= htmlspecialchars($label['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                
                <button onclick="clearFilters()" class="kanban-btn-icon" title="Limpar filtros">
                    <i class="fas fa-times-circle"></i>
                </button>
            </div>
        </div>
        
        <div class="kanban-header-right">
            <div class="kanban-stats">
                <span class="kanban-stat">
                    <i class="fas fa-clipboard-list"></i>
                    <span id="total-cards">0</span> cards
                </span>
                <span class="kanban-stat">
                    <i class="fas fa-dollar-sign"></i>
                    R$ <span id="total-value">0,00</span>
                </span>
            </div>
            
            <button onclick="openNewCardModal()" class="kanban-btn-primary">
                <i class="fas fa-plus"></i>
                Novo Card
            </button>
            
            <button onclick="refreshKanban()" class="kanban-btn-secondary" title="Atualizar (F5)">
                <i class="fas fa-sync-alt"></i>
            </button>
            
            <button onclick="exportToCSV()" class="kanban-btn-secondary" title="Exportar CSV">
                <i class="fas fa-download"></i>
            </button>
            
            <button onclick="toggleArchivedView()" class="kanban-btn-secondary" id="btn-archived">
                <i class="fas fa-archive"></i>
                Arquivados
            </button>
        </div>
    </div>
    
    <!-- Container do Kanban -->
    <div class="kanban-container" id="kanban-container">
        <?php foreach ($columns as $column): ?>
        <div class="kanban-column" data-column-id="<?= $column['id'] ?>">
            <div class="kanban-column-header" style="border-top-color: <?= $column['color'] ?>">
                <div class="kanban-column-title">
                    <?php if ($column['icon']): ?>
                    <i class="fas <?= $column['icon'] ?>" style="color: <?= $column['color'] ?>"></i>
                    <?php endif; ?>
                    <span><?= htmlspecialchars($column['name']) ?></span>
                    <span class="kanban-column-count" id="count-<?= $column['id'] ?>">0</span>
                </div>
                <div class="kanban-column-actions">
                    <?php if ($column['wip_limit']): ?>
                    <span class="kanban-wip-limit" title="Limite WIP: <?= $column['wip_limit'] ?>">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?= $column['wip_limit'] ?>
                    </span>
                    <?php endif; ?>
                    <button onclick="openColumnMenu(<?= $column['id'] ?>)" class="kanban-btn-icon-sm">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </div>
            </div>
            
            <div class="kanban-column-cards" id="column-<?= $column['id'] ?>" data-column-id="<?= $column['id'] ?>">
                <!-- Cards serão carregados via JavaScript -->
                <div class="kanban-loading">
                    <i class="fas fa-spinner fa-spin"></i>
                </div>
            </div>
            
            <div class="kanban-column-footer">
                <button onclick="openNewCardModal(<?= $column['id'] ?>)" class="kanban-add-card-btn">
                    <i class="fas fa-plus"></i>
                    Adicionar card
                </button>
            </div>
        </div>
        <?php endforeach; ?>
        
        <!-- Botão para adicionar nova coluna -->
        <div class="kanban-add-column">
            <button onclick="openNewColumnModal()" class="kanban-add-column-btn">
                <i class="fas fa-plus"></i>
                Adicionar coluna
            </button>
        </div>
    </div>
</div>

<!-- Modal: Novo/Editar Card -->
<div id="card-modal" class="kanban-modal hidden">
    <div class="kanban-modal-overlay" onclick="closeCardModal()"></div>
    <div class="kanban-modal-content kanban-modal-large">
        <div class="kanban-modal-header">
            <h3 id="card-modal-title">
                <i class="fas fa-plus-circle"></i>
                Novo Card
            </h3>
            <button onclick="closeCardModal()" class="kanban-modal-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="card-form" onsubmit="saveCard(event)">
            <input type="hidden" id="card-id" value="">
            <input type="hidden" id="card-column-id" value="">
            
            <div class="kanban-modal-body">
                <div class="kanban-form-row">
                    <div class="kanban-form-group kanban-form-full">
                        <label for="card-title">Título *</label>
                        <input type="text" id="card-title" required placeholder="Ex: João Silva - Interesse em plano empresarial">
                    </div>
                </div>
                
                <div class="kanban-form-row">
                    <div class="kanban-form-group">
                        <label for="card-contact-name">Nome do Contato</label>
                        <input type="text" id="card-contact-name" placeholder="Nome do cliente">
                    </div>
                    <div class="kanban-form-group">
                        <label for="card-contact-phone">Telefone</label>
                        <input type="text" id="card-contact-phone" placeholder="(11) 99999-9999">
                    </div>
                </div>
                
                <div class="kanban-form-row">
                    <div class="kanban-form-group">
                        <label for="card-value">Valor (R$)</label>
                        <input type="number" id="card-value" step="0.01" min="0" placeholder="0,00">
                    </div>
                    <div class="kanban-form-group">
                        <label for="card-priority">Prioridade</label>
                        <select id="card-priority">
                            <option value="low">🟢 Baixa</option>
                            <option value="normal" selected>🟡 Normal</option>
                            <option value="high">🟠 Alta</option>
                            <option value="urgent">🔴 Urgente</option>
                        </select>
                    </div>
                </div>
                
                <div class="kanban-form-row">
                    <div class="kanban-form-group">
                        <label for="card-due-date">Data de Vencimento</label>
                        <input type="date" id="card-due-date">
                    </div>
                    <div class="kanban-form-group">
                        <label for="card-assigned">Responsável</label>
                        <select id="card-assigned">
                            <option value="">Sem responsável</option>
                            <?php foreach ($attendants as $att): ?>
                            <option value="<?= $att['id'] ?>"><?= htmlspecialchars($att['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="kanban-form-row">
                    <div class="kanban-form-group kanban-form-full">
                        <label>Etiquetas</label>
                        <div class="kanban-labels-selector" id="labels-selector">
                            <?php foreach ($labels as $label): ?>
                            <label class="kanban-label-checkbox">
                                <input type="checkbox" name="labels[]" value="<?= $label['id'] ?>">
                                <span class="kanban-label-tag" style="background-color: <?= $label['color'] ?>">
                                    <?= htmlspecialchars($label['name']) ?>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="kanban-form-row">
                    <div class="kanban-form-group kanban-form-full">
                        <label for="card-description">Descrição</label>
                        <textarea id="card-description" rows="4" placeholder="Detalhes sobre o lead/atendimento..."></textarea>
                    </div>
                </div>
                
                <!-- Seção de conversa vinculada (apenas para edição) -->
                <div id="linked-conversation-section" class="hidden">
                    <div class="kanban-form-divider">
                        <span>💬 Conversa Vinculada</span>
                    </div>
                    <div id="linked-conversation-info" class="kanban-linked-conversation">
                        <!-- Preenchido via JS -->
                    </div>
                </div>
                
                <!-- Seção de comentários (apenas para edição) -->
                <div id="comments-section" class="hidden">
                    <div class="kanban-form-divider">
                        <span>📝 Comentários</span>
                    </div>
                    <div id="card-comments" class="kanban-comments-list">
                        <!-- Preenchido via JS -->
                    </div>
                    <div class="kanban-add-comment">
                        <input type="text" id="new-comment" placeholder="Adicionar comentário...">
                        <button type="button" onclick="addComment()">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="kanban-modal-footer">
                <div class="kanban-modal-footer-left">
                    <button type="button" id="btn-archive-card" onclick="archiveCard()" class="kanban-btn-danger hidden">
                        <i class="fas fa-archive"></i>
                        Arquivar
                    </button>
                    <button type="button" id="btn-open-chat" onclick="openLinkedChat()" class="kanban-btn-secondary hidden">
                        <i class="fas fa-comments"></i>
                        Abrir Chat
                    </button>
                </div>
                <div class="kanban-modal-footer-right">
                    <button type="button" onclick="closeCardModal()" class="kanban-btn-secondary">
                        Cancelar
                    </button>
                    <button type="submit" class="kanban-btn-primary">
                        <i class="fas fa-save"></i>
                        Salvar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Nova Coluna -->
<div id="column-modal" class="kanban-modal hidden">
    <div class="kanban-modal-overlay" onclick="closeColumnModal()"></div>
    <div class="kanban-modal-content">
        <div class="kanban-modal-header">
            <h3 id="column-modal-title">
                <i class="fas fa-columns"></i>
                Nova Coluna
            </h3>
            <button onclick="closeColumnModal()" class="kanban-modal-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="column-form" onsubmit="saveColumn(event)">
            <input type="hidden" id="column-id" value="">
            
            <div class="kanban-modal-body">
                <div class="kanban-form-group">
                    <label for="column-name">Nome da Coluna *</label>
                    <input type="text" id="column-name" required placeholder="Ex: Em Negociação">
                </div>
                
                <div class="kanban-form-row">
                    <div class="kanban-form-group">
                        <label for="column-color">Cor</label>
                        <input type="color" id="column-color" value="#6B7280">
                    </div>
                    <div class="kanban-form-group">
                        <label for="column-icon">Ícone</label>
                        <select id="column-icon">
                            <option value="">Sem ícone</option>
                            <option value="fa-inbox">📥 Inbox</option>
                            <option value="fa-comments">💬 Comentários</option>
                            <option value="fa-file-invoice">📄 Documento</option>
                            <option value="fa-handshake">🤝 Negociação</option>
                            <option value="fa-check-circle">✅ Concluído</option>
                            <option value="fa-times-circle">❌ Cancelado</option>
                            <option value="fa-clock">⏰ Aguardando</option>
                            <option value="fa-star">⭐ Destaque</option>
                            <option value="fa-fire">🔥 Urgente</option>
                            <option value="fa-dollar-sign">💰 Financeiro</option>
                        </select>
                    </div>
                </div>
                
                <div class="kanban-form-group">
                    <label for="column-wip">Limite WIP (opcional)</label>
                    <input type="number" id="column-wip" min="0" placeholder="0 = ilimitado">
                    <small>Limite de cards nesta coluna</small>
                </div>
                
                <div class="kanban-form-group">
                    <label class="kanban-checkbox-label">
                        <input type="checkbox" id="column-is-final">
                        <span>Coluna final (fechado/ganho)</span>
                    </label>
                </div>
            </div>
            
            <div class="kanban-modal-footer">
                <button type="button" id="btn-delete-column" onclick="deleteColumn()" class="kanban-btn-danger hidden">
                    <i class="fas fa-trash"></i>
                    Excluir
                </button>
                <div class="kanban-modal-footer-right">
                    <button type="button" onclick="closeColumnModal()" class="kanban-btn-secondary">
                        Cancelar
                    </button>
                    <button type="submit" class="kanban-btn-primary">
                        <i class="fas fa-save"></i>
                        Salvar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Gerenciar Quadros -->
<div id="board-modal" class="kanban-modal hidden">
    <div class="kanban-modal-overlay" onclick="closeBoardModal()"></div>
    <div class="kanban-modal-content">
        <div class="kanban-modal-header">
            <h3>
                <i class="fas fa-th-large"></i>
                Gerenciar Quadros
            </h3>
            <button onclick="closeBoardModal()" class="kanban-modal-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="kanban-modal-body">
            <div class="kanban-boards-list" id="boards-list">
                <?php foreach ($boards as $board): ?>
                <div class="kanban-board-item" data-board-id="<?= $board['id'] ?>">
                    <div class="kanban-board-item-info">
                        <i class="fas <?= $board['icon'] ?? 'fa-columns' ?>" style="color: <?= $board['color'] ?>"></i>
                        <span><?= htmlspecialchars($board['name']) ?></span>
                        <?php if ($board['is_default']): ?>
                        <span class="kanban-badge">Padrão</span>
                        <?php endif; ?>
                    </div>
                    <div class="kanban-board-item-actions">
                        <button onclick="editBoard(<?= $board['id'] ?>)" class="kanban-btn-icon-sm">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php if (!$board['is_default']): ?>
                        <button onclick="deleteBoard(<?= $board['id'] ?>)" class="kanban-btn-icon-sm kanban-btn-danger-icon">
                            <i class="fas fa-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <button onclick="openNewBoardForm()" class="kanban-btn-primary kanban-btn-full">
                <i class="fas fa-plus"></i>
                Criar Novo Quadro
            </button>
            
            <div id="new-board-form" class="hidden" style="margin-top: 1rem;">
                <div class="kanban-form-group">
                    <label for="new-board-name">Nome do Quadro</label>
                    <input type="text" id="new-board-name" placeholder="Ex: Suporte Técnico">
                </div>
                <div class="kanban-form-row">
                    <div class="kanban-form-group">
                        <label for="new-board-color">Cor</label>
                        <input type="color" id="new-board-color" value="#3B82F6">
                    </div>
                    <div class="kanban-form-group">
                        <button type="button" onclick="createNewBoard()" class="kanban-btn-primary">
                            Criar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Gerenciar Labels -->
<div id="labels-modal" class="kanban-modal hidden">
    <div class="kanban-modal-overlay" onclick="closeLabelsModal()"></div>
    <div class="kanban-modal-content">
        <div class="kanban-modal-header">
            <h3>
                <i class="fas fa-tags"></i>
                Gerenciar Etiquetas
            </h3>
            <button onclick="closeLabelsModal()" class="kanban-modal-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="kanban-modal-body">
            <div class="kanban-labels-list" id="labels-list">
                <!-- Preenchido via JS -->
            </div>
            
            <div class="kanban-add-label-form">
                <input type="text" id="new-label-name" placeholder="Nova etiqueta...">
                <input type="color" id="new-label-color" value="#6B7280">
                <button onclick="createLabel()" class="kanban-btn-primary">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Menu de contexto da coluna -->
<div id="column-context-menu" class="kanban-context-menu hidden">
    <button onclick="editColumn()">
        <i class="fas fa-edit"></i>
        Editar coluna
    </button>
    <button onclick="openLabelsModal()">
        <i class="fas fa-tags"></i>
        Gerenciar etiquetas
    </button>
    <button onclick="openAutomationModal()">
        <i class="fas fa-robot"></i>
        Automação
    </button>
    <div class="kanban-context-divider"></div>
    <button onclick="archiveAllInColumn()" class="kanban-context-danger">
        <i class="fas fa-archive"></i>
        Arquivar todos
    </button>
</div>

<!-- Modal: Automação -->
<div id="automation-modal" class="kanban-modal hidden">
    <div class="kanban-modal-overlay" onclick="closeAutomationModal()"></div>
    <div class="kanban-modal-content">
        <div class="kanban-modal-header">
            <h3>
                <i class="fas fa-robot"></i>
                Automação da Coluna
            </h3>
            <button onclick="closeAutomationModal()" class="kanban-modal-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="automation-form" onsubmit="saveAutomation(event)">
            <input type="hidden" id="automation-column-id">
            <div class="kanban-modal-body">
                <div class="kanban-form-group">
                    <label>Gatilho</label>
                    <select id="auto-trigger" class="w-full border rounded p-2">
                        <option value="time_in_column">Tempo na coluna</option>
                    </select>
                </div>
                <div class="kanban-form-group">
                    <label>Condição (Dias)</label>
                    <input type="number" id="auto-days" class="w-full border rounded p-2" min="1" value="7">
                </div>
                <div class="kanban-form-group">
                    <label>Ação</label>
                    <select id="auto-action" class="w-full border rounded p-2">
                        <option value="archive">Arquivar Card</option>
                        <option value="notify">Notificar Responsável</option>
                    </select>
                </div>
            </div>
            <div class="kanban-modal-footer">
                <button type="button" onclick="closeAutomationModal()" class="kanban-btn-secondary">Cancelar</button>
                <button type="submit" class="kanban-btn-primary">Salvar Regra</button>
            </div>
        </form>
    </div>
</div>

<script>
// Variáveis globais passadas do PHP
const currentBoardId = <?= $currentBoardId ?>;
const currentUserId = <?= $userId ?>;
const availableLabels = <?= json_encode($labels) ?>;
</script>
<script src="assets/js/kanban.js?v=<?= time() ?>"></script>

<?php endif; // Fim do if ($tablesExist) ?>

<?php require_once 'includes/footer_spa.php'; ?>
