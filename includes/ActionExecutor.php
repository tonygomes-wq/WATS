<?php
/**
 * ActionExecutor - Executor de ações de Automation Flows
 * 
 * Responsável por executar ações configuradas em automation flows:
 * - Enviar mensagens
 * - Atribuir atendentes
 * - Adicionar/remover tags
 * - Criar tarefas
 * - Chamar webhooks
 * - Atualizar campos customizados
 * 
 * Cada ação é executada com isolamento de erros, garantindo que
 * falhas em uma ação não impeçam a execução das demais.
 */

class ActionExecutor
{
    private PDO $pdo;
    private int $userId;
    private array $instanceConfig;
    
    /**
     * Construtor
     * @param PDO $pdo Conexão com banco de dados
     * @param int $userId ID do usuário proprietário dos flows
     * @param array $instanceConfig Configuração da instância (Evolution API, etc)
     */
    public function __construct(PDO $pdo, int $userId, array $instanceConfig)
    {
        $this->pdo = $pdo;
        $this->userId = $userId;
        $this->instanceConfig = $instanceConfig;
    }
    
    /**
     * Executa lista de ações em ordem
     * 
     * Itera através de todas as ações configuradas, executando cada uma
     * com isolamento de erros. Se uma ação falha, o erro é registrado
     * mas a execução continua com as próximas ações.
     * 
     * Cada resultado de ação inclui:
     * - type: Tipo da ação executada
     * - status: 'success' ou 'failed'
     * - timestamp: Unix timestamp da execução
     * - error: Mensagem de erro (se falhou)
     * - Campos específicos da ação (message_id, attendant_id, card_id, etc)
     * 
     * @param array $actions Lista de ações a executar
     * @param array $context Contexto da execução (conversa, contato, mensagem, etc)
     * @return array Resultados de todas as ações executadas
     */
    public function executeActions(array $actions, array $context): array
    {
        $results = [];
        
        // Valida se há ações para executar
        if (empty($actions)) {
            return $results;
        }
        
        // Itera através das ações em ordem
        foreach ($actions as $index => $action) {
            $actionType = $action['type'] ?? 'unknown';
            
            // Estrutura base do resultado
            $actionResult = [
                'type' => $actionType,
                'status' => 'failed',
                'timestamp' => time()
            ];
            
            try {
                // Valida estrutura básica da ação
                if (!isset($action['type'])) {
                    throw new Exception("Action type not specified");
                }
                
                if (!isset($action['config']) || !is_array($action['config'])) {
                    throw new Exception("Action config not specified or invalid");
                }
                
                // Executa a ação específica com isolamento de erros
                $executionData = $this->executeAction($action, $context);
                
                // Remove campos redundantes do executionData antes de mesclar
                unset($executionData['action']); // Já temos 'type'
                unset($executionData['status']); // Será definido abaixo
                
                // Mescla dados de execução no resultado
                // Isso inclui campos específicos como message_id, attendant_id, etc
                $actionResult = array_merge($actionResult, $executionData);
                $actionResult['status'] = 'success';
                
            } catch (Exception $e) {
                // Registra erro mas continua com próximas ações
                $actionResult['error'] = $e->getMessage();
                error_log("ActionExecutor: Error executing action {$actionType}: " . $e->getMessage());
            }
            
            $results[] = $actionResult;
        }
        
        return $results;
    }

    /**
     * Executa uma ação individual
     * 
     * Roteia a execução para o método específico baseado no tipo de ação.
     * 
     * @param array $action Dados da ação
     * @param array $context Contexto da execução
     * @return array Resultado da execução
     * @throws Exception Se o tipo de ação é desconhecido
     */
    private function executeAction(array $action, array $context): array
    {
        $type = $action['type'];
        $config = $action['config'];
        
        // Roteia para método específico baseado no tipo
        switch ($type) {
            case 'send_message':
                return $this->sendMessage($config, $context);
                
            case 'assign_attendant':
                return $this->assignAttendant($config, $context);
                
            case 'add_tag':
                return $this->addTag($config, $context);
                
            case 'remove_tag':
                return $this->removeTag($config, $context);
                
            case 'create_task':
                return $this->createTask($config, $context);
                
            case 'webhook':
                return $this->callWebhook($config, $context);
                
            case 'update_field':
                return $this->updateField($config, $context);
                
            default:
                throw new Exception("Unknown action type: {$type}");
        }
    }
    
