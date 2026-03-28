<?php
/**
 * Teste de URL Evolution Go
 * Testa diferentes URLs para encontrar a correta
 */

require_once 'config/database.php';

$apiKey = 'a9F3kLm8Qz2XvP7rT1bYcN6dE4uHsJ5W';

$urls = [
    'http://evogo:4000' => 'Comunicação interna (nome do serviço)',
    'https://evogo.macip.com.br' => 'URL pública sem porta',
    'https://evogo.macip.com.br:4000' => 'URL pública com porta 4000',
    'http://evogo.macip.com.br:4000' => 'URL pública HTTP com porta 4000',
];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de URL - Evolution Go</title>
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
        .test-item.testing {
            background: #fff3cd;
            border-left-color: #ffc107;
        }
        pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        .badge-error {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Teste de URL - Evolution Go API</h1>
        
        <p><strong>Objetivo:</strong> Encontrar a URL correta para conectar com a Evolution Go API no Easypanel.</p>
        
        <?php
        foreach ($urls as $url => $description) {
            echo "<div class='test-item testing'>";
            echo "<h3>Testando: <code>$url</code></h3>";
            echo "<p><small>$description</small></p>";
            
            $ch = curl_init($url . '/');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . $apiKey
            ]);
            
            $start = microtime(true);
            $response = curl_exec($ch);
            $elapsed = round((microtime(true) - $start) * 1000, 2);
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            curl_close($ch);
            
            echo "<pre>";
            echo "HTTP Status: " . ($httpCode ?: 'N/A') . "\n";
            echo "Tempo: {$elapsed}ms\n";
            echo "cURL Error: " . ($curlError ?: 'Nenhum') . "\n";
            echo "</pre>";
            
            if ($httpCode >= 200 && $httpCode < 500) {
                echo "<div class='badge badge-success'>✅ SERVIDOR RESPONDEU!</div>";
                echo "<p><strong>Esta URL funciona!</strong> Use esta no .env:</p>";
                echo "<pre>EVOLUTION_GO_API_URL=$url</pre>";
                
                // Testar endpoint específico
                echo "<h4>Testando endpoint /instance/fetchInstances:</h4>";
                $ch2 = curl_init($url . '/instance/fetchInstances');
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                    'apikey: ' . $apiKey
                ]);
                
                $response2 = curl_exec($ch2);
                $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                curl_close($ch2);
                
                echo "<pre>";
                echo "HTTP Status: $httpCode2\n";
                echo "Resposta: " . substr($response2, 0, 200) . "\n";
                echo "</pre>";
                
            } else {
                echo "<div class='badge badge-error'>❌ NÃO RESPONDEU</div>";
                if ($curlError) {
                    echo "<p><strong>Erro:</strong> $curlError</p>";
                }
            }
            
            echo "</div>";
        }
        ?>
        
        <div style="margin-top: 40px; padding: 20px; background: #e8f4f8; border-radius: 5px; border-left: 4px solid #3498db;">
            <h3>📋 Próximos Passos</h3>
            <ol>
                <li>Identifique qual URL funcionou (marcada com ✅)</li>
                <li>Atualize o arquivo <code>.env</code> com a URL correta</li>
                <li>Faça redeploy do WATS no Easypanel</li>
                <li>Teste novamente a configuração da Evolution Go</li>
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
