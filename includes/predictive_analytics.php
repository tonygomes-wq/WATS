<?php
/**
 * Sistema de Análise Preditiva para Melhor Horário de Envio
 */

class PredictiveAnalytics
{
    private PDO $pdo;
    private int $userId;
    
    public function __construct(PDO $pdo, int $userId)
    {
        $this->pdo = $pdo;
        $this->userId = $userId;
    }
    
    /**
     * Calcula e armazena analytics de tempo
     */
    public function calculateTimeAnalytics(): void
    {
        // Buscar dados de dispatch dos últimos 90 dias
        $stmt = $this->pdo->prepare("
            SELECT 
                HOUR(sent_at) as hour_of_day,
                DAYOFWEEK(sent_at) as day_of_week,
                COUNT(*) as total_sent,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM dispatch_history
            WHERE user_id = ? 
            AND sent_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            AND sent_at IS NOT NULL
            GROUP BY HOUR(sent_at), DAYOFWEEK(sent_at)
        ");
        
        $stmt->execute([$this->userId]);
        $dispatchData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Buscar dados de resposta
        $stmt = $this->pdo->prepare("
            SELECT 
                HOUR(dh.sent_at) as hour_of_day,
                DAYOFWEEK(dh.sent_at) as day_of_week,
                COUNT(dr.id) as responses,
                AVG(dr.response_time_seconds) as avg_response_time
            FROM dispatch_history dh
            LEFT JOIN dispatch_responses dr ON dh.id = dr.dispatch_id
            WHERE dh.user_id = ? 
            AND dh.sent_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            AND dr.id IS NOT NULL
            GROUP BY HOUR(dh.sent_at), DAYOFWEEK(dh.sent_at)
        ");
        
        $stmt->execute([$this->userId]);
        $responseData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Indexar dados de resposta
        $responseIndex = [];
        foreach ($responseData as $row) {
            $key = $row['hour_of_day'] . '_' . $row['day_of_week'];
            $responseIndex[$key] = $row;
        }
        
        // Processar e salvar analytics
        foreach ($dispatchData as $data) {
            $key = $data['hour_of_day'] . '_' . $data['day_of_week'];
            $responses = $responseIndex[$key]['responses'] ?? 0;
            $avgResponseTime = $responseIndex[$key]['avg_response_time'] ?? 0;
            
            // Calcular score de engajamento (0-100)
            $engagementScore = $this->calculateEngagementScore(
                $data['total_sent'],
                $data['delivered'],
                $data['read_count'],
                $responses,
                $avgResponseTime
            );
            
            // Upsert na tabela
            $stmt = $this->pdo->prepare("
                INSERT INTO dispatch_time_analytics 
                (user_id, hour_of_day, day_of_week, total_sent, total_delivered, total_read, total_responses, avg_response_time, engagement_score)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    total_sent = VALUES(total_sent),
                    total_delivered = VALUES(total_delivered),
                    total_read = VALUES(total_read),
                    total_responses = VALUES(total_responses),
                    avg_response_time = VALUES(avg_response_time),
                    engagement_score = VALUES(engagement_score),
                    last_updated = NOW()
            ");
            
            $stmt->execute([
                $this->userId,
                $data['hour_of_day'],
                $data['day_of_week'],
                $data['total_sent'],
                $data['delivered'],
                $data['read_count'],
                $responses,
                $avgResponseTime,
                $engagementScore
            ]);
        }
    }
    
    /**
     * Calcula score de engajamento
     */
    private function calculateEngagementScore(int $sent, int $delivered, int $read, int $responses, float $avgResponseTime): float
    {
        if ($sent === 0) return 0;
        
        // Pesos para cada métrica
        $deliveryRate = ($delivered / $sent) * 25;  // 25% peso
        $readRate = ($read / $sent) * 30;           // 30% peso
        $responseRate = ($responses / $sent) * 35;  // 35% peso
        
        // Bonus por tempo de resposta rápido (< 5 min = 10 pontos)
        $responseTimeBonus = 0;
        if ($avgResponseTime > 0 && $avgResponseTime < 300) {
            $responseTimeBonus = 10;
        } elseif ($avgResponseTime > 0 && $avgResponseTime < 900) {
            $responseTimeBonus = 5;
        }
        
        $score = $deliveryRate + $readRate + $responseRate + $responseTimeBonus;
        
        return min(100, max(0, round($score, 2)));
    }
    
    /**
     * Obtém melhores horários para envio
     */
    public function getBestTimes(int $limit = 5): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                hour_of_day,
                day_of_week,
                engagement_score,
                total_sent,
                total_responses,
                avg_response_time
            FROM dispatch_time_analytics
            WHERE user_id = ?
            ORDER BY engagement_score DESC
            LIMIT ?
        ");
        
        $stmt->execute([$this->userId, $limit]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formatar resultados
        $dayNames = ['', 'Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
        
        return array_map(function($row) use ($dayNames) {
            return [
                'hour' => sprintf('%02d:00', $row['hour_of_day']),
                'day' => $dayNames[$row['day_of_week']],
                'day_number' => $row['day_of_week'],
                'score' => $row['engagement_score'],
                'total_sent' => $row['total_sent'],
                'responses' => $row['total_responses'],
                'avg_response_time' => $row['avg_response_time'],
                'recommendation' => $this->getRecommendationText($row['engagement_score'])
            ];
        }, $results);
    }
    
    /**
     * Obtém texto de recomendação baseado no score
     */
    private function getRecommendationText(float $score): string
    {
        if ($score >= 80) return 'Excelente';
        if ($score >= 60) return 'Muito Bom';
        if ($score >= 40) return 'Bom';
        if ($score >= 20) return 'Regular';
        return 'Baixo';
    }
    
    /**
     * Obtém heatmap de engajamento
     */
    public function getEngagementHeatmap(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                hour_of_day,
                day_of_week,
                engagement_score
            FROM dispatch_time_analytics
            WHERE user_id = ?
            ORDER BY day_of_week, hour_of_day
        ");
        
        $stmt->execute([$this->userId]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Criar matriz 7x24 (dias x horas)
        $heatmap = [];
        for ($day = 1; $day <= 7; $day++) {
            $heatmap[$day] = array_fill(0, 24, 0);
        }
        
        foreach ($data as $row) {
            $heatmap[$row['day_of_week']][$row['hour_of_day']] = $row['engagement_score'];
        }
        
        return $heatmap;
    }
    
    /**
     * Sugere próximo melhor horário
     */
    public function suggestNextBestTime(): ?array
    {
        $currentHour = (int)date('G');
        $currentDay = (int)date('w') + 1; // DAYOFWEEK é 1-7
        
        // Buscar melhores horários futuros (hoje ou próximos dias)
        $stmt = $this->pdo->prepare("
            SELECT 
                hour_of_day,
                day_of_week,
                engagement_score
            FROM dispatch_time_analytics
            WHERE user_id = ?
            AND engagement_score > 0
            ORDER BY 
                CASE 
                    WHEN day_of_week = ? AND hour_of_day > ? THEN 0
                    WHEN day_of_week > ? THEN 1
                    ELSE 2
                END,
                engagement_score DESC
            LIMIT 1
        ");
        
        $stmt->execute([$this->userId, $currentDay, $currentHour, $currentDay]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return null;
        }
        
        $dayNames = ['', 'Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
        
        return [
            'hour' => sprintf('%02d:00', $result['hour_of_day']),
            'day' => $dayNames[$result['day_of_week']],
            'score' => $result['engagement_score'],
            'is_today' => $result['day_of_week'] == $currentDay
        ];
    }
    
    /**
     * Obtém estatísticas gerais de tempo
     */
    public function getTimeStats(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                AVG(engagement_score) as avg_score,
                MAX(engagement_score) as max_score,
                MIN(engagement_score) as min_score,
                COUNT(*) as data_points
            FROM dispatch_time_analytics
            WHERE user_id = ?
        ");
        
        $stmt->execute([$this->userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
