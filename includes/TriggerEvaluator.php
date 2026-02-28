<?php
/**
 * TriggerEvaluator - Componente de avaliação de triggers
 * 
 * Responsável por avaliar se um trigger deve ser acionado com base no tipo
 * e configuração do trigger e no contexto da mensagem recebida.
 * 
 * Tipos de trigger suportados:
 * - keyword: Verifica se a mensagem contém palavras-chave específicas
 * - first_message: Verifica se é a primeira mensagem do contato
 * - off_hours: Verifica se a mensagem foi recebida fora do horário de atendimento
 * - no_response: Verifica se a conversa está sem resposta há muito tempo
 * - manual: Apenas execução manual (sempre retorna false na avaliação automática)
 */

class TriggerEvaluator
{
    private PDO $pdo;
    
    /**
     * Construtor
     * @param PDO $pdo Conexão com banco de dados
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    /**
     * Avalia se trigger deve ser acionado
     * @param string $triggerType Tipo do trigger
     * @param array $triggerConfig Configuração do trigger
     * @param array $context Contexto da mensagem
     * @return bool True se o trigger deve ser acionado
     */
    public function evaluate(
        string $triggerType, 
        array $triggerConfig, 
        array $context
    ): bool
    {
        // Valida tipo de trigger
        $validTypes = ['keyword', 'first_message', 'off_hours', 'no_response', 'manual'];
        if (!in_array($triggerType, $validTypes)) {
            error_log("TriggerEvaluator: Invalid trigger type: {$triggerType}");
            return false;
        }
        
        // Dispatcher para diferentes tipos de trigger
        switch ($triggerType) {
            case 'keyword':
                return $this->evaluateKeyword($triggerConfig, $context);
                
            case 'first_message':
                return $this->evaluateFirstMessage($triggerConfig, $context);
                
            case 'off_hours':
                return $this->evaluateOffHours($triggerConfig, $context);
                
            case 'no_response':
                return $this->evaluateNoResponse($triggerConfig, $context);
                
            case 'manual':
                // Trigger manual nunca é acionado automaticamente
                return false;
                
            default:
                return false;
        }
    }
    
