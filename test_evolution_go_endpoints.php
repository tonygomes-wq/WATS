<?php
/**
 * Teste de Endpoints Evolution Go
 * Testa endpoints específicos da Evolution Go API
 */

$baseUrl = 'http://evogo.macip.com.br:4000';
$apiKey = 'a9F3kLm8Qz2XvP7rT1bYcN6dE4uHsJ5W';
$instanceName = 'TESTE-WATS';

$endpoints = [
    'GET /' => 'Raiz da API',
    'GET /instance/fetchInstances' => 'Listar instâncias',
    'GET /instance/connectionState/' . $instanceName => 'Status da instância',
    'GET /instance/connect/' . $instanceName => 'Conectar/Gerar QR Code',
];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Endpoints - Evolution Go</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 1200px;
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
        .test-item {
            margin: 20px 0;
            padding: 20px;
            border-radius: 5px;
            border-left: 4px solid #ccc;
        }
        .test-item.success {
            background: #d4edda;
            border-left-color: #28a745;
        }
        .test-item.error {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
            max-height: 400px;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 10px;
        }
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        .badge-error {
            background: #f8d7da;
            color: #721c24;
        }
        .info-box {
            background: #e8f4f8;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Teste de Endpoints - Evolution Go API</h1>
        
        <div class="info-box">
            <strong>Base URL:</strong> <?php echo $baseUrl; ?><br>
            <strong>API Key:</strong> <?php echo substr($apiKey, 0, 10); ?>...<br>
            <strong>Instance Name:</strong> <?php echo $instanceName; ?>
        </div>
        
        <?php
        foreach ($endpoints as $endpoint => $description) {
            list($method, $path) = explode(' ', $endpoint, 2);
            $fullUrl = $baseUrl . $path;
            
            echo "<div class='test-item'>";
            echo "<h3>$method <code>$path</code></h3>";
            echo "<p><small>$description</small></p>";
            
            $ch = curl_init($fullUrl);
            
            if ($method === 'GET') {
                curl_setopt($ch, CURLOPT_HTTPGET, true);
            }
            
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'apikey: ' . $apiKey
            ]);
            
            $start = microtime(true);
            $response = curl_exec($ch);
            $elapsed = round((microtime(true) - $start) * 1000, 2);
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            $isSuccess = ($httpCode >= 200 && $httpCode < 400);
            
            if ($isSuccess) {
                echo "<div class='badge badge-success'>✅ HTTP $httpCode</div>";
                echo "<span style='color: #666;'>Tempo: {$elapsed}ms</span>";
            } else {
                echo "<div class='badge badge-error'>❌ HTTP $httpCode</div>";
                echo "<span style='color: #666;'>Tempo: {$elapsed}ms</span>";
            }
            
            if ($curlError) {
                echo "<p style='color: #dc3545;'><strong>Erro cURL:</strong> $curlError</p>";
            }
            
            echo "<h4>Resposta:</h4>";
            echo "<pre>";
            
            // Tentar formatar JSON
            $json = json_decode($response, true);
            if ($json) {
                echo htmlspecialchars(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            } else {
                echo htmlspecialchars(substr($response, 0, 1000));
            }
            
            echo "</pre>";
            echo "</div>";
        }
        ?>
        
        <div class="info-box" style="margin-top: 40px;">
            <h3>📋 Interpretação dos Resultados</h3>
            <ul>
                <li><strong>HTTP 200:</strong> ✅ Endpoint funcionando corretamente</li>
                <li><strong>HTTP 404:</strong> ⚠️ Endpoint não existe (pode ser normal se a instância não foi criada ainda)</li>
                <li><strong>HTTP 401/403:</strong> ❌ Problema de autenticação (API Key incorreta)</li>
                <li><strong>HTTP 500:</strong> ❌ Erro interno do servidor</li>
                <li><strong>Timeout:</strong> ❌ Servidor não respondeu a tempo</li>
            </ul>
        </div>
        
        <div style="margin-top: 40px; padding: 20px; background: #fff3cd; border-radius: 5px; border-left: 4px solid #ffc107;">
            <h3>⚠️ Próximos Passos</h3>
            <ol>
                <li>Se todos os endpoints retornarem 404, a Evolution Go pode não estar configurada corretamente</li>
                <li>Verifique os logs da Evolution Go no Easypanel</li>
                <li>Confirme que a variável <code>GLOBAL_API_KEY</code> na Evolution Go é: <code><?php echo $apiKey; ?></code></li>
                <li>Tente criar uma instância manualmente na Evolution Go primeiro</li>
            </ol>
        </div>
        
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
