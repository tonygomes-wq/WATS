<?php
/**
 * Teste de Conexão Evolution Go API
 * Diagnóstico detalhado de conectividade
 */

require_once 'config/database.php';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Conexão - Evolution Go API</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 1000px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        .test-section {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #3498db;
        }
        .success {
            color: #27ae60;
            font-weight: bold;
        }
        .error {
            color: #e74c3c;
            font-weight: bold;
        }
        .warning {
            color: #f39c12;
            font-weight: bold;
        }
        pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 13px;
        }
        .info-box {
            background: #e8f4f8;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnóstico de Conexão - Evolution Go API</h1>
        
        <div class="info-box">
            <strong>ℹ️ Objetivo:</strong> Verificar conectividade com a API Evolution Go e identificar problemas de rede.
        </div>

        <?php
        $apiUrl = EVOLUTION_GO_API_URL;
        $apiKey = EVOLUTION_GO_API_KEY;
        
        echo "<div class='test-section'>";
        echo "<h2>1. Configuração</h2>";
        echo "<pre>";
        echo "Evolution Go URL: " . htmlspecialchars($apiUrl) . "\n";
        echo "Evolution Go API Key: " . substr($apiKey, 0, 10) . "..." . substr($apiKey, -5) . "\n";
        echo "</pre>";
        echo "</div>";
        
        // Teste 1: DNS Resolution
        echo "<div class='test-section'>";
        echo "<h2>2. Resolução DNS</h2>";
        $host = parse_url($apiUrl, PHP_URL_HOST);
        echo "<p>Resolvendo: <strong>$host</strong></p>";
        
        $ip = gethostbyname($host);
        if ($ip === $host) {
            echo "<p class='error'>❌ Falha ao resolver DNS</p>";
            echo "<p>O domínio não foi encontrado. Verifique se o domínio está correto.</p>";
        } else {
            echo "<p class='success'>✅ DNS resolvido: $ip</p>";
        }
        echo "</div>";
        
        // Teste 2: Ping básico (HTTP HEAD)
        echo "<div class='test-section'>";
        echo "<h2>3. Teste de Conectividade (HTTP HEAD)</h2>";
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $start = microtime(true);
        curl_exec($ch);
        $elapsed = round((microtime(true) - $start) * 1000, 2);
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);
        
        echo "<pre>";
        echo "HTTP Status Code: " . ($httpCode ?: 'N/A') . "\n";
        echo "Tempo de resposta: {$elapsed}ms\n";
        echo "cURL Error Code: " . ($curlErrno ?: 'Nenhum') . "\n";
        echo "cURL Error Message: " . ($curlError ?: 'Nenhum') . "\n";
        echo "</pre>";
        
        if ($httpCode >= 200 && $httpCode < 500) {
            echo "<p class='success'>✅ Servidor respondeu (HTTP $httpCode)</p>";
        } else {
            echo "<p class='error'>❌ Servidor não respondeu</p>";
            if ($curlError) {
                echo "<p class='warning'>Erro: $curlError</p>";
            }
        }
        echo "</div>";
        
        // Teste 3: Endpoint de status (se existir)
        echo "<div class='test-section'>";
        echo "<h2>4. Teste de Endpoint de Status</h2>";
        
        $statusEndpoints = [
            '/health',
            '/status',
            '/api/health',
            '/api/status',
            '/'
        ];
        
        foreach ($statusEndpoints as $endpoint) {
            $testUrl = rtrim($apiUrl, '/') . $endpoint;
            echo "<p>Testando: <code>$testUrl</code></p>";
            
            $ch = curl_init($testUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . $apiKey
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode >= 200 && $httpCode < 300) {
                echo "<p class='success'>✅ HTTP $httpCode - Endpoint respondeu</p>";
                echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
                break;
            } elseif ($httpCode >= 400 && $httpCode < 500) {
                echo "<p class='warning'>⚠️ HTTP $httpCode - Endpoint existe mas retornou erro</p>";
            } else {
                echo "<p class='error'>❌ HTTP $httpCode - Endpoint não respondeu</p>";
            }
        }
        echo "</div>";
        
        // Teste 4: Verificar instância no banco
        echo "<div class='test-section'>";
        echo "<h2>5. Instâncias Configuradas</h2>";
        
        $stmt = $pdo->query("
            SELECT id, name, evolution_go_instance, evolution_go_token, whatsapp_provider
            FROM users 
            WHERE evolution_go_instance IS NOT NULL 
            AND evolution_go_instance != ''
        ");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($users) > 0) {
            echo "<p class='success'>✅ Encontrados " . count($users) . " usuário(s) com Evolution Go configurado:</p>";
            echo "<ul>";
            foreach ($users as $user) {
                echo "<li>";
                echo "<strong>" . htmlspecialchars($user['name']) . "</strong> (ID: {$user['id']})<br>";
                echo "Instance: " . htmlspecialchars($user['evolution_go_instance']) . "<br>";
                echo "Provider: " . htmlspecialchars($user['whatsapp_provider']) . "<br>";
                echo "Token: " . substr($user['evolution_go_token'], 0, 10) . "...";
                echo "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p class='warning'>⚠️ Nenhum usuário com Evolution Go configurado</p>";
        }
        echo "</div>";
        
        // Teste 5: Testar endpoint real da Evolution Go
        echo "<div class='test-section'>";
        echo "<h2>6. Teste de Endpoint Real (Instance Info)</h2>";
        
        if (count($users) > 0) {
            $testUser = $users[0];
            $instance = $testUser['evolution_go_instance'];
            $token = $testUser['evolution_go_token'];
            
            $testUrl = rtrim($apiUrl, '/') . '/instance/connectionState/' . $instance;
            echo "<p>Testando: <code>$testUrl</code></p>";
            
            $ch = curl_init($testUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'apikey: ' . $token
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            echo "<pre>";
            echo "HTTP Status: " . ($httpCode ?: 'N/A') . "\n";
            echo "cURL Error: " . ($curlError ?: 'Nenhum') . "\n";
            echo "\nResposta:\n";
            echo htmlspecialchars($response ?: 'Sem resposta');
            echo "</pre>";
            
            if ($httpCode >= 200 && $httpCode < 300) {
                echo "<p class='success'>✅ API Evolution Go está funcionando!</p>";
            } else {
                echo "<p class='error'>❌ API Evolution Go não respondeu corretamente</p>";
            }
        } else {
            echo "<p class='warning'>⚠️ Configure uma instância primeiro para testar</p>";
        }
        echo "</div>";
        
        // Recomendações
        echo "<div class='test-section'>";
        echo "<h2>7. Recomendações</h2>";
        echo "<ul>";
        
        if ($ip === $host) {
            echo "<li class='error'>❌ <strong>DNS não resolve:</strong> Verifique se o domínio <code>$host</code> está correto e acessível</li>";
        }
        
        if ($curlErrno === 28) {
            echo "<li class='error'>❌ <strong>Timeout:</strong> O servidor não respondeu a tempo. Possíveis causas:";
            echo "<ul>";
            echo "<li>Servidor Evolution Go não está rodando</li>";
            echo "<li>Firewall bloqueando a conexão</li>";
            echo "<li>URL incorreta</li>";
            echo "</ul></li>";
        }
        
        if ($curlErrno === 7) {
            echo "<li class='error'>❌ <strong>Falha ao conectar:</strong> Não foi possível estabelecer conexão com o servidor</li>";
        }
        
        echo "<li>✅ Verifique se o servidor Evolution Go está rodando em <code>$apiUrl</code></li>";
        echo "<li>✅ Teste acessar a URL diretamente no navegador</li>";
        echo "<li>✅ Verifique logs do servidor Evolution Go</li>";
        echo "<li>✅ Confirme que a porta está aberta e acessível</li>";
        echo "</ul>";
        echo "</div>";
        ?>
        
        <div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid #ecf0f1; text-align: center; color: #7f8c8d;">
            <p><strong>WATS - Sistema Multi-Canal</strong></p>
            <p>MACIP Tecnologia LTDA © <?php echo date('Y'); ?></p>
            <p style="font-size: 12px; margin-top: 10px;">
                <a href="/" style="color: #3498db; text-decoration: none;">← Voltar para o sistema</a> | 
                <a href="javascript:location.reload()" style="color: #3498db; text-decoration: none;">🔄 Recarregar teste</a>
            </p>
        </div>
    </div>
</body>
</html>
