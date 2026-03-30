<?php
/**
 * Corrigir URL da Evolution API
 * Substitui IPs internos do Docker por URL pública
 */

session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    die('Não autenticado. <a href="/login.php">Fazer login</a>');
}

$userId = $_SESSION['user_id'];

// Buscar configuração atual
$stmt = $pdo->prepare("SELECT evolution_api_url, evolution_instance FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$currentUrl = $user['evolution_api_url'] ?? '';
$instance = $user['evolution_instance'] ?? '';

// Verificar se é IP interno
$isInternalIp = (
    strpos($currentUrl, '172.18.') !== false ||
    strpos($currentUrl, '192.168.') !== false ||
    strpos($currentUrl, '10.') !== false ||
    strpos($currentUrl, 'localhost') !== false ||
    strpos($currentUrl, '127.0.0.1') !== false
);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Corrigir URL da Evolution API</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            border-radius: 12px;
            padding: 40px;
            max-width: 600px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 20px;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .info-box strong {
            display: block;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        .info-box code {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            margin: 5px;
            transition: all 0.3s;
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
        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            font-size: 14px;
            margin: 10px 0;
        }
        input[type="text"]:focus {
            outline: none;
            border-color: #3498db;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Corrigir URL da Evolution API</h1>
        
        <?php if ($isInternalIp): ?>
        <div class="alert alert-warning">
            <strong>⚠️ Problema Detectado!</strong><br>
            Sua Evolution API está configurada com um IP interno do Docker que não funciona do navegador.
        </div>
        <?php else: ?>
        <div class="alert alert-success">
            <strong>✅ URL Pública Detectada</strong><br>
            Sua Evolution API parece estar configurada corretamente.
        </div>
        <?php endif; ?>
        
        <div class="info-box">
            <strong>URL Atual:</strong>
            <code><?php echo htmlspecialchars($currentUrl ?: 'Não configurada'); ?></code>
        </div>
        
        <div class="info-box">
            <strong>Instância:</strong>
            <code><?php echo htmlspecialchars($instance ?: 'Não configurada'); ?></code>
        </div>
        
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <?php
            $newUrl = trim($_POST['new_url'] ?? '');
            
            if (empty($newUrl)) {
                echo '<div class="alert alert-warning">Por favor, informe a nova URL.</div>';
            } else {
                // Atualizar URL
                $stmt = $pdo->prepare("UPDATE users SET evolution_api_url = ? WHERE id = ?");
                $stmt->execute([$newUrl, $userId]);
                
                echo '<div class="alert alert-success">';
                echo '<strong>✅ URL Atualizada com Sucesso!</strong><br>';
                echo 'Nova URL: <code>' . htmlspecialchars($newUrl) . '</code><br><br>';
                echo '<a href="/test_webhook_messages.php" class="btn btn-primary">Testar Webhook</a>';
                echo '</div>';
                
                // Atualizar variável para exibir nova URL
                $currentUrl = $newUrl;
            }
            ?>
        <?php endif; ?>
        
        <form method="POST">
            <div class="alert alert-info">
                <strong>ℹ️ Instruções:</strong><br>
                1. Informe a URL pública da sua Evolution API<br>
                2. Exemplo: <code>https://evolution.macip.com.br</code><br>
                3. Não use IPs internos (172.x, 192.168.x, 10.x)<br>
                4. Não inclua a porta se usar HTTPS padrão
            </div>
            
            <label for="new_url"><strong>Nova URL da Evolution API:</strong></label>
            <input 
                type="text" 
                id="new_url" 
                name="new_url" 
                placeholder="https://evolution.macip.com.br"
                value="<?php echo $isInternalIp ? 'https://evolution.macip.com.br' : htmlspecialchars($currentUrl); ?>"
            >
            
            <button type="submit" class="btn btn-success">💾 Salvar Nova URL</button>
            <a href="/test_webhook_messages.php" class="btn btn-primary">🔍 Testar Webhook</a>
        </form>
    </div>
</body>
</html>
