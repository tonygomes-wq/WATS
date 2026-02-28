<?php
/**
 * API para Gerenciamento de Storage e Mensagens por Usuário
 * Apenas administradores podem acessar
 * 
 * MACIP Tecnologia LTDA
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar se está logado e é admin
requireLogin();
requireAdmin();

header('Content-Type: application/json');

// Obter ação
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if (!$action) {
    echo json_encode(['success' => false, 'message' => 'Ação não especificada']);
    exit;
}

try {
    switch ($action) {
        case 'update_storage_limit':
            updateStorageLimit($pdo);
            break;
            
        case 'delete_user_messages':
            deleteUserMessages($pdo);
            break;
            
        case 'delete_user_files':
            deleteUserFiles($pdo);
            break;
            
        case 'get_user_details':
            getUserDetails($pdo);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
} catch (Exception $e) {
    error_log("Erro em user_storage_actions.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}

/**
 * Atualizar limite de storage de um usuário
 */
function updateStorageLimit($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $userId = $data['user_id'] ?? null;
    $storageLimit = $data['storage_limit'] ?? null;
    
    if (!$userId || $storageLimit === null) {
        echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
        return;
    }
    
    // Verificar se usuário existe
    $stmt = $pdo->prepare("SELECT id, email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        return;
    }
    
    // Atualizar limite customizado
    $stmt = $pdo->prepare("
        INSERT INTO user_storage_limits (user_id, custom_limit_mb, updated_at, updated_by)
        VALUES (?, ?, NOW(), ?)
        ON DUPLICATE KEY UPDATE 
            custom_limit_mb = VALUES(custom_limit_mb),
            updated_at = NOW(),
            updated_by = VALUES(updated_by)
    ");
    
    $stmt->execute([$userId, $storageLimit, $_SESSION['user_id']]);
    
    echo json_encode([
        'success' => true,
        'message' => "Limite de storage atualizado para {$storageLimit} MB",
        'user_email' => $user['email']
    ]);
}

/**
 * Excluir mensagens de um usuário
 */
function deleteUserMessages($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $userId = $data['user_id'] ?? null;
    $deleteType = $data['delete_type'] ?? 'all'; // 'all', 'old', 'media_only'
    $daysOld = $data['days_old'] ?? 90;
    
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'ID do usuário não especificado']);
        return;
    }
    
    // Verificar se usuário existe
    $stmt = $pdo->prepare("SELECT id, email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        return;
    }
    
    $deletedCount = 0;
    
    // Construir query baseado no tipo de exclusão
    switch ($deleteType) {
        case 'all':
            // Excluir todas as mensagens
            $stmt = $pdo->prepare("DELETE FROM chat_messages WHERE user_id = ?");
            $stmt->execute([$userId]);
            $deletedCount = $stmt->rowCount();
            break;
            
        case 'old':
            // Excluir mensagens antigas
            $stmt = $pdo->prepare("
                DELETE FROM chat_messages 
                WHERE user_id = ? 
                AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$userId, $daysOld]);
            $deletedCount = $stmt->rowCount();
            break;
            
        case 'media_only':
            // Excluir apenas mensagens com mídia
            $stmt = $pdo->prepare("
                DELETE FROM chat_messages 
                WHERE user_id = ? 
                AND media_url IS NOT NULL
            ");
            $stmt->execute([$userId]);
            $deletedCount = $stmt->rowCount();
            break;
    }
    
    // Registrar ação no log
    $stmt = $pdo->prepare("
        INSERT INTO admin_actions_log (
            admin_id, action_type, target_user_id, details, created_at
        ) VALUES (?, 'delete_messages', ?, ?, NOW())
    ");
    
    $details = json_encode([
        'delete_type' => $deleteType,
        'days_old' => $daysOld,
        'deleted_count' => $deletedCount
    ]);
    
    try {
        $stmt->execute([$_SESSION['user_id'], $userId, $details]);
    } catch (Exception $e) {
        // Tabela de log pode não existir, apenas registrar erro
        error_log("Erro ao registrar log de ação: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => "{$deletedCount} mensagens excluídas com sucesso",
        'deleted_count' => $deletedCount,
        'user_email' => $user['email']
    ]);
}

