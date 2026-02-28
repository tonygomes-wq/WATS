<?php
/**
 * Job para processar análise de sentimento em background
 * Executar via cron: */5 * * * * php /path/to/process_sentiment.php
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/sentiment_analyzer.php';

$logPrefix = '[SENTIMENT_JOB]';

try {
    $analyzer = new SentimentAnalyzer($pdo);
    
    // Processar até 100 respostas por execução
    $processed = $analyzer->processPendingSentiments(100);
    
    if ($processed > 0) {
        echo "$logPrefix Processadas {$processed} respostas\n";
        error_log("$logPrefix Processadas {$processed} respostas");
    }
    
    exit(0);
} catch (Exception $e) {
    echo "$logPrefix Erro: " . $e->getMessage() . "\n";
    error_log("$logPrefix Erro: " . $e->getMessage());
    exit(1);
}
