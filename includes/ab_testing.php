<?php
/**
 * Sistema de A/B Testing para Mensagens
 */

class ABTesting
{
    private PDO $pdo;
    private int $userId;
    
    public function __construct(PDO $pdo, int $userId)
    {
        $this->pdo = $pdo;
        $this->userId = $userId;
    }
    
    /**
     * Cria um novo teste A/B
     */
    public function createTest(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO ab_tests 
            (user_id, name, description, variant_a_message, variant_b_message, status)
            VALUES (?, ?, ?, ?, ?, 'draft')
        ");
        
        $stmt->execute([
            $this->userId,
            $data['name'],
            $data['description'] ?? '',
            $data['variant_a_message'],
            $data['variant_b_message']
        ]);
        
        return (int)$this->pdo->lastInsertId();
    }
    
    /**
     * Inicia um teste A/B
     */
    public function startTest(int $testId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE ab_tests 
            SET status = 'running', started_at = NOW()
            WHERE id = ? AND user_id = ? AND status = 'draft'
        ");
        
        return $stmt->execute([$testId, $this->userId]) && $stmt->rowCount() > 0;
    }
    
    /**
     * Obtém variante para um contato (distribuição 50/50)
     */
    public function getVariantForContact(int $testId, int $contactId): string
    {
        // Usar hash do contactId para distribuição consistente
        $hash = crc32($testId . '_' . $contactId);
        return ($hash % 2 === 0) ? 'a' : 'b';
    }
    
