<?php
$page_title = 'Gerenciar Usuários';
require_once 'includes/header_spa.php';

$defaultPlanDefinitions = [
    ['slug' => 'free', 'name' => 'Gratuito', 'message_limit' => 500, 'is_active' => 1],
    ['slug' => 'basic', 'name' => 'Básico', 'message_limit' => 2000, 'is_active' => 1],
    ['slug' => 'pro', 'name' => 'Pro', 'message_limit' => 10000, 'is_active' => 1],
    ['slug' => 'enterprise', 'name' => 'Enterprise', 'message_limit' => 999999, 'is_active' => 1],
];

try {
    $planStmt = $pdo->query("SELECT slug, name, message_limit, is_active FROM pricing_plans ORDER BY sort_order ASC, id ASC");
    $storedPlans = $planStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $storedPlans = [];
}

if (empty($storedPlans)) {
    $storedPlans = $defaultPlanDefinitions;
}

$planMetaMap = [];
$planLimitMap = [];
foreach ($storedPlans as $plan) {
    $planMetaMap[$plan['slug']] = [
        'label' => $plan['name'],
        'limit' => (int) ($plan['message_limit'] ?? 0)
    ];
    $planLimitMap[$plan['slug']] = (int) ($plan['message_limit'] ?? 0);
}

$planSelectOptions = array_values(array_filter($storedPlans, function ($plan) {
    return (int) ($plan['is_active'] ?? 1) === 1;
}));

if (empty($planSelectOptions)) {
    $planSelectOptions = $storedPlans;
}

$defaultPlanSlug = $planSelectOptions[0]['slug'] ?? ($storedPlans[0]['slug'] ?? 'free');
$defaultPlanLimit = $planLimitMap[$defaultPlanSlug] ?? 500;

$planColorPalette = [
    'free' => 'gray',
    'basic' => 'blue',
    'pro' => 'purple',
    'enterprise' => 'yellow'
];