    /**
     * Envia mensagem para o contato
     * 
     * Utiliza a Evolution API para enviar mensagem automática.
     * Suporta substituição de variáveis no texto da mensagem.
     * 
     * @param array $config Configuração da ação (message)
     * @param array $context Contexto da execução
     * @return array Resultado com message_id
     */
    private function sendMessage(array $config, array $context): array
    {
        // Valida se mensagem está configurada
        if (!isset($config['message']) || empty($config['message'])) {
            throw new Exception("Message text not configured");
        }
        
        // Extrai texto da mensagem
        $messageText = $config['message'];
        
        // Substitui variáveis no texto da mensagem
        $messageText = VariableSubstitutor::substitute($messageText, $context);
        
        // Valida se há texto após substituição
        if (empty(trim($messageText))) {
            throw new Exception("Message text is empty after variable substitution");
        }
        
        // Extrai dados necessários do contexto
        $phone = $context['phone'] ?? null;
        $conversationId = $context['conversation_id'] ?? null;
        
        if (!$phone) {
            throw new Exception("Phone number not available in context");
        }
        
        // Extrai configuração da instância Evolution
        $instanceName = $this->instanceConfig['name'] ?? null;
        $apiKey = $this->instanceConfig['api_key'] ?? null;
        
        if (!$instanceName || !$apiKey) {
            throw new Exception("Evolution API instance not configured");
        }
        
        // Formata número de telefone para padrão internacional
        $phoneFormatted = $this->formatWhatsappNumber($phone);
        
        // Prepara dados para Evolution API
        $data = [
            'number' => $phoneFormatted,
            'text' => $messageText
        ];
        
        // Chama Evolution API
        $url = EVOLUTION_API_URL . '/message/sendText/' . $instanceName;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Trata resposta da API
        if ($httpCode >= 200 && $httpCode < 300) {
            $responseData = json_decode($response, true);
            
            return [
                'action' => 'send_message',
                'status' => 'success',
                'message_id' => $responseData['key']['id'] ?? null,
                'timestamp' => $responseData['messageTimestamp'] ?? time(),
                'message_text' => $messageText
            ];
        }
        
        // Trata erro da API
        $errorMessage = 'Failed to send message via Evolution API';
        
        if (!empty($curlError)) {
            $errorMessage = 'Connection error: ' . $curlError;
        } elseif (!empty($response)) {
            $errorData = json_decode($response, true);
            $errorMessage = $errorData['message'] ?? $errorData['error'] ?? $errorMessage;
        }
        
        throw new Exception($errorMessage);
    }
    
    /**
     * Formata número de telefone para padrão WhatsApp
     * 
     * @param string $phone Número de telefone
     * @return string Número formatado
     */
    private function formatWhatsappNumber(string $phone): string
    {
        // Remove caracteres não numéricos
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Adiciona @s.whatsapp.net se não tiver
        if (!str_contains($phone, '@')) {
            $phone = $phone . '@s.whatsapp.net';
        }
        
        return $phone;
    }
    
    /**
     * Atribui atendente à conversa
     * 
     * Atribui um atendente específico à conversa e encerra
     * qualquer sessão ativa de bot.
     * 
     * @param array $config Configuração da ação (attendant_id)
     * @param array $context Contexto da execução
     * @return array Resultado da atribuição
     */
    private function assignAttendant(array $config, array $context): array
    {
        // Valida se attendant_id está configurado
        if (!isset($config['attendant_id']) || empty($config['attendant_id'])) {
            throw new Exception("Attendant ID not configured");
        }
        
        $attendantId = (int) $config['attendant_id'];
        $conversationId = $context['conversation_id'] ?? null;
        
        if (!$conversationId) {
            throw new Exception("Conversation ID not available in context");
        }
        
        try {
            // Inicia transação para garantir consistência
            $this->pdo->beginTransaction();
            
            // Busca informações do atendente
            $stmt = $this->pdo->prepare("
                SELECT id, name, 'user' as type 
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$attendantId]);
            $attendant = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$attendant) {
                throw new Exception("Attendant not found: {$attendantId}");
            }
            
