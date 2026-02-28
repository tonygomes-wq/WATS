<?php

/**
 * AIProcessor - Processador de IA para Automation Flows
 * 
 * Responsável por processar prompts com IA, incluindo:
 * - Seleção de provedor (OpenAI/Gemini)
 * - Preparação de contexto com histórico de conversa
 * - Substituição de variáveis em prompts
 * - Chamadas às APIs de IA
 * 
 * Requirements: 3.1, 3.2, 3.3, 3.4, 3.5
 */

class AIProcessor
{
    private PDO $pdo;
    private array $config;
    private ?int $userId;
    
    /**
     * Construtor
     * @param PDO $pdo Conexão com banco de dados
     * @param array $config Configuração opcional (para testes)
     * @param int|null $userId ID do usuário (para buscar API keys)
     */
    public function __construct(PDO $pdo, array $config = [], ?int $userId = null)
    {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->userId = $userId;
    }
    
    /**
     * Processa prompt com IA
     * 
     * @param array $agentConfig Configuração do agente de IA
     * @param array $context Contexto da conversa
     * @return array Resultado com resposta da IA
     */
    public function process(array $agentConfig, array $context): array
    {
        $startTime = microtime(true);
        $result = [
            'success' => false,
            'response' => null,
            'prompt' => null,
            'provider' => null,
            'error' => null,
            'execution_time_ms' => 0
        ];
        
        try {
            // Verifica se IA está habilitada
            if (empty($agentConfig['enabled'])) {
                return $result;
            }
            
            // Seleciona provedor (padrão: openai)
            $provider = strtolower($agentConfig['provider'] ?? 'openai');
            if (!in_array($provider, ['openai', 'gemini'])) {
                $provider = 'openai';
            }
            $result['provider'] = $provider;
            
            // Prepara contexto com histórico
            $preparedContext = $this->prepareContext($context);
            
            // Prepara prompt com substituição de variáveis
            $prompt = $agentConfig['prompt'] ?? '';
            if (empty($prompt)) {
                throw new Exception("Prompt is required");
            }
            
            // Substitui variáveis no prompt
            $prompt = VariableSubstitutor::substitute($prompt, $preparedContext);
            $result['prompt'] = $prompt;
            
            // Chama provedor de IA
            $response = null;
            if ($provider === 'openai') {
                $response = $this->callOpenAI($prompt, $agentConfig);
            } elseif ($provider === 'gemini') {
                $response = $this->callGemini($prompt, $agentConfig);
            }
            
            if ($response !== null) {
                $result['success'] = true;
                $result['response'] = $response;
            } else {
                $result['error'] = "AI provider returned null response";
            }
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            error_log("AIProcessor: Error processing AI: " . $e->getMessage());
        }
        
        $result['execution_time_ms'] = round((microtime(true) - $startTime) * 1000);
        
        return $result;
    }
    
    /**
     * Prepara contexto para envio à IA
     * 
     * Carrega histórico de mensagens e prepara estrutura de contexto
     * completa para substituição de variáveis.
     * 
     * @param array $context Contexto base
     * @return array Contexto preparado com histórico
     */
    private function prepareContext(array $context): array
    {
        $preparedContext = $context;
        
        // Carrega histórico de mensagens se conversation_id está disponível
        if (!empty($context['conversation_id'])) {
            $history = $this->loadConversationHistory($context['conversation_id']);
            $preparedContext['history'] = $history;
        } else {
            $preparedContext['history'] = [];
        }
        
        // Adiciona timestamp formatado se não existe
        if (empty($preparedContext['timestamp'])) {
            $preparedContext['timestamp'] = date('Y-m-d H:i:s');
        } elseif (is_numeric($preparedContext['timestamp'])) {
            $preparedContext['timestamp'] = date('Y-m-d H:i:s', $preparedContext['timestamp']);
        }
        
        return $preparedContext;
    }
    
