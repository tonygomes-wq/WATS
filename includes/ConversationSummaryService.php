<?php
/**
 * Conversation Summary Service
 * Serviço para geração de resumos de conversas usando IA
 * WATS - Sistema de Automação WhatsApp
 */

class ConversationSummaryService {
    private $pdo;
    private $userId;
    private $generatedBy;
    private $googleAI;
    private $logPrefix = '[SUMMARY_SERVICE]';
    
    public function __construct($pdo, int $userId, int $generatedBy) {
        $this->pdo = $pdo;
        $this->userId = $userId;
        $this->generatedBy = $generatedBy;
        
        // Carregar Google AI
        require_once __DIR__ . '/../libs/google_ai.php';
        $this->googleAI = new GoogleAI();
    }
    
    /**
     * Gera resumo de uma conversa
     */
    public function generateSummary(int $conversationId): array {
        $startTime = microtime(true);
        
        try {
            // Verificar se conversa existe e pertence ao usuário
            $conversation = $this->getConversation($conversationId);
            if (!$conversation) {
                throw new Exception('Conversa não encontrada');
            }
            
            // Verificar se já existe resumo recente (últimas 24h)
            $existingSummary = $this->getRecentSummary($conversationId);
            if ($existingSummary) {
                return [
                    'success' => true,
                    'summary' => $existingSummary,
                    'cached' => true
                ];
            }
            
            // Buscar dados da conversa
            $conversationData = $this->fetchConversationData($conversationId);
            
            // Validar mínimo de mensagens
            if ($conversationData['message_count'] < 3) {
                throw new Exception('Conversa deve ter no mínimo 3 mensagens');
            }
            
            // Criar registro de resumo
            $summaryId = $this->createSummaryRecord($conversationId, $conversationData);
            
            // Gerar prompt para IA
            $prompt = $this->buildAIPrompt($conversationData);
            
            // Chamar Google AI
            $aiResponse = $this->googleAI->generateContent($prompt, [
                'temperature' => 0.7,
                'maxOutputTokens' => 2048
            ]);
            
            if (!$aiResponse) {
                throw new Exception('Erro ao gerar resumo com IA');
            }
            
            // Processar resposta da IA
            $parsedSummary = $this->parseAIResponse($aiResponse);
            
            // Analisar sentimento
            $sentiment = $this->analyzeSentiment($conversationData['messages']);
            
            // Atualizar registro com resumo completo
            $processingTime = round((microtime(true) - $startTime) * 1000);
            $this->updateSummaryRecord($summaryId, $aiResponse, $parsedSummary, $sentiment, $processingTime);
            
            // Atualizar conversa com ID do resumo
            $this->updateConversationSummary($conversationId, $summaryId);
            
            // Retornar resumo completo
            return [
                'success' => true,
                'summary' => $this->getSummary($summaryId),
                'cached' => false
            ];
            
        } catch (Exception $e) {
            error_log("{$this->logPrefix} Erro ao gerar resumo: " . $e->getMessage());
            
            if (isset($summaryId)) {
                $this->markSummaryFailed($summaryId, $e->getMessage());
            }
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Gera resumos em lote
     */
    public function generateBatchSummaries(array $conversationIds): array {
        try {
            // Criar registro de lote
            $batchId = $this->createBatchRecord($conversationIds);
            
            $results = [];
            $completed = 0;
            $failed = 0;
            
            foreach ($conversationIds as $conversationId) {
                $result = $this->generateSummary($conversationId);
                
                if ($result['success']) {
                    $completed++;
                    $results[] = [
                        'conversation_id' => $conversationId,
                        'summary_id' => $result['summary']['id'],
                        'status' => 'completed'
                    ];
                } else {
                    $failed++;
                    $results[] = [
                        'conversation_id' => $conversationId,
                        'status' => 'failed',
                        'error' => $result['message']
                    ];
                }
                
                // Atualizar progresso do lote
                $this->updateBatchProgress($batchId, $completed, $failed);
                
                // Pequeno delay entre requisições
                usleep(500000); // 0.5s
            }
            
            // Finalizar lote
            $this->completeBatchRecord($batchId, $results);
            
            return [
                'success' => true,
                'batch_id' => $batchId,
                'total' => count($conversationIds),
                'completed' => $completed,
                'failed' => $failed,
                'results' => $results
            ];
            
        } catch (Exception $e) {
            error_log("{$this->logPrefix} Erro no lote: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtém resumo por ID
     */
    public function getSummary(int $summaryId): ?array {
        $stmt = $this->pdo->prepare("
            SELECT cs.*, 
                   cc.phone, cc.contact_name,
                   u.name as attendant_name,
                   ub.name as generated_by_name
            FROM conversation_summaries cs
            LEFT JOIN chat_conversations cc ON cs.conversation_id = cc.id
            LEFT JOIN users u ON cs.attendant_id = u.id
            LEFT JOIN users ub ON cs.generated_by = ub.id
            WHERE cs.id = ? AND cs.user_id = ?
        ");
        $stmt->execute([$summaryId, $this->userId]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($summary) {
            $summary['summary_json'] = $summary['summary_json'] ? json_decode($summary['summary_json'], true) : null;
            $summary['keywords'] = $summary['keywords'] ? json_decode($summary['keywords'], true) : [];
            $summary['topics'] = $summary['topics'] ? json_decode($summary['topics'], true) : [];
        }
        
        return $summary ?: null;
    }
    
    /**
     * Lista resumos com filtros
     */
    public function listSummaries(array $filters = []): array {
        $where = ["cs.user_id = ?"];
        $params = [$this->userId];
        
        if (!empty($filters['attendant_id'])) {
            $where[] = "cs.attendant_id = ?";
            $params[] = $filters['attendant_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "cs.generated_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "cs.generated_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "cs.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['sentiment'])) {
            $where[] = "cs.sentiment = ?";
            $params[] = $filters['sentiment'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        $stmt = $this->pdo->prepare("
            SELECT cs.*, 
                   cc.phone, cc.contact_name,
                   u.name as attendant_name
            FROM conversation_summaries cs
            LEFT JOIN chat_conversations cc ON cs.conversation_id = cc.id
            LEFT JOIN users u ON cs.attendant_id = u.id
            WHERE {$whereClause}
            ORDER BY cs.generated_at DESC
            LIMIT 100
        ");
        $stmt->execute($params);
        $summaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($summaries as &$summary) {
            $summary['summary_json'] = $summary['summary_json'] ? json_decode($summary['summary_json'], true) : null;
            $summary['keywords'] = $summary['keywords'] ? json_decode($summary['keywords'], true) : [];
        }
        
        return $summaries;
    }
    
    /**
     * Lista conversas disponíveis para resumo
     */
    public function listAvailableConversations(array $filters = []): array {
        $where = ["cc.user_id = ?"];
        $params = [$this->userId];
        $joins = "";
        
        if (!empty($filters['date_from'])) {
            $where[] = "cc.created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "cc.created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "cc.status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['has_summary'])) {
            if ($filters['has_summary']) {
                $where[] = "cc.last_summary_id IS NOT NULL";
            } else {
                $where[] = "cc.last_summary_id IS NULL";
            }
        }
        
        // Busca por palavra-chave no conteúdo das mensagens
        if (!empty($filters['keyword'])) {
            $joins = "INNER JOIN chat_messages cm_search ON cm_search.conversation_id = cc.id";
            $where[] = "cm_search.message_text LIKE ?";
            $params[] = '%' . $filters['keyword'] . '%';
        }
        
        $whereClause = implode(' AND ', $where);
        
        $stmt = $this->pdo->prepare("
            SELECT cc.id, cc.phone, cc.contact_name, cc.status, cc.created_at, cc.updated_at,
                   NULL as attendant_name,
                   COUNT(DISTINCT cm.id) as message_count,
                   cs.id as summary_id,
                   cs.sentiment as summary_sentiment
            FROM chat_conversations cc
            {$joins}
            LEFT JOIN chat_messages cm ON cm.conversation_id = cc.id
            LEFT JOIN conversation_summaries cs ON cs.id = cc.last_summary_id
            WHERE {$whereClause}
            GROUP BY cc.id
            HAVING message_count >= 3
            ORDER BY cc.updated_at DESC
            LIMIT 100
        ");
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Deleta resumo
     */
    public function deleteSummary(int $summaryId): bool {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM conversation_summaries 
                WHERE id = ? AND user_id = ?
            ");
            return $stmt->execute([$summaryId, $this->userId]);
        } catch (Exception $e) {
            error_log("{$this->logPrefix} Erro ao deletar: " . $e->getMessage());
            return false;
        }
    }
    
    // ===== MÉTODOS PRIVADOS =====
    
    private function getConversation(int $conversationId): ?array {
        $stmt = $this->pdo->prepare("
            SELECT * FROM chat_conversations 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$conversationId, $this->userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    private function getRecentSummary(int $conversationId): ?array {
        $stmt = $this->pdo->prepare("
            SELECT * FROM conversation_summaries 
            WHERE conversation_id = ? 
            AND status = 'completed'
            AND generated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY generated_at DESC
            LIMIT 1
        ");
        $stmt->execute([$conversationId]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($summary) {
            $summary['summary_json'] = $summary['summary_json'] ? json_decode($summary['summary_json'], true) : null;
            $summary['keywords'] = $summary['keywords'] ? json_decode($summary['keywords'], true) : [];
        }
        
        return $summary ?: null;
    }
    
    private function fetchConversationData(int $conversationId): array {
        // Buscar dados da conversa
        $stmt = $this->pdo->prepare("
            SELECT cc.*, 
                   c.name as contact_name
            FROM chat_conversations cc
            LEFT JOIN contacts c ON c.user_id = cc.user_id AND c.phone = cc.phone
            WHERE cc.id = ?
        ");
        $stmt->execute([$conversationId]);
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
        $conversation['attendant_name'] = 'Atendente';
        
        // Buscar mensagens
        $stmt = $this->pdo->prepare("
            SELECT * FROM chat_messages 
            WHERE conversation_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$conversationId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular duração
        $duration = 0;
        if (count($messages) > 0) {
            $firstMsg = strtotime($messages[0]['created_at']);
            $lastMsg = strtotime($messages[count($messages) - 1]['created_at']);
            $duration = $lastMsg - $firstMsg;
        }
        
        return [
            'conversation' => $conversation,
            'messages' => $messages,
            'message_count' => count($messages),
            'duration_seconds' => $duration,
            'start_time' => $messages[0]['created_at'] ?? null,
            'end_time' => $messages[count($messages) - 1]['created_at'] ?? null
        ];
    }
    
    private function buildAIPrompt(array $data): string {
        $conversation = $data['conversation'];
        $messages = $data['messages'];
        
        $contactName = $conversation['contact_name'] ?: 'Cliente';
        $attendantName = $conversation['attendant_name'] ?: 'Atendente';
        $phone = $conversation['phone'];
        $duration = gmdate("H:i:s", $data['duration_seconds']);
        $messageCount = count($messages);
        
        // Formatar histórico de mensagens
        $history = "";
        foreach ($messages as $msg) {
            $sender = $msg['from_me'] ? $attendantName : $contactName;
            $time = date('H:i', strtotime($msg['created_at']));
            $content = $this->sanitizeForAI($msg['message_text'] ?? '');
            
            if ($msg['message_type'] !== 'text') {
                $content = "[{$msg['message_type']}]" . ($content ? " - $content" : "");
            }
            
            $history .= "[$time] $sender: $content\n";
        }
        
        $prompt = <<<PROMPT
Você é um assistente especializado em análise de conversas de atendimento ao cliente via WhatsApp.

Analise a seguinte conversa e gere um resumo estruturado e profissional:

DADOS DA CONVERSA:
- Contato: {$contactName} ({$phone})
- Atendente: {$attendantName}
- Duração: {$duration}
- Total de mensagens: {$messageCount}

HISTÓRICO DE MENSAGENS:
{$history}

GERE UM RESUMO ESTRUTURADO EM FORMATO JSON COM AS SEGUINTES CHAVES:

{
  "motivo": "Descreva em 1-2 frases o motivo principal do contato",
  "acoes": ["Lista de ações realizadas pelo atendente", "Cada ação em um item"],
  "resultado": "Descreva em 1-2 frases o resultado final do atendimento",
  "sentimento_cliente": "positivo, neutro ou negativo",
  "justificativa_sentimento": "Breve justificativa do sentimento identificado",
  "pontos_atencao": ["Lista de pontos que merecem atenção", "Problemas ou oportunidades"],
  "palavras_chave": ["palavra1", "palavra2", "palavra3", "palavra4", "palavra5"]
}

IMPORTANTE:
- Seja objetivo e profissional
- Foque em insights acionáveis
- Identifique problemas e oportunidades de melhoria
- Mantenha tom neutro e factual
- Retorne APENAS o JSON, sem texto adicional
PROMPT;

        return $prompt;
    }
    
    private function parseAIResponse(string $response): array {
        // Tentar extrair JSON da resposta
        $response = trim($response);
        
        // Remover markdown code blocks se existirem
        $response = preg_replace('/```json\s*/', '', $response);
        $response = preg_replace('/```\s*$/', '', $response);
        $response = trim($response);
        
        $parsed = json_decode($response, true);
        
        if (!$parsed) {
            // Fallback: estrutura básica
            return [
                'motivo' => 'Não foi possível extrair o motivo',
                'acoes' => [],
                'resultado' => 'Não foi possível extrair o resultado',
                'sentimento_cliente' => 'neutral',
                'justificativa_sentimento' => '',
                'pontos_atencao' => [],
                'palavras_chave' => []
            ];
        }
        
        return $parsed;
    }
    
    private function analyzeSentiment(array $messages): string {
        // Análise simples de sentimento baseada em palavras-chave
        $positiveWords = ['obrigado', 'obrigada', 'agradeço', 'excelente', 'ótimo', 'perfeito', 'resolvido', 'ajudou'];
        $negativeWords = ['problema', 'ruim', 'péssimo', 'horrível', 'demora', 'lento', 'não funciona', 'insatisfeito'];
        
        $positiveCount = 0;
        $negativeCount = 0;
        
        foreach ($messages as $msg) {
            if ($msg['from_me']) continue; // Analisar apenas mensagens do cliente
            
            $text = strtolower($msg['message_text'] ?? '');
            
            foreach ($positiveWords as $word) {
                if (strpos($text, $word) !== false) {
                    $positiveCount++;
                }
            }
            
            foreach ($negativeWords as $word) {
                if (strpos($text, $word) !== false) {
                    $negativeCount++;
                }
            }
        }
        
        if ($positiveCount > $negativeCount && $positiveCount > 0) {
            return 'positive';
        } elseif ($negativeCount > $positiveCount && $negativeCount > 0) {
            return 'negative';
        } elseif ($positiveCount > 0 && $negativeCount > 0) {
            return 'mixed';
        }
        
        return 'neutral';
    }
    
    private function sanitizeForAI(string $text): string {
        // Remover/mascarar dados sensíveis
        $text = preg_replace('/\b\d{3}\.\d{3}\.\d{3}-\d{2}\b/', '[CPF]', $text); // CPF
        $text = preg_replace('/\b\d{4}\s?\d{4}\s?\d{4}\s?\d{4}\b/', '[CARTÃO]', $text); // Cartão
        $text = preg_replace('/\b\d{3}\b/', '[CVV]', $text); // CVV
        
        return $text;
    }
    
    private function createSummaryRecord(int $conversationId, array $data): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO conversation_summaries 
            (conversation_id, user_id, attendant_id, contact_id, 
             message_count, duration_seconds, start_time, end_time,
             generated_by, status)
            VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, 'processing')
        ");
        
        $stmt->execute([
            $conversationId,
            $this->userId,
            $data['conversation']['contact_id'] ?? null,
            $data['message_count'],
            $data['duration_seconds'],
            $data['start_time'],
            $data['end_time'],
            $this->generatedBy
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    private function updateSummaryRecord(int $summaryId, string $fullText, array $parsed, string $sentiment, int $processingTime): void {
        $stmt = $this->pdo->prepare("
            UPDATE conversation_summaries 
            SET summary_text = ?,
                summary_json = ?,
                sentiment = ?,
                keywords = ?,
                processing_time_ms = ?,
                status = 'completed'
            WHERE id = ?
        ");
        
        $stmt->execute([
            $fullText,
            json_encode($parsed),
            $sentiment,
            json_encode($parsed['palavras_chave'] ?? []),
            $processingTime,
            $summaryId
        ]);
    }
    
    private function markSummaryFailed(int $summaryId, string $error): void {
        $stmt = $this->pdo->prepare("
            UPDATE conversation_summaries 
            SET status = 'failed', error_message = ?
            WHERE id = ?
        ");
        $stmt->execute([$error, $summaryId]);
    }
    
    private function updateConversationSummary(int $conversationId, int $summaryId): void {
        $stmt = $this->pdo->prepare("
            UPDATE chat_conversations 
            SET last_summary_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$summaryId, $conversationId]);
    }
    
    private function createBatchRecord(array $conversationIds): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO conversation_summary_batches 
            (user_id, generated_by, total_conversations, conversation_ids, status, started_at)
            VALUES (?, ?, ?, ?, 'processing', NOW())
        ");
        
        $stmt->execute([
            $this->userId,
            $this->generatedBy,
            count($conversationIds),
            json_encode($conversationIds)
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    private function updateBatchProgress(int $batchId, int $completed, int $failed): void {
        $stmt = $this->pdo->prepare("
            UPDATE conversation_summary_batches 
            SET completed_count = ?, failed_count = ?
            WHERE id = ?
        ");
        $stmt->execute([$completed, $failed, $batchId]);
    }
    
    private function completeBatchRecord(int $batchId, array $results): void {
        $summaryIds = array_column(array_filter($results, function($r) {
            return $r['status'] === 'completed';
        }), 'summary_id');
        
        $stmt = $this->pdo->prepare("
            UPDATE conversation_summary_batches 
            SET status = 'completed', 
                completed_at = NOW(),
                summary_ids = ?
            WHERE id = ?
        ");
        
        $stmt->execute([json_encode($summaryIds), $batchId]);
    }
}
