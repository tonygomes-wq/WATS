<?php
/**
 * Configurador de Webhook para Evolution API
 * Configura automaticamente o webhook na Evolution API
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    die('Faça login primeiro');
}

$userId = $_SESSION['user_id'];

// Buscar dados do usuário
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$webhookUrl = 'https://wats.macip.com.br/api/webhook_simple.php';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Webhook - Evolution API</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-6">
        <h1 class="text-3xl font-bold mb-6">🔗 Configurar Webhook</h1>
        
        <!-- Status Atual -->
        <div class="bg-white rounded-lg p-6 mb-6 shadow">
            <h2 class="text-xl font-bold mb-4">📊 Status Atual</h2>
            <div class="grid grid-cols-2 gap-4">
                <div><strong>Usuário:</strong> <?php echo htmlspecialchars($user['name']); ?></div>
                <div><strong>Instance:</strong> <?php echo htmlspecialchars($user['evolution_instance'] ?? 'NÃO CONFIGURADO'); ?></div>
                <div><strong>Token:</strong> <?php echo $user['evolution_token'] ? '✅ Configurado' : '❌ Não configurado'; ?></div>
                <div><strong>URL do Webhook:</strong> <code><?php echo $webhookUrl; ?></code></div>
            </div>
        </div>

        <!-- Configuração Automática -->
        <div class="bg-white rounded-lg p-6 mb-6 shadow">
            <h2 class="text-xl font-bold mb-4">🤖 Configuração Automática</h2>
            <p class="mb-4">Clique no botão abaixo para configurar automaticamente o webhook na Evolution API:</p>
            
            <button onclick="configureWebhook()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg mb-4">
                <i class="fas fa-cog mr-2"></i>Configurar Webhook Automaticamente
            </button>
            
            <div id="auto-result" class="mt-4"></div>
        </div>

        <!-- Configuração Manual -->
        <div class="bg-white rounded-lg p-6 mb-6 shadow">
            <h2 class="text-xl font-bold mb-4">✋ Configuração Manual</h2>
            <p class="mb-4">Se a configuração automática não funcionar, siga estes passos:</p>
            
            <div class="bg-gray-100 p-4 rounded-lg mb-4">
                <h3 class="font-bold mb-2">1. Acesse a Evolution API Manager:</h3>
                <p>URL: <code><?php echo EVOLUTION_API_URL; ?></code></p>
            </div>
            
            <div class="bg-gray-100 p-4 rounded-lg mb-4">
                <h3 class="font-bold mb-2">2. Configure o Webhook:</h3>
                <ul class="list-disc ml-6">
                    <li><strong>Instance:</strong> <?php echo htmlspecialchars($user['evolution_instance'] ?? 'SUA_INSTANCIA'); ?></li>
                    <li><strong>Webhook URL:</strong> <code><?php echo $webhookUrl; ?></code></li>
                    <li><strong>Events:</strong> <code>messages.upsert</code></li>
                    <li><strong>Webhook by Events:</strong> Ativado</li>
                </ul>
            </div>
            
            <div class="bg-yellow-100 p-4 rounded-lg">
                <h3 class="font-bold mb-2">⚠️ Importante:</h3>
                <p>Certifique-se de que a instância Evolution esteja <strong>conectada</strong> e <strong>ativa</strong> antes de configurar o webhook.</p>
            </div>
        </div>

        <!-- Teste do Webhook -->
        <div class="bg-white rounded-lg p-6 mb-6 shadow">
            <h2 class="text-xl font-bold mb-4">🧪 Testar Webhook</h2>
            <div class="flex space-x-4 mb-4">
                <button onclick="testWebhook()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                    <i class="fas fa-flask mr-2"></i>Testar Webhook
                </button>
                <button onclick="checkInstance()" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded">
                    <i class="fas fa-check mr-2"></i>Verificar Instância
                </button>
                <button onclick="simulateMessage()" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded">
                    <i class="fas fa-paper-plane mr-2"></i>Simular Mensagem
                </button>
            </div>
            <div id="test-result" class="mt-4"></div>
        </div>

        <!-- Log -->
        <div class="bg-gray-900 text-green-400 rounded-lg p-6 shadow">
            <h2 class="text-xl font-bold mb-4 text-white">📋 Log</h2>
            <div id="log" class="font-mono text-sm whitespace-pre-wrap h-64 overflow-y-auto"></div>
            <button onclick="clearLog()" class="mt-2 bg-red-600 text-white px-3 py-1 rounded text-sm">Limpar Log</button>
        </div>

        <div class="mt-6">
            <a href="test_send_receive.php" class="bg-green-600 text-white px-6 py-3 rounded-lg inline-block">
                🔙 Voltar ao Teste
            </a>
        </div>
    </div>

    <script>
        function log(message, type = 'info') {
            const logDiv = document.getElementById('log');
            const timestamp = new Date().toLocaleTimeString();
            const colors = {
                'info': '#00ff00',
                'success': '#00ff00',
                'error': '#ff0000',
                'warning': '#ffff00'
            };
            
            logDiv.innerHTML += `<span style="color: ${colors[type] || '#00ff00'}">[${timestamp}] ${message}</span>\n`;
            logDiv.scrollTop = logDiv.scrollHeight;
        }

        function clearLog() {
            document.getElementById('log').innerHTML = '';
        }

        async function configureWebhook() {
            log('🔧 Configurando webhook automaticamente...', 'info');
            
            const webhookConfig = {
                url: '<?php echo $webhookUrl; ?>',
                events: ['messages.upsert'],
                webhook_by_events: true
            };
            
            try {
                const response = await fetch('<?php echo EVOLUTION_API_URL; ?>/webhook/set/<?php echo $user['evolution_instance']; ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'apikey': '<?php echo $user['evolution_token'] ?: EVOLUTION_API_KEY; ?>'
                    },
                    body: JSON.stringify(webhookConfig)
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    log('✅ Webhook configurado com sucesso!', 'success');
                    document.getElementById('auto-result').innerHTML = '<div class="bg-green-100 p-4 rounded text-green-800">✅ Webhook configurado com sucesso!</div>';
                } else {
                    log('❌ Erro ao configurar webhook: ' + JSON.stringify(data), 'error');
                    document.getElementById('auto-result').innerHTML = '<div class="bg-red-100 p-4 rounded text-red-800">❌ Erro: ' + JSON.stringify(data) + '</div>';
                }
            } catch (error) {
                log('❌ Erro de rede: ' + error.message, 'error');
                document.getElementById('auto-result').innerHTML = '<div class="bg-red-100 p-4 rounded text-red-800">❌ Erro de rede: ' + error.message + '</div>';
            }
        }

        async function testWebhook() {
            log('🧪 Testando webhook...', 'info');
            
            try {
                const response = await fetch('<?php echo $webhookUrl; ?>');
                const data = await response.json();
                
                if (data.status) {
                    log('✅ Webhook respondendo: ' + data.status, 'success');
                    document.getElementById('test-result').innerHTML = '<div class="bg-green-100 p-4 rounded text-green-800">✅ Webhook ativo</div>';
                } else {
                    log('❌ Webhook não respondeu corretamente', 'error');
                    document.getElementById('test-result').innerHTML = '<div class="bg-red-100 p-4 rounded text-red-800">❌ Webhook inativo</div>';
                }
            } catch (error) {
                log('❌ Erro no teste: ' + error.message, 'error');
                document.getElementById('test-result').innerHTML = '<div class="bg-red-100 p-4 rounded text-red-800">❌ Erro: ' + error.message + '</div>';
            }
        }

        async function checkInstance() {
            log('🔍 Verificando instância...', 'info');
            
            try {
                const response = await fetch('<?php echo EVOLUTION_API_URL; ?>/instance/fetchInstances', {
                    headers: {
                        'apikey': '<?php echo $user['evolution_token'] ?: EVOLUTION_API_KEY; ?>'
                    }
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    log('✅ Instâncias encontradas: ' + JSON.stringify(data), 'success');
                    
                    const instance = data.find(i => i.instance.instanceName === '<?php echo $user['evolution_instance']; ?>');
                    if (instance) {
                        log('✅ Instância encontrada: ' + instance.instance.status, 'success');
                    } else {
                        log('❌ Instância não encontrada', 'error');
                    }
                } else {
                    log('❌ Erro ao verificar instâncias: ' + JSON.stringify(data), 'error');
                }
            } catch (error) {
                log('❌ Erro: ' + error.message, 'error');
            }
        }

        async function simulateMessage() {
            log('📨 Simulando recebimento de mensagem...', 'info');
            
            const testPayload = {
                event: 'messages.upsert',
                data: {
                    key: {
                        remoteJid: '<?php echo $user['phone'] ?? '5543999962354'; ?>@s.whatsapp.net',
                        fromMe: false,
                        id: 'test_' + Date.now()
                    },
                    message: {
                        conversation: 'Mensagem de teste simulada - ' + new Date().toLocaleTimeString()
                    },
                    messageTimestamp: Math.floor(Date.now() / 1000)
                }
            };
            
            try {
                const response = await fetch('<?php echo $webhookUrl; ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(testPayload)
                });
                
                if (response.ok) {
                    log('✅ Mensagem simulada enviada com sucesso!', 'success');
                } else {
                    log('❌ Erro ao simular mensagem', 'error');
                }
            } catch (error) {
                log('❌ Erro: ' + error.message, 'error');
            }
        }

        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            log('🚀 Configurador de webhook carregado', 'success');
        });
    </script>
</body>
</html>
