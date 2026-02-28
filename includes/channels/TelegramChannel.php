<?php
/**
 * Canal Telegram
 * Implementa integração com Telegram Bot API
 * Baseado em: chatwoot-4.10.0/app/models/channel/telegram.rb
 */

require_once __DIR__ . '/BaseChannel.php';

class TelegramChannel extends BaseChannel
{
    private string $botToken;
    private string $botName;
    private string $botUsername;
    private const API_URL = 'https://api.telegram.org/bot';
    
    protected function loadConfig(): void
    {
        parent::loadConfig();
        
        // Carregar configurações específicas do Telegram
        $stmt = $this->pdo->prepare("
            SELECT * FROM channel_telegram 
            WHERE channel_id = ?
        ");
        $stmt->execute([$this->channelId]);
        $telegramConfig = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$telegramConfig) {
            throw new Exception("Configuração do Telegram não encontrada");
        }
        
        $this->botToken = $telegramConfig['bot_token'];
        $this->botName = $telegramConfig['bot_name'] ?? '';
        $this->botUsername = $telegramConfig['bot_username'] ?? '';
    }
    
    public function getName(): string
    {
        return 'Telegram';
    }
    
    public function getType(): string
    {
        return 'telegram';
    }
    
    /**
     * Valida token do bot
     */
    public function validateCredentials(): bool
    {
        try {
            $url = self::API_URL . $this->botToken . '/getMe';
            $result = $this->makeHttpRequest($url);
            
            if ($result['success'] && isset($result['response']['ok']) && $result['response']['ok']) {
                $botInfo = $result['response']['result'];
                $this->botName = $botInfo['first_name'];
                $this->botUsername = $botInfo['username'];
                
                // Atualizar informações do bot
                $stmt = $this->pdo->prepare("
                    UPDATE channel_telegram 
                    SET bot_name = ?, bot_username = ?
                    WHERE channel_id = ?
                ");
                $stmt->execute([$this->botName, $this->botUsername, $this->channelId]);
                
                $this->updateChannelStatus('active');
                return true;
            }
            
            $this->updateChannelStatus('error', 'Token inválido');
            return false;
            
        } catch (Exception $e) {
            $this->logActivity('validate_credentials', [], 'error', $e->getMessage());
            $this->updateChannelStatus('error', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Configura webhook no Telegram
     */
    public function setupWebhook(): bool
    {
        try {
            // Deletar webhook existente
            $deleteUrl = self::API_URL . $this->botToken . '/deleteWebhook';
            $this->makeHttpRequest($deleteUrl, 'POST');
            
            // Configurar novo webhook
            $webhookUrl = SITE_URL . "/api/webhooks/telegram.php?token=" . urlencode($this->botToken);
            $setUrl = self::API_URL . $this->botToken . '/setWebhook';
            
            $result = $this->makeHttpRequest($setUrl, 'POST', [
                'url' => $webhookUrl,
                'allowed_updates' => ['message', 'edited_message', 'callback_query']
            ], ['Content-Type: application/json']);
            
            if ($result['success'] && isset($result['response']['ok']) && $result['response']['ok']) {
                // Atualizar status do webhook
                $stmt = $this->pdo->prepare("
                    UPDATE channel_telegram 
                    SET webhook_url = ?, webhook_verified = TRUE
                    WHERE channel_id = ?
                ");
                $stmt->execute([$webhookUrl, $this->channelId]);
                
                $this->logActivity('setup_webhook', ['webhook_url' => $webhookUrl]);
                return true;
            }
            
            $errorMsg = $result['response']['description'] ?? 'Erro ao configurar webhook';
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
            $chatId = $message['chat_id'];
            $text = $message['text'];
            
            $url = self::API_URL . $this->botToken . '/sendMessage';
            
            $data = [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML'
            ];
            
            // Responder a mensagem específica
            if (isset($message['reply_to_message_id'])) {
                $data['reply_to_message_id'] = $message['reply_to_message_id'];
            }
            
            // Adicionar teclado inline se houver
            if (isset($message['inline_keyboard'])) {
                $data['reply_markup'] = json_encode([
                    'inline_keyboard' => $message['inline_keyboard']
                ]);
            }
            
            $result = $this->makeHttpRequest($url, 'POST', $data, ['Content-Type: application/json']);
            
            if ($result['success'] && isset($result['response']['ok']) && $result['response']['ok']) {
                $messageId = $result['response']['result']['message_id'];
                
                $this->logActivity('send_message', [
                    'chat_id' => $chatId,
                    'message_id' => $messageId
                ]);
                
                return [
                    'success' => true,
                    'message_id' => $messageId,
                    'external_id' => $messageId
                ];
            }
            
            $errorMsg = $result['response']['description'] ?? 'Erro ao enviar mensagem';
            $this->logActivity('send_message', ['chat_id' => $chatId], 'error', $errorMsg);
            
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
     * Envia anexo (foto, vídeo, documento)
     */
    public function sendAttachment(array $attachment): array
    {
        try {
            $chatId = $attachment['chat_id'];
            $type = $attachment['type']; // photo, video, document, audio
            $fileUrl = $attachment['file_url'];
            $caption = $attachment['caption'] ?? '';
            
            $methodMap = [
                'photo' => 'sendPhoto',
                'video' => 'sendVideo',
                'document' => 'sendDocument',
                'audio' => 'sendAudio',
                'voice' => 'sendVoice'
            ];
            
            $method = $methodMap[$type] ?? 'sendDocument';
            $url = self::API_URL . $this->botToken . '/' . $method;
            
            $fieldMap = [
                'photo' => 'photo',
                'video' => 'video',
                'document' => 'document',
                'audio' => 'audio',
                'voice' => 'voice'
            ];
            
            $field = $fieldMap[$type] ?? 'document';
            
            $data = [
                'chat_id' => $chatId,
                $field => $fileUrl,
                'caption' => $caption
            ];
            
            $result = $this->makeHttpRequest($url, 'POST', $data, ['Content-Type: application/json']);
            
            if ($result['success'] && isset($result['response']['ok']) && $result['response']['ok']) {
                return [
                    'success' => true,
                    'message_id' => $result['response']['result']['message_id']
                ];
            }
            
            return [
                'success' => false,
                'error' => $result['response']['description'] ?? 'Erro ao enviar anexo'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Processa webhook recebido
     */
    public function receiveWebhook(array $payload): array
    {
        try {
            $this->updateLastSync();
            
            // Processar mensagem normal
            if (isset($payload['message'])) {
                return $this->processMessage($payload['message']);
            }
            
            // Processar mensagem editada
            if (isset($payload['edited_message'])) {
                return $this->processEditedMessage($payload['edited_message']);
            }
            
            // Processar callback de botão inline
            if (isset($payload['callback_query'])) {
                return $this->processCallbackQuery($payload['callback_query']);
            }
            
            return ['success' => true, 'message' => 'Evento não processado'];
            
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
    private function processMessage(array $message): array
    {
        $chatId = $message['chat']['id'];
        $from = $message['from'];
        $messageId = $message['message_id'];
        
        // Ignorar mensagens de bots
        if (isset($from['is_bot']) && $from['is_bot']) {
            return ['success' => true, 'message' => 'Mensagem de bot ignorada'];
        }
        
        // Criar ou atualizar contato
        $contactName = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''));
        if (empty($contactName)) {
            $contactName = $from['username'] ?? 'Usuário Telegram';
        }
        
        $contactId = $this->createOrUpdateContact([
            'name' => $contactName,
            'phone' => 'telegram_' . $from['id'],
            'source' => 'telegram',
            'source_id' => (string)$from['id']
        ]);
        
        // Determinar tipo de mensagem
        $messageType = 'text';
        $messageText = '';
        $additionalData = [
            'chat_id' => $chatId,
            'from_id' => $from['id'],
            'username' => $from['username'] ?? null
        ];
        
        if (isset($message['text'])) {
            $messageText = $message['text'];
            $messageType = 'text';
        } elseif (isset($message['photo'])) {
            $messageText = $message['caption'] ?? '[Foto]';
            $messageType = 'photo';
            $additionalData['photo'] = end($message['photo']);
        } elseif (isset($message['video'])) {
            $messageText = $message['caption'] ?? '[Vídeo]';
            $messageType = 'video';
            $additionalData['video'] = $message['video'];
        } elseif (isset($message['document'])) {
            $messageText = $message['caption'] ?? '[Documento]';
            $messageType = 'document';
            $additionalData['document'] = $message['document'];
        } elseif (isset($message['audio'])) {
            $messageText = '[Áudio]';
            $messageType = 'audio';
            $additionalData['audio'] = $message['audio'];
        } elseif (isset($message['voice'])) {
            $messageText = '[Mensagem de voz]';
            $messageType = 'voice';
            $additionalData['voice'] = $message['voice'];
        } elseif (isset($message['sticker'])) {
            $messageText = '[Sticker]';
            $messageType = 'sticker';
            $additionalData['sticker'] = $message['sticker'];
        } elseif (isset($message['location'])) {
            $messageText = '[Localização]';
            $messageType = 'location';
            $additionalData['location'] = $message['location'];
        }
        
        // Criar mensagem
        $newMessageId = $this->createMessage([
            'contact_id' => $contactId,
            'external_id' => (string)$messageId,
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
        
        return [
            'success' => true,
            'message_id' => $newMessageId,
            'contact_id' => $contactId
        ];
    }
    
    /**
     * Processa mensagem editada
     */
    private function processEditedMessage(array $message): array
    {
        $messageId = $message['message_id'];
        $newText = $message['text'] ?? $message['caption'] ?? '';
        
        // Atualizar mensagem existente
        $stmt = $this->pdo->prepare("
            UPDATE messages 
            SET message_text = ?, updated_at = NOW()
            WHERE channel_id = ? AND external_id = ?
        ");
        
        $stmt->execute([$newText, $this->channelId, (string)$messageId]);
        
        $this->logActivity('edit_message', ['message_id' => $messageId]);
        
        return ['success' => true, 'message' => 'Mensagem atualizada'];
    }
    
    /**
     * Processa callback de botão inline
     */
    private function processCallbackQuery(array $callback): array
    {
        $callbackId = $callback['id'];
        $data = $callback['data'] ?? '';
        
        // Responder ao callback
        $url = self::API_URL . $this->botToken . '/answerCallbackQuery';
        $this->makeHttpRequest($url, 'POST', [
            'callback_query_id' => $callbackId,
            'text' => 'Recebido!'
        ], ['Content-Type: application/json']);
        
        $this->logActivity('callback_query', ['data' => $data]);
        
        return ['success' => true, 'callback_data' => $data];
    }
    
    /**
     * Obtém URL do arquivo
     */
    public function getFileUrl(string $fileId): ?string
    {
        try {
            $url = self::API_URL . $this->botToken . '/getFile';
            $result = $this->makeHttpRequest($url, 'POST', ['file_id' => $fileId], ['Content-Type: application/json']);
            
            if ($result['success'] && isset($result['response']['result']['file_path'])) {
                $filePath = $result['response']['result']['file_path'];
                return "https://api.telegram.org/file/bot{$this->botToken}/{$filePath}";
            }
            
            return null;
        } catch (Exception $e) {
            return null;
        }
    }
}