    /**
     * Avalia trigger de palavra-chave
     * Verifica se a mensagem contém alguma das palavras-chave configuradas
     * 
     * @param array $config Configuração do trigger (deve conter 'keywords')
     * @param array $context Contexto da mensagem (deve conter 'message')
     * @return bool True se alguma palavra-chave foi encontrada
     */
    private function evaluateKeyword(array $config, array $context): bool
    {
        // Verifica se a mensagem está presente no contexto
        if (!isset($context['message']) || empty($context['message'])) {
            return false;
        }
        
        $message = $context['message'];
        
        // Extrai keywords da configuração
        $keywords = [];
        
        // Suporta keywords como array ou string separada por vírgulas
        if (isset($config['keywords'])) {
            if (is_array($config['keywords'])) {
                $keywords = $config['keywords'];
            } elseif (is_string($config['keywords'])) {
                // Separa por vírgula e remove espaços em branco
                $keywords = array_map('trim', explode(',', $config['keywords']));
            }
        }
        
        // Se não há keywords configuradas, retorna false
        if (empty($keywords)) {
            return false;
        }
        
        // Converte mensagem para minúsculas para matching case-insensitive
        $messageLower = mb_strtolower($message, 'UTF-8');
        
        // Verifica se alguma keyword está presente na mensagem
        foreach ($keywords as $keyword) {
            $keyword = trim($keyword);
            
            // Ignora keywords vazias
            if (empty($keyword)) {
                continue;
            }
            
            // Matching case-insensitive
            $keywordLower = mb_strtolower($keyword, 'UTF-8');
            
            // Verifica se a keyword está contida na mensagem
            if (mb_strpos($messageLower, $keywordLower) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Avalia trigger de primeira mensagem
     * Verifica se é a primeira mensagem do contato na conversa
     * 
     * @param array $config Configuração do trigger (pode conter 'window_seconds')
     * @param array $context Contexto da mensagem (deve conter 'conversation_id' ou 'phone')
     * @return bool True se é a primeira mensagem
     */
    private function evaluateFirstMessage(array $config, array $context): bool
    {
        // Verifica se temos conversation_id ou phone no contexto
        $conversationId = $context['conversation_id'] ?? null;
        $phone = $context['phone'] ?? null;
        
        if (!$conversationId && !$phone) {
            error_log("TriggerEvaluator: evaluateFirstMessage requires conversation_id or phone in context");
            return false;
        }
        
        try {
            // Extrai window_seconds da configuração (opcional)
            $windowSeconds = $config['window_seconds'] ?? null;
            
            // Monta a query base
            $sql = "SELECT COUNT(*) as message_count 
                    FROM chat_messages cm";
            
            $params = [];
            
            // Se temos conversation_id, usa ele diretamente
            if ($conversationId) {
                $sql .= " WHERE cm.conversation_id = :conversation_id";
                $params[':conversation_id'] = $conversationId;
            } else {
                // Se não temos conversation_id, busca pela phone através de chat_conversations
                $sql .= " INNER JOIN chat_conversations cc ON cm.conversation_id = cc.id
                         WHERE cc.contact_number = :phone";
                $params[':phone'] = $phone;
            }
            
            // Filtra apenas mensagens do contato (não do sistema ou usuário)
            $sql .= " AND cm.sender_type = 'contact'";
            
            // Se window_seconds está configurado, considera apenas mensagens dentro da janela de tempo
            if ($windowSeconds !== null && is_numeric($windowSeconds) && $windowSeconds > 0) {
                $sql .= " AND cm.created_at >= DATE_SUB(NOW(), INTERVAL :window_seconds SECOND)";
                $params[':window_seconds'] = (int)$windowSeconds;
            }
            
            // Executa a query
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            // Se o count é 1, significa que esta é a primeira mensagem
            // (a mensagem atual já foi inserida no banco antes da avaliação do trigger)
            $messageCount = (int)($result['message_count'] ?? 0);
            
            return $messageCount === 1;
            
        } catch (\PDOException $e) {
            error_log("TriggerEvaluator: Database error in evaluateFirstMessage: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Avalia trigger de fora de horário
     * Verifica se a mensagem foi recebida fora do horário de atendimento
     * 
     * @param array $config Configuração do trigger (deve conter 'start', 'end', 'timezone')
     * @param array $context Contexto da mensagem (deve conter 'timestamp')
     * @return bool True se está fora do horário
     */
    private function evaluateOffHours(array $config, array $context): bool
    {
        // Valida configuração obrigatória
        if (!isset($config['start']) || !isset($config['end'])) {
            error_log("TriggerEvaluator: evaluateOffHours requires 'start' and 'end' in config");
            return false;
        }
        
        // Extrai configuração
        $startTime = $config['start'];
        $endTime = $config['end'];
        $timezone = $config['timezone'] ?? 'UTC';
        
        // Valida formato de horário (HH:MM)
        if (!preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
            error_log("TriggerEvaluator: Invalid time format. Expected HH:MM, got start={$startTime}, end={$endTime}");
            return false;
        }
        
        try {
            // Cria timezone object
            $tz = new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            error_log("TriggerEvaluator: Invalid timezone '{$timezone}': " . $e->getMessage());
            return false;
        }
        
        // Obtém timestamp da mensagem (usa timestamp do contexto ou timestamp atual)
        $timestamp = $context['timestamp'] ?? time();
        
        // Cria DateTime object para o timestamp na timezone configurada
        $messageTime = new \DateTime('@' . $timestamp);
        $messageTime->setTimezone($tz);
        
        // Extrai hora e minuto da mensagem
        $messageHour = (int)$messageTime->format('H');
        $messageMinute = (int)$messageTime->format('i');
        $messageTimeInMinutes = $messageHour * 60 + $messageMinute;
        
        // Parse start e end times
        list($startHour, $startMinute) = explode(':', $startTime);
        list($endHour, $endMinute) = explode(':', $endTime);
        
        $startTimeInMinutes = (int)$startHour * 60 + (int)$startMinute;
        $endTimeInMinutes = (int)$endHour * 60 + (int)$endMinute;
        
        // Verifica se é horário overnight (ex: 18:00 até 08:00)
        if ($startTimeInMinutes > $endTimeInMinutes) {
            // Horário overnight: está DENTRO do horário se está entre start e 23:59 OU entre 00:00 e end
            // Portanto, está FORA do horário se está entre end e start
            $isWithinHours = $messageTimeInMinutes >= $startTimeInMinutes || $messageTimeInMinutes < $endTimeInMinutes;
            return !$isWithinHours; // Retorna true se está FORA do horário
        } else {
            // Horário normal: está DENTRO do horário se está entre start e end
            $isWithinHours = $messageTimeInMinutes >= $startTimeInMinutes && $messageTimeInMinutes < $endTimeInMinutes;
            return !$isWithinHours; // Retorna true se está FORA do horário
        }
    }
    
    /**
     * Avalia trigger de sem resposta
     * Verifica se a conversa está sem resposta há mais tempo que o configurado
     * 
     * @param array $config Configuração do trigger (deve conter 'minutes')
     * @param array $context Contexto da mensagem (deve conter 'conversation_id')
     * @return bool True se está sem resposta há muito tempo
     */
    private function evaluateNoResponse(array $config, array $context): bool
    {
        // Valida configuração obrigatória
        if (!isset($config['minutes']) || !is_numeric($config['minutes'])) {
            error_log("TriggerEvaluator: evaluateNoResponse requires 'minutes' in config");
            return false;
        }
        
        // Valida contexto obrigatório
        $conversationId = $context['conversation_id'] ?? null;
        if (!$conversationId) {
            error_log("TriggerEvaluator: evaluateNoResponse requires 'conversation_id' in context");
            return false;
        }
        
        $minutes = (int)$config['minutes'];
        
        // Se minutes é 0 ou negativo, retorna false
        if ($minutes <= 0) {
            return false;
        }
        
        try {
            // Busca a última mensagem de um atendente/usuário (não do contato)
            $sql = "SELECT created_at 
                    FROM chat_messages 
                    WHERE conversation_id = :conversation_id 
                      AND sender_type IN ('user', 'system')
                    ORDER BY created_at DESC 
                    LIMIT 1";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':conversation_id' => $conversationId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            // Se não há mensagem anterior de atendente/sistema, é a primeira mensagem do contato
            // Neste caso, retorna true (conversa sem resposta)
            if (!$result) {
                return true;
            }
            
            // Calcula tempo desde a última resposta
            $lastResponseTime = strtotime($result['created_at']);
            $currentTime = $context['timestamp'] ?? time();
            $elapsedMinutes = ($currentTime - $lastResponseTime) / 60;
            
            // Retorna true se o tempo decorrido é maior que o threshold configurado
            return $elapsedMinutes > $minutes;
            
        } catch (\PDOException $e) {
            error_log("TriggerEvaluator: Database error in evaluateNoResponse: " . $e->getMessage());
            return false;
        }
    }
}
