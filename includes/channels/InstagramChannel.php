<?php
/**
 * Instagram Channel Implementation
 * Integração com Instagram Graph API para mensagens diretas
 */

require_once __DIR__ . '/BaseChannel.php';

class InstagramChannel extends BaseChannel {
    
    protected $channelType = 'instagram';
    
    /**
     * Validar credenciais do Instagram
     */
    public function validateCredentials() {
        $accessToken = $this->config['access_token'] ?? '';
        $instagramAccountId = $this->config['instagram_account_id'] ?? '';
        
        if (empty($accessToken) || empty($instagramAccountId)) {
            throw new Exception('Access Token e Instagram Account ID são obrigatórios');
        }
        
        // Verificar se o token é válido
        $url = "https://graph.facebook.com/v18.0/{$instagramAccountId}?fields=id,username&access_token={$accessToken}";
        
        $response = $this->makeRequest('GET', $url);
        
        if (!isset($response['id'])) {
            throw new Exception('Token inválido ou conta não encontrada');
        }
        
        return [
            'valid' => true,
            'account_id' => $response['id'],
            'username' => $response['username'] ?? 'Instagram'
        ];
    }
    
    /**
     * Configurar webhook do Instagram
     */
    public function setupWebhook($webhookUrl) {
        // Instagram usa o mesmo sistema de webhooks do Facebook
        // A configuração é feita no Facebook App Dashboard
        
        $pageId = $this->config['page_id'] ?? '';
        $accessToken = $this->config['access_token'] ?? '';
        
        if (empty($pageId)) {
            throw new Exception('Page ID é obrigatório para configurar webhook');
        }
        
        // Subscrever aos eventos do Instagram
        $url = "https://graph.facebook.com/v18.0/{$pageId}/subscribed_apps";
        
        $response = $this->makeRequest('POST', $url, [
            'subscribed_fields' => 'messages,messaging_postbacks,message_deliveries,message_reads',
            'access_token' => $accessToken
        ]);
        
        if (!isset($response['success']) || !$response['success']) {
            throw new Exception('Falha ao configurar webhook do Instagram');
        }
        
        return true;
    }
    
    /**
     * Enviar mensagem de texto
     */
    public function sendMessage($recipientId, $message) {
        $instagramAccountId = $this->config['instagram_account_id'] ?? '';
        $accessToken = $this->config['access_token'] ?? '';
        
        $url = "https://graph.facebook.com/v18.0/{$instagramAccountId}/messages";
        
        $data = [
            'recipient' => ['id' => $recipientId],
            'message' => ['text' => $message],
            'access_token' => $accessToken
        ];
        
        $response = $this->makeRequest('POST', $url, $data);
        
        if (isset($response['error'])) {
            throw new Exception('Erro ao enviar mensagem: ' . $response['error']['message']);
        }
        
        // Criar registro da mensagem
        $this->createMessage([
            'recipient_id' => $recipientId,
            'message_text' => $message,
            'message_type' => 'text',
            'external_id' => $response['message_id'] ?? null
        ]);
        
        $this->logActivity('message_sent', [
            'recipient_id' => $recipientId,
            'message_id' => $response['message_id'] ?? null
        ]);
        
        return $response;
    }
    
    /**
     * Enviar anexo (imagem, vídeo, etc)
     */
    public function sendAttachment($recipientId, $attachmentType, $attachmentUrl) {
        $instagramAccountId = $this->config['instagram_account_id'] ?? '';
        $accessToken = $this->config['access_token'] ?? '';
        
        $url = "https://graph.facebook.com/v18.0/{$instagramAccountId}/messages";
        
        $data = [
            'recipient' => ['id' => $recipientId],
            'message' => [
                'attachment' => [
                    'type' => $attachmentType,
                    'payload' => [
                        'url' => $attachmentUrl,
                        'is_reusable' => true
                    ]
                ]
            ],
            'access_token' => $accessToken
        ];
        
        $response = $this->makeRequest('POST', $url, $data);
        
        if (isset($response['error'])) {
            throw new Exception('Erro ao enviar anexo: ' . $response['error']['message']);
        }
        
        // Criar registro da mensagem
        $this->createMessage([
            'recipient_id' => $recipientId,
            'message_type' => $attachmentType,
            'media_url' => $attachmentUrl,
            'external_id' => $response['message_id'] ?? null
        ]);
        
        return $response;
    }
    
