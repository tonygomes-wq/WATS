<?php
/**
 * Habilitar Webhook da Evolution API (Server-Side)
 * ATENÇÃO: Remova este arquivo após usar!
 */
require_once 'config/database.php';

$globalApiKey = EVOLUTION_API_KEY;
$correctWebhookUrl = 'https://wats.macip.com.br/api/chat_webhook.php';

// ============================================================
// DETECTAR URL CORRETA DA EVOLUTION API DINAMICAMENTE
// Testa múltiplas possibilidades em ordem
// ============================================================
function detectEvolutionUrl(string $globalApiKey): array {
    $candidates = [];

    // 1. Tentar URL configurada no .env primeiro
    if (defined('EVOLUTION_API_URL') && !empty(EVOLUTION_API_URL)) {
        $candidates[] = EVOLUTION_API_URL;
    }

    // 2. Tentar gateway padrão Docker em portas comuns (porta 8080 = Evolution API)
    $gatewayIps = [];

    // Tentar ler a rota padrão via /proc/net/route (Linux)
    if (file_exists('/proc/net/route')) {
        $routes = file('/proc/net/route');
        foreach ($routes as $line) {
            $fields = preg_split('/\s+/', trim($line));
            if (isset($fields[1]) && $fields[1] === '00000000' && isset($fields[2])) {
                // Gateway em hex little-endian
                $hex = $fields[2];
                if ($hex !== '00000000') {
                    $ip = implode('.', array_reverse(array_map('hexdec', str_split($hex, 2))));
                    $gatewayIps[] = $ip;
                }
            }
        }
    }

    // IPs comuns de gateway Docker
    $gatewayIps = array_unique(array_merge($gatewayIps, ['172.18.0.1','172.17.0.1','172.19.0.1','172.20.0.1','172.21.0.1','10.0.0.1']));

    foreach ($gatewayIps as $ip) {
        $candidates[] = "http://{$ip}:8080";
    }
    // URL externa como último recurso
    $candidates[] = 'https://evolution.macip.com.br';

    foreach ($candidates as $url) {
        $ch = curl_init(rtrim($url, '/') . '/instance/fetchInstances');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'apikey: ' . $globalApiKey],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if (!$err && $code === 200) {
            return ['url' => $url, 'tested' => $candidates];
        }
    }

    return ['url' => EVOLUTION_API_URL, 'tested' => $candidates, 'failed' => true];
}

$detected    = detectEvolutionUrl($globalApiKey);
$evolutionUrl = $detected['url'];

$action  = $_POST['action'] ?? '';
$result  = null;

