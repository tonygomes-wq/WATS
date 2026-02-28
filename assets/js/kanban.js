// Variáveis globais
let cards = {};
let selectedColumnId = null;
let currentCardId = null;
let pollingInterval = null;
let lastUpdateTime = Date.now();

// Inicialização
document.addEventListener('DOMContentLoaded', function () {
    loadCards();
    initSortable();
    startPolling();

    // Fechar menus ao clicar fora
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.kanban-context-menu') && !e.target.closest('.kanban-btn-icon-sm')) {
            document.getElementById('column-context-menu').classList.add('hidden');
        }
    });

    // Filtros de URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('user')) {
        document.getElementById('filter-assignee').value = urlParams.get('user');
    }
    
    // Parar polling quando sair da página
    window.addEventListener('beforeunload', stopPolling);
    
    // Pausar polling quando aba não está visível
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopPolling();
        } else {
            startPolling();
        }
    });
});

// Polling automático - atualiza cards a cada 30 segundos
function startPolling() {
    if (pollingInterval) return;
    
    pollingInterval = setInterval(async () => {
        try {
            await loadCards(true); // true = silent (sem loading)
            console.log('[Kanban] Cards atualizados via polling');
        } catch (error) {
            console.error('[Kanban] Erro no polling:', error);
        }
    }, 30000); // 30 segundos
}

function stopPolling() {
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
    }
}

// Carregar cards do quadro
async function loadCards(silent = false) {
    try {
        const response = await fetch(`api/kanban/cards.php?board_id=${currentBoardId}`);
        const data = await response.json();

        if (data.success) {
            cards = {};
            let totalCards = 0;
            let totalValue = 0;

            // Só limpar colunas se não for silent ou se houver mudanças
            if (!silent) {
                document.querySelectorAll('.kanban-column-cards').forEach(col => {
                    col.innerHTML = '';
                });
            }

            data.cards.forEach(card => {
                if (!cards[card.column_id]) {
                    cards[card.column_id] = [];
                }
                cards[card.column_id].push(card);
                totalCards++;
                totalValue += parseFloat(card.value) || 0;
            });

            // Renderizar apenas se não for silent ou se houver mudanças
            if (!silent) {
                Object.keys(cards).forEach(columnId => {
                    renderColumnCards(columnId, cards[columnId]);
                });
            } else {
                // Modo silent: verificar se houve mudanças antes de re-renderizar
                const currentCardCount = document.querySelectorAll('.kanban-card').length;
                if (currentCardCount !== totalCards) {
                    Object.keys(cards).forEach(columnId => {
                        renderColumnCards(columnId, cards[columnId]);
                    });
                }
            }

            document.getElementById('total-cards').textContent = totalCards;
            document.getElementById('total-value').textContent = formatCurrency(totalValue);

            updateColumnCounts();

            // Reaplicar filtros se houver
            filterCards();
            
            lastUpdateTime = Date.now();
        }
    } catch (error) {
        console.error('Erro ao carregar cards:', error);
        if (!silent) {
            showToast('Erro ao carregar cards', 'error');
        }
    }
}

// Renderizar cards de uma coluna
function renderColumnCards(columnId, columnCards) {
    const container = document.getElementById(`column-${columnId}`);
    if (!container) return;

    container.innerHTML = '';

    columnCards.sort((a, b) => a.position - b.position).forEach(card => {
        container.appendChild(createCardElement(card));
    });
}

