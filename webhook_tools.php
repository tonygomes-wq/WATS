<?php
/**
 * PAINEL DE FERRAMENTAS DO WEBHOOK
 * Acesso centralizado a todas as ferramentas de webhook e correções
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
$apiUrl = !empty($user['evolution_api_url']) ? $user['evolution_api_url'] : EVOLUTION_API_URL;

// Verificar status do webhook
$webhookStatus = 'unknown';
$webhookEnabled = false;
$webhookUrl = '';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ferramentas do Webhook</title>
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
            border-radius: 16px;
            padding: 40px;
            margin-bottom: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #7f8c8d;
            font-size: 16px;
        }
        .tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .tool-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .tool-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
        }
        .tool-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        .tool-title {
            font-size: 24px;
            color: #2c3e50;
            margin-bottom: 10px;
            font-weight: 600;
        }
        .tool-description {
            color: #7f8c8d;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        .tool-status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        .status-success {
            background: #d4edda;
            color: #155724;
        }
        .status-warning {
            background: #fff3cd;
            color: #856404;
        }
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        .btn-primary {
            background: #27ae60;
        }
        .btn-primary:hover {
            background: #229954;
        }
        .btn-danger {
            background: #e74c3c;
        }
        .btn-danger:hover {
            background: #c0392b;
        }
        .info-box {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #ecf0f1;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #2c3e50;
        }
        .info-value {
            color: #7f8c8d;
            font-family: monospace;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: white;
            text-decoration: none;
            font-weight: 600;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Ferramentas do Webhook</h1>
            <p class="subtitle">Configuração, diagnóstico e correções do sistema de mensagens</p>
        </div>
        
        <div class="tools-grid">
            <!-- Configurar Webhook -->
            <div class="tool-card">
                <div class="tool-icon">⚙️</div>
                <h2 class="tool-title">Configurar Webhook</h2>
                <p class="tool-description">
                    Configure o webhook na Evolution API para receber mensagens, atualizações de status e fotos de perfil em tempo real.
                </p>
                <a href="/configure_webhook_now.php" class="btn btn-primary">
                    Configurar Agora
                </a>
            </div>
            
            <!-- Testar Webhook -->
            <div class="tool-card">
                <div class="tool-icon">🔍</div>
                <h2 class="tool-title">Testar Webhook</h2>
                <p class="tool-description">
                    Verifique se o webhook está configurado corretamente e veja quais eventos estão sendo monitorados.
                </p>
                <a href="/test_webhook_simple.php" class="btn">
                    Verificar Status
                </a>
            </div>
            
            <!-- Diagnóstico Completo -->
            <div class="tool-card">
                <div class="tool-icon">🩺</div>
                <h2 class="tool-title">Diagnóstico Completo</h2>
                <p class="tool-description">
                    Teste completo do sistema: conexão, envio de mensagens, webhook, logs e últimas mensagens recebidas.
                </p>
                <a href="/test_webhook_messages.php" class="btn">
                    Executar Diagnóstico
                </a>
            </div>
            
            <!-- Corrigir Timestamps -->
            <div class="tool-card">
                <div class="tool-icon">🕐</div>
                <h2 class="tool-title">Corrigir Horários</h2>
                <span class="tool-status status-warning">Correção Necessária</span>
                <p class="tool-description">
                    Corrige mensagens com horário errado devido ao timestamp vir em milissegundos da Evolution API.
                </p>
                <a href="/fix_message_timestamps.php" class="btn btn-primary">
                    Corrigir Agora
                </a>
            </div>
            
            <!-- Baixar Fotos -->
            <div class="tool-card">
                <div class="tool-icon">📸</div>
                <h2 class="tool-title">Baixar Fotos de Perfil</h2>
                <span class="tool-status status-warning">Ação Recomendada</span>
                <p class="tool-description">
                    Baixa fotos de perfil dos contatos da Evolution API e salva localmente para exibição no chat.
                </p>
                <a href="/force_download_profile_pics.php" class="btn btn-primary">
                    Baixar Fotos
                </a>
            </div>
            
            <!-- Documentação -->
            <div class="tool-card">
                <div class="tool-icon">📚</div>
                <h2 class="tool-title">Documentação</h2>
                <p class="tool-description">
                    Veja todas as correções aplicadas, problemas resolvidos e próximos passos para configuração completa.
                </p>
                <a href="/CORRECOES_WEBHOOK.md" class="btn" target="_blank">
                    Ver Documentação
                </a>
            </div>
        </div>
        
        <div class="info-box">
            <h2 style="margin-bottom: 20px; color: #2c3e50;">📋 Configuração Atual</h2>
            
            <div class="info-item">
                <span class="info-label">Instância:</span>
                <span class="info-value"><?php echo htmlspecialchars($instance ?: 'Não configurada'); ?></span>
            </div>
            
            <div class="info-item">
                <span class="info-label">API URL:</span>
                <span class="info-value"><?php echo htmlspecialchars($apiUrl); ?></span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Webhook URL:</span>
                <span class="info-value">https://wats.macip.com.br/api/chat_webhook.php</span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Timezone:</span>
                <span class="info-value">America/Sao_Paulo (Brasília)</span>
            </div>
        </div>
        
        <a href="/chat.php" class="back-link">← Voltar para o Chat</a>
    </div>
</body>
</html>
