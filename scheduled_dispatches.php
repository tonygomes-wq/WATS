<?php
$page_title = 'Agendamentos de Disparo';
require_once 'includes/header_spa.php';

$userId = $_SESSION['user_id'];

// Categorias do usuário
$stmt = $pdo->prepare("SELECT c.id, c.name, COUNT(cc.contact_id) AS total
    FROM categories c
    LEFT JOIN contact_categories cc ON c.id = cc.category_id
    WHERE c.user_id = ?
    GROUP BY c.id
    ORDER BY c.name");
$stmt->execute([$userId]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contatos do usuário
$stmt = $pdo->prepare("SELECT id, name, phone FROM contacts WHERE user_id = ? ORDER BY name, phone");
$stmt->execute([$userId]);
$contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$defaultDate = date('Y-m-d\TH:i', strtotime('+15 minutes'));
?>

<div class="refined-container">
    <div class="refined-card" style="background: linear-gradient(135deg, #10b981, #059669); border: none; color: white;">
        <div>
            <h1 style="font-size: 24px; font-weight: 600; margin-bottom: 8px; color: white;">Agendamentos de Disparo</h1>
            <p style="font-size: 13px; opacity: 0.9;">Programe suas mensagens para serem enviadas automaticamente em uma data e hora específicas.</p>
        </div>
        <div class="mt-4 md:mt-0 flex items-center gap-3 text-sm">
            <div class="bg-white/15 rounded-lg px-4 py-2">
                <span class="uppercase text-xs tracking-wide">Fuso horário</span>
                <div class="font-semibold"><?php echo date_default_timezone_get(); ?></div>
            </div>
            <div class="bg-white/15 rounded-lg px-4 py-2">
                <span class="uppercase text-xs tracking-wide">Status plataforma</span>
                <div class="font-semibold">
                    <i class="fas fa-circle text-green-300 mr-1"></i>Ativa
                </div>
            </div>
        </div>
    </div>

    <div class="refined-card">
        <div style="border-bottom: 0.5px solid var(--border); display: flex; gap: var(--space-2); margin-bottom: var(--space-6);">
            <button class="tab-button" style="padding: var(--space-3) var(--space-4); font-size: 13px; font-weight: 600; color: var(--accent-primary); border-bottom: 2px solid var(--accent-primary); background: none; border-top: none; border-left: none; border-right: none; cursor: pointer;" data-tab="scheduler">Novo Agendamento</button>
            <button class="tab-button" style="padding: var(--space-3) var(--space-4); font-size: 13px; font-weight: 600; color: var(--text-secondary); background: none; border: none; cursor: pointer;" data-tab="list">Meus Agendamentos</button>
        </div>

        <div id="tab-scheduler">
            <form id="scheduleForm" onsubmit="return submitSchedule(event)">
                <div class="refined-grid refined-grid-2">
                    <div class="refined-section">
                        <label class="refined-label">Mensagem</label>
                        <textarea id="scheduleMessage" class="refined-textarea" rows="6" placeholder="Escreva a mensagem que será enviada"></textarea>
                        <p style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Use macros como {nome} e {telefone} para personalizar.</p>
                    </div>
                    <div>
                        <div class="refined-section">
                            <label class="refined-label">Data e hora</label>
                            <input type="datetime-local" id="scheduleDatetime" value="<?php echo $defaultDate; ?>" class="refined-input">
                        </div>
                        <div style="background: var(--bg-body); border: 0.5px solid var(--border-subtle); border-radius: var(--radius-md); padding: var(--space-4);">
                            <h3 style="font-size: 13px; font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-2); display: flex; align-items: center; gap: 8px;"><i class="fas fa-layer-group" style="color: var(--accent-primary);"></i>Adicionar contatos por categoria</h3>
                            <div class="grid sm:grid-cols-2 gap-2">
                                <?php if (empty($categories)): ?>
                                    <p class="text-sm text-gray-500">Nenhuma categoria criada ainda.</p>
                                <?php else: ?>
                                    <?php foreach ($categories as $category): ?>
                                    <button type="button" class="border border-gray-200 rounded-lg px-3 py-2 text-left hover:border-green-400 hover:text-green-600 text-sm" onclick="addCategoryContacts(<?php echo (int)$category['id']; ?>, '<?php echo addslashes($category['name']); ?>')">
                                        <span class="font-semibold"><?php echo htmlspecialchars($category['name']); ?></span>
                                        <span class="block text-xs text-gray-500"><?php echo (int)$category['total']; ?> contato(s)</span>
                                    </button>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="refined-alert refined-alert-success" style="margin-top: var(--space-4);">
                            <i class="fas fa-check-circle"></i>
                            <div style="flex: 1;">
                                <h3 style="font-size: 13px; font-weight: 600; margin-bottom: 4px;">Resumo</h3>
                                <p style="font-size: 12px;">Contatos selecionados: <span id="selectedCount">0</span></p>
                                <div id="selectedPreview" style="font-size: 11px; margin-top: 8px; max-height: 96px; overflow-y: auto;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                    <div class="refined-section" style="border-top: 0.5px solid var(--border); padding-top: var(--space-6);">
                        <h3 class="refined-label" style="font-size: 14px; display: flex; align-items: center; gap: 8px; margin-bottom: var(--space-4);"><i class="fas fa-address-book" style="color: var(--accent-primary);"></i>Contatos</h3>
                        <div class="refined-action-bar">
                            <div class="refined-search" style="flex: 1;">
                                <i class="fas fa-search"></i>
                                <input type="text" id="contactSearch" placeholder="Buscar por nome ou telefone" class="refined-input">
                            </div>
                            <div class="refined-action-group">
                                <button type="button" class="refined-btn refined-btn-sm" onclick="selectAllContacts(true)" style="background: var(--accent-primary); color: white; border: none; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s;"><i class="fas fa-check-double"></i>Selecionar todos</button>
                                <button type="button" class="refined-btn refined-btn-sm" onclick="selectAllContacts(false)" style="background: transparent; color: var(--text-secondary); border: 1px solid var(--border); padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s;"><i class="fas fa-times"></i>Limpar</button>
                            </div>
                        </div>
                        <div style="border: 0.5px solid var(--border); border-radius: var(--radius-md); max-height: 288px; overflow-y: auto;" id="contactsList"></div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-3 rounded-xl flex items-center gap-2">
                            <i class="fas fa-calendar-check"></i> Agendar disparo
                        </button>
                    </div>
            </form>
        </div>

        <div id="tab-list" class="hidden p-6 space-y-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="flex items-center gap-2">
                    <label class="text-sm font-semibold text-gray-700">Status</label>
                    <select id="statusFilter" class="border rounded-xl px-3 py-2 text-sm">
                        <option value="">Todos</option>
                        <option value="pending">Pendente</option>
                        <option value="processing">Processando</option>
                        <option value="completed">Concluído</option>
                        <option value="failed">Falhou</option>
                        <option value="cancelled">Cancelado</option>
                    </select>
                </div>
                <button type="button" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-green-700 border border-green-200 rounded-lg hover:bg-green-50" onclick="exportSchedulesCSV()">
                    <i class="fas fa-file-export"></i> Exportar CSV
                </button>
            </div>

            <div class="border rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Mensagem</th>
                            <th class="px-4 py-3">Disparo</th>
                            <th class="px-4 py-3">Progresso</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="schedulesTable" class="divide-y"></tbody>
                </table>
            </div>
            <div id="schedulePagination" class="flex justify-between text-sm text-gray-600"></div>
        </div>
    </div>
</div>

<style>
/* Remover efeito hover branco da lista de contatos */
#contactsList label:hover {
    background: transparent !important;
}

/* Estilos para os botões de ação */
.refined-btn:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

.refined-btn-sm:hover {
    opacity: 0.9;
}

/* Botão Limpar com hover suave */
button[onclick="selectAllContacts(false)"]:hover {
    background: var(--bg-tertiary) !important;
    border-color: var(--border-emphasis) !important;
}

/* Botão Selecionar todos com hover */
button[onclick="selectAllContacts(true)"]:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}
</style>

