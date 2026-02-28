<?php
/**
 * API de Notificações por Email
 */

// Capturar erros fatais
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Handler para erros fatais
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success' => false, 
            'error' => 'Erro interno: ' . $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line']
        ]);
    }
});

session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Carregar EmailSender apenas quando necessário (lazy loading)
$emailSenderLoaded = false;
function loadEmailSender() {
    global $emailSenderLoaded;
    if (!$emailSenderLoaded) {
        require_once '../includes/email_sender.php';
        $emailSenderLoaded = true;
    }
}

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

// Verificar se é Admin
require_once '../includes/functions.php';
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_settings':
            getSettings($pdo, $user_id);
            break;
            
        case 'save_settings':
            saveSettings($pdo, $user_id);
            break;
            
        case 'get_preferences':
            getPreferences($pdo, $user_id);
            break;
            
        case 'save_preferences':
            savePreferences($pdo, $user_id);
            break;
            
        case 'list_templates':
            listTemplates($pdo, $user_id);
            break;
            
        case 'get_template':
            getTemplate($pdo, $user_id);
            break;
            
        case 'save_template':
            saveTemplate($pdo, $user_id);
            break;
            
        case 'delete_template':
            deleteTemplate($pdo, $user_id);
            break;
            
        case 'test_email':
            testEmail($pdo, $user_id);
            break;
            
        case 'get_logs':
            getLogs($pdo, $user_id);
            break;
            
        case 'get_stats':
            getStats($pdo, $user_id);
            break;
            
        case 'disconnect_oauth':
            disconnectOAuth($pdo, $user_id);
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
 * Obter configurações SMTP
 */
function getSettings($pdo, $user_id) {
    try {
        // Verificar se tabela existe
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'email_settings'");
        if ($tableCheck->rowCount() == 0) {
            echo json_encode(['success' => true, 'settings' => null]);
            return;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM email_settings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Ocultar senha
        if ($settings && isset($settings['smtp_password'])) {
            $settings['smtp_password'] = '********';
        }
        
        echo json_encode([
            'success' => true,
            'settings' => $settings ?: null
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => true, 'settings' => null]);
    }
}

/**
 * Salvar configurações SMTP
 */
function saveSettings($pdo, $user_id) {
    $data = $_POST;
    
    // Verificar se já existe configuração
    $stmt = $pdo->prepare("SELECT id FROM email_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Atualizar
        $sql = "UPDATE email_settings SET 
                smtp_host = ?, smtp_port = ?, smtp_username = ?, 
                smtp_encryption = ?, from_email = ?, from_name = ?, is_enabled = ?";
        
        $params = [
            $data['smtp_host'],
            $data['smtp_port'],
            $data['smtp_username'],
            $data['smtp_encryption'],
            $data['from_email'],
            $data['from_name'],
            isset($data['is_enabled']) ? 1 : 0
        ];
        
        // Atualizar senha apenas se fornecida
        if (!empty($data['smtp_password']) && $data['smtp_password'] !== '********') {
            $sql .= ", smtp_password = ?";
            $params[] = $data['smtp_password'];
        }
        
        $sql .= " WHERE user_id = ?";
        $params[] = $user_id;
        
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute($params);
        
    } else {
        // Inserir
        $stmt = $pdo->prepare("
            INSERT INTO email_settings 
            (user_id, smtp_host, smtp_port, smtp_username, smtp_password, 
             smtp_encryption, from_email, from_name, is_enabled)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $success = $stmt->execute([
            $user_id,
            $data['smtp_host'],
            $data['smtp_port'],
            $data['smtp_username'],
            $data['smtp_password'],
            $data['smtp_encryption'],
            $data['from_email'],
            $data['from_name'],
            isset($data['is_enabled']) ? 1 : 0
        ]);
    }
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Configurações salvas com sucesso'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao salvar configurações']);
    }
}

/**
 * Obter preferências de notificação
 */
function getPreferences($pdo, $user_id) {
    try {
        // Verificar se tabela existe
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'notification_preferences'");
        if ($tableCheck->rowCount() == 0) {
            echo json_encode(['success' => true, 'preferences' => null]);
            return;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM notification_preferences WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $preferences = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'preferences' => $preferences ?: null
        ]);
        return;
    } catch (Exception $e) {
        echo json_encode(['success' => true, 'preferences' => null]);
    }
}

/**
 * Salvar preferências de notificação
 */
