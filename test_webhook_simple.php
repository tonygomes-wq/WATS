<?php
/**
 * TESTE SIMPLES DE WEBHOOK
 * Verifica se o webhook está configurado corretamente
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    die('Não autenticado. <a href="/login.php">Fazer login</a>');
}

$userId = $_SESSION['user_id'];

// Buscar configuração do usuário
$stmt = $pdo->prepare("
    SELECT 
        evolution_instance, 
        evolution_token, 
        evolution_api_url
    FROM users 
    WHERE id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$instance = $user['evolution_instance'] ?? '';
$token = $user['evolution_token'] ?? '';
$apiUrl = !empty($user['evolution_api_url']) ? $user['evolution_api_url'] : EVOLUTION_API_URL;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Webhook</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 30px;
        }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #229954;
        }
        .btn-secondary {
            background: #3498db;
        }
        .btn-secondary:hover {
            background: #2980b9;
        }
        .result {
            margin: 20px 0;
            padding: 20px;
            border-radius: 8px;
            background: #f8f9fa;
        }
        .success {
            background: #d4edda;
            border-left: 5px solid #28a745;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border-left: 5px solid #dc3545;
            color: #721c24;
        }
        pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 12px;
            margin: 10px 0;
        }
        .info-box {
            background: #d1ecf1;
            border-left: 5px solid #17a2b8;
            color: #0c5460;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Teste de Webhook</h1>
        
        <div class="info-box">
            <strong>📋 Configuração Atual:</strong><br><br>
            <strong>Instância:</strong> <?php echo htmlspecialchars($instance); ?><br>
            <strong>API URL:</strong> <?php echo htmlspecialchars($apiUrl); ?><br>
            <strong>Webhook URL:</strong> https://wats.macip.com.br/api/chat_webhook.php
        </div>
        
        <div id="result"></div>
        
        <button class="btn" onclick="checkWebhook()">
            🔍 Verificar Webhook
        </button>
        
        <a href="/configure_webhook_now.php" class="btn btn-secondary">
            ⚙️ Configurar Webhook
        </a>
        
        <a href="/chat.php" class="btn btn-secondary">
            💬 Ir para o Chat
        </a>
    </div>
    
    <script>
        async function checkWebhook() {
            const resultDiv = document.getElementById('result');
            resultDiv.innerHTML = '<div class="result">⏳ Verificando webhook...</div>';
            
            try {
                const response = await fetch('<?php echo rtrim($apiUrl, '/'); ?>/webhook/find/<?php echo $instance; ?>', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'apikey': '<?php echo $token; ?>'
                    }
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    let html = '<div class="result success">';
                    html += '<strong>✅ Webhook Encontrado!</strong><br><br>';
                    html += '<strong>Status:</strong> ' + (data.enabled ? '✅ Ativo' : '❌ Desativado') + '<br>';
                    html += '<strong>URL:</strong> ' + (data.url || 'Não configurado') + '<br>';
                    html += '<strong>Eventos:</strong> ' + (data.events ? data.events.length : 0) + '<br>';
                    html += '</div>';
                    html += '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                    resultDiv.innerHTML = html;
                } else {
                    resultDiv.innerHTML = '<div class="result error"><strong>❌ Erro:</strong> ' + 
                        (data.message || 'Webhook não configurado') + '</div>';
                }
            } catch (error) {
                resultDiv.innerHTML = '<div class="result error"><strong>❌ Erro de Conexão:</strong> ' + 
                    error.message + '</div>';
            }
        }
        
        // Verificar automaticamente ao carregar
        window.addEventListener('load', checkWebhook);
    </script>
</body>
</html>