// Criar elemento do card
function createCardElement(card) {
    const div = document.createElement('div');
    div.className = 'kanban-card';
    div.dataset.cardId = card.id;
    div.onclick = () => openCardModal(card.id);
    div.style.position = 'relative'; // Garantir que o badge se posicione dentro do card

    if (card.priority === 'urgent') div.classList.add('kanban-card-urgent');
    if (card.priority === 'high') div.classList.add('kanban-card-high');

    if (card.due_date && new Date(card.due_date) < new Date()) {
        div.classList.add('kanban-card-overdue');
    }

    let labelsHtml = '';
    // Labels removidos - usando apenas badge do canal
    
    // Badge do canal de origem
    let channelBadgeHtml = '';
    if (card.source_channel) {
        const channelIcons = {
            'whatsapp': { icon: 'fab fa-whatsapp', color: '#25D366', name: 'WhatsApp' },
            'telegram': { icon: 'fab fa-telegram', color: '#0088cc', name: 'Telegram' },
            'facebook': { icon: 'fab fa-facebook-messenger', color: '#0084ff', name: 'Facebook' },
            'messenger': { icon: 'fab fa-facebook-messenger', color: '#0084ff', name: 'Messenger' },
            'instagram': { icon: 'fab fa-instagram', color: '#E4405F', name: 'Instagram' },
            'email': { icon: 'fas fa-envelope', color: '#EA4335', name: 'Email' },
            'teams': { icon: 'fas fa-users', color: '#5558AF', name: 'Microsoft Teams' }
        };
        
        const channel = channelIcons[card.source_channel.toLowerCase()] || channelIcons['whatsapp'];
        channelBadgeHtml = `
            <div class="channel-badge-icon" style="position: absolute; top: 6px; right: 6px; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background-color: ${channel.color}; color: white; box-shadow: 0 3px 8px rgba(0,0,0,0.25); z-index: 10; border: 3px solid var(--kanban-surface);" title="Origem: ${channel.name}">
                <i class="${channel.icon}" style="font-size: 14px;"></i>
            </div>
        `;
    }

    let indicatorsHtml = '<div class="kanban-card-indicators">';

    if (card.conversation_id) {
        indicatorsHtml += '<span class="kanban-card-indicator" title="Conversa vinculada"><i class="fas fa-comments"></i></span>';
    }

    if (card.due_date) {
        const dueDate = new Date(card.due_date);
        const isOverdue = dueDate < new Date();
        indicatorsHtml += `<span class="kanban-card-indicator ${isOverdue ? 'overdue' : ''}" title="Vencimento: ${formatDate(card.due_date)}">
            <i class="fas fa-clock"></i> ${formatDateShort(card.due_date)}
        </span>`;
    }

    if (card.comments_count > 0) {
        indicatorsHtml += `<span class="kanban-card-indicator" title="${card.comments_count} comentário(s)">
            <i class="fas fa-comment"></i> ${card.comments_count}
        </span>`;
    }

    indicatorsHtml += '</div>';

    div.innerHTML = `
        ${channelBadgeHtml}
        <div class="kanban-card-title" style="padding-right: 40px;">${escapeHtml(card.title)}</div>
        ${card.contact_name ? `<div class="kanban-card-contact"><i class="fas fa-user"></i> ${escapeHtml(card.contact_name)}</div>` : ''}
        ${card.contact_phone ? `<div class="kanban-card-phone"><i class="fas fa-phone"></i> ${formatPhone(card.contact_phone)}</div>` : ''}
        ${card.value > 0 ? `<div class="kanban-card-value"><i class="fas fa-dollar-sign"></i> R$ ${formatCurrency(card.value)}</div>` : ''}
        ${indicatorsHtml}
        ${card.assigned_name ? `<div class="kanban-card-assigned"><i class="fas fa-user-circle"></i> ${escapeHtml(card.assigned_name)}</div>` : ''}
    `;

    return div;
}

// Inicializar Sortable
function initSortable() {
    document.querySelectorAll('.kanban-column-cards').forEach(column => {
        new Sortable(column, {
            group: 'kanban-cards',
            animation: 150,
            ghostClass: 'kanban-card-ghost',
            chosenClass: 'kanban-card-chosen',
            dragClass: 'kanban-card-drag',
            handle: '.kanban-card',
            onEnd: function (evt) {
                const cardId = evt.item.dataset.cardId;
                const newColumnId = evt.to.dataset.columnId;
                const newPosition = evt.newIndex;

                moveCard(cardId, newColumnId, newPosition);
            }
        });
    });

    new Sortable(document.getElementById('kanban-container'), {
        animation: 150,
        handle: '.kanban-column-header',
        draggable: '.kanban-column',
        ghostClass: 'kanban-column-ghost',
        onEnd: function (evt) {
            reorderColumns();
        }
    });
}

