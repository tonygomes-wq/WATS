<?php
/**
 * Cron Job - Backup Agendado de Conversas
 * 
 * Execute via cron:
 * 0 * * * * php /path/to/wats/cron/backup_scheduler.php
 * 
 * Verifica backups agendados e executa quando necessário
 * 
 * MACIP Tecnologia LTDA
 */

// Definir caminho base
define('BASE_PATH', dirname(__DIR__));

// Carregar dependências
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/backup_service.php';

// Log de início
$logFile = BASE_PATH . '/logs/backup_scheduler.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

logMessage("=== Iniciando verificação de backups agendados ===");

try {
    // Buscar configurações de backup ativas com agendamento
    $stmt = $pdo->prepare("
        SELECT bc.*, u.name as user_name, u.email as user_email
        FROM backup_configs bc
        INNER JOIN users u ON bc.user_id = u.id
        WHERE bc.is_active = 1 
        AND bc.schedule != 'manual'
    ");
    $stmt->execute();
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    logMessage("Encontradas " . count($configs) . " configurações de backup ativas");
    
    $now = new DateTime();
    $currentHour = (int) $now->format('H');
    $currentMinute = (int) $now->format('i');
    $currentDayOfWeek = (int) $now->format('w'); // 0 = domingo
    $currentDayOfMonth = (int) $now->format('j');
    
    foreach ($configs as $config) {
        $userId = (int) $config['user_id'];
        $schedule = $config['schedule'];
        $scheduleTime = $config['schedule_time'] ?? '03:00:00';
        $lastBackup = $config['last_backup_at'] ? new DateTime($config['last_backup_at']) : null;
        
        // Extrair hora do agendamento
        list($scheduleHour, $scheduleMinute) = explode(':', $scheduleTime);
        $scheduleHour = (int) $scheduleHour;
        $scheduleMinute = (int) $scheduleMinute;
        
        // Verificar se é hora de executar
        $shouldRun = false;
        
        // Tolerância de 5 minutos para execução
        $hourMatch = ($currentHour === $scheduleHour);
        $minuteMatch = abs($currentMinute - $scheduleMinute) <= 5;
        
        if ($hourMatch && $minuteMatch) {
            switch ($schedule) {
                case 'daily':
                    // Executar todo dia no horário
                    $shouldRun = true;
                    if ($lastBackup) {
                        // Não executar se já rodou hoje
                        $shouldRun = $lastBackup->format('Y-m-d') !== $now->format('Y-m-d');
                    }
                    break;
                    
                case 'weekly':
                    // Executar toda segunda-feira (1)
                    if ($currentDayOfWeek === 1) {
                        $shouldRun = true;
                        if ($lastBackup) {
                            $daysSinceLastBackup = $now->diff($lastBackup)->days;
                            $shouldRun = $daysSinceLastBackup >= 6;
                        }
                    }
                    break;
                    
                case 'monthly':
                    // Executar no dia 1 de cada mês
                    if ($currentDayOfMonth === 1) {
                        $shouldRun = true;
                        if ($lastBackup) {
                            $shouldRun = $lastBackup->format('Y-m') !== $now->format('Y-m');
                        }
                    }
                    break;
            }
        }
        
        if ($shouldRun) {
            logMessage("Executando backup para usuário {$userId} ({$config['user_email']})");
            
            try {
                $options = [
                    'format' => $config['format'] ?? 'json',
                    'include_media' => (bool) $config['include_media'],
                    'config_id' => $config['id'],
                    'retention_days' => (int) $config['retention_days']
                ];
                
                $result = createConversationBackup($userId, $options);
                
                if ($result['success']) {
                    logMessage("Backup criado com sucesso: {$result['filename']} ({$result['conversations']} conversas, {$result['messages']} mensagens)");
                    
                    // Atualizar last_backup_at e calcular next_backup_at
                    $nextBackup = calculateNextBackup($schedule, $scheduleTime);
                    
                    $stmt = $pdo->prepare("
                        UPDATE backup_configs 
                        SET last_backup_at = NOW(), next_backup_at = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$nextBackup, $config['id']]);
                    
                } else {
                    logMessage("ERRO ao criar backup: " . ($result['error'] ?? 'Erro desconhecido'));
                }
                
            } catch (Exception $e) {
                logMessage("EXCEÇÃO ao criar backup: " . $e->getMessage());
            }
        }
    }
    
    // Limpar backups expirados
    logMessage("Verificando backups expirados...");
    $deleted = cleanupExpiredBackups();
    logMessage("Backups expirados removidos: $deleted");
    
} catch (Exception $e) {
    logMessage("ERRO FATAL: " . $e->getMessage());
}

logMessage("=== Verificação concluída ===\n");

/**
 * Calcula próxima data de backup
 */
function calculateNextBackup($schedule, $scheduleTime) {
    $now = new DateTime();
    list($hour, $minute) = explode(':', $scheduleTime);
    
    switch ($schedule) {
        case 'daily':
            $next = new DateTime('tomorrow ' . $scheduleTime);
            break;
            
        case 'weekly':
            $next = new DateTime('next monday ' . $scheduleTime);
            break;
            
        case 'monthly':
            $next = new DateTime('first day of next month ' . $scheduleTime);
            break;
            
        default:
            return null;
    }
    
    return $next->format('Y-m-d H:i:s');
}
