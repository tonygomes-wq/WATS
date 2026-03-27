<?php
/**
 * Script de verificação da configuração Z-API
 * Execute no navegador: /check_zapi_setup.php
 * 
 * Verifica se as colunas necessárias existem no banco de dados
 * e se a configuração está correta.
 */

require_once 'includes/header_spa.php';

$user_id = $_SESSION['user_id'];
$results = [];
$allGood = true;

// 1. Verificar colunas na tabela users
$requiredColumns = ['whatsapp_provider', 'zapi_instance_id', 'zapi_token', 'zapi_client_token'];
$missingColumns = [];

foreach ($requiredColumns as $col) {
    try {
        $stmt = $pdo->prepare("SELECT $col FROM users LIMIT 1");
        $stmt->execute();
        $results[] = ['check' => "Coluna '$col' na tabela users", 'status' => 'ok', 'detail' => 'Existe'];
    } catch (PDOException $e) {
        $missingColumns[] = $col;
        $results[] = ['check' => "Coluna '$col' na tabela users", 'status' => 'error', 'detail' => 'NÃO EXISTE - Execute a migration!'];
        $allGood = false;
    }
}

// 2. Verificar dados do usuário atual
try {
    $stmt = $pdo->prepare("SELECT whatsapp_provider, zapi_instance_id, zapi_token, zapi_client_token, evolution_instance, evolution_token FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($userData) {
        $results[] = [
            'check' => 'Provider atual',
            'status' => 'info',
            'detail' => $userData['whatsapp_provider'] ?: '(não definido — padrão: evolution)'
        ];
        $results[] = [
            'check' => 'Z-API Instance ID',
            'status' => !empty($userData['zapi_instance_id']) ? 'ok' : 'warning',
            'detail' => !empty($userData['zapi_instance_id']) ? htmlspecialchars($userData['zapi_instance_id']) : '(vazio)'
        ];
        $results[] = [
            'check' => 'Z-API Token',
            'status' => !empty($userData['zapi_token']) ? 'ok' : 'warning',
            'detail' => !empty($userData['zapi_token']) ? '****' . substr($userData['zapi_token'], -6) : '(vazio)'
        ];
        $results[] = [
            'check' => 'Z-API Client-Token',
            'status' => !empty($userData['zapi_client_token']) ? 'ok' : 'warning',
            'detail' => !empty($userData['zapi_client_token']) ? '****' . substr($userData['zapi_client_token'], -6) : '(vazio — obrigatório para envio!)'
        ];
        $results[] = [
            'check' => 'Evolution Instance',
            'status' => !empty($userData['evolution_instance']) ? 'ok' : 'info',
            'detail' => !empty($userData['evolution_instance']) ? htmlspecialchars($userData['evolution_instance']) : '(vazio)'
        ];
    }
} catch (PDOException $e) {
    $results[] = ['check' => 'Dados do usuário', 'status' => 'error', 'detail' => $e->getMessage()];
    $allGood = false;
}

// 3. Verificar ENUM do whatsapp_provider
try {
    $stmt = $pdo->prepare("
        SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'whatsapp_provider'
    ");
    $stmt->execute();
    $colInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($colInfo) {
        $hasZapi = strpos($colInfo['COLUMN_TYPE'], 'zapi') !== false;
        $results[] = [
            'check' => 'ENUM whatsapp_provider inclui zapi',
            'status' => $hasZapi ? 'ok' : 'error',
            'detail' => $colInfo['COLUMN_TYPE']
        ];
        if (!$hasZapi) $allGood = false;
    }
} catch (PDOException $e) {
    $results[] = ['check' => 'ENUM whatsapp_provider', 'status' => 'error', 'detail' => $e->getMessage()];
}

// 4. Verificar arquivos críticos
$criticalFiles = [
    'includes/channels/WhatsAppChannel.php',
    'includes/channels/providers/ZAPIProvider.php',
    'includes/channels/ProviderInterface.php',
    'api/zapi_webhook.php',
];

foreach ($criticalFiles as $file) {
    $exists = file_exists(__DIR__ . '/' . $file);
    $results[] = [
        'check' => "Arquivo: $file",
        'status' => $exists ? 'ok' : 'error',
        'detail' => $exists ? 'Existe' : 'NÃO ENCONTRADO!'
    ];
    if (!$exists) $allGood = false;
}

// 5. Verificar tabela webhook_logs
try {
    $stmt = $pdo->prepare("SELECT 1 FROM chat_webhook_logs LIMIT 1");
    $stmt->execute();
    $results[] = ['check' => 'Tabela chat_webhook_logs', 'status' => 'ok', 'detail' => 'Existe'];
} catch (PDOException $e) {
    $results[] = ['check' => 'Tabela chat_webhook_logs', 'status' => 'warning', 'detail' => 'Não encontrada (webhook logs não serão salvos)'];
}

?>

<div class="main-content">
    <div class="bg-white border-b border-gray-200 px-6 py-5">
        <h1 class="text-2xl font-semibold text-gray-900">Verificação Z-API</h1>
        <p class="text-sm text-gray-600 mt-1">Diagnóstico da configuração Z-API no sistema</p>
    </div>
    
    <div class="p-6">
        <!-- Status geral -->
        <div class="mb-6 p-4 rounded-lg border <?php echo $allGood ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'; ?>">
            <h2 class="font-bold <?php echo $allGood ? 'text-green-800' : 'text-red-800'; ?>">
                <i class="fas <?php echo $allGood ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
                <?php echo $allGood ? 'Sistema pronto para Z-API!' : 'Ação necessária — veja os erros abaixo'; ?>
            </h2>
        </div>
        
        <?php if (!empty($missingColumns)): ?>
        <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
            <h3 class="font-bold text-yellow-800 mb-2"><i class="fas fa-database mr-2"></i>Migration necessária</h3>
            <p class="text-sm text-yellow-700 mb-2">Execute o seguinte SQL no banco de dados:</p>
            <pre class="bg-gray-800 text-green-400 p-4 rounded text-sm overflow-x-auto">source migrations/multi_provider_support.sql;</pre>
            <p class="text-xs text-yellow-600 mt-2">Ou execute via phpMyAdmin importando o arquivo <code>migrations/multi_provider_support.sql</code></p>
        </div>
        <?php endif; ?>
        
        <!-- Tabela de resultados -->
        <table class="w-full bg-white border border-gray-200 rounded-lg overflow-hidden">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Verificação</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Status</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Detalhe</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $r): ?>
                <tr class="border-t border-gray-100">
                    <td class="px-4 py-3 text-sm text-gray-800"><?php echo $r['check']; ?></td>
                    <td class="px-4 py-3 text-sm">
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
                    <td class="px-4 py-3 text-sm text-gray-600 font-mono"><?php echo $r['detail']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="mt-6 flex gap-3">
            <a href="/my_instance.php" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium">
                <i class="fas fa-cog mr-2"></i>Ir para Minha Instância
            </a>
            <a href="/channels.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
                <i class="fas fa-broadcast-tower mr-2"></i>Ir para Canais
            </a>
            <button onclick="location.reload()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm font-medium text-gray-700">
                <i class="fas fa-sync-alt mr-2"></i>Reverificar
            </button>
        </div>
    </div>
</div>

<?php require_once 'includes/footer_spa.php'; ?>
