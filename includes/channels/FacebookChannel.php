<?php
/**
 * Canal Facebook Messenger
 * Implementa integração com Facebook Messenger Platform
 * Baseado em: chatwoot-4.10.0/app/models/channel/facebook_page.rb
 */

require_once __DIR__ . '/BaseChannel.php';

class FacebookChannel extends BaseChannel
{
    private string $pageId;
    private string $pageName;
    private string $pageAccessToken;
    private string $userAccessToken;
    private const GRAPH_API_VERSION = 'v18.0';
    private const GRAPH_API_URL = 'https://graph.facebook.com';
    
    protected function loadConfig(): void
    {
        parent::loadConfig();
        
        // Carregar configurações específicas do Facebook
        $stmt = $this->pdo->prepare("
            SELECT * FROM channel_facebook 
            WHERE channel_id = ?
        ");
        $stmt->execute([$this->channelId]);
        $facebookConfig = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$facebookConfig) {
            throw new Exception("Configuração do Facebook não encontrada");
        }
        
        $this->pageId = $facebookConfig['page_id'];
        $this->pageName = $facebookConfig['page_name'] ?? '';
        $this->pageAccessToken = $facebookConfig['page_access_token'];
        $this->userAccessToken = $facebookConfig['user_access_token'];
    }
    
    public function getName(): string
    {
        return 'Facebook Messenger';
    }
    
    public function getType(): string
    {
        return 'facebook';
    }
    