    /**
     * Obtém mensagem da variante
     */
    public function getVariantMessage(int $testId, string $variant): ?string
    {
        $column = $variant === 'a' ? 'variant_a_message' : 'variant_b_message';
        
        $stmt = $this->pdo->prepare("
            SELECT {$column} FROM ab_tests
            WHERE id = ? AND user_id = ? AND status = 'running'
        ");
        
        $stmt->execute([$testId, $this->userId]);
        return $stmt->fetchColumn() ?: null;
    }
    
    /**
     * Registra envio de variante
     */
    public function recordSend(int $testId, int $dispatchId, string $variant): void
    {
        // Atualizar contador no teste
        $column = $variant === 'a' ? 'variant_a_sent' : 'variant_b_sent';
        
        $stmt = $this->pdo->prepare("
            UPDATE ab_tests SET {$column} = {$column} + 1
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$testId, $this->userId]);
        
        // Registrar resultado individual
        $stmt = $this->pdo->prepare("
            INSERT INTO ab_test_results 
            (test_id, dispatch_id, variant)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$testId, $dispatchId, $variant]);
    }
    
    /**
     * Registra resposta para uma variante
     */
    public function recordResponse(int $dispatchId, string $sentiment): void
    {
        // Buscar teste e variante associados
        $stmt = $this->pdo->prepare("
            SELECT test_id, variant FROM ab_test_results
            WHERE dispatch_id = ?
        ");
        $stmt->execute([$dispatchId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) return;
        
        $testId = $result['test_id'];
        $variant = $result['variant'];
        
        // Atualizar resultado individual
        $stmt = $this->pdo->prepare("
            UPDATE ab_test_results 
            SET response_received = TRUE, response_sentiment = ?
            WHERE dispatch_id = ?
        ");
        $stmt->execute([$sentiment, $dispatchId]);
        
        // Atualizar contadores do teste
        $responseColumn = $variant === 'a' ? 'variant_a_responses' : 'variant_b_responses';
        $positiveColumn = $variant === 'a' ? 'variant_a_positive' : 'variant_b_positive';
        
        $sql = "UPDATE ab_tests SET {$responseColumn} = {$responseColumn} + 1";
        if ($sentiment === 'positive') {
            $sql .= ", {$positiveColumn} = {$positiveColumn} + 1";
        }
        $sql .= " WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$testId]);
        
        // Verificar se deve finalizar o teste
        $this->checkTestCompletion($testId);
    }
    
    /**
     * Verifica se o teste deve ser finalizado
     */
    private function checkTestCompletion(int $testId): void
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM ab_tests WHERE id = ?
        ");
        $stmt->execute([$testId]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$test || $test['status'] !== 'running') return;
        
        $totalSent = $test['variant_a_sent'] + $test['variant_b_sent'];
        $totalResponses = $test['variant_a_responses'] + $test['variant_b_responses'];
        
        // Critérios para finalização automática:
        // 1. Mínimo de 100 envios por variante
        // 2. Mínimo de 20 respostas por variante
        // 3. Ou 7 dias desde o início
        
        $daysSinceStart = (time() - strtotime($test['started_at'])) / 86400;
        
        $shouldComplete = (
            ($test['variant_a_sent'] >= 100 && $test['variant_b_sent'] >= 100 &&
             $test['variant_a_responses'] >= 20 && $test['variant_b_responses'] >= 20) ||
            $daysSinceStart >= 7
        );
        
        if ($shouldComplete) {
            $this->completeTest($testId);
        }
    }
    
    /**
     * Finaliza um teste e determina vencedor
     */
    public function completeTest(int $testId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ab_tests WHERE id = ? AND user_id = ?");
        $stmt->execute([$testId, $this->userId]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$test) {
            throw new Exception('Teste não encontrado');
        }
        
        // Calcular taxas de resposta
        $rateA = $test['variant_a_sent'] > 0 
            ? ($test['variant_a_responses'] / $test['variant_a_sent']) * 100 
            : 0;
        $rateB = $test['variant_b_sent'] > 0 
            ? ($test['variant_b_responses'] / $test['variant_b_sent']) * 100 
            : 0;
        
        // Calcular taxas de sentimento positivo
        $positiveRateA = $test['variant_a_responses'] > 0
            ? ($test['variant_a_positive'] / $test['variant_a_responses']) * 100
            : 0;
        $positiveRateB = $test['variant_b_responses'] > 0
            ? ($test['variant_b_positive'] / $test['variant_b_responses']) * 100
            : 0;
        
        // Score combinado (70% taxa de resposta + 30% sentimento positivo)
        $scoreA = ($rateA * 0.7) + ($positiveRateA * 0.3);
        $scoreB = ($rateB * 0.7) + ($positiveRateB * 0.3);
        
        // Determinar vencedor
        $winner = 'tie';
        $confidence = 0;
        
        if ($scoreA > $scoreB * 1.1) { // 10% de margem
            $winner = 'a';
            $confidence = min(100, (($scoreA - $scoreB) / $scoreB) * 100);
        } elseif ($scoreB > $scoreA * 1.1) {
            $winner = 'b';
            $confidence = min(100, (($scoreB - $scoreA) / $scoreA) * 100);
        }
        
        // Atualizar teste
        $stmt = $this->pdo->prepare("
            UPDATE ab_tests 
            SET status = 'completed', winner = ?, confidence_level = ?, completed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$winner, $confidence, $testId]);
        
        return [
            'winner' => $winner,
            'confidence' => round($confidence, 2),
            'variant_a' => [
                'sent' => $test['variant_a_sent'],
                'responses' => $test['variant_a_responses'],
                'response_rate' => round($rateA, 2),
                'positive_rate' => round($positiveRateA, 2),
                'score' => round($scoreA, 2)
            ],
            'variant_b' => [
                'sent' => $test['variant_b_sent'],
                'responses' => $test['variant_b_responses'],
                'response_rate' => round($rateB, 2),
                'positive_rate' => round($positiveRateB, 2),
                'score' => round($scoreB, 2)
            ]
        ];
    }
    
    /**
     * Lista testes do usuário
     */
    public function listTests(string $status = null, int $limit = 20): array
    {
        $sql = "SELECT * FROM ab_tests WHERE user_id = ?";
        $params = [$this->userId];
        
        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtém detalhes de um teste
     */
    public function getTest(int $testId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM ab_tests WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$testId, $this->userId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Obtém resultados detalhados de um teste
     */
    public function getTestResults(int $testId): array
    {
        $test = $this->getTest($testId);
        if (!$test) {
            throw new Exception('Teste não encontrado');
        }
        
        // Buscar resultados individuais
        $stmt = $this->pdo->prepare("
            SELECT 
                variant,
                response_received,
                response_sentiment,
                COUNT(*) as count
            FROM ab_test_results
            WHERE test_id = ?
            GROUP BY variant, response_received, response_sentiment
        ");
        $stmt->execute([$testId]);
        $breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'test' => $test,
            'breakdown' => $breakdown
        ];
    }
    
    /**
     * Cancela um teste
     */
    public function cancelTest(int $testId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE ab_tests 
            SET status = 'cancelled'
            WHERE id = ? AND user_id = ? AND status IN ('draft', 'running')
        ");
        
        return $stmt->execute([$testId, $this->userId]) && $stmt->rowCount() > 0;
    }
}