// ============================================================
// AÇÕES
// ============================================================
if ($action === 'enable') {
    $instance = $_POST['instance'] ?? '';
    $apiKey   = $_POST['apikey']   ?? $globalApiKey;
    $wUrl     = $_POST['wurl']     ?? $correctWebhookUrl;

    // Payload flat (sem wrapper)
    $payloadFlat = [
        'url'              => $wUrl,
        'enabled'          => true,
        'webhookByEvents'  => false,
        'webhookBase64'    => false,
        'events'           => [
            'MESSAGES_UPSERT',
            'MESSAGES_UPDATE',
            'MESSAGES_DELETE',
            'SEND_MESSAGE',
            'CONNECTION_UPDATE',
            'QRCODE_UPDATED'
        ]
    ];

    // Payload com wrapper "webhook"
    $payloadWrapped = ['webhook' => $payloadFlat];

    // Tentar múltiplas abordagens em ordem
    $attempts = [
        ['method' => 'PUT',  'url' => $evolutionUrl . '/webhook/set/' . $instance, 'payload' => $payloadFlat,    'desc' => 'PUT /webhook/set/ (flat)'],
        ['method' => 'POST', 'url' => $evolutionUrl . '/webhook/set/' . $instance, 'payload' => $payloadFlat,    'desc' => 'POST /webhook/set/ (flat)'],
        ['method' => 'PUT',  'url' => $evolutionUrl . '/webhook/set/' . $instance, 'payload' => $payloadWrapped, 'desc' => 'PUT /webhook/set/ (wrapper)'],
        ['method' => 'POST', 'url' => $evolutionUrl . '/webhook/set/' . $instance, 'payload' => $payloadWrapped, 'desc' => 'POST /webhook/set/ (wrapper)'],
    ];

    $allAttempts = [];
    $finalResult = null;

    foreach ($attempts as $attempt) {
        $ch = curl_init($attempt['url']);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'apikey: ' . $apiKey
            ],
            CURLOPT_POSTFIELDS     => json_encode($attempt['payload']),
        ];
        if ($attempt['method'] === 'PUT') {
            $opts[CURLOPT_CUSTOMREQUEST] = 'PUT';
        } else {
            $opts[CURLOPT_POST] = true;
        }
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        $ok = !$err && $code >= 200 && $code < 300;
        $allAttempts[] = [
            'desc'    => $attempt['desc'],
            'code'    => $code,
            'error'   => $err,
            'body'    => json_decode($resp, true) ?? $resp,
            'success' => $ok,
        ];

        if ($ok) {
            $finalResult = end($allAttempts);
            break;
        }
    }

    if (!$finalResult) {
        $finalResult = end($allAttempts); // último tentativa como resultado
    }

    $result = [
        'type'     => 'enable',
        'code'     => $finalResult['code'],
        'error'    => $finalResult['error'],
        'body'     => $finalResult['body'],
        'instance' => $instance,
        'wurl'     => $wUrl,
        'success'  => $finalResult['success'],
        'attempts' => $allAttempts,
    ];

} elseif ($action === 'test_wh') {
    // Testa via localhost (evita hairpin NAT — o PHP chama a si mesmo)
    $localUrl = 'http://localhost/api/chat_webhook.php';
    $ch = curl_init($localUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => ['Host: wats.macip.com.br'],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    $result = ['type'=>'test_wh','code'=>$code,'error'=>$err,'body'=>$resp,'url_tested'=>$localUrl,'success'=>!$err && $code>0];

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
            'source'           => 'web',
        ]
    ];
    error_log("[SIMULATE] Payload enviado para webhook: " . json_encode($payload));

    // Usar localhost para evitar hairpin NAT
    $localWebhook = 'http://localhost/api/chat_webhook.php';
    $ch = curl_init($localWebhook);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Host: wats.macip.com.br'],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    $result = ['type'=>'simulate','code'=>$code,'error'=>$err,'body'=>json_decode($resp,true)??$resp,'success'=>!$err&&$code===200,'phone'=>$phone,'instance'=>$instance,'local_url'=>$localWebhook];
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
<div style="background:#161b22;border:1px solid #21262d;border-radius:6px;padding:12px;margin-bottom:10px;font-size:13px">
    <?php if (!empty($detected['failed'])): ?>
        <span style="color:#f85149">❌ Não foi possível conectar à Evolution API em nenhuma URL testada!</span>
    <?php else: ?>
        <span style="color:#3fb950">✅ Evolution API encontrada em:</span> <code><?= htmlspecialchars($evolutionUrl) ?></code>
    <?php endif; ?>
    <br>URLs testadas: <code><?= htmlspecialchars(implode(', ', $detected['tested'] ?? [])) ?></code>
    <br>Webhook WATS: <code><?= htmlspecialchars($correctWebhookUrl) ?></code> (simulação via localhost)
