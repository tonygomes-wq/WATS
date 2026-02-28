<?php

namespace App\Services;

use App\Repositories\MessageRepository;
use App\Repositories\ConversationRepository;
use InvalidArgumentException;

/**
 * Service para Mensagens do Chat
 * 
 * Contém toda a lógica de negócio relacionada a mensagens.
 */
class MessageService extends BaseService
{
    private MessageRepository $messageRepo;
    private ConversationRepository $conversationRepo;
    
    public function __construct(
        MessageRepository $messageRepo,
        ConversationRepository $conversationRepo
    ) {
        $this->messageRepo = $messageRepo;
        $this->conversationRepo = $conversationRepo;
    }
    
    /**
     * Listar mensagens de uma conversa
     */
    public function listMessages(int $userId, int $conversationId, array $options = []): array
    {
        // Verificar se conversa existe e pertence ao usuário
        $conversation = $this->conversationRepo->findById($conversationId);
        if (!$conversation) {
            throw new InvalidArgumentException('Conversa não encontrada');
        }
        
        // Buscar mensagens
        $messages = $this->messageRepo->findByConversation($conversationId, $options);
        $total = $this->messageRepo->countByConversation($conversationId);
        
        // Formatar mensagens
        foreach ($messages as &$msg) {
            $msg = $this->formatMessage($msg);
        }
        
        // Marcar como lidas automaticamente
        $this->messageRepo->markAllAsRead($conversationId);
        $this->conversationRepo->update($conversationId, ['unread_count' => 0]);
        
        return [
            'success' => true,
            'conversation' => [
                'id' => (int) $conversation['id'],
                'phone' => $conversation['phone'] ?? '',
                'contact_name' => $conversation['contact_name'] ?? $conversation['phone'] ?? '',
                'profile_pic_url' => $conversation['profile_pic_url'] ?? null
            ],
            'messages' => $messages,
            'total' => $total,
            'has_more' => count($messages) === ($options['limit'] ?? 50)
        ];
    }
    
    /**
     * Enviar mensagem
     */
    public function sendMessage(int $userId, int $conversationId, array $data): array
    {
        // Verificar se conversa pertence ao usuário
        if (!$this->conversationRepo->belongsToUser($conversationId, $userId)) {
            throw new InvalidArgumentException('Conversa não encontrada');
        }
        
        // Validar dados
        if (empty($data['message_text']) && empty($data['media_url'])) {
            throw new InvalidArgumentException('Mensagem ou mídia é obrigatória');
        }
        
        // Criar mensagem
        $messageId = $this->messageRepo->create([
            'conversation_id' => $conversationId,
            'message_id' => $data['message_id'] ?? uniqid('msg_'),
            'from_me' => 1,
            'message_type' => $data['message_type'] ?? 'text',
            'message_text' => $data['message_text'] ?? null,
            'media_url' => $data['media_url'] ?? null,
            'media_mimetype' => $data['media_mimetype'] ?? null,
            'media_filename' => $data['media_filename'] ?? null,
            'media_size' => $data['media_size'] ?? null,
            'caption' => $data['caption'] ?? null,
            'quoted_message_id' => $data['quoted_message_id'] ?? null,
            'status' => 'pending',
            'timestamp' => time(),
            'user_id' => $userId
        ]);
        
        // Atualizar última mensagem da conversa
        $this->conversationRepo->update($conversationId, [
            'last_message_text' => $data['message_text'] ?? '[Mídia]',
            'last_message_time' => date('Y-m-d H:i:s')
        ]);
        
        return [
            'success' => true,
            'message_id' => $messageId,
            'message' => 'Mensagem enviada'
        ];
    }
    
    /**
     * Deletar mensagem
     */
    public function deleteMessage(int $userId, int $messageId): array
    {
        // Buscar mensagem
        $message = $this->messageRepo->findById($messageId);
        if (!$message) {
            throw new InvalidArgumentException('Mensagem não encontrada');
        }
        
        // Verificar se conversa pertence ao usuário
        if (!$this->conversationRepo->belongsToUser($message['conversation_id'], $userId)) {
            throw new InvalidArgumentException('Sem permissão para deletar esta mensagem');
        }
        
        $success = $this->messageRepo->delete($messageId);
        
        return [
            'success' => $success,
            'message' => $success ? 'Mensagem deletada' : 'Erro ao deletar mensagem'
        ];
    }
    
