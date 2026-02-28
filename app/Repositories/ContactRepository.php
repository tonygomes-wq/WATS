<?php

namespace App\Repositories;

use PDO;

/**
 * Repository para Contatos
 */
class ContactRepository extends BaseRepository
{
    protected string $table = 'contacts';
    
    /**
     * Buscar contato por telefone
     */
    public function findByPhone(int $userId, string $phone): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = ? AND phone = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $phone]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Criar contato
     */
    public function create(array $data): int
    {
        $sql = "
            INSERT INTO {$this->table} (user_id, phone, name, email)
            VALUES (?, ?, ?, ?)
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['user_id'],
            $data['phone'],
            $data['name'] ?? null,
            $data['email'] ?? null
        ]);
        
        return (int) $this->db->lastInsertId();
    }
}
