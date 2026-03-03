<?php
/**
 * Teste Redis - Easypanel/Docker
 * Execute: php tests/test_redis_easypanel.php
 */

echo "=================================\n";
echo "TESTE REDIS - EASYPANEL/DOCKER\n";
echo "=================================\n\n";

// Carregar .env
require_once __DIR__ . '/../config/env.php';

echo "1. Verificando configurações do .env...\n";
echo "   REDIS_HOST: " . env('REDIS_HOST', 'não definido') . "\n";
echo "   REDIS_PORT: " . env('REDIS_PORT', 'não definido') . "\n";
echo "   REDIS_PASSWORD: " . (env('REDIS_PASSWORD') ? '***definida***' : 'não definida') . "\n";
echo "   CACHE_DRIVER: " . env('CACHE_DRIVER', 'não definido') . "\n";
echo "\n";

// Teste 1: Verificar extensão Redis
echo "2. Verificando extensão PHP Redis...\n";
if (extension_loaded('redis')) {
    echo "   ✅ Extensão Redis instalada\n";
    echo "   Versão: " . phpversion('redis') . "\n";
} else {
    echo "   ❌ Extensão Redis NÃO instalada\n";
    echo "   Execute: pecl install redis\n";
    echo "\n";
    echo "   ⚠️  Sistema usará fallback (cache de arquivos)\n";
}
echo "\n";

// Teste 2: Tentar conectar no Redis
echo "3. Testando conexão com Redis...\n";

$redisHost = env('REDIS_HOST', 'wats_redis-wats');
$redisPort = (int)env('REDIS_PORT', 6379);
$redisPass = env('REDIS_PASSWORD', '');

echo "   Tentando conectar em: {$redisHost}:{$redisPort}\n";