// Mover card
async function moveCard(cardId, newColumnId, newPosition) {
    try {
        const response = await fetch('api/kanban/move_card.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                card_id: cardId,
                column_id: newColumnId,
                position: newPosition
            })
        });

        const data = await response.json();

        if (data.success) {
            updateColumnCounts();
            showToast('Card movido com sucesso', 'success');
        } else {
            showToast(data.error || 'Erro ao mover card', 'error');
            loadCards();
        }
    } catch (error) {
        console.error('Erro ao mover card:', error);
        showToast('Erro ao mover card', 'error');
        loadCards();
    }
}

// Reordenar colunas
async function reorderColumns() {
    const columns = document.querySelectorAll('.kanban-column');
    const order = Array.from(columns).map((col, index) => ({
        id: col.dataset.columnId,
        position: index
    }));

    try {
        await fetch('api/kanban/columns.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reorder', columns: order })
        });
    } catch (error) {
        console.error('Erro ao reordenar colunas:', error);
    }
}

// Atualizar contadores das colunas
function updateColumnCounts() {
    document.querySelectorAll('.kanban-column').forEach(column => {
        const columnId = column.dataset.columnId;
        const count = column.querySelectorAll('.kanban-card').length;
        const countEl = document.getElementById(`count-${columnId}`);
        if (countEl) countEl.textContent = count;
    });
}

// Abrir modal de card
async function openCardModal(cardId = null) {
    const modal = document.getElementById('card-modal');
    const form = document.getElementById('card-form');

    // Reset form
    form.reset();
    document.getElementById('card-id').value = '';
    document.querySelectorAll('#labels-selector input[type="checkbox"]').forEach(cb => cb.checked = false);

    // Esconder seções de edição
    document.getElementById('linked-conversation-section').classList.add('hidden');
    document.getElementById('comments-section').classList.add('hidden');
    document.getElementById('btn-archive-card').classList.add('hidden');
    document.getElementById('btn-open-chat').classList.add('hidden');

    if (cardId) {
        // Modo edição
        document.getElementById('card-modal-title').innerHTML = '<i class="fas fa-edit"></i> Editar Card';
        currentCardId = cardId;

        try {
            const response = await fetch(`api/kanban/cards.php?id=${cardId}`);
            const data = await response.json();

            if (data.success && data.card) {
                const card = data.card;

                document.getElementById('card-id').value = card.id;
                document.getElementById('card-column-id').value = card.column_id;
                document.getElementById('card-title').value = card.title;
                document.getElementById('card-contact-name').value = card.contact_name || '';
                document.getElementById('card-contact-phone').value = card.contact_phone || '';
                document.getElementById('card-value').value = card.value || '';
                document.getElementById('card-priority').value = card.priority;
                document.getElementById('card-due-date').value = card.due_date || '';
                document.getElementById('card-assigned').value = card.assigned_to || '';
                document.getElementById('card-description').value = card.description || '';

                // Marcar labels
                if (card.labels) {
                    card.labels.forEach(label => {
                        const cb = document.querySelector(`#labels-selector input[value="${label.id}"]`);
                        if (cb) cb.checked = true;
                    });
                }

                // Mostrar botões de edição
                document.getElementById('btn-archive-card').classList.remove('hidden');

                // Conversa vinculada
                if (card.conversation_id) {
                    document.getElementById('linked-conversation-section').classList.remove('hidden');
                    document.getElementById('btn-open-chat').classList.remove('hidden');
                    loadLinkedConversation(card.conversation_id);
                }

                // Comentários
                document.getElementById('comments-section').classList.remove('hidden');
                loadCardComments(cardId);
            }
        } catch (error) {
            console.error('Erro ao carregar card:', error);
            showToast('Erro ao carregar card', 'error');
            return;
        }
    } else {
        // Modo criação
        document.getElementById('card-modal-title').innerHTML = '<i class="fas fa-plus-circle"></i> Novo Card';
        currentCardId = null;

        if (selectedColumnId) {
            document.getElementById('card-column-id').value = selectedColumnId;
        }
    }

    modal.classList.remove('hidden');
}

