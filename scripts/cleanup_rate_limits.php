<?php
/**
 * Script para Limpar Rate Limits Antigos
 * 
 * Executar via cron a cada hora:
 * 0 * * * * php /caminho/para/scripts/cleanup_rate_limits.php
 */

require_once __DIR__ . '/../includes/RateLimiter.php';
require_once __DIR__ . '/../includes/Logger.php';

echo "Iniciando limpeza de rate limits...\n";

$rateLimiter = new RateLimiter();
$removed = $rateLimiter->cleanup(3600); // Remover arquivos mais antigos que 1 hora

echo "✅ Removidos $removed arquivos de rate limit\n";

// Log da limpeza
Logger::info('Limpeza de rate limits executada', [
    'files_removed' => $removed
]);

// Limpar logs antigos também (manter últimos 30 dias)
$logsRemoved = Logger::cleanup(30);
echo "✅ Removidos $logsRemoved arquivos de log antigos\n";

Logger::info('Limpeza de logs executada', [
    'files_removed' => $logsRemoved
]);

echo "Limpeza concluída!\n";
