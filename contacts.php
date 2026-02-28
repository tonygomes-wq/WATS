<?php
$page_title = 'Meus Contatos';
require_once 'includes/header_spa.php';

$userId = $_SESSION['user_id'];

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = sanitize($_POST['name'] ?? '');
        $phone = formatPhone($_POST['phone'] ?? '');
        $categories = $_POST['categories'] ?? [];

        if (empty($phone)) {
            setError('Por favor, informe o telefone.');
        } elseif (!validatePhone($phone)) {
            setError('Telefone inválido. Use o formato: (XX) XXXXX-XXXX');
        } else {
            // Verificar se já existe
            $stmt = $pdo->prepare("SELECT id FROM contacts WHERE user_id = ? AND phone = ?");
            $stmt->execute([$userId, $phone]);
            if ($stmt->fetch()) {
                setError('Este telefone já está cadastrado.');
            } else {
                $stmt = $pdo->prepare("INSERT INTO contacts (user_id, name, phone) VALUES (?, ?, ?)");
                if ($stmt->execute([$userId, $name, $phone])) {
                    $contactId = $pdo->lastInsertId();

                    // Adicionar categorias
                    if (!empty($categories)) {
                        $stmt = $pdo->prepare("INSERT INTO contact_categories (contact_id, category_id) VALUES (?, ?)");
                        foreach ($categories as $categoryId) {
                            $stmt->execute([$contactId, $categoryId]);
                        }
                    }

                    setSuccess('Contato adicionado com sucesso!');
                } else {
                    setError('Erro ao adicionar contato.');
                }
            }
        }
        header('Location: /contacts.php');
        exit;
    }

    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $phone = formatPhone($_POST['phone'] ?? '');
        $categories = $_POST['categories'] ?? [];

        if (empty($phone)) {
            setError('Por favor, informe o telefone.');
        } elseif (!validatePhone($phone)) {
            setError('Telefone inválido.');
        } else {
            // Verificar se já existe em outro contato
            $stmt = $pdo->prepare("SELECT id FROM contacts WHERE user_id = ? AND phone = ? AND id != ?");
            $stmt->execute([$userId, $phone, $id]);
            if ($stmt->fetch()) {
                setError('Este telefone já está cadastrado em outro contato.');
            } else {
                $stmt = $pdo->prepare("UPDATE contacts SET name = ?, phone = ? WHERE id = ? AND user_id = ?");
                if ($stmt->execute([$name, $phone, $id, $userId])) {
                    // Atualizar categorias
                    $pdo->prepare("DELETE FROM contact_categories WHERE contact_id = ?")->execute([$id]);
                    if (!empty($categories)) {
                        $stmt = $pdo->prepare("INSERT INTO contact_categories (contact_id, category_id) VALUES (?, ?)");
                        foreach ($categories as $categoryId) {
                            $stmt->execute([$id, $categoryId]);
                        }
                    }
                    setSuccess('Contato atualizado com sucesso!');
                } else {
                    setError('Erro ao atualizar contato.');
                }
            }
        }
        header('Location: /contacts.php');
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$id, $userId])) {
            setSuccess('Contato deletado com sucesso!');
        } else {
            setError('Erro ao deletar contato.');
        }
        header('Location: /contacts.php');
        exit;
    }

    if ($action === 'bulk_add_category') {
        $contactIds = $_POST['contact_ids'] ?? [];
        $categoryId = intval($_POST['category_id'] ?? 0);

        if (empty($contactIds) || empty($categoryId)) {
            setError('Selecione contatos e uma categoria.');
        } else {
            // Verificar se a categoria pertence ao usuário
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ? AND user_id = ?");
            $stmt->execute([$categoryId, $userId]);
            if (!$stmt->fetch()) {
                setError('Categoria inválida.');
            } else {
                $added = 0;
                $stmt = $pdo->prepare("INSERT IGNORE INTO contact_categories (contact_id, category_id) VALUES (?, ?)");
                foreach ($contactIds as $contactId) {
                    // Verificar se o contato pertence ao usuário
                    $stmtCheck = $pdo->prepare("SELECT id FROM contacts WHERE id = ? AND user_id = ?");
                    $stmtCheck->execute([$contactId, $userId]);
                    if ($stmtCheck->fetch()) {
                        if ($stmt->execute([$contactId, $categoryId])) {
                            $added++;
                        }
                    }
                }
                setSuccess("$added contato(s) adicionado(s) à categoria com sucesso!");
            }
        }
        header('Location: /contacts.php');
        exit;
    }

    if ($action === 'import') {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            setError('Por favor, selecione um arquivo CSV válido.');
        } else {
            $file = $_FILES['csv_file']['tmp_name'];
            $imported = 0;
            $errors = 0;

            if (($handle = fopen($file, 'r')) !== false) {
                // Detectar encoding
                $content = file_get_contents($file);
                $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
                fclose($handle);

                // Reabrir com encoding correto
                $handle = fopen($file, 'r');

                // Pular cabeçalho
                $header = fgetcsv($handle, 1000, ',');

                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    if ($encoding !== 'UTF-8') {
                        $data = array_map(function ($item) use ($encoding) {
                            return mb_convert_encoding($item, 'UTF-8', $encoding);
                        }, $data);
                    }

                    $name = isset($data[0]) ? sanitize($data[0]) : '';
                    $phone = isset($data[1]) ? formatPhone($data[1]) : '';

                    if (empty($phone)) {
                        $errors++;
                        continue;
                    }

                    if (!validatePhone($phone)) {
                        $errors++;
                        continue;
                    }

                    // Verificar se já existe
                    $stmt = $pdo->prepare("SELECT id FROM contacts WHERE user_id = ? AND phone = ?");
                    $stmt->execute([$userId, $phone]);
                    if ($stmt->fetch()) {
                        continue; // Pular duplicados
                    }

                    $stmt = $pdo->prepare("INSERT INTO contacts (user_id, name, phone) VALUES (?, ?, ?)");
                    if ($stmt->execute([$userId, $name, $phone])) {
                        $imported++;
                    } else {
                        $errors++;
                    }
                }
                fclose($handle);

                if ($imported > 0) {
                    setSuccess("$imported contato(s) importado(s) com sucesso!" . ($errors > 0 ? " ($errors erro(s))" : ""));
                } else {
                    setError("Nenhum contato foi importado. Verifique o arquivo.");
                }
            } else {
                setError('Erro ao ler o arquivo CSV.');
            }
        }
        header('Location: /contacts.php');
        exit;
    }
}