// Fechar modal de card
function closeCardModal() {
    document.getElementById('card-modal').classList.add('hidden');
    currentCardId = null;
    selectedColumnId = null;
}

// Salvar card
async function saveCard(event) {
    event.preventDefault();

    const cardId = document.getElementById('card-id').value;
    const columnId = document.getElementById('card-column-id').value;

    // Coletar labels selecionadas
    const selectedLabels = [];
    document.querySelectorAll('#labels-selector input[type="checkbox"]:checked').forEach(cb => {
        selectedLabels.push(cb.value);
    });

    const cardData = {
        column_id: columnId || document.querySelector('.kanban-column').dataset.columnId,
        title: document.getElementById('card-title').value,
        contact_name: document.getElementById('card-contact-name').value,
        contact_phone: document.getElementById('card-contact-phone').value,
        value: document.getElementById('card-value').value || 0,
        priority: document.getElementById('card-priority').value,
        due_date: document.getElementById('card-due-date').value,
        assigned_to: document.getElementById('card-assigned').value,
        description: document.getElementById('card-description').value,
        labels: selectedLabels
    };

    try {
        const url = cardId ? `api/kanban/cards.php?id=${cardId}` : 'api/kanban/cards.php';
        const method = cardId ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(cardData)
        });

        const data = await response.json();

        if (data.success) {
            closeCardModal();
            loadCards();
            showToast(cardId ? 'Card atualizado!' : 'Card criado!', 'success');
        } else {
            showToast(data.error || 'Erro ao salvar card', 'error');
        }
    } catch (error) {
        console.error('Erro ao salvar card:', error);
        showToast('Erro ao salvar card', 'error');
    }
}

// Abrir modal para novo card em coluna específica
function openNewCardModal(columnId = null) {
    selectedColumnId = columnId;
    openCardModal(null);
}

// Arquivar card
async function archiveCard() {
    if (!currentCardId) return;

    if (!confirm('Deseja arquivar este card?')) return;

    try {
        const response = await fetch(`api/kanban/cards.php?id=${currentCardId}`, {
            method: 'DELETE'
        });

        const data = await response.json();

        if (data.success) {
            closeCardModal();
            loadCards();
            showToast('Card arquivado!', 'success');
        } else {
            showToast(data.error || 'Erro ao arquivar card', 'error');
        }
    } catch (error) {
        console.error('Erro ao arquivar card:', error);
        showToast('Erro ao arquivar card', 'error');
    }
}

// Carregar conversa vinculada
async function loadLinkedConversation(conversationId) {
    try {
        const response = await fetch(`api/chat_conversations.php?id=${conversationId}`);
        const data = await response.json();

        if (data.success && data.conversation) {
            const conv = data.conversation;
            document.getElementById('linked-conversation-info').innerHTML = `
                <div class="kanban-linked-conv-header">
                    <strong>${escapeHtml(conv.contact_name || conv.phone)}</strong>
                    <span class="kanban-linked-conv-status">${conv.status}</span>
                </div>
                <div class="kanban-linked-conv-preview">
                    ${escapeHtml(conv.last_message_text || 'Sem mensagens')}
                </div>
                <small>Última mensagem: ${formatDateTime(conv.last_message_time)}</small>
            `;
        }
    } catch (error) {
        console.error('Erro ao carregar conversa:', error);
    }
}

