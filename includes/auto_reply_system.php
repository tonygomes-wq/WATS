<?php
/**
 * Sistema de Respostas Automáticas Baseadas em Sentimento
 */

class AutoReplySystem
{
    private PDO $pdo;
    private int $userId;
    
    public function __construct(PDO $pdo, int $userId)
    {
        $this->pdo = $pdo;
        $this->userId = $userId;
    }
    
    /**
     * Processa resposta automática baseada em sentimento
     */
    public function processAutoReply(int $responseId): ?array
    {
        // Buscar resposta e configurações
        $stmt = $this->pdo->prepare("
            SELECT 
                dr.*,
                c.name as contact_name,
                dc.id as campaign_id,
                u.evolution_instance,
                u.evolution_api_key
            FROM dispatch_responses dr
            LEFT JOIN contacts c ON dr.contact_id = c.id
            LEFT JOIN dispatch_campaigns dc ON dr.campaign_id = dc.id
            LEFT JOIN users u ON dr.user_id = u.id
            WHERE dr.id = ? AND dr.user_id = ?
        ");
        
        $stmt->execute([$responseId, $this->userId]);
        $response = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$response) {
            return null;
        }
        
        // Verificar se auto-reply está habilitado para este usuário
        $stmt = $this->pdo->prepare("
            SELECT auto_reply_enabled, auto_reply_config 
            FROM user_settings 
            WHERE user_id = ?
        ");
        $stmt->execute([$this->userId]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$settings || !$settings['auto_reply_enabled']) {
            return null;
        }
        
        $config = $settings['auto_reply_config'] ? json_decode($settings['auto_reply_config'], true) : [];
        
        // Determinar mensagem baseada no sentimento
        $replyMessage = $this->getReplyMessage($response['sentiment'], $config, $response['contact_name']);
        
        if (!$replyMessage) {
            return null;
        }
        
        // Enviar resposta automática
        return $this->sendAutoReply(
            $response['phone'],
            $replyMessage,
            $response['evolution_instance'],
            $response['evolution_api_key']
        );
    }
    
    /**
     * Determina mensagem de resposta baseada no sentimento
     */
    private function getReplyMessage(string $sentiment, array $config, string $contactName): ?string
    {
        $templates = [
            'positive' => $config['positive_template'] ?? "Olá {nome}! 😊\n\nQue ótimo receber sua mensagem positiva! Estamos muito felizes em poder ajudar.\n\nEm breve entraremos em contato para dar continuidade.\n\nObrigado!",
            
            'negative' => $config['negative_template'] ?? "Olá {nome},\n\nLamentamos muito pela sua experiência negativa. 😔\n\nSua opinião é muito importante para nós e queremos resolver isso o mais rápido possível.\n\nUm de nossos atendentes entrará em contato em breve para entender melhor a situação.\n\nPedimos desculpas pelo transtorno.",
            
            'neutral' => $config['neutral_template'] ?? "Olá {nome}! 👋\n\nRecebemos sua mensagem e agradecemos o contato.\n\nEm breve retornaremos com mais informações.\n\nQualquer dúvida, estamos à disposição!"
        ];
        
        $template = $templates[$sentiment] ?? null;
        
        if (!$template) {
            return null;
        }
        
        // Substituir variáveis
        return str_replace('{nome}', $contactName ?: 'Cliente', $template);
    }
    
    /**
     * Envia resposta automática via Evolution API
     */
    private function sendAutoReply(string $phone, string $message, string $instance, string $apiKey): array
    {
        if (empty($instance) || empty($apiKey)) {
            return ['success' => false, 'error' => 'Credenciais Evolution não configuradas'];
        }
        
        require_once BASE_PATH . '/includes/DispatchSender.php';
        
        $phoneFormatted = '55' . preg_replace('/[^0-9]/', '', $phone) . '@s.whatsapp.net';
        
        $data = [
            'number' => $phoneFormatted,
            'text' => $message
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, EVOLUTION_API_URL . '/message/sendText/' . $instance);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . EVOLUTION_API_KEY,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $success = $httpCode >= 200 && $httpCode < 300;
        
        // Registrar auto-reply
        if ($success) {
            $this->logAutoReply($phone, $message);
        }
        
        return [
            'success' => $success,
            'message' => $message,
            'http_code' => $httpCode
        ];
    }
    
    /**
     * Registra auto-reply no histórico
     */
    private function logAutoReply(string $phone, string $message): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO auto_reply_log 
                (user_id, phone, message, sent_at)
                VALUES (?, ?, ?, NOW())
            ");
            
            $stmt->execute([$this->userId, $phone, $message]);
        } catch (Exception $e) {
            error_log('Erro ao registrar auto-reply: ' . $e->getMessage());
        }
    }
    
    /**
     * Obtém configurações de auto-reply
     */
    public function getSettings(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT auto_reply_enabled, auto_reply_config 
            FROM user_settings 
            WHERE user_id = ?
        ");
        
        $stmt->execute([$this->userId]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$settings) {
            return [
                'enabled' => false,
                'config' => []
            ];
        }
        
        return [
            'enabled' => (bool)$settings['auto_reply_enabled'],
            'config' => $settings['auto_reply_config'] ? json_decode($settings['auto_reply_config'], true) : []
        ];
    }
    
    /**
     * Atualiza configurações de auto-reply
     */
    public function updateSettings(bool $enabled, array $config): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO user_settings (user_id, auto_reply_enabled, auto_reply_config, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                auto_reply_enabled = VALUES(auto_reply_enabled),
                auto_reply_config = VALUES(auto_reply_config),
                updated_at = NOW()
        ");
        
        return $stmt->execute([
            $this->userId,
            $enabled ? 1 : 0,
            json_encode($config)
        ]);
    }
}
