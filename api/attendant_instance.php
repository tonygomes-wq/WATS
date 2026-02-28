<?php
/**
 * API de Gerenciamento de Instâncias de Atendentes
 * Permite criar, conectar, desconectar e monitorar instâncias WhatsApp
 */

session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Verificar autenticação
if (!isset($_SESSION['user_id']) && !isset($_SESSION['attendant_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Determinar tipo de usuário e IDs
// Atendentes podem estar logados via user_id com user_type = 'attendant'
$userType = $_SESSION['user_type'] ?? 'user';
$userId = $_SESSION['user_id'] ?? null;
$attendantId = $_SESSION['attendant_id'] ?? null;

// Se é atendente logado via user_id, usar user_id como attendant_id
if ($userType === 'attendant' && $userId && !$attendantId) {
    $attendantId = $userId;
}

$isSupervisor = ($userType === 'supervisor' || $userType === 'admin') && $userId;
$isAttendant = ($userType === 'attendant') || isset($_SESSION['attendant_id']);

// Carregar configuração da Evolution API
$evolutionApiUrl = defined('EVOLUTION_API_URL') ? EVOLUTION_API_URL : '';
$evolutionApiKey = defined('EVOLUTION_API_KEY') ? EVOLUTION_API_KEY : '';

// Debug log
error_log("ATTENDANT_INSTANCE_API: action=$action, userType=$userType, userId=$userId, attendantId=$attendantId, isSupervisor=" . ($isSupervisor ? 'true' : 'false') . ", isAttendant=" . ($isAttendant ? 'true' : 'false'));

try {
    switch ($action) {
        // ==========================================
        // AÇÕES DO SUPERVISOR
        // ==========================================
        
        case 'create_instance':
            requireSupervisor();
            createAttendantInstance($pdo, $userId);
            break;
            
        case 'delete_instance':
            requireSupervisor();
            deleteAttendantInstance($pdo, $userId);
            break;
            
        case 'disconnect_attendant':
            requireSupervisor();
            disconnectAttendantInstance($pdo, $userId);
            break;
            
        case 'list_instances':
            requireSupervisor();
            listAttendantInstances($pdo, $userId);
            break;
            
        case 'get_attendant_stats':
            requireSupervisor();
            getAttendantStats($pdo, $userId);
            break;
            
        // ==========================================
        // AÇÕES DO ATENDENTE
        // ==========================================
        
        case 'get_my_instance':
            requireAttendant();
            getMyInstance($pdo, $attendantId);
            break;
            
        case 'create_and_qr':
            // Atendente cria instância com nome personalizado e gera QR
            if ($isAttendant) {
                $instanceName = $_POST['instance_name'] ?? '';
                createAndGenerateQR($pdo, $attendantId, $instanceName);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Apenas atendentes podem usar esta ação']);
            }
            break;
            
        case 'generate_qr':
            // Atendente pode gerar QR da própria instância
            if ($isAttendant) {
                generateQRCode($pdo, $attendantId, 'attendant');
            } else {
                requireSupervisor();
                $targetAttendantId = (int)($_POST['attendant_id'] ?? 0);
                generateQRCode($pdo, $targetAttendantId, 'supervisor', $userId);
            }
            break;
            
        case 'check_connection':
            if ($isAttendant) {
                checkConnection($pdo, $attendantId);
            } else {
                requireSupervisor();
                $targetAttendantId = (int)($_GET['attendant_id'] ?? 0);
                checkConnectionForSupervisor($pdo, $targetAttendantId, $userId);
            }
            break;
            
        case 'get_connection_logs':
            if ($isAttendant) {
                getConnectionLogs($pdo, $attendantId);
            } else {
                requireSupervisor();
                $targetAttendantId = (int)($_GET['attendant_id'] ?? 0);
                getConnectionLogsForSupervisor($pdo, $targetAttendantId, $userId);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ação inválida']);
    }
} catch (Exception $e) {
    error_log("ATTENDANT_INSTANCE_API ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// Capturar erros fatais
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("ATTENDANT_INSTANCE_API FATAL: " . $error['message'] . " in " . $error['file'] . ":" . $error['line']);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Erro interno: ' . $error['message']
        ]);
    }
});

// ==========================================
// FUNÇÕES AUXILIARES
// ==========================================

function requireSupervisor() {
    global $userId, $pdo;
    
    if (!$userId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso negado']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT user_type FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !in_array($user['user_type'], ['supervisor', 'admin'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Apenas supervisores podem realizar esta ação']);
        exit;
    }
}

