<?php
/**
 * API de Gerenciamento de Setores/Departamentos
 * Permite criar, editar, ativar/desativar e excluir setores
 */

session_start();
require_once '../config/database.php';
require_once '../includes/default_departments.php';

header('Content-Type: application/json');

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Verificar se o usuário é supervisor ou admin
$stmt = $pdo->prepare("SELECT user_type FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || ($user['user_type'] !== 'supervisor' && $user['user_type'] !== 'admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
    exit;
}

try {
    switch ($action) {
        case 'list':
            listDepartments($pdo, $user_id);
            break;
            
        case 'create':
            createDepartment($pdo, $user_id);
            break;
            
        case 'update':
            updateDepartment($pdo, $user_id);
            break;
            
        case 'delete':
            deleteDepartment($pdo, $user_id);
            break;
            
        case 'toggle_status':
            toggleDepartmentStatus($pdo, $user_id);
            break;
            
        case 'get':
            getDepartment($pdo, $user_id);
            break;
            
        case 'create_defaults':
            createDefaultDepartmentsForSupervisor($pdo, $user_id);
            break;
            
        case 'get_users':
            getDepartmentUsers($pdo, $user_id);
            break;
            
        case 'stats':
            getDepartmentStats($pdo, $user_id);
            break;
            
        case 'colors':
            echo json_encode([
                'success' => true,
                'colors' => getDepartmentColors()
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ação inválida']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Lista todos os setores do supervisor
 */
function listDepartments($pdo, $supervisor_id) {
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    
    $sql = "
        SELECT 
            d.*,
            COUNT(DISTINCT ud.user_id) as user_count,
            COUNT(DISTINCT c.id) as active_conversations
        FROM departments d
        LEFT JOIN user_departments ud ON d.id = ud.department_id
        LEFT JOIN chat_conversations c ON d.id = c.department_id AND c.status IN ('open', 'in_progress')
        WHERE d.supervisor_id = :supervisor_id
    ";
    
    $params = [':supervisor_id' => $supervisor_id];
    
    if ($search) {
        $sql .= " AND d.name LIKE :search";
        $params[':search'] = "%$search%";
    }
    
    if ($status !== '') {
        $sql .= " AND d.is_active = :status";
        $params[':status'] = $status;
    }
    
    $sql .= " GROUP BY d.id ORDER BY d.name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'departments' => $departments,
        'total' => count($departments)
    ]);
}

/**
 * Cria um novo setor
 */
function createDepartment($pdo, $supervisor_id) {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $color = $_POST['color'] ?? '#3B82F6';
    
    // Validações
    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nome é obrigatório']);
        return;
    }
    
    // Verificar se já existe setor com mesmo nome
    $stmt = $pdo->prepare("
        SELECT id FROM departments 
        WHERE supervisor_id = ? AND name = ?
    ");
    $stmt->execute([$supervisor_id, $name]);
    
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Já existe um setor com este nome']);
        return;
    }
    
    // Criar setor
    $stmt = $pdo->prepare("
        INSERT INTO departments (supervisor_id, name, description, color, is_active)
        VALUES (:supervisor_id, :name, :description, :color, 1)
    ");
    
    $stmt->execute([
        ':supervisor_id' => $supervisor_id,
        ':name' => $name,
        ':description' => $description,
        ':color' => $color
    ]);
    
    $department_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Setor criado com sucesso',
        'department_id' => $department_id
    ]);
}

/**
 * Atualiza um setor
 */