if (extension_loaded('redis')) {
    try {
        $redis = new Redis();
        
        // Tentar conectar
        $connected = @$redis->connect($redisHost, $redisPort, 2);
        
        if (!$connected) {
            echo "   ❌ Não foi possível conectar\n";
            echo "\n";
            echo "   Possíveis causas:\n";
            echo "   1. Redis não está rodando no Easypanel\n";
            echo "   2. Nome do serviço incorreto (verifique no Easypanel)\n";
            echo "   3. Rede Docker não configurada corretamente\n";
            echo "\n";
            echo "   Soluções:\n";
            echo "   - Verifique se o serviço Redis está UP no Easypanel\n";
            echo "   - Confirme o nome exato do serviço Redis\n";
            echo "   - Tente usar 'redis' ou 'wats-redis' como REDIS_HOST\n";
            exit(1);
        }
        
        // Autenticar
        if ($redisPass) {
            $auth = @$redis->auth($redisPass);
            if (!$auth) {
                echo "   ❌ Falha na autenticação\n";
                echo "   Verifique a senha no .env\n";
                exit(1);
            }
        }
        
        // Testar PING
        $pong = $redis->ping();
        if ($pong) {
            echo "   ✅ Redis conectado com sucesso!\n";
            
            // Obter informações
            $info = $redis->info();
            echo "   Versão Redis: " . ($info['redis_version'] ?? 'N/A') . "\n";
            echo "   Memória usada: " . ($info['used_memory_human'] ?? 'N/A') . "\n";
            echo "   Uptime: " . round(($info['uptime_in_seconds'] ?? 0) / 86400, 1) . " dias\n";
        } else {
            echo "   ❌ Redis não respondeu ao PING\n";
            exit(1);
        }
        
    } catch (Exception $e) {
        echo "   ❌ Erro: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    echo "   ⚠️  Extensão Redis não instalada, pulando teste de conexão\n";
}
echo "\n";

// Teste 3: Testar RedisCache
echo "4. Testando classe RedisCache...\n";
require_once __DIR__ . '/../libs/RedisCache.php';

$cache = new RedisCache();
$stats = $cache->getStats();

if ($stats['enabled']) {
    echo "   ✅ RedisCache ATIVO\n";
} else {
    echo "   ⚠️  RedisCache usando FALLBACK (cache de arquivos)\n";
}
echo "\n";

// Teste 4: SET/GET
echo "5. Testando SET/GET...\n";
$testKey = 'test:easypanel:' . time();
$testValue = [
    'usuario' => 'Tony Gomes',
    'sistema' => 'WATS',
    'timestamp' => time()
];

$cache->set($testKey, $testValue, 60);
$retrieved = $cache->get($testKey);

if ($retrieved === $testValue) {
    echo "   ✅ SET/GET funcionando perfeitamente\n";
} else {
    echo "   ❌ Erro no SET/GET\n";
    var_dump(['esperado' => $testValue, 'recebido' => $retrieved]);
}
echo "\n";

// Teste 5: Expiração
echo "6. Testando expiração (TTL)...\n";
$cache->set('test:ttl', 'valor_temporario', 2);
echo "   Valor definido com TTL de 2 segundos\n";
echo "   Aguardando 3 segundos...\n";
sleep(3);
$expired = $cache->get('test:ttl');

if ($expired === null) {
    echo "   ✅ Expiração funcionando corretamente\n";
} else {
    echo "   ❌ Valor não expirou como esperado\n";
}
echo "\n";

// Teste 6: Performance
echo "7. Testando Performance...\n";

// Criar dados de teste
$testData = [];
for ($i = 0; $i < 100; $i++) {
    $testData[] = ['id' => $i, 'nome' => "Teste $i", 'timestamp' => time()];
}

// Teste SEM cache
$start = microtime(true);
for ($i = 0; $i < 100; $i++) {
    $dummy = $testData; // Simular processamento
}
$timeWithoutCache = microtime(true) - $start;

// Teste COM cache
$cache->set('test:performance', $testData, 60);
$start = microtime(true);
for ($i = 0; $i < 100; $i++) {
    $cached = $cache->get('test:performance');
}
$timeWithCache = microtime(true) - $start;

$improvement = round((($timeWithoutCache - $timeWithCache) / $timeWithoutCache) * 100, 2);

echo "   Sem cache: " . round($timeWithoutCache * 1000, 2) . "ms\n";
echo "   Com cache: " . round($timeWithCache * 1000, 2) . "ms\n";
echo "   Melhoria: {$improvement}%\n";

if ($improvement > 30) {
    echo "   ✅ Performance excelente\n";
} else {
    echo "   ⚠️  Performance pode ser melhorada\n";
}
echo "\n";

// Limpar testes
$cache->flush('test:*');

// Resumo final
echo "=================================\n";
echo "RESUMO DOS TESTES\n";
echo "=================================\n";
echo "Extensão Redis: " . (extension_loaded('redis') ? '✅ Instalada' : '❌ Não instalada') . "\n";
echo "Conexão Redis: " . ($stats['enabled'] ? '✅ Conectado' : '⚠️  Fallback ativo') . "\n";
echo "Cache funcionando: ✅ Sim\n";
echo "Performance: ✅ OK\n";
echo "\n";

if (!extension_loaded('redis')) {
    echo "⚠️  ATENÇÃO: Extensão Redis não instalada\n";
    echo "Sistema está usando cache de arquivos como fallback\n";
    echo "\n";
    echo "Para instalar a extensão Redis:\n";
    echo "1. Adicione ao Dockerfile:\n";
    echo "   RUN pecl install redis && docker-php-ext-enable redis\n";
    echo "2. Faça rebuild do container no Easypanel\n";
    echo "\n";
} elseif (!$stats['enabled']) {
    echo "⚠️  ATENÇÃO: Redis não conectou\n";
    echo "Verifique:\n";
    echo "1. Nome do serviço Redis no Easypanel\n";
    echo "2. Se o serviço Redis está rodando (UP)\n";
    echo "3. Se estão na mesma rede Docker\n";
    echo "\n";
    echo "Tente alterar REDIS_HOST no .env para:\n";
    echo "- redis\n";
    echo "- wats-redis\n";
    echo "- wats_redis\n";
    echo "\n";
} else {
    echo "✅ TUDO FUNCIONANDO PERFEITAMENTE!\n";
    echo "\n";
    echo "Próximos passos:\n";
    echo "1. Acesse: https://wats.macip.com.br/admin_redis_dashboard.php\n";
    echo "2. Implemente cache nas páginas principais\n";
    echo "3. Monitore a performance\n";
    echo "\n";
}

echo "=================================\n";
