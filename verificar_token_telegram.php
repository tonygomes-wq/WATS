<?php
/**
 * Verificar Token do Telegram
 */

require_once 'config/database.php';

echo "<h1>🔍 Verificação do Token do Telegram</h1>";

try {
    // Buscar token do banco
    $stmt = $pdo->query("
        SELECT 
            c.id as channel_id,
            c.name,
            c.status,
            ct.bot_token,
            ct.bot_name,
            ct.bot_username,
            ct.webhook_url,
            ct.webhook_verified
        FROM channels c
        JOIN channel_telegram ct ON ct.channel_id = c.id
        WHERE c.channel_type = 'telegram'
        ORDER BY c.id DESC
        LIMIT 1
    ");
    
    $channel = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$channel) {
        echo "<p>❌ Nenhum canal Telegram encontrado no banco de dados.</p>";
        exit;
    }
    
    echo "<h2>📊 Dados do Canal</h2>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>Campo</th><th>Valor</th></tr>";
    echo "<tr><td>Channel ID</td><td>{$channel['channel_id']}</td></tr>";
    echo "<tr><td>Nome</td><td>{$channel['name']}</td></tr>";
    echo "<tr><td>Status</td><td>{$channel['status']}</td></tr>";
    echo "<tr><td>Bot Token</td><td>" . substr($channel['bot_token'], 0, 20) . "...</td></tr>";
    echo "<tr><td>Bot Name</td><td>{$channel['bot_name']}</td></tr>";
    echo "<tr><td>Bot Username</td><td>@{$channel['bot_username']}</td></tr>";
    echo "<tr><td>Webhook URL</td><td>" . ($channel['webhook_url'] ?: 'Não configurado') . "</td></tr>";
    echo "<tr><td>Webhook Verified</td><td>" . ($channel['webhook_verified'] ? '✅ Sim' : '❌ Não') . "</td></tr>";
    echo "</table>";
    
    // Testar token
    echo "<h2>🧪 Testando Token...</h2>";
    
    $botToken = $channel['bot_token'];
    $url = "https://api.telegram.org/bot{$botToken}/getMe";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    echo "<h3>Resposta da API:</h3>";
    echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";
    
    if ($httpCode === 200 && isset($result['ok']) && $result['ok']) {
        echo "<h3>✅ TOKEN VÁLIDO!</h3>";
        echo "<p><strong>Bot:</strong> {$result['result']['first_name']}</p>";
        echo "<p><strong>Username:</strong> @{$result['result']['username']}</p>";
        echo "<p><strong>ID:</strong> {$result['result']['id']}</p>";
        
        // Atualizar informações no banco
        $stmt = $pdo->prepare("
            UPDATE channel_telegram 
            SET bot_name = ?, bot_username = ?
            WHERE channel_id = ?
        ");
        $stmt->execute([
            $result['result']['first_name'],
            $result['result']['username'],
            $channel['channel_id']
        ]);
        
        echo "<p>✅ Informações atualizadas no banco de dados.</p>";
        
        // Verificar webhook
        echo "<h2>🔗 Verificando Webhook...</h2>";
        
        $webhookUrl = "https://api.telegram.org/bot{$botToken}/getWebhookInfo";
        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $webhookResponse = curl_exec($ch);
        curl_close($ch);
        
        $webhookInfo = json_decode($webhookResponse, true);
        
        echo "<pre>" . json_encode($webhookInfo, JSON_PRETTY_PRINT) . "</pre>";
        
        if (isset($webhookInfo['result']['url']) && !empty($webhookInfo['result']['url'])) {
            echo "<p>✅ Webhook configurado: {$webhookInfo['result']['url']}</p>";
        } else {
            echo "<p>⚠️ Webhook não configurado. Configurando agora...</p>";
            
            // Configurar webhook
            $siteUrl = 'https://wats.macip.com.br';
            $webhookSetUrl = $siteUrl . "/api/webhooks/telegram.php?token=" . urlencode($botToken);
            
            $setUrl = "https://api.telegram.org/bot{$botToken}/setWebhook";
            $ch = curl_init($setUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'url' => $webhookSetUrl,
                'allowed_updates' => ['message', 'edited_message', 'callback_query']
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            $setResponse = curl_exec($ch);
            curl_close($ch);
            
            $setResult = json_decode($setResponse, true);
            
            if (isset($setResult['ok']) && $setResult['ok']) {
                echo "<p>✅ Webhook configurado com sucesso!</p>";
                echo "<p><strong>URL:</strong> {$webhookSetUrl}</p>";
                
                // Atualizar no banco
                $stmt = $pdo->prepare("
                    UPDATE channel_telegram 
                    SET webhook_url = ?, webhook_verified = TRUE
                    WHERE channel_id = ?
                ");
                $stmt->execute([$webhookSetUrl, $channel['channel_id']]);
            } else {
                echo "<p>❌ Erro ao configurar webhook:</p>";
                echo "<pre>" . json_encode($setResult, JSON_PRETTY_PRINT) . "</pre>";
            }
        }
        
    } else {
        echo "<h3>❌ TOKEN INVÁLIDO!</h3>";
        echo "<p><strong>Erro:</strong> {$result['description']}</p>";
        echo "<p><strong>Código:</strong> {$result['error_code']}</p>";
        
        echo "<h3>🔧 Como Corrigir:</h3>";
        echo "<ol>";
        echo "<li>Acesse o <a href='https://t.me/BotFather' target='_blank'>@BotFather</a> no Telegram</li>";
        echo "<li>Envie o comando: <code>/mybots</code></li>";
        echo "<li>Selecione seu bot</li>";
        echo "<li>Clique em 'API Token'</li>";
        echo "<li>Copie o token completo</li>";
        echo "<li>Volte para <a href='channels.php'>Canais</a> e reconfigure</li>";
        echo "</ol>";
    }
    
} catch (Exception $e) {
    echo "<h2>❌ ERRO</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}
