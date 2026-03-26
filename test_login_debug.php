<?php
/**
 * Script de Diagnóstico de Login
 * Testa o processo de login e mostra erros detalhados
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>Diagnóstico de Login</title></head><body>";
echo "<h1>Diagnóstico de Login</h1>";

session_start();

echo "<h2>1. Verificando arquivos necessários...</h2>";
$files = [
    'config/database.php',
    'includes/functions.php',
    'includes/totp.php',
    'includes/AuthService.php',
    'includes/RateLimiter.php',
    'includes/SecurityHelpers.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ $file<br>";
    } else {
        echo "❌ $file NÃO ENCONTRADO<br>";
    }
}

echo "<h2>2. Carregando dependências...</h2>";
try {
    require_once 'config/database.php';
    echo "✅ Database conectado<br>";
    
    require_once 'includes/functions.php';
    echo "✅ Functions carregado<br>";
    
    require_once 'includes/totp.php';
    echo "✅ TOTP carregado<br>";
    
    require_once 'includes/AuthService.php';
    echo "✅ AuthService carregado<br>";
    
    require_once 'includes/RateLimiter.php';
    echo "✅ RateLimiter carregado<br>";
    
    require_once 'includes/SecurityHelpers.php';
    echo "✅ SecurityHelpers carregado<br>";
} catch (Exception $e) {
    echo "❌ Erro ao carregar: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    exit;
}

echo "<h2>3. Testando busca de usuário...</h2>";
$email = 'suporte@macip.com.br';

try {
    $stmt = $pdo->prepare("SELECT id, email, two_factor_enabled, two_factor_secret FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "✅ Usuário encontrado<br>";
        echo "ID: " . $user['id'] . "<br>";
        echo "Email: " . $user['email'] . "<br>";
        echo "2FA Habilitado: " . ($user['two_factor_enabled'] ? 'SIM' : 'NÃO') . "<br>";
        echo "2FA Secret: " . ($user['two_factor_secret'] ? 'EXISTE' : 'VAZIO') . "<br>";
    } else {
        echo "❌ Usuário não encontrado<br>";
    }
} catch (Exception $e) {
    echo "❌ Erro ao buscar usuário: " . $e->getMessage() . "<br>";
}

echo "<h2>4. Testando AuthService...</h2>";
try {
    $authService = new AuthService($pdo);
    echo "✅ AuthService instanciado<br>";
    
    // Testar com senha incorreta (não vamos expor a senha real)
    $result = $authService->authenticate($email, 'senha_teste_incorreta');
    echo "Resultado do teste: " . json_encode($result) . "<br>";
    
    if (!$result['success']) {
        echo "✅ AuthService funcionando (rejeitou senha incorreta)<br>";
    }
} catch (Exception $e) {
    echo "❌ Erro no AuthService: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h2>5. Verificando triggers problemáticos...</h2>";
try {
    $stmt = $pdo->query("
        SELECT TRIGGER_NAME, EVENT_OBJECT_TABLE, DEFINER
        FROM information_schema.TRIGGERS
        WHERE TRIGGER_SCHEMA = DATABASE()
        AND DEFINER LIKE '%faceso56%'
    ");
    $triggers = $stmt->fetchAll();
    
    if (count($triggers) > 0) {
        echo "⚠️ Encontrados " . count($triggers) . " triggers com DEFINER incorreto:<br>";
        foreach ($triggers as $trigger) {
            echo "- " . $trigger['TRIGGER_NAME'] . " na tabela " . $trigger['EVENT_OBJECT_TABLE'] . "<br>";
        }
        echo "<br><strong>SOLUÇÃO:</strong> Execute o script fix_triggers.sql<br>";
    } else {
        echo "✅ Nenhum trigger com DEFINER incorreto<br>";
    }
} catch (Exception $e) {
    echo "❌ Erro ao verificar triggers: " . $e->getMessage() . "<br>";
}

echo "<h2>6. Testando RateLimiter...</h2>";
try {
    $rateLimiter = new RateLimiter();
    $testIP = '127.0.0.1';
    $allowed = $rateLimiter->allow($testIP, 'test', 5, 60);
    echo "✅ RateLimiter funcionando (allowed: " . ($allowed ? 'true' : 'false') . ")<br>";
} catch (Exception $e) {
    echo "❌ Erro no RateLimiter: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h2>Conclusão</h2>";
echo "<p>Se todos os itens acima estão ✅, o problema pode ser:</p>";
echo "<ul>";
echo "<li>Senha incorreta</li>";
echo "<li>2FA ainda habilitado (verificar banco)</li>";
echo "<li>Triggers com DEFINER incorreto</li>";
echo "<li>Erro JavaScript no frontend</li>";
echo "</ul>";

echo "<h2>Teste de Login Manual</h2>";
echo "<form method='POST' action='api/login_ajax.php'>";
echo "<p>Email: <input type='email' name='email' value='suporte@macip.com.br'></p>";
echo "<p>Senha: <input type='password' name='password'></p>";
echo "<input type='hidden' name='action' value='login'>";
echo "<p><button type='submit'>Testar Login</button></p>";
echo "</form>";

echo "</body></html>";
?>
