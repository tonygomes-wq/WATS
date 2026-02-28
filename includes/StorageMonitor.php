<?php
/**
 * Sistema de Monitoramento de Storage
 * Monitora uso de disco, banco de dados e envia alertas
 * 
 * MACIP Tecnologia LTDA
 */

class StorageMonitor
{
    private $pdo;
    private $config;
    private $retentionPolicy;
    
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->retentionPolicy = require __DIR__ . '/../config/data_retention.php';
        $this->config = $this->retentionPolicy['storage_alerts'];
    }
    
    /**
     * Verificar uso de storage de todos os usuários
     */
    public function checkAllUsers()
    {
        $stmt = $this->pdo->query("SELECT id, email, plan FROM users WHERE is_active = 1");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $alerts = [];
        
        foreach ($users as $user) {
            $usage = $this->getUserStorageUsage($user['id']);
            $limit = $this->getUserStorageLimit($user['plan']);
            
            if ($limit > 0) { // -1 = ilimitado
                $percentage = ($usage / $limit) * 100;
                
                if ($percentage >= $this->config['critical_threshold']) {
                    $alerts[] = [
                        'user_id' => $user['id'],
                        'email' => $user['email'],
                        'level' => 'critical',
                        'usage_mb' => $usage,
                        'limit_mb' => $limit,
                        'percentage' => round($percentage, 2)
                    ];
                } elseif ($percentage >= $this->config['warning_threshold']) {
                    $alerts[] = [
                        'user_id' => $user['id'],
                        'email' => $user['email'],
                        'level' => 'warning',
                        'usage_mb' => $usage,
                        'limit_mb' => $limit,
                        'percentage' => round($percentage, 2)
                    ];
                }
            }
        }
        
        return $alerts;
    }
    
    /**
     * Calcular uso de storage de um usuário
     */
    public function getUserStorageUsage($userId)
    {
        $totalSize = 0;
        
        // 1. Tamanho das mensagens no banco
        $stmt = $this->pdo->prepare("
            SELECT 
                SUM(LENGTH(message)) as message_size,
                SUM(LENGTH(response)) as response_size
            FROM dispatch_history
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalSize += ($data['message_size'] ?? 0) + ($data['response_size'] ?? 0);
        
        // 2. Tamanho dos contatos
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) * 1024 as contacts_size
            FROM contacts
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalSize += $data['contacts_size'] ?? 0;
        
        // 3. Tamanho dos arquivos de mídia
        $uploadDir = __DIR__ . "/../uploads/user_{$userId}/";
        if (is_dir($uploadDir)) {
            $totalSize += $this->getDirectorySize($uploadDir);
        }
        
        // 4. Tamanho dos logs do usuário
        $stmt = $this->pdo->prepare("
            SELECT SUM(LENGTH(log_data)) as log_size
            FROM audit_logs
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalSize += $data['log_size'] ?? 0;
        
        // Converter para MB
        return round($totalSize / 1024 / 1024, 2);
    }
    
    /**
     * Obter limite de storage do plano do usuário
     */
    public function getUserStorageLimit($plan)
    {
        $limits = $this->retentionPolicy['plan_limits'];
        
        if (isset($limits[$plan])) {
            return $limits[$plan]['max_storage_mb'];
        }
        
        // Padrão: plano básico
        return $limits['basic']['max_storage_mb'];
    }
    
    /**
     * Calcular tamanho de um diretório recursivamente
     */
    private function getDirectorySize($path, $maxDepth = 10)
    {
        $size = 0;
        
        if (!is_dir($path)) {
            return 0;
        }
        
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            // Limitar profundidade para evitar travamento
            $iterator->setMaxDepth($maxDepth);
            
            $fileCount = 0;
            $maxFiles = 10000; // Limite de arquivos para evitar timeout
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                    $fileCount++;
                    
                    // Parar se atingir limite de arquivos
                    if ($fileCount >= $maxFiles) {
                        error_log("StorageMonitor: Limite de $maxFiles arquivos atingido em $path");
                        break;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Erro ao calcular tamanho do diretório: " . $e->getMessage());
        }
        
        return $size;
    }
    
    /**
     * Enviar alertas de storage
     */
    public function sendAlerts($alerts)
    {
        foreach ($alerts as $alert) {
            // Criar notificação no sistema
            $this->createNotification($alert);
            
            // Enviar email se configurado
            if ($this->config['notify_user']) {
                $this->sendEmailAlert($alert);
            }
            
            // Notificar admin se crítico
            if ($alert['level'] === 'critical' && $this->config['notify_admin']) {
                $this->notifyAdmin($alert);
            }
        }
    }
    
    /**
     * Criar notificação no sistema
     */
    private function createNotification($alert)
    {
        $title = $alert['level'] === 'critical' 
            ? '🔴 Storage Crítico!' 
            : '⚠️ Aviso de Storage';
        
        $message = sprintf(
            "Você está usando %.2f%% do seu limite de storage (%d MB de %d MB). ",
            $alert['percentage'],
            $alert['usage_mb'],
            $alert['limit_mb']
        );
        
        if ($alert['level'] === 'critical') {
            $message .= "Libere espaço urgentemente ou faça upgrade do plano.";
        } else {
            $message .= "Considere limpar dados antigos ou fazer upgrade do plano.";
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO notifications (
                user_id, type, title, message, priority, created_at
            ) VALUES (?, 'storage_alert', ?, ?, ?, NOW())
        ");
        
        $priority = $alert['level'] === 'critical' ? 'high' : 'normal';
        $stmt->execute([
            $alert['user_id'],
            $title,
            $message,
            $priority
        ]);
    }
    
    /**
     * Enviar email de alerta
     */
    private function sendEmailAlert($alert)
    {
        // Implementar envio de email
        // Usar PHPMailer ou sistema de email existente
        
        $subject = $alert['level'] === 'critical' 
            ? 'URGENTE: Storage Crítico - WATS'
            : 'Aviso: Storage Alto - WATS';
        
        $body = "
            <h2>Alerta de Storage</h2>
            <p>Olá,</p>
            <p>Seu uso de storage está em <strong>{$alert['percentage']}%</strong>.</p>
            <ul>
                <li>Uso atual: {$alert['usage_mb']} MB</li>
                <li>Limite do plano: {$alert['limit_mb']} MB</li>
            </ul>
            <p>Recomendamos que você:</p>
            <ul>
                <li>Exclua mensagens e campanhas antigas</li>
                <li>Remova arquivos de mídia não utilizados</li>
                <li>Considere fazer upgrade do seu plano</li>
            </ul>
        ";
        
        // TODO: Implementar envio real de email
        error_log("Email de alerta enviado para: " . $alert['email']);
    }
    
    /**
     * Notificar administrador
     */
    private function notifyAdmin($alert)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO notifications (
                user_id, type, title, message, priority, created_at
            ) 
            SELECT 
                id, 
                'admin_storage_alert', 
                '🔴 Usuário com Storage Crítico',
                CONCAT('Usuário ', ?, ' atingiu ', ?, '% de uso de storage'),
                'high',
                NOW()
            FROM users 
            WHERE is_admin = 1
        ");
        
        $stmt->execute([
            $alert['email'],
            $alert['percentage']
        ]);
    }
    
    /**
     * Obter estatísticas gerais de storage
     */
    public function getSystemStats()
    {
        // Tamanho total do banco de dados
        $stmt = $this->pdo->query("
            SELECT 
                SUM(data_length + index_length) / 1024 / 1024 AS size_mb
            FROM information_schema.TABLES 
            WHERE table_schema = DATABASE()
        ");
        $dbSize = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Tamanho total dos uploads
        $uploadDir = __DIR__ . '/../uploads/';
        $uploadSize = 0;
        if (is_dir($uploadDir)) {
            $uploadSize = $this->getDirectorySize($uploadDir) / 1024 / 1024;
        }
        
        // Total de usuários
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
        $userCount = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Total de mensagens
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM dispatch_history");
        $messageCount = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'database_size_mb' => round($dbSize['size_mb'], 2),
            'uploads_size_mb' => round($uploadSize, 2),
            'total_size_mb' => round($dbSize['size_mb'] + $uploadSize, 2),
            'total_users' => $userCount['total'],
            'total_messages' => $messageCount['total'],
            'avg_per_user_mb' => $userCount['total'] > 0 
                ? round(($dbSize['size_mb'] + $uploadSize) / $userCount['total'], 2)
                : 0
        ];
    }
}
