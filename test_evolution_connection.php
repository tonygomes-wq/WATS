<?php
/**
 * Script de Diagnóstico Evolution API
 * 
 * Testa conectividade, autenticação e criação de instância
 * MACIP Tecnologia LTDA - 2026
 */

require_once 'config/database.php';

// Estilo CSS
echo '<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
h2 { color: #2563eb; border-bottom: 3px solid #2563eb; padding-bottom: 10px; }
h3 { color: #059669; margin-top: 30px; }
.success { color: #059669; font-weight: bold; }
.error { color: #dc2626; font-weight: bold; }
.warning { color: #d97706; font-weight: bold; }
.info { background: #eff6ff; border-left: 4px solid #2563eb; padding: 15px; margin: 15px 0; }
.code { background: #1f2937; color: #10b981; padding: 15px; border-radius: 5px; overflow-x: auto; font-family: monospace; }
pre { background: #f3f4f6; padding: 15px; border-radius: 5px; overflow-x: auto; }
.badge { display: inline-block; padding: 5px 10px; border-radius: 5px; font-size: 12px; font-weight: bold; }
.badge-success { background: #d1fae5; color: #059669; }
.badge-error { background: #fee2e2; color: #dc2626; }
.badge-warning { background: #fef3c7; color: #d97706; }
hr { margin: 30px 0; border: none; border-top: 2px solid #e5e7eb; }
</style>';

echo '<div class="container">';
echo "<h2>🔍 Diagnóstico Evolution API - WATS</h2>";
echo "<p><strong>Data/Hora:</strong> " . date('d/m/Y H:i:s') . "</p>";

// 1. Verificar configurações
echo "<h3>1️⃣ Configurações Carregadas</h3>";
echo "<div class='info'>";
echo "<strong>URL Evolution API:</strong> " . EVOLUTION_API_URL . "<br>";
echo "<strong>API Key (parcial):</strong> " . substr(EVOLUTION_API_KEY, 0, 10) . "..." . substr(EVOLUTION_API_KEY, -5) . "<br>";
echo "<strong>Tamanho da Key:</strong> " . strlen(EVOLUTION_API_KEY) . " caracteres<br>";
echo "</div>";

// 2. Testar conectividade
echo "<h3>2️⃣ Teste de Conectividade</h3>";
$ch = curl_init(EVOLUTION_API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Para testes
curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "<span class='error'>❌ ERRO: Não foi possível conectar</span><br>";
    echo "<div class='code'>Detalhes: $error</div>";
    echo "<div class='info'><strong>Possíveis causas:</strong><br>";
    echo "• Evolution API não está rodando<br>";
    echo "• URL incorreta no .env<br>";
    echo "• Firewall bloqueando conexão<br>";
    echo "• Problema de DNS</div>";
} else {
    echo "<span class='success'>✅ Conectividade OK</span> <span class='badge badge-success'>HTTP $httpCode</span><br>";
}

// 3. Testar autenticação
echo "<h3>3️⃣ Teste de Autenticação</h3>";
$ch = curl_init(EVOLUTION_API_URL . '/instance/fetchInstances');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'apikey: ' . EVOLUTION_API_KEY
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "<span class='error'>❌ ERRO: $error</span><br>";
} elseif ($httpCode == 401) {
    echo "<span class='error'>❌ ERRO 401: Não Autorizado</span> <span class='badge badge-error'>API Key Incorreta</span><br>";
    echo "<div class='info'>";
    echo "<strong>🔧 SOLUÇÃO:</strong><br>";
    echo "1. Acesse o container da Evolution API no Easypanel<br>";
    echo "2. Verifique a variável <code>AUTHENTICATION_API_KEY</code> no .env<br>";
    echo "3. Compare com a API Key no .env do WATS<br>";
    echo "4. Atualize uma das duas para ficarem iguais<br>";
    echo "5. Reinicie o container após alterar<br>";
    echo "</div>";
    echo "<div class='code'>";
    echo "# Comando para verificar no container Evolution API:<br>";
    echo "docker exec -it &lt;container_evolution&gt; cat .env | grep API_KEY<br><br>";
    echo "# Ou via Easypanel:<br>";
    echo "1. Abra o serviço Evolution API<br>";
    echo "2. Vá em 'Environment Variables'<br>";
    echo "3. Procure por AUTHENTICATION_API_KEY<br>";
    echo "</div>";
} elseif ($httpCode == 200) {
    echo "<span class='success'>✅ Autenticação OK!</span> <span class='badge badge-success'>HTTP 200</span><br>";
    $data = json_decode($response, true);
    if (is_array($data) && count($data) > 0) {
        echo "<p><strong>Instâncias encontradas:</strong> " . count($data) . "</p>";
        echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "</pre>";
    } else {
        echo "<p>Nenhuma instância configurada ainda.</p>";
    }
} else {
    echo "<span class='warning'>⚠️ Resposta inesperada</span> <span class='badge badge-warning'>HTTP $httpCode</span><br>";
    echo "<pre>$response</pre>";
}

// 4. Testar criação de instância (simulação)
echo "<h3>4️⃣ Teste de Criação de Instância (Simulação)</h3>";
$testData = [
    'instanceName' => 'test_diagnostic_' . time(),
    'token' => bin2hex(random_bytes(16)),
    'qrcode' => true,
    'integration' => 'WHATSAPP-BAILEYS'
];

echo "<p><strong>Dados que seriam enviados:</strong></p>";
echo "<pre>" . json_encode($testData, JSON_PRETTY_PRINT) . "</pre>";

$ch = curl_init(EVOLUTION_API_URL . '/instance/create');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'apikey: ' . EVOLUTION_API_KEY
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "<span class='error'>❌ ERRO: $error</span><br>";
} elseif ($httpCode == 401) {
    echo "<span class='error'>❌ ERRO 401: Não autorizado</span><br>";
    echo "<div class='info'><strong>SOLUÇÃO:</strong> Corrigir API Key conforme instruções acima</div>";
} elseif ($httpCode == 201 || $httpCode == 200) {
    echo "<span class='success'>✅ Criação de instância funcionaria perfeitamente!</span> <span class='badge badge-success'>HTTP $httpCode</span><br>";
    echo "<p><strong>Resposta da API:</strong></p>";
    $responseData = json_decode($response, true);
    echo "<pre>" . json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "</pre>";
    
    // Deletar instância de teste
    echo "<p>Deletando instância de teste...</p>";
    $ch = curl_init(EVOLUTION_API_URL . '/instance/delete/' . $testData['instanceName']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . EVOLUTION_API_KEY
    ]);
    $deleteResponse = curl_exec($ch);
    $deleteHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($deleteHttpCode >= 200 && $deleteHttpCode < 300) {
        echo "<span class='success'>✅ Instância de teste deletada com sucesso</span><br>";
    } else {
        echo "<span class='warning'>⚠️ Não foi possível deletar instância de teste (HTTP $deleteHttpCode)</span><br>";
        echo "<p>Você pode deletá-la manualmente no painel da Evolution API</p>";
    }
} else {
    echo "<span class='warning'>⚠️ Resposta inesperada</span> <span class='badge badge-warning'>HTTP $httpCode</span><br>";
    echo "<pre>$response</pre>";
}

// 5. Verificar usuário atual
echo "<h3>5️⃣ Verificar Configuração do Usuário</h3>";
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT id, name, email, evolution_instance, evolution_token FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "<div class='info'>";
        echo "<strong>Usuário:</strong> " . htmlspecialchars($user['name']) . " (" . htmlspecialchars($user['email']) . ")<br>";
        echo "<strong>ID:</strong> " . $user['id'] . "<br>";
        echo "<strong>Instância configurada:</strong> " . ($user['evolution_instance'] ? htmlspecialchars($user['evolution_instance']) : '<span class="warning">Nenhuma</span>') . "<br>";
        echo "<strong>Token configurado:</strong> " . ($user['evolution_token'] ? '<span class="success">Sim</span>' : '<span class="warning">Não</span>') . "<br>";
        echo "</div>";
    }
} else {
    echo "<span class='warning'>⚠️ Usuário não está logado</span><br>";
    echo "<p>Faça login para ver suas configurações</p>";
}

// Resumo final
echo "<hr>";
echo "<h3>📝 Resumo do Diagnóstico</h3>";

$allOk = true;
$issues = [];

if ($error) {
    $allOk = false;
    $issues[] = "Problema de conectividade com Evolution API";
}

if (isset($httpCode) && $httpCode == 401) {
    $allOk = false;
    $issues[] = "API Key incorreta ou inválida";
}

if ($allOk) {
    echo "<div class='info' style='background: #d1fae5; border-left-color: #059669;'>";
    echo "<span class='success' style='font-size: 18px;'>✅ TUDO FUNCIONANDO PERFEITAMENTE!</span><br><br>";
    echo "Seu sistema está configurado corretamente e pronto para criar instâncias WhatsApp.<br>";
    echo "Você pode acessar <a href='/my_instance.php'>Configurar Minha Instância</a> e criar sua instância.";
    echo "</div>";
} else {
    echo "<div class='info' style='background: #fee2e2; border-left-color: #dc2626;'>";
    echo "<span class='error' style='font-size: 18px;'>❌ PROBLEMAS ENCONTRADOS:</span><br><br>";
    foreach ($issues as $issue) {
        echo "• $issue<br>";
    }
    echo "<br><strong>Siga as instruções acima para corrigir.</strong>";
    echo "</div>";
}

echo "<hr>";
echo "<p style='text-align: center; color: #6b7280; font-size: 12px;'>";
echo "MACIP Tecnologia LTDA - Sistema WATS<br>";
echo "Diagnóstico gerado em " . date('d/m/Y H:i:s');
echo "</p>";

echo '</div>'; // container
?>
