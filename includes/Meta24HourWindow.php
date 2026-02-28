<?php
/**
 * Gerenciador de Janela de 24 Horas da Meta API
 * A Meta permite mensagens livres apenas dentro de 24h após última mensagem do cliente
 */

class Meta24HourWindow
{
    private PDO $pdo;
    private int $windowHours = 24;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Verifica se está dentro da janela de 24h para enviar mensagem livre
     * 
     * @param int $conversationId ID da conversa
     * @param int $userId ID do usuário
     * @return array ['within_window' => bool, 'hours_remaining' => float, 'last_client_message' => string|null]
     */
    public function checkWindow(int $conversationId, int $userId): array
    {
        // Buscar última mensagem recebida do cliente (from_me = 0)
        $stmt = $this->pdo->prepare("
            SELECT created_at, message_text
            FROM chat_messages
            WHERE conversation_id = ?
            AND user_id = ?
            AND from_me = 0
            AND provider = 'meta'
            ORDER BY created_at DESC
            LIMIT 1
        ");
        
        $stmt->execute([$conversationId, $userId]);
        $lastMessage = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lastMessage) {
            // Sem mensagens do cliente - janela fechada
            return [
                'within_window' => false,
                'hours_remaining' => 0,
                'last_client_message' => null,
                'reason' => 'no_client_message'
            ];
        }

        $lastMessageTime = strtotime($lastMessage['created_at']);
        $currentTime = time();
        $hoursSinceLastMessage = ($currentTime - $lastMessageTime) / 3600;
        $hoursRemaining = $this->windowHours - $hoursSinceLastMessage;

        $withinWindow = $hoursRemaining > 0;

        return [
            'within_window' => $withinWindow,
            'hours_remaining' => max(0, $hoursRemaining),
            'hours_elapsed' => $hoursSinceLastMessage,
            'last_client_message' => $lastMessage['created_at'],
            'last_message_preview' => substr($lastMessage['message_text'], 0, 50)
        ];
    }

    /**
     * Verifica se pode enviar mensagem livre ou precisa de template
     * 
     * @param int $conversationId ID da conversa
     * @param int $userId ID do usuário
     * @param string $messageText Texto da mensagem (opcional, para log)
     * @return array ['can_send' => bool, 'requires_template' => bool, 'reason' => string]
     */
    public function canSendFreeMessage(int $conversationId, int $userId, string $messageText = ''): array
    {
        $windowCheck = $this->checkWindow($conversationId, $userId);

        if ($windowCheck['within_window']) {
            return [
                'can_send' => true,
                'requires_template' => false,
                'reason' => 'within_24h_window',
                'hours_remaining' => $windowCheck['hours_remaining']
            ];
        }

        // Fora da janela - requer template
        error_log(sprintf(
            '[META_24H] Janela expirada para conversa %d. Última mensagem: %s (%.1f horas atrás)',
            $conversationId,
            $windowCheck['last_client_message'] ?? 'nunca',
            $windowCheck['hours_elapsed'] ?? 0
        ));

        return [
            'can_send' => false,
            'requires_template' => true,
            'reason' => 'window_expired',
            'hours_elapsed' => $windowCheck['hours_elapsed'] ?? 0,
            'suggestion' => 'Use um template aprovado pela Meta para reengajar o cliente'
        ];
    }

    /**
     * Obtém templates disponíveis do usuário
     * 
     * @param int $userId ID do usuário
     * @return array Lista de templates
     */
    public function getAvailableTemplates(int $userId): array
    {
        // Buscar templates aprovados do usuário
        $stmt = $this->pdo->prepare("
            SELECT 
                id,
                template_name,
                template_language,
                template_category,
                template_status,
                template_components
            FROM meta_message_templates
            WHERE user_id = ?
            AND template_status = 'APPROVED'
            ORDER BY template_name ASC
        ");
        
        $stmt->execute([$userId]);
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decodificar components JSON
        foreach ($templates as &$template) {
            if (!empty($template['template_components'])) {
                $template['components'] = json_decode($template['template_components'], true);
            }
        }

        return $templates;
    }

    /**
     * Registra tentativa de envio fora da janela para análise
     * 
     * @param int $conversationId ID da conversa
     * @param int $userId ID do usuário
     * @param string $attemptedMessage Mensagem que tentou enviar
     */
    public function logWindowViolation(int $conversationId, int $userId, string $attemptedMessage): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO meta_window_violations 
                (user_id, conversation_id, attempted_message, violation_time, created_at)
                VALUES (?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $userId,
                $conversationId,
                substr($attemptedMessage, 0, 500)
            ]);
        } catch (Exception $e) {
            error_log('[META_24H] Erro ao registrar violação: ' . $e->getMessage());
        }
    }

    /**
     * Calcula estatísticas de uso da janela de 24h
     * 
     * @param int $userId ID do usuário
     * @param int $days Período em dias
     * @return array Estatísticas
     */
    public function getWindowStats(int $userId, int $days = 7): array
    {
        $stats = [
            'total_conversations' => 0,
            'within_window' => 0,
            'outside_window' => 0,
            'window_utilization' => 0,
            'avg_response_time_hours' => 0
        ];

        try {
            // Buscar conversas ativas do período
            $stmt = $this->pdo->prepare("
                SELECT id
                FROM chat_conversations
                WHERE user_id = ?
                AND provider = 'meta'
                AND last_message_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            
            $stmt->execute([$userId, $days]);
            $conversations = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $stats['total_conversations'] = count($conversations);

            foreach ($conversations as $convId) {
                $check = $this->checkWindow($convId, $userId);
                if ($check['within_window']) {
                    $stats['within_window']++;
                } else {
                    $stats['outside_window']++;
                }
            }

            if ($stats['total_conversations'] > 0) {
                $stats['window_utilization'] = round(
                    ($stats['within_window'] / $stats['total_conversations']) * 100,
                    2
                );
            }

        } catch (Exception $e) {
            error_log('[META_24H] Erro ao calcular estatísticas: ' . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Sugere melhor horário para reengajamento baseado em histórico
     * 
     * @param int $conversationId ID da conversa
     * @return array Sugestão de horário
     */
    public function suggestReengagementTime(int $conversationId): array
    {
        try {
            // Analisar padrão de respostas do cliente
            $stmt = $this->pdo->prepare("
                SELECT 
                    HOUR(created_at) as hour,
                    DAYOFWEEK(created_at) as day_of_week,
                    COUNT(*) as message_count
                FROM chat_messages
                WHERE conversation_id = ?
                AND from_me = 0
                GROUP BY HOUR(created_at), DAYOFWEEK(created_at)
                ORDER BY message_count DESC
                LIMIT 1
            ");
            
            $stmt->execute([$conversationId]);
            $pattern = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($pattern) {
                $days = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
                
                return [
                    'suggested_hour' => $pattern['hour'],
                    'suggested_day' => $days[$pattern['day_of_week'] - 1],
                    'confidence' => 'high',
                    'reason' => 'Baseado em padrão de atividade do cliente'
                ];
            }

        } catch (Exception $e) {
            error_log('[META_24H] Erro ao sugerir horário: ' . $e->getMessage());
        }

        // Padrão: horário comercial
        return [
            'suggested_hour' => 10,
            'suggested_day' => 'Dias úteis',
            'confidence' => 'low',
            'reason' => 'Sugestão padrão (horário comercial)'
        ];
    }
}