    /**
     * Carrega histórico de mensagens da conversa
     * 
     * @param int $conversationId ID da conversa
     * @return array Histórico de mensagens (últimas 10)
     */
    private function loadConversationHistory(int $conversationId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT message, direction, created_at
                FROM chat_messages
                WHERE conversation_id = ?
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $stmt->execute([$conversationId]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Inverte ordem para ter mensagens mais antigas primeiro
            $messages = array_reverse($messages);
            
            // Formata mensagens para estrutura esperada
            $history = [];
            foreach ($messages as $msg) {
                $history[] = [
                    'role' => $msg['direction'] === 'incoming' ? 'user' : 'assistant',
                    'text' => $msg['message'],
                    'timestamp' => $msg['created_at']
                ];
            }
            
            return $history;
            
        } catch (Exception $e) {
            error_log("AIProcessor: Error loading conversation history: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Chama OpenAI API
     * 
     * Implementa chamada à OpenAI Chat Completions API com:
     * - Suporte a configuração de model, temperature, max_tokens
     * - Timeout configurável (padrão 30 segundos)
     * - Retry logic com exponential backoff (3 tentativas)
     * 
     * Requirements: 3.2, 3.8
     * 
     * @param string $prompt Prompt preparado
     * @param array $config Configuração do agente
     * @return string|null Resposta da IA ou null em caso de erro
     */
    private function callOpenAI(string $prompt, array $config): ?string
    {
        // Busca API key do usuário
        if ($this->userId === null) {
            error_log("AIProcessor: user_id not set, cannot call OpenAI");
            return null;
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT config_value FROM system_config 
                WHERE config_key = 'openai_api_key' AND user_id = ?
            ");
            $stmt->execute([$this->userId]);
            $apiKey = $stmt->fetchColumn();
            
            if (!$apiKey) {
                error_log("AIProcessor: OpenAI API key not configured for user {$this->userId}");
                return null;
            }
        } catch (Exception $e) {
            error_log("AIProcessor: Error fetching OpenAI API key: " . $e->getMessage());
            return null;
        }
        
        // Configurações com valores padrão
        $model = $config['model'] ?? 'gpt-4';
        $temperature = $config['temperature'] ?? 0.7;
        $maxTokens = $config['max_tokens'] ?? 500;
        $timeout = $config['timeout'] ?? 30;
        
        // Prepara payload
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => (float)$temperature,
            'max_tokens' => (int)$maxTokens
        ];
        
        // Retry logic com exponential backoff (3 tentativas)
        $maxRetries = 3;
        $baseDelay = 1; // segundos
        
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            try {
                $ch = curl_init('https://api.openai.com/v1/chat/completions');
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($payload),
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $apiKey
                    ],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => (int)$timeout
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);
                
                // Verifica erro de curl
                if ($response === false) {
                    throw new Exception("cURL error: " . $curlError);
                }
                
                // Verifica código HTTP
                if ($httpCode >= 200 && $httpCode < 300) {
                    $data = json_decode($response, true);
                    
                    if (isset($data['choices'][0]['message']['content'])) {
                        return $data['choices'][0]['message']['content'];
                    } else {
                        throw new Exception("Invalid response format from OpenAI");
                    }
                }
                
                // Erros 5xx são retryable
                if ($httpCode >= 500 && $httpCode < 600) {
                    if ($attempt < $maxRetries - 1) {
                        $delay = $baseDelay * pow(2, $attempt); // exponential backoff
                        error_log("AIProcessor: OpenAI returned {$httpCode}, retrying in {$delay}s (attempt " . ($attempt + 1) . "/{$maxRetries})");
                        sleep($delay);
                        continue;
                    }
                }
                
                // Erros 4xx não são retryable
                if ($httpCode >= 400 && $httpCode < 500) {
                    $errorData = json_decode($response, true);
                    $errorMsg = $errorData['error']['message'] ?? "HTTP {$httpCode}";
                    error_log("AIProcessor: OpenAI API error: {$errorMsg}");
                    return null;
                }
                
                // Outros erros
                throw new Exception("Unexpected HTTP code: {$httpCode}");
                
            } catch (Exception $e) {
                error_log("AIProcessor: Error calling OpenAI (attempt " . ($attempt + 1) . "/{$maxRetries}): " . $e->getMessage());
                
                // Se não é a última tentativa, faz retry com backoff
                if ($attempt < $maxRetries - 1) {
                    $delay = $baseDelay * pow(2, $attempt);
                    sleep($delay);
                    continue;
                }
                
                // Última tentativa falhou
                return null;
            }
        }
        
