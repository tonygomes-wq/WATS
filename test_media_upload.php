<?php
/**
 * Script de Teste - Upload de Mídias
 * 
 * Testa todo o fluxo de upload de arquivos
 * MACIP Tecnologia LTDA - 2026
 */

require_once 'config/database.php';

// Estilo CSS
echo '<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
h2 { color: #2563eb; border-bottom: 3px solid #2563eb; padding-bottom: 10px; }
h3 { color: #059669; margin-top: 30px; }
.success { color: #059669; font-weight: bold; }
.error { color: #dc2626; font-weight: bold; }
.warning { color: #d97706; font-weight: bold; }
.info { background: #eff6ff; border-left: 4px solid #2563eb; padding: 15px; margin: 15px 0; }
.code { background: #1f2937; color: #10b981; padding: 15px; border-radius: 5px; overflow-x: auto; font-family: monospace; }
pre { background: #f3f4f6; padding: 15px; border-radius: 5px; overflow-x: auto; }
.badge { display: inline-block; padding: 5px 10px; border-radius: 5px; font-size: 12px; font-weight: bold; }
.badge-success { background: #d1fae5; color: #059669; }
.badge-error { background: #fee2e2; color: #dc2626; }
.badge-warning { background: #fef3c7; color: #d97706; }
hr { margin: 30px 0; border: none; border-top: 2px solid #e5e7eb; }
</style>';

echo '<div class="container">';
echo "<h2>🔍 Diagnóstico de Upload de Mídias - WATS</h2>";
echo "<p><strong>Data/Hora:</strong> " . date('d/m/Y H:i:s') . "</p>";

// 1. Verificar permissões de diretórios
echo "<h3>1️⃣ Verificar Permissões de Diretórios</h3>";

$uploadDir = __DIR__ . '/uploads';
$testUserId = 1; // Usuário de teste
$userMediaDir = $uploadDir . "/user_{$testUserId}/media";

echo "<div class='info'>";
echo "<strong>Diretório base:</strong> $uploadDir<br>";
echo "<strong>Diretório do usuário:</strong> $userMediaDir<br>";
echo "</div>";

// Verificar se diretório base existe
if (!is_dir($uploadDir)) {
    echo "<span class='warning'>⚠️ Diretório base não existe</span><br>";
    echo "Tentando criar...<br>";
    if (mkdir($uploadDir, 0755, true)) {
        echo "<span class='success'>✅ Diretório base criado</span><br>";
    } else {
        echo "<span class='error'>❌ Erro ao criar diretório base</span><br>";
    }
} else {
    echo "<span class='success'>✅ Diretório base existe</span><br>";
}

// Verificar permissões do diretório base
if (is_writable($uploadDir)) {
    echo "<span class='success'>✅ Diretório base tem permissão de escrita</span><br>";
} else {
    echo "<span class='error'>❌ Diretório base SEM permissão de escrita</span><br>";
    echo "<div class='code'>";
    echo "# Comando para corrigir:<br>";
    echo "chmod -R 755 uploads<br>";
    echo "chown -R www-data:www-data uploads<br>";
    echo "</div>";
}

// Verificar se diretório do usuário existe
if (!is_dir($userMediaDir)) {
    echo "<span class='warning'>⚠️ Diretório do usuário não existe</span><br>";
    echo "Tentando criar...<br>";
    if (mkdir($userMediaDir, 0755, true)) {
        echo "<span class='success'>✅ Diretório do usuário criado</span><br>";
    } else {
        echo "<span class='error'>❌ Erro ao criar diretório do usuário</span><br>";
    }
} else {
    echo "<span class='success'>✅ Diretório do usuário existe</span><br>";
}

// Verificar permissões do diretório do usuário
if (is_writable($userMediaDir)) {
    echo "<span class='success'>✅ Diretório do usuário tem permissão de escrita</span><br>";
} else {
    echo "<span class='error'>❌ Diretório do usuário SEM permissão de escrita</span><br>";
}

// 2. Verificar configurações PHP
echo "<h3>2️⃣ Configurações PHP</h3>";

$uploadMaxFilesize = ini_get('upload_max_filesize');
$postMaxSize = ini_get('post_max_size');
$maxExecutionTime = ini_get('max_execution_time');
$memoryLimit = ini_get('memory_limit');

echo "<div class='info'>";
echo "<strong>upload_max_filesize:</strong> $uploadMaxFilesize<br>";
echo "<strong>post_max_size:</strong> $postMaxSize<br>";
echo "<strong>max_execution_time:</strong> {$maxExecutionTime}s<br>";
echo "<strong>memory_limit:</strong> $memoryLimit<br>";
echo "</div>";

// Verificar se valores são adequados
$uploadMaxBytes = return_bytes($uploadMaxFilesize);
$postMaxBytes = return_bytes($postMaxSize);

if ($uploadMaxBytes < 10 * 1024 * 1024) { // Menos de 10MB
    echo "<span class='warning'>⚠️ upload_max_filesize muito baixo (recomendado: 100M)</span><br>";
}

if ($postMaxBytes < 10 * 1024 * 1024) { // Menos de 10MB
    echo "<span class='warning'>⚠️ post_max_size muito baixo (recomendado: 100M)</span><br>";
}

if ($maxExecutionTime < 60) {
    echo "<span class='warning'>⚠️ max_execution_time muito baixo (recomendado: 300)</span><br>";
}

// 3. Testar criação de arquivo
echo "<h3>3️⃣ Teste de Criação de Arquivo</h3>";

$testFile = $userMediaDir . '/test_' . time() . '.txt';
$testContent = 'Teste de upload - ' . date('Y-m-d H:i:s');

if (file_put_contents($testFile, $testContent)) {
    echo "<span class='success'>✅ Arquivo de teste criado com sucesso</span><br>";
    echo "<div class='info'>";
    echo "<strong>Arquivo:</strong> $testFile<br>";
    echo "<strong>Conteúdo:</strong> $testContent<br>";
    echo "</div>";
    
    // Verificar se arquivo existe
    if (file_exists($testFile)) {
        echo "<span class='success'>✅ Arquivo existe e pode ser lido</span><br>";
        
        // Deletar arquivo de teste
        if (unlink($testFile)) {
            echo "<span class='success'>✅ Arquivo de teste deletado</span><br>";
        }
    }
} else {
    echo "<span class='error'>❌ Erro ao criar arquivo de teste</span><br>";
    echo "<div class='info'>";
    echo "<strong>Possíveis causas:</strong><br>";
    echo "• Permissões incorretas no diretório<br>";
    echo "• Disco cheio<br>";
    echo "• SELinux bloqueando escrita<br>";
    echo "</div>";
}

// 4. Verificar handlers
echo "<h3>4️⃣ Verificar Handlers de Mídia</h3>";

$handlers = [
    'api/handlers/WhatsAppMediaHandler.php',
    'api/handlers/MediaStorageManager.php',
    'api/handlers/MessageDatabaseManager.php',
    'api/send_media.php'
];

foreach ($handlers as $handler) {
    if (file_exists($handler)) {
        echo "<span class='success'>✅ $handler</span><br>";
    } else {
        echo "<span class='error'>❌ $handler NÃO ENCONTRADO</span><br>";
    }
}

// 5. Verificar Evolution API
echo "<h3>5️⃣ Verificar Evolution API</h3>";

echo "<div class='info'>";
echo "<strong>URL:</strong> " . EVOLUTION_API_URL . "<br>";
echo "<strong>API Key:</strong> " . substr(EVOLUTION_API_KEY, 0, 10) . "..." . substr(EVOLUTION_API_KEY, -5) . "<br>";
echo "</div>";

// Testar conectividade
$ch = curl_init(EVOLUTION_API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "<span class='error'>❌ Erro de conexão: $error</span><br>";
} elseif ($httpCode >= 200 && $httpCode < 400) {
    echo "<span class='success'>✅ Evolution API acessível (HTTP $httpCode)</span><br>";
} else {
    echo "<span class='warning'>⚠️ Evolution API retornou HTTP $httpCode</span><br>";
}

// 6. Verificar usuário logado
echo "<h3>6️⃣ Verificar Usuário Logado</h3>";

session_start();
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT id, name, email, evolution_instance FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "<div class='info'>";
        echo "<strong>Usuário:</strong> " . htmlspecialchars($user['name']) . "<br>";
        echo "<strong>Email:</strong> " . htmlspecialchars($user['email']) . "<br>";
        echo "<strong>ID:</strong> " . $user['id'] . "<br>";
        echo "<strong>Instância:</strong> " . ($user['evolution_instance'] ? htmlspecialchars($user['evolution_instance']) : '<span class="warning">Não configurada</span>') . "<br>";
        echo "</div>";
        
        if (!$user['evolution_instance']) {
            echo "<span class='warning'>⚠️ Usuário não tem instância configurada</span><br>";
            echo "<p>Configure em: <a href='/my_instance.php'>Configurar Minha Instância</a></p>";
        }
    }
} else {
    echo "<span class='warning'>⚠️ Usuário não está logado</span><br>";
    echo "<p>Faça login para testar upload de mídias</p>";
}

// Resumo
echo "<hr>";
echo "<h3>📝 Resumo do Diagnóstico</h3>";

$issues = [];

if (!is_writable($uploadDir)) {
    $issues[] = "Diretório de uploads sem permissão de escrita";
}

if ($uploadMaxBytes < 10 * 1024 * 1024) {
    $issues[] = "Limite de upload muito baixo";
}

if ($error) {
    $issues[] = "Evolution API não acessível";
}

if (empty($issues)) {
    echo "<div class='info' style='background: #d1fae5; border-left-color: #059669;'>";
    echo "<span class='success' style='font-size: 18px;'>✅ SISTEMA PRONTO PARA UPLOAD DE MÍDIAS!</span><br><br>";
    echo "Todos os testes passaram. O sistema está configurado corretamente.";
    echo "</div>";
} else {
    echo "<div class='info' style='background: #fee2e2; border-left-color: #dc2626;'>";
    echo "<span class='error' style='font-size: 18px;'>❌ PROBLEMAS ENCONTRADOS:</span><br><br>";
    foreach ($issues as $issue) {
        echo "• $issue<br>";
    }
    echo "</div>";
}

echo "<hr>";
echo "<p style='text-align: center; color: #6b7280; font-size: 12px;'>";
echo "MACIP Tecnologia LTDA - Sistema WATS<br>";
echo "Diagnóstico gerado em " . date('d/m/Y H:i:s');
echo "</p>";

echo '</div>'; // container

// Função auxiliar para converter tamanhos
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int) $val;
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}
?>
