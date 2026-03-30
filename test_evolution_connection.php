<?php
/**
 * Teste de Conectividade com Evolution API
 * Verifica se o WATS consegue se conectar à Evolution API
 */

session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    die('Não autenticado. <a href="/login.php">Fazer login</a>');
}

$userId = $_SESSION['user_id'];

// Buscar configuração do usuário
$stmt = $pdo->prepare("SELECT evolution_api_url, evolution_token, evolution_instance FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$apiUrl = $user['evolution_api_url'] ?? EVOLUTION_API_URL;
$apiKey = $user['evolution_token'] ?? EVOLUTION_API_KEY;
$instance = $user['evolution_instance'] ?? '';

// Testar diferentes URLs
$urlsToTest = [
    'URL Configurada' => $apiUrl,
    'URL Pública' => 'https://evolution.macip.com.br',
    'URL Interna (se no Easypanel)' => 'http://evolution:8080',
];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Conectividade - Evolution API</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .test-result {
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 4px solid;
        }
        .success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        .warning {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        .info {
            background: #d1ecf1;
            border-color: #17a2b8;
            color: #0c5460;
        }
        pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 12px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            margin: 5px;
        }
        .btn:hover {
            background: #2980b9;
        }
        .metric {
            display: inline-block;
            margin: 0 10px;
            padding: 5px 10px;
            background: #ecf0f1;
            border-radius: 4px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>🔍 Teste de Conectividade - Evolution API</h1>
            <p>Verificando se o WATS consegue se conectar à Evolution API...</p>
        </div>

        <?php foreach ($urlsToTest as $label => $url): ?>
            <?php
            if (empty($url)) continue;
            
            $testUrl = rtrim($url, '/') . '/instance/connectionState/' . $instance;
            $startTime = microtime(true);
            
            $ch = curl_init($testUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['apikey: ' . $apiKey],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_FOLLOWLOCATION => true
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $totalTime = round((microtime(true) - $startTime) * 1000);
            curl_close($ch);
            
            $success = ($httpCode === 200 || $httpCode === 201) && !$curlError;
            $resultClass = $success ? 'success' : 'error';
            ?>
            
            <div class="card">
                <h2><?php echo htmlspecialchars($label); ?></h2>
                <div class="test-result <?php echo $resultClass; ?>">
                    <strong><?php echo $success ? '✅ SUCESSO' : '❌ FALHOU'; ?></strong>
                    <div class="metric">URL: <?php echo htmlspecialchars($url); ?></div>
                    <div class="metric">HTTP: <?php echo $httpCode ?: 'N/A'; ?></div>
                    <div class="metric">Tempo: <?php echo $totalTime; ?>ms</div>
                </div>
                
                <?php if ($curlError): ?>
                    <div class="test-result error">
                        <strong>Erro cURL:</strong> <?php echo htmlspecialchars($curlError); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($response && strlen($response) < 1000): ?>
                    <details>
                        <summary style="cursor: pointer; padding: 10px; background: #ecf0f1; border-radius: 4px; margin-top: 10px;">
                            Ver Resposta
                        </summary>
                        <pre><?php echo htmlspecialchars($response); ?></pre>
                    </details>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="test-result info" style="margin-top: 10px;">
                        <strong>💡 Recomendação:</strong> Use esta URL no sistema!
                        <br><br>
                        <form method="POST" action="fix_evolution_url.php" style="display: inline;">
                            <input type="hidden" name="new_url" value="<?php echo htmlspecialchars($url); ?>">
                            <button type="submit" class="btn">Usar Esta URL</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        
        <div class="card">
            <h2>📋 Diagnóstico e Soluções</h2>
            
            <div class="test-result info">
                <strong>ℹ️ Sobre Redes Docker no Easypanel:</strong><br><br>
                
                Se o WATS e a Evolution API estão no mesmo Easypanel:
                <ul>
                    <li>✅ Use o nome do serviço: <code>http://evolution:8080</code></li>
                    <li>✅ Ou use a URL pública: <code>https://evolution.macip.com.br</code></li>
                    <li>❌ NÃO use IPs internos: <code>172.18.x.x</code></li>
                </ul>
            </div>
            
            <div class="test-result warning">
                <strong>⚠️ Timeout de 30 segundos:</strong><br><br>
                
                O erro "Connection timed out after 30001 milliseconds" indica que:
                <ol>
                    <li>A URL está incorreta ou inacessível</li>
                    <li>Os containers não estão na mesma rede Docker</li>
                    <li>Firewall bloqueando a conexão</li>
                </ol>
                
                <strong>Solução:</strong> Use a URL que funcionou no teste acima.
            </div>
            
            <div class="test-result info">
                <strong>🔧 Configurar Webhook:</strong><br><br>
                
                Após corrigir a URL, configure o webhook:
                <ol>
                    <li>Acesse: <a href="/test_webhook_messages.php">/test_webhook_messages.php</a></li>
                    <li>Clique em "Configurar Webhook Automaticamente"</li>
                    <li>Teste enviando uma mensagem do WhatsApp</li>
                </ol>
            </div>
        </div>
        
        <div class="card">
            <h2>🔑 Sobre APP_KEY e ENCRYPTION_KEY</h2>
            
            <div class="test-result success">
                <strong>✅ Não há problema em migrar com as mesmas chaves!</strong><br><br>
                
                Essas chaves são usadas para:
                <ul>
                    <li><code>APP_KEY</code>: Criptografia de sessões e cookies</li>
                    <li><code>ENCRYPTION_KEY</code>: Criptografia de dados sensíveis no banco</li>
                </ul>
                
                <strong>Importante:</strong> Se você mudar essas chaves, os usuários precisarão fazer login novamente
                e dados criptografados antigos não poderão ser descriptografados.
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="/test_webhook_messages.php" class="btn">🔍 Testar Webhook</a>
            <a href="/chat.php" class="btn">💬 Ir para Chat</a>
        </div>
    </div>
</body>
</html>
