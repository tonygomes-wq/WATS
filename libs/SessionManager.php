<?php
/**
 * SessionManager - Gerenciador de Sessões com Redis
 * WATS - WhatsApp Automation System
 */

class SessionManager {
    private $cache;
    private $ttl = 28800; // 8 horas
    
    public function __construct() {
        require_once __DIR__ . '/RedisCache.php';
        $this->cache = new RedisCache();
    }
    
    /**
     * Iniciar sessão com Redis
     */
    public function start() {
        // Configurar handler customizado se Redis estiver ativo
        if ($this->cache->getStats()['enabled']) {
            session_set_save_handler(
                [$this, 'open'],
                [$this, 'close'],
                [$this, 'read'],
                [$this, 'write'],
                [$this, 'destroy'],
                [$this, 'gc']
            );
        }
        
        session_start();
    }
    
    public function open($savePath, $sessionName) {
        return true;
    }
    
    public function close() {
        return true;
    }
    
    public function read($sessionId) {
        $key = "session:{$sessionId}";
        $data = $this->cache->get($key);
        return $data ? $data : '';
    }
    
    public function write($sessionId, $data) {
        $key = "session:{$sessionId}";
        return $this->cache->set($key, $data, $this->ttl);
    }
    
    public function destroy($sessionId) {
        $key = "session:{$sessionId}";
        return $this->cache->delete($key);
    }
    
    public function gc($maxLifetime) {
        // Redis já gerencia expiração automaticamente
        return true;
    }
    
    /**
     * Obter dados do usuário da sessão (com cache)
     */
    public function getUserData($userId) {
        $key = "user_data:{$userId}";
        
        $cached = $this->cache->get($key);
        if ($cached !== null) {
            return $cached;
        }
        
        // Buscar do banco
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($userData) {
            // Cachear por 5 minutos
            $this->cache->set($key, $userData, 300);
        }
        
        return $userData;
    }
    
    /**
     * Invalidar cache do usuário
     */
    public function invalidateUserCache($userId) {
        $key = "user_data:{$userId}";
        return $this->cache->delete($key);
    }
}
