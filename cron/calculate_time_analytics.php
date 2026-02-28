<?php
/**
 * Cron Job: Calcular Analytics de Tempo para Todos os Usuários
 * Executar diariamente: 0 2 * * * php /path/to/cron/calculate_time_analytics.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/predictive_analytics.php';

echo "[" . date('Y-m-d H:i:s') . "] Iniciando cálculo de time analytics...\n";

try {
    // Buscar todos os usuários ativos
    $stmt = $pdo->query("SELECT id FROM users WHERE status = 'active'");
    $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $processed = 0;
    $errors = 0;
    
    foreach ($users as $userId) {
        try {
            $analytics = new PredictiveAnalytics($pdo, $userId);
            $analytics->calculateTimeAnalytics();
            $processed++;
            echo "  - User {$userId}: OK\n";
        } catch (Exception $e) {
            $errors++;
            echo "  - User {$userId}: ERRO - " . $e->getMessage() . "\n";
        }
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Concluído: {$processed} processados, {$errors} erros\n";
    
} catch (Exception $e) {
    echo "[ERRO FATAL] " . $e->getMessage() . "\n";
    exit(1);
}
