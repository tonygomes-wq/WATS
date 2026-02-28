<?php
/**
 * BOT ENGINE - Motor de Execução de Fluxos
 * WATS - Sistema de Automação WhatsApp
 * 
 * Este arquivo é responsável por:
 * - Gerenciar sessões de bot por contato
 * - Executar nós do fluxo sequencialmente
 * - Processar inputs do usuário
 * - Enviar mensagens via Evolution API
 * - Transferir para atendimento humano
 */

class BotEngine {
    private $pdo;
    private $userId;
    private $instanceName;
    private $evolutionApiUrl;
    private $evolutionApiKey;
    private $logPrefix = '[BOT_ENGINE]';
    
    public function __construct($pdo, $userId) {
        $this->pdo = $pdo;
        $this->userId = $userId;
        $this->loadInstanceConfig();
    }
    
    /**
     * Carrega configurações da instância Evolution API
     * Suporta tanto supervisores quanto atendentes com instância própria
     */
    private function loadInstanceConfig() {
        // Primeiro, tentar buscar como supervisor na tabela users
        $stmt = $this->pdo->prepare("
            SELECT 
                evolution_instance as name, 
                evolution_api_url as api_url, 
                evolution_token as api_key,
                'supervisor' as user_type
            FROM users 
            WHERE id = ? 
            LIMIT 1
        ");
        $stmt->execute([$this->userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && !empty($user['name'])) {
            $this->instanceName = $user['name'];
            $this->evolutionApiUrl = rtrim($user['api_url'], '/');
            $this->evolutionApiKey = $user['api_key'];
            error_log("{$this->logPrefix} Instância carregada (supervisor): {$this->instanceName}");
            return;
        }
        
        // Se não encontrou ou não tem instância, verificar se é atendente com instância própria
        try {
            $checkTable = $this->pdo->query("SHOW TABLES LIKE 'attendant_instances'");
            if ($checkTable->rowCount() > 0) {
                $stmt = $this->pdo->prepare("
                    SELECT 
                        ai.instance_name as name,
                        u.evolution_api_url as api_url,
                        u.evolution_token as api_key,
                        'attendant' as user_type
                    FROM attendant_instances ai
                    JOIN supervisor_users su ON ai.attendant_id = su.id
                    JOIN users u ON su.supervisor_id = u.id
                    WHERE su.id = ? AND ai.status = 'connected'
                    LIMIT 1
                ");
                $stmt->execute([$this->userId]);
                $attendant = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($attendant) {
                    $this->instanceName = $attendant['name'];
                    $this->evolutionApiUrl = rtrim($attendant['api_url'], '/');
                    $this->evolutionApiKey = $attendant['api_key'];
                    error_log("{$this->logPrefix} Instância carregada (atendente): {$this->instanceName}");
                    return;
                }
            }
        } catch (Exception $e) {
            error_log("{$this->logPrefix} Erro ao buscar instância de atendente: " . $e->getMessage());
        }
        
        error_log("{$this->logPrefix} AVISO: Nenhuma instância configurada para user_id: {$this->userId}");
    }
    
    /**
     * Verifica se existe uma sessão de bot ativa para o telefone
     */
    public function hasActiveSession(string $phone): bool {
        $stmt = $this->pdo->prepare("
            SELECT id FROM bot_sessions 
            WHERE phone = ? AND user_id = ? AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$phone, $this->userId]);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Obtém a sessão ativa do contato
     */
    public function getActiveSession(string $phone): ?array {
        $stmt = $this->pdo->prepare("
            SELECT * FROM bot_sessions 
            WHERE phone = ? AND user_id = ? AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$phone, $this->userId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($session) {
            $session['state'] = $session['state'] ? json_decode($session['state'], true) : [];
        }
        
        return $session ?: null;
    }
    
    /**
     * Inicia uma nova sessão de bot para o contato
     */
    public function startSession(int $flowId, string $phone, ?int $contactId = null): ?array {
        // Buscar fluxo publicado
        $stmt = $this->pdo->prepare("
            SELECT * FROM bot_flows 
            WHERE id = ? AND user_id = ? AND status = 'published'
        ");
        $stmt->execute([$flowId, $this->userId]);
        $flow = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$flow) {
            $this->log(null, 'error', ['message' => 'Fluxo não encontrado ou não publicado', 'flow_id' => $flowId]);
            return null;
        }
        
        // Encerrar sessões anteriores
        $this->pdo->prepare("
            UPDATE bot_sessions SET status = 'completed' 
            WHERE phone = ? AND user_id = ? AND status = 'active'
        ")->execute([$phone, $this->userId]);
        
        // Buscar nó inicial
        $stmt = $this->pdo->prepare("
            SELECT id FROM bot_nodes 
            WHERE flow_id = ? AND type = 'start' 
            LIMIT 1
        ");
        $stmt->execute([$flowId]);
        $startNode = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$startNode) {
            $this->log(null, 'error', ['message' => 'Nó inicial não encontrado', 'flow_id' => $flowId]);
            return null;
        }
        
        // Criar sessão
        $stmt = $this->pdo->prepare("
            INSERT INTO bot_sessions (flow_id, version, user_id, contact_id, phone, state, last_node_id, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([
            $flowId,
            $flow['version'],
            $this->userId,
            $contactId,
            $phone,
            json_encode(['variables' => []]),
            $startNode['id']
        ]);
        
        $sessionId = $this->pdo->lastInsertId();
        
        $this->log($sessionId, 'session_started', ['flow_id' => $flowId, 'phone' => $phone]);
        
        // Executar a partir do nó inicial
        $this->executeFromNode($sessionId, $startNode['id']);
        
        return $this->getSessionById($sessionId);
    }
    
    /**
     * Processa input do usuário (mensagem recebida)
     */
    public function processInput(string $phone, string $message, array $messageData = []): bool {
        $session = $this->getActiveSession($phone);
        
        if (!$session) {
            return false;
        }
        
        $sessionId = $session['id'];
        $currentNodeId = $session['last_node_id'];
        
        // Buscar nó atual
        $node = $this->getNode($currentNodeId);
        
        if (!$node) {
            $this->endSession($sessionId, 'failed');
            return false;
        }
        
        $this->log($sessionId, 'input_received', [
            'node_id' => $currentNodeId,
            'message' => $message
        ]);
        
        // Se o nó atual é um input, processar a resposta
        if ($this->isInputNode($node['type'])) {
            $config = $node['config'] ? json_decode($node['config'], true) : [];
            $variableName = $config['variable'] ?? 'input';
            
            // Validar input baseado no tipo
            $validation = $this->validateInput($message, $node['type'], $config);
            
            if (!$validation['valid']) {
                // Enviar mensagem de erro e aguardar nova resposta
                $this->sendMessage($phone, $validation['error']);
                return true; // Não avança, aguarda nova resposta
            }
            
            // Salvar variável (com valor formatado se houver)
            $state = $session['state'];
            $state['variables'][$variableName] = $validation['value'] ?? $message;
            $this->updateSessionState($sessionId, $state);
            
            // Avançar para próximo nó
            $nextNodeId = $this->getNextNode($session['flow_id'], $currentNodeId, $message, $state);
            
            if ($nextNodeId) {
                $this->executeFromNode($sessionId, $nextNodeId);
            } else {
                $this->endSession($sessionId, 'completed');
            }
            
            return true;
        }
        
        // Se não é input, pode ser uma resposta a botões
        if ($node['type'] === 'buttons' || $node['type'] === 'whatsapp_buttons') {
            $config = $node['config'] ? json_decode($node['config'], true) : [];
            $buttons = $config['buttons'] ?? [];
            
            // Verificar se a resposta corresponde a um botão
            $buttonIndex = array_search(strtolower(trim($message)), array_map('strtolower', array_map('trim', $buttons)));
            
            $state = $session['state'];
            $state['variables']['button_response'] = $message;
            $state['variables']['button_index'] = $buttonIndex !== false ? $buttonIndex : -1;
            $this->updateSessionState($sessionId, $state);
            
            // Avançar para próximo nó
            $nextNodeId = $this->getNextNode($session['flow_id'], $currentNodeId, $message, $state);
            
            if ($nextNodeId) {
                $this->executeFromNode($sessionId, $nextNodeId);
            } else {
                $this->endSession($sessionId, 'completed');
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Executa o fluxo a partir de um nó específico
     */
    private function executeFromNode(int $sessionId, int $nodeId): void {
        $session = $this->getSessionById($sessionId);
        if (!$session || $session['status'] !== 'active') {
            return;
        }
        
        $node = $this->getNode($nodeId);
        if (!$node) {
            $this->endSession($sessionId, 'failed');
            return;
        }
        
        // Atualizar último nó
        $this->updateLastNode($sessionId, $nodeId);
        
        $this->log($sessionId, 'node_executed', [
            'node_id' => $nodeId,
            'type' => $node['type']
        ]);
        
        $config = $node['config'] ? json_decode($node['config'], true) : [];
        $state = $session['state'];
        
        // Executar ação do nó
        switch ($node['type']) {
            case 'start':
                // Apenas avança para o próximo
                $nextNodeId = $this->getNextNode($session['flow_id'], $nodeId, null, $state);
                if ($nextNodeId) {
                    $this->executeFromNode($sessionId, $nextNodeId);
                }
                break;
                
            case 'text':
                $text = $this->replaceVariables($config['text'] ?? '', $state);
                $this->sendMessage($session['phone'], $text);
                
                // Avança automaticamente
                $nextNodeId = $this->getNextNode($session['flow_id'], $nodeId, null, $state);
                if ($nextNodeId) {
                    usleep(500000); // 0.5s delay
                    $this->executeFromNode($sessionId, $nextNodeId);
                } else {
                    $this->endSession($sessionId, 'completed');
                }
                break;
                
            case 'image':
                $this->sendMedia($session['phone'], 'image', $config['url'] ?? '', $config['caption'] ?? '');
                $nextNodeId = $this->getNextNode($session['flow_id'], $nodeId, null, $state);
                if ($nextNodeId) {
                    usleep(500000);
                    $this->executeFromNode($sessionId, $nextNodeId);
                }
                break;
                
            case 'audio':
                $this->sendMedia($session['phone'], 'audio', $config['url'] ?? '');
                $nextNodeId = $this->getNextNode($session['flow_id'], $nodeId, null, $state);
                if ($nextNodeId) {
                    usleep(500000);
                    $this->executeFromNode($sessionId, $nextNodeId);
                }
                break;
                
            case 'video':
                $this->sendMedia($session['phone'], 'video', $config['url'] ?? '', $config['caption'] ?? '');
                $nextNodeId = $this->getNextNode($session['flow_id'], $nodeId, null, $state);
                if ($nextNodeId) {
                    usleep(500000);
                    $this->executeFromNode($sessionId, $nextNodeId);
                }
                break;
                
            case 'file':
                $this->sendMedia($session['phone'], 'document', $config['url'] ?? '', $config['filename'] ?? '');
                $nextNodeId = $this->getNextNode($session['flow_id'], $nodeId, null, $state);
                if ($nextNodeId) {
                    usleep(500000);
                    $this->executeFromNode($sessionId, $nextNodeId);
                }
                break;
                
            case 'buttons':
            case 'whatsapp_buttons':
                $text = $this->replaceVariables($config['text'] ?? 'Escolha uma opção:', $state);
                $buttons = $config['buttons'] ?? [];
                $this->sendButtons($session['phone'], $text, $buttons);
                // Aguarda resposta do usuário (não avança automaticamente)
                break;
                
            case 'whatsapp_list':
                $title = $config['title'] ?? 'Menu';
                $buttonText = $config['buttonText'] ?? 'Ver opções';
                $sections = $config['sections'] ?? [];
                $this->sendList($session['phone'], $title, $buttonText, $sections);
                // Aguarda resposta do usuário
                break;
                
            case 'input_text':
            case 'input_number':
            case 'input_email':
            case 'input_phone':
            case 'input_date':
                $placeholder = $config['placeholder'] ?? '';
                if ($placeholder) {
                    $this->sendMessage($session['phone'], $this->replaceVariables($placeholder, $state));
                }
                // Aguarda resposta do usuário
                break;
                
            case 'wait':
                $seconds = intval($config['seconds'] ?? 3);
                sleep($seconds);
                $nextNodeId = $this->getNextNode($session['flow_id'], $nodeId, null, $state);
                if ($nextNodeId) {
                    $this->executeFromNode($sessionId, $nextNodeId);
                }
                break;
                
            case 'set_variable':
                $varName = $config['variable'] ?? '';
                $varValue = $this->replaceVariables($config['value'] ?? '', $state);
                if ($varName) {
                    $state['variables'][$varName] = $varValue;
                    $this->updateSessionState($sessionId, $state);
                }
                $nextNodeId = $this->getNextNode($session['flow_id'], $nodeId, null, $state);
                if ($nextNodeId) {
                    $this->executeFromNode($sessionId, $nextNodeId);
                }
                break;
                
            case 'condition':
                $variable = $config['variable'] ?? '';
                $operator = $config['operator'] ?? 'equals';
                $value = $config['value'] ?? '';
                
                $varValue = $state['variables'][$variable] ?? '';
                $conditionMet = $this->evaluateCondition($varValue, $operator, $value);
                
                // Buscar próximo nó baseado na condição
                $nextNodeId = $this->getNextNodeByCondition($session['flow_id'], $nodeId, $conditionMet);
                if ($nextNodeId) {
                    $this->executeFromNode($sessionId, $nextNodeId);
                } else {
                    $this->endSession($sessionId, 'completed');
                }
                break;
                
            case 'webhook':
                $url = $this->replaceVariables($config['url'] ?? '', $state);
                $method = $config['method'] ?? 'POST';
                $response = $this->callWebhook($url, $method, $state['variables']);
                
                if ($response) {
                    $state['variables']['webhook_response'] = $response;
                    $this->updateSessionState($sessionId, $state);
                }
                
                $nextNodeId = $this->getNextNode($session['flow_id'], $nodeId, null, $state);
                if ($nextNodeId) {
                    $this->executeFromNode($sessionId, $nextNodeId);
                }
                break;
                
            case 'transfer':
                $message = $this->replaceVariables($config['message'] ?? 'Transferindo para um atendente...', $state);
                $this->sendMessage($session['phone'], $message);
                
                // Criar ticket de atendimento
                $this->createHumanTicket($session, $state);
                
                // Encerrar sessão do bot
                $this->endSession($sessionId, 'completed');
                break;
                
            case 'end_chat':
            case 'end':
                $message = $config['message'] ?? '';
                if ($message) {
                    $this->sendMessage($session['phone'], $this->replaceVariables($message, $state));
                }
                $this->endSession($sessionId, 'completed');
                break;
                
            case 'openai':
                $prompt = $this->replaceVariables($config['prompt'] ?? '', $state);
                
                // Chamar IA com configuração completa
                $response = $this->callAI($config, $prompt, $state);
                
                if ($response) {
                    $responseVar = $config['responseVariable'] ?? 'ai_response';
                    $state['variables'][$responseVar] = $response;
                    $this->updateSessionState($sessionId, $state);
                    $this->sendMessage($session['phone'], $response);
                } else {
                    // Enviar mensagem de erro amigável
                    $this->sendMessage($session['phone'], 'Desculpe, não consegui processar sua solicitação no momento. Tente novamente.');
                }
                
                $nextNodeId = $this->getNextNode($session['flow_id'], $nodeId, null, $state);
                if ($nextNodeId) {
                    $this->executeFromNode($sessionId, $nextNodeId);
                }
                break;
                
            default:
                // Tipo desconhecido, avança para próximo
                $nextNodeId = $this->getNextNode($session['flow_id'], $nodeId, null, $state);
                if ($nextNodeId) {
                    $this->executeFromNode($sessionId, $nextNodeId);
                }
                break;
        }
    }
    
    /**
     * Obtém o próximo nó baseado nas edges
     */
    private function getNextNode(int $flowId, int $currentNodeId, ?string $input, array $state): ?int {
        $stmt = $this->pdo->prepare("
            SELECT to_node_id, condition_json 
            FROM bot_edges 
            WHERE flow_id = ? AND from_node_id = ?
            ORDER BY sort_order ASC
        ");
        $stmt->execute([$flowId, $currentNodeId]);
        $edges = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($edges as $edge) {
            $condition = $edge['condition_json'] ? json_decode($edge['condition_json'], true) : null;
            
            // Se não tem condição, retorna este nó
            if (!$condition) {
                return intval($edge['to_node_id']);
            }
            
            // Avaliar condição
            if ($this->evaluateEdgeCondition($condition, $input, $state)) {
                return intval($edge['to_node_id']);
            }
        }
        
        // Se tem edges mas nenhuma condição foi satisfeita, retorna o primeiro
        if (!empty($edges)) {
            return intval($edges[0]['to_node_id']);
        }
        
        return null;
    }
    
    /**
     * Obtém próximo nó baseado em condição true/false
     */
    private function getNextNodeByCondition(int $flowId, int $nodeId, bool $conditionMet): ?int {
        $stmt = $this->pdo->prepare("
            SELECT to_node_id, condition_json 
            FROM bot_edges 
            WHERE flow_id = ? AND from_node_id = ?
            ORDER BY sort_order ASC
        ");
        $stmt->execute([$flowId, $nodeId]);
        $edges = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($edges as $edge) {
            $condition = $edge['condition_json'] ? json_decode($edge['condition_json'], true) : null;
            
            if ($condition && isset($condition['branch'])) {
                if (($condition['branch'] === 'true' && $conditionMet) ||
                    ($condition['branch'] === 'false' && !$conditionMet)) {
                    return intval($edge['to_node_id']);
                }
            }
        }
        
        // Fallback: primeiro edge
        return !empty($edges) ? intval($edges[0]['to_node_id']) : null;
    }
    
    /**
     * Avalia uma condição
     */
    private function evaluateCondition(string $value, string $operator, string $compareValue): bool {
        $value = strtolower(trim($value));
        $compareValue = strtolower(trim($compareValue));
        
        switch ($operator) {
            case 'equals':
                return $value === $compareValue;
            case 'not_equals':
                return $value !== $compareValue;
            case 'contains':
                return strpos($value, $compareValue) !== false;
            case 'not_contains':
                return strpos($value, $compareValue) === false;
            case 'starts':
                return strpos($value, $compareValue) === 0;
            case 'ends':
                return substr($value, -strlen($compareValue)) === $compareValue;
            case 'greater':
                return floatval($value) > floatval($compareValue);
            case 'less':
                return floatval($value) < floatval($compareValue);
            case 'empty':
                return empty($value);
            case 'not_empty':
                return !empty($value);
            default:
                return false;
        }
    }
    
    /**
     * Avalia condição de uma edge
     */
    private function evaluateEdgeCondition(array $condition, ?string $input, array $state): bool {
        if (isset($condition['button_index'])) {
            return ($state['variables']['button_index'] ?? -1) == $condition['button_index'];
        }
        
        if (isset($condition['value']) && $input !== null) {
            return strtolower(trim($input)) === strtolower(trim($condition['value']));
        }
        
        return true;
    }
    
    /**
     * Substitui variáveis no texto
     */
    private function replaceVariables(string $text, array $state): string {
        $variables = $state['variables'] ?? [];
        
        foreach ($variables as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
            $text = str_replace('{' . $key . '}', $value, $text);
        }
        
        return $text;
    }
    
    /**
     * Valida input do usuário baseado no tipo
     */
    private function validateInput(string $input, string $type, array $config): array {
        $input = trim($input);
        
        switch ($type) {
            case 'input_number':
                if (!is_numeric($input)) {
                    return [
                        'valid' => false,
                        'error' => '❌ Por favor, digite apenas números.'
                    ];
                }
                
                // Verificar min/max se configurado
                $min = $config['min'] ?? null;
                $max = $config['max'] ?? null;
                $value = floatval($input);
                
                if ($min !== null && $value < $min) {
                    return [
                        'valid' => false,
                        'error' => "❌ O valor deve ser maior ou igual a {$min}."
                    ];
                }
                
                if ($max !== null && $value > $max) {
                    return [
                        'valid' => false,
                        'error' => "❌ O valor deve ser menor ou igual a {$max}."
                    ];
                }
                
                return ['valid' => true, 'value' => $value];
                
            case 'input_email':
                if (!filter_var($input, FILTER_VALIDATE_EMAIL)) {
                    return [
                        'valid' => false,
                        'error' => '❌ Por favor, digite um email válido. Exemplo: nome@email.com'
                    ];
                }
                return ['valid' => true, 'value' => strtolower($input)];
                
            case 'input_phone':
                // Remove caracteres não numéricos
                $cleanPhone = preg_replace('/[^0-9]/', '', $input);
                
                // Valida telefone brasileiro (10 ou 11 dígitos)
                if (strlen($cleanPhone) < 10 || strlen($cleanPhone) > 11) {
                    return [
                        'valid' => false,
                        'error' => '❌ Por favor, digite um telefone válido. Exemplo: (11) 99999-9999'
                    ];
                }
                
                return ['valid' => true, 'value' => $cleanPhone];
                
            case 'input_date':
                // Tenta parsear data em vários formatos
                $formats = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'd/m/y'];
                $date = null;
                
                foreach ($formats as $format) {
                    $d = DateTime::createFromFormat($format, $input);
                    if ($d && $d->format($format) === $input) {
                        $date = $d;
                        break;
                    }
                }
                
                if (!$date) {
                    return [
                        'valid' => false,
                        'error' => '❌ Por favor, digite uma data válida. Exemplo: 25/12/2024'
                    ];
                }
                
                return ['valid' => true, 'value' => $date->format('Y-m-d')];
                
            case 'input_text':
            default:
                // Validar tamanho mínimo/máximo se configurado
                $minLength = $config['minLength'] ?? null;
                $maxLength = $config['maxLength'] ?? null;
                $length = mb_strlen($input);
                
                if ($minLength !== null && $length < $minLength) {
                    return [
                        'valid' => false,
                        'error' => "❌ O texto deve ter pelo menos {$minLength} caracteres."
                    ];
                }
                
                if ($maxLength !== null && $length > $maxLength) {
                    return [
                        'valid' => false,
                        'error' => "❌ O texto deve ter no máximo {$maxLength} caracteres."
                    ];
                }
                
                return ['valid' => true, 'value' => $input];
        }
    }
    
    /**
     * Verifica se é um nó de input
     */
    private function isInputNode(string $type): bool {
        return in_array($type, [
            'input_text', 'input_number', 'input_email', 
            'input_phone', 'input_date', 'file_upload', 'rating'
        ]);
    }
    
    // ===== ENVIO DE MENSAGENS =====
    
    /**
     * Envia mensagem de texto
     */
    private function sendMessage(string $phone, string $text): bool {
        if (!$this->instanceName || !$text) {
            return false;
        }
        
        $url = "{$this->evolutionApiUrl}/message/sendText/{$this->instanceName}";
        
        $data = [
            'number' => $phone,
            'text' => $text
        ];
        
        return $this->makeApiRequest($url, $data);
    }
    
    /**
     * Envia mídia (imagem, áudio, vídeo, documento)
     */
    private function sendMedia(string $phone, string $type, string $url, string $caption = ''): bool {
        if (!$this->instanceName || !$url) {
            return false;
        }
        
        $endpoint = "{$this->evolutionApiUrl}/message/sendMedia/{$this->instanceName}";
        
        $data = [
            'number' => $phone,
            'mediatype' => $type,
            'media' => $url,
            'caption' => $caption
        ];
        
        return $this->makeApiRequest($endpoint, $data);
    }
    
    /**
     * Envia botões (WhatsApp)
     */
    private function sendButtons(string $phone, string $text, array $buttons): bool {
        if (!$this->instanceName) {
            return false;
        }
        
        // Formatar botões para Evolution API
        $formattedButtons = array_map(function($btn, $idx) {
            return [
                'buttonId' => 'btn_' . $idx,
                'buttonText' => ['displayText' => $btn]
            ];
        }, $buttons, array_keys($buttons));
        
        $url = "{$this->evolutionApiUrl}/message/sendButtons/{$this->instanceName}";
        
        $data = [
            'number' => $phone,
            'title' => '',
            'description' => $text,
            'buttons' => $formattedButtons
        ];
        
        // Fallback: se botões não funcionarem, enviar como texto
        if (!$this->makeApiRequest($url, $data)) {
            $textWithButtons = $text . "\n\n";
            foreach ($buttons as $idx => $btn) {
                $textWithButtons .= ($idx + 1) . ". " . $btn . "\n";
            }
            $textWithButtons .= "\n_Digite o número da opção desejada_";
            return $this->sendMessage($phone, $textWithButtons);
        }
        
        return true;
    }
    
    /**
     * Envia lista (WhatsApp)
     */
    private function sendList(string $phone, string $title, string $buttonText, array $sections): bool {
        if (!$this->instanceName) {
            return false;
        }
        
        $url = "{$this->evolutionApiUrl}/message/sendList/{$this->instanceName}";
        
        $data = [
            'number' => $phone,
            'title' => $title,
            'description' => '',
            'buttonText' => $buttonText,
            'sections' => $sections
        ];
        
        return $this->makeApiRequest($url, $data);
    }
    
    /**
     * Faz requisição para a API com retry automático
     */
    private function makeApiRequest(string $url, array $data, int $maxRetries = 3): bool {
        $attempt = 0;
        $lastError = null;
        
        while ($attempt < $maxRetries) {
            $attempt++;
            
            try {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($data),
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'apikey: ' . $this->evolutionApiKey
                    ],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_CONNECTTIMEOUT => 10
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);
                
                // Sucesso
                if ($httpCode >= 200 && $httpCode < 300) {
                    if ($attempt > 1) {
                        $this->log(null, 'api_retry_success', [
                            'url' => $url,
                            'attempt' => $attempt
                        ]);
                    }
                    return true;
                }
                
                // Erro temporário (429, 500, 502, 503, 504) - retry
                if (in_array($httpCode, [429, 500, 502, 503, 504])) {
                    $lastError = "HTTP {$httpCode}: {$response}";
                    
                    if ($attempt < $maxRetries) {
                        // Backoff exponencial: 1s, 2s, 4s
                        $waitTime = pow(2, $attempt - 1);
                        sleep($waitTime);
                        continue;
                    }
                }
                
                // Erro permanente (400, 401, 403, 404) - não retry
                if (in_array($httpCode, [400, 401, 403, 404])) {
                    $lastError = "HTTP {$httpCode}: {$response}";
                    break;
                }
                
                // Erro de conexão - retry
                if ($curlError) {
                    $lastError = "CURL Error: {$curlError}";
                    
                    if ($attempt < $maxRetries) {
                        sleep(pow(2, $attempt - 1));
                        continue;
                    }
                }
                
                // Outro erro
                $lastError = "HTTP {$httpCode}: {$response}";
                break;
                
            } catch (Exception $e) {
                $lastError = "Exception: " . $e->getMessage();
                
                if ($attempt < $maxRetries) {
                    sleep(pow(2, $attempt - 1));
                    continue;
                }
            }
        }
        
        // Todas as tentativas falharam
        $this->log(null, 'api_error', [
            'url' => $url,
            'attempts' => $attempt,
            'error' => $lastError
        ]);
        
        error_log("{$this->logPrefix} API Error após {$attempt} tentativas: {$lastError}");
        return false;
    }
    
    /**
     * Chama webhook externo
     */
    private function callWebhook(string $url, string $method, array $data): ?string {
        if (!$url) {
            return null;
        }
        
        $ch = curl_init($url);
        
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ];
        
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        } elseif ($method === 'PUT') {
            $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }
        
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        curl_close($ch);
        
        return $response ?: null;
    }
    
    /**
     * Chama provedor de IA (OpenAI, Gemini, Claude, Groq)
     */
    private function callAI(array $config, string $prompt, array $state): ?string {
        $provider = $config['provider'] ?? 'openai';
        $model = $config['model'] ?? 'gpt-3.5-turbo';
        $apiKey = $config['apiKey'] ?? null;
        $systemPrompt = $config['systemPrompt'] ?? '';
        $temperature = floatval($config['temperature'] ?? 0.7);
        $maxTokens = intval($config['maxTokens'] ?? 500);
        
        // Se não tem API Key no config, buscar do sistema
        if (!$apiKey) {
            $stmt = $this->pdo->prepare("
                SELECT config_value FROM system_config 
                WHERE config_key = ? AND user_id = ?
            ");
            $stmt->execute(["{$provider}_api_key", $this->userId]);
            $apiKey = $stmt->fetchColumn();
        }
        
        if (!$apiKey) {
            $this->log(null, 'error', [
                'message' => "API Key não configurada para provider: {$provider}",
                'provider' => $provider
            ]);
            return "Erro: API Key não configurada. Configure em Configurações > Integrações.";
        }
        
        try {
            switch ($provider) {
                case 'openai':
                    return $this->callOpenAI($apiKey, $model, $systemPrompt, $prompt, $temperature, $maxTokens);
                    
                case 'gemini':
                    return $this->callGemini($apiKey, $model, $systemPrompt, $prompt, $temperature, $maxTokens);
                    
                case 'anthropic':
                    return $this->callAnthropic($apiKey, $model, $systemPrompt, $prompt, $temperature, $maxTokens);
                    
                case 'groq':
                    return $this->callGroq($apiKey, $model, $systemPrompt, $prompt, $temperature, $maxTokens);
                    
                default:
                    $this->log(null, 'error', [
                        'message' => "Provider não suportado: {$provider}",
                        'provider' => $provider
                    ]);
                    return "Erro: Provider de IA não suportado.";
            }
        } catch (Exception $e) {
            $this->log(null, 'error', [
                'message' => "Erro ao chamar IA: " . $e->getMessage(),
                'provider' => $provider,
                'model' => $model
            ]);
            return "Erro ao processar resposta da IA. Tente novamente.";
        }
    }
    
    /**
     * Chama OpenAI API
     */
    private function callOpenAI(string $apiKey, string $model, string $systemPrompt, string $userPrompt, float $temperature, int $maxTokens): ?string {
        $messages = [];
        
        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        
        $messages[] = ['role' => 'user', 'content' => $userPrompt];
        
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("OpenAI API retornou código {$httpCode}");
        }
        
        if ($response) {
            $data = json_decode($response, true);
            return $data['choices'][0]['message']['content'] ?? null;
        }
        
        return null;
    }
    
    /**
     * Chama Google Gemini API
     */
    private function callGemini(string $apiKey, string $model, string $systemPrompt, string $userPrompt, float $temperature, int $maxTokens): ?string {
        $fullPrompt = $systemPrompt ? "{$systemPrompt}\n\n{$userPrompt}" : $userPrompt;
        
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'contents' => [
                    ['parts' => [['text' => $fullPrompt]]]
                ],
                'generationConfig' => [
                    'temperature' => $temperature,
                    'maxOutputTokens' => $maxTokens
                ]
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Gemini API retornou código {$httpCode}");
        }
        
        if ($response) {
            $data = json_decode($response, true);
            return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        }
        
        return null;
    }
    
    /**
     * Chama Anthropic Claude API
     */
    private function callAnthropic(string $apiKey, string $model, string $systemPrompt, string $userPrompt, float $temperature, int $maxTokens): ?string {
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
                'system' => $systemPrompt ?: 'You are a helpful assistant.',
                'messages' => [
                    ['role' => 'user', 'content' => $userPrompt]
                ]
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Anthropic API retornou código {$httpCode}");
        }
        
        if ($response) {
            $data = json_decode($response, true);
            return $data['content'][0]['text'] ?? null;
        }
        
        return null;
    }
    
    /**
     * Chama Groq API (compatível com OpenAI)
     */
    private function callGroq(string $apiKey, string $model, string $systemPrompt, string $userPrompt, float $temperature, int $maxTokens): ?string {
        $messages = [];
        
        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        
        $messages[] = ['role' => 'user', 'content' => $userPrompt];
        
        $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Groq API retornou código {$httpCode}");
        }
        
        if ($response) {
            $data = json_decode($response, true);
            return $data['choices'][0]['message']['content'] ?? null;
        }
        
        return null;
    }
    
    /**
     * Cria ticket de atendimento humano
     */
    private function createHumanTicket(array $session, array $state): void {
        $phone = $session['phone'];
        
        // Buscar ou criar conversa
        $stmt = $this->pdo->prepare("
            SELECT id FROM chat_conversations 
            WHERE user_id = ? AND phone = ?
            LIMIT 1
        ");
        $stmt->execute([$this->userId, $phone]);
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($conversation) {
            // Atualizar status para aguardando atendimento
            $this->pdo->prepare("
                UPDATE chat_conversations 
                SET status = 'waiting', 
                    bot_session_id = NULL,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$conversation['id']]);
        } else {
            // Criar nova conversa
            $stmt = $this->pdo->prepare("
                INSERT INTO chat_conversations (user_id, phone, name, status, created_at, updated_at)
                VALUES (?, ?, ?, 'waiting', NOW(), NOW())
            ");
            $stmt->execute([
                $this->userId,
                $phone,
                $state['variables']['nome'] ?? 'Contato ' . substr($phone, -4)
            ]);
        }
        
        $this->log($session['id'], 'transferred_to_human', [
            'phone' => $phone,
            'variables' => $state['variables']
        ]);
    }
    
    // ===== GERENCIAMENTO DE SESSÃO =====
    
    private function getSessionById(int $sessionId): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM bot_sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($session) {
            $session['state'] = $session['state'] ? json_decode($session['state'], true) : [];
        }
        
        return $session ?: null;
    }
    
    private function getNode(int $nodeId): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM bot_nodes WHERE id = ?");
        $stmt->execute([$nodeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    private function updateSessionState(int $sessionId, array $state): void {
        $this->pdo->prepare("
            UPDATE bot_sessions SET state = ?, updated_at = NOW() WHERE id = ?
        ")->execute([json_encode($state), $sessionId]);
    }
    
    private function updateLastNode(int $sessionId, int $nodeId): void {
        $this->pdo->prepare("
            UPDATE bot_sessions SET last_node_id = ?, last_step_at = NOW(), updated_at = NOW() WHERE id = ?
        ")->execute([$nodeId, $sessionId]);
    }
    
    public function endSession(int $sessionId, string $status = 'completed'): void {
        $this->pdo->prepare("
            UPDATE bot_sessions SET status = ?, updated_at = NOW() WHERE id = ?
        ")->execute([$status, $sessionId]);
        
        $this->log($sessionId, 'session_ended', ['status' => $status]);
    }
    
    private function log(int $sessionId, string $event, array $payload = []): void {
        $session = $sessionId ? $this->getSessionById($sessionId) : null;
        
        $this->pdo->prepare("
            INSERT INTO bot_logs (session_id, flow_id, node_id, event, payload, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ")->execute([
            $sessionId,
            $session['flow_id'] ?? 0,
            $session['last_node_id'] ?? null,
            $event,
            json_encode($payload)
        ]);
    }
    
    // ===== GATILHOS =====
    
    /**
     * Verifica se deve iniciar um fluxo baseado em gatilhos
     */
    public function checkTriggers(string $phone, string $message, ?int $conversationId = null): ?int {
        // Buscar fluxos publicados com gatilhos configurados
        $stmt = $this->pdo->prepare("
            SELECT bf.id, bf.name, bn.config
            FROM bot_flows bf
            JOIN bot_nodes bn ON bn.flow_id = bf.id AND bn.type = 'start'
            WHERE bf.user_id = ? AND bf.status = 'published'
        ");
        $stmt->execute([$this->userId]);
        $flows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($flows as $flow) {
            $config = $flow['config'] ? json_decode($flow['config'], true) : [];
            $triggerType = $config['trigger_type'] ?? 'manual';
            
            switch ($triggerType) {
                case 'keyword':
                    $keywords = $config['keywords'] ?? [];
                    $messageLower = strtolower($message);
                    foreach ($keywords as $kw) {
                        if ($kw && strpos($messageLower, strtolower($kw)) !== false) {
                            return intval($flow['id']);
                        }
                    }
                    break;
                    
                case 'first_message':
                    if ($this->isFirstMessage($phone)) {
                        return intval($flow['id']);
                    }
                    break;
                    
                case 'all':
                    return intval($flow['id']);
            }
        }
        
        return null;
    }
    
    private function isFirstMessage(string $phone): bool {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM chat_messages cm
            JOIN chat_conversations cc ON cc.id = cm.conversation_id
            WHERE cc.user_id = ? AND cc.phone = ? AND cm.from_me = 0
        ");
        $stmt->execute([$this->userId, $phone]);
        return intval($stmt->fetchColumn()) <= 1;
    }
}
