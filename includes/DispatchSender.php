<?php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/plan_check.php';
require_once BASE_PATH . '/includes/whatsapp_meta_service.php';
require_once BASE_PATH . '/includes/channels/WhatsAppChannel.php';
require_once BASE_PATH . '/includes/IdentifierResolver.php';

class DispatchSender
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? $GLOBALS['pdo'];
    }

    /**
     * @param array{
     *   user_id:int,
     *   contact_id?:int|null,
     *   phone:string,
     *   contact_name?:string,
     *   message?:string,
     *   campaign_id?:int|null,
     *   has_attachment?:bool,
     *   attachment_type?:string,
     *   template_name?:string,
     *   template_language?:string,
     *   template_components?:array
     * } $payload
     */
    public function send(array $payload): array
    {
        $userId = (int)($payload['user_id'] ?? 0);
        $contactId = isset($payload['contact_id']) ? (int)$payload['contact_id'] : null;
        $campaignId = isset($payload['campaign_id']) ? (int)$payload['campaign_id'] : null;
        $rawPhone = $payload['phone'] ?? '';
        $phone = formatPhone($rawPhone);
        $contactName = $payload['contact_name'] ?? '';
        $message = trim($payload['message'] ?? '');
        $hasAttachment = $payload['has_attachment'] ?? false;
        $attachmentType = $payload['attachment_type'] ?? null;
        $templateName = trim($payload['template_name'] ?? '');
        $templateLanguage = $payload['template_language'] ?? 'pt_BR';
        $templateComponents = $payload['template_components'] ?? [];

        if ($userId <= 0) {
            return $this->error('Usuário inválido', 'invalid_user');
        }

        if (!$phone) {
            return $this->error('Telefone inválido', 'invalid_phone');
        }

        if ($message === '' && $templateName === '') {
            return $this->error('Informe uma mensagem ou template', 'missing_message');
        }

        $planCheck = checkPlanLimit($userId);
        if (!$planCheck['allowed']) {
            return $this->error($planCheck['message'], 'plan_limit');
        }

        $user = $this->loadUser($userId);
        if (!$user) {
            return $this->error('Usuário não encontrado', 'invalid_user');
        }

        $provider = $user['whatsapp_provider'] ?? 'evolution';
        $transportUsed = null;
        $result = ['success' => false, 'error' => ''];

        if ($provider === 'meta') {
            // Tentar obter conversation_id se disponível
            $conversationId = $this->findConversationId($userId, $phone);
            
            $result = $this->sendViaMeta($phone, $message, $templateName, $templateLanguage, $templateComponents, $user, $userId, $conversationId);
            $transportUsed = $result['transport'] ?? 'meta';

            if (!$result['success'] && !empty($user['evolution_instance']) && !empty($user['evolution_token'])) {
                $fallback = $this->sendViaEvolution($phone, $message ?: '[TEMPLATE] ' . $templateName, $user, true);
                $transportUsed = 'evolution_fallback';
                $result = $fallback;
            }
        } elseif ($provider === 'zapi') {
            // Usar WhatsAppChannel para Z-API
            $result = $this->sendViaWhatsAppChannel($phone, $message, $user);
            $transportUsed = 'zapi';
        } else {
            // Evolution ou outros providers
            $result = $this->sendViaEvolution($phone, $message, $user);
            $transportUsed = $provider;
        }

        $status = $result['success'] ? 'sent' : 'failed';
        $messageId = $result['message_id'] ?? null;
        $errorMessage = $result['success'] ? null : ($result['error'] ?? 'Erro desconhecido');
        
        $dispatchId = $this->logDispatchHistory(
            $userId, 
            $contactId, 
            $campaignId,
            $phone,
            $contactName,
            $message ?: '[TEMPLATE] ' . $templateName, 
            $status,
            $hasAttachment,
            $attachmentType,
            $messageId,
            $errorMessage
        );

        if ($result['success']) {
            incrementMessageCount($userId);
            
            if ($campaignId) {
                $this->updateCampaignStats($campaignId, 'sent');
            }
        } else {
            if ($campaignId) {
                $this->updateCampaignStats($campaignId, 'failed');
            }
        }

        return [
            'success' => $result['success'],
            'dispatch_id' => $dispatchId,
            'transport' => $transportUsed,
            'error' => $result['error'] ?? null,
            'code' => $result['code'] ?? null,
        ];
    }

    private function loadUser(int $userId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, whatsapp_provider, evolution_instance, evolution_token,
            evolution_api_url, evolution_api_key,
            zapi_instance_id, zapi_token, provider_config, supports_lid,
            meta_phone_number_id, meta_business_account_id, meta_app_id, meta_app_secret,
            meta_permanent_token, meta_webhook_verify_token, meta_api_version
            FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    private function sendViaEvolution(string $phone, string $message, array $user, bool $isFallback = false): array
    {
        if (empty($user['evolution_instance']) || empty($user['evolution_token'])) {
            $msg = $isFallback
                ? 'Fallback para Evolution não disponível'
                : 'Configure sua instância Evolution API em Minha Instância.';
            return $this->error($msg, 'missing_instance');
        }

        try {
            // Usar WhatsAppChannel para envio
            $channel = new WhatsAppChannel($user, $this->pdo);
            
            // Normalizar identificador (suporta phone, JID, LID)
            $resolver = new IdentifierResolver($this->pdo);
            $identifier = $this->normalizeIdentifier($phone, $resolver);
            
            // Enviar mensagem
            $result = $channel->sendMessage($identifier, $message);
            
            return $result['success']
                ? ['success' => true, 'message_id' => $result['messageId'] ?? null]
                : $this->error($result['error'] ?? 'Falha ao enviar via Evolution API', 'provider_error');
                
        } catch (Exception $e) {
            error_log('[DISPATCH_SENDER] Erro ao enviar via Evolution: ' . $e->getMessage());
            return $this->error('Erro ao enviar mensagem: ' . $e->getMessage(), 'provider_error');
        }
    }

    /**
     * Enviar mensagem via WhatsAppChannel (Z-API ou outros providers)
     * Suporta normalização de números: 55+ddd+numero, ddd+numero, JID, LID
     */
    private function sendViaWhatsAppChannel(string $phone, string $message, array $user): array
    {
        try {
            // Criar canal WhatsApp
            $channel = new WhatsAppChannel($user, $this->pdo);
            
            // Normalizar identificador (suporta phone, JID, LID)
            $resolver = new IdentifierResolver($this->pdo);
            $identifier = $this->normalizeIdentifier($phone, $resolver);
            
            // Enviar mensagem
            $result = $channel->sendMessage($identifier, $message);
            
            return $result['success']
                ? ['success' => true, 'message_id' => $result['messageId'] ?? null]
                : $this->error($result['error'] ?? 'Falha ao enviar mensagem', 'provider_error');
                
        } catch (Exception $e) {
            error_log('[DISPATCH_SENDER] Erro ao enviar via WhatsAppChannel: ' . $e->getMessage());
            return $this->error('Erro ao enviar mensagem: ' . $e->getMessage(), 'provider_error');
        }
    }

    private function sendViaMeta(string $phone, string $message, string $templateName, string $language, array $components, array $user, int $userId, ?int $conversationId = null): array
    {
        $required = ['meta_phone_number_id','meta_business_account_id','meta_app_id','meta_app_secret','meta_permanent_token'];
        foreach ($required as $field) {
            if (empty($user[$field])) {
                return $this->error('Configure os dados da API oficial antes de enviar.', 'missing_meta_config');
            }
        }
        
        // Verificar janela de 24h se for mensagem livre (não template)
        if (empty($templateName) && $conversationId) {
            require_once BASE_PATH . '/includes/Meta24HourWindow.php';
            $windowManager = new Meta24HourWindow($this->pdo);
            $windowCheck = $windowManager->canSendFreeMessage($conversationId, $userId, $message);
            
            if (!$windowCheck['can_send']) {
                error_log('[DISPATCH_SENDER] Janela de 24h expirada para conversa ' . $conversationId);
                $windowManager->logWindowViolation($conversationId, $userId, $message);
                
                return $this->error(
                    'Janela de 24h expirada. Use um template aprovado para reengajar o cliente.',
                    'window_expired',
                    [
                        'requires_template' => true,
                        'hours_elapsed' => $windowCheck['hours_elapsed'] ?? 0,
                        'suggestion' => $windowCheck['suggestion'] ?? ''
                    ]
                );
            }
            
            error_log(sprintf(
                '[DISPATCH_SENDER] Dentro da janela de 24h (%.1f horas restantes)',
                $windowCheck['hours_remaining'] ?? 0
            ));
        }

        $metaConfig = [
            'meta_phone_number_id' => $user['meta_phone_number_id'],
            'meta_business_account_id' => $user['meta_business_account_id'],
            'meta_app_id' => $user['meta_app_id'],
            'meta_app_secret' => $user['meta_app_secret'],
            'meta_permanent_token' => $user['meta_permanent_token'],
            'meta_webhook_verify_token' => $user['meta_webhook_verify_token'] ?? null,
            'meta_api_version' => $user['meta_api_version'] ?? 'v19.0',
        ];

        if ($templateName !== '') {
            $response = sendMetaTemplateMessage($phone, $templateName, $language, $components, $metaConfig, $userId);
            $response['transport'] = 'meta_template';
            return $response['success'] ? $response : $this->error($response['error'] ?? 'Falha ao enviar template Meta', 'provider_error');
        }

        $response = sendMetaTextMessage($phone, $message, $metaConfig, $userId);
        $response['transport'] = 'meta_text';
        return $response['success'] ? $response : $this->error($response['error'] ?? 'Falha ao enviar mensagem Meta', 'provider_error');
    }

    /**
     * Normaliza identificador para formato correto
     * Suporta: 55+ddd+numero, ddd+numero, JID, LID
     * 
     * @param string $phone Número ou identificador
     * @param IdentifierResolver $resolver Resolver para conversões
     * @return string Identificador normalizado
     */
    private function normalizeIdentifier(string $phone, IdentifierResolver $resolver): string
    {
        // Se já é JID ou LID, retornar como está
        $type = IdentifierResolver::getType($phone);
        if ($type === 'jid' || $type === 'lid') {
            return $phone;
        }
        
        // Remover caracteres não numéricos
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        
        // Se tem 10 ou 11 dígitos (ddd+numero), adicionar código do país (55)
        if (strlen($cleanPhone) === 10 || strlen($cleanPhone) === 11) {
            $cleanPhone = '55' . $cleanPhone;
        }
        
        // Converter para JID
        return IdentifierResolver::toJID($cleanPhone);
    }

    private function logDispatchHistory(
        int $userId, 
        ?int $contactId, 
        ?int $campaignId,
        string $phone,
        string $contactName,
        string $message, 
        string $status,
        bool $hasAttachment = false,
        ?string $attachmentType = null,
        ?string $messageId = null,
        ?string $errorMessage = null
    ): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO dispatch_history (
                campaign_id, user_id, contact_id, phone, contact_name, message, 
                has_attachment, attachment_type, status, message_id, error_message, sent_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $campaignId,
            $userId, 
            $contactId, 
            $phone,
            $contactName,
            $message, 
            $hasAttachment ? 1 : 0,
            $attachmentType,
            $status,
            $messageId,
            $errorMessage
        ]);
        
        return (int)$this->pdo->lastInsertId();
    }
    
    private function updateCampaignStats(int $campaignId, string $type): void
    {
        $column = $type === 'sent' ? 'sent_count' : 'failed_count';
        
        $stmt = $this->pdo->prepare("
            UPDATE dispatch_campaigns 
            SET {$column} = {$column} + 1,
                status = CASE 
                    WHEN status = 'draft' THEN 'in_progress'
                    ELSE status
                END,
                started_at = CASE 
                    WHEN started_at IS NULL THEN NOW()
                    ELSE started_at
                END
            WHERE id = ?
        ");
        
        $stmt->execute([$campaignId]);
    }

    private function error(string $message, string $code = 'error', array $extra = []): array
    {
        return array_merge([
            'success' => false,
            'error' => $message,
            'code' => $code,
        ], $extra);
    }

    private function findConversationId(int $userId, string $phone): ?int
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id 
                FROM chat_conversations 
                WHERE user_id = ? 
                AND (remote_jid LIKE ? OR remote_jid LIKE ?)
                AND provider = 'meta'
                ORDER BY last_message_at DESC
                LIMIT 1
            ");
            $stmt->execute([$userId, "%$phone%", "$phone%"]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (int)$result['id'] : null;
        } catch (Exception $e) {
            error_log('[DISPATCH_SENDER] Erro ao buscar conversation_id: ' . $e->getMessage());
            return null;
        }
    }
}