function requireAttendant() {
    global $attendantId, $isAttendant;
    
    if (!$attendantId || !$isAttendant) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso negado - apenas atendentes']);
        exit;
    }
}

function verifyAttendantBelongsToSupervisor($pdo, $attendantId, $supervisorId) {
    $stmt = $pdo->prepare("SELECT id FROM supervisor_users WHERE id = ? AND supervisor_id = ?");
    $stmt->execute([$attendantId, $supervisorId]);
    return $stmt->fetch() !== false;
}

// ==========================================
// FUNÇÕES DO SUPERVISOR
// ==========================================

function createAttendantInstance($pdo, $supervisorId) {
    global $evolutionApiUrl, $evolutionApiKey;
    
    $attendantId = (int)($_POST['attendant_id'] ?? 0);
    
    if (!$attendantId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID do atendente é obrigatório']);
        return;
    }
    
    // Verificar se atendente pertence ao supervisor
    if (!verifyAttendantBelongsToSupervisor($pdo, $attendantId, $supervisorId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Atendente não encontrado']);
        return;
    }
    
    // Verificar se já existe instância
    $stmt = $pdo->prepare("SELECT id FROM attendant_instances WHERE attendant_id = ?");
    $stmt->execute([$attendantId]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Atendente já possui uma instância configurada']);
        return;
    }
    
    // Buscar dados do atendente
    $stmt = $pdo->prepare("SELECT name, email FROM supervisor_users WHERE id = ?");
    $stmt->execute([$attendantId]);
    $attendant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Gerar nome único para a instância
    $instanceName = 'att_' . $attendantId . '_' . substr(md5($attendant['email']), 0, 8);
    
    // Criar instância na Evolution API
    $evolutionResult = createEvolutionInstance($instanceName);
    
    if (!$evolutionResult['success']) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao criar instância: ' . $evolutionResult['error']]);
        return;
    }
    
    $pdo->beginTransaction();
    
    try {
        // Inserir registro da instância (incluindo URL da Evolution API)
        $stmt = $pdo->prepare("
            INSERT INTO attendant_instances 
            (attendant_id, supervisor_id, instance_name, instance_token, status, evolution_api_url)
            VALUES (?, ?, ?, ?, 'disconnected', ?)
        ");
        $stmt->execute([
            $attendantId,
            $supervisorId,
            $instanceName,
            $evolutionResult['token'] ?? null,
            EVOLUTION_API_URL
        ]);
        
        // Atualizar atendente para usar instância própria
        $stmt = $pdo->prepare("
            UPDATE supervisor_users 
            SET use_own_instance = 1, instance_config_allowed = 1 
            WHERE id = ?
        ");
        $stmt->execute([$attendantId]);
        
        // Log
        logInstanceAction($pdo, $attendantId, 'connect', 'supervisor', $supervisorId, [
            'instance_name' => $instanceName
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Instância criada com sucesso',
            'instance_name' => $instanceName
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function deleteAttendantInstance($pdo, $supervisorId) {
    global $evolutionApiUrl, $evolutionApiKey;
    
    $attendantId = (int)($_POST['attendant_id'] ?? 0);
    
    if (!verifyAttendantBelongsToSupervisor($pdo, $attendantId, $supervisorId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Atendente não encontrado']);
        return;
    }
    
    // Buscar instância
    $stmt = $pdo->prepare("SELECT instance_name FROM attendant_instances WHERE attendant_id = ?");
    $stmt->execute([$attendantId]);
    $instance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$instance) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Instância não encontrada']);
        return;
    }
    
    // Deletar instância na Evolution API
    deleteEvolutionInstance($instance['instance_name']);
    
    $pdo->beginTransaction();
    
    try {
        // Remover registro
        $stmt = $pdo->prepare("DELETE FROM attendant_instances WHERE attendant_id = ?");
        $stmt->execute([$attendantId]);
        
        // Atualizar atendente
        $stmt = $pdo->prepare("
            UPDATE supervisor_users 
            SET use_own_instance = 0, instance_config_allowed = 0 
            WHERE id = ?
        ");
        $stmt->execute([$attendantId]);
        
        // Log
        logInstanceAction($pdo, $attendantId, 'disconnect', 'supervisor', $supervisorId, [
            'instance_name' => $instance['instance_name'],
            'action' => 'deleted'
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Instância removida com sucesso'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function disconnectAttendantInstance($pdo, $supervisorId) {
    global $evolutionApiUrl, $evolutionApiKey;
    
    $attendantId = (int)($_POST['attendant_id'] ?? 0);
    
    if (!verifyAttendantBelongsToSupervisor($pdo, $attendantId, $supervisorId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Atendente não encontrado']);
        return;
    }
    
    // Buscar instância
    $stmt = $pdo->prepare("SELECT instance_name FROM attendant_instances WHERE attendant_id = ?");
    $stmt->execute([$attendantId]);
    $instance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$instance) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Instância não encontrada']);
        return;
    }
    
    // Desconectar na Evolution API
    $result = logoutEvolutionInstance($instance['instance_name']);
    
    // Atualizar status
    $stmt = $pdo->prepare("
        UPDATE attendant_instances 
        SET status = 'disconnected', 
            phone_number = NULL, 
            phone_name = NULL,
            disconnected_at = NOW(),
            qr_code = NULL
        WHERE attendant_id = ?
    ");
    $stmt->execute([$attendantId]);
    
    // Log
    logInstanceAction($pdo, $attendantId, 'disconnect', 'supervisor', $supervisorId, [
        'instance_name' => $instance['instance_name']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Instância desconectada com sucesso'
    ]);
}

function listAttendantInstances($pdo, $supervisorId) {
    $stmt = $pdo->prepare("
        SELECT 
            su.id as attendant_id,
            su.name as attendant_name,
            su.email,
            su.status as attendant_status,
            su.use_own_instance,
            ai.id as instance_id,
            ai.instance_name,
            ai.status as instance_status,
            ai.phone_number,
            ai.phone_name,
            ai.connected_at,
            ai.last_activity,
            (SELECT COUNT(*) FROM chat_conversations WHERE assigned_to = su.id AND status IN ('open', 'in_progress')) as active_chats
        FROM supervisor_users su
        LEFT JOIN attendant_instances ai ON su.id = ai.attendant_id
        WHERE su.supervisor_id = ?
        ORDER BY su.name ASC
    ");
    $stmt->execute([$supervisorId]);
    $instances = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'instances' => $instances,
        'total' => count($instances)
    ]);
}

function getAttendantStats($pdo, $supervisorId) {
    $attendantId = (int)($_GET['attendant_id'] ?? 0);
    $period = $_GET['period'] ?? '7days';
    
    if (!verifyAttendantBelongsToSupervisor($pdo, $attendantId, $supervisorId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Atendente não encontrado']);
        return;
    }
    
    // Determinar período
    switch ($period) {
        case 'today':
            $dateFilter = "DATE(date) = CURDATE()";
            break;
        case '7days':
            $dateFilter = "date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case '30days':
            $dateFilter = "date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
        default:
            $dateFilter = "date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            SUM(messages_sent) as total_sent,
            SUM(messages_received) as total_received,
            SUM(conversations_started) as total_conversations,
            SUM(conversations_closed) as total_closed,
            AVG(avg_response_time_seconds) as avg_response_time,
            SUM(total_online_minutes) as total_online
        FROM attendant_instance_stats
        WHERE attendant_id = ? AND $dateFilter
    ");
    $stmt->execute([$attendantId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Dados diários para gráfico
    $stmt = $pdo->prepare("
        SELECT 
            date,
            messages_sent,
            messages_received,
            conversations_started,
            conversations_closed
        FROM attendant_instance_stats
        WHERE attendant_id = ? AND $dateFilter
        ORDER BY date ASC
    ");
    $stmt->execute([$attendantId]);
    $daily = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'summary' => $stats,
        'daily' => $daily
    ]);
}

// ==========================================
// FUNÇÕES DO ATENDENTE
// ==========================================

function getMyInstance($pdo, $attendantId) {
    $stmt = $pdo->prepare("
        SELECT 
            ai.*,
            su.use_own_instance,
            su.instance_config_allowed
        FROM supervisor_users su
        LEFT JOIN attendant_instances ai ON su.id = ai.attendant_id
        WHERE su.id = ?
    ");
    $stmt->execute([$attendantId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$data) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Dados não encontrados']);
        return;
    }
    
    // Não retornar QR code expirado
    if ($data['qr_code'] && $data['qr_code_expires_at']) {
        if (strtotime($data['qr_code_expires_at']) < time()) {
            $data['qr_code'] = null;
        }
    }
    
    echo json_encode([
        'success' => true,
        'instance' => $data
    ]);
}

function createAndGenerateQR($pdo, $attendantId, $instanceName) {
    global $evolutionApiUrl, $evolutionApiKey;
    
    error_log("ATTENDANT_INSTANCE: createAndGenerateQR iniciado para attendantId=$attendantId, instanceName=$instanceName");
    
    // Validar nome da instância
    if (empty($instanceName)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nome da instância é obrigatório']);
        return;
    }
    
    if (!preg_match('/^[a-zA-Z0-9-_]+$/', $instanceName)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nome da instância deve conter apenas letras, números, hífen e underscore']);
        return;
    }
    
    // Verificar se atendente tem permissão
    $stmt = $pdo->prepare("SELECT name, email, supervisor_id, use_own_instance FROM supervisor_users WHERE id = ?");
    $stmt->execute([$attendantId]);
    $attendant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$attendant || !$attendant['use_own_instance']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Atendente não está configurado para usar instância própria']);
        return;
    }
    
    // Verificar se tabela attendant_instances existe
    try {
        $checkTable = $pdo->query("SHOW TABLES LIKE 'attendant_instances'");
        if ($checkTable->rowCount() == 0) {
            $pdo->exec("
                CREATE TABLE attendant_instances (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    attendant_id INT NOT NULL,
                    supervisor_id INT NOT NULL,
                    instance_name VARCHAR(100) NOT NULL,
                    instance_token VARCHAR(255),
                    status ENUM('disconnected', 'connecting', 'connected', 'error') DEFAULT 'disconnected',
                    qr_code LONGTEXT,
                    qr_code_expires_at DATETIME,
                    phone_number VARCHAR(20),
                    phone_name VARCHAR(100),
                    connected_at DATETIME,
                    last_activity DATETIME,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_attendant (attendant_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
    } catch (Exception $e) {
        error_log("Erro ao criar tabela: " . $e->getMessage());
    }
    
    // Verificar se já existe instância
    $stmt = $pdo->prepare("SELECT instance_name FROM attendant_instances WHERE attendant_id = ?");
    $stmt->execute([$attendantId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Você já possui uma instância configurada: ' . $existing['instance_name']]);
        return;
    }
    
    // Criar instância na Evolution API
    error_log("ATTENDANT_INSTANCE: Criando instância na Evolution API: $instanceName");
    $evolutionResult = createEvolutionInstance($instanceName);
    
    if (!$evolutionResult['success']) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao criar instância: ' . ($evolutionResult['error'] ?? 'Erro desconhecido')]);
        return;
    }
    
    // Aguardar criação
    sleep(2);
    
    // Inserir registro no banco
    try {
        $stmt = $pdo->prepare("
            INSERT INTO attendant_instances 
            (attendant_id, supervisor_id, instance_name, instance_token, status, evolution_api_url)
            VALUES (?, ?, ?, ?, 'disconnected', ?)
        ");
        $stmt->execute([
            $attendantId,
            $attendant['supervisor_id'],
            $instanceName,
            $evolutionResult['token'] ?? null,
            EVOLUTION_API_URL
        ]);
        error_log("ATTENDANT_INSTANCE: Registro inserido no banco");
    } catch (Exception $e) {
        error_log("ATTENDANT_INSTANCE: Erro ao inserir no banco: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao salvar instância no banco']);
        return;
    }
    
    // Gerar QR Code
    error_log("ATTENDANT_INSTANCE: Gerando QR Code para: $instanceName");
    $qrResult = getEvolutionQRCode($instanceName);
    
    if (!$qrResult['success']) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Instância criada, mas erro ao gerar QR Code: ' . ($qrResult['error'] ?? 'Erro desconhecido')]);
        return;
    }
    
    // Salvar QR Code
    $expiresAt = date('Y-m-d H:i:s', time() + 60);
    $stmt = $pdo->prepare("
        UPDATE attendant_instances 
        SET qr_code = ?, qr_code_expires_at = ?, status = 'connecting'
        WHERE attendant_id = ?
    ");
    $stmt->execute([$qrResult['qr_code'], $expiresAt, $attendantId]);
    
    // Log
    logInstanceAction($pdo, $attendantId, 'instance_created', 'attendant', $attendantId, [
        'instance_name' => $instanceName
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Instância criada com sucesso!',
        'instance_name' => $instanceName,
        'qr_code' => $qrResult['qr_code'],
        'base64' => str_replace('data:image/png;base64,', '', $qrResult['qr_code']),
        'expires_at' => $expiresAt
    ]);
}

function generateQRCode($pdo, $attendantId, $performerType, $performerId = null) {
    global $evolutionApiUrl, $evolutionApiKey;
    
    error_log("ATTENDANT_INSTANCE: generateQRCode iniciado para attendantId=$attendantId");
    error_log("ATTENDANT_INSTANCE: evolutionApiUrl=$evolutionApiUrl");
    error_log("ATTENDANT_INSTANCE: evolutionApiKey=" . (empty($evolutionApiKey) ? 'VAZIO' : 'CONFIGURADO'));
    
    // Verificar se tabela attendant_instances existe
    try {
        $checkTable = $pdo->query("SHOW TABLES LIKE 'attendant_instances'");
        if ($checkTable->rowCount() == 0) {
            // Criar tabela se não existir
            $pdo->exec("
                CREATE TABLE attendant_instances (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    attendant_id INT NOT NULL,
                    supervisor_id INT NOT NULL,
                    instance_name VARCHAR(100) NOT NULL,
                    instance_token VARCHAR(255),
                    status ENUM('disconnected', 'connecting', 'connected', 'error') DEFAULT 'disconnected',
                    qr_code LONGTEXT,
                    qr_code_expires_at DATETIME,
                    phone_number VARCHAR(20),
                    phone_name VARCHAR(100),
                    connected_at DATETIME,
                    last_activity DATETIME,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_attendant (attendant_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
    } catch (Exception $e) {
        error_log("Erro ao verificar/criar tabela attendant_instances: " . $e->getMessage());
    }
    
    // Buscar instância
    $instance = null;
    try {
        $stmt = $pdo->prepare("SELECT instance_name, status FROM attendant_instances WHERE attendant_id = ?");
        $stmt->execute([$attendantId]);
        $instance = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erro ao buscar instância: " . $e->getMessage());
    }
    
    // Se não existe instância, criar automaticamente
    if (!$instance) {
        error_log("ATTENDANT_INSTANCE: Instância não existe, criando nova...");
        
        // Buscar dados do atendente
        $stmt = $pdo->prepare("SELECT name, email, supervisor_id, use_own_instance FROM supervisor_users WHERE id = ?");
        $stmt->execute([$attendantId]);
        $attendant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("ATTENDANT_INSTANCE: Dados do atendente: " . json_encode($attendant));
        
        if (!$attendant || !$attendant['use_own_instance']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Atendente não está configurado para usar instância própria']);
            return;
        }
        
        // Gerar nome único para a instância
        $instanceName = 'att_' . $attendantId . '_' . substr(md5($attendant['email']), 0, 8);
        error_log("ATTENDANT_INSTANCE: Nome da instância gerado: $instanceName");
        
        // Criar instância na Evolution API
        $evolutionResult = createEvolutionInstance($instanceName);
        error_log("ATTENDANT_INSTANCE: Resultado da criação: " . json_encode($evolutionResult));
        
        if (!$evolutionResult['success']) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erro ao criar instância: ' . ($evolutionResult['error'] ?? 'Erro desconhecido')]);
            return;
        }
        
        // Aguardar criação da instância
        sleep(2);
        
        // Inserir registro da instância no banco
        try {
            $stmt = $pdo->prepare("
                INSERT INTO attendant_instances 
                (attendant_id, supervisor_id, instance_name, instance_token, status, evolution_api_url)
                VALUES (?, ?, ?, ?, 'disconnected', ?)
            ");
            $stmt->execute([
                $attendantId,
                $attendant['supervisor_id'],
                $instanceName,
                $evolutionResult['token'] ?? null,
                EVOLUTION_API_URL
            ]);
            error_log("ATTENDANT_INSTANCE: Registro inserido no banco com sucesso");
        } catch (Exception $e) {
            error_log("ATTENDANT_INSTANCE: Erro ao inserir no banco: " . $e->getMessage());
        }
        
        $instance = ['instance_name' => $instanceName, 'status' => 'disconnected'];
        
        // Log
        logInstanceAction($pdo, $attendantId, 'instance_created', $performerType, $performerId ?? $attendantId, [
            'instance_name' => $instanceName
        ]);
    }
    
    if ($instance['status'] === 'connected') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Instância já está conectada']);
        return;
    }
    
    $instanceName = $instance['instance_name'];
    
    // Verificar se a instância existe na Evolution API (igual ao supervisor)
    error_log("ATTENDANT_INSTANCE: Verificando se instância existe na Evolution API: $instanceName");
    $statusResponse = getEvolutionInstanceStatus($instanceName);
    
    if (!$statusResponse['success']) {
        // Instância não existe na Evolution API, criar nova
        error_log("ATTENDANT_INSTANCE: Instância não existe na Evolution API, criando: $instanceName");
        
        $createResult = createEvolutionInstance($instanceName);
        
        if (!$createResult['success']) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erro ao criar instância na Evolution API: ' . ($createResult['error'] ?? 'Erro desconhecido')]);
            return;
        }
        
        // Aguardar criação
        sleep(3);
    }
    
    // Gerar QR Code via Evolution API
    error_log("ATTENDANT_INSTANCE: Gerando QR Code para: $instanceName");
    $qrResult = getEvolutionQRCode($instanceName);
    
    if (!$qrResult['success']) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao gerar QR Code: ' . $qrResult['error']]);
        return;
    }
    
    // Salvar QR Code
    $expiresAt = date('Y-m-d H:i:s', time() + 60); // 60 segundos
    
    $stmt = $pdo->prepare("
        UPDATE attendant_instances 
        SET qr_code = ?, 
            qr_code_expires_at = ?,
            status = 'connecting'
        WHERE attendant_id = ?
    ");
    $stmt->execute([$qrResult['qr_code'], $expiresAt, $attendantId]);
    
    // Log
    logInstanceAction($pdo, $attendantId, 'qr_generated', $performerType, $performerId ?? $attendantId);
    
    echo json_encode([
        'success' => true,
        'qr_code' => $qrResult['qr_code'],
        'expires_at' => $expiresAt
    ]);
}

function checkConnection($pdo, $attendantId) {
    global $evolutionApiUrl, $evolutionApiKey;
    
    // Buscar instância
    $stmt = $pdo->prepare("SELECT instance_name, status FROM attendant_instances WHERE attendant_id = ?");
    $stmt->execute([$attendantId]);
    $instance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$instance) {
        echo json_encode([
            'success' => true,
            'has_instance' => false,
            'status' => 'no_instance'
        ]);
        return;
    }
    
    // Verificar status na Evolution API
    $statusResult = getEvolutionInstanceStatus($instance['instance_name']);
    
    $newStatus = 'disconnected';
    $phoneNumber = null;
    $phoneName = null;
    
    if ($statusResult['success'] && $statusResult['connected']) {
        $newStatus = 'connected';
        $phoneNumber = $statusResult['phone_number'] ?? null;
        $phoneName = $statusResult['phone_name'] ?? null;
        
        // Atualizar se mudou para conectado
        if ($instance['status'] !== 'connected') {
            $stmt = $pdo->prepare("
                UPDATE attendant_instances 
                SET status = 'connected',
                    phone_number = ?,
                    phone_name = ?,
                    connected_at = NOW(),
                    qr_code = NULL,
                    last_activity = NOW()
                WHERE attendant_id = ?
            ");
            $stmt->execute([$phoneNumber, $phoneName, $attendantId]);
            
            logInstanceAction($pdo, $attendantId, 'qr_scanned', 'attendant', $attendantId, [
                'phone_number' => $phoneNumber
            ]);
        }
    } else if ($instance['status'] === 'connected') {
        // Estava conectado mas desconectou
        $stmt = $pdo->prepare("
            UPDATE attendant_instances 
            SET status = 'disconnected',
                disconnected_at = NOW()
            WHERE attendant_id = ?
        ");
        $stmt->execute([$attendantId]);
        
        logInstanceAction($pdo, $attendantId, 'disconnect', 'system', null, [
            'reason' => 'connection_lost'
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'has_instance' => true,
        'status' => $newStatus,
        'phone_number' => $phoneNumber,
        'phone_name' => $phoneName
    ]);
}

function checkConnectionForSupervisor($pdo, $attendantId, $supervisorId) {
    if (!verifyAttendantBelongsToSupervisor($pdo, $attendantId, $supervisorId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Atendente não encontrado']);
        return;
    }
    
    checkConnection($pdo, $attendantId);
}

function getConnectionLogs($pdo, $attendantId) {
    $limit = (int)($_GET['limit'] ?? 50);
    
    $stmt = $pdo->prepare("
        SELECT 
            icl.*,
            CASE 
                WHEN icl.performed_by_type = 'supervisor' THEN u.name
                WHEN icl.performed_by_type = 'attendant' THEN su.name
                ELSE 'Sistema'
            END as performed_by_name
        FROM instance_connection_logs icl
        LEFT JOIN users u ON icl.performed_by_type = 'supervisor' AND icl.performed_by_id = u.id
        LEFT JOIN supervisor_users su ON icl.performed_by_type = 'attendant' AND icl.performed_by_id = su.id
        WHERE icl.attendant_id = ?
        ORDER BY icl.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$attendantId, $limit]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'logs' => $logs
    ]);
}

function getConnectionLogsForSupervisor($pdo, $attendantId, $supervisorId) {
    if (!verifyAttendantBelongsToSupervisor($pdo, $attendantId, $supervisorId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Atendente não encontrado']);
        return;
    }
    
    getConnectionLogs($pdo, $attendantId);
}

function logInstanceAction($pdo, $attendantId, $action, $performerType, $performerId, $details = null) {
    try {
        // Verificar se tabela existe
        $checkTable = $pdo->query("SHOW TABLES LIKE 'instance_connection_logs'");
        if ($checkTable->rowCount() == 0) {
            // Criar tabela se não existir
            $pdo->exec("
                CREATE TABLE instance_connection_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    attendant_id INT NOT NULL,
                    action VARCHAR(50) NOT NULL,
                    performed_by_type ENUM('supervisor', 'attendant', 'system') NOT NULL,
                    performed_by_id INT,
                    details JSON,
                    ip_address VARCHAR(45),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_attendant (attendant_id),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO instance_connection_logs 
            (attendant_id, action, performed_by_type, performed_by_id, details, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $attendantId,
            $action,
            $performerType,
            $performerId,
            $details ? json_encode($details) : null,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        // Log silencioso - não interromper o fluxo principal
        error_log("Erro ao registrar log de instância: " . $e->getMessage());
    }
}

// ==========================================
// FUNÇÕES DA EVOLUTION API
// ==========================================

function createEvolutionInstance($instanceName) {
    global $evolutionApiUrl, $evolutionApiKey;
    
    error_log("ATTENDANT_INSTANCE: createEvolutionInstance chamado para: $instanceName");
    error_log("ATTENDANT_INSTANCE: URL: $evolutionApiUrl");
    
    if (empty($evolutionApiUrl) || empty($evolutionApiKey)) {
        error_log("ATTENDANT_INSTANCE: Evolution API não configurada!");
        return ['success' => false, 'error' => 'Evolution API não configurada'];
    }
    
    $data = [
        'instanceName' => $instanceName,
        'qrcode' => true,
        'integration' => 'WHATSAPP-BAILEYS'
    ];
    
    $url = $evolutionApiUrl . '/instance/create';
    error_log("ATTENDANT_INSTANCE: Chamando URL: $url");
    error_log("ATTENDANT_INSTANCE: Dados: " . json_encode($data));
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . $evolutionApiKey
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    error_log("ATTENDANT_INSTANCE: HTTP Code: $httpCode");
    error_log("ATTENDANT_INSTANCE: Response: " . substr($response, 0, 500));
    if ($curlError) {
        error_log("ATTENDANT_INSTANCE: cURL Error: $curlError");
    }
    
    if ($httpCode >= 200 && $httpCode < 300) {
        $result = json_decode($response, true);
        return [
            'success' => true,
            'token' => $result['hash'] ?? null
        ];
    }
    
    $errorMsg = 'HTTP ' . $httpCode;
    if ($response) {
        $errorData = json_decode($response, true);
        if (isset($errorData['message'])) {
            $errorMsg .= ' - ' . (is_array($errorData['message']) ? implode(', ', $errorData['message']) : $errorData['message']);
        }
    }
    
    return ['success' => false, 'error' => $errorMsg];
}

function deleteEvolutionInstance($instanceName) {
    global $evolutionApiUrl, $evolutionApiKey;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $evolutionApiUrl . '/instance/delete/' . $instanceName);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $evolutionApiKey
    ]);
    
    curl_exec($ch);
    curl_close($ch);
    
    return true;
}

function logoutEvolutionInstance($instanceName) {
    global $evolutionApiUrl, $evolutionApiKey;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $evolutionApiUrl . '/instance/logout/' . $instanceName);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $evolutionApiKey
    ]);
    
    curl_exec($ch);
    curl_close($ch);
    
    return true;
}

function getEvolutionQRCode($instanceName) {
    global $evolutionApiUrl, $evolutionApiKey;
    
    error_log("ATTENDANT_INSTANCE: getEvolutionQRCode para: $instanceName");
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $evolutionApiUrl . '/instance/connect/' . $instanceName);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . $evolutionApiKey
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    error_log("ATTENDANT_INSTANCE: QR Response HTTP: $httpCode");
    error_log("ATTENDANT_INSTANCE: QR Response: " . substr($response, 0, 500));
    
    if ($curlError) {
        error_log("ATTENDANT_INSTANCE: cURL Error: $curlError");
        return ['success' => false, 'error' => 'Erro de conexão: ' . $curlError];
    }
    
    if ($httpCode >= 200 && $httpCode < 300) {
        $result = json_decode($response, true);
        
        // Extrair QR Code usando a mesma lógica do supervisor
        $qrCode = extractQRCodeForAttendant($result);
        
        if ($qrCode) {
            return [
                'success' => true,
                'qr_code' => 'data:image/png;base64,' . $qrCode
            ];
        }
        
        return ['success' => false, 'error' => 'QR Code não encontrado na resposta'];
    }
    
    return ['success' => false, 'error' => 'HTTP ' . $httpCode];
}

/**
 * Extrair QR Code de diferentes formatos de resposta (igual ao supervisor)
 */
function extractQRCodeForAttendant($data) {
    error_log("ATTENDANT_INSTANCE: extractQRCode - Dados: " . json_encode($data));
    
    // O campo 'code' contém os dados do QR Code que precisam ser convertidos em imagem
    if (!empty($data['code']) && is_string($data['code'])) {
        $qrData = $data['code'];
        error_log("ATTENDANT_INSTANCE: Campo 'code' encontrado: " . substr($qrData, 0, 100) . "...");
        
        // Usar APIs externas para gerar a imagem do QR Code (igual ao supervisor)
        $qrApis = [
            'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qrData),
            'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . urlencode($qrData),
            'https://quickchart.io/qr?text=' . urlencode($qrData) . '&size=300'
        ];
        
        foreach ($qrApis as $api) {
            error_log("ATTENDANT_INSTANCE: Tentando API: $api");
            $qrImageData = @file_get_contents($api);
            if ($qrImageData !== false && strlen($qrImageData) > 1000) {
                error_log("ATTENDANT_INSTANCE: QR Code gerado com sucesso via: $api");
                return base64_encode($qrImageData);
            }
        }
        
        error_log("ATTENDANT_INSTANCE: Todas as APIs de QR falharam");
    }
    
    // Tentar outros formatos como fallback
    $possibleLocations = [
        'qrcode.base64' => $data['qrcode']['base64'] ?? null,
        'base64' => $data['base64'] ?? null,
        'qr' => $data['qr'] ?? null,
        'qrcode' => $data['qrcode'] ?? null,
        'qrCode' => $data['qrCode'] ?? null,
        'qr_code' => $data['qr_code'] ?? null
    ];
    
    foreach ($possibleLocations as $location => $qrCode) {
        if (!empty($qrCode) && is_string($qrCode) && strlen($qrCode) > 100) {
            if (preg_match('/^[A-Za-z0-9+\/]+=*$/', $qrCode)) {
                error_log("ATTENDANT_INSTANCE: QR Code encontrado em: $location");
                return $qrCode;
            }
        }
    }
    
    error_log("ATTENDANT_INSTANCE: Nenhum QR Code encontrado");
    return null;
}

function getEvolutionInstanceStatus($instanceName) {
    global $evolutionApiUrl, $evolutionApiKey;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $evolutionApiUrl . '/instance/connectionState/' . $instanceName);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $evolutionApiKey
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        $result = json_decode($response, true);
        $state = $result['state'] ?? $result['instance']['state'] ?? 'close';
        
        return [
            'success' => true,
            'connected' => ($state === 'open'),
            'state' => $state,
            'phone_number' => $result['instance']['owner'] ?? null,
            'phone_name' => $result['instance']['profileName'] ?? null
        ];
    }
    
    return ['success' => false, 'error' => 'HTTP ' . $httpCode];
}
