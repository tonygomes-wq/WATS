<?php
/**
 * Processador de Webhooks da Meta API (WhatsApp Business API)
 * Processa mensagens recebidas, status updates, etc.
 */

class MetaWebhookProcessor {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Processa o payload do webhook da Meta
     */
    public function process($payload) {
        error_log("[META_PROCESSOR] Payload: " . json_encode($payload));
        
        if (!isset($payload['entry']) || !is_array($payload['entry'])) {
            error_log("[META_PROCESSOR] Payload inválido - sem entry");
            return ['success' => false, 'error' => 'Invalid payload'];
        }
        
        foreach ($payload['entry'] as $entry) {
            if (!isset($entry['changes'])) {
                continue;
            }
            
            foreach ($entry['changes'] as $change) {
                $this->processChange($change);
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Processa uma mudança (change) do webhook
     */
    private function processChange($change) {
        $value = $change['value'] ?? [];
        
        // Processar mensagens recebidas
        if (isset($value['messages']) && is_array($value['messages'])) {
            foreach ($value['messages'] as $message) {
                $this->processIncomingMessage($message, $value);
            }
        }
        
        // Processar status de mensagens enviadas
        if (isset($value['statuses']) && is_array($value['statuses'])) {
            foreach ($value['statuses'] as $status) {
                $this->processMessageStatus($status);
            }
        }
    }
    
    /**
     * Processa mensagem recebida
     */
    private function processIncomingMessage($message, $metadata) {
        $messageId = $message['id'] ?? null;
        $from = $message['from'] ?? null;
        $timestamp = $message['timestamp'] ?? time();
        $type = $message['type'] ?? 'text';
        
        if (!$messageId || !$from) {
            error_log("[META_PROCESSOR] Mensagem inválida - sem ID ou remetente");
            return;
        }
        
        // Identificar usuário pelo phone_number_id
        $phoneNumberId = $metadata['metadata']['phone_number_id'] ?? null;
        if (!$phoneNumberId) {
            error_log("[META_PROCESSOR] Phone number ID não encontrado");
            return;
        }
        
        $user = $this->findUserByPhoneNumberId($phoneNumberId);
        if (!$user) {
            error_log("[META_PROCESSOR] Usuário não encontrado para phone_number_id: $phoneNumberId");
            return;
        }
        
        // Verificar se mensagem já existe
        $existing = $this->findMessageByExternalId($messageId);
        if ($existing) {
            error_log("[META_PROCESSOR] Mensagem já existe: $messageId");
            return;
        }
        
        // Processar mídia se houver
        $mediaPath = null;
        $messageText = '';
        $mimeType = null;
        $filename = null;
        
        if ($type !== 'text') {
            require_once __DIR__ . '/../includes/MetaMediaHandler.php';
            require_once __DIR__ . '/../includes/TokenEncryption.php';
            
            // Buscar configurações completas do usuário
            $userConfig = $this->getUserConfig($user['id']);
            
            if ($userConfig) {
                $mediaHandler = new MetaMediaHandler($this->pdo);
                $mediaResult = $mediaHandler->processIncomingMedia($message, $userConfig);
                
                if ($mediaResult['success']) {
                    $mediaPath = $mediaResult['media_path'];
                    $messageText = $mediaResult['caption'] ?? '';
                    $mimeType = $mediaResult['mime_type'] ?? null;
                    $filename = $mediaResult['filename'] ?? null;
                    error_log("[META_PROCESSOR] Mídia processada: $mediaPath");
                } else {
                    error_log("[META_PROCESSOR] Erro ao processar mídia: " . ($mediaResult['error'] ?? 'desconhecido'));
                }
            }
        } else {
            // Mensagem de texto
            $messageText = $message['text']['body'] ?? '';
        }
        
        // Salvar mensagem no banco
        $this->saveIncomingMessage([
            'user_id' => $user['id'],
            'external_id' => $messageId,
            'remote_jid' => $from,
            'message_text' => $messageText,
            'message_type' => $type,
            'from_me' => false,
            'timestamp' => $timestamp,
            'provider' => 'meta',
            'media_path' => $mediaPath,
            'mime_type' => $mimeType,
            'filename' => $filename
        ]);
        
        error_log("[META_PROCESSOR] Mensagem salva: $messageId de $from");
    }
    
    /**
     * Processa atualização de status de mensagem
     */
    private function processMessageStatus($status) {
        $messageId = $status['id'] ?? null;
        $statusValue = $status['status'] ?? null;
        $timestamp = $status['timestamp'] ?? time();
        
        if (!$messageId || !$statusValue) {
            error_log("[META_PROCESSOR] Status inválido");
            return;
        }
        
        // Mapear status da Meta para status interno
        $internalStatus = $this->mapMetaStatus($statusValue);
        
        // Atualizar status da mensagem
        $this->updateMessageStatus($messageId, $internalStatus, $timestamp);
        
        error_log("[META_PROCESSOR] Status atualizado: $messageId -> $internalStatus");
    }
    
    /**
     * Mapeia status da Meta para status interno
     */
    private function mapMetaStatus($metaStatus) {
        $mapping = [
            'sent' => 'sent',
            'delivered' => 'delivered',
            'read' => 'read',
            'failed' => 'failed'
        ];
        
        return $mapping[$metaStatus] ?? 'sent';
    }
    
    /**
     * Obtém configuração completa do usuário para processar mídias
     */
    private function getUserConfig($userId) {
        require_once __DIR__ . '/../includes/TokenEncryption.php';
        $encryption = new TokenEncryption();
        
        $stmt = $this->pdo->prepare("
            SELECT 
                meta_phone_number_id,
                meta_permanent_token,
                meta_api_version
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$config) {
            return null;
        }
        
        // Descriptografar token se necessário
        if (!empty($config['meta_permanent_token'])) {
            $decrypted = $encryption->decrypt($config['meta_permanent_token']);
            if ($decrypted) {
                $config['meta_permanent_token'] = $decrypted;
            }
        }
        
        return $config;
    }
    
    /**
     * Encontra usuário pelo phone_number_id da Meta
     */
    private function findUserByPhoneNumberId($phoneNumberId) {
        $stmt = $this->pdo->prepare("
            SELECT id, name, email 
            FROM users 
            WHERE meta_phone_number_id = ? 
            AND whatsapp_provider = 'meta'
        ");
        $stmt->execute([$phoneNumberId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Encontra mensagem pelo ID externo
     */
    private function findMessageByExternalId($externalId) {
        $stmt = $this->pdo->prepare("
            SELECT id 
            FROM chat_messages 
            WHERE external_message_id = ?
        ");
        $stmt->execute([$externalId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Salva mensagem recebida no banco
     */
    private function saveIncomingMessage($data) {
        // Verificar se conversa existe
        $conversationId = $this->getOrCreateConversation(
            $data['user_id'],
            $data['remote_jid']
        );
        
        $stmt = $this->pdo->prepare("
            INSERT INTO chat_messages (
                user_id,
                conversation_id,
                external_message_id,
                remote_jid,
                message_text,
                message_type,
                from_me,
                status,
                created_at,
                provider,
                media_path,
                mime_type,
                filename
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'received', FROM_UNIXTIME(?), ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['user_id'],
            $conversationId,
            $data['external_id'],
            $data['remote_jid'],
            $data['message_text'],
            $data['message_type'],
            $data['from_me'] ? 1 : 0,
            $data['timestamp'],
            $data['provider'],
            $data['media_path'] ?? null,
            $data['mime_type'] ?? null,
            $data['filename'] ?? null
        ]);
    }
    
    /**
     * Obtém ou cria conversa
     */
    private function getOrCreateConversation($userId, $remoteJid) {
        // Buscar conversa existente
        $stmt = $this->pdo->prepare("
            SELECT id 
            FROM chat_conversations 
            WHERE user_id = ? AND remote_jid = ?
        ");
        $stmt->execute([$userId, $remoteJid]);
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($conversation) {
            return $conversation['id'];
        }
        
        // Criar nova conversa
        $stmt = $this->pdo->prepare("
            INSERT INTO chat_conversations (
                user_id,
                remote_jid,
                contact_name,
                last_message_at,
                unread_count
            ) VALUES (?, ?, ?, NOW(), 1)
        ");
        $stmt->execute([$userId, $remoteJid, $remoteJid]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Atualiza status de mensagem
     */
    private function updateMessageStatus($externalId, $status, $timestamp) {
        $readAt = ($status === 'read') ? 'FROM_UNIXTIME(?)' : 'read_at';
        
        $sql = "
            UPDATE chat_messages 
            SET status = ?, 
                read_at = $readAt
            WHERE external_message_id = ?
        ";
        
        $params = [$status];
        if ($status === 'read') {
            $params[] = $timestamp;
        }
        $params[] = $externalId;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }
}
