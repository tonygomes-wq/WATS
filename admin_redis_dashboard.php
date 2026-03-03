<?php
require_once 'includes/auth.php';
require_once 'libs/RedisCache.php';

// Apenas admin
if (!$isAdmin) {
    header('Location: dashboard.php');
    exit;
}

$cache = new RedisCache();
$stats = $cache->getStats();

// Obter info do Redis
$redisInfo = [];
if ($stats['enabled']) {
    try {
        require_once 'config/redis.php';
        $redis = getRedisConnection();
        $info = $redis->info();
        
        $redisInfo = [
            'version' => $info['redis_version'] ?? 'N/A',
            'uptime_days' => round(($info['uptime_in_seconds'] ?? 0) / 86400, 1),
            'connected_clients' => $info['connected_clients'] ?? 0,
            'used_memory' => $info['used_memory_human'] ?? 'N/A',
            'total_keys' => $redis->dbSize(),
            'hits' => $info['keyspace_hits'] ?? 0,
            'misses' => $info['keyspace_misses'] ?? 0,
        ];
        
        // Calcular hit rate do Redis
        $total = $redisInfo['hits'] + $redisInfo['misses'];
        $redisInfo['hit_rate'] = $total > 0 ? round(($redisInfo['hits'] / $total) * 100, 2) : 0;
        
    } catch (Exception $e) {
        $redisInfo['error'] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redis Dashboard - WATS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #2563eb;
        }
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .status-active { background: #10b981; color: white; }
        .status-inactive { background: #ef4444; color: white; }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
        }
        .btn-danger { background: #ef4444; color: white; }
        .btn-primary { background: #2563eb; color: white; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <h1>🚀 Redis Dashboard</h1>
        
        <div class="stat-card">
            <h3>Status do Redis</h3>
            <span class="status-badge <?= $stats['enabled'] ? 'status-active' : 'status-inactive' ?>">
                <?= $stats['enabled'] ? 'ATIVO' : 'INATIVO' ?>
            </span>
        </div>
        
        <?php if ($stats['enabled']): ?>
        <h2>Estatísticas da Aplicação</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['hits'] ?></div>
                <div class="stat-label">Cache Hits</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['misses'] ?></div>
                <div class="stat-label">Cache Misses</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['hit_rate'] ?>%</div>
                <div class="stat-label">Hit Rate</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['sets'] ?></div>
                <div class="stat-label">Writes</div>
            </div>
        </div>
        
        <h2>Informações do Redis Server</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $redisInfo['version'] ?? 'N/A' ?></div>
                <div class="stat-label">Versão</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $redisInfo['uptime_days'] ?? 'N/A' ?></div>
                <div class="stat-label">Uptime (dias)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $redisInfo['used_memory'] ?? 'N/A' ?></div>
                <div class="stat-label">Memória Usada</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $redisInfo['total_keys'] ?? 'N/A' ?></div>
                <div class="stat-label">Total de Chaves</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $redisInfo['hit_rate'] ?? 'N/A' ?>%</div>
                <div class="stat-label">Hit Rate (Redis)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $redisInfo['connected_clients'] ?? 'N/A' ?></div>
                <div class="stat-label">Clientes Conectados</div>
            </div>
        </div>
        
        <div class="stat-card" style="margin-top: 20px;">
            <h3>Ações</h3>
            <button onclick="clearCache()" class="btn btn-danger">Limpar Todo Cache</button>
            <button onclick="location.reload()" class="btn btn-primary">Atualizar</button>
        </div>
        <?php else: ?>
        <div class="stat-card">
            <p>Redis não está ativo. Sistema usando cache de arquivos como fallback.</p>
            <p>Para ativar Redis, altere no .env:</p>
            <code>CACHE_DRIVER=redis</code>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
    function clearCache() {
        if (confirm('Tem certeza que deseja limpar todo o cache?')) {
            fetch('api/clear_cache.php', { method: 'POST' })
                .then(r => r.json())
                .then(data => {
                    alert(data.message);
                    location.reload();
                })
                .catch(err => {
                    alert('Erro ao limpar cache: ' + err);
                });
        }
    }
    </script>
</body>
</html>
