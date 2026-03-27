<?php
/**
 * Script de Teste de Timezone
 * 
 * Verifica se o timezone está configurado corretamente
 * Acesse: /test_timezone.php
 */

require_once 'config/database.php';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Timezone - WATS</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
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
        h2 {
            color: #34495e;
            margin-top: 30px;
            border-left: 4px solid #3498db;
            padding-left: 15px;
        }
        pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 20px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 14px;
            line-height: 1.6;
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
        .info {
            background: #e8f4f8;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        table th {
            background: #3498db;
            color: white;
            font-weight: 600;
        }
        table tr:hover {
            background: #f8f9fa;
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
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>⏰ Teste de Timezone - WATS</h1>
        
        <div class="info">
            <strong>ℹ️ Informação:</strong> Este script verifica se o timezone está configurado corretamente no sistema.
            O timezone esperado é <strong>America/Sao_Paulo (BRT/BRST)</strong>.
        </div>

        <h2>1. Configuração do Sistema</h2>
        <pre><?php
echo "Variável TZ (ambiente): " . (getenv('TZ') ?: '<span class="warning">não definida</span>') . "\n";
echo "Timezone PHP atual:     " . date_default_timezone_get() . "\n";
echo "Versão PHP:             " . PHP_VERSION . "\n";
echo "Sistema Operacional:    " . PHP_OS . "\n";

// Verificar se está correto
$currentTz = date_default_timezone_get();
$isCorrect = ($currentTz === 'America/Sao_Paulo');
echo "\nStatus: ";
if ($isCorrect) {
    echo '<span class="success">✅ Timezone configurado corretamente!</span>';
} else {
    echo '<span class="error">❌ Timezone incorreto! Esperado: America/Sao_Paulo, Atual: ' . $currentTz . '</span>';
}
?></pre>

        <h2>2. Data e Hora Atual</h2>
        <table>
            <thead>
                <tr>
                    <th>Formato</th>
                    <th>Valor</th>
                    <th>Descrição</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>Y-m-d H:i:s</code></td>
                    <td><strong><?php echo date('Y-m-d H:i:s'); ?></strong></td>
                    <td>Formato padrão banco de dados</td>
                </tr>
                <tr>
                    <td><code>c</code></td>
                    <td><?php echo date('c'); ?></td>
                    <td>ISO 8601</td>
                </tr>
                <tr>
                    <td><code>r</code></td>
                    <td><?php echo date('r'); ?></td>
                    <td>RFC 2822</td>
                </tr>
                <tr>
                    <td><code>time()</code></td>
                    <td><?php echo time(); ?></td>
                    <td>Unix timestamp</td>
                </tr>
                <tr>
                    <td><code>e</code></td>
                    <td><?php echo date('e'); ?></td>
                    <td>Identificador do timezone</td>
                </tr>
                <tr>
                    <td><code>T</code></td>
                    <td><?php echo date('T'); ?></td>
                    <td>Abreviação do timezone</td>
                </tr>
                <tr>
                    <td><code>P</code></td>
                    <td><?php echo date('P'); ?></td>
                    <td>Diferença do GMT</td>
                </tr>
            </tbody>
        </table>

        <h2>3. Teste com Banco de Dados</h2>
        <pre><?php
try {
    // Criar tabela temporária se não existir
    $pdo->exec("CREATE TABLE IF NOT EXISTS test_timezone (
        id INT AUTO_INCREMENT PRIMARY KEY,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_at_php VARCHAR(50)
    )");
    
    // Inserir registro de teste
    $phpTime = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("INSERT INTO test_timezone (created_at_php) VALUES (?)");
    $stmt->execute([$phpTime]);
    $id = $pdo->lastInsertId();
    
    // Buscar registro
    $stmt = $pdo->prepare("SELECT created_at, created_at_php FROM test_timezone WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    
    echo "Horário PHP:   " . $phpTime . "\n";
    echo "Horário MySQL: " . $row['created_at'] . "\n";
    echo "Diferença:     ";
    
    $diff = strtotime($row['created_at']) - strtotime($phpTime);
    if (abs($diff) <= 2) {
        echo '<span class="success">✅ Sincronizado (diferença: ' . $diff . 's)</span>';
    } else {
        echo '<span class="warning">⚠️ Dessincronizado (diferença: ' . $diff . 's)</span>';
    }
    
    // Limpar teste
    $stmt = $pdo->prepare("DELETE FROM test_timezone WHERE id = ?");
    $stmt->execute([$id]);
    
    echo "\n\n<span class=\"success\">✅ Teste com banco de dados concluído com sucesso!</span>";
    
} catch (Exception $e) {
    echo '<span class="error">❌ Erro: ' . htmlspecialchars($e->getMessage()) . '</span>';
}
?></pre>

        <h2>4. Comparação de Fusos Horários</h2>
        <table>
            <thead>
                <tr>
                    <th>Timezone</th>
                    <th>Data/Hora</th>
                    <th>Offset GMT</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $timezones = [
                    'UTC' => 'Tempo Universal Coordenado',
                    'America/Sao_Paulo' => 'São Paulo, Brasil',
                    'America/New_York' => 'Nova York, EUA',
                    'Europe/London' => 'Londres, Reino Unido',
                    'Asia/Tokyo' => 'Tóquio, Japão',
                    'Australia/Sydney' => 'Sydney, Austrália'
                ];
                
                $currentTz = date_default_timezone_get();
                
                foreach ($timezones as $tz => $description) {
                    try {
                        $dt = new DateTime('now', new DateTimeZone($tz));
                        $isCurrent = ($tz === $currentTz);
                        
                        echo "<tr" . ($isCurrent ? " style='background: #e8f4f8; font-weight: bold;'" : "") . ">";
                        echo "<td>" . htmlspecialchars($tz) . "<br><small style='color: #7f8c8d;'>" . htmlspecialchars($description) . "</small></td>";
                        echo "<td>" . $dt->format('Y-m-d H:i:s') . "</td>";
                        echo "<td>" . $dt->format('P') . "</td>";
                        echo "<td>";
                        if ($isCurrent) {
                            echo '<span class="badge badge-success">✓ Atual</span>';
                        }
                        echo "</td>";
                        echo "</tr>";
                    } catch (Exception $e) {
                        echo "<tr><td colspan='4'><span class='error'>Erro ao processar $tz</span></td></tr>";
                    }
                }
                ?>
            </tbody>
        </table>

        <h2>5. Informações do Servidor</h2>
        <pre><?php
echo "Servidor Web:      " . $_SERVER['SERVER_SOFTWARE'] . "\n";
echo "Document Root:     " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script Filename:   " . $_SERVER['SCRIPT_FILENAME'] . "\n";
echo "Server Time:       " . date('Y-m-d H:i:s T') . "\n";

// Verificar se está em container Docker
$isDocker = file_exists('/.dockerenv');
echo "Ambiente Docker:   " . ($isDocker ? '<span class="success">✅ Sim</span>' : '<span class="warning">❌ Não</span>') . "\n";

// Verificar timezone do sistema (Linux)
if (file_exists('/etc/timezone')) {
    $systemTz = trim(file_get_contents('/etc/timezone'));
    echo "System Timezone:   " . $systemTz . "\n";
}
?></pre>

        <h2>6. Recomendações</h2>
        <div class="info">
            <?php
            $currentTz = date_default_timezone_get();
            if ($currentTz === 'America/Sao_Paulo') {
                echo '<span class="success">✅ Tudo configurado corretamente!</span><br><br>';
                echo 'O timezone está configurado para <strong>America/Sao_Paulo</strong>. ';
                echo 'Todas as datas e horários do sistema estarão no horário de Brasília.';
            } else {
                echo '<span class="error">❌ Ação necessária!</span><br><br>';
                echo '<strong>O timezone não está configurado corretamente.</strong><br><br>';
                echo 'Para corrigir:<br>';
                echo '1. No Easypanel, adicione a variável de ambiente: <code>TZ=America/Sao_Paulo</code><br>';
                echo '2. Faça redeploy do container<br>';
                echo '3. Recarregue esta página para verificar<br><br>';
                echo 'Consulte o arquivo <code>CONFIGURACAO_TIMEZONE_EASYPANEL.md</code> para mais detalhes.';
            }
            ?>
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
