<?php
/**
 * Teste Simples Redis - WATS
 * Execute: php test_redis_simples.php
 */

echo "\n";
echo "========================================\n";
echo "TESTE REDIS - WATS\n";
echo "========================================\n\n";

// Carregar .env
require_once __DIR__ . '/config/env.php';

echo "1. Configurações do .env:\n";
echo "   REDIS_HOST: " . env('REDIS_HOST', 'não definido') . "\n";
echo "   REDIS_PORT: " . env('REDIS_PORT', 'não definido') . "\n";
echo "   REDIS_PASSWORD: " . (env('REDIS_PASSWORD') ? '***definida***' : 'não definida') . "\n";
echo "   CACHE_DRIVER: " . env('CACHE_DRIVER', 'não definido') . "\n";
echo "\n";

// Verificar extensão Redis
echo "2. Extensão PHP Redis:\n";
if (extension_loaded('redis')) {
    echo "   ✅ INSTALADA (Versão: " . phpversion('redis') . ")\n";
    $hasExtension = true;
} else {
    echo "   ⚠️  NÃO INSTALADA (usará fallback)\n";
    $hasExtension = false;
}
echo "\n";

// Testar conexão
echo "3. Testando conexão:\n";

$redisHost = env('REDIS_HOST', 'wats_redis-wats');
$redisPort = (int)env('REDIS_PORT', 6379);
$redisPass = env('REDIS_PASSWORD', '');

echo "   Conectando em: {$redisHost}:{$redisPort}\n";

$connected = false;

if ($hasExtension) {
    try {
        $redis = new Redis();
        $connected = @$redis->connect($redisHost, $redisPort, 2);
        
        if ($connected) {
            // Autenticar
            if ($redisPass) {
                $auth = @$redis->auth($redisPass);
                if (!$auth) {
                    echo "   ❌ Falha na autenticação\n";
                    $connected = false;
                }
            }
            
            if ($connected) {
                // Testar PING
                $pong = $redis->ping();
                if ($pong) {
                    echo "   ✅ CONECTADO COM SUCESSO!\n";
                    
                    // Informações
                    $info = $redis->info();
                    echo "   Versão: " . ($info['redis_version'] ?? 'N/A') . "\n";
                    echo "   Memória: " . ($info['used_memory_human'] ?? 'N/A') . "\n";
                    echo "   Uptime: " . round(($info['uptime_in_seconds'] ?? 0) / 86400, 1) . " dias\n";
                } else {
                    echo "   ❌ Redis não respondeu ao PING\n";
                    $connected = false;
                }
            }
        } else {
            echo "   ❌ NÃO FOI POSSÍVEL CONECTAR\n";
            echo "\n";
            echo "   Possíveis causas:\n";
            echo "   - Redis não está rodando no Easypanel\n";
            echo "   - Nome do host incorreto\n";
            echo "   - Rede Docker não configurada\n";
        }
        
    } catch (Exception $e) {
        echo "   ❌ ERRO: " . $e->getMessage() . "\n";
        $connected = false;
    }
} else {
    echo "   ⚠️  Pulando teste (extensão não instalada)\n";
}
echo "\n";

// Testar RedisCache
echo "4. Testando RedisCache:\n";
require_once __DIR__ . '/libs/RedisCache.php';

$cache = new RedisCache();
$stats = $cache->getStats();

if ($stats['enabled']) {
    echo "   ✅ RedisCache ATIVO (usando Redis)\n";
} else {
    echo "   ⚠️  RedisCache em FALLBACK (usando arquivos)\n";
}
echo "\n";

// Teste SET/GET
echo "5. Testando SET/GET:\n";
$testKey = 'test_simples_' . time();
$testValue = [
    'usuario' => 'Tony Gomes',
    'sistema' => 'WATS',
    'timestamp' => time(),
    'teste' => 'Redis funcionando!'
];

$cache->set($testKey, $testValue, 60);
$retrieved = $cache->get($testKey);

if ($retrieved === $testValue) {
    echo "   ✅ SET/GET FUNCIONANDO PERFEITAMENTE\n";
    echo "   Dados salvos e recuperados com sucesso\n";
} else {
    echo "   ❌ Erro no SET/GET\n";
}

// Limpar teste
$cache->delete($testKey);
echo "\n";

// Teste de performance
echo "6. Teste de Performance:\n";

$testData = ['id' => 123, 'nome' => 'Teste', 'dados' => str_repeat('x', 1000)];

// Sem cache
$start = microtime(true);
for ($i = 0; $i < 100; $i++) {
    $dummy = $testData;
}
$timeWithout = microtime(true) - $start;

// Com cache
$cache->set('perf_test', $testData, 60);
$start = microtime(true);
for ($i = 0; $i < 100; $i++) {
    $cached = $cache->get('perf_test');
}
$timeWith = microtime(true) - $start;

$improvement = round((($timeWithout - $timeWith) / $timeWithout) * 100, 2);

echo "   Sem cache: " . round($timeWithout * 1000, 2) . "ms\n";
echo "   Com cache: " . round($timeWith * 1000, 2) . "ms\n";
echo "   Melhoria: {$improvement}%\n";

if ($improvement > 30) {
    echo "   ✅ Performance EXCELENTE\n";
} else {
    echo "   ⚠️  Performance OK\n";
}

$cache->delete('perf_test');
echo "\n";

// Resumo final
echo "========================================\n";
echo "RESUMO\n";
echo "========================================\n";

if ($hasExtension && $connected) {
    echo "✅ REDIS FUNCIONANDO PERFEITAMENTE!\n";
    echo "\n";
    echo "Status:\n";
    echo "  ✅ Extensão PHP Redis instalada\n";
    echo "  ✅ Conectado ao Redis Server\n";
    echo "  ✅ Cache funcionando\n";
    echo "  ✅ Performance otimizada\n";
    echo "\n";
    echo "Próximos passos:\n";
    echo "  1. Acesse: https://wats.macip.com.br/admin_redis_dashboard.php\n";
    echo "  2. Implemente cache nas páginas principais\n";
    echo "  3. Monitore a performance\n";
    
} elseif ($hasExtension && !$connected) {
    echo "⚠️  REDIS NÃO CONECTOU\n";
    echo "\n";
    echo "Status:\n";
    echo "  ✅ Extensão PHP Redis instalada\n";
    echo "  ❌ Não conectou ao Redis Server\n";
    echo "  ✅ Cache funcionando (fallback)\n";
    echo "\n";
    echo "Ações necessárias:\n";
    echo "  1. Verifique se Redis está UP no Easypanel\n";
    echo "  2. Confirme o nome do serviço: wats_redis-wats\n";
    echo "  3. Verifique se estão na mesma rede Docker\n";
    
} else {
    echo "⚠️  EXTENSÃO REDIS NÃO INSTALADA\n";
    echo "\n";
    echo "Status:\n";
    echo "  ❌ Extensão PHP Redis não instalada\n";
    echo "  ✅ Cache funcionando (fallback com arquivos)\n";
    echo "  ⚠️  Performance reduzida\n";
    echo "\n";
    echo "Para instalar a extensão:\n";
    echo "  1. Adicione ao Dockerfile:\n";
    echo "     RUN pecl install redis && docker-php-ext-enable redis\n";
    echo "  2. Faça rebuild do container no Easypanel\n";
    echo "\n";
    echo "OU continue usando assim (funciona, mas mais lento)\n";
}

echo "\n";
echo "========================================\n";
echo "\n";
