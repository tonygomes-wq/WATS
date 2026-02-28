<?php
$page_title = 'Minhas Categorias';
require_once 'includes/header_spa.php';

$userId = $_SESSION['user_id'];

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = sanitize($_POST['name'] ?? '');
        $color = sanitize($_POST['color'] ?? '#3B82F6');
        $contacts = $_POST['contacts'] ?? [];
        
        if (empty($name)) {
            setError('Por favor, informe o nome da categoria.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO categories (user_id, name, color) VALUES (?, ?, ?)");
            if ($stmt->execute([$userId, $name, $color])) {
                $categoryId = $pdo->lastInsertId();
                
                // Adicionar contatos à categoria
                if (!empty($contacts)) {
                    $stmt = $pdo->prepare("INSERT INTO contact_categories (contact_id, category_id) VALUES (?, ?)");
                    foreach ($contacts as $contactId) {
                        $stmt->execute([$contactId, $categoryId]);
                    }
                }
                
                setSuccess('Categoria criada com sucesso!');
            } else {
                setError('Erro ao criar categoria. Talvez ela já exista.');
            }
        }
        header('Location: /categories.php');
        exit;
    }
    
    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $color = sanitize($_POST['color'] ?? '#3B82F6');
        
        if (empty($name)) {
            setError('Por favor, informe o nome da categoria.');
        } else {
            $stmt = $pdo->prepare("UPDATE categories SET name = ?, color = ? WHERE id = ? AND user_id = ?");
            if ($stmt->execute([$name, $color, $id, $userId])) {
                setSuccess('Categoria atualizada com sucesso!');
            } else {
                setError('Erro ao atualizar categoria.');
            }
        }
        header('Location: /categories.php');
        exit;
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$id, $userId])) {
            setSuccess('Categoria deletada com sucesso!');
        } else {
            setError('Erro ao deletar categoria.');
        }
        header('Location: /categories.php');
        exit;
    }
}

// Listar categorias com contagem de contatos
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

// Buscar todos os contatos do usuário para o dropdown
$stmt = $pdo->prepare("SELECT id, name, phone FROM contacts WHERE user_id = ? ORDER BY name");
$stmt->execute([$userId]);
$allContacts = $stmt->fetchAll();
?>

<div class="refined-container">
<div class="refined-card">
    <div class="refined-action-bar">
        <h1 class="refined-title">
            <i class="fas fa-folder-open"></i>Minhas Categorias
        </h1>
        <button onclick="openAddModal()" class="refined-btn refined-btn-primary">
            <i class="fas fa-plus"></i>Nova Categoria
        </button>
    </div>
    
    <?php if (empty($categories)): ?>
        <div class="refined-empty">
            <i class="fas fa-tags"></i>
            <h3>Nenhuma categoria criada</h3>
            <p>Crie categorias para organizar seus contatos!</p>
        </div>
    <?php else: ?>
        <div class="refined-grid refined-grid-3">
            <?php foreach ($categories as $category): ?>
            <div style="border: 0.5px solid var(--border); border-left: 3px solid <?php echo htmlspecialchars($category['color']); ?>; background: var(--bg-card); padding: var(--space-4); border-radius: var(--radius-md); transition: all var(--transition-fast);" onmouseover="this.style.borderColor='var(--border-emphasis)'" onmouseout="this.style.borderColor='var(--border)'">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-2);">
                    <div style="display: flex; align-items: center; gap: var(--space-3);">
                        <div style="width: 12px; height: 12px; border-radius: 50%; background-color: <?php echo htmlspecialchars($category['color']); ?>"></div>
                        <h3 style="font-size: 14px; font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($category['name']); ?></h3>
                    </div>
                    <div style="display: flex; gap: var(--space-2);">
                        <button onclick='openEditModal(<?php echo json_encode($category); ?>)' style="color: #3b82f6; background: none; border: none; cursor: pointer; padding: 4px;" onmouseover="this.style.color='#2563eb'" onmouseout="this.style.color='#3b82f6'">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteCategory(<?php echo $category['id']; ?>)" style="color: #ef4444; background: none; border: none; cursor: pointer; padding: 4px;" onmouseover="this.style.color='#dc2626'" onmouseout="this.style.color='#ef4444'">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <p style="font-size: 12px; color: var(--text-secondary); display: flex; align-items: center; gap: 4px;">
                    <i class="fas fa-user"></i>
                    <?php echo $category['contact_count']; ?> contato(s)
                </p>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Adicionar -->
