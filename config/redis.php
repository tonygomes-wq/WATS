<?php
/**
 * Configuração Redis
 * WATS - WhatsApp Automation System
 * MACIP Tecnologia LTDA
 */

require_once __DIR__ . '/env.php';

// Configurações Redis
define('REDIS_ENABLED', env('CACHE_DRIVER', 'file') === 'redis');
define('REDIS_HOST', env('REDIS_HOST', '127.0.0.1'));
define('REDIS_PORT', (int)env('REDIS_PORT', 6379));
define('REDIS_PASSWORD', env('REDIS_PASSWORD', null));
define('REDIS_DATABASE', (int)env('REDIS_DATABASE', 0));
define('REDIS_TIMEOUT', (int)env('REDIS_TIMEOUT', 2));

// Prefixo para todas as chaves (evita conflitos)
define('REDIS_PREFIX', env('REDIS_PREFIX', 'wats:'));

// TTL padrão (em segundos)
define('REDIS_DEFAULT_TTL', (int)env('REDIS_DEFAULT_TTL', 3600)); // 1 hora

/**
 * Obter instância Redis (Singleton)
 */
function getRedisConnection() {
    static $redis = null;
    
    if (!REDIS_ENABLED) {
        return null;
    }
    
    if ($redis !== null) {
        return $redis;
    }
    
    try {
        if (!extension_loaded('redis')) {
            error_log("REDIS: Extensão PHP Redis não instalada");
            return null;
        }
        
        $redis = new Redis();
        
        // Conectar com timeout
        $connected = $redis->connect(
            REDIS_HOST,
            REDIS_PORT,
            REDIS_TIMEOUT
        );
        
        if (!$connected) {
            error_log("REDIS: Falha ao conectar");
            return null;
        }
        
        // Autenticar se necessário
        if (REDIS_PASSWORD) {
            $redis->auth(REDIS_PASSWORD);
        }
        
        // Selecionar database
        $redis->select(REDIS_DATABASE);
        
        // Configurar serialização automática
        $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
        
        // Configurar prefixo
        $redis->setOption(Redis::OPT_PREFIX, REDIS_PREFIX);
        
        return $redis;
        
    } catch (Exception $e) {
        error_log("REDIS ERRO: " . $e->getMessage());
        return null;
    }
}
