<?php
/**
 * QueryCache - Cache inteligente de queries MySQL
 * WATS - WhatsApp Automation System
 */

class QueryCache {
    private $cache;
    private $pdo;
    
    // TTL por tipo de dado (em segundos)
    private $ttlConfig = [
        'user_profile' => 300,      // 5 minutos
        'contacts' => 600,          // 10 minutos
        'categories' => 1800,       // 30 minutos
        'departments' => 1800,      // 30 minutos
        'settings' => 3600,         // 1 hora
        'plans' => 3600,            // 1 hora
        'default' => 600            // 10 minutos
    ];
    
    public function __construct($pdo) {
        require_once __DIR__ . '/RedisCache.php';
        $this->cache = new RedisCache();
        $this->pdo = $pdo;
    }
    
    /**
     * Executar query com cache
     */
    public function query($sql, $params = [], $cacheKey = null, $ttl = null) {
        // Gerar chave de cache
        if ($cacheKey === null) {
            $cacheKey = $this->generateCacheKey($sql, $params);
        }
        
        // Tentar obter do cache
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        // Executar query
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Cachear resultado
        if ($ttl === null) {
            $ttl = $this->ttlConfig['default'];
        }
        $this->cache->set($cacheKey, $result, $ttl);
        
        return $result;
    }
    
    /**
     * Obter um único registro com cache
     */
    public function queryOne($sql, $params = [], $cacheKey = null, $ttl = null) {
        $result = $this->query($sql, $params, $cacheKey, $ttl);
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Invalidar cache por padrão
     */
    public function invalidate($pattern) {
        return $this->cache->flush($pattern);
    }
    
    /**
     * Gerar chave de cache única
     */
    private function generateCacheKey($sql, $params) {
        $key = 'query:' . md5($sql . serialize($params));
        return $key;
    }
    
    /**
     * Obter TTL por tipo
     */
    public function getTTL($type) {
        return $this->ttlConfig[$type] ?? $this->ttlConfig['default'];
    }
}
