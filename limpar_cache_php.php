<?php
/**
 * Limpar cache do PHP (OPcache)
 */

echo "<h1>Limpando Cache do PHP</h1>";

// Limpar OPcache
if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "<p>✅ OPcache limpo com sucesso!</p>";
    } else {
        echo "<p>❌ Falha ao limpar OPcache</p>";
    }
} else {
    echo "<p>ℹ️ OPcache não está habilitado</p>";
}

// Limpar cache de realpath
if (function_exists('clearstatcache')) {
    clearstatcache(true);
    echo "<p>✅ Cache de realpath limpo!</p>";
}

echo "<h2>Informações do PHP:</h2>";
echo "<ul>";
echo "<li>Versão: " . PHP_VERSION . "</li>";
echo "<li>OPcache: " . (function_exists('opcache_reset') ? 'Habilitado' : 'Desabilitado') . "</li>";
echo "</ul>";

echo "<h2>Próximo passo:</h2>";
echo "<p>Tente enviar a imagem novamente!</p>";