    /**
     * Processar webhook recebido
     */
    public function processWebhook($payload) {
        if (!isset($payload['entry'])) {
            return false;
        }
        
        foreach ($payload['entry'] as $entry) {
            if (!isset($entry['messaging'])) {
                continue;
            }
            
            foreach ($entry['messaging'] as $event) {
                // Processar mensagem recebida
                if (isset($event['message'])) {
                    $this->processIncomingMessage($event);
                }
                
                // Processar postback (botões)
                if (isset($event['postback'])) {
                    $this->processPostback($event);
                }
                
                // Processar delivery
                if (isset($event['delivery'])) {
                    $this->processDelivery($event);
                }
                
                // Processar leitura
                if (isset($event['read'])) {
                    $this->processRead($event);
                }
            }
        }
        
        return true;
    }
    
    /**
     * Processar mensagem recebida
     */
    private function processIncomingMessage($event) {
        $senderId = $event['sender']['id'];
        $message = $event['message'];
        
        // Criar ou atualizar contato
        $contact = $this->createOrUpdateContact([
            'external_id' => $senderId,
            'name' => $this->getUserInfo($senderId)['username'] ?? 'Instagram User',
            'source' => 'instagram'
        ]);
        
        // Dados da mensagem
        $messageData = [
            'contact_id' => $contact['id'],
            'external_id' => $message['mid'] ?? null,
            'from_me' => false
        ];
        
        // Processar texto
        if (isset($message['text'])) {
            $messageData['message_text'] = $message['text'];
            $messageData['message_type'] = 'text';
        }
        
        // Processar anexos
        if (isset($message['attachments'])) {
            foreach ($message['attachments'] as $attachment) {
                $messageData['message_type'] = $attachment['type'];
                $messageData['media_url'] = $attachment['payload']['url'] ?? null;
            }
        }
        
        // Criar mensagem no banco
        $this->createMessage($messageData);
        
        $this->logActivity('message_received', [
            'sender_id' => $senderId,
            'message_id' => $message['mid'] ?? null
        ]);
    }
    
    /**
     * Processar postback (clique em botão)
     */
    private function processPostback($event) {
        $senderId = $event['sender']['id'];
        $payload = $event['postback']['payload'] ?? '';
        
        $this->logActivity('postback_received', [
            'sender_id' => $senderId,
            'payload' => $payload
        ]);
    }
    
    /**
     * Processar confirmação de entrega
     */
    private function processDelivery($event) {
        $mids = $event['delivery']['mids'] ?? [];
        
        foreach ($mids as $mid) {
            // Atualizar status da mensagem
            $this->updateMessageStatus($mid, 'delivered');
        }
    }
    
    /**
     * Processar confirmação de leitura
     */
    private function processRead($event) {
        $watermark = $event['read']['watermark'] ?? 0;
        
        // Marcar mensagens como lidas até o timestamp do watermark
        $this->markMessagesAsRead($watermark);
    }
    
    /**
     * Obter informações do usuário
     */
    private function getUserInfo($userId) {
        $accessToken = $this->config['access_token'] ?? '';
        
        $url = "https://graph.facebook.com/v18.0/{$userId}?fields=id,username,profile_pic&access_token={$accessToken}";
        
        try {
            $response = $this->makeRequest('GET', $url);
            return $response;
        } catch (Exception $e) {
            return ['username' => 'Instagram User'];
        }
    }
    
    /**
     * Marcar mensagem como lida
     */
    public function markAsRead($senderId) {
        $instagramAccountId = $this->config['instagram_account_id'] ?? '';
        $accessToken = $this->config['access_token'] ?? '';
        
        $url = "https://graph.facebook.com/v18.0/{$instagramAccountId}/messages";
        
        $data = [
            'recipient' => ['id' => $senderId],
            'sender_action' => 'mark_seen',
            'access_token' => $accessToken
        ];
        
        return $this->makeRequest('POST', $url, $data);
    }
    
    /**
     * Obter informações do canal
     */
    public function getChannelInfo() {
        $instagramAccountId = $this->config['instagram_account_id'] ?? '';
        $accessToken = $this->config['access_token'] ?? '';
        
        $url = "https://graph.facebook.com/v18.0/{$instagramAccountId}?fields=id,username,profile_picture_url,followers_count&access_token={$accessToken}";
        
        try {
            $response = $this->makeRequest('GET', $url);
            return [
                'id' => $response['id'] ?? null,
                'username' => $response['username'] ?? 'Instagram',
                'profile_picture' => $response['profile_picture_url'] ?? null,
                'followers' => $response['followers_count'] ?? 0
            ];
        } catch (Exception $e) {
            return [
                'id' => $instagramAccountId,
                'username' => 'Instagram',
                'error' => $e->getMessage()
            ];
        }
    }
}
