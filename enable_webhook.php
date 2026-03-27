<?php
/**
 * Habilitar Webhook da Evolution API (Server-Side)
 * ATENÇÃO: Remova este arquivo após usar!
 */
require_once 'config/database.php';

$evolutionUrl = EVOLUTION_API_URL;
$globalApiKey = EVOLUTION_API_KEY;
$correctWebhookUrl = 'https://wats.macip.com.br/api/chat_webhook.php';

$action  = $_POST['action'] ?? '';
$result  = null;

// ============================================================
// AÇÕES
// ============================================================
if ($action === 'enable') {
    $instance = $_POST['instance'] ?? '';
    $apiKey   = $_POST['apikey']   ?? $globalApiKey;
    $wUrl     = $_POST['wurl']     ?? $correctWebhookUrl;

    $payload = [
        'url'              => $wUrl,
        'enabled'          => true,
        'webhook_by_events'=> false,
        'webhook_base64'   => false,
        'events'           => [
            'MESSAGES_UPSERT',
            'MESSAGES_UPDATE',
            'MESSAGES_DELETE',
            'SEND_MESSAGE',
            'CONNECTION_UPDATE',
            'QRCODE_UPDATED'
        ]
    ];

    $ch = curl_init($evolutionUrl . '/webhook/set/' . $instance);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'apikey: ' . $apiKey
        ],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    $result = [
        'type'     => 'enable',
        'code'     => $code,
        'error'    => $err,
        'body'     => json_decode($resp, true) ?? $resp,
        'instance' => $instance,
        'wurl'     => $wUrl,
        'success'  => !$err && $code >= 200 && $code < 300,
    ];

} elseif ($action === 'test_wh') {
    // Testa se o endpoint do webhook está respondendo (chamada server-side)
    $ch = curl_init($correctWebhookUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    $result = ['type'=>'test_wh','code'=>$code,'error'=>$err,'body'=>$resp,'success'=>!$err && $code>0];

} elseif ($action === 'simulate') {
    // Simula uma mensagem de recebimento diretamente no webhook
    $instance = $_POST['instance'] ?? '';
    $phone    = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
    if (!$phone) $phone = '554399962354';

    $payload = [
        'event'    => 'messages.upsert',
        'instance' => $instance,
        'data'     => [
            'key' => [
                'remoteJid' => $phone . '@s.whatsapp.net',
                'fromMe'    => false,
                'id'        => 'TEST_' . strtoupper(bin2hex(random_bytes(8))),
            ],
            'pushName'         => 'Teste Simulado',
            'message'          => ['conversation' => '✅ Mensagem de teste simulada via script PHP - ' . date('H:i:s')],
            'messageTimestamp' => time(),
            'messageType'      => 'conversation',
        ]
    ];

    $ch = curl_init($correctWebhookUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    $result = ['type'=>'simulate','code'=>$code,'error'=>$err,'body'=>json_decode($resp,true)??$resp,'success'=>!$err&&$code===200,'phone'=>$phone,'instance'=>$instance];
}

// ============================================================
// Buscar instâncias do banco
// ============================================================
$stmt = $pdo->query("SELECT id, name, evolution_instance, evolution_token FROM users WHERE evolution_instance IS NOT NULL AND evolution_instance != ''");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Habilitar Webhook</title>
<style>
* { box-sizing: border-box; }
body { font-family:'Segoe UI',Arial,sans-serif; background:#0f1117; color:#e0e0e0; margin:0; }
.c  { max-width:860px; margin:30px auto; padding:20px; }
h1  { color:#58a6ff; font-size:22px; border-bottom:1px solid #21262d; padding-bottom:10px; }
h3  { color:#79c0ff; margin:0 0 12px; font-size:15px; }
.card { background:#161b22; border:1px solid #21262d; border-radius:8px; padding:20px; margin:15px 0; }
.ok  { color:#3fb950; font-weight:bold; }
.err { color:#f85149; font-weight:bold; }
.warn{ color:#d29922; font-weight:bold; }
.a-ok   { background:#0d4a2333; border-left:4px solid #3fb950; padding:12px 16px; border-radius:4px; margin:8px 0; }
.a-err  { background:#4a0d0d33; border-left:4px solid #f85149; padding:12px 16px; border-radius:4px; margin:8px 0; }
.a-warn { background:#4a3a0d33; border-left:4px solid #d29922; padding:12px 16px; border-radius:4px; margin:8px 0; }
pre { background:#0d1117; border:1px solid #21262d; border-radius:4px; padding:12px; font-size:12px; overflow-x:auto; white-space:pre-wrap; word-break:break-all; }
input,select { background:#0d1117; border:1px solid #30363d; color:#e0e0e0; padding:8px 10px; border-radius:4px; width:100%; margin-bottom:8px; font-size:13px; }
label { display:block; color:#8b949e; font-size:11px; margin-bottom:3px; }
.btn { padding:9px 18px; border-radius:6px; font-weight:600; font-size:13px; cursor:pointer; border:none; margin:4px 4px 4px 0; color:white; }
.btn-g { background:#238636; } .btn-b { background:#1f6feb; } .btn-o { background:#b45309; }
.row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
</style>
</head>
<body><div class="c">
<h1>🔗 Habilitar Webhook da Evolution API</h1>
<p style="color:#8b949e;font-size:13px">
    URL do Evolution API (interna): <code><?= htmlspecialchars($evolutionUrl) ?></code><br>
    URL do Webhook (WATS): <code><?= htmlspecialchars($correctWebhookUrl) ?></code>
</p>

<?php if ($result): ?>
<div class="card">
    <h3>📋 Resultado da Ação</h3>
    <?php if ($result['success']): ?>
        <div class="a-ok">✅ <strong>Sucesso!</strong> HTTP <?= $result['code'] ?></div>
    <?php else: ?>
        <div class="a-err">❌ <strong>Falha!</strong> HTTP <?= $result['code'] ?><?= $result['error'] ? " — {$result['error']}" : '' ?></div>
    <?php endif; ?>

    <?php if ($result['type'] === 'enable' && $result['success']): ?>
        <div class="a-ok" style="margin-top:8px">
            ✅ Webhook habilitado para instância <strong><?= htmlspecialchars($result['instance']) ?></strong><br>
            Apontando para: <code><?= htmlspecialchars($result['wurl']) ?></code><br><br>
            Agora envie uma mensagem WhatsApp para o número da instância e em seguida acesse o diagnóstico para verificar se os webhooks estão chegando.
        </div>
    <?php elseif ($result['type'] === 'simulate' && $result['success']): ?>
        <div class="a-ok" style="margin-top:8px">
            ✅ Mensagem simulada processada com sucesso!<br>
            Verifique o chat — a mensagem de teste deve aparecer nas conversas do usuário com a instância <strong><?= htmlspecialchars($result['instance']) ?></strong>.
        </div>
    <?php elseif ($result['type'] === 'test_wh'): ?>
        <?php if ($result['success']): ?>
            <div class="a-ok">✅ Endpoint do webhook está respondendo (HTTP <?= $result['code'] ?>)</div>
        <?php else: ?>
            <div class="a-err">❌ Endpoint do webhook não acessível: <?= htmlspecialchars($result['error'] ?: 'HTTP ' . $result['code']) ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <details style="margin-top:10px"><summary style="cursor:pointer;color:#58a6ff;font-size:12px">Ver resposta completa da API</summary>
        <pre><?= htmlspecialchars(json_encode($result['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
    </details>
</div>
<?php endif; ?>

<!-- PASSO 1: TESTAR WEBHOOK -->
<div class="card">
    <h3>1️⃣ Verificar se o Endpoint Responde</h3>
    <p style="color:#8b949e;font-size:13px;margin:0 0 12px">Antes de habilitar, confirme que o endpoint do webhook está acessível pelo servidor PHP:</p>
    <form method="POST">
        <input type="hidden" name="action" value="test_wh">
        <button type="submit" class="btn btn-b">🔍 Testar Endpoint do Webhook</button>
    </form>
</div>

<!-- PASSO 2: HABILITAR WEBHOOK -->
<div class="card">
    <h3>2️⃣ Habilitar Webhook na Instância</h3>
    <form method="POST">
        <input type="hidden" name="action" value="enable">
        <div class="row">
            <div>
                <label>Instância</label>
                <select name="instance">
                    <?php foreach ($users as $u): ?>
                        <option value="<?= htmlspecialchars($u['evolution_instance']) ?>">
                            <?= htmlspecialchars($u['evolution_instance']) ?> (<?= htmlspecialchars($u['name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>API Key da instância (ou global)</label>
                <input type="text" name="apikey" value="<?= htmlspecialchars($globalApiKey) ?>">
            </div>
        </div>
        <label>URL do Webhook (que a Evolution API vai chamar)</label>
        <input type="text" name="wurl" value="<?= htmlspecialchars($correctWebhookUrl) ?>">
        <button type="submit" class="btn btn-g">✅ Habilitar Webhook</button>
    </form>
</div>

<!-- PASSO 3: SIMULAR MENSAGEM -->
<div class="card">
    <h3>3️⃣ Simular Recebimento de Mensagem</h3>
    <p style="color:#8b949e;font-size:13px;margin:0 0 12px">
        Após habilitar, use este botão para simular uma mensagem recebida e confirmar que o processamento funciona ponta a ponta.
        Se aparecer no chat, o recebimento está funcionando.
    </p>
    <form method="POST">
        <input type="hidden" name="action" value="simulate">
        <div class="row">
            <div>
                <label>Instância</label>
                <select name="instance">
                    <?php foreach ($users as $u): ?>
                        <option value="<?= htmlspecialchars($u['evolution_instance']) ?>">
                            <?= htmlspecialchars($u['evolution_instance']) ?> (<?= htmlspecialchars($u['name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Número do "Remetente" (simulado)</label>
                <input type="text" name="phone" value="554399962354" placeholder="554399999999">
            </div>
        </div>
        <button type="submit" class="btn btn-o">📨 Simular Mensagem Recebida</button>
    </form>
</div>

<!-- ÚLTIMOS WEBHOOKS RECEBIDOS -->
<div class="card">
    <h3>📊 Últimos Webhooks Recebidos (últimas 2h)</h3>
    <?php
    try {
        $wStmt = $pdo->query("SELECT event_type, COUNT(*) as n, SUM(processed) as proc, MAX(created_at) as last FROM chat_webhook_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR) GROUP BY event_type ORDER BY last DESC");
        $wRows = $wStmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($wRows)) {
            echo "<div class='a-warn'>⚠️ Nenhum webhook recebido nas últimas 2 horas</div>";
        } else {
            echo "<table style='width:100%;border-collapse:collapse;font-size:13px'><tr style='color:#8b949e'><th style='text-align:left;padding:6px'>Evento</th><th>Total</th><th>Processados</th><th>Último</th></tr>";
            foreach ($wRows as $wr) {
                echo "<tr><td style='padding:6px'>{$wr['event_type']}</td><td style='padding:6px;text-align:center'>{$wr['n']}</td><td style='padding:6px;text-align:center;color:#3fb950'>{$wr['proc']}</td><td style='padding:6px;font-size:11px'>{$wr['last']}</td></tr>";
            }
            echo "</table>";
        }
    } catch (Exception $e) {
        echo "<div class='a-err'>Erro: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    ?>
</div>

<p style="color:#6b7280;font-size:11px;margin-top:20px">⚠️ Remova este arquivo após usar: <code>enable_webhook.php</code></p>
</div></body></html>