// Verificar se é admin
if (!isAdmin()) {
    header('Location: /dashboard.php');
    exit;
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;
        $is_supervisor = isset($_POST['is_supervisor']) ? 1 : 0;
        $two_factor_enabled = isset($_POST['two_factor_enabled']) ? 1 : 0;
        $plan = sanitize($_POST['plan'] ?? 'free');
        $plan_limit = intval($_POST['plan_limit'] ?? 500);
        
        // Se for admin, não pode ser supervisor
        if ($is_admin) {
            $is_supervisor = 0;
        }
        
        if (empty($name) || empty($email) || empty($password)) {
            setError('Por favor, preencha todos os campos.');
        } elseif (!validateEmail($email)) {
            setError('Email inválido.');
        } elseif (strlen($password) < 6) {
            setError('A senha deve ter no mínimo 6 caracteres.');
        } else {
            // Verificar se email já existe
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                setError('Este email já está cadastrado.');
            } else {
                // Novos usuários não-admin devem alterar senha no primeiro login
                $must_change_password = $is_admin ? 0 : 1;
                $first_login = $is_admin ? 0 : 1;
                
                // Tentar inserir com todas as colunas, se falhar usar apenas as básicas
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, is_admin, is_supervisor, is_active, two_factor_enabled, plan, plan_limit, must_change_password, first_login) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $success = $stmt->execute([$name, $email, hashPassword($password), $is_admin, $is_supervisor, 1, $two_factor_enabled, $plan, $plan_limit, $must_change_password, $first_login]);
                } catch (Exception $e) {
                    // Fallback: inserir apenas campos básicos
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, is_admin, is_supervisor, is_active, two_factor_enabled, must_change_password, first_login) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $success = $stmt->execute([$name, $email, hashPassword($password), $is_admin, $is_supervisor, 1, $two_factor_enabled, $must_change_password, $first_login]);
                }
                
                if ($success) {
                    setSuccess('Usuário criado com sucesso!');
                } else {
                    setError('Erro ao criar usuário.');
                }
            }
        }
        header('Location: /users.php');
        exit;
    }
    
    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;
        $is_supervisor = isset($_POST['is_supervisor']) ? 1 : 0;
        $two_factor_enabled = isset($_POST['two_factor_enabled']) ? 1 : 0;
        $plan = sanitize($_POST['plan'] ?? 'free');
        $plan_limit = intval($_POST['plan_limit'] ?? 500);
        
        // Se for admin, não pode ser supervisor
        if ($is_admin) {
            $is_supervisor = 0;
        }
        
        if (empty($name) || empty($email)) {
            setError('Por favor, preencha todos os campos.');
        } elseif (!validateEmail($email)) {
            setError('Email inválido.');
        } else {
            // Verificar se email já existe em outro usuário
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $id]);
            if ($stmt->fetch()) {
                setError('Este email já está cadastrado.');
            } else {
                if (!empty($password)) {
                    if (strlen($password) < 6) {
                        setError('A senha deve ter no mínimo 6 caracteres.');
                        header('Location: /users.php');
                        exit;
                    }
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ?, is_admin = ?, is_supervisor = ?, two_factor_enabled = ?, plan = ?, plan_limit = ? WHERE id = ?");
                    $stmt->execute([$name, $email, hashPassword($password), $is_admin, $is_supervisor, $two_factor_enabled, $plan, $plan_limit, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, is_admin = ?, is_supervisor = ?, two_factor_enabled = ?, plan = ?, plan_limit = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $is_admin, $is_supervisor, $two_factor_enabled, $plan, $plan_limit, $id]);
                }
                setSuccess('Usuário atualizado com sucesso!');
            }
        }
        header('Location: /users.php');
        exit;
    }
    
    if ($action === 'toggle_active') {
        $id = intval($_POST['id'] ?? 0);
        $is_active = intval($_POST['is_active'] ?? 0);
        
        if ($id === $_SESSION['user_id']) {
            setError('Você não pode desativar seu próprio usuário.');
        } else {
            $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
            if ($stmt->execute([$is_active, $id])) {
                $status = $is_active ? 'ativado' : 'desativado';
                setSuccess("Usuário $status com sucesso!");
            } else {
                setError('Erro ao alterar status do usuário.');
            }
        }
        header('Location: /users.php');
        exit;
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id === $_SESSION['user_id']) {
            setError('Você não pode deletar seu próprio usuário.');
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt->execute([$id])) {
                setSuccess('Usuário deletado com sucesso!');
            } else {
                setError('Erro ao deletar usuário.');
            }
        }
        header('Location: /users.php');
        exit;
    }
}

// Listar usuários
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();
?>

