<?php
/**
 * Teste Completo de Webhook - Envio e Recebimento de Mensagens
 * Diagnóstico de problemas com recebimento de mensagens
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Verificar autenticação
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Buscar configuração do usuário
$stmt = $pdo->prepare("
    SELECT 
        evolution_instance, evolution_token, whatsapp_provider,
        evolution_go_instance, evolution_go_token
    FROM users 
    WHERE id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$provider = $user['whatsapp_provider'] ?? 'evolution';
$instance = ($provider === 'evolution-go') ? $user['evolution_go_instance'] : $user['evolution_instance'];
$token = ($provider === 'evolution-go') ? $user['evolution_go_token'] : $user['evolution_token'];
$apiUrl = ($provider === 'evolution-go') ? EVOLUTION_GO_API_URL : EVOLUTION_API_URL;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Webhook - Envio e Recebimento</title>
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
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #7f8c8d;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .card h2 {
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #7f8c8d;
        }
        
        .info-value {
            color: #2c3e50;
            font-family: 'Courier New', monospace;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-success {
            background: #d4edda;
            color: #155724;
        }
        
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            margin: 5px;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .test-section {
            margin: 20px 0;
        }
        
        .input-group {
            margin: 15px 0;
        }
        
        .input-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .input-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .input-group input:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .result-box {
            margin: 15px 0;
            padding: 15px;
            border-radius: 8px;
            display: none;
        }
        
        .result-box.show {
            display: block;
        }
        
        .result-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .result-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
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
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        
        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        
        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        
        .messages-list {
            max-height: 400px;
            overflow-y: auto;
            margin: 15px 0;
        }
        
        .message-item {
            padding: 10px;
            margin: 5px 0;
            border-radius: 8px;
            background: #f8f9fa;
            border-left: 4px solid #3498db;
        }
        
        .message-item.received {
            border-left-color: #27ae60;
        }
        
        .message-item.sent {
            border-left-color: #3498db;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Teste de Webhook - Envio e Recebimento de Mensagens</h1>
            <p>Diagnóstico completo do sistema de mensagens</p>
        </div>
        
        <div class="grid">
            <!-- Configuração Atual -->
            <div class="card">
                <h2>⚙️ Configuração Atual</h2>
                <div class="info-item">
                    <span class="info-label">Provider:</span>
                    <span class="info-value"><?php echo htmlspecialchars($provider); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Instância:</span>
                    <span class="info-value"><?php echo htmlspecialchars($instance ?: 'Não configurada'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">API URL:</span>
                    <span class="info-value"><?php echo htmlspecialchars($apiUrl); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Token:</span>
                    <span class="info-value"><?php echo $token ? substr($token, 0, 10) . '...' : 'Não configurado'; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Webhook URL:</span>
                    <span class="info-value"><?php echo 'https://wats.macip.com.br/api/chat_webhook.php'; ?></span>
                </div>
            </div>
            
            <!-- Status da Conexão -->
            <div class="card">
                <h2>📡 Status da Conexão</h2>
                <div id="connectionStatus">
                    <p style="text-align: center; color: #7f8c8d;">Carregando...</p>
                </div>
                <button onclick="checkConnection()" class="btn btn-primary" style="width: 100%; margin-top: 15px;">
                    🔄 Verificar Conexão
                </button>
            </div>
        </div>
        
        <div class="grid">
            <!-- Teste de Envio -->
            <div class="card">
                <h2>📤 Teste de Envio de Mensagem</h2>
                <div class="alert alert-info">
                    <strong>ℹ️ Como testar:</strong><br>
                    1. Digite um número de WhatsApp<br>
                    2. Digite uma mensagem de teste<br>
                    3. Clique em "Enviar Mensagem"<br>
                    4. Verifique se a mensagem chegou no WhatsApp
                </div>
                
                <div class="input-group">
                    <label>Número do WhatsApp:</label>
                    <input type="text" id="sendPhone" placeholder="5511999999999" value="5547992814164">
                </div>
                
                <div class="input-group">
                    <label>Mensagem:</label>
                    <input type="text" id="sendMessage" placeholder="Teste de envio" value="🧪 Teste de envio - <?php echo date('H:i:s'); ?>">
                </div>
                
                <button onclick="sendTestMessage()" class="btn btn-success" style="width: 100%;">
                    📤 Enviar Mensagem de Teste
                </button>
                
                <div id="sendResult" class="result-box"></div>
            </div>
            
            <!-- Verificação de Webhook -->
            <div class="card">
                <h2>🔗 Verificação de Webhook</h2>
                <div class="alert alert-warning">
                    <strong>⚠️ Importante:</strong><br>
                    O webhook precisa estar configurado na Evolution API para receber mensagens.
                </div>
                
                <button onclick="checkWebhook()" class="btn btn-primary" style="width: 100%;">
                    🔍 Verificar Configuração do Webhook
                </button>
                
                <div id="webhookResult" class="result-box"></div>
                
                <button onclick="setWebhook()" class="btn btn-success" style="width: 100%; margin-top: 10px;">
                    ⚙️ Configurar Webhook Automaticamente
                </button>
            </div>
        </div>
        
        <!-- Mensagens Recentes -->
        <div class="card">
            <h2>📨 Últimas Mensagens Recebidas</h2>
            <div class="alert alert-info">
                <strong>ℹ️ Como testar recebimento:</strong><br>
                1. Envie uma mensagem do seu WhatsApp para o número conectado<br>
                2. Clique em "Atualizar" para ver se a mensagem foi recebida<br>
                3. Se não aparecer, há problema no webhook
            </div>
            
            <button onclick="loadRecentMessages()" class="btn btn-primary">
                🔄 Atualizar Mensagens
            </button>
            
            <div id="messagesList" class="messages-list"></div>
        </div>
        
        <!-- Logs do Webhook -->
        <div class="card">
            <h2>📋 Logs do Webhook (Últimos 10)</h2>
            <button onclick="loadWebhookLogs()" class="btn btn-primary">
                🔄 Carregar Logs
            </button>
            
            <div id="webhookLogs"></div>
        </div>
    </div>
    
    <script>
        const apiUrl = '<?php echo $apiUrl; ?>';
        const instance = '<?php echo $instance; ?>';
        const token = '<?php echo $token; ?>';
        
        // Verificar conexão
        async function checkConnection() {
            const statusDiv = document.getElementById('connectionStatus');
            statusDiv.innerHTML = '<p style="text-align: center;"><span class="loading"></span> Verificando...</p>';
            
            try {
                const response = await fetch(`${apiUrl}/instance/connectionState/${instance}`, {
                    headers: {
                        'apikey': token
                    }
                });
                
                const data = await response.json();
                console.log('Connection status:', data);
                
                const state = data.state || data.instance?.state || 'unknown';
                const isConnected = state === 'open' || state === 'connected';
                
                statusDiv.innerHTML = `
                    <div class="info-item">
                        <span class="info-label">Status:</span>
                        <span class="status-badge ${isConnected ? 'status-success' : 'status-error'}">
                            ${isConnected ? '✅ Conectado' : '❌ Desconectado'}
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Estado:</span>
                        <span class="info-value">${state}</span>
                    </div>
                    ${data.instance?.owner ? `
                    <div class="info-item">
                        <span class="info-label">Número:</span>
                        <span class="info-value">${data.instance.owner}</span>
                    </div>
                    ` : ''}
                `;
            } catch (error) {
                console.error('Error checking connection:', error);
                statusDiv.innerHTML = `
                    <div class="status-badge status-error">❌ Erro ao verificar conexão</div>
                    <pre>${error.message}</pre>
                `;
            }
        }
        
        // Enviar mensagem de teste
        async function sendTestMessage() {
            const phone = document.getElementById('sendPhone').value;
            const message = document.getElementById('sendMessage').value;
            const resultDiv = document.getElementById('sendResult');
            
            if (!phone || !message) {
                resultDiv.className = 'result-box result-error show';
                resultDiv.innerHTML = '❌ Preencha o número e a mensagem';
                return;
            }
            
            resultDiv.className = 'result-box show';
            resultDiv.innerHTML = '<span class="loading"></span> Enviando mensagem...';
            
            try {
                const response = await fetch(`${apiUrl}/message/sendText/${instance}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'apikey': token
                    },
                    body: JSON.stringify({
                        number: phone,
                        text: message
                    })
                });
                
                const data = await response.json();
                console.log('Send response:', data);
                
                if (response.ok) {
                    resultDiv.className = 'result-box result-success show';
                    resultDiv.innerHTML = `
                        ✅ Mensagem enviada com sucesso!
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    `;
                } else {
                    throw new Error(data.message || 'Erro ao enviar mensagem');
                }
            } catch (error) {
                console.error('Error sending message:', error);
                resultDiv.className = 'result-box result-error show';
                resultDiv.innerHTML = `
                    ❌ Erro ao enviar mensagem
                    <pre>${error.message}</pre>
                `;
            }
        }
        
        // Verificar webhook
        async function checkWebhook() {
            const resultDiv = document.getElementById('webhookResult');
            resultDiv.className = 'result-box show';
            resultDiv.innerHTML = '<span class="loading"></span> Verificando webhook...';
            
            try {
                const response = await fetch(`${apiUrl}/webhook/find/${instance}`, {
                    headers: {
                        'apikey': token
                    }
                });
                
                const data = await response.json();
                console.log('Webhook config:', data);
                
                if (response.ok && data.webhook) {
                    resultDiv.className = 'result-box result-success show';
                    resultDiv.innerHTML = `
                        ✅ Webhook configurado!
                        <pre>${JSON.stringify(data.webhook, null, 2)}</pre>
                    `;
                } else {
                    resultDiv.className = 'result-box result-error show';
                    resultDiv.innerHTML = `
                        ❌ Webhook não configurado
                        <p>Configure o webhook para receber mensagens.</p>
                    `;
                }
            } catch (error) {
                console.error('Error checking webhook:', error);
                resultDiv.className = 'result-box result-error show';
                resultDiv.innerHTML = `
                    ❌ Erro ao verificar webhook
                    <pre>${error.message}</pre>
                `;
            }
        }
        
        // Configurar webhook
        async function setWebhook() {
            const resultDiv = document.getElementById('webhookResult');
            resultDiv.className = 'result-box show';
            resultDiv.innerHTML = '<span class="loading"></span> Configurando webhook...';
            
            try {
                const response = await fetch(`${apiUrl}/webhook/set/${instance}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'apikey': token
                    },
                    body: JSON.stringify({
                        url: 'https://wats.macip.com.br/api/chat_webhook.php',
                        webhook_by_events: false,
                        webhook_base64: false,
                        events: [
                            'QRCODE_UPDATED',
                            'MESSAGES_UPSERT',
                            'MESSAGES_UPDATE',
                            'MESSAGES_DELETE',
                            'SEND_MESSAGE',
                            'CONNECTION_UPDATE'
                        ]
                    })
                });
                
                const data = await response.json();
                console.log('Set webhook response:', data);
                
                if (response.ok) {
                    resultDiv.className = 'result-box result-success show';
                    resultDiv.innerHTML = `
                        ✅ Webhook configurado com sucesso!
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                        <p style="margin-top: 10px;">Agora envie uma mensagem do WhatsApp para testar o recebimento.</p>
                    `;
                } else {
                    throw new Error(data.message || 'Erro ao configurar webhook');
                }
            } catch (error) {
                console.error('Error setting webhook:', error);
                resultDiv.className = 'result-box result-error show';
                resultDiv.innerHTML = `
                    ❌ Erro ao configurar webhook
                    <pre>${error.message}</pre>
                `;
            }
        }
        
        // Carregar mensagens recentes
        async function loadRecentMessages() {
            const listDiv = document.getElementById('messagesList');
            listDiv.innerHTML = '<p style="text-align: center;"><span class="loading"></span> Carregando...</p>';
            
            try {
                const response = await fetch('/api/get_recent_messages.php?limit=10');
                const data = await response.json();
                
                if (data.success && data.messages && data.messages.length > 0) {
                    listDiv.innerHTML = data.messages.map(msg => `
                        <div class="message-item ${msg.from_me ? 'sent' : 'received'}">
                            <strong>${msg.from_me ? '📤 Enviada' : '📥 Recebida'}</strong> - ${msg.time_formatted || msg.created_at_formatted}<br>
                            <small style="color: #7f8c8d;">${msg.contact_name || msg.phone}</small><br>
                            ${msg.message_text || '[Sem texto]'}
                        </div>
                    `).join('');
                } else {
                    listDiv.innerHTML = '<p style="text-align: center; color: #7f8c8d;">Nenhuma mensagem encontrada</p>';
                }
            } catch (error) {
                console.error('Error loading messages:', error);
                listDiv.innerHTML = `<p style="color: #e74c3c;">Erro: ${error.message}</p>`;
            }
        }
        
        // Carregar logs do webhook
        async function loadWebhookLogs() {
            const logsDiv = document.getElementById('webhookLogs');
            logsDiv.innerHTML = '<p style="text-align: center;"><span class="loading"></span> Carregando logs...</p>';
            
            try {
                const response = await fetch('/api/get_recent_webhooks.php?limit=10');
                const data = await response.json();
                
                if (data.success && data.logs && data.logs.length > 0) {
                    logsDiv.innerHTML = data.logs.map(log => `
                        <div class="message-item">
                            <strong>${log.event_type}</strong> - ${log.created_at}<br>
                            <pre style="margin-top: 5px;">${JSON.stringify(JSON.parse(log.payload), null, 2).substring(0, 200)}...</pre>
                        </div>
                    `).join('');
                } else {
                    logsDiv.innerHTML = '<p style="text-align: center; color: #7f8c8d;">Nenhum log encontrado</p>';
                }
            } catch (error) {
                console.error('Error loading logs:', error);
                logsDiv.innerHTML = `<p style="color: #e74c3c;">Erro: ${error.message}</p>`;
            }
        }
        
        // Carregar status ao iniciar
        window.addEventListener('DOMContentLoaded', () => {
            checkConnection();
            loadRecentMessages();
        });
    </script>
</body>
</html>