        return null;
    }
    
    /**
     * Chama Gemini API
     * 
     * Implementa chamada à Google Gemini API com:
     * - Suporte a configuração de model, temperature, max_tokens
     * - Timeout configurável (padrão 30 segundos)
     * - Retry logic com exponential backoff (3 tentativas)
     * 
     * Requirements: 3.3, 3.8
     * 
     * @param string $prompt Prompt preparado
     * @param array $config Configuração do agente
     * @return string|null Resposta da IA ou null em caso de erro
     */
    private function callGemini(string $prompt, array $config): ?string
    {
        // Busca API key do usuário
        if ($this->userId === null) {
            error_log("AIProcessor: user_id not set, cannot call Gemini");
            return null;
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT config_value FROM system_config 
                WHERE config_key = 'gemini_api_key' AND user_id = ?
            ");
            $stmt->execute([$this->userId]);
            $apiKey = $stmt->fetchColumn();
            
            if (!$apiKey) {
                error_log("AIProcessor: Gemini API key not configured for user {$this->userId}");
                return null;
            }
        } catch (Exception $e) {
            error_log("AIProcessor: Error fetching Gemini API key: " . $e->getMessage());
            return null;
        }
        
        // Configurações com valores padrão
        $model = $config['model'] ?? 'gemini-pro';
        $temperature = $config['temperature'] ?? 0.7;
        $maxTokens = $config['max_tokens'] ?? 500;
        $timeout = $config['timeout'] ?? 30;
        
        // Prepara payload para Gemini API
        // Gemini usa estrutura diferente do OpenAI
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => (float)$temperature,
                'maxOutputTokens' => (int)$maxTokens
            ]
        ];
        
        // Retry logic com exponential backoff (3 tentativas)
        $maxRetries = 3;
        $baseDelay = 1; // segundos
        
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            try {
                // Gemini API endpoint: https://generativelanguage.googleapis.com/v1/models/{model}:generateContent?key={apiKey}
                $url = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key={$apiKey}";
                
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($payload),
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json'
                    ],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => (int)$timeout
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);
                
                // Verifica erro de curl
                if ($response === false) {
                    throw new Exception("cURL error: " . $curlError);
                }
                
                // Verifica código HTTP
                if ($httpCode >= 200 && $httpCode < 300) {
                    $data = json_decode($response, true);
                    
                    // Gemini retorna resposta em candidates[0].content.parts[0].text
                    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                        return $data['candidates'][0]['content']['parts'][0]['text'];
                    } else {
                        throw new Exception("Invalid response format from Gemini");
                    }
                }
                
                // Erros 5xx são retryable
                if ($httpCode >= 500 && $httpCode < 600) {
                    if ($attempt < $maxRetries - 1) {
                        $delay = $baseDelay * pow(2, $attempt); // exponential backoff
                        error_log("AIProcessor: Gemini returned {$httpCode}, retrying in {$delay}s (attempt " . ($attempt + 1) . "/{$maxRetries})");
                        sleep($delay);
                        continue;
                    }
                }
                
                // Erros 4xx não são retryable
                if ($httpCode >= 400 && $httpCode < 500) {
                    $errorData = json_decode($response, true);
                    $errorMsg = $errorData['error']['message'] ?? "HTTP {$httpCode}";
                    error_log("AIProcessor: Gemini API error: {$errorMsg}");
                    return null;
                }
                
                // Outros erros
                throw new Exception("Unexpected HTTP code: {$httpCode}");
                
            } catch (Exception $e) {
                error_log("AIProcessor: Error calling Gemini (attempt " . ($attempt + 1) . "/{$maxRetries}): " . $e->getMessage());
                
                // Se não é a última tentativa, faz retry com backoff
                if ($attempt < $maxRetries - 1) {
                    $delay = $baseDelay * pow(2, $attempt);
                    sleep($delay);
                    continue;
                }
                
                // Última tentativa falhou
                return null;
            }
        }
        
        return null;
    }
}