function updateDepartment($pdo, $supervisor_id) {
    $department_id = $_POST['department_id'] ?? 0;
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $color = $_POST['color'] ?? '#3B82F6';
    
    // Verificar se o setor pertence ao supervisor
    $stmt = $pdo->prepare("SELECT id FROM departments WHERE id = ? AND supervisor_id = ?");
    $stmt->execute([$department_id, $supervisor_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Setor não encontrado']);
        return;
    }
    
    // Verificar se já existe outro setor com mesmo nome
    $stmt = $pdo->prepare("
        SELECT id FROM departments 
        WHERE supervisor_id = ? AND name = ? AND id != ?
    ");
    $stmt->execute([$supervisor_id, $name, $department_id]);
    
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Já existe um setor com este nome']);
        return;
    }
    
    // Atualizar setor
    $stmt = $pdo->prepare("
        UPDATE departments 
        SET name = :name, description = :description, color = :color
        WHERE id = :department_id
    ");
    
    $stmt->execute([
        ':name' => $name,
        ':description' => $description,
        ':color' => $color,
        ':department_id' => $department_id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Setor atualizado com sucesso'
    ]);
}

/**
 * Exclui um setor
 */
function deleteDepartment($pdo, $supervisor_id) {
    $department_id = $_POST['department_id'] ?? 0;
    
    // Verificar se tem conversas ativas
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM chat_conversations 
        WHERE department_id = ? AND status IN ('open', 'in_progress')
    ");
    $stmt->execute([$department_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'Não é possível excluir setor com conversas ativas. Transfira ou finalize as conversas primeiro.'
        ]);
        return;
    }
    
    // Verificar se tem usuários atribuídos
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM user_departments 
        WHERE department_id = ?
    ");
    $stmt->execute([$department_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'Não é possível excluir setor com usuários atribuídos. Remova os usuários primeiro.'
        ]);
        return;
    }
    
    // Excluir setor
    $stmt = $pdo->prepare("
        DELETE FROM departments 
        WHERE id = ? AND supervisor_id = ?
    ");
    
    $stmt->execute([$department_id, $supervisor_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Setor excluído com sucesso'
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Setor não encontrado']);
    }
}

/**
 * Ativa/Desativa um setor
 */
function toggleDepartmentStatus($pdo, $supervisor_id) {
    $department_id = $_POST['department_id'] ?? 0;
    
    // Verificar se o setor pertence ao supervisor
    $stmt = $pdo->prepare("
        SELECT is_active FROM departments 
        WHERE id = ? AND supervisor_id = ?
    ");
    $stmt->execute([$department_id, $supervisor_id]);
    $dept = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dept) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Setor não encontrado']);
        return;
    }
    
    $new_status = $dept['is_active'] ? 0 : 1;
    
    $stmt = $pdo->prepare("
        UPDATE departments 
        SET is_active = :status
        WHERE id = :department_id
    ");
    
    $stmt->execute([
        ':status' => $new_status,
        ':department_id' => $department_id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => $new_status ? 'Setor ativado' : 'Setor desativado',
        'new_status' => $new_status
    ]);
}

/**
 * Obtém dados de um setor específico
 */
function getDepartment($pdo, $supervisor_id) {
    $department_id = $_GET['department_id'] ?? 0;
    
    $stmt = $pdo->prepare("
        SELECT 
            d.*,
            COUNT(DISTINCT ud.user_id) as user_count
        FROM departments d
        LEFT JOIN user_departments ud ON d.id = ud.department_id
        WHERE d.id = ? AND d.supervisor_id = ?
        GROUP BY d.id
    ");
    
    $stmt->execute([$department_id, $supervisor_id]);
    $department = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($department) {
        echo json_encode([
            'success' => true,
            'department' => $department
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Setor não encontrado']);
    }
}

/**
 * Cria setores padrão para o supervisor
 */
function createDefaultDepartmentsForSupervisor($pdo, $supervisor_id) {
    // Verificar se já tem setores
    if (supervisorHasDepartments($pdo, $supervisor_id)) {
        echo json_encode([
            'success' => false,
            'error' => 'Supervisor já possui setores criados'
        ]);
        return;
    }
    
    $result = createDefaultDepartments($pdo, $supervisor_id);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Setores padrão criados com sucesso',
            'created_count' => $result['created_count']
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $result['error']
        ]);
    }
}

/**
 * Obtém usuários de um setor
 */
function getDepartmentUsers($pdo, $supervisor_id) {
    $department_id = $_GET['department_id'] ?? 0;
    
    // Verificar se o setor pertence ao supervisor
    $stmt = $pdo->prepare("SELECT id FROM departments WHERE id = ? AND supervisor_id = ?");
    $stmt->execute([$department_id, $supervisor_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Setor não encontrado']);
        return;
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            su.*,
            ud.is_primary
        FROM supervisor_users su
        INNER JOIN user_departments ud ON su.id = ud.user_id
        WHERE ud.department_id = ? AND su.supervisor_id = ?
        ORDER BY ud.is_primary DESC, su.name ASC
    ");
    
    $stmt->execute([$department_id, $supervisor_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'total' => count($users)
    ]);
}

/**
 * Obtém estatísticas de um setor
 */
function getDepartmentStats($pdo, $supervisor_id) {
    $department_id = $_GET['department_id'] ?? 0;
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT c.id) as total_conversations,
            COUNT(DISTINCT CASE WHEN c.status = 'open' THEN c.id END) as open_conversations,
            COUNT(DISTINCT CASE WHEN c.status = 'in_progress' THEN c.id END) as in_progress_conversations,
            COUNT(DISTINCT CASE WHEN c.status = 'resolved' THEN c.id END) as resolved_conversations,
            COUNT(DISTINCT CASE WHEN c.status = 'closed' THEN c.id END) as closed_conversations,
            AVG(TIMESTAMPDIFF(MINUTE, c.created_at, c.resolved_at)) as avg_resolution_time_minutes,
            COUNT(DISTINCT ud.user_id) as user_count
        FROM departments d
        LEFT JOIN chat_conversations c ON d.id = c.department_id
        LEFT JOIN user_departments ud ON d.id = ud.department_id
        WHERE d.id = ? AND d.supervisor_id = ?
        GROUP BY d.id
    ");
    
    $stmt->execute([$department_id, $supervisor_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stats) {
        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Setor não encontrado']);
    }
}
