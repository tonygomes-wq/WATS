<?php
/**
 * RateLimiter - Proteção contra abusos e DDoS
 * WATS - WhatsApp Automation System
 */

class RateLimiter {
    private $cache;
    
    // Limites por tipo de ação
    private $limits = [
        'api_request' => ['max' => 60, 'window' => 60],      // 60 req/min
        'login_attempt' => ['max' => 5, 'window' => 300],    // 5 tentativas/5min
        'message_send' => ['max' => 100, 'window' => 60],    // 100 msgs/min
        'webhook' => ['max' => 200, 'window' => 60],         // 200 webhooks/min
        'export' => ['max' => 5, 'window' => 3600],          // 5 exports/hora
    ];
    
    public function __construct() {
        require_once __DIR__ . '/RedisCache.php';
        $this->cache = new RedisCache();
    }
    
    /**
     * Verificar se ação é permitida
     */
    public function check($identifier, $action = 'api_request') {
        if (!isset($this->limits[$action])) {
            return true; // Sem limite definido
        }
        
        $limit = $this->limits[$action];
        $key = "ratelimit:{$action}:{$identifier}";
        
        // Obter contador atual
        $current = $this->cache->get($key);
        
        if ($current === null) {
            // Primeira requisição
            $this->cache->set($key, 1, $limit['window']);
            return true;
        }
        
        if ($current >= $limit['max']) {
            // Limite excedido
            return false;
        }
        
        // Incrementar contador
        $this->cache->increment($key);
        return true;
    }
    
    /**
     * Obter informações do limite
     */
    public function getInfo($identifier, $action = 'api_request') {
        if (!isset($this->limits[$action])) {
            return null;
        }
        
        $limit = $this->limits[$action];
        $key = "ratelimit:{$action}:{$identifier}";
        $current = (int)$this->cache->get($key);
        
        return [
            'limit' => $limit['max'],
            'remaining' => max(0, $limit['max'] - $current),
            'current' => $current,
            'window' => $limit['window']
        ];
    }
    
    /**
     * Resetar limite para um identificador
     */
    public function reset($identifier, $action = 'api_request') {
        $key = "ratelimit:{$action}:{$identifier}";
        return $this->cache->delete($key);
    }
    
    /**
     * Bloquear temporariamente
     */
    public function block($identifier, $duration = 3600) {
        $key = "blocked:{$identifier}";
        return $this->cache->set($key, true, $duration);
    }
    
    /**
     * Verificar se está bloqueado
     */
    public function isBlocked($identifier) {
        $key = "blocked:{$identifier}";
        return $this->cache->exists($key);
    }
}
