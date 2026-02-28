<?php
/**
 * API de Gerenciamento de Usuários Supervisor (Atendentes)
 * Permite criar, editar, bloquear e excluir atendentes
 */

session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Verificar se o usuário é supervisor
$stmt = $pdo->prepare("SELECT user_type, max_supervisor_users, supervisor_users_count FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || ($user['user_type'] !== 'supervisor' && $user['user_type'] !== 'admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado. Apenas supervisores podem gerenciar atendentes.']);
    exit;
}

try {
    switch ($action) {
        case 'list':
            listSupervisorUsers($pdo, $user_id);
            break;
            
        case 'create':
            createSupervisorUser($pdo, $user_id, $user);
            break;
            
        case 'update':
            updateSupervisorUser($pdo, $user_id);
            break;
            
        case 'block':
            blockSupervisorUser($pdo, $user_id);
            break;
            
        case 'unblock':
            unblockSupervisorUser($pdo, $user_id);
            break;
            
        case 'delete':
            deleteSupervisorUser($pdo, $user_id);
            break;
            
        case 'get':
            getSupervisorUser($pdo, $user_id);
            break;
            
        case 'assign_departments':
            assignDepartments($pdo, $user_id);
            break;
            
        case 'stats':
            getSupervisorUserStats($pdo, $user_id);
            break;
            
        case 'update_permissions':
            updateMenuPermissions($pdo, $user_id);
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
 * Lista todos os atendentes do supervisor
 */
function listSupervisorUsers($pdo, $supervisor_id) {
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    $department = $_GET['department'] ?? '';
    
    $sql = "
        SELECT 
            su.*,
            GROUP_CONCAT(DISTINCT d.name SEPARATOR ', ') as departments,
            GROUP_CONCAT(DISTINCT d.id) as department_ids,
            COUNT(DISTINCT c.id) as active_conversations
        FROM supervisor_users su
        LEFT JOIN user_departments ud ON su.id = ud.user_id
        LEFT JOIN departments d ON ud.department_id = d.id
        LEFT JOIN chat_conversations c ON su.id = c.assigned_to AND c.status IN ('open', 'in_progress')
        WHERE su.supervisor_id = :supervisor_id
    ";
    
    $params = [':supervisor_id' => $supervisor_id];
    
    if ($search) {
        $sql .= " AND (su.name LIKE :search OR su.email LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    if ($status) {
        $sql .= " AND su.status = :status";
        $params[':status'] = $status;
    }
    
    if ($department) {
        $sql .= " AND d.id = :department";
        $params[':department'] = $department;
    }
    
    $sql .= " GROUP BY su.id ORDER BY su.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'total' => count($users)
    ]);
}

/**
 * Cria um novo atendente
 */
