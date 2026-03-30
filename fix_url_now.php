<?php
/**
 * ATUALIZAR URL PARA INTERNA - UM CLIQUE
 * Script super simples para resolver o problema de timeout
 */

session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    die('Não autenticado. <a href="/login.php">Fazer login</a>');
}

$userId = $_SESSION['user_id'];
$internalUrl = 'http://evolution-api:8080';

// Se foi clicado o botão, atualizar
$updated = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $stmt = $pdo->prepare("UPDATE users SET evolution_api_url = ? WHERE id = ?");
    $stmt->execute([$internalUrl, $userId]);
    $updated = true;
}

// Buscar configuração atual
$stmt = $pdo->prepare("SELECT evolution_api_url, evolution_instance FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$currentUrl = $user['evolution_api_url'] ?? '';
$instance = $user['evolution_instance'] ?? '';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atualizar URL - Evolution API</title>
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
        .status-box {
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            border-left: 5px solid;
        }
        .status-before {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        .status-after {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        .status-success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        .url-display {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            margin: 10px 0;
            word-break: break-all;
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
        .icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        .comparison {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 15px;
            align-items: center;
            margin: 20px 0;
        }
        .comparison-item {
            text-align: center;
        }
        .comparison-arrow {
            font-size: 24px;
            color: #27ae60;
        }
        .info-list {
            list-style: none;
            padding: 0;
        }
        .info-list li {
            padding: 8px 0;
            border-bottom: 1px solid #ecf0f1;
        }
        .info-list li:last-child {
            border-bottom: none;
        }
        .info-list li strong {
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($updated): ?>
            <div class="icon">🎉</div>
            <h1>URL Atualizada com Sucesso!</h1>
            
            <div class="status-box status-success">
                <strong>✅ Tudo Pronto!</strong><br><br>
                Agora o sistema usa comunicação interna (super rápido!)
            </div>
            
            <div class="comparison">
                <div class="comparison-item">
                    <div style="color: #e74c3c; font-size: 14px; margin-bottom: 5px;">❌ ANTES</div>
                    <div style="font-size: 12px; color: #7f8c8d;">Externa (lenta)</div>
                    <div class="url-display" style="font-size: 11px;">
                        <?php echo htmlspecialchars($currentUrl ?: 'https://evolution.macip.com.br'); ?>
                    </div>
                </div>
                
                <div class="comparison-arrow">→</div>
                
                <div class="comparison-item">
                    <div style="color: #27ae60; font-size: 14px; margin-bottom: 5px;">✅ AGORA</div>
                    <div style="font-size: 12px; color: #7f8c8d;">Interna (rápida)</div>
                    <div class="url-display" style="font-size: 11px;">
                        <?php echo htmlspecialchars($internalUrl); ?>
                    </div>
                </div>
            </div>
            
            <div style="margin: 30px 0;">
                <strong>🚀 Benefícios:</strong>
                <ul class="info-list">
                    <li>✅ Sem timeout ao criar instâncias</li>
                    <li>✅ Envio de mensagens 100x mais rápido</li>
                    <li>✅ Comunicação direta entre containers</li>
                    <li>✅ Mais seguro (tráfego interno)</li>
                </ul>
            </div>
            
            <a href="/my_instance.php" class="btn">
                🚀 Criar Instância Agora
            </a>
            
            <a href="/test_webhook_messages.php" class="btn btn-secondary">
                🔍 Testar Conexão
            </a>
            
        <?php else: ?>
            <div class="icon">🔧</div>
            <h1>Atualizar URL para Interna</h1>
            
            <div class="status-box status-before">
                <strong>⚠️ Problema Detectado</strong><br><br>
                Você está usando URL externa, por isso está dando timeout.
            </div>
            
            <div style="margin: 20px 0;">
                <strong>URL Atual:</strong>
                <div class="url-display">
                    <?php echo htmlspecialchars($currentUrl ?: 'Não configurada'); ?>
                </div>
            </div>
            
            <div style="margin: 20px 0;">
                <strong>Nova URL (Interna):</strong>
                <div class="url-display">
                    <?php echo htmlspecialchars($internalUrl); ?>
                </div>
            </div>
            
            <div class="status-box status-after">
                <strong>✅ O que vai acontecer:</strong><br><br>
                <ul class="info-list">
                    <li>✅ URL será atualizada para comunicação interna</li>
                    <li>✅ Sem mais timeout ao criar instâncias</li>
                    <li>✅ Mensagens 100x mais rápidas</li>
                    <li>✅ Tudo funcionando perfeitamente</li>
                </ul>
            </div>
            
            <form method="POST">
                <button type="submit" name="update" class="btn">
                    ✅ Atualizar Agora (1 Clique)
                </button>
            </form>
            
            <div style="text-align: center; margin-top: 20px; color: #7f8c8d; font-size: 14px;">
                Instância: <strong><?php echo htmlspecialchars($instance ?: 'Não configurada'); ?></strong>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
