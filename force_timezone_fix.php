<?php
/**
 * Script para Forçar Correção de Timezone em Todos os Arquivos
 * Execute este script UMA VEZ para aplicar a correção
 */

$files = [
    'chat.php',
    'dashboard.php',
    'index.php',
    'api/chat_conversations.php',
    'api/chat_realtime_fetch.php',
];

$timezoneCode = "// ✅ FORÇAR TIMEZONE BRASIL\ndate_default_timezone_set('America/Sao_Paulo');\n\n";

echo "<!DOCTYPE html>\n<html>\n<head>\n<title>Forçar Correção de Timezone</title>\n<style>\nbody { font-family: Arial; max-width: 800px; margin: 50px auto; padding: 20px; }\n.success { color: green; }\n.error { color: red; }\n.info { color: blue; }\n</style>\n</head>\n<body>\n";

echo "<h1>🕐 Forçar Correção de Timezone</h1>\n";
echo "<p>Aplicando correção de timezone em arquivos críticos...</p>\n";

foreach ($files as $file) {
    echo "<hr>\n";
    echo "<h3>Arquivo: $file</h3>\n";
    
    if (!file_exists($file)) {
        echo "<p class='error'>❌ Arquivo não encontrado</p>\n";
        continue;
    }
    
    $content = file_get_contents($file);
    
    // Verificar se já tem a correção
    if (strpos($content, "date_default_timezone_set('America/Sao_Paulo')") !== false) {
        echo "<p class='info'>ℹ️ Arquivo já tem a correção</p>\n";
        continue;
    }
    
    // Adicionar após o <?php
    $content = preg_replace(
        '/(<\?php\s*\n)/',
        "<?php\n" . $timezoneCode,
        $content,
        1
    );
    
    // Salvar
    if (file_put_contents($file, $content)) {
        echo "<p class='success'>✅ Correção aplicada com sucesso!</p>\n";
    } else {
        echo "<p class='error'>❌ Erro ao salvar arquivo</p>\n";
    }
}

echo "<hr>\n";
echo "<h2>✅ Processo Concluído!</h2>\n";
echo "<p><strong>Próximos passos:</strong></p>\n";
echo "<ol>\n";
echo "<li>Faça <strong>redeploy</strong> do container no Easypanel</li>\n";
echo "<li>Aguarde 1-2 minutos</li>\n";
echo "<li>Acesse <a href='/verificar_timezone_aplicado.php'>/verificar_timezone_aplicado.php</a></li>\n";
echo "<li>Envie uma mensagem de teste</li>\n";
echo "</ol>\n";

echo "<p><a href='/' style='display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px;'>← Voltar para o sistema</a></p>\n";

echo "</body>\n</html>";