// Carregar comentários do card
async function loadCardComments(cardId) {
    try {
        const response = await fetch(`api/kanban/comments.php?card_id=${cardId}`);
        const data = await response.json();

        const container = document.getElementById('card-comments');
        container.innerHTML = '';

        if (data.success && data.comments.length > 0) {
            data.comments.forEach(comment => {
                container.innerHTML += `
                    <div class="kanban-comment">
                        <div class="kanban-comment-header">
                            <strong>${escapeHtml(comment.user_name)}</strong>
                            <span>${formatDateTime(comment.created_at)}</span>
                        </div>
                        <div class="kanban-comment-text">${escapeHtml(comment.comment)}</div>
                    </div>
                `;
            });
        } else {
            container.innerHTML = '<p class="kanban-no-comments">Nenhum comentário ainda</p>';
        }
    } catch (error) {
        console.error('Erro ao carregar comentários:', error);
    }
}

// Adicionar comentário
async function addComment() {
    const input = document.getElementById('new-comment');
    const comment = input.value.trim();

    if (!comment || !currentCardId) return;

    try {
        const response = await fetch('api/kanban/comments.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                card_id: currentCardId,
                comment: comment
            })
        });

        const data = await response.json();

        if (data.success) {
            input.value = '';
            loadCardComments(currentCardId);
            showToast('Comentário adicionado!', 'success');
        }
    } catch (error) {
        console.error('Erro ao adicionar comentário:', error);
    }
}

// Abrir chat vinculado
function openLinkedChat() {
    const cardId = document.getElementById('card-id').value;
    // Buscar conversation_id e abrir chat
    if (currentCardId) {
        window.location.href = `chat.php?conversation_from_card=${currentCardId}`;
    }
}

// Modal de coluna
function openColumnMenu(columnId) {
    selectedColumnId = columnId;
    const menu = document.getElementById('column-context-menu');
    const btn = event.target.closest('button');
    const rect = btn.getBoundingClientRect();

    menu.style.top = `${rect.bottom + 5}px`;
    menu.style.left = `${rect.left}px`;
    menu.classList.remove('hidden');
}

function openNewColumnModal() {
    document.getElementById('column-modal').classList.remove('hidden');
    document.getElementById('column-form').reset();
    document.getElementById('column-id').value = '';
    document.getElementById('column-modal-title').innerHTML = '<i class="fas fa-columns"></i> Nova Coluna';
    document.getElementById('btn-delete-column').classList.add('hidden');
}

function closeColumnModal() {
    document.getElementById('column-modal').classList.add('hidden');
}

async function editColumn() {
    if (!selectedColumnId) return;

    document.getElementById('column-context-menu').classList.add('hidden');

    try {
        const response = await fetch(`api/kanban/columns.php?id=${selectedColumnId}`);
        const data = await response.json();

        if (data.success && data.column) {
            const col = data.column;
            document.getElementById('column-id').value = col.id;
            document.getElementById('column-name').value = col.name;
            document.getElementById('column-color').value = col.color;
            document.getElementById('column-icon').value = col.icon || '';
            document.getElementById('column-wip').value = col.wip_limit || '';
            document.getElementById('column-is-final').checked = col.is_final == 1;

            document.getElementById('column-modal-title').innerHTML = '<i class="fas fa-edit"></i> Editar Coluna';
            document.getElementById('btn-delete-column').classList.remove('hidden');
            document.getElementById('column-modal').classList.remove('hidden');
        }
    } catch (error) {
        console.error('Erro ao carregar coluna:', error);
    }
}

async function saveColumn(event) {
    event.preventDefault();

    const columnId = document.getElementById('column-id').value;
    const columnData = {
        board_id: currentBoardId,
        name: document.getElementById('column-name').value,
        color: document.getElementById('column-color').value,
        icon: document.getElementById('column-icon').value,
        wip_limit: document.getElementById('column-wip').value || null,
        is_final: document.getElementById('column-is-final').checked ? 1 : 0
    };

    try {
        const url = columnId ? `api/kanban/columns.php?id=${columnId}` : 'api/kanban/columns.php';
        const method = columnId ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(columnData)
        });

        const data = await response.json();

        if (data.success) {
            closeColumnModal();
            location.reload(); // Recarregar para mostrar nova coluna
        } else {
            showToast(data.error || 'Erro ao salvar coluna', 'error');
        }
    } catch (error) {
        console.error('Erro ao salvar coluna:', error);
    }
}