    /**
     * Atualizar status da mensagem
     */
    public function updateMessageStatus(int $messageId, string $status): bool
    {
        $validStatuses = ['pending', 'sent', 'delivered', 'read', 'failed'];
        if (!in_array($status, $validStatuses)) {
            throw new InvalidArgumentException('Status inválido');
        }
        
        return $this->messageRepo->updateStatus($messageId, $status);
    }
    
    /**
     * Formatar mensagem para resposta
     */
    private function formatMessage(array $msg): array
    {
        $formatted = [
            'id' => (int) $msg['id'],
            'message_id' => $msg['message_id'] ?? null,
            'from_me' => (bool) ($msg['from_me'] ?? false),
            'message_type' => $msg['message_type'] ?? 'text',
            'message_text' => $msg['message_text'] ?? '',
            'media_url' => $msg['media_url'] ?? null,
            'media_mimetype' => $msg['media_mimetype'] ?? null,
            'media_filename' => $msg['media_filename'] ?? null,
            'media_size' => (int) ($msg['media_size'] ?? 0),
            'caption' => $msg['caption'] ?? null,
            'quoted_message_id' => $msg['quoted_message_id'] ? (int) $msg['quoted_message_id'] : null,
            'status' => $msg['status'] ?? 'pending',
            'timestamp' => (int) ($msg['timestamp'] ?? 0),
            'created_at' => $msg['created_at'] ?? null,
            'is_read' => !empty($msg['read_at']),
            'has_media' => !empty($msg['media_url'])
        ];
        
        // Formatar data/hora
        if (!empty($msg['created_at'])) {
            $formatted['created_at_formatted'] = date('d/m/Y H:i', strtotime($msg['created_at']));
            $formatted['time_formatted'] = $this->formatMessageDateTime($msg['created_at']);
        } else {
            $formatted['created_at_formatted'] = date('d/m/Y H:i', $msg['timestamp']);
            $formatted['time_formatted'] = $this->formatMessageDateTime(date('Y-m-d H:i:s', $msg['timestamp']));
        }
        
        // Texto padrão para mídias
        if (empty($formatted['message_text']) && $formatted['message_type'] !== 'text') {
            $formatted['message_text'] = $this->getMediaPlaceholder($formatted['message_type']);
        }
        
        // Formatar tamanho de arquivo
        if ($formatted['has_media'] && $formatted['media_size'] > 0) {
            $formatted['media_size_formatted'] = $this->formatBytes($formatted['media_size']);
        }
        
        return $formatted;
    }
    
    /**
     * Placeholder para tipos de mídia
     */
    private function getMediaPlaceholder(string $type): string
    {
        $placeholders = [
            'image' => '[Imagem enviada]',
            'document' => '[Documento enviado]',
            'audio' => '[Áudio enviado]',
            'video' => '[Vídeo enviado]',
            'sticker' => '[Figurinha enviada]'
        ];
        
        return $placeholders[$type] ?? '[Mensagem de mídia]';
    }
    
    /**
     * Formatar data/hora da mensagem
     */
    private function formatMessageDateTime(string $datetime): string
    {
        $time = strtotime($datetime);
        $now = time();
        
        // Hoje
        if (date('Y-m-d', $time) === date('Y-m-d', $now)) {
            return 'Hoje às ' . date('H:i', $time);
        }
        
        // Ontem
        if (date('Y-m-d', $time) === date('Y-m-d', $now - 86400)) {
            return 'Ontem às ' . date('H:i', $time);
        }
        
        // Esta semana
        $diff = $now - $time;
        if ($diff < 604800) {
            $days = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
            return $days[date('w', $time)] . ' às ' . date('H:i', $time);
        }
        
        // Mais antigo
        return date('d/m/Y H:i', $time);
    }
    
    /**
     * Formatar tamanho de arquivo
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log(1024));
        
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }
}
