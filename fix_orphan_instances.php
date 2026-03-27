<?php
/**
 * Corrige instâncias órfãs no banco de dados
 * (instâncias que existem no banco mas foram deletadas na Evolution API)
 *
 * ATENÇÃO: Remova este arquivo após executar!
 */

require_once 'config/database.php';

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Corrigir Instâncias Órfãs</title>
<style>
body { font-family: 'Segoe UI', Arial, sans-serif; background: #0f1117; color: #e0e0e0; margin: 0; }
.c  { max-width: 800px; margin: 40px auto; padding: 20px; }
h2  { color: #58a6ff; border-bottom: 1px solid #21262d; padding-bottom: 10px; }
.card { background: #161b22; border: 1px solid #21262d; border-radius: 8px; padding: 20px; margin: 15px 0; }
.ok  { color: #3fb950; } .err { color: #f85149; } .warn { color: #d29922; }
.alert-ok   { background:#0d4a2333; border-left: 4px solid #3fb950; padding:12px; border-radius:4px; margin:8px 0; }
.alert-err  { background:#4a0d0d33; border-left: 4px solid #f85149; padding:12px; border-radius:4px; margin:8px 0; }
.alert-warn { background:#4a3a0d33; border-left: 4px solid #d29922; padding:12px; border-radius:4px; margin:8px 0; }
table { width:100%; border-collapse:collapse; margin-top:10px; font-size:13px; }
th { background:#21262d; padding:8px 12px; text-align:left; color:#8b949e; }
td { padding:8px 12px; border-bottom:1px solid #21262d; }
pre { background:#0d1117; border:1px solid #21262d; border-radius:4px; padding:10px; font-size:12px; overflow-x:auto; }
.btn { display:inline-block; padding:8px 18px; border-radius:6px; text-decoration:none; font-weight:600; font-size:13px; cursor:pointer; border:none; margin:4px; }
.btn-danger { background:#da3633; color:white; }
.btn-primary { background:#1f6feb; color:white; }
</style>
</head>
<body><div class="c">
<h2>🔧 Correção de Instâncias Órfãs</h2>
<p style="color:#8b949e">Verifica quais usuários têm instâncias no banco que não existem mais na Evolution API e corrige automaticamente.</p>

<?php

$doFix = isset($_POST['fix']);
$globalApiKey = EVOLUTION_API_KEY;
$evolutionUrl = EVOLUTION_API_URL;

// Buscar todos os usuários com instância configurada
$stmt = $pdo->query("SELECT id, name, email, evolution_instance, evolution_token FROM users WHERE evolution_instance IS NOT NULL AND evolution_instance != ''");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<div class='card'>";
echo "<h3 style='color:#79c0ff;margin-top:0'>📋 Usuários com Instância no Banco</h3>";

if (empty($users)) {
    echo "<div class='alert-warn'>⚠️ Nenhum usuário com instância configurada.</div>";
    echo "</div></div></body></html>";
    exit;
}

$orphans = []; // Instâncias que existem no banco mas não na Evolution API
$ok      = []; // Instâncias OK

echo "<table><tr><th>ID</th><th>Nome</th><th>Instância</th><th>Status na Evolution API</th></tr>";

foreach ($users as $u) {
    $instName = $u['evolution_instance'];
    $apiKey   = !empty($u['evolution_token']) ? $u['evolution_token'] : $globalApiKey;

    // Verificar se existe na Evolution API
    $ch = curl_init($evolutionUrl . '/instance/connectionState/' . $instName);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'apikey: ' . $apiKey],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($code === 404) {
        $orphans[] = $u;
        $statusHtml = "<span class='err'>❌ NÃO EXISTE (404) — Órfã!</span>";
    } elseif ($err) {
        $statusHtml = "<span class='warn'>⚠️ Erro de conexão: $err</span>";
    } elseif ($code === 200) {
        $data  = json_decode($resp, true);
        $state = $data['instance']['state'] ?? 'unknown';
        $ok[]  = $u;
        $stateClass = strtolower($state) === 'open' ? 'ok' : 'warn';
        $statusHtml = "<span class='$stateClass'>✅ Existe — Estado: $state</span>";
    } else {
        $statusHtml = "<span class='warn'>⚠️ HTTP $code</span>";
    }

    echo "<tr>
        <td>{$u['id']}</td>
        <td>{$u['name']}</td>
        <td><strong>$instName</strong></td>
        <td>$statusHtml</td>
    </tr>";
}
echo "</table>";
echo "</div>";

// =============================
// INSTÂNCIAS ÓRFÃS
// =============================
if (empty($orphans)) {
    echo "<div class='alert-ok'>✅ <strong>Nenhuma instância órfã encontrada!</strong> Todos os usuários têm instâncias válidas.</div>";
} else {
    echo "<div class='card'>";
    echo "<h3 style='color:#f85149;margin-top:0'>❌ Instâncias Órfãs Encontradas (" . count($orphans) . ")</h3>";
    echo "<p>As seguintes instâncias existem no banco mas <strong>foram deletadas da Evolution API</strong>:</p>";
    echo "<table><tr><th>ID</th><th>Nome</th><th>Email</th><th>Instância Órfã</th></tr>";
    foreach ($orphans as $o) {
        echo "<tr>
            <td>{$o['id']}</td>
            <td>{$o['name']}</td>
            <td>{$o['email']}</td>
            <td><code>{$o['evolution_instance']}</code></td>
        </tr>";
    }
    echo "</table>";

    if (!$doFix) {
        echo "<br><form method='POST'>";
        echo "<button type='submit' name='fix' value='1' class='btn btn-danger' onclick=\"return confirm('Limpar evolution_instance e evolution_token de " . count($orphans) . " usuário(s) órfão(s)?')\">";
        echo "🗑️ Limpar Instâncias Órfãs do Banco</button>";
        echo "</form>";
    }
    echo "</div>";
}

// =============================
// EXECUTAR CORREÇÃO
// =============================
if ($doFix && !empty($orphans)) {
    echo "<div class='card'>";
    echo "<h3 style='color:#79c0ff;margin-top:0'>🔧 Executando Correção...</h3>";

    foreach ($orphans as $o) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET evolution_instance = NULL, evolution_token = NULL WHERE id = ?");
            $stmt->execute([$o['id']]);
            $rows = $stmt->rowCount();

            if ($rows > 0) {
                echo "<div class='alert-ok'>✅ <strong>{$o['name']}</strong> (ID: {$o['id']}) — instância <code>{$o['evolution_instance']}</code> removida do banco.</div>";
            } else {
                echo "<div class='alert-err'>❌ Falha ao limpar usuário ID {$o['id']}.</div>";
            }
        } catch (Exception $e) {
            echo "<div class='alert-err'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    echo "<div class='alert-ok' style='margin-top:15px'>
        <strong>✅ Correção concluída!</strong><br>
        Os usuários afetados agora podem acessar <strong>Minha Instância</strong> para criar uma nova instância WhatsApp.
    </div>";
    echo "</div>";
}

echo "<p style='color:#6b7280;font-size:12px;margin-top:30px'>⚠️ Remova este arquivo após usar: <code>fix_orphan_instances.php</code></p>";
?>
</div></body></html>
