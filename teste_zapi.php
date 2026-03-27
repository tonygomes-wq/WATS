<?php
ob_start();
$page_title = 'Teste Z-API - Envio e Recebimento';
require_once 'includes/header_spa.php';

$user_id = $_SESSION['user_id'];

// Buscar dados Z-API do usuário
$stmt = $pdo->prepare("SELECT zapi_instance_id, zapi_token, zapi_client_token, whatsapp_provider FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

$instanceId = $userData['zapi_instance_id'] ?? '';
$token = $userData['zapi_token'] ?? '';
$clientToken = $userData['zapi_client_token'] ?? '';
$provider = $userData['whatsapp_provider'] ?? '';

$testResults = [];
$sendResult = null;
$webhookUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/api/zapi_webhook.php';

// ============================================================
// AÇÃO: Testar envio de mensagem
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'test_send') {
        $testPhone = trim($_POST['test_phone'] ?? '');
        $testMessage = trim($_POST['test_message'] ?? 'Teste WATS Z-API - ' . date('H:i:s'));
        
        if (empty($testPhone)) {
            $sendResult = ['success' => false, 'error' => 'Informe o número de telefone'];
        } else {
            $phoneFormatted = preg_replace('/[^0-9]/', '', $testPhone);
            $url = "https://api.z-api.io/instances/{$instanceId}/token/{$token}/send-text";
            
            $data = ['phone' => $phoneFormatted, 'message' => $testMessage];
            
            $headers = ['Content-Type: application/json'];
            if (!empty($clientToken)) {
                $headers[] = 'Client-Token: ' . $clientToken;
            }
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            $responseData = json_decode($response, true);
            
            $sendResult = [
                'success' => $httpCode >= 200 && $httpCode < 300 && !$curlError,
                'http_code' => $httpCode,
                'response' => $responseData,
                'raw_response' => $response,
                'curl_error' => $curlError,
                'url' => $url,
                'headers_sent' => $headers,
                'payload' => $data
            ];
        }
    }
    
    if ($_POST['action'] === 'test_webhook_simulate') {
        // Simular um webhook Z-API localmente
        // Formato INDIVIDUAL (como Z-API realmente envia nos webhooks individuais)
        $simulatePayload = [
            'phone' => trim($_POST['simulate_phone'] ?? '5511999999999'),
            'messageId' => 'test_' . uniqid(),
            'fromMe' => false,
            'timestamp' => time(),
            'senderName' => 'Teste Simulado',
            'momment' => time() * 1000,
            'text' => ['message' => $_POST['simulate_message'] ?? 'Mensagem de teste simulada - ' . date('H:i:s')]
        ];
        
        // Enviar para nosso próprio webhook
        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($simulatePayload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        $sendResult = [
            'type' => 'webhook_simulate',
            'success' => $httpCode >= 200 && $httpCode < 300 && !$curlError,
            'http_code' => $httpCode,
            'response' => json_decode($response, true),
            'raw_response' => $response,
            'curl_error' => $curlError,
            'payload_sent' => $simulatePayload
        ];
    }
    
    if ($_POST['action'] === 'check_zapi_webhook') {
        // Verificar configuração do webhook na Z-API
        $url = "https://api.z-api.io/instances/{$instanceId}/token/{$token}/webhooks";
        
        $headers = ['Content-Type: application/json'];
        if (!empty($clientToken)) {
            $headers[] = 'Client-Token: ' . $clientToken;
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        $sendResult = [
            'type' => 'webhook_check',
            'success' => $httpCode >= 200 && $httpCode < 300 && !$curlError,
            'http_code' => $httpCode,
            'response' => json_decode($response, true),
            'raw_response' => $response,
            'curl_error' => $curlError
        ];
    }
    
    if ($_POST['action'] === 'set_zapi_webhook') {
        // Configurar webhook na Z-API automaticamente
        $url = "https://api.z-api.io/instances/{$instanceId}/token/{$token}/update-webhook-received";
        
        $headers = ['Content-Type: application/json'];
        if (!empty($clientToken)) {
            $headers[] = 'Client-Token: ' . $clientToken;
        }
        
        $webhookData = ['value' => $webhookUrl];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($webhookData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        $sendResult = [
            'type' => 'webhook_set',
            'success' => $httpCode >= 200 && $httpCode < 300 && !$curlError,
            'http_code' => $httpCode,
            'response' => json_decode($response, true),
            'raw_response' => $response,
            'curl_error' => $curlError,
            'webhook_url' => $webhookUrl
        ];
    }
}

// ============================================================
// DIAGNÓSTICOS AUTOMÁTICOS
// ============================================================

// 1. Verificar provider
$testResults[] = [
    'name' => 'Provider configurado como Z-API',
    'status' => $provider === 'zapi' ? 'ok' : 'error',
    'detail' => $provider ?: '(não definido)'
];

// 2. Verificar Instance ID
$testResults[] = [
    'name' => 'Instance ID preenchido',
    'status' => !empty($instanceId) ? 'ok' : 'error',
    'detail' => !empty($instanceId) ? htmlspecialchars($instanceId) : '(vazio)'
];

// 3. Verificar Token
$testResults[] = [
    'name' => 'Token preenchido',
    'status' => !empty($token) ? 'ok' : 'error',
    'detail' => !empty($token) ? '****' . substr($token, -6) : '(vazio)'
];

// 4. Verificar Client-Token
$testResults[] = [
    'name' => 'Client-Token preenchido',
    'status' => !empty($clientToken) ? 'ok' : 'warning',
    'detail' => !empty($clientToken) ? '****' . substr($clientToken, -6) : '(vazio — pode causar erro 400)'
];

// 5. Verificar endpoint do webhook acessível
$testResults[] = [
    'name' => 'URL do Webhook (local)',
    'status' => 'info',
    'detail' => $webhookUrl
];

// 6. Verificar se o endpoint GET responde OK
$ch = curl_init($webhookUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$webhookResponse = curl_exec($ch);
$webhookHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$webhookError = curl_error($ch);
curl_close($ch);

$testResults[] = [
    'name' => 'Webhook endpoint acessível (GET)',
    'status' => $webhookHttpCode === 200 ? 'ok' : 'error',
    'detail' => $webhookError ? "Erro: $webhookError" : "HTTP $webhookHttpCode - " . ($webhookResponse ?: 'sem resposta')
];

// 7. Verificar tabela webhook_logs
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM chat_webhook_logs WHERE event_type LIKE 'zapi_%' ORDER BY id DESC");
    $logCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $testResults[] = [
        'name' => 'Webhook logs Z-API recebidos',
        'status' => $logCount > 0 ? 'ok' : 'warning',
        'detail' => "$logCount logs encontrados"
    ];
} catch (PDOException $e) {
    $testResults[] = [
        'name' => 'Tabela chat_webhook_logs',
        'status' => 'error',
        'detail' => $e->getMessage()
    ];
}

// 8. Verificar também tabela webhook_logs (sem prefixo chat_)
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM webhook_logs WHERE event_type LIKE 'zapi_%'");
    $logCount2 = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $testResults[] = [
        'name' => 'Webhook logs (tabela webhook_logs)',
        'status' => $logCount2 > 0 ? 'ok' : 'warning',
        'detail' => "$logCount2 logs encontrados"
    ];
} catch (PDOException $e) {
    // tabela pode não existir
    $testResults[] = [
        'name' => 'Tabela webhook_logs',
        'status' => 'warning',
        'detail' => 'Tabela não encontrada (normal se usa chat_webhook_logs)'
    ];
}

// 9. Verificar status da instância na Z-API
if (!empty($instanceId) && !empty($token)) {
    $statusUrl = "https://api.z-api.io/instances/{$instanceId}/token/{$token}/status";
    $headers = ['Content-Type: application/json'];
    if (!empty($clientToken)) {
        $headers[] = 'Client-Token: ' . $clientToken;
    }
    
    $ch = curl_init($statusUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $statusResponse = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $statusData = json_decode($statusResponse, true);
    $connected = $statusData['connected'] ?? $statusData['status'] ?? 'desconhecido';
    
    $testResults[] = [
        'name' => 'Status da instância Z-API',
        'status' => ($statusCode >= 200 && $statusCode < 300) ? 'ok' : 'error',
        'detail' => "HTTP $statusCode — " . (is_string($connected) ? $connected : json_encode($statusData))
    ];
}

// 10. Últimas mensagens recebidas via Z-API
$recentMessages = [];
try {
    $stmt = $pdo->prepare("
        SELECT cm.id, cm.message_text, cm.from_me, cm.message_type, cm.timestamp, cm.status,
               cc.phone, cc.contact_name, cc.instance_name
        FROM chat_messages cm
        JOIN chat_conversations cc ON cm.conversation_id = cc.id
        WHERE cc.user_id = ?
        ORDER BY cm.id DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $recentMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Ignorar
}

?>

<div class="main-content">
    <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-6 py-5">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
            <i class="fas fa-vial mr-2 text-green-600"></i>Teste Z-API — Envio e Recebimento
        </h1>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Diagnóstico completo da integração Z-API</p>
    </div>
    
    <div class="p-6 space-y-6">
        
        <!-- Resultado da ação -->
        <?php if ($sendResult): ?>
        <div class="p-4 rounded-lg border <?php echo $sendResult['success'] ? 'bg-green-50 dark:bg-green-900/20 border-green-200' : 'bg-red-50 dark:bg-red-900/20 border-red-200'; ?>">
            <h3 class="font-bold mb-2 <?php echo $sendResult['success'] ? 'text-green-800 dark:text-green-300' : 'text-red-800 dark:text-red-300'; ?>">
                <i class="fas <?php echo $sendResult['success'] ? 'fa-check-circle' : 'fa-times-circle'; ?> mr-2"></i>
                Resultado: <?php echo $sendResult['success'] ? 'Sucesso' : 'Falha'; ?>
                <?php if (isset($sendResult['http_code'])): ?> (HTTP <?php echo $sendResult['http_code']; ?>)<?php endif; ?>
            </h3>
            <?php if (!empty($sendResult['curl_error'])): ?>
                <p class="text-red-700 dark:text-red-400 text-sm"><strong>cURL Error:</strong> <?php echo htmlspecialchars($sendResult['curl_error']); ?></p>
            <?php endif; ?>
            <details class="mt-2">
                <summary class="text-sm cursor-pointer text-gray-600 dark:text-gray-400 hover:text-gray-800">Ver detalhes completos</summary>
                <pre class="mt-2 bg-gray-800 text-green-400 p-4 rounded text-xs overflow-x-auto max-h-96"><?php echo htmlspecialchars(json_encode($sendResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
            </details>
        </div>
        <?php endif; ?>
        
        <!-- Diagnósticos -->
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                <h2 class="font-semibold text-gray-800 dark:text-gray-200"><i class="fas fa-stethoscope mr-2"></i>Diagnóstico Automático</h2>
            </div>
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">Verificação</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">Status</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">Detalhe</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($testResults as $r): ?>
                    <tr class="border-t border-gray-100 dark:border-gray-700">
                        <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-200"><?php echo $r['name']; ?></td>
                        <td class="px-4 py-2">
                            <?php
                            $badge = match($r['status']) {
                                'ok' => '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800"><i class="fas fa-check mr-1"></i>OK</span>',
                                'error' => '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800"><i class="fas fa-times mr-1"></i>ERRO</span>',
                                'warning' => '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800"><i class="fas fa-exclamation mr-1"></i>AVISO</span>',
                                default => '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800"><i class="fas fa-info mr-1"></i>INFO</span>',
                            };
                            echo $badge;
                            ?>
                        </td>
                        <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 font-mono text-xs break-all"><?php echo $r['detail']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="grid md:grid-cols-2 gap-6">
            
            <!-- Teste de Envio -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                <div class="px-4 py-3 bg-blue-50 dark:bg-blue-900/20 border-b border-gray-200 dark:border-gray-600">
                    <h2 class="font-semibold text-blue-800 dark:text-blue-300"><i class="fas fa-paper-plane mr-2"></i>1. Teste de Envio</h2>
                </div>
                <form method="POST" class="p-4 space-y-3">
                    <input type="hidden" name="action" value="test_send">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Número (com DDD)</label>
                        <input type="text" name="test_phone" placeholder="5511999998888" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Mensagem</label>
                        <input type="text" name="test_message" value="Teste WATS Z-API <?php echo date('H:i:s'); ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                    </div>
                    <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
                        <i class="fas fa-paper-plane mr-2"></i>Enviar Mensagem de Teste
                    </button>
                </form>
            </div>
            
            <!-- Teste de Recebimento (simulação) -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                <div class="px-4 py-3 bg-green-50 dark:bg-green-900/20 border-b border-gray-200 dark:border-gray-600">
                    <h2 class="font-semibold text-green-800 dark:text-green-300"><i class="fas fa-inbox mr-2"></i>2. Simular Recebimento</h2>
                    <p class="text-xs text-green-600 dark:text-green-400 mt-1">Envia um payload simulado ao webhook local</p>
                </div>
                <form method="POST" class="p-4 space-y-3">
                    <input type="hidden" name="action" value="test_webhook_simulate">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Número simulado</label>
                        <input type="text" name="simulate_phone" placeholder="5511999998888" value="5511999999999" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Mensagem simulada</label>
                        <input type="text" name="simulate_message" value="Mensagem simulada <?php echo date('H:i:s'); ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                    </div>
                    <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium">
                        <i class="fas fa-inbox mr-2"></i>Simular Webhook de Recebimento
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Webhook Config na Z-API -->
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
            <div class="px-4 py-3 bg-purple-50 dark:bg-purple-900/20 border-b border-gray-200 dark:border-gray-600">
                <h2 class="font-semibold text-purple-800 dark:text-purple-300"><i class="fas fa-link mr-2"></i>3. Configuração do Webhook na Z-API</h2>
                <p class="text-xs text-purple-600 dark:text-purple-400 mt-1">O webhook precisa estar configurado no painel da Z-API apontando para este sistema</p>
            </div>
            <div class="p-4 space-y-3">
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">URL do Webhook que deve ser configurada na Z-API:</p>
                    <div class="flex items-center gap-2">
                        <code class="flex-1 bg-gray-800 text-green-400 px-3 py-2 rounded text-sm break-all"><?php echo htmlspecialchars($webhookUrl); ?></code>
                        <button onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($webhookUrl); ?>')" class="px-3 py-2 bg-gray-200 dark:bg-gray-600 rounded text-sm hover:bg-gray-300" title="Copiar">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                
                <div class="grid md:grid-cols-2 gap-3">
                    <form method="POST">
                        <input type="hidden" name="action" value="check_zapi_webhook">
                        <button type="submit" class="w-full px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm font-medium">
                            <i class="fas fa-search mr-2"></i>Verificar Webhooks Configurados
                        </button>
                    </form>
                    <form method="POST">
                        <input type="hidden" name="action" value="set_zapi_webhook">
                        <button type="submit" class="w-full px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 text-sm font-medium">
                            <i class="fas fa-cog mr-2"></i>Configurar Webhook Automaticamente
                        </button>
                    </form>
                </div>
                
                <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded p-3 mt-2">
                    <p class="text-sm text-yellow-800 dark:text-yellow-300 font-medium"><i class="fas fa-exclamation-triangle mr-1"></i> Se o botão automático não funcionar:</p>
                    <ol class="text-xs text-yellow-700 dark:text-yellow-400 list-decimal list-inside mt-1 space-y-1">
                        <li>Acesse o <strong>painel da Z-API</strong></li>
                        <li>Vá na sua instância → <strong>Webhooks</strong></li>
                        <li>No campo <strong>"Received"</strong> (ou "Recebimento"), cole a URL acima</li>
                        <li>Ative o webhook e salve</li>
                    </ol>
                </div>
            </div>
        </div>
        
        <!-- Últimas mensagens -->
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                <h2 class="font-semibold text-gray-800 dark:text-gray-200"><i class="fas fa-history mr-2"></i>4. Últimas 10 Mensagens no Sistema</h2>
            </div>
            <div class="overflow-x-auto">
                <?php if (empty($recentMessages)): ?>
                    <p class="p-4 text-sm text-gray-500 dark:text-gray-400">Nenhuma mensagem encontrada no banco de dados.</p>
                <?php else: ?>
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-3 py-2 text-left">ID</th>
                            <th class="px-3 py-2 text-left">Telefone</th>
                            <th class="px-3 py-2 text-left">Contato</th>
                            <th class="px-3 py-2 text-left">Direção</th>
                            <th class="px-3 py-2 text-left">Tipo</th>
                            <th class="px-3 py-2 text-left">Mensagem</th>
                            <th class="px-3 py-2 text-left">Status</th>
                            <th class="px-3 py-2 text-left">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentMessages as $msg): ?>
                        <tr class="border-t border-gray-100 dark:border-gray-700">
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400"><?php echo $msg['id']; ?></td>
                            <td class="px-3 py-2 font-mono text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($msg['phone'] ?? ''); ?></td>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400"><?php echo htmlspecialchars($msg['contact_name'] ?? '—'); ?></td>
                            <td class="px-3 py-2">
                                <?php if ($msg['from_me']): ?>
                                    <span class="text-blue-600">➡️ Enviada</span>
                                <?php else: ?>
                                    <span class="text-green-600">⬅️ Recebida</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 text-gray-500 dark:text-gray-400"><?php echo $msg['message_type']; ?></td>
                            <td class="px-3 py-2 text-gray-800 dark:text-gray-200 max-w-xs truncate"><?php echo htmlspecialchars(mb_substr($msg['message_text'] ?? '', 0, 60)); ?></td>
                            <td class="px-3 py-2 text-gray-500 dark:text-gray-400"><?php echo $msg['status']; ?></td>
                            <td class="px-3 py-2 text-gray-500 dark:text-gray-400"><?php echo $msg['timestamp'] ? date('d/m H:i:s', $msg['timestamp']) : '—'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Links úteis -->
        <div class="flex gap-3">
            <a href="/my_instance.php" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium">
                <i class="fas fa-cog mr-2"></i>Minha Instância
            </a>
            <a href="/check_zapi_setup.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
                <i class="fas fa-stethoscope mr-2"></i>Diagnóstico Setup
            </a>
            <a href="/chat.php" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">
                <i class="fas fa-comments mr-2"></i>Ir para Chat
            </a>
            <button onclick="location.reload()" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300">
                <i class="fas fa-sync-alt mr-2"></i>Atualizar
            </button>
        </div>
    </div>
</div>

<?php require_once 'includes/footer_spa.php'; ?>
