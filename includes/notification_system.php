<?php
/**
 * Sistema de Notificações Push em Tempo Real
 * Suporta SSE (Server-Sent Events) e WebSocket
 */

class NotificationSystem
{
    private PDO $pdo;
    private int $userId;
    
    public function __construct(PDO $pdo, int $userId)
    {
        $this->pdo = $pdo;
        $this->userId = $userId;
    }
    
    /**
     * Cria uma notificação
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO notifications 
            (user_id, type, title, message, data, priority, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $this->userId,
            $data['type'] ?? 'info',
            $data['title'] ?? '',
            $data['message'] ?? '',
            isset($data['data']) ? json_encode($data['data']) : null,
            $data['priority'] ?? 'normal'
        ]);
        
        return (int)$this->pdo->lastInsertId();
    }
    
    /**
     * Busca notificações não lidas
     */
    public function getUnread(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM notifications
            WHERE user_id = ? AND is_read = 0
            ORDER BY created_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$this->userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Marca notificação como lida
     */
    public function markAsRead(int $notificationId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE notifications 
            SET is_read = 1, read_at = NOW()
            WHERE id = ? AND user_id = ?
        ");
        
        return $stmt->execute([$notificationId, $this->userId]);
    }
    
    /**
     * Marca todas como lidas
     */
    public function markAllAsRead(): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE notifications 
            SET is_read = 1, read_at = NOW()
            WHERE user_id = ? AND is_read = 0
        ");
        
        return $stmt->execute([$this->userId]);
    }
    
    /**
     * Conta notificações não lidas
     */
    public function countUnread(): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM notifications
            WHERE user_id = ? AND is_read = 0
        ");
        
        $stmt->execute([$this->userId]);
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * Notificação para resposta negativa
     */
    public function notifyNegativeResponse(array $response): int
    {
        return $this->create([
            'type' => 'negative_response',
            'title' => '⚠️ Resposta Negativa Recebida',
            'message' => "Contato {$response['contact_name']} enviou uma resposta negativa",
            'priority' => 'high',
            'data' => [
                'response_id' => $response['id'],
                'campaign_id' => $response['campaign_id'],
                'phone' => $response['phone'],
                'message' => $response['message_text']
            ]
        ]);
    }
    
    /**
     * Notificação para nova resposta
     */
    public function notifyNewResponse(array $response): int
    {
        $priority = $response['is_first_response'] ? 'high' : 'normal';
        $icon = $response['is_first_response'] ? '🎉' : '💬';
        
        return $this->create([
            'type' => 'new_response',
            'title' => "{$icon} Nova Resposta Recebida",
            'message' => "Contato {$response['contact_name']} respondeu sua mensagem",
            'priority' => $priority,
            'data' => [
                'response_id' => $response['id'],
                'campaign_id' => $response['campaign_id'],
                'phone' => $response['phone'],
                'sentiment' => $response['sentiment']
            ]
        ]);
    }
    
    /**
     * Notificação para campanha concluída
     */
    public function notifyCampaignCompleted(array $campaign): int
    {
        $successRate = $campaign['total_contacts'] > 0 
            ? round(($campaign['sent_count'] / $campaign['total_contacts']) * 100, 2)
            : 0;
        
        return $this->create([
            'type' => 'campaign_completed',
            'title' => '✅ Campanha Concluída',
            'message' => "Campanha '{$campaign['name']}' finalizada com {$successRate}% de sucesso",
            'priority' => 'normal',
            'data' => [
                'campaign_id' => $campaign['id'],
                'sent_count' => $campaign['sent_count'],
                'failed_count' => $campaign['failed_count'],
                'response_count' => $campaign['response_count']
            ]
        ]);
    }
    
    /**
     * Limpa notificações antigas
     */
    public function cleanOldNotifications(int $daysOld = 30): int
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM notifications
            WHERE user_id = ? 
            AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            AND is_read = 1
        ");
        
        $stmt->execute([$this->userId, $daysOld]);
        return $stmt->rowCount();
    }
}