function savePreferences($pdo, $user_id) {
    $data = $_POST;
    
    // Verificar se já existe
    $stmt = $pdo->prepare("SELECT id FROM notification_preferences WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Atualizar
        $stmt = $pdo->prepare("
            UPDATE notification_preferences SET
                notify_new_conversation = ?,
                notify_conversation_assigned = ?,
                notify_conversation_transferred = ?,
                notify_conversation_closed = ?,
                notify_sla_warning = ?,
                notify_customer_reply = ?,
                daily_summary = ?,
                daily_summary_time = ?,
                weekly_summary = ?,
                weekly_summary_day = ?,
                monthly_summary = ?,
                alert_queue_threshold = ?,
                alert_wait_time_threshold = ?
            WHERE user_id = ?
        ");
        
        $success = $stmt->execute([
            isset($data['notify_new_conversation']) ? 1 : 0,
            isset($data['notify_conversation_assigned']) ? 1 : 0,
            isset($data['notify_conversation_transferred']) ? 1 : 0,
            isset($data['notify_conversation_closed']) ? 1 : 0,
            isset($data['notify_sla_warning']) ? 1 : 0,
            isset($data['notify_customer_reply']) ? 1 : 0,
            isset($data['daily_summary']) ? 1 : 0,
            $data['daily_summary_time'] ?? '18:00:00',
            isset($data['weekly_summary']) ? 1 : 0,
            $data['weekly_summary_day'] ?? 1,
            isset($data['monthly_summary']) ? 1 : 0,
            $data['alert_queue_threshold'] ?? 10,
            $data['alert_wait_time_threshold'] ?? 300,
            $user_id
        ]);
        
    } else {
        // Inserir
        $stmt = $pdo->prepare("
            INSERT INTO notification_preferences 
            (user_id, user_type, notify_new_conversation, notify_conversation_assigned,
             notify_conversation_transferred, notify_conversation_closed, notify_sla_warning,
             notify_customer_reply, daily_summary, daily_summary_time, weekly_summary,
             weekly_summary_day, monthly_summary, alert_queue_threshold, alert_wait_time_threshold)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $success = $stmt->execute([
            $user_id,
            'supervisor',
            isset($data['notify_new_conversation']) ? 1 : 0,
            isset($data['notify_conversation_assigned']) ? 1 : 0,
            isset($data['notify_conversation_transferred']) ? 1 : 0,
            isset($data['notify_conversation_closed']) ? 1 : 0,
            isset($data['notify_sla_warning']) ? 1 : 0,
            isset($data['notify_customer_reply']) ? 1 : 0,
            isset($data['daily_summary']) ? 1 : 0,
            $data['daily_summary_time'] ?? '18:00:00',
            isset($data['weekly_summary']) ? 1 : 0,
            $data['weekly_summary_day'] ?? 1,
            isset($data['monthly_summary']) ? 1 : 0,
            $data['alert_queue_threshold'] ?? 10,
            $data['alert_wait_time_threshold'] ?? 300
        ]);
    }
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Preferências salvas com sucesso'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao salvar preferências']);
    }
}

/**
 * Listar templates
 */
function listTemplates($pdo, $user_id) {
    try {
        // Verificar se tabela existe
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'email_templates'");
        if ($tableCheck->rowCount() == 0) {
            echo json_encode(['success' => true, 'templates' => []]);
            return;
        }
        
        $stmt = $pdo->prepare("
            SELECT * FROM email_templates 
            WHERE user_id = ? OR is_default = 1
            ORDER BY is_default DESC, type, name
        ");
        $stmt->execute([$user_id]);
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'templates' => $templates
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => true, 'templates' => []]);
    }
}

/**
 * Obter template específico
 */
function getTemplate($pdo, $user_id) {
    $template_id = $_GET['id'] ?? null;
    
    if (!$template_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID não fornecido']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE id = ?");
    $stmt->execute([$template_id]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$template) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Template não encontrado']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'template' => $template
    ]);
}

/**
 * Salvar template
 */
