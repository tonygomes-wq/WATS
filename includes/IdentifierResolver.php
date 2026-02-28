<?php
/**
 * Classe para resolver identificadores WhatsApp
 * Suporta Phone, JID e LID (preparação para mudança Meta 2026)
 * 
 * Tipos de identificadores:
 * - Phone: 5511999999999
 * - JID: 5511999999999@s.whatsapp.net (Jabber ID - formato atual)
 * - LID: abc123xyz@lid (Linked ID - formato futuro Meta)
 */
class IdentifierResolver {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Normaliza identificador removendo sufixos WhatsApp
     * 
     * @param string $identifier Identificador a normalizar
     * @return string|null Identificador normalizado
     */
    public static function normalize($identifier) {
        if (empty($identifier)) {
            return null;
        }
        
        // Remover sufixos conhecidos do WhatsApp
        $identifier = str_replace([
            '@s.whatsapp.net',
            '@g.us',
            '@lid',
            '@c.us'
        ], '', $identifier);
        
        return trim($identifier);
    }
    
    /**
     * Detecta tipo de identificador
     * 
     * @param string $identifier Identificador a detectar
     * @return string Tipo: 'phone', 'jid', 'lid' ou 'unknown'
     */
    public static function getType($identifier) {
        if (empty($identifier)) {
            return 'unknown';
        }
        
        // LID: contém @lid
        if (strpos($identifier, '@lid') !== false) {
            return 'lid';
        }
        
        // JID: contém @ mas não é LID
        if (strpos($identifier, '@') !== false) {
            return 'jid';
        }
        
        // Phone: apenas números (10-15 dígitos)
        if (preg_match('/^\d{10,15}$/', $identifier)) {
            return 'phone';
        }
        
        return 'unknown';
    }
    
    /**
     * Converte identificador para formato JID completo
     * 
     * @param string $identifier Identificador a converter
     * @param bool $isGroup Se é grupo (usa @g.us ao invés de @s.whatsapp.net)
     * @return string JID completo
     */
    public static function toJID($identifier, $isGroup = false) {
        $normalized = self::normalize($identifier);
        $type = self::getType($identifier);
        
        // Se já é LID, retornar com sufixo @lid
        if ($type === 'lid') {
            return $normalized . '@lid';
        }
        
        // Se já é JID, retornar como está
        if ($type === 'jid') {
            return $identifier;
        }
        
        // Se é phone, adicionar sufixo apropriado
        if ($type === 'phone') {
            $suffix = $isGroup ? '@g.us' : '@s.whatsapp.net';
            return $normalized . $suffix;
        }
        
        // Fallback: retornar como está
        return $identifier;
    }
    
    /**
     * Resolve LID para Phone (se disponível no banco)
     * 
     * @param string $lid LID a resolver
     * @return string|null Phone encontrado ou null
     */
    public function resolveToPhone($lid) {
        $normalized = self::normalize($lid);
        
        $stmt = $this->pdo->prepare("
            SELECT phone 
            FROM whatsapp_identifiers 
            WHERE lid = ? 
            ORDER BY last_seen_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$normalized]);
        
        return $stmt->fetchColumn() ?: null;
    }
    
    /**
     * Resolve Phone para LID (se disponível no banco)
     * 
     * @param string $phone Phone a resolver
     * @return string|null LID encontrado ou null
     */
    public function resolveToLID($phone) {
        $normalized = self::normalize($phone);
        
        $stmt = $this->pdo->prepare("
            SELECT lid 
            FROM whatsapp_identifiers 
            WHERE phone = ? 
            ORDER BY last_seen_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$normalized]);
        
        return $stmt->fetchColumn() ?: null;
    }
    
    /**
     * Salva mapeamento entre identificadores
     * 
     * @param int $contactId ID do contato
     * @param string|null $phone Número de telefone
     * @param string|null $jid Jabber ID
     * @param string|null $lid Linked ID
     * @return bool Sucesso da operação
     */
    public function saveMapping($contactId, $phone = null, $jid = null, $lid = null) {
        // Normalizar identificadores
        $phone = $phone ? self::normalize($phone) : null;
        $jid = $jid ? self::normalize($jid) : null;
        $lid = $lid ? self::normalize($lid) : null;
        
        // Se não temos pelo menos phone ou lid, não podemos salvar
        if (!$phone && !$lid) {
            return false;
        }
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO whatsapp_identifiers (contact_id, phone, jid, lid, last_seen_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    jid = COALESCE(VALUES(jid), jid),
                    lid = COALESCE(VALUES(lid), lid),
                    last_seen_at = NOW()
            ");
            
            return $stmt->execute([$contactId, $phone, $jid, $lid]);
        } catch (Exception $e) {
            error_log("[IdentifierResolver] Erro ao salvar mapeamento: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Busca contato por qualquer identificador
     * 
     * @param string $identifier Identificador a buscar
     * @return array|null Dados do contato ou null
     */
    public function findContact($identifier) {
        $normalized = self::normalize($identifier);
        $type = self::getType($identifier);
        
        // Tentar buscar diretamente em contacts
        $stmt = $this->pdo->prepare("
            SELECT id, phone, primary_identifier, identifier_type
            FROM contacts
            WHERE phone = ? OR primary_identifier = ?
            LIMIT 1
        ");
        $stmt->execute([$normalized, $normalized]);
        $contact = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($contact) {
            return $contact;
        }
        
        // Tentar buscar em whatsapp_identifiers
        $column = $type === 'lid' ? 'lid' : ($type === 'jid' ? 'jid' : 'phone');
        
        $stmt = $this->pdo->prepare("
            SELECT c.id, c.phone, c.primary_identifier, c.identifier_type
            FROM contacts c
            INNER JOIN whatsapp_identifiers wi ON wi.contact_id = c.id
            WHERE wi.{$column} = ?
            ORDER BY wi.last_seen_at DESC
            LIMIT 1
        ");
        $stmt->execute([$normalized]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Atualiza timestamp de última visualização do identificador
     * 
     * @param string $identifier Identificador visto
     * @param int $contactId ID do contato
     * @return bool Sucesso da operação
     */
    public function updateLastSeen($identifier, $contactId) {
        $normalized = self::normalize($identifier);
        $type = self::getType($identifier);
        
        if ($type === 'unknown') {
            return false;
        }
        
        $column = $type === 'lid' ? 'lid' : ($type === 'jid' ? 'jid' : 'phone');
        
        try {
            $stmt = $this->pdo->prepare("
                UPDATE whatsapp_identifiers 
                SET last_seen_at = NOW()
                WHERE contact_id = ? AND {$column} = ?
            ");
            
            return $stmt->execute([$contactId, $normalized]);
        } catch (Exception $e) {
            error_log("[IdentifierResolver] Erro ao atualizar last_seen: " . $e->getMessage());
            return false;
        }
    }
}