<div class="refined-container">
<div class="refined-card">
    <div class="refined-action-bar">
        <h1 class="refined-title">
            <i class="fas fa-users"></i>Gerenciar Usuários
        </h1>
        <div class="flex gap-2">
            <button onclick="exportUsers()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition">
                <i class="fas fa-download mr-2"></i>Exportar
            </button>
            <button onclick="openAddModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition">
                <i class="fas fa-plus mr-2"></i>Novo Usuário
            </button>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <input type="text" id="search-input" placeholder="Buscar por nome ou email..." 
                   onkeyup="filterUsers()"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
        </div>
        <div>
            <select id="status-filter" onchange="filterUsers()" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                <option value="">Todos os status</option>
                <option value="active">Ativos</option>
                <option value="inactive">Inativos</option>
            </select>
        </div>
        <div>
            <select id="plan-filter" onchange="filterUsers()" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                <option value="">Todos os planos</option>
                <?php foreach ($planSelectOptions as $plan): ?>
                <option value="<?php echo htmlspecialchars($plan['slug']); ?>"><?php echo htmlspecialchars($plan['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <select id="type-filter" onchange="filterUsers()" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                <option value="">Todos os tipos</option>
                <option value="admin">Administradores</option>
                <option value="user">Usuários</option>
            </select>
        </div>
    </div>
    
    <!-- Estatísticas Rápidas -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-blue-50 rounded-lg p-4 border-l-4 border-blue-500">
            <div class="text-sm font-semibold mb-2" style="color: #1e40af !important;">Total de Usuários</div>
            <div class="text-3xl font-bold" style="color: #1e3a8a;"><?php echo count($users); ?></div>
        </div>
        <div class="bg-green-50 rounded-lg p-4 border-l-4 border-green-500">
            <div class="text-sm font-semibold mb-2" style="color: #15803d !important;">Ativos</div>
            <div class="text-3xl font-bold" style="color: #14532d;"><?php echo count(array_filter($users, fn($u) => $u['is_active'])); ?></div>
        </div>
        <div class="bg-purple-50 rounded-lg p-4 border-l-4 border-purple-500">
            <div class="text-sm font-semibold mb-2" style="color: #7e22ce !important;">Administradores</div>
            <div class="text-3xl font-bold" style="color: #581c87;"><?php echo count(array_filter($users, fn($u) => $u['is_admin'])); ?></div>
        </div>
        <div class="bg-yellow-50 rounded-lg p-4 border-l-4 border-yellow-500">
            <div class="text-sm font-semibold mb-2" style="color: #a16207 !important;">Com 2FA</div>
            <div class="text-3xl font-bold" style="color: #713f12;"><?php echo count(array_filter($users, fn($u) => $u['two_factor_enabled'])); ?></div>
        </div>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Plano</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mensagens</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">2FA</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vencimento</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($users as $user): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4"><?php echo htmlspecialchars($user['name']); ?></td>
                    <td class="px-6 py-4"><?php echo htmlspecialchars($user['email']); ?></td>
                    <td class="px-6 py-4">
                        <!-- Toggle Ativar/Desativar -->
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" 
                                   class="sr-only peer" 
                                   <?php echo $user['is_active'] ? 'checked' : ''; ?>
                                   onchange="toggleUserStatus(<?php echo $user['id']; ?>, this.checked)"
                                   <?php echo $user['id'] === $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                        </label>
                        <span class="ml-2 text-xs <?php echo $user['is_active'] ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo $user['is_active'] ? 'Ativo' : 'Inativo'; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <?php
                        $userPlanSlug = $user['plan'] ?? $defaultPlanSlug;
                        $planLabel = $planMetaMap[$userPlanSlug]['label'] ?? ucfirst($userPlanSlug);
                        $planBadgeColor = $planColorPalette[$userPlanSlug] ?? 'green';
                        ?>
                        <span class="text-<?php echo $planBadgeColor; ?>-600 text-sm font-semibold">
                            <?php echo htmlspecialchars($planLabel); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="text-sm"><?php echo $user['messages_sent'] ?? 0; ?> / <?php echo $user['plan_limit'] ?? 500; ?></span>
                    </td>
                    <td class="px-6 py-4">
                        <?php if ($user['is_admin']): ?>
                            <span class="text-purple-600 text-sm font-semibold">
                                <i class="fas fa-crown"></i> Admin
                            </span>
                        <?php elseif ($user['is_supervisor']): ?>
                            <span class="text-blue-600 text-sm font-semibold">
                                <i class="fas fa-user-tie"></i> Supervisor
                            </span>
                        <?php else: ?>
                            <span class="text-gray-600 text-sm">
                                <i class="fas fa-user"></i> Usuário
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <?php if ($user['two_factor_enabled']): ?>
                            <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">
                                <i class="fas fa-shield-alt"></i> Ativo
                            </span>
                        <?php else: ?>
                            <span class="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded">
                                <i class="fas fa-shield-alt"></i> Inativo
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <?php if (!empty($user['plan_expires_at'])): ?>
                            <?php 
                            $end_date = new DateTime($user['plan_expires_at']);
                            $now = new DateTime();
                            $diff = $now->diff($end_date);
                            $is_expired = $end_date < $now;
                            ?>
                            <div class="text-sm <?php echo $is_expired ? 'text-red-600' : 'text-gray-800'; ?>">
                                <?php echo $end_date->format('d/m/Y'); ?>
                            </div>
                            <?php if ($is_expired): ?>
                                <span class="text-xs text-red-600">Vencido há <?php echo $diff->days; ?> dias</span>
                            <?php else: ?>
                                <span class="text-xs text-green-600"><?php echo $diff->days; ?> dias restantes</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-sm text-gray-400">Sem vencimento</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?php echo formatDate($user['created_at']); ?></td>
                    <td class="px-6 py-4">
                        <button onclick='openEditModal(<?php echo json_encode($user); ?>)' class="text-blue-600 hover:text-blue-800 mr-3">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                        <button onclick="deleteUser(<?php echo $user['id']; ?>)" class="text-red-600 hover:text-red-800">
                            <i class="fas fa-trash"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Adicionar -->
<div id="addModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-8 w-full max-w-md">
        <h2 class="text-2xl font-bold mb-4">Novo Usuário</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Nome</label>
                <input type="text" name="name" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                <input type="email" name="email" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Senha</label>
                <input type="password" name="password" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Plano</label>
                <select name="plan" id="add_plan" onchange="updatePlanLimit('add')" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    <?php foreach ($planSelectOptions as $plan): ?>
                    <option value="<?php echo htmlspecialchars($plan['slug']); ?>" <?php echo $plan['slug'] === $defaultPlanSlug ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($plan['name']); ?> (<?php echo number_format((int) $plan['message_limit']); ?> mensagens)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Limite de Mensagens</label>
                <input type="number" name="plan_limit" id="add_plan_limit" value="<?php echo $defaultPlanLimit; ?>" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" name="is_admin" id="add_is_admin" class="mr-2" onchange="handleRoleChange('add')">
                    <span class="text-sm font-semibold text-purple-700">
                        <i class="fas fa-crown mr-1"></i>Administrador
                    </span>
                </label>
                <p class="text-xs text-gray-500 mt-1 ml-6">Acesso total ao sistema</p>
            </div>
            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" name="is_supervisor" id="add_is_supervisor" class="mr-2" onchange="handleRoleChange('add')">
                    <span class="text-sm font-semibold text-blue-700">
                        <i class="fas fa-user-tie mr-1"></i>Supervisor
                    </span>
                </label>
                <p class="text-xs text-gray-500 mt-1 ml-6">Pode gerenciar atendentes e acessar relatórios</p>
            </div>
            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" name="two_factor_enabled" class="mr-2">
                    <span class="text-sm"><i class="fas fa-shield-alt mr-1"></i>Autenticação de 2 Fatores (2FA)</span>
                </label>
                <p class="text-xs text-gray-500 mt-1 ml-6">Requer código adicional no login</p>
            </div>
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
        <h2 class="text-2xl font-bold mb-4">Editar Usuário</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Nome</label>
                <input type="text" name="name" id="edit_name" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                <input type="email" name="email" id="edit_email" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Nova Senha (deixe em branco para não alterar)</label>
                <input type="password" name="password" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Plano</label>
                <select name="plan" id="edit_plan" onchange="updatePlanLimit('edit')" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    <?php foreach ($planSelectOptions as $plan): ?>
                    <option value="<?php echo htmlspecialchars($plan['slug']); ?>">
                        <?php echo htmlspecialchars($plan['name']); ?> (<?php echo number_format((int) $plan['message_limit']); ?> mensagens)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Limite de Mensagens</label>
                <input type="number" name="plan_limit" id="edit_plan_limit" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" name="is_admin" id="edit_is_admin" class="mr-2" onchange="handleRoleChange('edit')">
                    <span class="text-sm font-semibold text-purple-700">
                        <i class="fas fa-crown mr-1"></i>Administrador
                    </span>
                </label>
                <p class="text-xs text-gray-500 mt-1 ml-6">Acesso total ao sistema</p>
            </div>
            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" name="is_supervisor" id="edit_is_supervisor" class="mr-2" onchange="handleRoleChange('edit')">
                    <span class="text-sm font-semibold text-blue-700">
                        <i class="fas fa-user-tie mr-1"></i>Supervisor
                    </span>
                </label>
                <p class="text-xs text-gray-500 mt-1 ml-6">Pode gerenciar atendentes e acessar relatórios</p>
            </div>
            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" name="two_factor_enabled" id="edit_two_factor_enabled" class="mr-2">
                    <span class="text-sm"><i class="fas fa-shield-alt mr-1"></i>Autenticação de 2 Fatores (2FA)</span>
                </label>
                <p class="text-xs text-gray-500 mt-1 ml-6">Requer código adicional no login</p>
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
const planLimits = <?php echo json_encode($planLimitMap, JSON_UNESCAPED_UNICODE); ?>;
const defaultPlanSlug = '<?php echo $defaultPlanSlug; ?>';

// Controlar exclusão mútua entre Admin e Supervisor
function handleRoleChange(mode) {
    const adminCheckbox = document.getElementById(mode + '_is_admin');
    const supervisorCheckbox = document.getElementById(mode + '_is_supervisor');
    
    if (adminCheckbox.checked) {
        supervisorCheckbox.checked = false;
        supervisorCheckbox.disabled = true;
    } else {
        supervisorCheckbox.disabled = false;
    }
}

function openAddModal() {
    document.getElementById('addModal').classList.remove('hidden');
}

function closeAddModal() {
    document.getElementById('addModal').classList.add('hidden');
}

function openEditModal(user) {
    document.getElementById('edit_id').value = user.id;
    document.getElementById('edit_name').value = user.name;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_is_admin').checked = user.is_admin == 1;
    document.getElementById('edit_is_supervisor').checked = user.is_supervisor == 1;
    document.getElementById('edit_two_factor_enabled').checked = user.two_factor_enabled == 1;
    
    // Aplicar lógica de exclusão mútua
    handleRoleChange('edit');
    
    const editPlanSelect = document.getElementById('edit_plan');
    const userPlan = user.plan || defaultPlanSlug;
    const planExists = Array.from(editPlanSelect.options).some(option => option.value === userPlan);
    if (!planExists && userPlan) {
        const fallbackOption = new Option(userPlan + ' (inativo)', userPlan);
        editPlanSelect.add(fallbackOption);
    }
    editPlanSelect.value = userPlan;
    document.getElementById('edit_plan_limit').value = user.plan_limit || 500;
    document.getElementById('editModal').classList.remove('hidden');
}

function updatePlanLimit(mode) {
    const planSelect = document.getElementById(mode + '_plan');
    const limitInput = document.getElementById(mode + '_plan_limit');
    const selectedPlan = planSelect.value || defaultPlanSlug;
    if (planLimits.hasOwnProperty(selectedPlan)) {
        limitInput.value = planLimits[selectedPlan];
    }
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

function toggleUserStatus(userId, isActive) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="toggle_active">
        <input type="hidden" name="id" value="${userId}">
        <input type="hidden" name="is_active" value="${isActive ? 1 : 0}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function deleteUser(id) {
    if (confirm('Tem certeza que deseja deletar este usuário?')) {
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

function createInstanceForUser(userId) {
    if (!confirm('Deseja criar uma instância automaticamente para este usuário?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'create_for_user');
    formData.append('user_id', userId);
    
    // Mostrar loading
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Criando...';
    button.disabled = true;
    
    fetch('/api/admin_instance_manager.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('success', `Instância criada com sucesso para o usuário! Nome: ${data.instance_name}`);
            // Recarregar a página após 2 segundos
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            showMessage('error', 'Erro ao criar instância: ' + data.message);
            button.innerHTML = originalText;
            button.disabled = false;
        }
    })
    .catch(error => {
        showMessage('error', 'Erro de conexão: ' + error.message);
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

function showMessage(type, message) {
    const alertClass = type === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700';
    const icon = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
    
    const messageDiv = document.createElement('div');
    messageDiv.className = `${alertClass} border rounded-lg p-4 mb-4`;
    messageDiv.innerHTML = `
        <i class="${icon} mr-2"></i>
        ${message}
    `;
    
    // Inserir no topo da página
    const container = document.querySelector('.bg-white.rounded-lg.shadow-lg');
    container.insertBefore(messageDiv, container.firstChild);
    
    // Remover após 5 segundos
    setTimeout(() => {
        messageDiv.remove();
    }, 5000);
}

// Filtrar usuários na tabela
function filterUsers() {
    const searchTerm = document.getElementById('search-input').value.toLowerCase();
    const statusFilter = document.getElementById('status-filter').value;
    const planFilter = document.getElementById('plan-filter').value;
    const typeFilter = document.getElementById('type-filter').value;
    
    const rows = document.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const name = row.cells[0]?.textContent.toLowerCase() || '';
        const email = row.cells[1]?.textContent.toLowerCase() || '';
        const isActive = row.querySelector('input[type="checkbox"]')?.checked;
        const plan = row.cells[3]?.textContent.toLowerCase() || '';
        const isAdmin = row.cells[5]?.textContent.toLowerCase().includes('admin');
        
        let show = true;
        
        // Filtro de busca
        if (searchTerm && !name.includes(searchTerm) && !email.includes(searchTerm)) {
            show = false;
        }
        
        // Filtro de status
        if (statusFilter === 'active' && !isActive) show = false;
        if (statusFilter === 'inactive' && isActive) show = false;
        
        // Filtro de plano
        if (planFilter && !plan.includes(planFilter.toLowerCase())) show = false;
        
        // Filtro de tipo
        if (typeFilter === 'admin' && !isAdmin) show = false;
        if (typeFilter === 'user' && isAdmin) show = false;
        
        row.style.display = show ? '' : 'none';
    });
}

// Exportar usuários para CSV
function exportUsers() {
    const rows = document.querySelectorAll('tbody tr');
    let csv = 'Nome,Email,Status,Plano,Limite,Tipo,2FA,Criado em\n';
    
    rows.forEach(row => {
        if (row.style.display !== 'none') {
            const name = row.cells[0]?.textContent.trim() || '';
            const email = row.cells[1]?.textContent.trim() || '';
            const status = row.querySelector('input[type="checkbox"]')?.checked ? 'Ativo' : 'Inativo';
            const plan = row.cells[3]?.textContent.trim() || '';
            const limit = row.cells[4]?.textContent.trim() || '';
            const type = row.cells[5]?.textContent.trim() || '';
            const twofa = row.cells[6]?.textContent.trim() || '';
            const created = row.cells[8]?.textContent.trim() || '';
            
            csv += `"${name}","${email}","${status}","${plan}","${limit}","${type}","${twofa}","${created}"\n`;
        }
    });
    
    // Download do arquivo
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'usuarios_' + new Date().toISOString().split('T')[0] + '.csv';
    link.click();
}
</script>
    <style>
        /* Garantir visibilidade dos TÍTULOS dos cards */
        .grid > div > div[class*="text-sm"] {
            color: #1e40af !important;
            font-weight: 600 !important;
            font-size: 0.875rem !important;
            opacity: 1 !important;
            display: block !important;
        }
        
        /* Garantir visibilidade MÁXIMA dos números nos cards */
        .grid > div > div[class*="text-3xl"] {
            color: #000000 !important;
            font-weight: 900 !important;
            font-size: 2rem !important;
            line-height: 1.2 !important;
            opacity: 1 !important;
        }
        
        /* Forçar visibilidade dos cards - títulos */
        .bg-blue-50 > div:first-child {
            color: #1e40af !important;
        }
        
        .bg-green-50 > div:first-child {
            color: #15803d !important;
        }
        
        .bg-purple-50 > div:first-child {
            color: #7e22ce !important;
        }
        
        .bg-yellow-50 > div:first-child {
            color: #a16207 !important;
        }
        
        /* Forçar visibilidade dos cards - números */
        .bg-blue-50 .text-3xl,
        .bg-green-50 .text-3xl,
        .bg-purple-50 .text-3xl,
        .bg-yellow-50 .text-3xl {
            color: #1a1a1a !important;
            font-weight: 900 !important;
        }
        
        /* Correção específica para botões de ação na página de usuários */
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