function saveTemplate($pdo, $user_id) {
    $data = $_POST;
    $template_id = $data['id'] ?? null;
    
    if ($template_id) {
        // Atualizar
        $stmt = $pdo->prepare("
            UPDATE email_templates 
            SET name = ?, subject = ?, body = ?, is_active = ?
            WHERE id = ? AND user_id = ?
        ");
        
        $success = $stmt->execute([
            $data['name'],
            $data['subject'],
            $data['body'],
            isset($data['is_active']) ? 1 : 0,
            $template_id,
            $user_id
        ]);
        
        $message = 'Template atualizado com sucesso';
        
    } else {
        // Inserir
        $stmt = $pdo->prepare("
            INSERT INTO email_templates 
            (user_id, name, type, subject, body, is_active)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $success = $stmt->execute([
            $user_id,
            $data['name'],
            $data['type'],
            $data['subject'],
            $data['body'],
            isset($data['is_active']) ? 1 : 0
        ]);
        
        $message = 'Template criado com sucesso';
    }
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => $message
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao salvar template']);
    }
}

/**
 * Deletar template
 */
function deleteTemplate($pdo, $user_id) {
    $template_id = $_POST['id'] ?? $_GET['id'] ?? null;
    
    if (!$template_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID não fornecido']);
        return;
    }
    
    // Não permitir deletar templates padrão
    $stmt = $pdo->prepare("SELECT is_default FROM email_templates WHERE id = ?");
    $stmt->execute([$template_id]);
    $template = $stmt->fetch();
    
    if ($template && $template['is_default']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Não é possível deletar templates padrão']);
        return;
    }
    
    $stmt = $pdo->prepare("DELETE FROM email_templates WHERE id = ? AND user_id = ?");
    $success = $stmt->execute([$template_id, $user_id]);
    
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
 * Testar envio de email
 */
function testEmail($pdo, $user_id) {
    try {
        $to = $_POST['to'] ?? null;
        
        if (!$to) {
            echo json_encode(['success' => false, 'error' => 'Email de destino não fornecido']);
            return;
        }
        
        // Verificar se tabela existe
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'email_settings'");
        if ($tableCheck->rowCount() == 0) {
            echo json_encode(['success' => false, 'error' => 'Configure as notificações primeiro. Execute o SQL de criação das tabelas.']);
            return;
        }
        
        // Verificar se há configuração
        $stmt = $pdo->prepare("SELECT * FROM email_settings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$settings) {
            echo json_encode(['success' => false, 'error' => 'Nenhuma configuração de email encontrada. Configure o SMTP ou conecte sua conta Microsoft.']);
            return;
        }
        
        // Carregar EmailSender
        loadEmailSender();
        
        $emailSender = new EmailSender($pdo, $user_id);
        $result = $emailSender->testConnection($to);
        
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Erro: ' . $e->getMessage()]);
    }
}

/**
 * Obter logs de email
 */
function getLogs($pdo, $user_id) {
    try {
        // Verificar se tabela existe
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'email_logs'");
        if ($tableCheck->rowCount() == 0) {
            echo json_encode(['success' => true, 'logs' => []]);
            return;
        }
        
        $limit = $_GET['limit'] ?? 50;
        $status = $_GET['status'] ?? null;
        
        $sql = "SELECT * FROM email_logs WHERE user_id = ?";
        $params = [$user_id];
        
        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = (int)$limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formatar datas
        foreach ($logs as &$log) {
            $log['created_at_formatted'] = date('d/m/Y H:i', strtotime($log['created_at']));
            if ($log['sent_at']) {
                $log['sent_at_formatted'] = date('d/m/Y H:i', strtotime($log['sent_at']));
            }
        }
        
        echo json_encode([
            'success' => true,
            'logs' => $logs
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => true, 'logs' => []]);
    }
}

/**
 * Obter estatísticas
 */
function getStats($pdo, $user_id) {
    try {
        // Verificar se tabela existe
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'email_logs'");
        if ($tableCheck->rowCount() == 0) {
            // Tabela não existe, retornar zeros
            echo json_encode([
                'success' => true,
                'stats' => [
                    'total_sent' => 0,
                    'total_failed' => 0,
                    'sent_today' => 0,
                    'success_rate' => 0
                ]
            ]);
            return;
        }
        
        // Total enviados
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM email_logs WHERE user_id = ? AND status = 'sent'");
        $stmt->execute([$user_id]);
        $total_sent = $stmt->fetch()['count'] ?? 0;
        
        // Total falhados
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM email_logs WHERE user_id = ? AND status = 'failed'");
        $stmt->execute([$user_id]);
        $total_failed = $stmt->fetch()['count'] ?? 0;
        
        // Enviados hoje
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM email_logs WHERE user_id = ? AND DATE(created_at) = CURDATE()");
        $stmt->execute([$user_id]);
        $sent_today = $stmt->fetch()['count'] ?? 0;
        
        // Taxa de sucesso
        $total = $total_sent + $total_failed;
        $success_rate = $total > 0 ? ($total_sent / $total) * 100 : 0;
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'total_sent' => (int)$total_sent,
                'total_failed' => (int)$total_failed,
                'sent_today' => (int)$sent_today,
                'success_rate' => round($success_rate, 1)
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => true,
            'stats' => [
                'total_sent' => 0,
                'total_failed' => 0,
                'sent_today' => 0,
                'success_rate' => 0
            ]
        ]);
    }
}

/**
 * Desconectar conta OAuth (Microsoft)
 */
function disconnectOAuth($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE email_settings 
            SET oauth_provider = NULL, 
                oauth_tokens = NULL, 
                smtp_encryption = 'tls',
                smtp_host = '',
                smtp_port = 587,
                smtp_username = '',
                smtp_password = '',
                from_email = ''
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Conta Microsoft desconectada com sucesso'
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Erro ao desconectar: ' . $e->getMessage()
        ]);
    }
}