function createSupervisorUser($pdo, $supervisor_id, $supervisor_data) {
    // Verificar limite de usuários
    if ($supervisor_data['max_supervisor_users'] > 0 && 
        $supervisor_data['supervisor_users_count'] >= $supervisor_data['max_supervisor_users']) {
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'error' => 'Limite de atendentes atingido. Faça upgrade do seu plano.'
        ]);
        return;
    }
    
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';
    $departments = json_decode($_POST['departments'] ?? '[]', true);
    $instanceType = $_POST['instance_type'] ?? 'supervisor'; // 'supervisor' ou 'own'
    $instanceConfigAllowed = isset($_POST['instance_config_allowed']) ? 1 : 0;
    $canManageFlows = isset($_POST['can_manage_flows']) ? 1 : 0;
    
    // Validações
    if (empty($name) || empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nome, email e senha são obrigatórios']);
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email inválido']);
        return;
    }
    
    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Senha deve ter no mínimo 6 caracteres']);
        return;
    }
    
    // Verificar se email já existe em supervisor_users
    $stmt = $pdo->prepare("SELECT id FROM supervisor_users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email já cadastrado como atendente']);
        return;
    }
    
    // Verificar se email já existe em users (evitar conflitos de login)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email já cadastrado como usuário/supervisor. Use outro email.']);
        return;
    }
    
    $pdo->beginTransaction();
    
    try {
        // Criar usuário
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        
        // Verificar se colunas de instância existem
        $useOwnInstance = ($instanceType === 'own') ? 1 : 0;
        
        $stmt = $pdo->prepare("
            INSERT INTO supervisor_users (supervisor_id, name, email, phone, password, status, must_change_password, use_own_instance, instance_config_allowed, can_manage_flows)
            VALUES (:supervisor_id, :name, :email, :phone, :password, 'active', 1, :use_own_instance, :instance_config_allowed, :can_manage_flows)
        ");
        
        $stmt->execute([
            ':supervisor_id' => $supervisor_id,
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone,
            ':password' => $hashed_password,
            ':use_own_instance' => $useOwnInstance,
            ':instance_config_allowed' => $instanceConfigAllowed,
            ':can_manage_flows' => $canManageFlows
        ]);
        
        $user_id = $pdo->lastInsertId();
        
        // Se usar instância própria, criar registro na tabela de instâncias
        if ($useOwnInstance) {
            $instanceName = 'att_' . $user_id . '_' . substr(md5($email), 0, 8);
            
            // Verificar se tabela existe antes de inserir
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO attendant_instances (attendant_id, supervisor_id, instance_name, status)
                    VALUES (?, ?, ?, 'disconnected')
                ");
                $stmt->execute([$user_id, $supervisor_id, $instanceName]);
            } catch (PDOException $e) {
                // Tabela pode não existir ainda, ignorar
            }
        }
        
        // Atribuir setores
        if (!empty($departments)) {
            $stmt = $pdo->prepare("
                INSERT INTO user_departments (user_id, department_id, is_primary)
                VALUES (:user_id, :department_id, :is_primary)
            ");
            
            foreach ($departments as $index => $dept_id) {
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':department_id' => $dept_id,
                    ':is_primary' => ($index === 0 ? 1 : 0)
                ]);
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Atendente criado com sucesso',
            'user_id' => $user_id
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Atualiza um atendente
 */
function updateSupervisorUser($pdo, $supervisor_id) {
    $user_id = $_POST['user_id'] ?? 0;
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';
    $departments = json_decode($_POST['departments'] ?? '[]', true);
    $instanceType = $_POST['instance_type'] ?? 'supervisor';
    $instanceConfigAllowed = isset($_POST['instance_config_allowed']) ? 1 : 0;
    $canManageFlows = isset($_POST['can_manage_flows']) ? 1 : 0;
    
    // Verificar se o atendente pertence ao supervisor
    $stmt = $pdo->prepare("SELECT id FROM supervisor_users WHERE id = ? AND supervisor_id = ?");
    $stmt->execute([$user_id, $supervisor_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Atendente não encontrado']);
        return;
    }
    
    $pdo->beginTransaction();
    
    try {
        // Atualizar dados básicos
        $useOwnInstance = ($instanceType === 'own') ? 1 : 0;
        
        $sql = "UPDATE supervisor_users SET name = :name, email = :email, phone = :phone, use_own_instance = :use_own_instance, instance_config_allowed = :instance_config_allowed, can_manage_flows = :can_manage_flows";
        $params = [
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone,
            ':use_own_instance' => $useOwnInstance,
            ':instance_config_allowed' => $instanceConfigAllowed,
            ':can_manage_flows' => $canManageFlows,
            ':user_id' => $user_id
        ];
        
        // Se senha foi fornecida, atualizar também
        if (!empty($password)) {
            $sql .= ", password = :password, must_change_password = 0";
            $params[':password'] = password_hash($password, PASSWORD_BCRYPT);
        }
        
        $sql .= " WHERE id = :user_id";
        
        // Se mudou para instância própria, criar registro se não existir
        if ($useOwnInstance) {
            try {
                $stmtCheck = $pdo->prepare("SELECT id FROM attendant_instances WHERE attendant_id = ?");
                $stmtCheck->execute([$user_id]);
                if (!$stmtCheck->fetch()) {
                    $instanceName = 'att_' . $user_id . '_' . substr(md5($email), 0, 8);
                    $stmtIns = $pdo->prepare("INSERT INTO attendant_instances (attendant_id, supervisor_id, instance_name, status) VALUES (?, ?, ?, 'disconnected')");
                    $stmtIns->execute([$user_id, $supervisor_id, $instanceName]);
                }
            } catch (PDOException $e) {
                // Tabela pode não existir, ignorar
            }
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Atualizar setores
        if (isset($_POST['departments'])) {
            // Remover setores antigos
            $stmt = $pdo->prepare("DELETE FROM user_departments WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // Adicionar novos setores
            if (!empty($departments)) {
                $stmt = $pdo->prepare("
                    INSERT INTO user_departments (user_id, department_id, is_primary)
                    VALUES (:user_id, :department_id, :is_primary)
                ");
                
                foreach ($departments as $index => $dept_id) {
                    $stmt->execute([
                        ':user_id' => $user_id,
                        ':department_id' => $dept_id,
                        ':is_primary' => ($index === 0 ? 1 : 0)
                    ]);
                }
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Atendente atualizado com sucesso'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Bloqueia um atendente
 */
function blockSupervisorUser($pdo, $supervisor_id) {
    $user_id = $_POST['user_id'] ?? 0;
    
    $stmt = $pdo->prepare("
        UPDATE supervisor_users 
        SET status = 'blocked' 
        WHERE id = ? AND supervisor_id = ?
    ");
    
    $stmt->execute([$user_id, $supervisor_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Atendente bloqueado com sucesso'
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Atendente não encontrado']);
    }
}

/**
 * Desbloqueia um atendente
 */
function unblockSupervisorUser($pdo, $supervisor_id) {
    $user_id = $_POST['user_id'] ?? 0;
    
    $stmt = $pdo->prepare("
        UPDATE supervisor_users 
        SET status = 'active' 
        WHERE id = ? AND supervisor_id = ?
    ");
    
    $stmt->execute([$user_id, $supervisor_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Atendente desbloqueado com sucesso'
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Atendente não encontrado']);
    }
}

/**
 * Exclui um atendente
 */
function deleteSupervisorUser($pdo, $supervisor_id) {
    $user_id = $_POST['user_id'] ?? 0;
    
    // Verificar se tem conversas ativas
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM chat_conversations 
        WHERE assigned_to = ? AND status IN ('open', 'in_progress')
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'Não é possível excluir atendente com conversas ativas. Transfira ou finalize as conversas primeiro.'
        ]);
        return;
    }
    
    $stmt = $pdo->prepare("
        DELETE FROM supervisor_users 
        WHERE id = ? AND supervisor_id = ?
    ");
    
    $stmt->execute([$user_id, $supervisor_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Atendente excluído com sucesso'
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Atendente não encontrado']);
    }
}

/**
 * Obtém dados de um atendente específico
 */
function getSupervisorUser($pdo, $supervisor_id) {
    $user_id = $_GET['user_id'] ?? 0;
    
    $stmt = $pdo->prepare("
        SELECT 
            su.*,
            GROUP_CONCAT(DISTINCT ud.department_id) as department_ids
        FROM supervisor_users su
        LEFT JOIN user_departments ud ON su.id = ud.user_id
        WHERE su.id = ? AND su.supervisor_id = ?
        GROUP BY su.id
    ");
    
    $stmt->execute([$user_id, $supervisor_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Converter department_ids para array
        $user['department_ids'] = $user['department_ids'] ? explode(',', $user['department_ids']) : [];
        
        echo json_encode([
            'success' => true,
            'user' => $user
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Atendente não encontrado']);
    }
}

/**
 * Atribui setores a um atendente
 */
function assignDepartments($pdo, $supervisor_id) {
    $user_id = $_POST['user_id'] ?? 0;
    $departments = json_decode($_POST['departments'] ?? '[]', true);
    
    // Verificar se o atendente pertence ao supervisor
    $stmt = $pdo->prepare("SELECT id FROM supervisor_users WHERE id = ? AND supervisor_id = ?");
    $stmt->execute([$user_id, $supervisor_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Atendente não encontrado']);
        return;
    }
    
    $pdo->beginTransaction();
    
    try {
        // Remover setores antigos
        $stmt = $pdo->prepare("DELETE FROM user_departments WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Adicionar novos setores
        if (!empty($departments)) {
            $stmt = $pdo->prepare("
                INSERT INTO user_departments (user_id, department_id, is_primary)
                VALUES (:user_id, :department_id, :is_primary)
            ");
            
            foreach ($departments as $index => $dept_id) {
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':department_id' => $dept_id,
                    ':is_primary' => ($index === 0 ? 1 : 0)
                ]);
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Setores atualizados com sucesso'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Obtém estatísticas de um atendente
 */
function getSupervisorUserStats($pdo, $supervisor_id) {
    $user_id = $_GET['user_id'] ?? 0;
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT c.id) as total_conversations,
            COUNT(DISTINCT CASE WHEN c.status = 'open' THEN c.id END) as open_conversations,
            COUNT(DISTINCT CASE WHEN c.status = 'in_progress' THEN c.id END) as in_progress_conversations,
            COUNT(DISTINCT CASE WHEN c.status = 'resolved' THEN c.id END) as resolved_conversations,
            COUNT(DISTINCT CASE WHEN c.status = 'closed' THEN c.id END) as closed_conversations,
            AVG(TIMESTAMPDIFF(MINUTE, c.created_at, c.resolved_at)) as avg_resolution_time_minutes,
            COUNT(DISTINCT cm.id) as total_messages_sent
        FROM supervisor_users su
        LEFT JOIN chat_conversations c ON su.id = c.assigned_to
        LEFT JOIN chat_messages cm ON c.id = cm.conversation_id AND cm.from_me = 1
        WHERE su.id = ? AND su.supervisor_id = ?
        GROUP BY su.id
    ");
    
    $stmt->execute([$user_id, $supervisor_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stats) {
        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Atendente não encontrado']);
    }
}

/**
 * Atualizar permissões de menu do atendente
 */
function updateMenuPermissions($pdo, $supervisor_id) {
    $attendant_id = $_POST['user_id'] ?? null;
    $permissions = $_POST['permissions'] ?? null;
    
    if (!$attendant_id || !$permissions) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
        return;
    }
    
    // Verificar se o atendente pertence ao supervisor
    $stmt = $pdo->prepare("SELECT id FROM supervisor_users WHERE id = ? AND supervisor_id = ?");
    $stmt->execute([$attendant_id, $supervisor_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Você não tem permissão para editar este atendente']);
        return;
    }
    
    // Atualizar permissões
    $stmt = $pdo->prepare("UPDATE supervisor_users SET allowed_menus = ? WHERE id = ?");
    $stmt->execute([$permissions, $attendant_id]);
    
    // Log da atividade (opcional - ignorar se tabela não existir)
    try {
        $stmt = $pdo->prepare("
            INSERT INTO supervisor_activity_logs (user_id, supervisor_user_id, action, description)
            VALUES (?, ?, 'update_permissions', 'Permissões de menu atualizadas')
        ");
        $stmt->execute([$supervisor_id, $attendant_id]);
    } catch (PDOException $e) {
        // Ignorar erro se tabela não existir
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Permissões atualizadas com sucesso'
    ]);
}
