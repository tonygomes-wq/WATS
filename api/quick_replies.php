<?php
/**
 * API de Respostas Rápidas/Templates
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
$user_type = $_SESSION['user_type'] ?? 'user';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            listTemplates($pdo, $user_id, $user_type);
            break;
            
        case 'get':
            getTemplate($pdo, $user_id, $user_type);
            break;
            
        case 'create':
            createTemplate($pdo, $user_id);
            break;
            
        case 'update':
            updateTemplate($pdo, $user_id);
            break;
            
        case 'delete':
            deleteTemplate($pdo, $user_id);
            break;
            
        case 'toggle_status':
            toggleStatus($pdo, $user_id);
            break;
            
        case 'stats':
            getStats($pdo, $user_id);
            break;
            
        case 'categories':
            getCategories($pdo, $user_id);
            break;
            
        case 'use':
            incrementUsage($pdo, $user_id, $user_type);
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
 * Listar templates
 */
function listTemplates($pdo, $user_id, $user_type) {
    // Se for atendente, buscar templates do supervisor
    if ($user_type === 'attendant') {
        $stmt = $pdo->prepare("
            SELECT supervisor_id FROM supervisor_users WHERE id = ?
        ");
        $stmt->execute([$user_id]);
        $attendant = $stmt->fetch();
        
        if (!$attendant) {
            echo json_encode(['success' => false, 'error' => 'Atendente não encontrado']);
            return;
        }
        
        $supervisor_id = $attendant['supervisor_id'];
    } else {
        $supervisor_id = $user_id;
    }
    
    $stmt = $pdo->prepare("
        SELECT * FROM quick_replies
        WHERE supervisor_id = ?
        ORDER BY category, name
    ");
    $stmt->execute([$supervisor_id]);
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'templates' => $templates
    ]);
}

/**
 * Obter template específico
 */
function getTemplate($pdo, $user_id, $user_type) {
    $template_id = $_GET['id'] ?? null;
    
    if (!$template_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID não fornecido']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM quick_replies WHERE id = ?");
    $stmt->execute([$template_id]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$template) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Template não encontrado']);
        return;
    }
    
    // Verificar permissão
    if ($user_type !== 'attendant' && $template['supervisor_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Sem permissão']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'template' => $template
    ]);
}

/**
 * Criar template
 */
function createTemplate($pdo, $user_id) {
    $name = $_POST['name'] ?? '';
    $shortcut = $_POST['shortcut'] ?? '';
    $message = $_POST['message'] ?? '';
    $category = $_POST['category'] ?? 'Geral';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validações
    if (empty($name) || empty($shortcut) || empty($message)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
        return;
    }
    
    // Normalizar atalho
    $shortcut = strtolower(trim($shortcut));
    $shortcut = preg_replace('/[^a-z0-9]/', '', $shortcut);
    
    // Verificar se atalho já existe
    $stmt = $pdo->prepare("
        SELECT id FROM quick_replies 
        WHERE supervisor_id = ? AND shortcut = ?
    ");
    $stmt->execute([$user_id, $shortcut]);
    
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Atalho já existe']);
        return;
    }
    
    // Criar template
    $stmt = $pdo->prepare("
        INSERT INTO quick_replies (supervisor_id, name, shortcut, message, category, is_active)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $success = $stmt->execute([$user_id, $name, $shortcut, $message, $category, $is_active]);
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Template criado com sucesso',
            'id' => $pdo->lastInsertId()
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao criar template']);
    }
}

/**
 * Atualizar template
 */
function updateTemplate($pdo, $user_id) {
    $id = $_POST['id'] ?? null;
    $name = $_POST['name'] ?? '';
    $shortcut = $_POST['shortcut'] ?? '';
    $message = $_POST['message'] ?? '';
    $category = $_POST['category'] ?? 'Geral';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (!$id || empty($name) || empty($shortcut) || empty($message)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
        return;
    }
    
    // Normalizar atalho
    $shortcut = strtolower(trim($shortcut));
    $shortcut = preg_replace('/[^a-z0-9]/', '', $shortcut);
    
    // Verificar se template existe e pertence ao supervisor
    $stmt = $pdo->prepare("SELECT id FROM quick_replies WHERE id = ? AND supervisor_id = ?");
    $stmt->execute([$id, $user_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Template não encontrado']);
        return;
    }
    
    // Verificar se atalho já existe em outro template
    $stmt = $pdo->prepare("
        SELECT id FROM quick_replies 
        WHERE supervisor_id = ? AND shortcut = ? AND id != ?
    ");
    $stmt->execute([$user_id, $shortcut, $id]);
    
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Atalho já existe em outro template']);
        return;
    }
    
    // Atualizar
    $stmt = $pdo->prepare("
        UPDATE quick_replies 
        SET name = ?, shortcut = ?, message = ?, category = ?, is_active = ?
        WHERE id = ? AND supervisor_id = ?
    ");
    
    $success = $stmt->execute([$name, $shortcut, $message, $category, $is_active, $id, $user_id]);
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Template atualizado com sucesso'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao atualizar template']);
    }
}

/**
 * Deletar template
 */
function deleteTemplate($pdo, $user_id) {
    $id = $_POST['id'] ?? $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID não fornecido']);
        return;
    }
    
    $stmt = $pdo->prepare("
        DELETE FROM quick_replies 
        WHERE id = ? AND supervisor_id = ?
    ");
    
    $success = $stmt->execute([$id, $user_id]);
    
    if ($success && $stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Template excluído com sucesso'
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Template não encontrado']);
    }
}

/**
 * Alternar status ativo/inativo
 */
function toggleStatus($pdo, $user_id) {
    $id = $_POST['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID não fornecido']);
        return;
    }
    
    $stmt = $pdo->prepare("
        UPDATE quick_replies 
        SET is_active = NOT is_active
        WHERE id = ? AND supervisor_id = ?
    ");
    
    $success = $stmt->execute([$id, $user_id]);
    
    if ($success && $stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Status atualizado'
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Template não encontrado']);
    }
}

/**
 * Obter estatísticas
 */
function getStats($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
            SUM(usage_count) as total_uses,
            COUNT(DISTINCT category) as categories
        FROM quick_replies
        WHERE supervisor_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
}

/**
 * Obter categorias
 */
function getCategories($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT category
        FROM quick_replies
        WHERE supervisor_id = ?
        ORDER BY category
    ");
    $stmt->execute([$user_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'categories' => $categories
    ]);
}

/**
 * Incrementar contador de uso
 */
function incrementUsage($pdo, $user_id, $user_type) {
    $template_id = $_POST['id'] ?? null;
    
    if (!$template_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID não fornecido']);
        return;
    }
    
    $stmt = $pdo->prepare("
        UPDATE quick_replies 
        SET usage_count = usage_count + 1
        WHERE id = ?
    ");
    
    $stmt->execute([$template_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Uso registrado'
    ]);
}
