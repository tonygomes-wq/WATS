<?php

namespace App\Repositories;

use PDO;

/**
 * Repository para Mensagens do Chat
 * 
 * Responsável por todas as operações de banco de dados relacionadas a mensagens.
 */
class MessageRepository extends BaseRepository
{
    protected string $table = 'chat_messages';
    
    /**
     * Buscar mensagens de uma conversa
     * 
     * @param int $conversationId ID da conversa
     * @param array $options Opções (limit, offset, before_id)
     * @return array Lista de mensagens
     */
    public function findByConversation(int $conversationId, array $options = []): array
    {
        $limit = min((int)($options['limit'] ?? 50), 500);
        $offset = (int)($options['offset'] ?? 0);
        $beforeId = (int)($options['before_id'] ?? 0);
        
        $sql = "
            SELECT 
                m.id,
                m.message_id,
                m.from_me,
                m.message_type,
                m.message_text,
                m.media_url,
                m.media_mimetype,
                m.media_filename,
                m.media_size,
                m.caption,
                m.quoted_message_id,
                m.status,
                m.timestamp,
                FROM_UNIXTIME(m.timestamp) as created_at,
                m.read_at,
                m.user_id as sender_user_id
            FROM {$this->table} m
            WHERE m.conversation_id = ?
        ";
        
        $params = [$conversationId];
        
        // Paginação por ID
        if ($beforeId > 0) {
            $sql .= " AND m.id < ?";
            $params[] = $beforeId;
        }
        
        $sql .= " ORDER BY m.timestamp DESC, m.id DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Inverter ordem (mais antigo primeiro)
        return array_reverse($messages);
    }
    
    /**
     * Contar mensagens de uma conversa
     */
    public function countByConversation(int $conversationId): int
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE conversation_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$conversationId]);
        
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    /**
     * Criar nova mensagem
     */
    public function create(array $data): int
    {
        $sql = "
            INSERT INTO {$this->table} (
                conversation_id, message_id, from_me, message_type, 
                message_text, media_url, media_mimetype, media_filename,
                media_size, caption, quoted_message_id, status, 
                timestamp, user_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['conversation_id'],
            $data['message_id'] ?? null,
            $data['from_me'] ?? 0,
            $data['message_type'] ?? 'text',
            $data['message_text'] ?? null,
            $data['media_url'] ?? null,
            $data['media_mimetype'] ?? null,
            $data['media_filename'] ?? null,
            $data['media_size'] ?? null,
            $data['caption'] ?? null,
            $data['quoted_message_id'] ?? null,
            $data['status'] ?? 'pending',
            $data['timestamp'] ?? time(),
            $data['user_id']
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Atualizar status da mensagem
     */
    public function updateStatus(int $id, string $status): bool
    {
        $sql = "UPDATE {$this->table} SET status = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([$status, $id]);
    }
    
    /**
     * Marcar mensagem como lida
     */
    public function markAsRead(int $id): bool
    {
        $sql = "UPDATE {$this->table} SET read_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([$id]);
    }
    
    /**
     * Marcar todas mensagens de uma conversa como lidas
     */
    public function markAllAsRead(int $conversationId): int
    {
        $sql = "
            UPDATE {$this->table} 
            SET read_at = NOW() 
            WHERE conversation_id = ? AND from_me = 0 AND read_at IS NULL
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$conversationId]);
        
        return $stmt->rowCount();
    }
    
    /**
     * Deletar mensagem
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([$id]);
    }
    
    /**
     * Buscar mensagem por ID externo
     */
    public function findByExternalId(string $externalId): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE message_id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$externalId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Buscar última mensagem de uma conversa
     */
    public function findLastByConversation(int $conversationId): ?array
    {
        $sql = "
            SELECT * FROM {$this->table} 
            WHERE conversation_id = ? 
            ORDER BY timestamp DESC, id DESC 
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$conversationId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}