/**
 * Excluir arquivos de mídia de um usuário
 */
function deleteUserFiles($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $userId = $data['user_id'] ?? null;
    $fileType = $data['file_type'] ?? 'all'; // 'all', 'media', 'chat_media'
    
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'ID do usuário não especificado']);
        return;
    }
    
    // Verificar se usuário existe
    $stmt = $pdo->prepare("SELECT id, email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        return;
    }
    
    $userDir = __DIR__ . "/../uploads/user_{$userId}/";
    $deletedFiles = 0;
    $freedSpace = 0;
    
    if (!is_dir($userDir)) {
        echo json_encode([
            'success' => true,
            'message' => 'Diretório do usuário não existe',
            'deleted_files' => 0,
            'freed_space_mb' => 0
        ]);
        return;
    }
    
    // Função para excluir arquivos de um diretório
    $deleteDirectory = function($dir) use (&$deletedFiles, &$freedSpace) {
        if (!is_dir($dir)) return;
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $filePath = $dir . '/' . $file;
            if (is_file($filePath)) {
                $freedSpace += filesize($filePath);
                unlink($filePath);
                $deletedFiles++;
            }
        }
    };
    
    // Excluir baseado no tipo
    switch ($fileType) {
        case 'all':
            $deleteDirectory($userDir . 'media');
            $deleteDirectory($userDir . 'chat_media');
            break;
            
        case 'media':
            $deleteDirectory($userDir . 'media');
            break;
            
        case 'chat_media':
            $deleteDirectory($userDir . 'chat_media');
            break;
    }
    
    $freedSpaceMB = round($freedSpace / 1024 / 1024, 2);
    
    // Atualizar URLs de mídia no banco para NULL
    if ($fileType === 'all' || $fileType === 'chat_media') {
        $stmt = $pdo->prepare("
            UPDATE chat_messages 
            SET media_url = NULL 
            WHERE user_id = ? AND media_url IS NOT NULL
        ");
        $stmt->execute([$userId]);
    }
    
    // Registrar ação no log
    $stmt = $pdo->prepare("
        INSERT INTO admin_actions_log (
            admin_id, action_type, target_user_id, details, created_at
        ) VALUES (?, 'delete_files', ?, ?, NOW())
    ");
    
    $details = json_encode([
        'file_type' => $fileType,
        'deleted_files' => $deletedFiles,
        'freed_space_mb' => $freedSpaceMB
    ]);
    
    try {
        $stmt->execute([$_SESSION['user_id'], $userId, $details]);
    } catch (Exception $e) {
        error_log("Erro ao registrar log de ação: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => "{$deletedFiles} arquivos excluídos, {$freedSpaceMB} MB liberados",
        'deleted_files' => $deletedFiles,
        'freed_space_mb' => $freedSpaceMB,
        'user_email' => $user['email']
    ]);
}

/**
 * Obter detalhes de um usuário
 */
function getUserDetails($pdo) {
    $userId = $_GET['user_id'] ?? null;
    
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'ID do usuário não especificado']);
        return;
    }
    
    // Buscar informações do usuário
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.email,
            u.name,
            u.plan,
            u.created_at,
            COUNT(DISTINCT cm.id) as total_messages,
            COUNT(DISTINCT CASE WHEN cm.media_url IS NOT NULL THEN cm.id END) as messages_with_media
        FROM users u
        LEFT JOIN chat_messages cm ON cm.user_id = u.id
        WHERE u.id = ?
        GROUP BY u.id
    ");
    
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        return;
    }
    
    // Buscar limite customizado se existir
    $stmt = $pdo->prepare("SELECT custom_limit_mb FROM user_storage_limits WHERE user_id = ?");
    $stmt->execute([$userId]);
    $customLimit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $user['custom_storage_limit'] = $customLimit ? $customLimit['custom_limit_mb'] : null;
    
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
}