<script>
const contactsData = <?php echo json_encode($contacts, JSON_UNESCAPED_UNICODE); ?>;
let filteredContacts = [...contactsData];
let selectedContacts = [];
let schedulesState = { page: 1, status: '' };
let lastSchedules = [];

function initTabs() {
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-button').forEach(b => {
                b.classList.remove('text-green-600', 'border-b-2', 'border-green-600');
                b.classList.add('text-gray-500');
            });
            btn.classList.add('text-green-600', 'border-b-2', 'border-green-600');
            btn.classList.remove('text-gray-500');

            const tab = btn.dataset.tab;
            document.getElementById('tab-scheduler').classList.add('hidden');
            document.getElementById('tab-list').classList.add('hidden');
            document.getElementById(`tab-${tab}`).classList.remove('hidden');
        });
    });
}

function renderContacts(list = filteredContacts) {
    const container = document.getElementById('contactsList');
    if (list.length === 0) {
        container.innerHTML = '<p class="p-4 text-sm text-gray-500">Nenhum contato encontrado.</p>';
        return;
    }
    container.innerHTML = list.map(contact => {
        const checked = selectedContacts.find(c => c.id === contact.id) ? 'checked' : '';
        const safeName = contact.name ? contact.name.replace(/"/g, '&quot;') : 'Sem nome';
        return `
            <label class="flex items-center gap-3 p-3 border-b last:border-b-0 cursor-pointer text-sm" style="transition: none;">
                <input type="checkbox" class="contact-checkbox" data-id="${contact.id}" ${checked} onchange="toggleContact(${contact.id})">
                <div>
                    <div class="font-semibold text-gray-800">${safeName}</div>
                    <div class="text-gray-500 text-xs">${contact.phone}</div>
                </div>
            </label>
        `;
    }).join('');
}

function toggleContact(contactId) {
    const contact = contactsData.find(c => c.id === contactId);
    if (!contact) return;

    const index = selectedContacts.findIndex(c => c.id === contactId);
    if (index >= 0) {
        selectedContacts.splice(index, 1);
    } else {
        selectedContacts.push(contact);
    }
    updateSelectedPreview();
}

function selectAllContacts(select) {
    if (select) {
        selectedContacts = [...filteredContacts];
    } else {
        selectedContacts = [];
    }
    document.querySelectorAll('.contact-checkbox').forEach(cb => cb.checked = select);
    updateSelectedPreview();
}

function updateSelectedPreview() {
    const countEl = document.getElementById('selectedCount');
    const previewEl = document.getElementById('selectedPreview');
    countEl.textContent = selectedContacts.length;
    previewEl.innerHTML = selectedContacts.slice(0, 10).map(c => `<div>${c.name || 'Sem nome'} - ${c.phone}</div>`).join('');
    if (selectedContacts.length > 10) {
        previewEl.innerHTML += `<div class="text-gray-500">+${selectedContacts.length - 10} mais...</div>`;
    }
    document.querySelectorAll('.contact-checkbox').forEach(cb => {
        const id = parseInt(cb.dataset.id, 10);
        cb.checked = selectedContacts.some(c => c.id === id);
    });
}

document.getElementById('contactSearch').addEventListener('input', (e) => {
    const term = e.target.value.toLowerCase();
    filteredContacts = contactsData.filter(c => {
        const name = (c.name || '').toLowerCase();
        const phone = (c.phone || '').toLowerCase();
        return name.includes(term) || phone.includes(term);
    });
    renderContacts();
});

async function addCategoryContacts(categoryId, name) {
    try {
        const res = await fetch(`/api/get_category_contacts.php?category_id=${categoryId}`);
        const data = await res.json();
        const added = [];
        (data.contacts || []).forEach(contact => {
            contact.id = parseInt(contact.id, 10);
            if (!selectedContacts.some(c => c.id === contact.id)) {
                selectedContacts.push(contact);
                added.push(contact);
            }
        });
        if (added.length) {
            updateSelectedPreview();
            renderContacts();
            alert(`${added.length} contato(s) da categoria "${name}" adicionados.`);
        } else {
            alert('Todos os contatos desta categoria já foram adicionados.');
        }
    } catch (error) {
        console.error(error);
        alert('Erro ao carregar contatos da categoria.');
    }
}

async function submitSchedule(event) {
    event.preventDefault();
    const message = document.getElementById('scheduleMessage').value.trim();
    const scheduled_for = document.getElementById('scheduleDatetime').value;
    if (!message) {
        alert('Escreva a mensagem.');
        return false;
    }
    if (!scheduled_for) {
        alert('Informe a data e hora.');
        return false;
    }
    if (selectedContacts.length === 0) {
        alert('Selecione ao menos um contato.');
        return false;
    }

    const submitBtn = event.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.classList.add('opacity-60');

    try {
        const payload = {
            message,
            scheduled_for,
            contacts: selectedContacts.map(c => c.id)
        };
        const res = await fetch('/api/scheduled_dispatches/create.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            alert('Agendamento criado com sucesso!');
            event.target.reset();
            selectedContacts = [];
            updateSelectedPreview();
            renderContacts();
            loadSchedules();
        } else {
            alert(data.error || 'Erro ao salvar agendamento.');
        }
    } catch (error) {
        console.error(error);
        alert('Erro inesperado ao salvar.');
    } finally {
        submitBtn.disabled = false;
        submitBtn.classList.remove('opacity-60');
    }
    return false;
}

async function loadSchedules(page = 1) {
    schedulesState.page = page;
    const params = new URLSearchParams({
        page,
        limit: 10,
    });
    if (schedulesState.status) params.append('status', schedulesState.status);

    const table = document.getElementById('schedulesTable');
    table.innerHTML = '<tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Carregando...</td></tr>';
    try {
        const res = await fetch(`/api/scheduled_dispatches/list.php?${params.toString()}`);
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'Erro ao carregar');
        renderScheduleRows(data.data || []);
        renderPagination(data.pagination);
    } catch (error) {
        console.error(error);
        table.innerHTML = '<tr><td colspan="5" class="px-4 py-6 text-center text-red-500">Erro ao carregar agendamentos.</td></tr>';
    }
}

