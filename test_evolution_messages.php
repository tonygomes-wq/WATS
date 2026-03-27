<?php
/**
 * 🔬 DIAGNÓSTICO COMPLETO - ENVIO E RECEBIMENTO DE MENSAGENS
 * Evolution API - WATS
 *
 * Testa:
 * 1. Conectividade e autenticação
 * 2. Listagem de instâncias e status
 * 3. Envio de mensagem de texto
 * 4. Verificação de mensagens recebidas (webhook logs)
 * 5. Estrutura de banco de dados (chat_messages, chat_conversations, webhook_logs)
 * 6. Instâncias configuradas por usuário no banco
 * 7. Logs de erro recentes
 *
 * USO: Acesse pelo navegador: http://seu-dominio/test_evolution_messages.php?phone=5511999999999
 *
 * ATENÇÃO: Remova este arquivo após o diagnóstico!
 */

// Segurança básica: bloquear em produção se não há query string de confirmação
// Comente a linha abaixo se quiser acessar sem parâmetro de confirmação
// if (!isset($_GET['confirm'])) { die('Adicione ?confirm=1 na URL'); }

require_once 'config/database.php';

// ==========================================
// PARÂMETROS VIA URL
// ==========================================
$testPhone   = $_GET['phone']    ?? '';  // Número para envio: 5511999999999
$testMessage = $_GET['msg']      ?? 'Teste de diagnóstico WATS - ' . date('d/m/Y H:i:s');
$instanceOverride = $_GET['instance'] ?? ''; // Forçar instância específica
$doSend      = isset($_GET['send']); // Adicionar ?send para realmente enviar
$showWebhook = isset($_GET['webhook']); // Mostrar detalhes do webhook

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Diagnóstico Evolution API - Envio/Recebimento</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; background: #0f1117; color: #e0e0e0; }
        .container { max-width: 1100px; margin: 0 auto; padding: 30px 20px; }
        h1 { color: #58a6ff; border-bottom: 2px solid #21262d; padding-bottom: 12px; font-size: 24px; }
        h2 { color: #79c0ff; margin-top: 35px; font-size: 18px; display: flex; align-items: center; gap: 8px; }
        .card { background: #161b22; border: 1px solid #21262d; border-radius: 8px; padding: 20px; margin: 15px 0; }
        .ok    { color: #3fb950; font-weight: bold; }
        .err   { color: #f85149; font-weight: bold; }
        .warn  { color: #d29922; font-weight: bold; }
        .info  { color: #79c0ff; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; margin-left: 6px; }
        .badge-ok   { background: #0d4a23; color: #3fb950; }
        .badge-err  { background: #4a0d0d; color: #f85149; }
        .badge-warn { background: #4a3a0d; color: #d29922; }
        .badge-info { background: #0d284a; color: #79c0ff; }
        pre, .code { background: #0d1117; border: 1px solid #21262d; border-radius: 6px; padding: 15px; overflow-x: auto; font-family: 'Courier New', monospace; font-size: 13px; white-space: pre-wrap; word-break: break-all; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        th { background: #21262d; padding: 8px 12px; text-align: left; color: #8b949e; font-weight: 600; border-bottom: 1px solid #30363d; }
        td { padding: 8px 12px; border-bottom: 1px solid #21262d; vertical-align: top; }
        tr:hover td { background: #1c2128; }
        .url-box { background: #0d1117; border: 1px solid #30363d; border-radius: 6px; padding: 12px; margin: 10px 0; }
        .url-box a { color: #58a6ff; text-decoration: none; }
        .url-box a:hover { text-decoration: underline; }
        .section-divider { border: none; border-top: 1px solid #21262d; margin: 30px 0; }
        .alert { padding: 12px 16px; border-radius: 6px; margin: 10px 0; border-left: 4px solid; }
        .alert-ok   { background: #0d4a2333; border-color: #3fb950; }
        .alert-err  { background: #4a0d0d33; border-color: #f85149; }
        .alert-warn { background: #4a3a0d33; border-color: #d29922; }
        .btn { display: inline-block; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 13px; margin: 4px; }
        .btn-primary { background: #1f6feb; color: white; }
        .btn-success { background: #238636; color: white; }
        .btn-danger  { background: #da3633; color: white; }
    </style>
</head>
<body>
<div class="container">

<h1>🔬 Diagnóstico Evolution API — Envio & Recebimento</h1>
<p style="color:#8b949e">Data/Hora: <strong style="color:#e0e0e0"><?= date('d/m/Y H:i:s') ?></strong> | Servidor: <strong style="color:#e0e0e0"><?= gethostname() ?></strong></p>

<?php
// ==========================================
// FORMULÁRIO DE CONTROLE
// ==========================================
?>
<div class="card">
    <strong>🎛️ Parâmetros do Teste</strong><br><br>
    <form method="GET" style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end;">
        <div>
            <label style="display:block;color:#8b949e;font-size:12px;margin-bottom:4px;">Número (com DDI): ex: 5511999999999</label>
            <input type="text" name="phone" value="<?= htmlspecialchars($testPhone) ?>"
                   placeholder="5511999999999" style="background:#0d1117;border:1px solid #30363d;color:#e0e0e0;padding:8px;border-radius:4px;width:200px;">
        </div>
        <div>
            <label style="display:block;color:#8b949e;font-size:12px;margin-bottom:4px;">Mensagem de teste</label>
            <input type="text" name="msg" value="<?= htmlspecialchars($testMessage) ?>"
                   style="background:#0d1117;border:1px solid #30363d;color:#e0e0e0;padding:8px;border-radius:4px;width:300px;">
        </div>
        <div>
            <label style="display:block;color:#8b949e;font-size:12px;margin-bottom:4px;">Instância (opcional)</label>
            <input type="text" name="instance" value="<?= htmlspecialchars($instanceOverride) ?>"
                   placeholder="Deixe vazio para auto-detectar" style="background:#0d1117;border:1px solid #30363d;color:#e0e0e0;padding:8px;border-radius:4px;width:200px;">
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary">🔍 Só Diagnosticar</button>
            <button type="submit" name="send" value="1" class="btn btn-success" onclick="return confirm('Enviar mensagem de teste para <?= htmlspecialchars($testPhone) ?>?')">
                📤 Enviar Mensagem
            </button>
            <button type="submit" name="webhook" value="1" class="btn" style="background:#6e40c9;color:white;">📥 Ver Webhooks</button>
        </div>
    </form>
</div>

<?php

// ==========================================
// 1. CONFIGURAÇÕES
// ==========================================
echo '<h2>1️⃣ Configurações da Evolution API</h2>';
echo '<div class="card">';
$evolutionUrl = EVOLUTION_API_URL;
$globalApiKey = EVOLUTION_API_KEY;

$urlOk = !empty($evolutionUrl) && $evolutionUrl !== 'https://evolution.macip.com.br' || strpos($evolutionUrl, 'http') === 0;
echo "<table>";
echo "<tr><th>Variável</th><th>Valor</th><th>Status</th></tr>";
echo "<tr><td>EVOLUTION_API_URL</td><td><code>$evolutionUrl</code></td><td>" . ($evolutionUrl ? '<span class="ok">✅ Definida</span>' : '<span class="err">❌ Vazia</span>') . "</td></tr>";
echo "<tr><td>EVOLUTION_API_KEY</td><td><code>" . substr($globalApiKey, 0, 8) . "..." . substr($globalApiKey, -4) . "</code> (" . strlen($globalApiKey) . " chars)</td><td>" . (!empty($globalApiKey) && $globalApiKey !== 'sua-chave-api' ? '<span class="ok">✅ Configurada</span>' : '<span class="err">❌ Não configurada</span>') . "</td></tr>";
echo "</table>";
echo '</div>';

// ==========================================
// 2. CONECTIVIDADE
// ==========================================
echo '<h2>2️⃣ Teste de Conectividade</h2>';
echo '<div class="card">';

$ch = curl_init($evolutionUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_NOBODY         => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_FOLLOWLOCATION => true,
]);
curl_exec($ch);
$connHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$connTime     = round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000, 1);
$connError    = curl_error($ch);
$connRealUrl  = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

if ($connError) {
    echo "<div class='alert alert-err'>❌ <strong>Falha de Conexão:</strong> $connError</div>";
    echo "<p class='warn'>⚠️ Causas possíveis:</p><ul>
        <li>Evolution API não está rodando</li>
        <li>URL incorreta no .env: <code>EVOLUTION_API_URL=$evolutionUrl</code></li>
        <li>Firewall / rede bloqueando conexão</li>
        <li>Problema de DNS ou SSL</li>
    </ul>";
} else {
    $badgeClass = ($connHttpCode >= 200 && $connHttpCode < 400) ? 'badge-ok' : 'badge-warn';
    echo "<div class='alert alert-ok'>✅ <strong>Conectado!</strong> HTTP <span class='badge $badgeClass'>$connHttpCode</span> em {$connTime}ms</div>";
    echo "<p>URL efetiva: <code>$connRealUrl</code></p>";
}
echo '</div>';

// ==========================================
// 3. AUTENTICAÇÃO E LISTAGEM DE INSTÂNCIAS
// ==========================================
echo '<h2>3️⃣ Autenticação & Instâncias na Evolution API</h2>';
echo '<div class="card">';

$ch = curl_init($evolutionUrl . '/instance/fetchInstances');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'apikey: ' . $globalApiKey],
]);
$instancesResponse = curl_exec($ch);
$instancesCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$instancesError    = curl_error($ch);
curl_close($ch);

if ($instancesError) {
    echo "<div class='alert alert-err'>❌ Erro cURL: $instancesError</div>";
} elseif ($instancesCode === 401) {
    echo "<div class='alert alert-err'>❌ <strong>HTTP 401 - Não Autorizado</strong></div>";
    echo "<p class='err'>A API KEY global está incorreta.<br>Verifique <code>EVOLUTION_API_KEY</code> no .env</p>";
    echo "<pre>" . htmlspecialchars($instancesResponse) . "</pre>";
} elseif ($instancesCode === 200) {
    $instances = json_decode($instancesResponse, true);
    if (is_array($instances)) {
        echo "<div class='alert alert-ok'>✅ <strong>Autenticado!</strong> " . count($instances) . " instância(s) encontrada(s)</div>";
        echo "<table><tr><th>Instância</th><th>Status de Conexão</th><th>Propietário</th><th>Perfil</th></tr>";
        foreach ($instances as $inst) {
            $instName   = $inst['instance']['instanceName'] ?? $inst['name'] ?? '?';
            $instState  = $inst['instance']['state'] ?? $inst['connectionStatus'] ?? '?';
            $instOwner  = $inst['instance']['owner'] ?? '-';
            $instProfile = $inst['instance']['profileName'] ?? '-';
            $stateClass = strtolower($instState) === 'open' ? 'ok' : (strtolower($instState) === 'connecting' ? 'warn' : 'err');
            echo "<tr>
                <td><strong>$instName</strong></td>
                <td><span class='$stateClass'>$instState</span></td>
                <td>$instOwner</td>
                <td>$instProfile</td>
            </tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='alert alert-warn'>⚠️ Resposta inválida: <pre>" . htmlspecialchars($instancesResponse) . "</pre></div>";
    }
} else {
    echo "<div class='alert alert-warn'>⚠️ HTTP $instancesCode</div>";
    echo "<pre>" . htmlspecialchars($instancesResponse) . "</pre>";
}
echo '</div>';

// ==========================================
// 4. INSTÂNCIAS CONFIGURADAS NO BANCO
// ==========================================
echo '<h2>4️⃣ Instâncias Configuradas no Banco de Dados</h2>';
echo '<div class="card">';

try {
    $stmt = $pdo->query("
        SELECT id, name, email, evolution_instance, 
               CASE WHEN evolution_token IS NOT NULL AND evolution_token != '' THEN 'Sim' ELSE 'Não' END as has_token,
               LEFT(evolution_token, 8) as token_preview,
               whatsapp_provider
        FROM users 
        WHERE evolution_instance IS NOT NULL AND evolution_instance != ''
        ORDER BY id ASC
    ");
    $usersWithInstance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($usersWithInstance) === 0) {
        echo "<div class='alert alert-warn'>⚠️ Nenhum usuário tem instância Evolution configurada no banco</div>";
    } else {
        echo "<table><tr><th>ID</th><th>Nome</th><th>Email</th><th>Instância</th><th>Token</th><th>Provedor</th></tr>";
        foreach ($usersWithInstance as $u) {
            echo "<tr>
                <td>{$u['id']}</td>
                <td>{$u['name']}</td>
                <td>{$u['email']}</td>
                <td><strong>{$u['evolution_instance']}</strong></td>
                <td>" . ($u['has_token'] === 'Sim' ? "<span class='ok'>✅ {$u['token_preview']}...</span>" : "<span class='err'>❌ Sem token</span>") . "</td>
                <td>{$u['whatsapp_provider']}</td>
            </tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<div class='alert alert-err'>❌ Erro ao consultar banco: " . htmlspecialchars($e->getMessage()) . "</div>";
}
echo '</div>';

// ==========================================
// 5. STATUS DE SAÚDE DE CADA INSTÂNCIA
// ==========================================
if (!empty($usersWithInstance)) {
    echo '<h2>5️⃣ Status de Conexão de Cada Instância</h2>';
    echo '<div class="card">';
    
    foreach ($usersWithInstance as $u) {
        $instName = $u['evolution_instance'];
        $apiKey   = !empty($u['token_preview']) ? $u['evolution_token'] ?? $globalApiKey : $globalApiKey;
        // Usar apikey do usuário se disponível (via consulta separada)
        try {
            $stmt2 = $pdo->prepare("SELECT evolution_token FROM users WHERE id = ?");
            $stmt2->execute([$u['id']]);
            $fullUser = $stmt2->fetch(PDO::FETCH_ASSOC);
            $apiKey = !empty($fullUser['evolution_token']) ? $fullUser['evolution_token'] : $globalApiKey;
        } catch (Exception $e) {
            $apiKey = $globalApiKey;
        }

        $ch = curl_init($evolutionUrl . '/instance/connectionState/' . $instName);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'apikey: ' . $apiKey],
        ]);
        $stateResp = curl_exec($ch);
        $stateCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $stateErr  = curl_error($ch);
        curl_close($ch);

        $stateData = json_decode($stateResp, true);
        $connState = $stateData['instance']['state'] ?? $stateData['state'] ?? ($stateErr ?: 'Erro HTTP ' . $stateCode);

        $stateClass = strtolower($connState) === 'open' ? 'ok' : (strtolower($connState) === 'connecting' ? 'warn' : 'err');
        $stateIcon  = strtolower($connState) === 'open' ? '✅' : (strtolower($connState) === 'connecting' ? '⏳' : '❌');

        echo "<div style='display:flex;justify-content:space-between;align-items:center;padding:10px;border-bottom:1px solid #21262d;'>";
        echo "<div><strong>$instName</strong> <span style='color:#8b949e;font-size:12px'>(Usuário: {$u['name']})</span></div>";
        echo "<div><span class='$stateClass'>$stateIcon $connState</span></div>";
        echo "</div>";

        if (strtolower($connState) !== 'open') {
            echo "<div class='alert alert-err' style='margin:4px 0;'>";
            echo "<strong>Problema:</strong> Instância <code>$instName</code> não está conectada (estado: $connState).<br>";
            echo "Possíveis causas: WhatsApp desconectado, QR Code expirado, instância parada.<br>";
            echo "Solução: Vá em <strong>Minha Instância</strong> e reconecte o WhatsApp.";
            echo "</div>";
        }
    }
    echo '</div>';
}

// ==========================================
// 6. ENVIO DE MENSAGEM DE TESTE
// ==========================================
echo '<h2>6️⃣ Teste de Envio de Mensagem</h2>';
echo '<div class="card">';

$autoDetectedInstance = null;
$autoDetectedApiKey   = null;

// Auto-detectar instância (pegar primeira conectada)
if (!empty($usersWithInstance)) {
    $firstUser = $usersWithInstance[0];
    $autoDetectedInstance = $instanceOverride ?: $firstUser['evolution_instance'];
    try {
        $stmt3 = $pdo->prepare("SELECT evolution_token FROM users WHERE id = ?");
        $stmt3->execute([$firstUser['id']]);
        $fu = $stmt3->fetch(PDO::FETCH_ASSOC);
        $autoDetectedApiKey = !empty($fu['evolution_token']) ? $fu['evolution_token'] : $globalApiKey;
    } catch (Exception $e) {
        $autoDetectedApiKey = $globalApiKey;
    }
}

if (empty($testPhone)) {
    echo "<div class='alert alert-warn'>ℹ️ Informe um número no campo <strong>Número</strong> acima para testar o envio.</div>";
} elseif (empty($autoDetectedInstance)) {
    echo "<div class='alert alert-err'>❌ Nenhuma instância disponível para envio. Configure uma instância primeiro.</div>";
} else {
    $phoneFormatted = preg_replace('/[^0-9]/', '', $testPhone);
    if (strlen($phoneFormatted) < 12) {
        $phoneFormatted = '55' . $phoneFormatted;
    }

    $sendPayload = ['number' => $phoneFormatted, 'text' => $testMessage];
    $sendUrl     = $evolutionUrl . '/message/sendText/' . $autoDetectedInstance;

    echo "<table>";
    echo "<tr><th>Campo</th><th>Valor</th></tr>";
    echo "<tr><td>Instância</td><td><strong>$autoDetectedInstance</strong></td></tr>";
    echo "<tr><td>Número formatado</td><td><code>$phoneFormatted</code></td></tr>";
    echo "<tr><td>URL</td><td><code>$sendUrl</code></td></tr>";
    echo "<tr><td>API Key usada</td><td><code>" . substr($autoDetectedApiKey, 0, 8) . "...</code></td></tr>";
    echo "<tr><td>Mensagem</td><td>" . htmlspecialchars($testMessage) . "</td></tr>";
    echo "</table>";

    if ($doSend) {
        echo "<br><strong>📤 Enviando mensagem...</strong><br>";

        $ch = curl_init($sendUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($sendPayload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'apikey: ' . $autoDetectedApiKey],
        ]);
        $sendResponse = curl_exec($ch);
        $sendCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $sendError    = curl_error($ch);
        $sendTime     = round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000, 1);
        curl_close($ch);

        if ($sendError) {
            echo "<div class='alert alert-err'>❌ <strong>Erro cURL:</strong> $sendError</div>";
        } elseif ($sendCode >= 200 && $sendCode < 300) {
            $rData = json_decode($sendResponse, true);
            $msgId = $rData['key']['id'] ?? $rData['messageId'] ?? 'Não retornado';
            echo "<div class='alert alert-ok'>✅ <strong>Mensagem enviada com sucesso!</strong> HTTP $sendCode em {$sendTime}ms</div>";
            echo "<table>";
            echo "<tr><td>Message ID</td><td><code>$msgId</code></td></tr>";
            echo "<tr><td>Timestamp</td><td><code>" . ($rData['messageTimestamp'] ?? '-') . "</code></td></tr>";
            echo "<tr><td>Status</td><td><code>" . ($rData['status'] ?? '-') . "</code></td></tr>";
            echo "</table>";
            echo "<p><strong>Resposta completa:</strong></p><pre>" . json_encode($rData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        } else {
            echo "<div class='alert alert-err'>❌ <strong>Falha no envio!</strong> HTTP $sendCode</div>";
            $errData = json_decode($sendResponse, true);

            // Diagnóstico de erros comuns
            $errMsg = $errData['message'] ?? $errData['error'] ?? $errData['response']['message'] ?? $sendResponse;
            echo "<div class='alert alert-warn'><strong>Erro:</strong> " . htmlspecialchars(is_string($errMsg) ? $errMsg : json_encode($errMsg)) . "</div>";

            echo "<p class='warn'>Causas comuns:</p><ul>";
            if ($sendCode == 401) echo "<li>❌ API Key incorreta — verifique <code>evolution_token</code> do usuário ou <code>EVOLUTION_API_KEY</code></li>";
            if ($sendCode == 404) echo "<li>❌ Instância '$autoDetectedInstance' não encontrada na Evolution API</li>";
            if ($sendCode == 400) echo "<li>❌ Dados inválidos (número mal formatado, instância desconectada, ou campo 'text' ausente)</li>";
            if ($sendCode >= 500) echo "<li>❌ Erro interno da Evolution API — verifique os logs do servidor da Evolution</li>";
            echo "<li>ℹ️ Instância não conectada ao WhatsApp (estado diferente de 'open')</li>";
            echo "</ul>";
            echo "<p><strong>Resposta da API:</strong></p><pre>" . htmlspecialchars($sendResponse) . "</pre>";
        }
    } else {
        echo "<div class='alert alert-warn'>ℹ️ Clique em <strong>📤 Enviar Mensagem</strong> para realizar o envio de teste.</div>";
    }
}
echo '</div>';

// ==========================================
// 7. ÚLTIMAS MENSAGENS NO BANCO (chat_messages)
// ==========================================
echo '<h2>7️⃣ Últimas Mensagens no Banco (chat_messages)</h2>';
echo '<div class="card">';
try {
    // Verificar se tabela existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'chat_messages'");
    if ($stmt->rowCount() === 0) {
        echo "<div class='alert alert-err'>❌ Tabela <code>chat_messages</code> não existe!</div>";
    } else {
        $stmt = $pdo->query("
            SELECT cm.id, cm.conversation_id, cm.message_id, cm.from_me, 
                   cm.message_type, LEFT(cm.message_text, 80) as msg_preview,
                   cm.status, FROM_UNIXTIME(cm.timestamp) as dt,
                   cc.phone, u.name as user_name
            FROM chat_messages cm
            LEFT JOIN chat_conversations cc ON cc.id = cm.conversation_id
            LEFT JOIN users u ON u.id = cm.user_id
            ORDER BY cm.id DESC
            LIMIT 20
        ");
        $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($msgs)) {
            echo "<div class='alert alert-warn'>⚠️ Nenhuma mensagem encontrada em <code>chat_messages</code></div>";
        } else {
            echo "<p style='color:#8b949e;font-size:12px'>Últimas 20 mensagens:</p>";
            echo "<table><tr><th>ID</th><th>Conversa</th><th>De mim?</th><th>Tipo</th><th>Mensagem</th><th>Status</th><th>Data</th><th>Usuário</th></tr>";
            foreach ($msgs as $m) {
                $fromMeLabel = $m['from_me'] ? "<span class='ok'>→ Eu</span>" : "<span class='info'>← Contato</span>";
                $statusClass = match($m['status']) {
                    'read'      => 'ok',
                    'delivered' => 'info',
                    'sent'      => 'info',
                    'failed'    => 'err',
                    default     => 'warn'
                };
                echo "<tr>
                    <td>{$m['id']}</td>
                    <td><span title='Phone: {$m['phone']}'>{$m['conversation_id']}</span></td>
                    <td>$fromMeLabel</td>
                    <td>{$m['message_type']}</td>
                    <td>" . htmlspecialchars($m['msg_preview'] ?? '') . "</td>
                    <td><span class='{$statusClass}'>{$m['status']}</span></td>
                    <td style='white-space:nowrap'>{$m['dt']}</td>
                    <td style='font-size:11px'>{$m['user_name']}</td>
                </tr>";
            }
            echo "</table>";
        }
    }
} catch (Exception $e) {
    echo "<div class='alert alert-err'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</div>";
}
echo '</div>';

// ==========================================
// 8. LOGS DO WEBHOOK (RECEBIMENTO)
// ==========================================
echo '<h2>8️⃣ Logs de Recebimento (Webhook)</h2>';
echo '<div class="card">';
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'chat_webhook_logs'");
    if ($stmt->rowCount() === 0) {
        echo "<div class='alert alert-warn'>⚠️ Tabela <code>chat_webhook_logs</code> não existe — webhooks não estão sendo registrados</div>";
    } else {
        // Contagem por tipo de evento
        $stmt = $pdo->query("
            SELECT event_type, COUNT(*) as total, 
                   SUM(CASE WHEN processed = 1 THEN 1 ELSE 0 END) as processed,
                   SUM(CASE WHEN processed = 0 THEN 1 ELSE 0 END) as pending,
                   MAX(created_at) as last_received
            FROM chat_webhook_logs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY event_type
            ORDER BY total DESC
        ");
        $wStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($wStats)) {
            echo "<p style='color:#8b949e;font-size:12px'>Estatísticas das últimas 24h:</p>";
            echo "<table><tr><th>Evento</th><th>Total</th><th>Processados</th><th>Pendentes</th><th>Último recebido</th></tr>";
            foreach ($wStats as $ws) {
                $pendClass = $ws['pending'] > 0 ? 'err' : 'ok';
                echo "<tr>
                    <td><strong>{$ws['event_type']}</strong></td>
                    <td>{$ws['total']}</td>
                    <td><span class='ok'>{$ws['processed']}</span></td>
                    <td><span class='$pendClass'>{$ws['pending']}</span></td>
                    <td style='font-size:11px'>{$ws['last_received']}</td>
                </tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='alert alert-warn'>⚠️ Nenhum webhook recebido nas últimas 24h</div>";
            echo "<p>Verifique se o webhook está configurado na Evolution API apontando para:</p>";
            echo "<div class='url-box'><code>" . (SITE_URL) . "/api/chat_webhook.php</code></div>";
        }

        if ($showWebhook) {
            // Últimos 10 webhooks completos
            $stmt = $pdo->query("
                SELECT id, event_type, instance_name, phone, processed, error_message, created_at,
                       LEFT(payload, 500) as payload_preview
                FROM chat_webhook_logs
                ORDER BY id DESC
                LIMIT 10
            ");
            $webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "<br><strong>Últimos 10 webhooks recebidos:</strong>";
            foreach ($webhooks as $wh) {
                $procClass = $wh['processed'] ? 'ok' : 'err';
                echo "<div style='border:1px solid #30363d;border-radius:6px;padding:10px;margin-top:10px;'>";
                echo "<div style='display:flex;justify-content:space-between;'>";
                echo "<div><strong>#{$wh['id']}</strong> — <code>{$wh['event_type']}</code> | Instância: <code>{$wh['instance_name']}</code> | Fone: <code>{$wh['phone']}</code></div>";
                echo "<div><span class='$procClass'>" . ($wh['processed'] ? '✅ Processado' : '❌ Não processado') . "</span></div>";
                echo "</div>";
                echo "<div style='color:#8b949e;font-size:11px;margin-top:4px'>{$wh['created_at']}</div>";
                if ($wh['error_message']) {
                    echo "<div class='alert alert-err' style='margin-top:6px;font-size:12px'>Erro: {$wh['error_message']}</div>";
                }
                echo "<details><summary style='cursor:pointer;color:#79c0ff;font-size:12px;margin-top:6px'>Ver payload</summary><pre style='font-size:11px'>" . htmlspecialchars($wh['payload_preview']) . "...</pre></details>";
                echo "</div>";
            }
        }
    }
} catch (Exception $e) {
    echo "<div class='alert alert-err'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</div>";
}
echo '</div>';

// ==========================================
// 9. VERIFICAR CONFIGURAÇÃO DO WEBHOOK NA EVOLUTION API
// ==========================================
echo '<h2>9️⃣ Configuração de Webhook na Evolution API</h2>';
echo '<div class="card">';

if (!empty($usersWithInstance)) {
    foreach ($usersWithInstance as $u) {
        $instName = $u['evolution_instance'];
        try {
            $stmt4 = $pdo->prepare("SELECT evolution_token FROM users WHERE id = ?");
            $stmt4->execute([$u['id']]);
            $fu4 = $stmt4->fetch(PDO::FETCH_ASSOC);
            $apiKey4 = !empty($fu4['evolution_token']) ? $fu4['evolution_token'] : $globalApiKey;
        } catch (Exception $e) {
            $apiKey4 = $globalApiKey;
        }

        $ch = curl_init($evolutionUrl . '/webhook/find/' . $instName);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'apikey: ' . $apiKey4],
        ]);
        $whResp = curl_exec($ch);
        $whCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $whErr  = curl_error($ch);
        curl_close($ch);

        echo "<div style='padding:10px 0;border-bottom:1px solid #21262d;'>";
        echo "<strong>Instância: $instName</strong><br>";
        
        if ($whErr) {
            echo "<span class='err'>❌ Erro: $whErr</span>";
        } elseif ($whCode === 200) {
            $whData = json_decode($whResp, true);
            $whUrl     = $whData['webhook']['url'] ?? $whData['url'] ?? 'Não configurada';
            $whEnabled = $whData['webhook']['enabled'] ?? $whData['enabled'] ?? false;
            $whEvents  = $whData['webhook']['events'] ?? $whData['events'] ?? [];
            
            $urlClass   = !empty($whUrl) && $whUrl !== 'Não configurada' ? 'ok' : 'err';
            $enabClass  = $whEnabled ? 'ok' : 'err';
            
            echo "<table style='margin-top:8px;'>";
            echo "<tr><td style='width:150px'>URL do Webhook:</td><td><span class='$urlClass'><code>$whUrl</code></span></td></tr>";
            echo "<tr><td>Habilitado:</td><td><span class='$enabClass'>" . ($whEnabled ? '✅ Sim' : '❌ Não') . "</span></td></tr>";
            echo "<tr><td>Eventos:</td><td><code>" . implode(', ', is_array($whEvents) ? $whEvents : []) . "</code></td></tr>";
            echo "</table>";
            
            // Verificar se a URL aponta para este servidor
            $expectedUrl = SITE_URL . '/api/chat_webhook.php';
            if (strpos($whUrl, 'chat_webhook.php') === false) {
                echo "<div class='alert alert-err' style='margin-top:8px'>❌ <strong>Webhook URL incorreta!</strong><br>
                    Esperado: <code>$expectedUrl</code><br>
                    Atual: <code>$whUrl</code><br>
                    Configure o webhook em <strong>Minha Instância → Configurar Webhook</strong>
                </div>";
            } elseif (!$whEnabled) {
                echo "<div class='alert alert-warn' style='margin-top:8px'>⚠️ Webhook está desabilitado! Habilite para receber mensagens.</div>";
            } else {
                echo "<div class='alert alert-ok' style='margin-top:8px'>✅ Webhook configurado corretamente!</div>";
            }
        } else {
            echo "<span class='warn'>⚠️ HTTP $whCode</span> <pre style='font-size:11px'>" . htmlspecialchars($whResp) . "</pre>";
        }
        echo "</div>";
    }
} else {
    echo "<div class='alert alert-warn'>⚠️ Nenhuma instância configurada para verificar webhook</div>";
    echo "<div class='url-box'>Webhook deve ser configurado em: <code>" . SITE_URL . "/api/chat_webhook.php</code></div>";
}
echo '</div>';

// ==========================================
// 10. ÚLTIMAS CONVERSAS
// ==========================================
echo '<h2>🔟 Últimas Conversas Ativas</h2>';
echo '<div class="card">';
try {
    $stmt = $pdo->query("
        SELECT cc.id, cc.phone, cc.contact_name, cc.instance_name, cc.unread_count,
               cc.last_message_time, cc.last_message_text, u.name as user_name
        FROM chat_conversations cc
        LEFT JOIN users u ON u.id = cc.user_id
        ORDER BY cc.last_message_time DESC
        LIMIT 15
    ");
    $convs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($convs)) {
        echo "<div class='alert alert-warn'>⚠️ Nenhuma conversa encontrada</div>";
    } else {
        echo "<table><tr><th>ID</th><th>Telefone</th><th>Contato</th><th>Instância</th><th>Não lidas</th><th>Última msg</th><th>Usuário</th></tr>";
        foreach ($convs as $c) {
            $unreadClass = $c['unread_count'] > 0 ? 'warn' : 'ok';
            echo "<tr>
                <td>{$c['id']}</td>
                <td>{$c['phone']}</td>
                <td>" . htmlspecialchars($c['contact_name'] ?? '-') . "</td>
                <td><code>{$c['instance_name']}</code></td>
                <td><span class='$unreadClass'>{$c['unread_count']}</span></td>
                <td style='font-size:11px'>" . htmlspecialchars(substr($c['last_message_text'] ?? '', 0, 40)) . "<br>{$c['last_message_time']}</td>
                <td style='font-size:11px'>{$c['user_name']}</td>
            </tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<div class='alert alert-err'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</div>";
}
echo '</div>';

// ==========================================
// 11. RESUMO E DIAGNÓSTICO FINAL
// ==========================================
echo '<hr class="section-divider">';
echo '<h2>📊 Resumo do Diagnóstico</h2>';
echo '<div class="card">';

$problems = [];
$warnings  = [];

if ($connError) $problems[] = "Não foi possível conectar à Evolution API: $connError";
if ($instancesCode === 401) $problems[] = "API Key global inválida (HTTP 401)";
if ($instancesCode >= 500) $problems[] = "Evolution API com erro interno (HTTP $instancesCode)";
if (empty($usersWithInstance)) $warnings[] = "Nenhum usuário tem instância Evolution configurada no banco";

// Verificar instâncias desconectadas (feito acima, mas resumir)
// ...

if (empty($problems) && empty($warnings)) {
    echo "<div class='alert alert-ok'>
        <strong style='font-size:16px'>✅ Configuração parece OK!</strong><br>
        Se ainda há problemas de envio/recebimento, verifique:<br>
        <ul>
            <li>Se o webhook está apontando para a URL correta</li>
            <li>Se a instância está com estado 'open' (WhatsApp conectado)</li>
            <li>Os logs de erro do PHP em <code>/logs/</code></li>
        </ul>
    </div>";
} else {
    if (!empty($problems)) {
        echo "<div class='alert alert-err'><strong>❌ Problemas encontrados:</strong><ul>";
        foreach ($problems as $p) echo "<li>$p</li>";
        echo "</ul></div>";
    }
    if (!empty($warnings)) {
        echo "<div class='alert alert-warn'><strong>⚠️ Atenção:</strong><ul>";
        foreach ($warnings as $w) echo "<li>$w</li>";
        echo "</ul></div>";
    }
}

echo "<p style='color:#8b949e;font-size:12px;margin-top:20px'>
    ⚠️ <strong>Segurança:</strong> Remova este arquivo após o diagnóstico: 
    <code>test_evolution_messages.php</code>
</p>";
echo '</div>';

echo '</div><!-- /container -->';
?>
</body>
</html>
