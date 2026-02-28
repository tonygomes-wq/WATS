<?php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/libs/google_ai.php';

class SentimentAnalyzer
{
    private PDO $pdo;
    
    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? $GLOBALS['pdo'];
    }
    
    /**
     * Analisa o sentimento de um texto usando Google AI
     */
    public function analyzeSentiment(string $text): string
    {
        if (empty(trim($text))) {
            return 'unknown';
        }
        
        try {
            $prompt = "Analise o sentimento desta mensagem de WhatsApp e responda APENAS com uma palavra: positivo, negativo ou neutro.\n\nMensagem: {$text}";
            
            $response = callGoogleAI($prompt, [
                'temperature' => 0.1,
                'maxOutputTokens' => 10
            ]);
            
            $sentiment = strtolower(trim($response));
            
            // Mapear resposta para valores aceitos
            if (strpos($sentiment, 'positiv') !== false) {
                return 'positive';
            } elseif (strpos($sentiment, 'negativ') !== false) {
                return 'negative';
            } elseif (strpos($sentiment, 'neutr') !== false) {
                return 'neutral';
            }
            
            return 'unknown';
        } catch (Exception $e) {
            error_log('Erro ao analisar sentimento: ' . $e->getMessage());
            return 'unknown';
        }
    }
    
    /**
     * Processa sentimento de uma resposta específica
     */
    public function processResponseSentiment(int $responseId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT message_text, sentiment 
                FROM dispatch_responses 
                WHERE id = ?
            ");
            $stmt->execute([$responseId]);
            $response = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$response) {
                return false;
            }
            
            // Não processar se já foi analisado
            if ($response['sentiment'] !== 'unknown') {
                return true;
            }
            
            $sentiment = $this->analyzeSentiment($response['message_text']);
            
            $stmt = $this->pdo->prepare("
                UPDATE dispatch_responses 
                SET sentiment = ?, processed = 1 
                WHERE id = ?
            ");
            $stmt->execute([$sentiment, $responseId]);
            
            return true;
        } catch (Exception $e) {
            error_log('Erro ao processar sentimento da resposta: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Processa sentimentos pendentes em lote
     */
    public function processPendingSentiments(int $limit = 50): int
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, message_text 
                FROM dispatch_responses 
                WHERE sentiment = 'unknown' 
                AND processed = 0
                AND message_text IS NOT NULL
                AND message_text != ''
                ORDER BY received_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $processed = 0;
            foreach ($responses as $response) {
                $sentiment = $this->analyzeSentiment($response['message_text']);
                
                $updateStmt = $this->pdo->prepare("
                    UPDATE dispatch_responses 
                    SET sentiment = ?, processed = 1 
                    WHERE id = ?
                ");
                $updateStmt->execute([$sentiment, $response['id']]);
                
                $processed++;
                
                // Pequeno delay para não sobrecarregar a API
                usleep(100000); // 100ms
            }
            
            return $processed;
        } catch (Exception $e) {
            error_log('Erro ao processar sentimentos em lote: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Obtém estatísticas de sentimento para um usuário
     */
    public function getUserSentimentStats(int $userId, ?int $campaignId = null): array
    {
        try {
            $sql = "
                SELECT 
                    sentiment,
                    COUNT(*) as count,
                    AVG(response_time_seconds) as avg_response_time
                FROM dispatch_responses
                WHERE user_id = ?
            ";
            
            $params = [$userId];
            
            if ($campaignId) {
                $sql .= " AND campaign_id = ?";
                $params[] = $campaignId;
            }
            
            $sql .= " GROUP BY sentiment";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stats = [
                'positive' => ['count' => 0, 'avg_response_time' => 0],
                'neutral' => ['count' => 0, 'avg_response_time' => 0],
                'negative' => ['count' => 0, 'avg_response_time' => 0],
                'unknown' => ['count' => 0, 'avg_response_time' => 0],
                'total' => 0
            ];
            
            foreach ($results as $result) {
                $sentiment = $result['sentiment'];
                $stats[$sentiment] = [
                    'count' => (int)$result['count'],
                    'avg_response_time' => round($result['avg_response_time'] ?? 0)
                ];
                $stats['total'] += (int)$result['count'];
            }
            
            return $stats;
        } catch (Exception $e) {
            error_log('Erro ao obter estatísticas de sentimento: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Analisa sentimento em tempo real (para uso imediato)
     */
    public function analyzeRealtime(string $text): array
    {
        $sentiment = $this->analyzeSentiment($text);
        
        $labels = [
            'positive' => 'Positivo',
            'neutral' => 'Neutro',
            'negative' => 'Negativo',
            'unknown' => 'Desconhecido'
        ];
        
        $icons = [
            'positive' => '😊',
            'neutral' => '😐',
            'negative' => '😞',
            'unknown' => '❓'
        ];
        
        $colors = [
            'positive' => 'green',
            'neutral' => 'gray',
            'negative' => 'red',
            'unknown' => 'gray'
        ];
        
        return [
            'sentiment' => $sentiment,
            'label' => $labels[$sentiment] ?? 'Desconhecido',
            'icon' => $icons[$sentiment] ?? '❓',
            'color' => $colors[$sentiment] ?? 'gray'
        ];
    }
}