function renderScheduleRows(items) {
    const table = document.getElementById('schedulesTable');
    lastSchedules = items;
    if (!items.length) {
        table.innerHTML = '<tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Nenhum agendamento encontrado.</td></tr>';
        return;
    }
    table.innerHTML = items.map(item => {
        const progress = item.total_contacts ? Math.round((item.sent_count / item.total_contacts) * 100) : 0;
        const canCancel = ['pending', 'processing'].includes(item.status);
        return `
            <tr>
                <td class="px-4 py-3">
                    <div class="font-semibold text-gray-800">${(item.message || '').substring(0, 60) || 'Mensagem'}</div>
                    <div class="text-xs text-gray-500">${item.total_contacts} destinatário(s)</div>
                </td>
                <td class="px-4 py-3 text-sm text-gray-600">${formatDateTime(item.scheduled_for)}</td>
                <td class="px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">${item.sent_count}/${item.total_contacts}</div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="h-2 rounded-full ${progress === 100 ? 'bg-green-500' : 'bg-blue-500'}" style="width:${progress}%"></div>
                    </div>
                </td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 rounded-full text-xs font-semibold ${statusBadge(item.status)}">${formatStatus(item.status)}</span>
                    ${item.last_error ? `<div class="text-xs text-red-500 mt-1">${item.last_error}</div>` : ''}
                </td>
                <td class="px-4 py-3 text-right text-sm">
                    ${canCancel ? `<button class="text-red-500 hover:text-red-700" onclick="cancelDispatch(${item.id})">Cancelar</button>` : ''}
                </td>
            </tr>
        `;
    }).join('');
}