// Filtros
$search = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';

// Listar contatos
$sql = "
    SELECT c.*, GROUP_CONCAT(cat.name SEPARATOR ', ') as category_names,
           GROUP_CONCAT(cat.id) as category_ids,
           GROUP_CONCAT(cat.color) as category_colors
    FROM contacts c
    LEFT JOIN contact_categories cc ON c.id = cc.contact_id
    LEFT JOIN categories cat ON cc.category_id = cat.id
    WHERE c.user_id = ?
";

$params = [$userId];

if (!empty($search)) {
    $sql .= " AND (c.name LIKE ? OR c.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " GROUP BY c.id";

if (!empty($categoryFilter)) {
    $sql .= " HAVING FIND_IN_SET(?, category_ids)";
    $params[] = $categoryFilter;
}

$sql .= " ORDER BY c.name, c.phone";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contacts = $stmt->fetchAll();

// Listar categorias para filtros
$stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY name");
$stmt->execute([$userId]);
$categories = $stmt->fetchAll();

// Exibir mensagens de sucesso/erro
$successMessage = getSuccess();
$errorMessage = getError();
?>

<div class="refined-container">

    <?php
    if ($successMessage) {
        echo '<div class="refined-alert refined-alert-success">';
        echo '<i class="fas fa-check-circle"></i>';
        echo '<div class="refined-alert-content" style="flex: 1;"><p>' . htmlspecialchars($successMessage) . '</p></div>';
        echo '</div>';
    }

    if ($errorMessage) {
        echo '<div class="refined-alert refined-alert-danger">';
        echo '<i class="fas fa-exclamation-circle"></i>';
        echo '<div class="refined-alert-content" style="flex: 1;"><p>' . htmlspecialchars($errorMessage) . '</p></div>';
        echo '</div>';
    }
    ?>

    <div class="refined-card">
        <div class="refined-action-bar">
            <h1 class="refined-title">
                <i class="fas fa-users"></i>Meus Contatos
            </h1>
            <div class="refined-action-group">
                <button onclick="openBulkCategoryModal()" class="refined-btn" id="bulkCategoryBtn" style="display: none; background: #8b5cf6; border-color: #8b5cf6; color: white;">
                    <i class="fas fa-folder-plus"></i>Adicionar à Categoria (<span id="selectedCount">0</span>)
                </button>
                <a href="/template.csv" download class="refined-btn">
                    <i class="fas fa-download"></i>Baixar Template
                </a>
                <button onclick="syncWhatsAppHistory()" id="syncHistoryBtn" class="refined-btn" style="background: #128C7E; border-color: #128C7E; color: white;">
                    <i class="fas fa-sync-alt"></i>Sincronizar Histórico
                </button>
                <button onclick="importFromWhatsApp()" id="importWhatsAppBtn" class="refined-btn-primary refined-btn">
                    <i class="fab fa-whatsapp"></i>Importar do WhatsApp
                </button>
                <button onclick="openImportModal()" class="refined-btn">
                    <i class="fas fa-file-csv"></i>Importar CSV
                </button>
                <button onclick="openAddModal()" class="refined-btn-primary refined-btn">
                    <i class="fas fa-plus"></i>Novo Contato
                </button>
            </div>
        </div>

        <form method="GET" class="refined-section">
            <div class="refined-grid refined-grid-2">
                <div class="refined-search">
                    <i class="fas fa-search"></i>
                    <input
                        type="text"
                        name="search"
                        placeholder="Buscar por nome ou telefone..."
                        value="<?php echo htmlspecialchars($search); ?>"
                        class="refined-input">
                </div>
                <div style="display: flex; gap: 8px;">
                    <select name="category" class="refined-select" style="flex: 1;">
                        <option value="">Todas as categorias</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $categoryFilter == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="refined-btn-primary refined-btn">
                        <i class="fas fa-search"></i>
                    </button>
                    <?php if (!empty($search) || !empty($categoryFilter)): ?>
                        <a href="/contacts.php" class="refined-btn">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <p class="text-gray-600 mb-4">Total: <?php echo count($contacts); ?> contato(s)</p>

        <?php if (empty($contacts)): ?>
            <div class="text-center py-12">
                <i class="fas fa-address-book text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 text-lg">Nenhum contato encontrado.</p>
                <p class="text-gray-400 mt-2">Adicione contatos manualmente ou importe via CSV!</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left">
                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)" class="rounded">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Telefone</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categorias</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($contacts as $contact): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-4">
                                    <input type="checkbox" class="contact-checkbox rounded" value="<?php echo $contact['id']; ?>" onchange="updateBulkButton()">
                                </td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($contact['name'] ?: 'Sem nome'); ?></td>
                                <td class="px-6 py-4 font-mono"><?php echo htmlspecialchars($contact['phone']); ?></td>
                                <td class="px-6 py-4">
                                    <?php
                                    if ($contact['category_names']) {
                                        $names = explode(', ', $contact['category_names']);
                                        $colors = explode(',', $contact['category_colors']);
                                        foreach ($names as $idx => $catName) {
                                            $color = $colors[$idx] ?? '#3B82F6';
                                            echo '<span class="inline-block text-xs px-2 py-1 rounded mr-1 mb-1" style="background-color: ' . htmlspecialchars($color) . '20; color: ' . htmlspecialchars($color) . '">' . htmlspecialchars($catName) . '</span>';
                                        }
                                    } else {
                                        echo '<span class="text-gray-400 text-sm">Sem categoria</span>';
                                    }
                                    ?>
                                </td>
                                <td class="px-6 py-4">
                                    <button onclick='openEditModal(<?php echo json_encode($contact); ?>)' class="text-blue-600 hover:text-blue-800 mr-3">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteContact(<?php echo $contact['id']; ?>)" class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Adicionar -->
    <div id="addModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 overflow-y-auto">
        <div class="bg-white rounded-lg p-8 w-full max-w-md m-4">
            <h2 class="text-2xl font-bold mb-4">Novo Contato</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Nome (opcional)</label>
                    <input type="text" name="name" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Telefone *</label>
                    <input type="text" name="phone" required placeholder="11999887766" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    <p class="text-xs text-gray-500 mt-1">Formato: DDD + número (apenas números)</p>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Categorias</label>
                    <div class="space-y-2 max-h-40 overflow-y-auto border rounded-lg p-2">
                        <?php foreach ($categories as $cat): ?>
                            <label class="flex items-center">
                                <input type="checkbox" name="categories[]" value="<?php echo $cat['id']; ?>" class="mr-2">
                                <span class="w-3 h-3 rounded-full mr-2" style="background-color: <?php echo $cat['color']; ?>"></span>
                                <span class="text-sm"><?php echo htmlspecialchars($cat['name']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeAddModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg">
                        Cancelar
                    </button>
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                        Adicionar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar -->
    <div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 overflow-y-auto">
        <div class="bg-white rounded-lg p-8 w-full max-w-md m-4">
            <h2 class="text-2xl font-bold mb-4">Editar Contato</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Nome (opcional)</label>
                    <input type="text" name="name" id="edit_name" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Telefone *</label>
                    <input type="text" name="phone" id="edit_phone" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Categorias</label>
                    <div id="edit_categories" class="space-y-2 max-h-40 overflow-y-auto border rounded-lg p-2">
                        <?php foreach ($categories as $cat): ?>
                            <label class="flex items-center">
                                <input type="checkbox" name="categories[]" value="<?php echo $cat['id']; ?>" class="mr-2 edit-cat-checkbox">
                                <span class="w-3 h-3 rounded-full mr-2" style="background-color: <?php echo $cat['color']; ?>"></span>
                                <span class="text-sm"><?php echo htmlspecialchars($cat['name']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeEditModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg">
                        Cancelar
                    </button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Importar -->
    <div id="importModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-8 w-full max-w-md">
            <h2 class="text-2xl font-bold mb-4">Importar Contatos (CSV)</h2>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Arquivo CSV</label>
                    <input type="file" name="csv_file" accept=".csv" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    <p class="text-xs text-gray-500 mt-2">
                        <i class="fas fa-info-circle"></i> O arquivo deve ter 2 colunas: nome,telefone<br>
                        O nome é opcional, mas o telefone é obrigatório.<br>
                        <a href="/template.csv" download class="text-blue-600 hover:underline">Baixe o template aqui</a>
                    </p>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeImportModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg">
                        Cancelar
                    </button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                        Importar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Adicionar em Massa à Categoria -->
    <div id="bulkCategoryModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-8 w-full max-w-md">
            <h2 class="text-2xl font-bold mb-4">
                <i class="fas fa-folder-plus mr-2 text-purple-600"></i>Adicionar à Categoria
            </h2>
            <form method="POST" action="" id="bulkCategoryForm">
                <input type="hidden" name="action" value="bulk_add_category">
                <div id="selectedContactsInput"></div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Selecione a Categoria</label>
                    <select name="category_id" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        <option value="">Escolha uma categoria...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="bg-purple-50 border border-purple-200 rounded-lg p-3 mb-4">
                    <p class="text-sm text-purple-800">
                        <i class="fas fa-info-circle mr-1"></i>
                        <span id="bulkSelectedCount">0</span> contato(s) selecionado(s)
                    </p>
                </div>

                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeBulkCategoryModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg">
                        Cancelar
                    </button>
                    <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg">
                        Adicionar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.remove('hidden');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.add('hidden');
        }

        function openEditModal(contact) {
            document.getElementById('edit_id').value = contact.id;
            document.getElementById('edit_name').value = contact.name || '';
            document.getElementById('edit_phone').value = contact.phone;

            // Limpar checkboxes
            document.querySelectorAll('.edit-cat-checkbox').forEach(cb => cb.checked = false);

            // Marcar categorias do contato
            if (contact.category_ids) {
                const categoryIds = contact.category_ids.split(',');
                categoryIds.forEach(catId => {
                    const checkbox = document.querySelector(`.edit-cat-checkbox[value="${catId}"]`);
                    if (checkbox) checkbox.checked = true;
                });
            }

            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        function openImportModal() {
            document.getElementById('importModal').classList.remove('hidden');
        }

        function closeImportModal() {
            document.getElementById('importModal').classList.add('hidden');
        }

        function deleteContact(id) {
            if (confirm('Tem certeza que deseja deletar este contato?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
        `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Funções para seleção em massa
        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.contact-checkbox');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
            updateBulkButton();
        }

        function updateBulkButton() {
            const checkboxes = document.querySelectorAll('.contact-checkbox:checked');
            const count = checkboxes.length;
            const btn = document.getElementById('bulkCategoryBtn');
            const countSpan = document.getElementById('selectedCount');

            if (count > 0) {
                btn.style.display = 'inline-block';
                countSpan.textContent = count;
            } else {
                btn.style.display = 'none';
            }
        }

        function openBulkCategoryModal() {
            const checkboxes = document.querySelectorAll('.contact-checkbox:checked');
            const count = checkboxes.length;

            if (count === 0) {
                alert('Selecione pelo menos um contato');
                return;
            }

            // Adicionar inputs hidden com IDs dos contatos
            const container = document.getElementById('selectedContactsInput');
            container.innerHTML = '';
            checkboxes.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'contact_ids[]';
                input.value = cb.value;
                container.appendChild(input);
            });

            // Atualizar contador no modal
            document.getElementById('bulkSelectedCount').textContent = count;

            // Abrir modal
            document.getElementById('bulkCategoryModal').classList.remove('hidden');
        }

        function closeBulkCategoryModal() {
            document.getElementById('bulkCategoryModal').classList.add('hidden');
        }

        // Sincronizar histórico do WhatsApp
        async function syncWhatsAppHistory() {
            if (!confirm('Deseja sincronizar o histórico de mensagens do WhatsApp?\n\nIsso irá ativar a sincronização completa do histórico na sua instância Evolution API.\n\nATENÇÃO: Esta operação pode demorar alguns minutos dependendo da quantidade de mensagens.')) {
                return;
            }

            const btn = document.getElementById('syncHistoryBtn');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sincronizando...';

            try {
                const response = await fetch('/api/evolution_proxy.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'fix_store_messages'
                    })
                });

                const data = await response.json();

                if (data.success) {
                    alert('✅ Sincronização de histórico ativada com sucesso!\n\nAs mensagens antigas serão sincronizadas automaticamente nos próximos minutos.\n\nVocê pode acompanhar o progresso no chat.');
                } else {
                    alert('❌ Erro ao ativar sincronização: ' + (data.error || 'Erro desconhecido'));
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('❌ Erro ao sincronizar histórico: ' + error.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        // Importar contatos do WhatsApp (Evolution API)
        async function importFromWhatsApp() {
            if (!confirm('Deseja importar todos os contatos salvos no seu WhatsApp?\n\nIsso irá buscar os contatos da sua instância Evolution API conectada.')) {
                return;
            }

            const btn = document.getElementById('importWhatsAppBtn');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Importando...';

            try {
                const response = await fetch('/api/import_contacts.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });

                const data = await response.json();

                if (data.success) {
                    const message = `✅ Importação concluída!\n\n` +
                        `📊 Total na API: ${data.total_contacts || 0}\n` +
                        `✨ Novos importados: ${data.imported || 0}\n` +
                        `🔄 Atualizados: ${data.updated || 0}\n` +
                        `⏭️ Ignorados: ${data.skipped || 0}`;

                    alert(message);

                    if (data.imported > 0 || data.updated > 0) {
                        location.reload();
                    }
                } else {
                    alert('❌ Erro ao importar: ' + (data.error || 'Erro desconhecido'));
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('❌ Erro ao importar contatos: ' + error.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }
    </script>

    <style>
        /* Correção específica para botões de ação na página de contatos */
        button:has(.fa-edit),
        button.text-blue-600:has(.fa-edit),
        button[class*="text-blue"]:has(.fa-edit) {
            all: unset !important;
            cursor: pointer !important;
            color: #3b82f6 !important;
            transition: color 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 4px !important;
            margin-right: 12px !important;
        }

        button:has(.fa-edit):hover,
        button.text-blue-600:has(.fa-edit):hover,
        button[class*="text-blue"]:has(.fa-edit):hover {
            color: #2563eb !important;
        }

        button:has(.fa-trash),
        button.text-red-600:has(.fa-trash),
        button[class*="text-red"]:has(.fa-trash) {
            all: unset !important;
            cursor: pointer !important;
            color: #ef4444 !important;
            transition: color 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 4px !important;
        }

        button:has(.fa-trash):hover,
        button.text-red-600:has(.fa-trash):hover,
        button[class*="text-red"]:has(.fa-trash):hover {
            color: #dc2626 !important;
        }
    </style>

</div>
<?php require_once 'includes/footer_spa.php'; ?>