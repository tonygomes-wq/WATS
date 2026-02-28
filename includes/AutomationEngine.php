<?php
/**
 * AutomationEngine - Motor de execução de Automation Flows
 * 
 * Responsável por orquestrar a execução de automation flows, incluindo:
 * - Avaliação de triggers
 * - Processamento de IA
 * - Execução de ações
 * - Registro de logs
 */

class AutomationEngine
{
    private PDO $pdo;
    private int $userId;
    private array $instanceConfig;
    
    /**
     * Construtor
     * @param PDO $pdo Conexão com banco de dados
     * @param int $userId ID do usuário proprietário dos flows
     */
    public function __construct(PDO $pdo, int $userId)
    {
        $this->pdo = $pdo;
        $this->userId = $userId;
        $this->instanceConfig = $this->loadInstanceConfig();
    }
    
    /**
     * Carrega configuração da instância do usuário
     * @return array Configuração da instância
     */
    private function loadInstanceConfig(): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT name, api_url, api_key, channel_type 
                FROM user_instances 
                WHERE user_id = ? AND status = 'active'
                LIMIT 1
            ");
            $stmt->execute([$this->userId]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $config ?: [];
        } catch (Exception $e) {
            error_log("AutomationEngine: Error loading instance config: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Verifica se há sessão ativa de bot para o telefone
     * @param string $phone Telefone do contato
     * @return bool
     */
    private function hasActiveBotSession(string $phone): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM bot_sessions 
                WHERE phone = ? AND status = 'active' AND user_id = ?
            ");
            $stmt->execute([$phone, $this->userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] > 0;
        } catch (Exception $e) {
            error_log("AutomationEngine: Error checking bot session: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verifica se conversa está em atendimento humano
     * @param int $conversationId ID da conversa
     * @return bool
     */
    private function isInHumanAttendance(int $conversationId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT attendant_id, status 
                FROM chat_conversations 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$conversationId, $this->userId]);
            $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$conversation) {
                return false;
            }
            
            // Considera em atendimento humano se tem atendente atribuído e status é 'open'
            return !empty($conversation['attendant_id']) && $conversation['status'] === 'open';
        } catch (Exception $e) {
            error_log("AutomationEngine: Error checking human attendance: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Carrega flows ativos do usuário
     * @return array Lista de flows
     */
    private function loadActiveFlows(): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM automation_flows 
                WHERE user_id = ? AND status = 'active'
                ORDER BY id ASC
            ");
            $stmt->execute([$this->userId]);
            $flows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decodifica campos JSON
            foreach ($flows as &$flow) {
                $flow['trigger_config'] = json_decode($flow['trigger_config'] ?? '{}', true);
                $flow['agent_config'] = json_decode($flow['agent_config'] ?? '{}', true);
                $flow['action_config'] = json_decode($flow['action_config'] ?? '{}', true);
            }
            
            return $flows;
        } catch (Exception $e) {
            error_log("AutomationEngine: Error loading active flows: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Executa um flow específico manualmente
     * @param int $flowId ID do flow
     * @param int $conversationId ID da conversa
     * @param array $context Contexto da execução
     * @return array Resultado da execução
     */
    public function executeFlow(
        int $flowId, 
        int $conversationId, 
        array $context = []
    ): array
    {
        $startTime = microtime(true);
        $result = [
            'success' => false,
            'flow_id' => $flowId,
            'conversation_id' => $conversationId,
            'error' => null
        ];
        
        try {
            // Carrega o flow
            $stmt = $this->pdo->prepare("
                SELECT * FROM automation_flows 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$flowId, $this->userId]);
            $flow = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Valida se o flow existe e pertence ao usuário
            if (!$flow) {
                throw new Exception("Flow not found or access denied");
            }
            
            // Decodifica campos JSON
            $flow['trigger_config'] = json_decode($flow['trigger_config'] ?? '{}', true);
            $flow['agent_config'] = json_decode($flow['agent_config'] ?? '{}', true);
            $flow['action_config'] = json_decode($flow['action_config'] ?? '{}', true);
            
            // Carrega dados da conversa
            $stmt = $this->pdo->prepare("
                SELECT c.*, co.name as contact_name, co.phone as contact_phone, co.email as contact_email
                FROM chat_conversations c
                LEFT JOIN contacts co ON c.contact_id = co.id
                WHERE c.id = ? AND c.user_id = ?
            ");
            $stmt->execute([$conversationId, $this->userId]);
            $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$conversation) {
                throw new Exception("Conversation not found or access denied");
            }
            
            // Prepara contexto completo
            $fullContext = array_merge([
                'conversation_id' => $conversationId,
                'phone' => $conversation['contact_phone'],
                'contact_id' => $conversation['contact_id'],
                'contact_name' => $conversation['contact_name'] ?? '',
                'contact_email' => $conversation['contact_email'] ?? '',
                'channel' => $conversation['channel'] ?? 'whatsapp',
                'timestamp' => time(),
                'message' => $context['message'] ?? '',
                'is_manual_execution' => true
            ], $context);
            
            // Executa o flow
            $flowResult = $this->executeFlowInternal($flow, $fullContext);
            
            $result['success'] = $flowResult['status'] === 'success';
            $result['flow_name'] = $flow['name'];
            $result['ai_response'] = $flowResult['ai_response'];
            $result['actions_executed'] = $flowResult['actions_executed'];
            $result['execution_time_ms'] = round((microtime(true) - $startTime) * 1000);
            
            if ($flowResult['status'] === 'failed') {
                $result['error'] = $flowResult['error'] ?? 'Unknown error';
            }
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            error_log("AutomationEngine: Error in executeFlow: " . $e->getMessage());
            
            // Registra erro no log
            $this->logExecution(
                $flowId,
                $conversationId,
                'failed',
                [
                    'trigger_payload' => [
                        'manual_execution' => true,
                        'timestamp' => time()
                    ],
                    'error_message' => $e->getMessage(),
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000)
                ]
            );
        }
        
        return $result;
    }
    
    /**
     * Executa um flow específico (método interno)
     * @param array $flow Dados do flow
     * @param array $context Contexto da execução
     * @return array Resultado
     */
    private function executeFlowInternal(array $flow, array $context): array
    {
        $startTime = microtime(true);
        $result = [
            'flow_id' => $flow['id'],
            'flow_name' => $flow['name'],
            'status' => 'success',
            'ai_response' => null,
            'actions_executed' => []
        ];
        
        try {
            // TODO: Processar IA (será implementado em AIProcessor)
            // TODO: Executar ações (será implementado em ActionExecutor)
            
            $executionTimeMs = round((microtime(true) - $startTime) * 1000);
            
            // Registra execução no log
            $this->logExecution(
                $flow['id'],
                $context['conversation_id'] ?? 0,
                'success',
                [
                    'trigger_payload' => [
                        'message' => $context['message'] ?? '',
                        'phone' => $context['phone'] ?? '',
                        'timestamp' => $context['timestamp'] ?? time(),
                        'channel' => $context['channel'] ?? 'whatsapp',
                        'trigger_type' => $flow['trigger_type'],
                        'is_manual' => $context['is_manual_execution'] ?? false
                    ],
                    'agent_prompt' => null,
                    'agent_response' => null,
                    'action_results' => [],
                    'execution_time_ms' => $executionTimeMs
                ]
            );
            
        } catch (Exception $e) {
            $result['status'] = 'failed';
            $result['error'] = $e->getMessage();
            
            // Registra erro no log
            $this->logExecution(
                $flow['id'],
                $context['conversation_id'] ?? 0,
                'failed',
                [
                    'trigger_payload' => [
                        'message' => $context['message'] ?? '',
                        'phone' => $context['phone'] ?? ''
                    ],
                    'error_message' => $e->getMessage()
                ]
            );
        }
        
        return $result;
    }
    
    /**
     * Registra execução no log
     * @param int $flowId ID do flow
     * @param int $conversationId ID da conversa
     * @param string $status Status da execução
     * @param array $data Dados da execução
     * @return int ID do log criado
     */
    private function logExecution(
        int $flowId, 
        int $conversationId, 
        string $status, 
        array $data
    ): int
    {
        try {
            // Inicia transação
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO automation_flow_logs (
                    flow_id,
                    conversation_id,
                    trigger_payload,
                    agent_prompt,
                    agent_response,
                    action_results,
                    status,
                    error_message,
                    execution_time_ms,
                    executed_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $flowId,
                $conversationId,
                json_encode($data['trigger_payload'] ?? []),
                $data['agent_prompt'] ?? null,
                $data['agent_response'] ?? null,
                json_encode($data['action_results'] ?? []),
                $status,
                $data['error_message'] ?? null,
                $data['execution_time_ms'] ?? 0
            ]);
            
            $logId = $this->pdo->lastInsertId();
            
            // Commit da transação
            $this->pdo->commit();
            
            return $logId;
            
        } catch (Exception $e) {
            // Rollback em caso de erro
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            
            error_log("AutomationEngine: Error logging execution: " . $e->getMessage());
            return 0;
        }
    }
}