    /**
     * Valida credenciais do Facebook
     */
    public function validateCredentials(): bool
    {
        try {
            $url = self::GRAPH_API_URL . '/' . self::GRAPH_API_VERSION . '/' . $this->pageId;
            $url .= '?fields=name,access_token&access_token=' . urlencode($this->pageAccessToken);
            
            $result = $this->makeHttpRequest($url);
            
            if ($result['success'] && isset($result['response']['id'])) {
                $this->pageName = $result['response']['name'] ?? $this->pageName;
                
                // Atualizar nome da página
                $stmt = $this->pdo->prepare("
                    UPDATE channel_facebook 
                    SET page_name = ?
                    WHERE channel_id = ?
                ");
                $stmt->execute([$this->pageName, $this->channelId]);
                
                $this->updateChannelStatus('active');
                return true;
            }
            
            $errorMsg = $result['response']['error']['message'] ?? 'Token inválido';
            $this->updateChannelStatus('error', $errorMsg);
            return false;
            
        } catch (Exception $e) {
            $this->logActivity('validate_credentials', [], 'error', $e->getMessage());
            $this->updateChannelStatus('error', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Configura webhook no Facebook
     */
    public function setupWebhook(): bool
    {
        try {
            // Subscrever aos eventos do Messenger
            $url = self::GRAPH_API_URL . '/' . self::GRAPH_API_VERSION . '/' . $this->pageId . '/subscribed_apps';
            
            $data = [
                'subscribed_fields' => 'messages,messaging_postbacks,message_deliveries,message_reads,message_echoes',
                'access_token' => $this->pageAccessToken
            ];
            
            $result = $this->makeHttpRequest($url, 'POST', $data);
            
            if ($result['success'] && isset($result['response']['success']) && $result['response']['success']) {
                // Atualizar status do webhook
                $stmt = $this->pdo->prepare("
                    UPDATE channel_facebook 
                    SET webhook_verified = TRUE
                    WHERE channel_id = ?
                ");
                $stmt->execute([$this->channelId]);
                
                $this->logActivity('setup_webhook', ['page_id' => $this->pageId]);
                return true;
            }
            
            $errorMsg = $result['response']['error']['message'] ?? 'Erro ao configurar webhook';
            $this->logActivity('setup_webhook', [], 'error', $errorMsg);
            return false;
            
        } catch (Exception $e) {
            $this->logActivity('setup_webhook', [], 'error', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Envia mensagem de texto
     */
    public function sendMessage(array $message): array
    {
        try {
            $recipientId = $message['recipient_id'];
            $text = $message['text'];
            
            $url = self::GRAPH_API_URL . '/' . self::GRAPH_API_VERSION . '/me/messages';
            
            $data = [
                'recipient' => ['id' => $recipientId],
                'message' => ['text' => $text],
                'messaging_type' => 'RESPONSE',
                'access_token' => $this->pageAccessToken
            ];
            
            // Adicionar quick replies se houver
            if (isset($message['quick_replies'])) {
                $data['message']['quick_replies'] = $message['quick_replies'];
            }
            
            $result = $this->makeHttpRequest($url, 'POST', $data, ['Content-Type: application/json']);
            
            if ($result['success'] && isset($result['response']['message_id'])) {
                $messageId = $result['response']['message_id'];
                
                $this->logActivity('send_message', [
                    'recipient_id' => $recipientId,
                    'message_id' => $messageId
                ]);
                
                return [
                    'success' => true,
                    'message_id' => $messageId,
                    'external_id' => $messageId
                ];
            }
            
            $errorMsg = $result['response']['error']['message'] ?? 'Erro ao enviar mensagem';
            $this->logActivity('send_message', ['recipient_id' => $recipientId], 'error', $errorMsg);
            
            return [
                'success' => false,
                'error' => $errorMsg
            ];
            
        } catch (Exception $e) {
            $this->logActivity('send_message', [], 'error', $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Envia anexo (imagem, vídeo, arquivo)
     */
    public function sendAttachment(array $attachment): array
    {
        try {
            $recipientId = $attachment['recipient_id'];
            $type = $attachment['type']; // image, video, audio, file
            $url = $attachment['url'];
            
            $apiUrl = self::GRAPH_API_URL . '/' . self::GRAPH_API_VERSION . '/me/messages';
            
            $data = [
                'recipient' => ['id' => $recipientId],
                'message' => [
                    'attachment' => [
                        'type' => $type,
                        'payload' => [
                            'url' => $url,
                            'is_reusable' => true
                        ]
                    ]
                ],
                'messaging_type' => 'RESPONSE',
                'access_token' => $this->pageAccessToken
            ];
            
            $result = $this->makeHttpRequest($apiUrl, 'POST', $data, ['Content-Type: application/json']);
            
            if ($result['success'] && isset($result['response']['message_id'])) {
                return [
                    'success' => true,
                    'message_id' => $result['response']['message_id']
                ];
            }
            
            return [
                'success' => false,
                'error' => $result['response']['error']['message'] ?? 'Erro ao enviar anexo'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Envia ação de digitação
     */
    public function sendTypingAction(string $recipientId, string $action = 'typing_on'): bool
    {
        try {
            $url = self::GRAPH_API_URL . '/' . self::GRAPH_API_VERSION . '/me/messages';
            
            $data = [
                'recipient' => ['id' => $recipientId],
                'sender_action' => $action, // typing_on, typing_off, mark_seen
                'access_token' => $this->pageAccessToken
            ];
            
            $result = $this->makeHttpRequest($url, 'POST', $data, ['Content-Type: application/json']);
            
            return $result['success'];
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Marca mensagem como lida
     */
    public function markAsRead(string $externalId): bool
    {
        // Facebook não usa message_id para marcar como lido, usa sender_action
        return true;
    }
    
    /**
     * Processa webhook recebido
     */
    public function receiveWebhook(array $payload): array
    {
        try {
            $this->updateLastSync();
            
            if (!isset($payload['entry'])) {
                return ['success' => false, 'error' => 'Payload inválido'];
            }
            
            foreach ($payload['entry'] as $entry) {
                if (isset($entry['messaging'])) {
                    foreach ($entry['messaging'] as $event) {
                        // Processar mensagem
                        if (isset($event['message']) && !isset($event['message']['is_echo'])) {
                            $this->processMessage($event);
                        }
                        
                        // Processar postback (botão clicado)
                        if (isset($event['postback'])) {
                            $this->processPostback($event);
                        }
                        
                        // Processar delivery
                        if (isset($event['delivery'])) {
                            $this->processDelivery($event);
                        }
                        
                        // Processar read
                        if (isset($event['read'])) {
                            $this->processRead($event);
                        }
                    }
                }
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            $this->logActivity('receive_webhook', $payload, 'error', $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Processa mensagem recebida
     */
    private function processMessage(array $event): void
    {
        $sender = $event['sender'];
        $message = $event['message'];
        $messageId = $message['mid'];
        
        // Obter informações do usuário
        $userInfo = $this->getUserInfo($sender['id']);
        $userName = $userInfo['name'] ?? 'Usuário Facebook';
        
        // Criar ou atualizar contato
        $contactId = $this->createOrUpdateContact([
            'name' => $userName,
            'phone' => 'facebook_' . $sender['id'],
            'source' => 'facebook',
            'source_id' => $sender['id']
        ]);
        
        // Determinar tipo de mensagem
        $messageType = 'text';
        $messageText = '';
        $additionalData = [
            'sender_id' => $sender['id'],
            'recipient_id' => $event['recipient']['id']
        ];
        
        if (isset($message['text'])) {
            $messageText = $message['text'];
            $messageType = 'text';
        } elseif (isset($message['attachments'])) {
            $attachment = $message['attachments'][0];
            $attachmentType = $attachment['type'];
            
            $messageText = '[' . ucfirst($attachmentType) . ']';
            $messageType = $attachmentType;
            $additionalData['attachment'] = $attachment;
        }
        
        // Quick reply
        if (isset($message['quick_reply'])) {
            $additionalData['quick_reply'] = $message['quick_reply'];
        }
        
        // Criar mensagem
        $newMessageId = $this->createMessage([
            'contact_id' => $contactId,
            'external_id' => $messageId,
            'message_text' => $messageText,
            'from_me' => false,
            'message_type' => $messageType,
            'status' => 'received',
            'additional_data' => $additionalData
        ]);
        
        $this->logActivity('receive_message', [
            'message_id' => $messageId,
            'contact_id' => $contactId,
            'type' => $messageType
        ]);
    }
    
    /**
     * Processa postback (botão clicado)
     */
    private function processPostback(array $event): void
    {
        $sender = $event['sender'];
        $postback = $event['postback'];
        $payload = $postback['payload'];
        $title = $postback['title'] ?? '';
        
        // Obter informações do usuário
        $userInfo = $this->getUserInfo($sender['id']);
        $userName = $userInfo['name'] ?? 'Usuário Facebook';
        
        // Criar ou atualizar contato
        $contactId = $this->createOrUpdateContact([
            'name' => $userName,
            'phone' => 'facebook_' . $sender['id'],
            'source' => 'facebook',
            'source_id' => $sender['id']
        ]);
        
        // Criar mensagem de postback
        $this->createMessage([
            'contact_id' => $contactId,
            'external_id' => uniqid('postback_'),
            'message_text' => "Clicou em: {$title}",
            'from_me' => false,
            'message_type' => 'postback',
            'status' => 'received',
            'additional_data' => [
                'payload' => $payload,
                'title' => $title
            ]
        ]);
        
        $this->logActivity('receive_postback', [
            'contact_id' => $contactId,
            'payload' => $payload
        ]);
    }
    
    /**
     * Processa confirmação de entrega
     */
    private function processDelivery(array $event): void
    {
        $delivery = $event['delivery'];
        $mids = $delivery['mids'] ?? [];
        
        foreach ($mids as $mid) {
            // Atualizar status da mensagem
            $stmt = $this->pdo->prepare("
                UPDATE messages 
                SET status = 'delivered'
                WHERE channel_id = ? AND external_id = ?
            ");
            $stmt->execute([$this->channelId, $mid]);
        }
        
        $this->logActivity('delivery_confirmation', ['mids' => $mids]);
    }
    
    /**
     * Processa confirmação de leitura
     */
    private function processRead(array $event): void
    {
        $read = $event['read'];
        $watermark = $read['watermark'];
        
        // Atualizar todas as mensagens até o watermark
        $stmt = $this->pdo->prepare("
            UPDATE messages 
            SET status = 'read'
            WHERE channel_id = ? 
            AND from_me = TRUE 
            AND UNIX_TIMESTAMP(created_at) <= ?
        ");
        $stmt->execute([$this->channelId, $watermark]);
        
        $this->logActivity('read_confirmation', ['watermark' => $watermark]);
    }
    
    /**
     * Obtém informações do usuário
     */
    private function getUserInfo(string $userId): array
    {
        try {
            $url = self::GRAPH_API_URL . '/' . self::GRAPH_API_VERSION . '/' . $userId;
            $url .= '?fields=name,first_name,last_name,profile_pic&access_token=' . urlencode($this->pageAccessToken);
            
            $result = $this->makeHttpRequest($url);
            
            if ($result['success'] && isset($result['response']['name'])) {
                return $result['response'];
            }
            
            return ['name' => 'Usuário Facebook'];
            
        } catch (Exception $e) {
            return ['name' => 'Usuário Facebook'];
        }
    }
    
    /**
     * Envia template de mensagem
     */
    public function sendTemplate(array $template): array
    {
        try {
            $recipientId = $template['recipient_id'];
            
            $url = self::GRAPH_API_URL . '/' . self::GRAPH_API_VERSION . '/me/messages';
            
            $data = [
                'recipient' => ['id' => $recipientId],
                'message' => [
                    'attachment' => [
                        'type' => 'template',
                        'payload' => $template['payload']
                    ]
                ],
                'messaging_type' => 'RESPONSE',
                'access_token' => $this->pageAccessToken
            ];
            
            $result = $this->makeHttpRequest($url, 'POST', $data, ['Content-Type: application/json']);
            
            if ($result['success'] && isset($result['response']['message_id'])) {
                return [
                    'success' => true,
                    'message_id' => $result['response']['message_id']
                ];
            }
            
            return [
                'success' => false,
                'error' => $result['response']['error']['message'] ?? 'Erro ao enviar template'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