            // Atualiza conversa com dados do atendente
            $stmt = $this->pdo->prepare("
                UPDATE chat_conversations 
                SET attended_by = ?,
                    attended_by_name = ?,
                    attended_by_type = ?,
                    attended_at = NOW(),
                    status = 'in_progress'
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([
                $attendantId,
                $attendant['name'],
                $attendant['type'],
                $conversationId,
                $this->userId
            ]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("Conversation not found or already assigned");
            }
            
            // Encerra qualquer sessão ativa de bot para esta conversa
            $phone = $context['phone'] ?? null;
            if ($phone) {
                $stmt = $this->pdo->prepare("
                    UPDATE bot_sessions 
                    SET status = 'completed',
                        updated_at = NOW()
                    WHERE phone = ? 
                      AND user_id = ? 
                      AND status = 'active'
                ");
                $stmt->execute([$phone, $this->userId]);
                
                $botSessionsEnded = $stmt->rowCount();
            } else {
                $botSessionsEnded = 0;
            }
            
            // Commit da transação
            $this->pdo->commit();
            
            return [
                'action' => 'assign_attendant',
                'status' => 'success',
                'attendant_id' => $attendantId,
                'attendant_name' => $attendant['name'],
                'conversation_id' => $conversationId,
                'bot_sessions_ended' => $botSessionsEnded,
                'timestamp' => time()
            ];
            
        } catch (Exception $e) {
            // Rollback em caso de erro
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
    
    /**
     * Adiciona tag à conversa
     * 
     * Adiciona uma tag específica à conversa para categorização.
     * 
     * @param array $config Configuração da ação (tag)
     * @param array $context Contexto da execução
     * @return array Resultado da operação
     */
    private function addTag(array $config, array $context): array
    {
        try {
            // Validar configuração - aceita 'tag' ou 'tag_id'
            $tag = $config['tag'] ?? $config['tag_id'] ?? null;
            
            if (empty($tag)) {
                throw new Exception('tag or tag_id is required for add_tag action');
            }
            
            $conversationId = $context['conversation_id'] ?? null;
            
            if (!$conversationId) {
                throw new Exception('conversation_id not found in context');
            }
            
            // Buscar tags atuais da conversa
            $stmt = $this->pdo->prepare("
                SELECT tags 
                FROM chat_conversations 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$conversationId, $this->userId]);
            $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$conversation) {
                throw new Exception("Conversation not found: $conversationId");
            }
            
            // Decodificar tags existentes (JSON array)
            $tags = [];
            if (!empty($conversation['tags'])) {
                $tags = json_decode($conversation['tags'], true);
                if (!is_array($tags)) {
                    $tags = [];
                }
            }
            
            // Adicionar tag se ainda não existe
            if (!in_array($tag, $tags)) {
                $tags[] = $tag;
                
                // Atualizar no banco
                $stmt = $this->pdo->prepare("
                    UPDATE chat_conversations 
                    SET tags = ? 
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([json_encode($tags), $conversationId, $this->userId]);
            }
            
            return [
                'action' => 'add_tag',
                'tag' => $tag,
                'status' => 'success',
                'tags' => $tags
            ];
            
        } catch (Exception $e) {
            error_log("ActionExecutor::addTag() Error: " . $e->getMessage());
            return [
                'action' => 'add_tag',
                'tag' => $config['tag'] ?? $config['tag_id'] ?? null,
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Remove tag da conversa
     * 
     * Remove uma tag específica da conversa.
     * 
     * @param array $config Configuração da ação (tag ou tag_id)
     * @param array $context Contexto da execução
     * @return array Resultado da operação
     */
    private function removeTag(array $config, array $context): array
    {
        try {
            // Validar configuração - aceita 'tag' ou 'tag_id'
            $tag = $config['tag'] ?? $config['tag_id'] ?? null;
            
            if (empty($tag)) {
                throw new Exception('tag or tag_id is required for remove_tag action');
            }
            
            $conversationId = $context['conversation_id'] ?? null;
            
            if (!$conversationId) {
                throw new Exception('conversation_id not found in context');
            }
            
            // Buscar tags atuais da conversa
            $stmt = $this->pdo->prepare("
                SELECT tags 
                FROM chat_conversations 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$conversationId, $this->userId]);
            $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$conversation) {
                throw new Exception("Conversation not found: $conversationId");
            }
            
            // Decodificar tags existentes (JSON array)
            $tags = [];
            if (!empty($conversation['tags'])) {
                $tags = json_decode($conversation['tags'], true);
                if (!is_array($tags)) {
                    $tags = [];
                }
            }
            
            // Remover tag se existe
            $originalCount = count($tags);
            $tags = array_values(array_filter($tags, function($t) use ($tag) {
                return $t != $tag;
            }));
            
            $removed = $originalCount > count($tags);
            
            if ($removed) {
                // Atualizar no banco
                $stmt = $this->pdo->prepare("
                    UPDATE chat_conversations 
                    SET tags = ? 
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([json_encode($tags), $conversationId, $this->userId]);
            }
            
            return [
                'action' => 'remove_tag',
                'tag' => $tag,
                'status' => 'success',
                'removed' => $removed,
                'tags' => $tags
            ];
            
        } catch (Exception $e) {
            error_log("ActionExecutor::removeTag() Error: " . $e->getMessage());
            return [
                'action' => 'remove_tag',
                'tag' => $config['tag'] ?? $config['tag_id'] ?? null,
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Cria tarefa no sistema de kanban
     * 
     * Cria uma nova tarefa relacionada à conversa no sistema de kanban.
     * 
     * @param array $config Configuração da ação (title, description, etc)
     * @param array $context Contexto da execução
     * @return array Resultado com task_id
     */
    private function createTask(array $config, array $context): array
    {
        try {
            // Validar título obrigatório
            $title = $config['title'] ?? null;
            if (empty($title)) {
                throw new Exception('Task title is required');
            }
            
            // Substituir variáveis no título e descrição
            $title = VariableSubstitutor::substitute($title, $context);
            $description = isset($config['description']) 
                ? VariableSubstitutor::substitute($config['description'], $context) 
                : null;
            
            // Extrair dados do contexto
            $conversationId = $context['conversation_id'] ?? null;
            $contactName = $context['contact_name'] ?? null;
            $contactPhone = $context['phone'] ?? null;
            
            // Extrair configurações opcionais
            $priority = $config['priority'] ?? 'normal';
            $dueDate = $config['due_date'] ?? null;
            $value = $config['value'] ?? null;
            $assignedTo = $config['assigned_to'] ?? null;
            
            // Validar prioridade
            if (!in_array($priority, ['low', 'normal', 'high', 'urgent'])) {
                $priority = 'normal';
            }
            
            // Buscar ou criar board padrão do usuário
            $stmt = $this->pdo->prepare("
                SELECT id FROM kanban_boards 
                WHERE user_id = ? 
                ORDER BY is_default DESC, id ASC 
                LIMIT 1
            ");
            $stmt->execute([$this->userId]);
            $board = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$board) {
                // Criar board padrão se não existir
                $stmt = $this->pdo->prepare("
                    INSERT INTO kanban_boards (user_id, name, description, icon, color, is_default) 
                    VALUES (?, 'Pipeline de Vendas', 'Acompanhamento de leads e oportunidades', 'fa-funnel-dollar', '#10B981', 1)
                ");
                $stmt->execute([$this->userId]);
                $boardId = $this->pdo->lastInsertId();
                
                // Criar colunas padrão
                $defaultColumns = [
                    ['Novos Leads', '#6366F1', 'fa-inbox', 0],
                    ['Em Contato', '#F59E0B', 'fa-comments', 1],
                    ['Proposta Enviada', '#3B82F6', 'fa-file-invoice', 2],
                    ['Negociação', '#8B5CF6', 'fa-handshake', 3],
                    ['Fechado/Ganho', '#10B981', 'fa-check-circle', 4],
                    ['Perdido', '#EF4444', 'fa-times-circle', 5]
                ];
                
                $stmtCol = $this->pdo->prepare("
                    INSERT INTO kanban_columns (board_id, name, color, icon, position, is_final) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($defaultColumns as $col) {
                    $isFinal = ($col[0] === 'Fechado/Ganho' || $col[0] === 'Perdido') ? 1 : 0;
                    $stmtCol->execute([$boardId, $col[0], $col[1], $col[2], $col[3], $isFinal]);
                }
            } else {
                $boardId = $board['id'];
            }
            
            // Buscar primeira coluna do board (ou coluna especificada)
            $columnId = $config['column_id'] ?? null;
            
            if (!$columnId) {
                $stmt = $this->pdo->prepare("
                    SELECT id FROM kanban_columns 
                    WHERE board_id = ? 
                    ORDER BY position ASC 
                    LIMIT 1
                ");
                $stmt->execute([$boardId]);
                $column = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$column) {
                    throw new Exception('No columns found in kanban board');
                }
                
                $columnId = $column['id'];
            } else {
                // Validar que a coluna pertence ao board do usuário
                $stmt = $this->pdo->prepare("
                    SELECT kc.id FROM kanban_columns kc
                    INNER JOIN kanban_boards kb ON kc.board_id = kb.id
                    WHERE kc.id = ? AND kb.user_id = ?
                ");
                $stmt->execute([$columnId, $this->userId]);
                
                if (!$stmt->fetch()) {
                    throw new Exception('Invalid column_id or column does not belong to user');
                }
            }
            
            // Obter próxima posição na coluna
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(MAX(position), -1) + 1 as next_pos 
                FROM kanban_cards 
                WHERE column_id = ?
            ");
            $stmt->execute([$columnId]);
            $nextPos = $stmt->fetch(PDO::FETCH_ASSOC)['next_pos'];
            
            // Inserir card no kanban
            $stmt = $this->pdo->prepare("
                INSERT INTO kanban_cards (
                    column_id, conversation_id, title, description,
                    contact_name, contact_phone, assigned_to, assigned_type,
                    priority, due_date, value, position, created_by, source_channel
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $assignedType = $assignedTo ? 'attendant' : null;
            $sourceChannel = $context['channel'] ?? 'automation';
            
            $stmt->execute([
                $columnId,
                $conversationId,
                $title,
                $description,
                $contactName,
                $contactPhone,
                $assignedTo,
                $assignedType,
                $priority,
                $dueDate,
                $value,
                $nextPos,
                $this->userId,
                $sourceChannel
            ]);
            
            $cardId = $this->pdo->lastInsertId();
            
            // Adicionar labels se especificadas
            if (!empty($config['labels']) && is_array($config['labels'])) {
                $stmtLabel = $this->pdo->prepare("
                    INSERT INTO kanban_card_labels (card_id, label_id) 
                    VALUES (?, ?)
                ");
                
                foreach ($config['labels'] as $labelId) {
                    try {
                        $stmtLabel->execute([$cardId, $labelId]);
                    } catch (PDOException $e) {
                        // Ignorar erro de label inválida, continuar com outras
                        error_log("ActionExecutor: Failed to add label {$labelId} to card {$cardId}: " . $e->getMessage());
                    }
                }
            }
            
            return [
                'action' => 'create_task',
                'status' => 'success',
                'card_id' => $cardId,
                'board_id' => $boardId,
                'column_id' => $columnId,
                'title' => $title,
                'conversation_id' => $conversationId,
                'timestamp' => time()
            ];
            
        } catch (Exception $e) {
            error_log("ActionExecutor::createTask() Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Chama webhook externo
     * 
     * Envia dados da conversa para um webhook externo configurado.
     * Suporta timeout configurável (padrão 10 segundos).
     * 
     * @param array $config Configuração da ação (url, method, timeout)
     * @param array $context Contexto da execução
     * @return array Resultado da chamada
     */
    private function callWebhook(array $config, array $context): array
    {
        // Valida se URL está configurada
        if (!isset($config['url']) || empty($config['url'])) {
            throw new Exception("Webhook URL not configured");
        }
        
        $url = $config['url'];
        
        // Valida formato da URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new Exception("Invalid webhook URL format");
        }
        
        // Extrai método HTTP (padrão: POST)
        $method = strtoupper($config['method'] ?? 'POST');
        
        // Valida método HTTP
        if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH'])) {
            throw new Exception("Invalid HTTP method: {$method}");
        }
        
        // Extrai timeout (padrão: 10 segundos)
        $timeout = isset($config['timeout']) ? (int) $config['timeout'] : 10;
        
        // Valida timeout
        if ($timeout < 1 || $timeout > 60) {
            $timeout = 10; // Força padrão se inválido
        }
        
        // Prepara payload com dados da conversa
        $payload = [
            'conversation_id' => $context['conversation_id'] ?? null,
            'contact' => [
                'name' => $context['contact_name'] ?? null,
                'phone' => $context['phone'] ?? null,
                'email' => $context['contact_email'] ?? null
            ],
            'message' => [
                'text' => $context['message'] ?? null,
                'timestamp' => $context['timestamp'] ?? time(),
                'message_id' => $context['message_id'] ?? null
            ],
            'channel' => $context['channel'] ?? null,
            'ai_response' => $context['ai_response'] ?? null,
            'metadata' => [
                'user_id' => $this->userId,
                'triggered_at' => date('c')
            ]
        ];
        
        // Substitui variáveis na URL se necessário
        $url = VariableSubstitutor::substitute($url, $context);
        
        // Prepara requisição cURL
        $ch = curl_init();
        
        // Configura URL e método
        if ($method === 'GET') {
            // Para GET, adiciona payload como query string
            $queryString = http_build_query($payload);
            $url = $url . (str_contains($url, '?') ? '&' : '?') . $queryString;
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        } else {
            // Para POST/PUT/PATCH, envia payload como JSON no body
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'User-Agent: WATS-AutomationEngine/1.0'
            ]);
        }
        
        // Configura opções comuns
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min($timeout, 5));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        
        // Executa requisição
        $startTime = microtime(true);
        $response = curl_exec($ch);
        $executionTime = round((microtime(true) - $startTime) * 1000); // em ms
        
        // Captura informações da resposta
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        
        curl_close($ch);
        
        // Trata timeout
        if ($curlErrno === CURLE_OPERATION_TIMEDOUT || $curlErrno === CURLE_OPERATION_TIMEOUTED) {
            throw new Exception("Webhook request timeout after {$timeout} seconds");
        }
        
        // Trata erro de conexão
        if ($curlErrno !== 0) {
            throw new Exception("Connection error: {$curlError} (code: {$curlErrno})");
        }
        
        // Trata resposta HTTP
        $success = ($httpCode >= 200 && $httpCode < 300);
        
        if (!$success) {
            // Tenta extrair mensagem de erro da resposta
            $errorMessage = "HTTP {$httpCode}";
            
            if (!empty($response)) {
                $responseData = json_decode($response, true);
                if (isset($responseData['error'])) {
                    $errorMessage .= ": " . $responseData['error'];
                } elseif (isset($responseData['message'])) {
                    $errorMessage .= ": " . $responseData['message'];
                }
            }
            
            throw new Exception("Webhook returned error: {$errorMessage}");
        }
        
        // Sucesso - retorna resultado
        return [
            'action' => 'webhook',
            'status' => 'success',
            'url' => $url,
            'method' => $method,
            'http_code' => $httpCode,
            'execution_time_ms' => $executionTime,
            'response' => $response ? substr($response, 0, 500) : null, // Limita resposta a 500 chars
            'timestamp' => time()
        ];
    }
    
    /**
     * Atualiza campo customizado do contato
     * 
     * Atualiza um campo customizado específico no registro do contato.
     * Os campos customizados são armazenados em uma coluna JSON chamada 'custom_fields'.
     * 
     * @param array $config Configuração da ação (field, value)
     * @param array $context Contexto da execução
     * @return array Resultado da atualização
     */
    private function updateField(array $config, array $context): array
    {
        try {
            // Validar configuração obrigatória
            $field = $config['field'] ?? null;
            
            if (empty($field)) {
                throw new Exception('Field name is required for update_field action');
            }
            
            // Validar que o campo não é vazio ou inválido
            if (!is_string($field) || strlen(trim($field)) === 0) {
                throw new Exception('Field name must be a non-empty string');
            }
            
            // Extrair valor (pode ser null para limpar o campo)
            $value = $config['value'] ?? null;
            
            // Substituir variáveis no valor se for string
            if (is_string($value)) {
                $value = VariableSubstitutor::substitute($value, $context);
            }
            
            // Extrair contact_id do contexto
            $contactId = $context['contact_id'] ?? null;
            $phone = $context['phone'] ?? null;
            
            // Se não temos contact_id, tentar buscar pelo telefone
            if (!$contactId && $phone) {
                $stmt = $this->pdo->prepare("
                    SELECT id 
                    FROM contacts 
                    WHERE user_id = ? AND phone = ?
                    LIMIT 1
                ");
                $stmt->execute([$this->userId, $phone]);
                $contact = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($contact) {
                    $contactId = $contact['id'];
                } else {
                    throw new Exception("Contact not found for phone: {$phone}");
                }
            }
            
            if (!$contactId) {
                throw new Exception('contact_id not found in context and could not be resolved');
            }
            
            // Verificar se a coluna custom_fields existe na tabela contacts
            $stmt = $this->pdo->query("
                SELECT COLUMN_NAME 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'contacts' 
                  AND COLUMN_NAME = 'custom_fields'
            ");
            
            $columnExists = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Se a coluna não existe, criar automaticamente
            if (!$columnExists) {
                $this->pdo->exec("
                    ALTER TABLE contacts 
                    ADD COLUMN custom_fields JSON DEFAULT NULL
                    AFTER email
                ");
                
                error_log("ActionExecutor: Created custom_fields column in contacts table");
            }
            
            // Buscar custom_fields atuais do contato
            $stmt = $this->pdo->prepare("
                SELECT custom_fields 
                FROM contacts 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$contactId, $this->userId]);
            $contact = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$contact) {
                throw new Exception("Contact not found: {$contactId}");
            }
            
            // Decodificar custom_fields existentes (JSON object)
            $customFields = [];
            if (!empty($contact['custom_fields'])) {
                $customFields = json_decode($contact['custom_fields'], true);
                if (!is_array($customFields)) {
                    $customFields = [];
                }
            }
            
            // Armazenar valor anterior para retorno
            $oldValue = $customFields[$field] ?? null;
            
            // Atualizar ou remover campo
            if ($value === null || $value === '') {
                // Remover campo se valor é null ou vazio
                unset($customFields[$field]);
                $operation = 'removed';
            } else {
                // Atualizar campo com novo valor
                $customFields[$field] = $value;
                $operation = 'updated';
            }
            
            // Salvar de volta no banco
            $stmt = $this->pdo->prepare("
                UPDATE contacts 
                SET custom_fields = ?,
                    updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([
                json_encode($customFields, JSON_UNESCAPED_UNICODE),
                $contactId,
                $this->userId
            ]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("Failed to update contact custom fields");
            }
            
            return [
                'action' => 'update_field',
                'status' => 'success',
                'contact_id' => $contactId,
                'field' => $field,
                'value' => $value,
                'old_value' => $oldValue,
                'operation' => $operation,
                'timestamp' => time()
            ];
            
        } catch (Exception $e) {
            error_log("ActionExecutor::updateField() Error: " . $e->getMessage());
            throw $e;
        }
    }
}
