<?php
/**
 * CONFIGURAR WEBHOOK - SOLUÇÃO RÁPIDA
 * Configura o webhook da Evolution API automaticamente
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

$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['configure'])) {
    // Configurar webhook
    $webhookUrl = 'https://wats.macip.com.br/api/chat_webhook.php';
    
    // Evolution API v2 requer o payload dentro de "webhook"
    $webhookData = [
        'webhook' => [
            'url' => $webhookUrl,
            'webhook_by_events' => false,
            'webhook_base64' => false,
            'events' => [
                'QRCODE_UPDATED',
                'MESSAGES_UPSERT',
                'MESSAGES_UPDATE',
                'MESSAGES_DELETE',
                'SEND_MESSAGE',
                'CONNECTION_UPDATE'
            ]
        ]
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => rtrim($apiUrl, '/') . '/webhook/set/' . $instance,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($webhookData),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'apikey: ' . $token
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        $error = "Erro de conexão: $curlError";
    } elseif ($httpCode >= 200 && $httpCode < 300) {
        $result = json_decode($response, true);
    } else {
        $error = "Erro HTTP $httpCode: $response";
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Webhook</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 28px;
        }
        .icon {
            font-size: 64px;
            margin-bottom: 20px;
            text-align: center;
        }
        .alert {
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            border-left: 5px solid;
        }
        .alert-success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        .alert-error {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        .alert-warning {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        .alert-info {
            background: #d1ecf1;
            border-color: #17a2b8;
            color: #0c5460;
        }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            width: 100%;
            margin: 10px 0;
        }
        .btn:hover {
            background: #229954;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }
        .btn-secondary {
            background: #3498db;
        }
        .btn-secondary:hover {
            background: #2980b9;
        }
        .info-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .info-box strong {
            display: block;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        code {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
        }
        ul {
            margin: 15px 0;
            padding-left: 20px;
        }
        ul li {
            margin: 8px 0;
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
    </style>
</head>
<body>
    <div class="container">
        <?php if ($result): ?>
            <div class="icon">🎉</div>
            <h1>Webhook Configurado!</h1>
            
            <div class="alert alert-success">
                <strong>✅ Sucesso!</strong><br><br>
                O webhook foi configurado com sucesso na Evolution API!
            </div>
            
            <div class="info-box">
                <strong>🎯 O que vai funcionar agora:</strong>
                <ul>
                    <li>✅ Receber mensagens do WhatsApp</li>
                    <li>✅ Atualizar status (lido, entregue)</li>
                    <li>✅ Checkmarks azuis quando lerem suas mensagens</li>
                    <li>✅ Fotos de perfil dos contatos</li>
                </ul>
            </div>
            
            <div class="info-box">
                <strong>📋 Configuração:</strong><br>
                <code>URL: https://wats.macip.com.br/api/chat_webhook.php</code><br>
                <code>Instância: <?php echo htmlspecialchars($instance); ?></code>
            </div>
            
            <?php if (isset($result['webhook'])): ?>
            <details style="margin: 20px 0;">
                <summary style="cursor: pointer; padding: 10px; background: #ecf0f1; border-radius: 4px;">
                    Ver Resposta Completa
                </summary>
                <pre><?php echo htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT)); ?></pre>
            </details>
            <?php endif; ?>
            
            <a href="/chat.php" class="btn">
                💬 Ir para o Chat
            </a>
            
            <a href="/test_webhook_messages.php" class="btn btn-secondary">
                🔍 Testar Webhook
            </a>
            
        <?php elseif ($error): ?>
            <div class="icon">❌</div>
            <h1>Erro ao Configurar</h1>
            
            <div class="alert alert-error">
                <strong>❌ Erro:</strong><br><br>
                <?php echo htmlspecialchars($error); ?>
            </div>
            
            <div class="alert alert-warning">
                <strong>🔧 Possíveis Causas:</strong>
                <ul>
                    <li>Evolution API não está acessível</li>
                    <li>Token/API Key incorreto</li>
                    <li>Instância não existe</li>
                    <li>Timeout de conexão</li>
                </ul>
            </div>
            
            <form method="POST">
                <button type="submit" name="configure" class="btn">
                    🔄 Tentar Novamente
                </button>
            </form>
            
            <a href="/test_webhook_messages.php" class="btn btn-secondary">
                🔍 Diagnóstico Completo
            </a>
            
        <?php else: ?>
            <div class="icon">🔗</div>
            <h1>Configurar Webhook</h1>
            
            <div class="alert alert-warning">
                <strong>⚠️ Problemas Detectados:</strong>
                <ul>
                    <li>❌ Não está recebendo mensagens</li>
                    <li>❌ Status não atualiza (sem checkmarks azuis)</li>
                    <li>❌ Fotos de perfil não carregam</li>
                </ul>
            </div>
            
            <div class="alert alert-info">
                <strong>✅ Solução:</strong><br><br>
                Configure o webhook para que a Evolution API envie eventos para o WATS.
            </div>
            
            <div class="info-box">
                <strong>📋 Configuração Atual:</strong><br><br>
                <strong>Instância:</strong> <code><?php echo htmlspecialchars($instance ?: 'Não configurada'); ?></code><br>
                <strong>API URL:</strong> <code><?php echo htmlspecialchars($apiUrl); ?></code><br>
                <strong>Webhook URL:</strong> <code>https://wats.macip.com.br/api/chat_webhook.php</code>
            </div>
            
            <div class="info-box">
                <strong>🎯 O que o webhook faz:</strong>
                <ul>
                    <li>📥 Recebe mensagens novas do WhatsApp</li>
                    <li>✅ Atualiza status (enviado, entregue, lido)</li>
                    <li>🖼️ Baixa fotos de perfil dos contatos</li>
                    <li>🔄 Sincroniza em tempo real</li>
                </ul>
            </div>
            
            <form method="POST">
                <button type="submit" name="configure" class="btn">
                    ⚙️ Configurar Webhook Agora
                </button>
            </form>
            
            <a href="/test_webhook_messages.php" class="btn btn-secondary">
                🔍 Diagnóstico Completo
            </a>
        <?php endif; ?>
    </div>
</body>
</html>
