<?php
/**
 * Atualizar para URL Interna da Evolution API
 * Agora que a Evolution API está no mesmo projeto, use comunicação interna
 */

session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    die('Não autenticado. <a href="/login.php">Fazer login</a>');
}

$userId = $_SESSION['user_id'];

// URLs internas possíveis (baseado nos nomes dos serviços no Easypanel)
$possibleInternalUrls = [
    'http://evolution-api:8080',
    'http://evolution-api-app:8080',
    'http://evolution:8080',
    'http://evogo:8080',
];

$workingUrl = null;
$testResults = [];

// Testar cada URL
foreach ($possibleInternalUrls as $url) {
    $testUrl = $url . '/';
    $startTime = microtime(true);
    
    $ch = curl_init($testUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 3,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $totalTime = round((microtime(true) - $startTime) * 1000);
    curl_close($ch);
    
    $success = ($httpCode === 200 || $httpCode === 404) && !$curlError;
    
    $testResults[] = [
        'url' => $url,
        'success' => $success,
        'http_code' => $httpCode,
        'time_ms' => $totalTime,
        'error' => $curlError
    ];
    
    if ($success && !$workingUrl) {
        $workingUrl = $url;
    }
}

// Se encontrou URL funcionando, atualizar automaticamente
$autoUpdated = false;
if ($workingUrl && $_GET['auto'] === 'true') {
    $stmt = $pdo->prepare("UPDATE users SET evolution_api_url = ? WHERE id = ?");
    $stmt->execute([$workingUrl, $userId]);
    $autoUpdated = true;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar URL Interna - Evolution API</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
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
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
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
        }
        .error {
            background: #f8d7da;
            border-color: #dc3545;
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
        .btn-success {
            background: #27ae60;
        }
        .btn-success:hover {
            background: #229954;
        }
        .btn-large {
            font-size: 18px;
            padding: 15px 30px;
        }
        code {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
        }
        .metric {
            display: inline-block;
            margin: 5px 10px 5px 0;
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
            <h1>🔧 Configurar URL Interna - Evolution API</h1>
            <p>Agora que a Evolution API está no mesmo projeto, vamos usar comunicação interna (muito mais rápido!).</p>
        </div>

        <?php if ($autoUpdated): ?>
        <div class="card">
            <div class="alert alert-success">
                <strong>✅ URL Atualizada com Sucesso!</strong><br><br>
                Nova URL: <code><?php echo htmlspecialchars($workingUrl); ?></code><br><br>
                Agora você pode criar instâncias normalmente!
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="/my_instance.php" class="btn btn-success btn-large">🚀 Criar Instância Agora</a>
                <a href="/test_webhook_messages.php" class="btn">🔍 Testar Conexão</a>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <h2>🔍 Teste de Conectividade</h2>
            <p>Testando URLs internas possíveis...</p>
            
            <?php foreach ($testResults as $result): ?>
                <div class="test-result <?php echo $result['success'] ? 'success' : 'error'; ?>">
                    <strong><?php echo $result['success'] ? '✅ FUNCIONA' : '❌ NÃO FUNCIONA'; ?></strong>
                    <br>
                    <div class="metric">URL: <?php echo htmlspecialchars($result['url']); ?></div>
                    <div class="metric">HTTP: <?php echo $result['http_code'] ?: 'N/A'; ?></div>
                    <div class="metric">Tempo: <?php echo $result['time_ms']; ?>ms</div>
                    <?php if ($result['error']): ?>
                        <br><small style="color: #721c24;">Erro: <?php echo htmlspecialchars($result['error']); ?></small>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($workingUrl && !$autoUpdated): ?>
        <div class="card">
            <div class="alert alert-success">
                <strong>🎉 URL Interna Encontrada!</strong><br><br>
                URL que funciona: <code><?php echo htmlspecialchars($workingUrl); ?></code>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="?auto=true" class="btn btn-success btn-large">
                    ✅ Atualizar Automaticamente
                </a>
            </div>
            
            <div class="alert alert-info" style="margin-top: 20px;">
                <strong>ℹ️ O que vai acontecer:</strong><br>
                1. Sua URL será atualizada para: <code><?php echo htmlspecialchars($workingUrl); ?></code><br>
                2. O sistema usará comunicação interna (super rápido)<br>
                3. Você poderá criar instâncias sem timeout<br>
                4. Mensagens serão enviadas e recebidas normalmente
            </div>
        </div>
        <?php elseif (!$workingUrl): ?>
        <div class="card">
            <div class="alert alert-error">
                <strong>❌ Nenhuma URL Interna Funcionou</strong><br><br>
                Possíveis causas:
                <ul>
                    <li>Evolution API não está rodando</li>
                    <li>Nome do serviço está diferente</li>
                    <li>Porta incorreta</li>
                </ul>
            </div>
            
            <div class="alert alert-warning">
                <strong>🔧 Como Resolver:</strong><br><br>
                
                <strong>1. Verifique o nome do serviço no Easypanel:</strong><br>
                - Vá no projeto WATS<br>
                - Veja o nome exato do serviço Evolution API<br>
                - Deve ser algo como: <code>evolution-api</code>, <code>evolution</code>, ou <code>evogo</code><br><br>
                
                <strong>2. Verifique se está rodando:</strong><br>
                - No Easypanel, veja se o serviço está com bolinha verde<br>
                - Veja os logs para verificar se iniciou corretamente<br><br>
                
                <strong>3. Verifique a porta:</strong><br>
                - A Evolution API deve estar na porta <code>8080</code><br>
                - Verifique nas configurações do serviço
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="?" class="btn">🔄 Testar Novamente</a>
                <a href="/test_evolution_connection.php" class="btn">🔍 Teste Completo</a>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <h2>📋 Informações Úteis</h2>
            
            <div class="alert alert-info">
                <strong>🌐 Estrutura do Projeto WATS:</strong><br><br>
                <pre style="background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 8px;">
Projeto: wats
├── evolution-api (porta 8080)
├── evolution-api-db (PostgreSQL)
├── evolution-api-redis (Redis)
├── mysql (porta 3306)
├── redis (porta 6379)
└── wats (porta 80)</pre>
            </div>
            
            <div class="alert alert-info">
                <strong>🔗 URLs de Comunicação:</strong><br><br>
                <strong>Interna (rápida):</strong><br>
                - WATS → Evolution API: <code>http://evolution-api:8080</code><br>
                - WATS → MySQL: <code>mysql:3306</code><br>
                - WATS → Redis: <code>redis:6379</code><br><br>
                
                <strong>Externa (pública):</strong><br>
                - WhatsApp → Evolution API: <code>https://evolution.macip.com.br</code><br>
                - Usuários → WATS: <code>https://wats.macip.com.br</code>
            </div>
        </div>
    </div>
</body>
</html>
