<?php
/**
 * RedisCache - Gerenciador de Cache com Redis
 * Fallback automático para cache de arquivos
 * 
 * WATS - WhatsApp Automation System
 * MACIP Tecnologia LTDA
 */

class RedisCache {
    private $redis;
    private $enabled;
    private $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0
    ];
    
    public function __construct() {
        require_once __DIR__ . '/../config/redis.php';
        $this->redis = getRedisConnection();
        $this->enabled = ($this->redis !== null);
        
        if (!$this->enabled) {
            error_log("RedisCache: Usando fallback (cache de arquivos)");
        }
    }
    
    /**
     * Obter valor do cache
     */
    public function get($key) {
        if (!$this->enabled) {
            return $this->getFromFile($key);
        }
        
        try {
            $value = $this->redis->get($key);
            
            if ($value === false) {
                $this->stats['misses']++;
                return null;
            }
            
            $this->stats['hits']++;
            return $value;
            
        } catch (Exception $e) {
            error_log("RedisCache GET erro: " . $e->getMessage());
            return $this->getFromFile($key);
        }
    }
    
    /**
     * Salvar valor no cache
     */
    public function set($key, $value, $ttl = null) {
        if ($ttl === null) {
            $ttl = REDIS_DEFAULT_TTL;
        }
        
        if (!$this->enabled) {
            return $this->setToFile($key, $value, $ttl);
        }
        
        try {
            $result = $this->redis->setex($key, $ttl, $value);
            $this->stats['sets']++;
            return $result;
            
        } catch (Exception $e) {
            error_log("RedisCache SET erro: " . $e->getMessage());
            return $this->setToFile($key, $value, $ttl);
        }
    }
    
    /**
     * Deletar chave do cache
     */
    public function delete($key) {
        if (!$this->enabled) {
            return $this->deleteFromFile($key);
        }
        
        try {
            $result = $this->redis->del($key);
            $this->stats['deletes']++;
            return $result > 0;
            
        } catch (Exception $e) {
            error_log("RedisCache DELETE erro: " . $e->getMessage());
            return $this->deleteFromFile($key);
        }
    }
    
    /**
     * Verificar se chave existe
     */
    public function exists($key) {
        if (!$this->enabled) {
            return $this->existsInFile($key);
        }
        
        try {
            return $this->redis->exists($key) > 0;
        } catch (Exception $e) {
            return $this->existsInFile($key);
        }
    }
    
    /**
     * Incrementar contador
     */
    public function increment($key, $amount = 1) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            return $this->redis->incrBy($key, $amount);
        } catch (Exception $e) {
            error_log("RedisCache INCREMENT erro: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Limpar cache por padrão
     */
    public function flush($pattern = '*') {
        if (!$this->enabled) {
            return $this->flushFiles();
        }
        
        try {
            $keys = $this->redis->keys($pattern);
            if (!empty($keys)) {
                return $this->redis->del($keys);
            }
            return 0;
        } catch (Exception $e) {
            error_log("RedisCache FLUSH erro: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obter estatísticas
     */
    public function getStats() {
        $stats = $this->stats;
        $stats['enabled'] = $this->enabled;
        $stats['hit_rate'] = $this->calculateHitRate();
        
        if ($this->enabled) {
            try {
                $info = $this->redis->info();
                $stats['redis_memory'] = $info['used_memory_human'] ?? 'N/A';
                $stats['redis_keys'] = $this->redis->dbSize();
            } catch (Exception $e) {
                // Ignorar erro
            }
        }
        
        return $stats;
    }
    
    private function calculateHitRate() {
        $total = $this->stats['hits'] + $this->stats['misses'];
        if ($total === 0) return 0;
        return round(($this->stats['hits'] / $total) * 100, 2);
    }
    
    // ========================================
    // FALLBACK: Cache de Arquivos
    // ========================================
    
    private function getCacheDir() {
        $dir = __DIR__ . '/../storage/cache';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }
    
    private function getCacheFile($key) {
        return $this->getCacheDir() . '/' . md5($key) . '.cache';
    }
    
    private function getFromFile($key) {
        $file = $this->getCacheFile($key);
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = unserialize(file_get_contents($file));
        
        // Verificar expiração
        if ($data['expires'] < time()) {
            unlink($file);
            return null;
        }
        
        return $data['value'];
    }
    
    private function setToFile($key, $value, $ttl) {
        $file = $this->getCacheFile($key);
        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        return file_put_contents($file, serialize($data)) !== false;
    }
    
    private function deleteFromFile($key) {
        $file = $this->getCacheFile($key);
        if (file_exists($file)) {
            return unlink($file);
        }
        return false;
    }
    
    private function existsInFile($key) {
        return $this->getFromFile($key) !== null;
    }
    
    private function flushFiles() {
        $dir = $this->getCacheDir();
        $files = glob($dir . '/*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        return count($files);
    }
}
