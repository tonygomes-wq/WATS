<?php
/**
 * Script de Limpeza - Rate Limiting
 * Remove registros antigos para manter performance
 * Executar via cron diariamente
 */

if (php_sapi_name() !== 'cli') {
    die('Este script deve ser executado via CLI');
}

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/MetaRateLimiter.php';

echo "===========================================\n";
echo "  Limpeza de Rate Limiting - WATS\n";
echo "===========================================\n\n";

try {
    $rateLimiter = new MetaRateLimiter($pdo);
    
    echo "[" . date('Y-m-d H:i:s') . "] Iniciando limpeza...\n";
    
    // Executar limpeza
    $rateLimiter->cleanup();
    
    echo "[" . date('Y-m-d H:i:s') . "] ✅ Limpeza concluída com sucesso!\n\n";
    
    // Estatísticas
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM meta_rate_limit_tracking");
    $trackingCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM meta_rate_limit_alerts");
    $alertsCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "Registros restantes:\n";
    echo "  - Tracking: " . number_format($trackingCount) . "\n";
    echo "  - Alertas: " . number_format($alertsCount) . "\n\n";
    
    exit(0);
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
