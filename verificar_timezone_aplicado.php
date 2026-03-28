<?php
/**
 * Verificação Rápida: Timezone Aplicado?
 * Verifica se a correção de timezone foi aplicada nos arquivos
 */

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Correção de Timezone</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 900px;
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
        .check-item {
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 4px solid #ccc;
        }
        .check-item.success {
            background: #d4edda;
            border-left-color: #28a745;
        }
        .check-item.error {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        .check-item.warning {
            background: #fff3cd;
            border-left-color: #ffc107;
        }
        .icon {
            font-size: 20px;
            margin-right: 10px;
        }
        pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
        }
        .summary {
            margin-top: 30px;
            padding: 20px;
            background: #e8f4f8;
            border-radius: 5px;
            border-left: 4px solid #3498db;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Verificação: Correção de Timezone Aplicada?</h1>
        
        <?php
        $files = [
            'api/chat_send.php',
            'api/send_media.php',
            'api/chat_messages.php',
            'api/chat_webhook.php',
            'api/zapi_webhook.php',
            'api/unified_webhook.php'
        ];
        
        $allOk = true;
        $results = [];
        
        foreach ($files as $file) {
            $fullPath = __DIR__ . '/' . $file;
            
            if (!file_exists($fullPath)) {
                $results[] = [
                    'file' => $file,
                    'status' => 'error',
                    'message' => 'Arquivo não encontrado'
                ];
                $allOk = false;
                continue;
            }
            
            $content = file_get_contents($fullPath);
            
            // Verificar se tem a linha de correção
            $hasCorrection = strpos($content, "date_default_timezone_set('America/Sao_Paulo')") !== false;
            
            if ($hasCorrection) {
                // Verificar se está no lugar certo (antes do require database.php)
                $lines = explode("\n", $content);
                $correctionLine = -1;
                $requireLine = -1;
                
                foreach ($lines as $index => $line) {
                    if (strpos($line, "date_default_timezone_set('America/Sao_Paulo')") !== false) {
                        $correctionLine = $index;
                    }
                    if (strpos($line, "require_once") !== false && strpos($line, "database.php") !== false) {
                        $requireLine = $index;
                        break;
                    }
                }
                
                if ($correctionLine > 0 && $requireLine > 0 && $correctionLine < $requireLine) {
                    $results[] = [
                        'file' => $file,
                        'status' => 'success',
                        'message' => 'Correção aplicada corretamente (linha ' . ($correctionLine + 1) . ')'
                    ];
                } else {
                    $results[] = [
                        'file' => $file,
                        'status' => 'warning',
                        'message' => 'Correção encontrada mas pode estar no lugar errado'
                    ];
                    $allOk = false;
                }
            } else {
                $results[] = [
                    'file' => $file,
                    'status' => 'error',
                    'message' => 'Correção NÃO aplicada'
                ];
                $allOk = false;
            }
        }
        
        // Exibir resultados
        foreach ($results as $result) {
            $icon = $result['status'] === 'success' ? '✅' : ($result['status'] === 'error' ? '❌' : '⚠️');
            echo "<div class='check-item {$result['status']}'>";
            echo "<span class='icon'>$icon</span>";
            echo "<strong>{$result['file']}</strong><br>";
            echo "<small>{$result['message']}</small>";
            echo "</div>";
        }
        
        // Verificar timezone atual do PHP
        echo "<h2 style='margin-top: 30px;'>Timezone Atual do PHP</h2>";
        $currentTz = date_default_timezone_get();
        $isCorrect = ($currentTz === 'America/Sao_Paulo');
        
        echo "<div class='check-item " . ($isCorrect ? 'success' : 'error') . "'>";
        echo "<span class='icon'>" . ($isCorrect ? '✅' : '❌') . "</span>";
        echo "<strong>Timezone PHP:</strong> $currentTz<br>";
        echo "<small>Esperado: America/Sao_Paulo</small>";
        echo "</div>";
        
        // Verificar horário atual
        echo "<h2 style='margin-top: 30px;'>Horário Atual</h2>";
        echo "<pre>";
        echo "Data/Hora PHP: " . date('Y-m-d H:i:s') . "\n";
        echo "Timezone: " . date('e') . "\n";
        echo "Offset GMT: " . date('P') . "\n";
        echo "Unix Timestamp: " . time() . "\n";
        echo "</pre>";
        
        // Verificar variável de ambiente
        echo "<h2 style='margin-top: 30px;'>Variável de Ambiente</h2>";
        $envTz = getenv('TZ');
        echo "<div class='check-item " . ($envTz ? 'success' : 'warning') . "'>";
        echo "<span class='icon'>" . ($envTz ? '✅' : '⚠️') . "</span>";
        echo "<strong>TZ (ambiente):</strong> " . ($envTz ?: 'Não definida') . "<br>";
        echo "<small>Recomendado: America/Sao_Paulo</small>";
        echo "</div>";
        
        // Resumo final
        echo "<div class='summary'>";
        if ($allOk && $isCorrect) {
            echo "<h2 style='color: #28a745; margin-top: 0;'>✅ Tudo OK!</h2>";
            echo "<p>A correção de timezone foi aplicada corretamente em todos os arquivos.</p>";
            echo "<p><strong>Próximo passo:</strong> Teste enviando uma mensagem e verifique se o horário está correto.</p>";
        } else {
            echo "<h2 style='color: #dc3545; margin-top: 0;'>❌ Ação Necessária</h2>";
            echo "<p>A correção de timezone NÃO foi aplicada completamente.</p>";
            echo "<p><strong>O que fazer:</strong></p>";
            echo "<ol>";
            echo "<li>Faça o deploy das alterações para o servidor</li>";
            echo "<li>Configure a variável de ambiente <code>TZ=America/Sao_Paulo</code> no Easypanel</li>";
            echo "<li>Faça redeploy do container</li>";
            echo "<li>Recarregue esta página para verificar novamente</li>";
            echo "</ol>";
            echo "<p>Consulte o arquivo <code>INSTRUCOES_APLICAR_CORRECAO_TIMEZONE.md</code> para mais detalhes.</p>";
        }
        echo "</div>";
        ?>
        
        <div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid #ecf0f1; text-align: center; color: #7f8c8d;">
            <p><strong>WATS - Sistema Multi-Canal</strong></p>
            <p>MACIP Tecnologia LTDA © <?php echo date('Y'); ?></p>
            <p style="font-size: 12px; margin-top: 10px;">
                <a href="/" style="color: #3498db; text-decoration: none;">← Voltar para o sistema</a> | 
                <a href="javascript:location.reload()" style="color: #3498db; text-decoration: none;">🔄 Recarregar verificação</a> |
                <a href="test_timezone.php" style="color: #3498db; text-decoration: none;">🕐 Teste completo de timezone</a>
            </p>
        </div>
    </div>
</body>
</html>
