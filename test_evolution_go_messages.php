<?php
/**
 * Teste Completo - Evolution Go API
 * 
 * Testa comunicação, envio e recebimento de mensagens
 * Acesse: /test_evolution_go_messages.php
 */

require_once 'config/database.php';
require_once 'includes/channels/providers/EvolutionGoProvider.php';
require_once 'includes/channels/WhatsAppChannel.php';

// Configuração do teste
$TEST_PHONE = '5511999999999'; // ALTERE PARA SEU NÚMERO DE TESTE
$TEST_INSTANCE = 'minha-instancia'; // ALTERE PARA SUA INSTÂNCIA
$TEST_API_KEY = 'a9F3kLm8Qz2XvP7rT1bYcN6dE4uHsJ5W'; // ALTERE PARA SUA API KEY

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste Evolution Go - Mensagens</title>
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
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .header h1 {
            color: #25D366;
            font-size: 32px;
            margin-bottom: 10px;
        }
        .header p {
            color: #666;
            font-size: 16px;
        }
        .config-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .config-section h2 {
            color: #333;
            font-size: 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .config-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .config-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #25D366;
        }
        .config-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .config-value {
            font-size: 16px;
            color: #333;
            font-weight: 600;
            word-break: break-all;
        }
        .test-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .test-section h3 {
            color: #333;
            font-size: 18px;
            margin-bottom: 15px;
        }
        .test-result {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            border-left: 4px solid #ccc;
        }
        .test-result.success {
            background: #d4edda;
            border-left-color: #28a745;
        }
        .test-result.error {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        .test-result.warning {
            background: #fff3cd;
            border-left-color: #ffc107;
        }
        .test-result.info {
            background: #d1ecf1;
            border-left-color: #17a2b8;
        }
        .btn {
            background: #25D366;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn:hover {
            background: #128C7E;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(37, 211, 102, 0.3);
        }
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .code-block {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            margin: 10px 0;
        }
        .input-group {
            margin-bottom: 15px;
        }
        .input-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        .input-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .input-group input:focus {
            outline: none;
            border-color: #25D366;
        }
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
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
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Teste Evolution Go API - Mensagens</h1>
            <p>Teste completo de comunicação, envio e recebimento de mensagens</p>
        </div>

        <!-- Configuração -->
        <div class="config-section">
            <h2>⚙️ Configuração do Teste</h2>
            <div class="config-grid">
                <div class="config-item">
                    <div class="config-label">Evolution Go URL</div>
                    <div class="config-value"><?php echo EVOLUTION_GO_API_URL; ?></div>
                </div>
                <div class="config-item">
                    <div class="config-label">Instance ID</div>
                    <div class="config-value"><?php echo htmlspecialchars($TEST_INSTANCE); ?></div>
                </div>
                <div class="config-item">
                    <div class="config-label">API Key</div>
                    <div class="config-value"><?php echo substr($TEST_API_KEY, 0, 20) . '...'; ?></div>
                </div>
            </div>
            
            <div class="input-group">
                <label>Número de Teste (com código do país)</label>
                <input type="text" id="testPhone" value="<?php echo htmlspecialchars($TEST_PHONE); ?>" placeholder="5511999999999">
            </div>
        </div>

        <!-- Teste 1: Conectividade -->
        <div class="test-section">
            <h3>1️⃣ Teste de Conectividade</h3>
            <p style="color: #666; margin-bottom: 15px;">Verifica se a Evolution Go API está acessível</p>
            <button class="btn" onclick="testConnectivity()">
                <span id="btn1-icon">▶️</span>
                <span id="btn1-text">Testar Conectividade</span>
            </button>
            <div id="result1"></div>
        </div>

        <!-- Teste 2: Status da Instância -->
        <div class="test-section">
            <h3>2️⃣ Status da Instância</h3>
            <p style="color: #666; margin-bottom: 15px;">Verifica se a instância está conectada ao WhatsApp</p>
            <button class="btn" onclick="testInstanceStatus()">
                <span id="btn2-icon">▶️</span>
                <span id="btn2-text">Verificar Status</span>
            </button>
            <div id="result2"></div>
        </div>

        <!-- Teste 3: Enviar Mensagem de Texto -->
        <div class="test-section">
            <h3>3️⃣ Enviar Mensagem de Texto</h3>
            <p style="color: #666; margin-bottom: 15px;">Envia uma mensagem de texto de teste</p>
            <div class="input-group">
                <label>Mensagem</label>
                <input type="text" id="testMessage" value="🚀 Teste Evolution Go API - <?php echo date('H:i:s'); ?>" placeholder="Digite a mensagem">
            </div>
            <button class="btn" onclick="testSendText()">
                <span id="btn3-icon">▶️</span>
                <span id="btn3-text">Enviar Mensagem</span>
            </button>
            <div id="result3"></div>
        </div>

        <!-- Teste 4: Verificar Número -->
        <div class="test-section">
            <h3>4️⃣ Verificar Número no WhatsApp</h3>
            <p style="color: #666; margin-bottom: 15px;">Verifica se o número existe no WhatsApp</p>
            <button class="btn" onclick="testCheckNumber()">
                <span id="btn4-icon">▶️</span>
                <span id="btn4-text">Verificar Número</span>
            </button>
            <div id="result4"></div>
        </div>

        <!-- Teste 5: Buscar Foto de Perfil -->
        <div class="test-section">
            <h3>5️⃣ Buscar Foto de Perfil</h3>
            <p style="color: #666; margin-bottom: 15px;">Busca a foto de perfil do contato</p>
            <button class="btn" onclick="testGetProfilePicture()">
                <span id="btn5-icon">▶️</span>
                <span id="btn5-text">Buscar Foto</span>
            </button>
            <div id="result5"></div>
        </div>

        <!-- Teste 6: Teste Completo -->
        <div class="test-section">
            <h3>🎯 Teste Completo</h3>
            <p style="color: #666; margin-bottom: 15px;">Executa todos os testes em sequência</p>
            <button class="btn" onclick="runAllTests()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <span id="btnAll-icon">▶️</span>
                <span id="btnAll-text">Executar Todos os Testes</span>
            </button>
            <div id="resultAll"></div>
        </div>
    </div>

    <script>
        const API_URL = '<?php echo EVOLUTION_GO_API_URL; ?>';
        const INSTANCE = '<?php echo $TEST_INSTANCE; ?>';
        const API_KEY = '<?php echo $TEST_API_KEY; ?>';

        function showResult(elementId, type, title, message, data = null) {
            const resultDiv = document.getElementById(elementId);
            let html = `
                <div class="test-result ${type}">
                    <strong>${title}</strong><br>
                    <span style="font-size: 14px;">${message}</span>
            `;
            
            if (data) {
                html += `<div class="code-block">${JSON.stringify(data, null, 2)}</div>`;
            }
            
            html += '</div>';
            resultDiv.innerHTML = html;
        }

        function setButtonLoading(btnNum, loading) {
            const icon = document.getElementById(`btn${btnNum}-icon`);
            const text = document.getElementById(`btn${btnNum}-text`);
            const btn = icon.parentElement;
            
            if (loading) {
                icon.innerHTML = '<span class="spinner"></span>';
                btn.disabled = true;
            } else {
                icon.textContent = '▶️';
                btn.disabled = false;
            }
        }

        async function testConnectivity() {
            setButtonLoading(1, true);
            showResult('result1', 'info', '⏳ Testando...', 'Verificando conectividade com Evolution Go API...');
            
            try {
                const response = await fetch(API_URL + '/', {
                    method: 'GET',
                    headers: {
                        'apikey': API_KEY
                    }
                });
                
                if (response.ok) {
                    const data = await response.text();
                    showResult('result1', 'success', '✅ Conectividade OK', 
                        `Evolution Go API está acessível (HTTP ${response.status})`, 
                        { status: response.status, response: data.substring(0, 200) });
                } else {
                    showResult('result1', 'warning', '⚠️ Resposta Inesperada', 
                        `API respondeu com status ${response.status}`, 
                        { status: response.status });
                }
            } catch (error) {
                showResult('result1', 'error', '❌ Erro de Conexão', 
                    'Não foi possível conectar à Evolution Go API', 
                    { error: error.message });
            } finally {
                setButtonLoading(1, false);
            }
        }

        async function testInstanceStatus() {
            setButtonLoading(2, true);
            showResult('result2', 'info', '⏳ Verificando...', 'Consultando status da instância...');
            
            try {
                const response = await fetch(`${API_URL}/instance/connectionState/${INSTANCE}`, {
                    method: 'GET',
                    headers: {
                        'apikey': API_KEY,
                        'Content-Type': 'application/json'
                    }
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    const state = data.state || data.instance?.state || 'unknown';
                    const isConnected = ['open', 'connected'].includes(state);
                    
                    if (isConnected) {
                        showResult('result2', 'success', '✅ Instância Conectada', 
                            `WhatsApp está conectado! Estado: ${state}`, data);
                    } else {
                        showResult('result2', 'warning', '⚠️ Instância Desconectada', 
                            `WhatsApp não está conectado. Estado: ${state}`, data);
                    }
                } else {
                    showResult('result2', 'error', '❌ Erro ao Verificar Status', 
                        data.message || 'Erro desconhecido', data);
                }
            } catch (error) {
                showResult('result2', 'error', '❌ Erro na Requisição', 
                    error.message, { error: error.message });
            } finally {
                setButtonLoading(2, false);
            }
        }

        async function testSendText() {
            setButtonLoading(3, true);
            const phone = document.getElementById('testPhone').value;
            const message = document.getElementById('testMessage').value;
            
            if (!phone || !message) {
                showResult('result3', 'error', '❌ Dados Incompletos', 
                    'Preencha o número e a mensagem');
                setButtonLoading(3, false);
                return;
            }
            
            showResult('result3', 'info', '⏳ Enviando...', 'Enviando mensagem de texto...');
            
            try {
                const response = await fetch(`${API_URL}/message/sendText/${INSTANCE}`, {
                    method: 'POST',
                    headers: {
                        'apikey': API_KEY,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        number: phone,
                        text: message
                    })
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    showResult('result3', 'success', '✅ Mensagem Enviada', 
                        'Mensagem enviada com sucesso!', data);
                } else {
                    showResult('result3', 'error', '❌ Erro ao Enviar', 
                        data.message || data.error || 'Erro desconhecido', data);
                }
            } catch (error) {
                showResult('result3', 'error', '❌ Erro na Requisição', 
                    error.message, { error: error.message });
            } finally {
                setButtonLoading(3, false);
            }
        }

        async function testCheckNumber() {
            setButtonLoading(4, true);
            const phone = document.getElementById('testPhone').value;
            
            if (!phone) {
                showResult('result4', 'error', '❌ Número Não Informado', 
                    'Preencha o número de teste');
                setButtonLoading(4, false);
                return;
            }
            
            showResult('result4', 'info', '⏳ Verificando...', 'Consultando número no WhatsApp...');
            
            try {
                const response = await fetch(`${API_URL}/chat/whatsappNumbers/${INSTANCE}`, {
                    method: 'POST',
                    headers: {
                        'apikey': API_KEY,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        numbers: [phone]
                    })
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    const result = Array.isArray(data) ? data[0] : data;
                    const exists = result?.exists || false;
                    
                    if (exists) {
                        showResult('result4', 'success', '✅ Número Existe', 
                            `O número ${phone} está no WhatsApp`, data);
                    } else {
                        showResult('result4', 'warning', '⚠️ Número Não Encontrado', 
                            `O número ${phone} não está no WhatsApp`, data);
                    }
                } else {
                    showResult('result4', 'error', '❌ Erro ao Verificar', 
                        data.message || 'Erro desconhecido', data);
                }
            } catch (error) {
                showResult('result4', 'error', '❌ Erro na Requisição', 
                    error.message, { error: error.message });
            } finally {
                setButtonLoading(4, false);
            }
        }

        async function testGetProfilePicture() {
            setButtonLoading(5, true);
            const phone = document.getElementById('testPhone').value;
            
            if (!phone) {
                showResult('result5', 'error', '❌ Número Não Informado', 
                    'Preencha o número de teste');
                setButtonLoading(5, false);
                return;
            }
            
            showResult('result5', 'info', '⏳ Buscando...', 'Consultando foto de perfil...');
            
            try {
                const response = await fetch(`${API_URL}/chat/fetchProfilePictureUrl/${INSTANCE}`, {
                    method: 'POST',
                    headers: {
                        'apikey': API_KEY,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        number: phone
                    })
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    const pictureUrl = data.profilePictureUrl || data.url;
                    
                    if (pictureUrl) {
                        let html = `
                            <div class="test-result success">
                                <strong>✅ Foto Encontrada</strong><br>
                                <span style="font-size: 14px;">Foto de perfil do contato:</span>
                                <div style="margin-top: 15px;">
                                    <img src="${pictureUrl}" alt="Foto de Perfil" style="max-width: 200px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.2);">
                                </div>
                                <div class="code-block">${JSON.stringify(data, null, 2)}</div>
                            </div>
                        `;
                        document.getElementById('result5').innerHTML = html;
                    } else {
                        showResult('result5', 'warning', '⚠️ Foto Não Disponível', 
                            'O contato não possui foto de perfil ou ela não está visível', data);
                    }
                } else {
                    showResult('result5', 'error', '❌ Erro ao Buscar Foto', 
                        data.message || 'Erro desconhecido', data);
                }
            } catch (error) {
                showResult('result5', 'error', '❌ Erro na Requisição', 
                    error.message, { error: error.message });
            } finally {
                setButtonLoading(5, false);
            }
        }

        async function runAllTests() {
            setButtonLoading('All', true);
            document.getElementById('resultAll').innerHTML = `
                <div class="test-result info">
                    <strong>⏳ Executando Testes...</strong><br>
                    <span style="font-size: 14px;">Aguarde enquanto todos os testes são executados...</span>
                </div>
            `;
            
            const results = [];
            
            // Teste 1
            await testConnectivity();
            await new Promise(resolve => setTimeout(resolve, 1000));
            
            // Teste 2
            await testInstanceStatus();
            await new Promise(resolve => setTimeout(resolve, 1000));
            
            // Teste 3
            await testSendText();
            await new Promise(resolve => setTimeout(resolve, 1000));
            
            // Teste 4
            await testCheckNumber();
            await new Promise(resolve => setTimeout(resolve, 1000));
            
            // Teste 5
            await testGetProfilePicture();
            
            setButtonLoading('All', false);
            document.getElementById('resultAll').innerHTML = `
                <div class="test-result success">
                    <strong>✅ Todos os Testes Concluídos!</strong><br>
                    <span style="font-size: 14px;">Verifique os resultados individuais acima.</span>
                </div>
            `;
        }
    </script>
</body>
</html>
