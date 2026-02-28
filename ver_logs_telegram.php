<?php
/**
 * Ver Logs do Telegram em Tempo Real
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h1>📋 Logs do Telegram</h1>";
echo "<p>Atualizando a cada 3 segundos...</p>";
echo "<hr>";

// Buscar logs recentes
$logFile = 'logs/' . date('Y-m-d') . '.log';

if (file_exists($logFile)) {
    $lines = file($logFile);
    $telegramLogs = array_filter($lines, function($line) {
        return stripos($line, 'telegram') !== false || 
               stripos($line, 'TelegramChannel') !== false;
    });
    
    if (empty($telegramLogs)) {
        echo "<p>⚠️ Nenhum log do Telegram encontrado ainda.</p>";
        echo "<p>Envie uma mensagem no Telegram para gerar logs.</p>";
    } else {
        echo "<pre style='background: #1a1a1a; color: #00ff00; padding: 20px; border-radius: 8px; overflow-x: auto;'>";
        echo htmlspecialchars(implode('', array_slice($telegramLogs, -50)));
        echo "</pre>";
    }
} else {
    echo "<p>⚠️ Arquivo de log não encontrado: {$logFile}</p>";
}

echo "<hr>";
echo "<p><a href='?refresh=1'>🔄 Atualizar</a> | <a href='chat.php'>💬 Ir para Chat</a></p>";

// Auto-refresh a cada 3 segundos
echo "<script>setTimeout(() => location.reload(), 3000);</script>";
