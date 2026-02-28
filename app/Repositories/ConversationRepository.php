<?php

namespace App\Repositories;

use PDO;

/**
 * Repository para Conversas do Chat
 * 
 * Responsável por todas as operações de banco de dados relacionadas a conversas.
 * Segue o padrão Repository para isolar a lógica de acesso a dados.
 */
class ConversationRepository extends BaseRepository
{
    protected string $table = 'chat_conversations';
    
    /**
     * Buscar conversas do usuário com filtros
     * 
     * @param int $userId ID do usuário
     * @param array $filters Filtros (search, archived, filter, limit, offset)
     * @return array Lista de conversas
     */
    public function findByUser(int $userId, array $filters = []): array
    {
        $search = $filters['search'] ?? '';
        $archived = $filters['archived'] ?? 0;
        $filter = $filters['filter'] ?? 'all';
        $limit = min((int)($filters['limit'] ?? 100), 200);
        $offset = (int)($filters['offset'] ?? 0);
        
        $sql = "
            SELECT 
                cc.id,
                cc.phone,
                cc.contact_name,
                cc.profile_pic_url,
                cc.last_message_text,
                cc.last_message_time,
                cc.unread_count,
                cc.is_pinned,
                cc.is_archived,
                cc.updated_at,
                cc.user_id as owner_user_id,
                cc.status,
                cc.assigned_to,
                cc.department_id,
                cc.priority,
                cc.channel_type,
                c.id as contact_id,
                c.name as contact_db_name,
                COALESCE(cc.contact_name, c.name, cc.phone) as display_name,
                u.name as owner_name
            FROM {$this->table} cc
            LEFT JOIN contacts c ON cc.contact_id = c.id
            LEFT JOIN users u ON cc.user_id = u.id
            WHERE cc.user_id = ? AND cc.is_archived = ?
        ";
        
        $params = [$userId, $archived];
        
        // Filtro de busca
        if (!empty($search)) {
            $sql .= " AND (
                cc.contact_name LIKE ? OR 
                c.name LIKE ? OR 
                cc.phone LIKE ?
            )";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        // Filtro por status
        if ($filter === 'inbox') {
            $sql .= " AND (cc.attended_by IS NULL AND cc.status NOT IN ('closed', 'in_progress'))";
        } elseif ($filter === 'mine') {
            $sql .= " AND (cc.attended_by = ? AND cc.status != 'closed')";
            $params[] = $userId;
        } elseif ($filter === 'history') {
            $sql .= " AND cc.status = 'closed'";
        } else {
            $sql .= " AND (cc.status IS NULL OR cc.status != 'closed')";
        }
        
        $sql .= " ORDER BY cc.is_pinned DESC, cc.last_message_time DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Contar total de conversas do usuário
     */
    public function countByUser(int $userId, bool $archived = false): int
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE user_id = ? AND is_archived = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $archived ? 1 : 0]);
        
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    /**
     * Buscar conversa por telefone
     */
    public function findByPhone(int $userId, string $phone): ?array
    {
        $sql = "
            SELECT * FROM {$this->table} 
            WHERE user_id = ? AND (phone = ? OR phone = ? OR phone = ?)
            LIMIT 1
        ";
        
        // Tentar com e sem código do país
        $phoneWithout55 = strlen($phone) > 11 && substr($phone, 0, 2) === '55' ? substr($phone, 2) : $phone;
        $phoneWith55 = substr($phone, 0, 2) !== '55' ? '55' . $phone : $phone;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $phone, $phoneWithout55, $phoneWith55]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Criar nova conversa
     */
    public function create(array $data): int
    {
        $sql = "
            INSERT INTO {$this->table} (user_id, contact_id, phone, contact_name, channel_type)
            VALUES (?, ?, ?, ?, ?)
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['user_id'],
            $data['contact_id'] ?? null,
            $data['phone'],
            $data['contact_name'] ?? null,
            $data['channel_type'] ?? 'whatsapp'
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Atualizar conversa
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $params[] = $value;
        }
        
        $params[] = $id;
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }
    
    /**
     * Marcar mensagens como lidas
     */
    public function markAsRead(int $conversationId): bool
    {
        // Atualizar mensagens
        $sql1 = "
            UPDATE chat_messages 
            SET read_at = NOW() 
            WHERE conversation_id = ? AND from_me = 0 AND read_at IS NULL
        ";
        $stmt1 = $this->db->prepare($sql1);
        $stmt1->execute([$conversationId]);
        
        // Zerar contador
        $sql2 = "UPDATE {$this->table} SET unread_count = 0 WHERE id = ?";
        $stmt2 = $this->db->prepare($sql2);
        
        return $stmt2->execute([$conversationId]);
    }
    
    /**
     * Arquivar/desarquivar conversa
     */
    public function setArchived(int $id, bool $archived): bool
    {
        return $this->update($id, ['is_archived' => $archived ? 1 : 0]);
    }
    
    /**
     * Fixar/desfixar conversa
     */
    public function setPinned(int $id, bool $pinned): bool
    {
        return $this->update($id, ['is_pinned' => $pinned ? 1 : 0]);
    }
    
    /**
     * Deletar conversa
     */
    public function delete(int $id, int $userId): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = ? AND user_id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([$id, $userId]);
    }
    
    /**
     * Verificar se conversa pertence ao usuário
     */
    public function belongsToUser(int $conversationId, int $userId): bool
    {
        $sql = "SELECT id FROM {$this->table} WHERE id = ? AND user_id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$conversationId, $userId]);
        
        return $stmt->fetch() !== false;
    }
}