</div>

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

    <?php if (!empty($result['attempts'])): ?>
    <details style="margin-top:10px" open><summary style="cursor:pointer;color:#d29922;font-size:12px;font-weight:bold">🔍 Debug: Todas as tentativas realizadas (<?= count($result['attempts']) ?>)</summary>
        <table style="width:100%;border-collapse:collapse;font-size:12px;margin-top:8px">
            <tr style="color:#8b949e"><th style="text-align:left;padding:5px">#</th><th style="text-align:left;padding:5px">Método/Endpoint</th><th style="text-align:center;padding:5px">HTTP</th><th style="text-align:center;padding:5px">Status</th><th style="text-align:left;padding:5px">Resposta</th></tr>
            <?php foreach ($result['attempts'] as $i => $att): ?>
            <tr style="border-top:1px solid #21262d">
                <td style="padding:5px"><?= $i+1 ?></td>
                <td style="padding:5px"><code><?= htmlspecialchars($att['desc']) ?></code></td>
                <td style="padding:5px;text-align:center"><?= $att['code'] ?></td>
                <td style="padding:5px;text-align:center"><?= $att['success'] ? '<span class="ok">✅</span>' : '<span class="err">❌</span>' ?></td>
                <td style="padding:5px;font-size:11px"><?= htmlspecialchars(is_array($att['body']) ? json_encode($att['body'], JSON_UNESCAPED_UNICODE) : ($att['error'] ?: $att['body'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </details>
    <?php endif; ?>
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
        $wStmt = $pdo->query("SELECT id, event_type, instance_name, phone, processed, error_message, LEFT(payload,800) as pay, created_at FROM chat_webhook_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR) ORDER BY id DESC LIMIT 10");
        $wRows = $wStmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($wRows)) {
            echo "<div class='a-warn'>⚠️ Nenhum webhook recebido nas últimas 2 horas</div>";
        } else {
            echo "<table style='width:100%;border-collapse:collapse;font-size:12px'><tr style='color:#8b949e'><th style='text-align:left;padding:5px'>ID</th><th>Evento</th><th>Instância</th><th>Phone</th><th>Processado</th><th>Erro</th><th>Hora</th></tr>";
            foreach ($wRows as $wr) {
                $pClass = $wr['processed'] ? 'ok' : 'err';
                $pIcon  = $wr['processed'] ? '✅' : '❌';
                echo "<tr>
                    <td style='padding:5px'>{$wr['id']}</td>
                    <td style='padding:5px'>{$wr['event_type']}</td>
                    <td style='padding:5px'><strong>{$wr['instance_name']}</strong></td>
                    <td style='padding:5px'>{$wr['phone']}</td>
                    <td style='padding:5px;text-align:center'><span class='{$pClass}'>{$pIcon}</span></td>
                    <td style='padding:5px;color:#f85149;font-size:11px'>" . htmlspecialchars($wr['error_message'] ?? '') . "</td>
                    <td style='padding:5px;font-size:11px'>{$wr['created_at']}</td>
                </tr>";
                if ($wr['pay']) {
                    $pay = json_decode($wr['pay'], true);
                    echo "<tr><td colspan='7' style='padding:4px 8px;'><details><summary style='cursor:pointer;color:#79c0ff;font-size:11px'>Ver payload</summary><pre style='font-size:10px;margin:4px 0'>" . htmlspecialchars(json_encode($pay, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) . "</pre></details></td></tr>";
                }
            }
            echo "</table>";
        }

        // Verificar se o problema é getUserByInstance
        echo "<br><div style='font-size:12px;color:#8b949e;border-top:1px solid #21262d;padding-top:10px;margin-top:10px'>";
        echo "<strong style='color:#79c0ff'>🔍 Diagnóstico: getUserByInstance</strong><br>";
        $u = $pdo->query("SELECT id, name, evolution_instance FROM users WHERE evolution_instance IS NOT NULL AND evolution_instance != '' LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($u as $row) {
            echo "Usuário <strong>{$row['name']}</strong> → instância: <code>{$row['evolution_instance']}</code><br>";
        }
        echo "</div>";

    } catch (Exception $e) {
        echo "<div class='a-err'>Erro: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    ?>
</div>

<p style="color:#6b7280;font-size:11px;margin-top:20px">⚠️ Remova este arquivo após usar: <code>enable_webhook.php</code></p>
</div></body></html>

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