async function deleteColumn() {
    const columnId = document.getElementById('column-id').value;
    if (!columnId) return;

    if (!confirm('Deseja excluir esta coluna? Os cards serão movidos para a primeira coluna.')) return;

    try {
        const response = await fetch(`api/kanban/columns.php?id=${columnId}`, {
            method: 'DELETE'
        });

        const data = await response.json();

        if (data.success) {
            closeColumnModal();
            location.reload();
        } else {
            showToast(data.error || 'Erro ao excluir coluna', 'error');
        }
    } catch (error) {
        console.error('Erro ao excluir coluna:', error);
    }
}

// Modal de quadros
function openBoardModal() {
    document.getElementById('board-modal').classList.remove('hidden');
}

function closeBoardModal() {
    document.getElementById('board-modal').classList.add('hidden');
}

function openNewBoardForm() {
    document.getElementById('new-board-form').classList.toggle('hidden');
}

async function createNewBoard() {
    const name = document.getElementById('new-board-name').value.trim();
    const color = document.getElementById('new-board-color').value;

    if (!name) {
        showToast('Digite o nome do quadro', 'error');
        return;
    }

    try {
        const response = await fetch('api/kanban/boards.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, color })
        });

        const data = await response.json();

        if (data.success) {
            window.location.href = `kanban.php?board=${data.board_id}`;
            // changeBoard(data.board_id);
        } else {
            showToast(data.error || 'Erro ao criar quadro', 'error');
        }
    } catch (error) {
        console.error('Erro ao criar quadro:', error);
    }
}

function changeBoard(boardId) {
    if (boardId) {
        window.location.href = `kanban.php?board=${boardId}`;
    }
}

// Modal de labels
function openLabelsModal() {
    document.getElementById('column-context-menu').classList.add('hidden');
    loadLabels();
    document.getElementById('labels-modal').classList.remove('hidden');
}

function closeLabelsModal() {
    document.getElementById('labels-modal').classList.add('hidden');
}

