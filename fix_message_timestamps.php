<?php
/**
 * CORRIGIR TIMESTAMPS DAS MENSAGENS
 * Corrige mensagens com timestamp em milissegundos (Evolution API)
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    die('Não autenticado. <a href="/login.php">Fazer login</a>');
}

$userId = $_SESSION['user_id'];
$fixed = 0;
$errors = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix'])) {
    try {
        // Buscar mensagens com timestamp suspeito (muito grande = milissegundos)
        // Timestamp em segundos tem 10 dígitos, em milissegundos tem 13
        $stmt = $pdo->prepare("
            SELECT id, timestamp, message_text, created_at
            FROM chat_messages
            WHERE user_id = ?
            AND timestamp > 9999999999
            ORDER BY id DESC
            LIMIT 1000
        ");
        $stmt->execute([$userId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($messages as $msg) {
            $oldTimestamp = $msg['timestamp'];
            $newTimestamp = (int)($oldTimestamp / 1000); // Converter de milissegundos para segundos
            
            // Atualizar timestamp
            $updateStmt = $pdo->prepare("
                UPDATE chat_messages 
                SET timestamp = ? 
                WHERE id = ?
            ");
            
            if ($updateStmt->execute([$newTimestamp, $msg['id']])) {
                $fixed++;
            } else {
                $errors++;
            }
        }
        
        // Atualizar last_message_time das conversas afetadas
        $stmt = $pdo->prepare("
            UPDATE chat_conversations cc
            SET last_message_time = (
                SELECT FROM_UNIXTIME(MAX(cm.timestamp))
                FROM chat_messages cm
                WHERE cm.conversation_id = cc.id
            )
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        
    } catch (Exception $e) {
        $errors++;
        $errorMessage = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Corrigir Timestamps</title>
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
        .stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        .stat-card h3 {
            font-size: 36px;
            margin-bottom: 10px;
            color: #27ae60;
        }
        .stat-card.error h3 {
            color: #e74c3c;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($fixed > 0 || $errors > 0): ?>
            <h1>✅ Correção Concluída!</h1>
            
            <div class="stats">
                <div class="stat-card">
                    <h3><?php echo $fixed; ?></h3>
                    <p>Mensagens Corrigidas</p>
                </div>
                <div class="stat-card <?php echo $errors > 0 ? 'error' : ''; ?>">
                    <h3><?php echo $errors; ?></h3>
                    <p>Erros</p>
                </div>
            </div>
            
            <div class="alert alert-success">
                <strong>✅ Sucesso!</strong><br><br>
                Os timestamps das mensagens foram corrigidos. As conversas agora mostram o horário correto.
            </div>
            
            <?php if (isset($errorMessage)): ?>
            <div class="alert alert-error">
                <strong>❌ Erro:</strong><br><br>
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
            <?php endif; ?>
            
            <a href="/chat.php" class="btn">
                💬 Ir para o Chat
            </a>
            
        <?php else: ?>
            <h1>🕐 Corrigir Timestamps</h1>
            
            <div class="alert alert-info">
                <strong>ℹ️ O que este script faz:</strong><br><br>
                Corrige mensagens que estão com horário errado devido ao timestamp vir em milissegundos da Evolution API.
                <br><br>
                <strong>Problema:</strong> Mensagens antigas aparecem com hora atual<br>
                <strong>Causa:</strong> Timestamp em milissegundos (13 dígitos) ao invés de segundos (10 dígitos)<br>
                <strong>Solução:</strong> Converter timestamps e atualizar conversas
            </div>
            
            <form method="POST">
                <button type="submit" name="fix" class="btn">
                    🔧 Corrigir Timestamps Agora
                </button>
            </form>
            
            <a href="/chat.php" class="btn btn-secondary">
                ← Voltar
            </a>
        <?php endif; ?>
    </div>
</body>
</html>
