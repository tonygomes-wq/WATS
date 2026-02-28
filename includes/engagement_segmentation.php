<?php
/**
 * Sistema de Segmentação Automática por Engajamento
 */

class EngagementSegmentation
{
    private PDO $pdo;
    private int $userId;
    
    // Thresholds para níveis de engajamento
    const LEVEL_HIGH = 80;
    const LEVEL_MEDIUM = 50;
    const LEVEL_LOW = 20;
    
    public function __construct(PDO $pdo, int $userId)
    {
        $this->pdo = $pdo;
        $this->userId = $userId;
    }
    
    /**
     * Calcula scores de engajamento para todos os contatos
     */
    public function calculateAllScores(): int
    {
        // Buscar todos os contatos do usuário
        $stmt = $this->pdo->prepare("SELECT id FROM contacts WHERE user_id = ?");
        $stmt->execute([$this->userId]);
        $contacts = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $processed = 0;
        foreach ($contacts as $contactId) {
            $this->calculateContactScore($contactId);
            $processed++;
        }
        
        return $processed;
    }
    
    /**
     * Calcula score de engajamento para um contato específico
     */
    public function calculateContactScore(int $contactId): array
    {
        // Buscar dados de dispatch para este contato
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_messages,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_count,
                MAX(sent_at) as last_sent
            FROM dispatch_history
            WHERE user_id = ? AND contact_id = ?
        ");
        $stmt->execute([$this->userId, $contactId]);
        $dispatchData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Buscar dados de resposta
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_responses,
                AVG(response_time_seconds) as avg_response_time,
                SUM(CASE WHEN sentiment = 'positive' THEN 1 ELSE 0 END) as positive,
                SUM(CASE WHEN sentiment = 'negative' THEN 1 ELSE 0 END) as negative,
                MAX(received_at) as last_response
            FROM dispatch_responses
            WHERE user_id = ? AND contact_id = ?
        ");
        $stmt->execute([$this->userId, $contactId]);
        $responseData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calcular score
        $score = $this->calculateScore($dispatchData, $responseData);
        $level = $this->getEngagementLevel($score);
        
        // Determinar última interação
        $lastInteraction = max(
            strtotime($dispatchData['last_sent'] ?? '1970-01-01'),
            strtotime($responseData['last_response'] ?? '1970-01-01')
        );
        
        // Verificar inatividade (30 dias sem interação)
        $daysSinceInteraction = (time() - $lastInteraction) / 86400;
        if ($daysSinceInteraction > 30 && $dispatchData['total_messages'] > 0) {
            $level = 'inactive';
            $score = max(0, $score - 30); // Penalidade por inatividade
        }
        
        // Salvar ou atualizar score
        $stmt = $this->pdo->prepare("
            INSERT INTO contact_engagement_scores 
            (user_id, contact_id, engagement_level, total_messages_received, total_responses_sent, 
             avg_response_time, last_interaction, positive_responses, negative_responses, score)
            VALUES (?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?), ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                engagement_level = VALUES(engagement_level),
                total_messages_received = VALUES(total_messages_received),
                total_responses_sent = VALUES(total_responses_sent),
                avg_response_time = VALUES(avg_response_time),
                last_interaction = VALUES(last_interaction),
                positive_responses = VALUES(positive_responses),
                negative_responses = VALUES(negative_responses),
                score = VALUES(score),
                last_calculated = NOW()
        ");
        
        $stmt->execute([
            $this->userId,
            $contactId,
            $level,
            $dispatchData['total_messages'] ?? 0,
            $responseData['total_responses'] ?? 0,
            $responseData['avg_response_time'] ?? 0,
            $lastInteraction > 0 ? $lastInteraction : null,
            $responseData['positive'] ?? 0,
            $responseData['negative'] ?? 0,
            $score
        ]);
        
        return [
            'contact_id' => $contactId,
            'score' => $score,
            'level' => $level,
            'total_messages' => $dispatchData['total_messages'] ?? 0,
            'total_responses' => $responseData['total_responses'] ?? 0
        ];
    }
    
    /**
     * Calcula score baseado nos dados
     */
    private function calculateScore(array $dispatch, array $response): float
    {
        $totalMessages = (int)($dispatch['total_messages'] ?? 0);
        $delivered = (int)($dispatch['delivered'] ?? 0);
        $read = (int)($dispatch['read_count'] ?? 0);
        $responses = (int)($response['total_responses'] ?? 0);
        $positive = (int)($response['positive'] ?? 0);
        $negative = (int)($response['negative'] ?? 0);
        $avgResponseTime = (float)($response['avg_response_time'] ?? 0);
        
        if ($totalMessages === 0) {
            return 50; // Score neutro para contatos sem histórico
        }
        
        $score = 0;
        
        // Taxa de entrega (20 pontos max)
        $score += ($delivered / $totalMessages) * 20;
        
        // Taxa de leitura (25 pontos max)
        $score += ($read / $totalMessages) * 25;
        
        // Taxa de resposta (30 pontos max)
        $score += ($responses / $totalMessages) * 30;
        
        // Sentimento positivo vs negativo (15 pontos max)
        if ($responses > 0) {
            $sentimentRatio = ($positive - $negative) / $responses;
            $score += (($sentimentRatio + 1) / 2) * 15; // Normaliza para 0-15
        }
        
        // Tempo de resposta rápido (10 pontos max)
        if ($avgResponseTime > 0) {
            if ($avgResponseTime < 300) { // < 5 min
                $score += 10;
            } elseif ($avgResponseTime < 900) { // < 15 min
                $score += 7;
            } elseif ($avgResponseTime < 3600) { // < 1 hora
                $score += 4;
            }
        }
        
        return min(100, max(0, round($score, 2)));
    }
    
    /**
     * Determina nível de engajamento
     */
    private function getEngagementLevel(float $score): string
    {
        if ($score >= self::LEVEL_HIGH) return 'high';
        if ($score >= self::LEVEL_MEDIUM) return 'medium';
        if ($score >= self::LEVEL_LOW) return 'low';
        return 'inactive';
    }
    
    /**
     * Obtém contatos por nível de engajamento
     */
    public function getContactsByLevel(string $level, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                ces.*,
                c.name as contact_name,
                c.phone
            FROM contact_engagement_scores ces
            JOIN contacts c ON ces.contact_id = c.id
            WHERE ces.user_id = ? AND ces.engagement_level = ?
            ORDER BY ces.score DESC
            LIMIT ?
        ");
        
        $stmt->execute([$this->userId, $level, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtém resumo de segmentação
     */
    public function getSegmentationSummary(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                engagement_level,
                COUNT(*) as count,
                AVG(score) as avg_score
            FROM contact_engagement_scores
            WHERE user_id = ?
            GROUP BY engagement_level
        ");
        
        $stmt->execute([$this->userId]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $summary = [
            'high' => ['count' => 0, 'avg_score' => 0],
            'medium' => ['count' => 0, 'avg_score' => 0],
            'low' => ['count' => 0, 'avg_score' => 0],
            'inactive' => ['count' => 0, 'avg_score' => 0]
        ];
        
        foreach ($data as $row) {
            $summary[$row['engagement_level']] = [
                'count' => (int)$row['count'],
                'avg_score' => round($row['avg_score'], 2)
            ];
        }
        
        $summary['total'] = array_sum(array_column($summary, 'count'));
        
        return $summary;
    }
    
    /**
     * Obtém top contatos engajados
     */
    public function getTopEngaged(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                ces.*,
                c.name as contact_name,
                c.phone
            FROM contact_engagement_scores ces
            JOIN contacts c ON ces.contact_id = c.id
            WHERE ces.user_id = ?
            ORDER BY ces.score DESC
            LIMIT ?
        ");
        
        $stmt->execute([$this->userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtém contatos que precisam de atenção (baixo engajamento ou inativos)
     */
    public function getNeedAttention(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                ces.*,
                c.name as contact_name,
                c.phone
            FROM contact_engagement_scores ces
            JOIN contacts c ON ces.contact_id = c.id
            WHERE ces.user_id = ? 
            AND ces.engagement_level IN ('low', 'inactive')
            AND ces.total_messages_received > 0
            ORDER BY ces.last_interaction DESC
            LIMIT ?
        ");
        
        $stmt->execute([$this->userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