<div id="addModal" class="refined-modal-overlay" style="display: none;">
    <div class="refined-modal">
        <div class="refined-modal-header">
            <h2 class="refined-modal-title">Nova Categoria</h2>
            <button type="button" onclick="closeAddModal()" class="refined-modal-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add">
            <div class="refined-section">
                <label class="refined-label">Nome</label>
                <input type="text" name="name" required class="refined-input">
            </div>
            <div class="refined-section">
                <label class="refined-label">Cor</label>
                <div class="flex space-x-2">
                    <input type="color" name="color" value="#3B82F6" class="w-16 h-10 border rounded cursor-pointer">
                    <input type="text" id="add_color_text" value="#3B82F6" class="flex-1 px-3 py-2 border rounded-lg" readonly>
                </div>
            </div>
            
            <?php if (!empty($allContacts)): ?>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">
                    <i class="fas fa-users mr-1"></i>Adicionar Contatos (Opcional)
                </label>
                <div class="border rounded-lg p-3 max-h-60 overflow-y-auto bg-gray-50">
                    <div class="mb-2">
                        <input type="text" id="searchContactsAdd" placeholder="Buscar contato..." class="w-full px-3 py-2 border rounded-lg text-sm" onkeyup="filterContactsAdd()">
                    </div>
                    <div id="contactListAdd" class="space-y-2">
                        <?php foreach ($allContacts as $contact): ?>
                        <label class="flex items-center p-2 hover:bg-gray-100 rounded cursor-pointer contact-item-add">
                            <input type="checkbox" name="contacts[]" value="<?php echo $contact['id']; ?>" class="mr-3">
                            <div class="flex-1">
                                <div class="font-medium text-sm contact-name"><?php echo htmlspecialchars($contact['name'] ?: 'Sem nome'); ?></div>
                                <div class="text-xs text-gray-600 contact-phone"><?php echo htmlspecialchars($contact['phone']); ?></div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-2">
                    <i class="fas fa-info-circle mr-1"></i>Selecione os contatos que deseja adicionar a esta categoria
                </p>
            </div>
            <?php endif; ?>
            
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeAddModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg">
                    Cancelar
                </button>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                    Criar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar -->
<div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-8 w-full max-w-md">
        <h2 class="text-2xl font-bold mb-4">Editar Categoria</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Nome</label>
                <input type="text" name="name" id="edit_name" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Cor</label>
                <div class="flex space-x-2">
                    <input type="color" name="color" id="edit_color" class="w-16 h-10 border rounded cursor-pointer">
                    <input type="text" id="edit_color_text" class="flex-1 px-3 py-2 border rounded-lg" readonly>
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

<script>
function openAddModal() {
    document.getElementById('addModal').classList.remove('hidden');
}

function closeAddModal() {
    document.getElementById('addModal').classList.add('hidden');
}

function openEditModal(category) {
    document.getElementById('edit_id').value = category.id;
    document.getElementById('edit_name').value = category.name;
    document.getElementById('edit_color').value = category.color;
    document.getElementById('edit_color_text').value = category.color;
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

function deleteCategory(id) {
    if (confirm('Tem certeza que deseja deletar esta categoria? Os contatos não serão deletados.')) {
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

// Sincronizar cor
document.querySelectorAll('input[type="color"]').forEach(input => {
    input.addEventListener('input', function() {
        const textInput = this.parentElement.querySelector('input[type="text"]');
        if (textInput) textInput.value = this.value;
    });
});

// Filtrar contatos no modal de adicionar
function filterContactsAdd() {
    const searchTerm = document.getElementById('searchContactsAdd').value.toLowerCase();
    const items = document.querySelectorAll('.contact-item-add');
    
    items.forEach(item => {
        const name = item.querySelector('.contact-name').textContent.toLowerCase();
        const phone = item.querySelector('.contact-phone').textContent.toLowerCase();
        
        if (name.includes(searchTerm) || phone.includes(searchTerm)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}
</script>

</div>
<?php require_once 'includes/footer_spa.php'; ?>