function renderPagination(pagination) {
    const container = document.getElementById('schedulePagination');
    if (!pagination || pagination.pages <= 1) {
        container.innerHTML = '';
        return;
    }
    container.innerHTML = `
        <button class="text-green-600 ${pagination.page === 1 ? 'opacity-50 cursor-not-allowed' : ''}" ${pagination.page === 1 ? 'disabled' : ''} onclick="loadSchedules(${pagination.page - 1})">Anterior</button>
        <span>Página ${pagination.page} de ${pagination.pages}</span>
        <button class="text-green-600 ${pagination.page >= pagination.pages ? 'opacity-50 cursor-not-allowed' : ''}" ${pagination.page >= pagination.pages ? 'disabled' : ''} onclick="loadSchedules(${pagination.page + 1})">Próxima</button>
    `;
}

async function cancelDispatch(id) {
    if (!confirm('Deseja cancelar este agendamento?')) return;
    try {
        const formData = new FormData();
        formData.append('id', id);
        const res = await fetch('/api/scheduled_dispatches/cancel.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            alert('Agendamento cancelado.');
            loadSchedules(schedulesState.page);
        } else {
            alert(data.error || 'Não foi possível cancelar.');
        }
    } catch (error) {
        console.error(error);
        alert('Erro ao cancelar agendamento.');
    }
}

function statusBadge(status) {
    switch (status) {
        case 'pending': return 'bg-yellow-100 text-yellow-700';
        case 'processing': return 'bg-blue-100 text-blue-700';
        case 'completed': return 'bg-green-100 text-green-700';
        case 'failed': return 'bg-red-100 text-red-700';
        case 'cancelled': return 'bg-gray-100 text-gray-600';
        default: return 'bg-gray-100 text-gray-600';
    }
}

function formatStatus(status) {
    const map = {
        pending: 'Pendente',
        processing: 'Processando',
        completed: 'Concluído',
        failed: 'Falhou',
        cancelled: 'Cancelado'
    };
    return map[status] || status;
}

function formatDateTime(dateStr) {
    const date = new Date(dateStr.replace(' ', 'T'));
    return date.toLocaleString('pt-BR');
}

document.getElementById('statusFilter').addEventListener('change', (e) => {
    schedulesState.status = e.target.value;
    loadSchedules(1);
});

function exportSchedulesCSV() {
    if (!lastSchedules.length) {
        alert('Nenhum agendamento disponível para exportação.');
        return;
    }
    const headers = ['Mensagem', 'Agendado para', 'Status', 'Total', 'Enviados'];
    const rows = lastSchedules.map(item => [
        '"' + (item.message ? item.message.replace(/"/g, '""') : '') + '"',
        '"' + item.scheduled_for + '"',
        item.status,
        item.total_contacts,
        item.sent_count
    ]);
    let csv = headers.join(',') + '\n' + rows.map(r => r.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'agendamentos.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

initTabs();
renderContacts();
updateSelectedPreview();
loadSchedules();
</script>

<?php require_once 'includes/footer_spa.php'; ?>
