<?php
/**
 * Meta API Rate Limiter
 * Gerencia limites de taxa da Meta API por usuário e globalmente
 * Implementa controle inteligente de rate limiting com alertas
 */

class MetaRateLimiter
{
    private PDO $pdo;
    private int $defaultLimit;
    private int $defaultWindow;
    
    // Limites por tier da Meta API
    const TIER_LIMITS = [
        'tier_1' => 1000,      // 1K conversas únicas/dia
        'tier_2' => 10000,     // 10K conversas únicas/dia
        'tier_3' => 100000,    // 100K conversas únicas/dia
        'unlimited' => 999999  // Sem limite (Tech Provider)
    ];
    
    // Thresholds para alertas (% do limite)
    const WARNING_THRESHOLD = 0.80;  // 80%
    const CRITICAL_THRESHOLD = 0.95; // 95%
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->defaultLimit = (int)(getenv('WEBHOOK_RATE_LIMIT') ?: 100);
        $this->defaultWindow = (int)(getenv('WEBHOOK_RATE_WINDOW') ?: 60);
    }
    
    /**
     * Verifica se o usuário pode enviar mensagem
     */
    public function canSend(int $userId, string $phone): array
    {
        // Buscar tier e limites do usuário
        $userLimits = $this->getUserLimits($userId);
        
        // Verificar limite por minuto (rate limit técnico)
        $minuteCheck = $this->checkMinuteLimit($userId);
        if (!$minuteCheck['allowed']) {
            return [
                'allowed' => false,
                'reason' => 'rate_limit_exceeded',
                'message' => 'Limite de mensagens por minuto excedido. Aguarde alguns segundos.',
                'retry_after' => $minuteCheck['retry_after']
            ];
        }
        
        // Verificar limite diário (conversas únicas)
        $dailyCheck = $this->checkDailyLimit($userId, $phone, $userLimits['daily_limit']);
        if (!$dailyCheck['allowed']) {
            return [
                'allowed' => false,
                'reason' => 'daily_limit_exceeded',
                'message' => sprintf(
                    'Limite diário de %d conversas únicas atingido. Tier atual: %s',
                    $userLimits['daily_limit'],
                    $userLimits['tier']
                ),
                'current_count' => $dailyCheck['current_count'],
                'limit' => $userLimits['daily_limit'],
                'tier' => $userLimits['tier']
            ];
        }
        
        // Verificar se está próximo do limite (alertas)
        $usage = $dailyCheck['current_count'] / $userLimits['daily_limit'];
        $alert = null;
        
        if ($usage >= self::CRITICAL_THRESHOLD) {
            $alert = [
                'level' => 'critical',
                'message' => sprintf(
                    'CRÍTICO: %d%% do limite diário utilizado (%d/%d)',
                    round($usage * 100),
                    $dailyCheck['current_count'],
                    $userLimits['daily_limit']
                )
            ];
            $this->logAlert($userId, 'critical', $alert['message']);
        } elseif ($usage >= self::WARNING_THRESHOLD) {
            $alert = [
                'level' => 'warning',
                'message' => sprintf(
                    'AVISO: %d%% do limite diário utilizado (%d/%d)',
                    round($usage * 100),
                    $dailyCheck['current_count'],
                    $userLimits['daily_limit']
                )
            ];
            $this->logAlert($userId, 'warning', $alert['message']);
        }
        
        return [
            'allowed' => true,
            'usage' => [
                'current' => $dailyCheck['current_count'],
                'limit' => $userLimits['daily_limit'],
                'percentage' => round($usage * 100, 2),
                'tier' => $userLimits['tier']
            ],
            'alert' => $alert
        ];
    }
    
    /**
     * Registra envio de mensagem
     */
    public function recordSend(int $userId, string $phone, bool $isNewConversation = false): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO meta_rate_limit_tracking 
            (user_id, phone, is_new_conversation, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $phone, $isNewConversation ? 1 : 0]);
    }
    
    /**
     * Obtém limites do usuário
     */
    private function getUserLimits(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT meta_tier, meta_daily_limit, meta_minute_limit
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $tier = $user['meta_tier'] ?? 'tier_1';
        $dailyLimit = $user['meta_daily_limit'] ?? self::TIER_LIMITS[$tier];
        $minuteLimit = $user['meta_minute_limit'] ?? $this->defaultLimit;
        
        return [
            'tier' => $tier,
            'daily_limit' => (int)$dailyLimit,
            'minute_limit' => (int)$minuteLimit
        ];
    }
    
    /**
     * Verifica limite por minuto
     */
    private function checkMinuteLimit(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM meta_rate_limit_tracking
            WHERE user_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $count = (int)$result['count'];
        $limit = $this->getUserLimits($userId)['minute_limit'];
        
        if ($count >= $limit) {
            return [
                'allowed' => false,
                'retry_after' => 60
            ];
        }
        
        return ['allowed' => true];
    }
    
    /**
     * Verifica limite diário de conversas únicas
     */
    private function checkDailyLimit(int $userId, string $phone, int $limit): array
    {
        // Contar conversas únicas nas últimas 24 horas
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT phone) as unique_conversations
            FROM meta_rate_limit_tracking
            WHERE user_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $currentCount = (int)$result['unique_conversations'];
        
        // Verificar se o telefone atual já foi contado
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as exists_count
            FROM meta_rate_limit_tracking
            WHERE user_id = ?
            AND phone = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute([$userId, $phone]);
        $phoneExists = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Se é uma nova conversa, incrementar contador
        $projectedCount = $currentCount;
        if ((int)$phoneExists['exists_count'] === 0) {
            $projectedCount++;
        }
        
        if ($projectedCount > $limit) {
            return [
                'allowed' => false,
                'current_count' => $currentCount
            ];
        }
        
        return [
            'allowed' => true,
            'current_count' => $currentCount
        ];
    }
    
    /**
     * Registra alerta de limite
     */
    private function logAlert(int $userId, string $level, string $message): void
    {
        // Verificar se já foi enviado alerta similar recentemente (evitar spam)
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM meta_rate_limit_alerts
            WHERE user_id = ?
            AND alert_level = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$userId, $level]);
        $recent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ((int)$recent['count'] > 0) {
            return; // Já enviou alerta recente
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO meta_rate_limit_alerts
            (user_id, alert_level, message, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $level, $message]);
        
        error_log("[META_RATE_LIMITER] Alerta $level para usuário $userId: $message");
    }
    
    /**
     * Obtém estatísticas de uso
     */
    public function getUsageStats(int $userId): array
    {
        $limits = $this->getUserLimits($userId);
        
        // Uso nas últimas 24 horas
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(DISTINCT phone) as unique_conversations,
                COUNT(*) as total_messages,
                SUM(CASE WHEN is_new_conversation = 1 THEN 1 ELSE 0 END) as new_conversations
            FROM meta_rate_limit_tracking
            WHERE user_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute([$userId]);
        $daily = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Uso na última hora
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as messages_last_hour
            FROM meta_rate_limit_tracking
            WHERE user_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$userId]);
        $hourly = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $usage = (int)$daily['unique_conversations'] / $limits['daily_limit'];
        
        return [
            'tier' => $limits['tier'],
            'limits' => [
                'daily' => $limits['daily_limit'],
                'minute' => $limits['minute_limit']
            ],
            'usage_24h' => [
                'unique_conversations' => (int)$daily['unique_conversations'],
                'total_messages' => (int)$daily['total_messages'],
                'new_conversations' => (int)$daily['new_conversations'],
                'percentage' => round($usage * 100, 2)
            ],
            'usage_1h' => [
                'messages' => (int)$hourly['messages_last_hour']
            ],
            'status' => $this->getUsageStatus($usage)
        ];
    }
    
    /**
     * Determina status de uso
     */
    private function getUsageStatus(float $usage): string
    {
        if ($usage >= self::CRITICAL_THRESHOLD) {
            return 'critical';
        } elseif ($usage >= self::WARNING_THRESHOLD) {
            return 'warning';
        }
        return 'normal';
    }
    
    /**
     * Limpa registros antigos (manutenção)
     */
    public function cleanup(): void
    {
        // Remover registros com mais de 7 dias
        $stmt = $this->pdo->prepare("
            DELETE FROM meta_rate_limit_tracking
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute();
        
        $stmt = $this->pdo->prepare("
            DELETE FROM meta_rate_limit_alerts
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute();
    }
}