async function loadLabels() {
    try {
        const response = await fetch(`api/kanban/labels.php?board_id=${currentBoardId}`);
        const data = await response.json();

        const container = document.getElementById('labels-list');
        container.innerHTML = '';

        if (data.success && data.labels.length > 0) {
            data.labels.forEach(label => {
                container.innerHTML += `
                    <div class="kanban-label-item">
                        <span class="kanban-label-color" style="background-color: ${label.color}"></span>
                        <span class="kanban-label-name">${escapeHtml(label.name)}</span>
                        <button onclick="deleteLabel(${label.id})" class="kanban-btn-icon-sm kanban-btn-danger-icon">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
            });
        }
    } catch (error) {
        console.error('Erro ao carregar labels:', error);
    }
}

async function createLabel() {
    const name = document.getElementById('new-label-name').value.trim();
    const color = document.getElementById('new-label-color').value;

    if (!name) return;

    try {
        const response = await fetch('api/kanban/labels.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ board_id: currentBoardId, name, color })
        });

        const data = await response.json();

        if (data.success) {
            document.getElementById('new-label-name').value = '';
            loadLabels();
            location.reload(); // Atualizar seletor de labels
        }
    } catch (error) {
        console.error('Erro ao criar label:', error);
    }
}

async function deleteLabel(labelId) {
    if (!confirm('Excluir esta etiqueta?')) return;

    try {
        await fetch(`api/kanban/labels.php?id=${labelId}`, { method: 'DELETE' });
        loadLabels();
        location.reload();
    } catch (error) {
        console.error('Erro ao excluir label:', error);
    }
}

// Filtros
function filterCards() {
    const search = document.getElementById('kanban-search').value.toLowerCase();
    const assignee = document.getElementById('filter-assignee').value;
    const priority = document.getElementById('filter-priority').value;
    const label = document.getElementById('filter-label').value;

    document.querySelectorAll('.kanban-card').forEach(card => {
        let show = true;
        const cardId = card.dataset.cardId;
        
        // Buscar dados do card
        let cardData = null;
        for (const colId in cards) {
            const found = cards[colId].find(c => c.id == cardId);
            if (found) {
                cardData = found;
                break;
            }
        }

        // Filtro de busca (texto)
        if (search && cardData) {
            const searchText = [
                cardData.title,
                cardData.contact_name,
                cardData.contact_phone,
                cardData.description
            ].filter(Boolean).join(' ').toLowerCase();
            
            if (!searchText.includes(search)) show = false;
        }

        // Filtro de responsável
        if (assignee && cardData) {
            if (cardData.assigned_to != assignee) show = false;
        }

        // Filtro de prioridade
        if (priority && cardData) {
            if (cardData.priority !== priority) show = false;
        }

        // Filtro de label
        if (label && cardData) {
            const hasLabel = cardData.labels && cardData.labels.some(l => l.id == label);
            if (!hasLabel) show = false;
        }

        card.style.display = show ? '' : 'none';
    });

    updateColumnCounts();
}

// Limpar todos os filtros
function clearFilters() {
    document.getElementById('kanban-search').value = '';
    document.getElementById('filter-assignee').value = '';
    document.getElementById('filter-priority').value = '';
    document.getElementById('filter-label').value = '';
    filterCards();
}

// Arquivados
let showingArchived = false;

function toggleArchivedView() {
    showingArchived = !showingArchived;
    const btn = document.getElementById('btn-archived');

    if (showingArchived) {
        btn.classList.add('active');
        // Adicionar classe visual ao botão para indicar ativo
        btn.classList.add('bg-blue-600', 'text-white');
        btn.classList.remove('bg-white', 'text-gray-700');
        loadArchivedCards();
    } else {
        btn.classList.remove('active');
        btn.classList.remove('bg-blue-600', 'text-white');
        btn.classList.add('bg-white', 'text-gray-700');
        loadCards();
    }
}

async function loadArchivedCards() {
    try {
        const response = await fetch(`api/kanban/cards.php?board_id=${currentBoardId}&archived=1`);
        const data = await response.json();

        if (data.success) {
            // Limpar colunas
            document.querySelectorAll('.kanban-column-cards').forEach(col => col.innerHTML = '');

            // Renderizar arquivados (simplificado: joga tudo na coluna original ou primeira)
            if (data.cards && data.cards.length > 0) {
                const cardsByCol = {};
                data.cards.forEach(card => {
                    if (!cardsByCol[card.column_id]) cardsByCol[card.column_id] = [];
                    cardsByCol[card.column_id].push(card);
                });

                Object.keys(cardsByCol).forEach(colId => {
                    renderColumnCards(colId, cardsByCol[colId]);
                });
            }

            showToast(`${data.cards?.length || 0} cards arquivados`, 'info');
        }
    } catch (error) {
        console.error('Erro ao carregar arquivados:', error);
    }
}

// Arquivar todos da coluna
async function archiveAllInColumn() {
    if (!selectedColumnId) return;

    if (!confirm('Arquivar todos os cards desta coluna?')) return;

    document.getElementById('column-context-menu').classList.add('hidden');

    try {
        const response = await fetch('api/kanban/cards.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'archive_column',
                column_id: selectedColumnId
            })
        });

        const data = await response.json();

        if (data.success) {
            loadCards();
            showToast('Cards arquivados!', 'success');
        } else {
            showToast(data.error || 'Erro ao arquivar', 'error');
        }
    } catch (error) {
        console.error('Erro ao arquivar cards:', error);
    }
}

// Automação
function openAutomationModal() {
    if (!selectedColumnId) return;
    document.getElementById('column-context-menu').classList.add('hidden');
    document.getElementById('automation-column-id').value = selectedColumnId;
    document.getElementById('automation-modal').classList.remove('hidden');
}

function closeAutomationModal() {
    document.getElementById('automation-modal').classList.add('hidden');
}

async function saveAutomation(event) {
    event.preventDefault();
    const columnId = document.getElementById('automation-column-id').value;
    const trigger = document.getElementById('auto-trigger').value;
    const days = document.getElementById('auto-days').value;
    const action = document.getElementById('auto-action').value;

    try {
        const response = await fetch('api/kanban/save_automation.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                column_id: columnId,
                trigger: trigger,
                days: days,
                action: action
            })
        });

        const data = await response.json();

        if (data.success) {
            showToast('Regra salva com sucesso!', 'success');
            closeAutomationModal();
        } else {
            showToast(data.error || 'Erro ao salvar regra', 'error');
        }
    } catch (error) {
        console.error('Erro ao salvar automação:', error);
        showToast('Erro ao salvar regra', 'error');
    }
}


// Utilitários
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatCurrency(value) {
    return parseFloat(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
}

function formatPhone(phone) {
    if (!phone) return '';
    const cleaned = phone.replace(/\D/g, '');
    if (cleaned.length === 11) {
        return `(${cleaned.slice(0, 2)}) ${cleaned.slice(2, 7)}-${cleaned.slice(7)}`;
    }
    return phone;
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('pt-BR');
}

function formatDateShort(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return `${date.getDate()}/${date.getMonth() + 1}`;
}

function formatDateTime(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleString('pt-BR');
}

function showToast(message, type = 'info') {
    if (type === 'error') {
        console.error(message);
    }
    const toast = document.createElement('div');
    toast.className = `kanban-toast kanban-toast-${type}`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${message}</span>
    `;
    document.body.appendChild(toast);

    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Atalhos de teclado
document.addEventListener('keydown', function(e) {
    // ESC - Fechar modais
    if (e.key === 'Escape') {
        closeCardModal();
        closeColumnModal();
        closeBoardModal();
        closeLabelsModal();
        closeAutomationModal();
        document.getElementById('column-context-menu').classList.add('hidden');
    }
    
    // Ctrl+N - Novo card
    if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        openNewCardModal();
    }
    
    // F5 ou Ctrl+R - Atualizar cards
    if (e.key === 'F5' || (e.ctrlKey && e.key === 'r')) {
        e.preventDefault();
        loadCards();
        showToast('Cards atualizados!', 'info');
    }
});

// Atualização manual
function refreshKanban() {
    loadCards();
    showToast('Kanban atualizado!', 'success');
}

// Exportar cards para CSV
async function exportToCSV() {
    try {
        const allCards = [];
        for (const colId in cards) {
            cards[colId].forEach(card => {
                allCards.push({
                    'Coluna': card.column_name || '',
                    'Título': card.title || '',
                    'Contato': card.contact_name || '',
                    'Telefone': card.contact_phone || '',
                    'Valor': card.value || 0,
                    'Prioridade': card.priority || '',
                    'Responsável': card.assigned_name || '',
                    'Vencimento': card.due_date || '',
                    'Criado em': card.created_at || ''
                });
            });
        }
        
        if (allCards.length === 0) {
            showToast('Nenhum card para exportar', 'info');
            return;
        }
        
        // Criar CSV
        const headers = Object.keys(allCards[0]);
        const csvContent = [
            headers.join(';'),
            ...allCards.map(row => headers.map(h => `"${(row[h] || '').toString().replace(/"/g, '""')}"`).join(';'))
        ].join('\n');
        
        // Download
        const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `kanban_export_${new Date().toISOString().split('T')[0]}.csv`;
        link.click();
        
        showToast('Exportação concluída!', 'success');
    } catch (error) {
        console.error('Erro ao exportar:', error);
        showToast('Erro ao exportar', 'error');
    }
}
