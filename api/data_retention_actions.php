<?php
/**
 * API para Ações de Retenção de Dados
 * Endpoints para gerenciar configurações e executar ações
 * 
 * MACIP Tecnologia LTDA
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/StorageMonitor.php';

header('Content-Type: application/json');

// Verificar autenticação
session_start();
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

// Obter ação
$action = $_GET['action'] ?? ($_POST['action'] ?? null);
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['action'])) {
    $action = $data['action'];
}

try {
    switch ($action) {
        
        // ============================================
        // FORÇAR LIMPEZA MANUAL
        // ============================================
        case 'force_cleanup':
            $startTime = microtime(true);
            $totalDeleted = 0;
            
            // Carregar política
            $policy = require __DIR__ . '/../config/data_retention.php';
            
            // Executar limpezas
            $tables = [
                'dispatch_history' => $policy['messages']['dispatch_history'],
                'webhook_logs' => $policy['messages']['webhook_logs'],
                'audit_logs' => $policy['logs']['audit_logs'],
                'login_attempts' => $policy['logs']['login_attempts'],
            ];
            
            foreach ($tables as $table => $days) {
                try {
                    $stmt = $pdo->prepare("
                        DELETE FROM $table 
                        WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                        LIMIT 1000
                    ");
                    $stmt->execute([$days]);
                    $totalDeleted += $stmt->rowCount();
                } catch (PDOException $e) {
                    // Tabela pode não existir, continuar
                    continue;
                }
            }
            
            $executionTime = round(microtime(true) - $startTime, 2);
            
            // Registrar no histórico
            $stmt = $pdo->prepare("
                INSERT INTO cleanup_history (
                    total_deleted, execution_time, storage_size_mb, created_at
                ) VALUES (?, ?, 0, NOW())
            ");
            $stmt->execute([$totalDeleted, $executionTime]);
            
            echo json_encode([
                'success' => true,
                'total_deleted' => $totalDeleted,
                'execution_time' => $executionTime
            ]);
            break;
        
        // ============================================
        // LIMPAR DADOS DE USUÁRIO ESPECÍFICO
        // ============================================
        case 'cleanup_user':
            $userId = $data['user_id'] ?? null;
            
            if (!$userId) {
                throw new Exception('ID do usuário não fornecido');
            }
            
            $policy = require __DIR__ . '/../config/data_retention.php';
            $totalDeleted = 0;
            
            // Limpar mensagens antigas do usuário
            $stmt = $pdo->prepare("
                DELETE FROM dispatch_history 
                WHERE user_id = ? 
                AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$userId, $policy['messages']['dispatch_history']]);
            $totalDeleted += $stmt->rowCount();
            
            // Limpar logs do usuário
            $stmt = $pdo->prepare("
                DELETE FROM audit_logs 
                WHERE user_id = ? 
                AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$userId, $policy['logs']['audit_logs']]);
            $totalDeleted += $stmt->rowCount();
            
            // Atualizar uso de storage
            $monitor = new StorageMonitor($pdo);
            $newUsage = $monitor->getUserStorageUsage($userId);
            
            echo json_encode([
                'success' => true,
                'total_deleted' => $totalDeleted,
                'new_usage_mb' => $newUsage
            ]);
            break;
        
        // ============================================
        // SALVAR CONFIGURAÇÕES
        // ============================================
        case 'save_config':
            $config = $data['config'] ?? null;
            
            if (!$config) {
                throw new Exception('Configurações não fornecidas');
            }
            
            // Carregar arquivo atual
            $configFile = __DIR__ . '/../config/data_retention.php';
            $currentPolicy = require $configFile;
            
            // Atualizar valores - Mensagens
            $currentPolicy['messages']['chat_messages'] = (int)$config['chat_messages'];
            $currentPolicy['messages']['dispatch_history'] = (int)$config['dispatch_history'];
            $currentPolicy['messages']['webhook_logs'] = (int)$config['webhook_logs'];
            $currentPolicy['messages']['failed_messages'] = (int)$config['failed_messages'];
            
            // Atualizar valores - Logs
            $currentPolicy['logs']['audit_logs'] = (int)$config['audit_logs'];
            $currentPolicy['logs']['login_attempts'] = (int)$config['login_attempts'];
            $currentPolicy['logs']['api_logs'] = (int)$config['api_logs'];
            $currentPolicy['logs']['error_logs'] = (int)$config['error_logs'];
            
            // Atualizar valores - Alertas
            $currentPolicy['storage_alerts']['warning_threshold'] = (int)$config['warning_threshold'];
            $currentPolicy['storage_alerts']['critical_threshold'] = (int)$config['critical_threshold'];
            
            // Atualizar valores - Limites por Plano
            if (isset($config['free_storage'])) {
                $currentPolicy['plan_limits']['free']['max_storage_mb'] = (int)$config['free_storage'];
                $currentPolicy['plan_limits']['free']['retention_days'] = (int)$config['free_retention'];
                $currentPolicy['plan_limits']['free']['max_messages_month'] = (int)$config['free_messages'];
            }
            
            if (isset($config['basic_storage'])) {
                $currentPolicy['plan_limits']['basic']['max_storage_mb'] = (int)$config['basic_storage'];
                $currentPolicy['plan_limits']['basic']['retention_days'] = (int)$config['basic_retention'];
                $currentPolicy['plan_limits']['basic']['max_messages_month'] = (int)$config['basic_messages'];
            }
            
            if (isset($config['professional_storage'])) {
                $currentPolicy['plan_limits']['professional']['max_storage_mb'] = (int)$config['professional_storage'];
                $currentPolicy['plan_limits']['professional']['retention_days'] = (int)$config['professional_retention'];
                $currentPolicy['plan_limits']['professional']['max_messages_month'] = (int)$config['professional_messages'];
            }
            
            if (isset($config['enterprise_storage'])) {
                $currentPolicy['plan_limits']['enterprise']['max_storage_mb'] = (int)$config['enterprise_storage'];
                $currentPolicy['plan_limits']['enterprise']['retention_days'] = (int)$config['enterprise_retention'];
                $currentPolicy['plan_limits']['enterprise']['max_messages_month'] = (int)$config['enterprise_messages'];
            }
            
            // Atualizar valores - Limpeza
            $currentPolicy['cleanup_settings']['enabled'] = isset($config['cleanup_enabled']);
            $currentPolicy['cleanup_settings']['run_hour'] = (int)$config['run_hour'];
            
            // Salvar arquivo
            $phpCode = "<?php\n/**\n * Política de Retenção de Dados\n * Atualizado em: " . date('Y-m-d H:i:s') . "\n */\n\nreturn " . var_export($currentPolicy, true) . ";\n";
            
            if (file_put_contents($configFile, $phpCode)) {
                echo json_encode(['success' => true, 'message' => 'Configurações salvas']);
            } else {
                throw new Exception('Erro ao salvar arquivo de configuração');
            }
            break;
        
        // ============================================
        // RESTAURAR CONFIGURAÇÕES PADRÃO
        // ============================================
        case 'reset_config':
            $configFile = __DIR__ . '/../config/data_retention.php';
            $backupFile = __DIR__ . '/../config/data_retention.backup.php';
            
            // Fazer backup da configuração atual
            if (file_exists($configFile)) {
                copy($configFile, $backupFile);
            }
            
            // Restaurar padrões (recriar o arquivo)
            $defaultPolicy = [
                'messages' => [
                    'chat_messages' => 180,
                    'dispatch_history' => 365,
                    'webhook_logs' => 30,
                    'failed_messages' => 90,
                ],
                'logs' => [
                    'audit_logs' => 365,
                    'login_attempts' => 90,
                    'api_logs' => 60,
                    'error_logs' => 30,
                    'debug_logs' => 7,
                ],
                'storage_alerts' => [
                    'warning_threshold' => 80,
                    'critical_threshold' => 95,
                    'check_interval_hours' => 6,
                    'notify_admin' => true,
                    'notify_user' => true,
                ],
                'cleanup_settings' => [
                    'enabled' => true,
                    'run_hour' => 3,
                    'batch_size' => 1000,
                    'max_execution_time' => 300,
                    'log_cleanup' => true,
                ],
            ];
            
            $phpCode = "<?php\n/**\n * Política de Retenção de Dados (Padrão)\n * Restaurado em: " . date('Y-m-d H:i:s') . "\n */\n\nreturn " . var_export($defaultPolicy, true) . ";\n";
            
            if (file_put_contents($configFile, $phpCode)) {
                echo json_encode(['success' => true, 'message' => 'Configurações restauradas']);
            } else {
                throw new Exception('Erro ao restaurar configurações');
            }
            break;
        
        // ============================================
        // OBTER STORAGE DE USUÁRIOS
        // ============================================
        case 'get_users_storage':
            $stmt = $pdo->query("
                SELECT * FROM v_user_storage_summary 
                ORDER BY percentage_used DESC
            ");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'users' => $users
            ]);
            break;
        
        // ============================================
        // OBTER LOGS DO SISTEMA
        // ============================================
        case 'get_logs':
            $type = $_GET['type'] ?? 'cleanup';
            $limit = (int)($_GET['limit'] ?? 50);
            
            if ($type === 'cleanup') {
                $stmt = $pdo->prepare("
                    SELECT * FROM cleanup_history 
                    ORDER BY created_at DESC 
                    LIMIT ?
                ");
                $stmt->execute([$limit]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT * FROM storage_checks 
                    ORDER BY created_at DESC 
                    LIMIT ?
                ");
                $stmt->execute([$limit]);
            }
            
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'logs' => $logs
            ]);
            break;
        
        // ============================================
        // CALCULAR STORAGE DE USUÁRIO
        // ============================================
        case 'calculate_user_storage':
            $userId = $_GET['user_id'] ?? null;
            
            if (!$userId) {
                throw new Exception('ID do usuário não fornecido');
            }
            
            $monitor = new StorageMonitor($pdo);
            $usage = $monitor->getUserStorageUsage($userId);
            $limit = $monitor->getUserStorageLimit($userId);
            
            echo json_encode([
                'success' => true,
                'usage_mb' => $usage,
                'limit_mb' => $limit,
                'percentage' => $limit > 0 ? ($usage / $limit) * 100 : 0
            ]);
            break;
        
        // ============================================
        // OBTER ESTATÍSTICAS GERAIS
        // ============================================
        case 'get_stats':
            $monitor = new StorageMonitor($pdo);
            $stats = $monitor->getSystemStats();
            
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
            break;
        
        default:
            throw new Exception('Ação não reconhecida: ' . $action);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
