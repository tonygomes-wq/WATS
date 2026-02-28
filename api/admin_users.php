<?php
/**
 * API de Gerenciamento de Usuários (Admin)
 * Operações administrativas de usuários
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

// Verificar se é admin
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            listUsers($pdo);
            break;
            
        case 'get':
            getUser($pdo);
            break;
            
        case 'stats':
            getUserStats($pdo);
            break;
            
        case 'reset_password':
            resetUserPassword($pdo);
            break;
            
        case 'send_reset_email':
            sendPasswordResetEmail($pdo);
            break;
            
        case 'export':
            exportUsers($pdo);
            break;
            
        case 'bulk_action':
            bulkAction($pdo);
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
 * Lista usuários com filtros
 */
function listUsers($pdo) {
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    $plan = $_GET['plan'] ?? '';
    $type = $_GET['type'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(10, intval($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;
    
    $where = ['1=1'];
    $params = [];
    
    if ($search) {
        $where[] = "(name LIKE ? OR email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($status === 'active') {
        $where[] = "is_active = 1";
    } elseif ($status === 'inactive') {
        $where[] = "is_active = 0";
    }
    
    if ($plan) {
        $where[] = "plan = ?";
        $params[] = $plan;
    }
    
    if ($type === 'admin') {
        $where[] = "is_admin = 1";
    } elseif ($type === 'user') {
        $where[] = "is_admin = 0";
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Contar total
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $whereClause");
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();
    
    // Buscar usuários
    $sql = "SELECT id, name, email, is_admin, is_supervisor, is_active, two_factor_enabled, 
                   plan, plan_limit, messages_sent, plan_expires_at, created_at, last_login
            FROM users 
            WHERE $whereClause 
            ORDER BY created_at DESC 
            LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'total' => $total,
        'page' => $page,
        'pages' => ceil($total / $limit)
    ]);
}

/**
 * Obtém dados de um usuário
 */
function getUser($pdo) {
    $id = intval($_GET['id'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        unset($user['password']); // Não retornar senha
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Usuário não encontrado']);
    }
}

/**
 * Estatísticas de usuários
 */
function getUserStats($pdo) {
    $stats = [];
    
    // Total de usuários
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $stats['total'] = $stmt->fetchColumn();
    
    // Ativos
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1");
    $stats['active'] = $stmt->fetchColumn();
    
    // Inativos
    $stats['inactive'] = $stats['total'] - $stats['active'];
    
    // Admins
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 1");
    $stats['admins'] = $stmt->fetchColumn();
    
    // Com 2FA
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE two_factor_enabled = 1");
    $stats['with_2fa'] = $stmt->fetchColumn();
    
    // Por plano
    $stmt = $pdo->query("SELECT plan, COUNT(*) as count FROM users GROUP BY plan");
    $stats['by_plan'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Novos este mês
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");
    $stats['new_this_month'] = $stmt->fetchColumn();
    
    // Planos vencidos
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE plan_expires_at IS NOT NULL AND plan_expires_at < NOW()");
    $stats['expired_plans'] = $stmt->fetchColumn();
    
    echo json_encode(['success' => true, 'stats' => $stats]);
}

/**
 * Reset de senha (gera nova senha aleatória)
 */
function resetUserPassword($pdo) {
    $id = intval($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID inválido']);
        return;
    }
    
    // Gerar senha aleatória
    $newPassword = bin2hex(random_bytes(4)); // 8 caracteres
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
    
    $stmt = $pdo->prepare("UPDATE users SET password = ?, must_change_password = 1 WHERE id = ?");
    $result = $stmt->execute([$hashedPassword, $id]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Senha resetada com sucesso',
            'new_password' => $newPassword
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Usuário não encontrado']);
    }
}

/**
 * Enviar email de reset de senha
 */
function sendPasswordResetEmail($pdo) {
    $id = intval($_POST['id'] ?? 0);
    
    // Buscar usuário
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Usuário não encontrado']);
        return;
    }
    
    // Gerar token de reset
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Salvar token (verificar se coluna existe)
    try {
        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
        $stmt->execute([$token, $expires, $id]);
    } catch (PDOException $e) {
        // Colunas podem não existir
        echo json_encode([
            'success' => false,
            'error' => 'Funcionalidade de reset por email não configurada. Use o reset manual.'
        ]);
        return;
    }
    
    // Enviar email (simplificado - em produção usar biblioteca de email)
    $resetLink = "https://" . ($_SERVER['HTTP_HOST'] ?? 'wats.macip.com.br') . "/reset_password.php?token=$token";
    
    $subject = "WATS - Redefinição de Senha";
    $message = "Olá {$user['name']},\n\n";
    $message .= "Você solicitou a redefinição de sua senha.\n\n";
    $message .= "Clique no link abaixo para criar uma nova senha:\n";
    $message .= "$resetLink\n\n";
    $message .= "Este link expira em 1 hora.\n\n";
    $message .= "Se você não solicitou esta redefinição, ignore este email.\n\n";
    $message .= "Atenciosamente,\nEquipe WATS";
    
    $headers = "From: noreply@wats.macip.com.br\r\n";
    $headers .= "Reply-To: suporte@macip.com.br\r\n";
    
    $sent = @mail($user['email'], $subject, $message, $headers);
    
    if ($sent) {
        echo json_encode([
            'success' => true,
            'message' => 'Email de redefinição enviado para ' . $user['email']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Erro ao enviar email. Verifique as configurações do servidor.'
        ]);
    }
}

/**
 * Exportar usuários
 */
function exportUsers($pdo) {
    $format = $_GET['format'] ?? 'csv';
    
    $stmt = $pdo->query("
        SELECT id, name, email, is_admin, is_active, two_factor_enabled, 
               plan, plan_limit, messages_sent, plan_expires_at, created_at
        FROM users 
        ORDER BY created_at DESC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format === 'json') {
        echo json_encode(['success' => true, 'users' => $users]);
        return;
    }
    
    // CSV
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="usuarios_' . date('Y-m-d') . '.csv"');
    
    echo "\xEF\xBB\xBF"; // BOM UTF-8
    
    $output = fopen('php://output', 'w');
    
    // Header
    fputcsv($output, ['ID', 'Nome', 'Email', 'Admin', 'Ativo', '2FA', 'Plano', 'Limite', 'Mensagens', 'Vencimento', 'Criado em'], ';');
    
    foreach ($users as $user) {
        fputcsv($output, [
            $user['id'],
            $user['name'],
            $user['email'],
            $user['is_admin'] ? 'Sim' : 'Não',
            $user['is_active'] ? 'Sim' : 'Não',
            $user['two_factor_enabled'] ? 'Sim' : 'Não',
            $user['plan'] ?? 'free',
            $user['plan_limit'] ?? 500,
            $user['messages_sent'] ?? 0,
            $user['plan_expires_at'] ?? 'Sem vencimento',
            $user['created_at']
        ], ';');
    }
    
    fclose($output);
    exit;
}

/**
 * Ação em lote
 */
function bulkAction($pdo) {
    $action = $_POST['bulk_action'] ?? '';
    $ids = json_decode($_POST['ids'] ?? '[]', true);
    
    if (empty($ids)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nenhum usuário selecionado']);
        return;
    }
    
    // Remover o próprio usuário da lista
    $ids = array_filter($ids, fn($id) => $id != $_SESSION['user_id']);
    
    if (empty($ids)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Você não pode executar esta ação em si mesmo']);
        return;
    }
    
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    switch ($action) {
        case 'activate':
            $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $message = count($ids) . ' usuário(s) ativado(s)';
            break;
            
        case 'deactivate':
            $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $message = count($ids) . ' usuário(s) desativado(s)';
            break;
            
        case 'enable_2fa':
            $stmt = $pdo->prepare("UPDATE users SET two_factor_enabled = 1 WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $message = '2FA habilitado para ' . count($ids) . ' usuário(s)';
            break;
            
        case 'disable_2fa':
            $stmt = $pdo->prepare("UPDATE users SET two_factor_enabled = 0 WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $message = '2FA desabilitado para ' . count($ids) . ' usuário(s)';
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ação em lote inválida']);
            return;
    }
    
    echo json_encode(['success' => true, 'message' => $message]);
}
