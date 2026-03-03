<?php
/**
 * Script de Teste Redis
 * Execute: php tests/test_redis.php
 */

require_once __DIR__ . '/../libs/RedisCache.php';
require_once __DIR__ . '/../libs/RateLimiter.php';
require_once __DIR__ . '/../config/database.php';

echo "=================================\n";
echo "TESTE REDIS - WATS\n";
echo "=================================\n\n";

// Teste 1: Conexão Redis
echo "1. Testando conexão Redis...\n";
$cache = new RedisCache();
$stats = $cache->getStats();

if ($stats['enabled']) {
    echo "   ✅ Redis CONECTADO\n";
    echo "   Memória: " . ($stats['redis_memory'] ?? 'N/A') . "\n";
    echo "   Chaves: " . ($stats['redis_keys'] ?? 'N/A') . "\n";
} else {
    echo "   ⚠️  Redis INATIVO (usando fallback)\n";
}
echo "\n";

// Teste 2: Set/Get
echo "2. Testando SET/GET...\n";
$testKey = 'test:' . time();
$testValue = ['nome' => 'Tony Gomes', 'timestamp' => time()];

$cache->set($testKey, $testValue, 60);
$retrieved = $cache->get($testKey);

if ($retrieved === $testValue) {
    echo "   ✅ SET/GET funcionando\n";
} else {
    echo "   ❌ Erro no SET/GET\n";
}
echo "\n";

// Teste 3: Expiração
echo "3. Testando expiração (TTL)...\n";
$cache->set('test:ttl', 'valor', 2);
echo "   Valor definido com TTL de 2 segundos\n";
echo "   Aguardando 3 segundos...\n";
sleep(3);
$expired = $cache->get('test:ttl');

if ($expired === null) {
    echo "   ✅ Expiração funcionando\n";
} else {
    echo "   ❌ Valor não expirou\n";
}
echo "\n";

// Teste 4: Rate Limiter
echo "4. Testando Rate Limiter...\n";
$rateLimiter = new RateLimiter();
$testId = 'test_user_' . time();

$allowed = 0;
$blocked = 0;

for ($i = 0; $i < 70; $i++) {
    if ($rateLimiter->check($testId, 'api_request')) {
        $allowed++;
    } else {
        $blocked++;
    }
}

echo "   Permitidas: $allowed\n";
echo "   Bloqueadas: $blocked\n";

if ($allowed <= 60 && $blocked >= 10) {
    echo "   ✅ Rate Limiter funcionando\n";
} else {
    echo "   ⚠️  Rate Limiter pode não estar funcionando corretamente\n";
}
echo "\n";

// Teste 5: Performance
echo "5. Testando Performance...\n";

// Sem cache
$start = microtime(true);
for ($i = 0; $i < 100; $i++) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $stmt->fetch();
}
$timeWithoutCache = microtime(true) - $start;

// Com cache
$start = microtime(true);
for ($i = 0; $i < 100; $i++) {
    $cached = $cache->get('test:count');
    if ($cached === null) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $result = $stmt->fetch();
        $cache->set('test:count', $result, 60);
    }
}
$timeWithCache = microtime(true) - $start;

$improvement = round((($timeWithoutCache - $timeWithCache) / $timeWithoutCache) * 100, 2);

echo "   Sem cache: " . round($timeWithoutCache * 1000, 2) . "ms\n";
echo "   Com cache: " . round($timeWithCache * 1000, 2) . "ms\n";
echo "   Melhoria: {$improvement}%\n";

if ($improvement > 50) {
    echo "   ✅ Performance excelente\n";
} else {
    echo "   ⚠️  Performance pode ser melhorada\n";
}
echo "\n";

// Limpar testes
$cache->flush('test:*');

echo "=================================\n";
echo "TESTES CONCLUÍDOS\n";
echo "=================================\n";
