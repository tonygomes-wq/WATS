<?php
$page_title = 'Diagnóstico do Sistema';
require_once 'includes/header_spa.php';
requireAdmin();

// Executar testes se solicitado
$testResults = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_tests'])) {
    $testResults = runSystemDiagnostics();
}

function runSystemDiagnostics() {
    global $pdo;
    $results = [
        'timestamp' => date('Y-m-d H:i:s'),
        'categories' => []
    ];
    
    // 1. AMBIENTE PHP
    $phpTests = [];
    $phpTests[] = [
        'name' => 'Versão do PHP',
        'status' => version_compare(PHP_VERSION, '7.4.0', '>=') ? 'success' : 'error',
        'message' => 'PHP ' . PHP_VERSION,
        'details' => version_compare(PHP_VERSION, '7.4.0', '>=') ? 'Versão adequada' : 'Necessário PHP 7.4 ou superior'
    ];
    
    $extensions = ['pdo', 'pdo_mysql', 'curl', 'mbstring', 'json', 'gd', 'zip'];
    foreach ($extensions as $ext) {
        $loaded = extension_loaded($ext);
        $phpTests[] = [
            'name' => "Extensão: $ext",
            'status' => $loaded ? 'success' : 'error',
            'message' => $loaded ? 'Instalada' : 'Não instalada',
            'details' => $loaded ? '' : "Execute: apt-get install php-$ext"
        ];
    }
    
    $phpTests[] = [
        'name' => 'Memory Limit',
        'status' => 'info',
        'message' => ini_get('memory_limit'),
        'details' => 'Recomendado: 256M ou superior'
    ];
    
    $phpTests[] = [
        'name' => 'Max Execution Time',
        'status' => 'info',
        'message' => ini_get('max_execution_time') . 's',
        'details' => 'Recomendado: 300s para disparos grandes'
    ];
    
    $phpTests[] = [
        'name' => 'Upload Max Filesize',
        'status' => 'info',
        'message' => ini_get('upload_max_filesize'),
        'details' => 'Para importação de CSV e mídia'
    ];
    
    $results['categories']['PHP & Ambiente'] = $phpTests;
    
    // 2. BANCO DE DADOS
    $dbTests = [];
    try {
        $dbTests[] = [
            'name' => 'Conexão MySQL',
            'status' => 'success',
            'message' => 'Conectado',
            'details' => 'Host: ' . DB_HOST . ' | DB: ' . DB_NAME
        ];
        
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        $dbTests[] = [
            'name' => 'Versão MySQL',
            'status' => 'info',
            'message' => $version,
            'details' => ''
        ];
        
        $requiredTables = [
            'users', 'contacts', 'categories', 'contact_categories', 
            'dispatch_history', 'chat_conversations', 'chat_messages',
            'campaigns', 'scheduled_dispatches', 'subscriptions'
        ];
        
        $stmt = $pdo->query("SHOW TABLES");
        $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($requiredTables as $table) {
            $exists = in_array($table, $existingTables);
            $dbTests[] = [
                'name' => "Tabela: $table",
                'status' => $exists ? 'success' : 'error',
                'message' => $exists ? 'Existe' : 'Não encontrada',
                'details' => $exists ? '' : 'Execute o script database.sql'
            ];
        }
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $userCount = $stmt->fetchColumn();
        $dbTests[] = [
            'name' => 'Total de Usuários',
            'status' => 'info',
            'message' => $userCount,
            'details' => ''
        ];
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM contacts");
        $contactCount = $stmt->fetchColumn();
        $dbTests[] = [
            'name' => 'Total de Contatos',
            'status' => 'info',
            'message' => $contactCount,
            'details' => ''
        ];
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM chat_messages");
        $messageCount = $stmt->fetchColumn();
        $dbTests[] = [
            'name' => 'Total de Mensagens',
            'status' => 'info',
            'message' => $messageCount,
            'details' => ''
        ];
        
    } catch (Exception $e) {
        $dbTests[] = [
            'name' => 'Erro no Banco',
            'status' => 'error',
            'message' => 'Falha na conexão',
            'details' => $e->getMessage()
        ];
    }
    
    $results['categories']['Banco de Dados'] = $dbTests;
    
    // 3. ARQUIVOS E PERMISSÕES
    $fileTests = [];
    
    $criticalFiles = [
        'config/database.php' => 'Configuração do banco',
        'includes/functions.php' => 'Funções principais',
        'api/send_message.php' => 'API de envio',
        'api/chat_webhook.php' => 'Webhook do chat',
        '.htaccess' => 'Configuração Apache'
    ];
    
    foreach ($criticalFiles as $file => $desc) {
        $exists = file_exists(__DIR__ . '/' . $file);
        $fileTests[] = [
            'name' => $desc,
            'status' => $exists ? 'success' : 'error',
            'message' => $exists ? 'Encontrado' : 'Não encontrado',
            'details' => $file
        ];
    }
    
    $writableDirs = [
        'storage/uploads' => 'Upload de arquivos',
        'storage/logs' => 'Logs do sistema',
        'backups' => 'Backups'
    ];
    
    foreach ($writableDirs as $dir => $desc) {
        $path = __DIR__ . '/' . $dir;
        $exists = is_dir($path);
        $writable = $exists && is_writable($path);
        
        $fileTests[] = [
            'name' => $desc,
            'status' => $writable ? 'success' : ($exists ? 'warning' : 'error'),
            'message' => $writable ? 'Gravável' : ($exists ? 'Sem permissão' : 'Não existe'),
            'details' => $dir . ($writable ? '' : ' - Execute: chmod 755 ' . $dir)
        ];
    }
    
    $results['categories']['Arquivos & Permissões'] = $fileTests;
    
    // 4. EVOLUTION API
    $apiTests = [];
    
    if (defined('EVOLUTION_API_URL') && EVOLUTION_API_URL !== 'https://sua-evolution-api.com') {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, EVOLUTION_API_URL);
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
            $apiTests[] = [
                'name' => 'Conectividade API',
                'status' => 'error',
                'message' => 'Erro de conexão',
                'details' => $error
            ];
        } else {
            $apiTests[] = [
                'name' => 'Conectividade API',
                'status' => $httpCode >= 200 && $httpCode < 300 ? 'success' : 'warning',
                'message' => "HTTP $httpCode",
                'details' => EVOLUTION_API_URL
            ];
        }
        
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE evolution_instance IS NOT NULL AND evolution_instance != ''");
            $instanceCount = $stmt->fetchColumn();
            
            $apiTests[] = [
                'name' => 'Instâncias Configuradas',
                'status' => 'info',
                'message' => $instanceCount,
                'details' => 'Usuários com instância Evolution'
            ];
        } catch (Exception $e) {
            // Ignorar se coluna não existir
        }
        
    } else {
        $apiTests[] = [
            'name' => 'Evolution API',
            'status' => 'warning',
            'message' => 'Não configurada',
            'details' => 'Configure em config/database.php'
        ];
    }
    
    $results['categories']['Evolution API'] = $apiTests;
    
    // 5. WEBHOOKS
    $webhookTests = [];
    
    $webhookUrl = SITE_URL . '/api/chat_webhook.php';
    $webhookTests[] = [
        'name' => 'URL do Webhook',
        'status' => 'info',
        'message' => 'Configurado',
        'details' => $webhookUrl
    ];
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM chat_messages WHERE direction = 'received' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $recentMessages = $stmt->fetchColumn();
        
        $webhookTests[] = [
            'name' => 'Mensagens Recebidas (1h)',
            'status' => $recentMessages > 0 ? 'success' : 'warning',
            'message' => $recentMessages,
            'details' => $recentMessages > 0 ? 'Webhook funcionando' : 'Nenhuma mensagem recebida'
        ];
    } catch (Exception $e) {
        // Ignorar erro
    }
    
    $webhookLogFile = __DIR__ . '/storage/logs/webhook.log';
    if (file_exists($webhookLogFile)) {
        $logSize = filesize($webhookLogFile);
        $webhookTests[] = [
            'name' => 'Log de Webhook',
            'status' => 'info',
            'message' => formatBytes($logSize),
            'details' => 'Última modificação: ' . date('Y-m-d H:i:s', filemtime($webhookLogFile))
        ];
    }
    
    $results['categories']['Webhooks'] = $webhookTests;
    
    // 6. SISTEMA OPERACIONAL
    $osTests = [];
    
    $osTests[] = [
        'name' => 'Sistema Operacional',
        'status' => 'info',
        'message' => PHP_OS,
        'details' => php_uname()
    ];
    
    $osTests[] = [
        'name' => 'Servidor Web',
        'status' => 'info',
        'message' => $_SERVER['SERVER_SOFTWARE'] ?? 'Desconhecido',
        'details' => ''
    ];
    
    if (function_exists('apache_get_modules')) {
        $hasRewrite = in_array('mod_rewrite', apache_get_modules());
        $osTests[] = [
            'name' => 'Apache mod_rewrite',
            'status' => $hasRewrite ? 'success' : 'warning',
            'message' => $hasRewrite ? 'Ativo' : 'Inativo',
            'details' => $hasRewrite ? '' : 'Necessário para URLs amigáveis'
        ];
    }
    
    $osTests[] = [
        'name' => 'Espaço em Disco',
        'status' => 'info',
        'message' => formatBytes(disk_free_space('.')),
        'details' => 'Livre de ' . formatBytes(disk_total_space('.'))
    ];
    
    $results['categories']['Sistema Operacional'] = $osTests;
    
    return $results;
}

function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>

<div class="max-w-7xl mx-auto p-6">
    <!-- Cabeçalho -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-800 dark:text-white flex items-center">
                    <i class="fas fa-stethoscope text-blue-600 mr-3"></i>
                    Diagnóstico do Sistema
                </h1>
                <p class="text-gray-600 dark:text-gray-300 mt-2">Verificação completa de saúde e configuração do sistema</p>
            </div>
            <form method="POST" class="inline">
                <button type="submit" name="run_tests" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition flex items-center">
                    <i class="fas fa-play-circle mr-2"></i>
                    <?php echo $testResults ? 'Executar Novamente' : 'Executar Diagnóstico'; ?>
                </button>
            </form>
        </div>
    </div>

    <?php if ($testResults): ?>
    <!-- Resumo Geral -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <?php
        $totalTests = 0;
        $successTests = 0;
        $errorTests = 0;
        $warningTests = 0;
        
        foreach ($testResults['categories'] as $category => $tests) {
            foreach ($tests as $test) {
                $totalTests++;
                if ($test['status'] === 'success') $successTests++;
                if ($test['status'] === 'error') $errorTests++;
                if ($test['status'] === 'warning') $warningTests++;
            }
        }
        
        $healthScore = $totalTests > 0 ? round(($successTests / $totalTests) * 100) : 0;
        ?>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 dark:text-gray-300 text-sm">Saúde Geral</p>
                    <p class="text-3xl font-bold text-blue-600"><?php echo $healthScore; ?>%</p>
                </div>
                <i class="fas fa-heartbeat text-blue-600 text-3xl"></i>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 dark:text-gray-300 text-sm">Testes OK</p>
                    <p class="text-3xl font-bold text-green-600"><?php echo $successTests; ?></p>
                </div>
                <i class="fas fa-check-circle text-green-600 text-3xl"></i>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 dark:text-gray-300 text-sm">Avisos</p>
                    <p class="text-3xl font-bold text-yellow-600"><?php echo $warningTests; ?></p>
                </div>
                <i class="fas fa-exclamation-triangle text-yellow-600 text-3xl"></i>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 dark:text-gray-300 text-sm">Erros</p>
                    <p class="text-3xl font-bold text-red-600"><?php echo $errorTests; ?></p>
                </div>
                <i class="fas fa-times-circle text-red-600 text-3xl"></i>
            </div>
        </div>
    </div>

    <!-- Resultados por Categoria -->
    <?php foreach ($testResults['categories'] as $categoryName => $tests): ?>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-4 flex items-center">
            <i class="fas fa-folder-open text-gray-600 dark:text-gray-400 mr-3"></i>
            <?php echo htmlspecialchars($categoryName); ?>
        </h2>
        
        <div class="space-y-3">
            <?php foreach ($tests as $test): ?>
            <div class="border rounded-lg p-4 <?php 
                echo $test['status'] === 'success' ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800' : 
                     ($test['status'] === 'error' ? 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800' : 
                     ($test['status'] === 'warning' ? 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800' : 
                     'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800')); 
            ?>">
                <div class="flex items-start justify-between">
                    <div class="flex items-start flex-1">
                        <i class="fas <?php 
                            echo $test['status'] === 'success' ? 'fa-check-circle text-green-600 dark:text-green-400' : 
                                 ($test['status'] === 'error' ? 'fa-times-circle text-red-600 dark:text-red-400' : 
                                 ($test['status'] === 'warning' ? 'fa-exclamation-triangle text-yellow-600 dark:text-yellow-400' : 
                                 'fa-info-circle text-blue-600 dark:text-blue-400')); 
                        ?> text-xl mr-3 mt-1"></i>
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <h3 class="font-semibold text-gray-800 dark:text-white"><?php echo htmlspecialchars($test['name']); ?></h3>
                                <span class="text-sm font-medium px-3 py-1 rounded-full <?php 
                                    echo $test['status'] === 'success' ? 'bg-green-200 dark:bg-green-800 text-green-800 dark:text-green-200' : 
                                         ($test['status'] === 'error' ? 'bg-red-200 dark:bg-red-800 text-red-800 dark:text-red-200' : 
                                         ($test['status'] === 'warning' ? 'bg-yellow-200 dark:bg-yellow-800 text-yellow-800 dark:text-yellow-200' : 
                                         'bg-blue-200 dark:bg-blue-800 text-blue-800 dark:text-blue-200')); 
                                ?>"><?php echo htmlspecialchars($test['message']); ?></span>
                            </div>
                            <?php if (!empty($test['details'])): ?>
                            <p class="text-sm text-gray-600 dark:text-gray-300 mt-1"><?php echo htmlspecialchars($test['details']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Informações Adicionais -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4 flex items-center">
            <i class="fas fa-info-circle text-blue-600 mr-3"></i>
            Informações do Diagnóstico
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <span class="font-semibold text-gray-700 dark:text-gray-300">Data/Hora:</span>
                <span class="text-gray-600 dark:text-gray-400 ml-2"><?php echo $testResults['timestamp']; ?></span>
            </div>
            <div>
                <span class="font-semibold text-gray-700 dark:text-gray-300">Total de Testes:</span>
                <span class="text-gray-600 dark:text-gray-400 ml-2"><?php echo $totalTests; ?></span>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Instruções Iniciais -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 text-center">
        <i class="fas fa-clipboard-check text-gray-400 dark:text-gray-500 text-6xl mb-4"></i>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-2">Pronto para Diagnosticar</h2>
        <p class="text-gray-600 dark:text-gray-300 mb-6">Clique no botão acima para executar uma verificação completa do sistema</p>
        
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6 text-left max-w-2xl mx-auto">
            <h3 class="font-bold text-blue-800 dark:text-blue-300 mb-3 flex items-center">
                <i class="fas fa-list-check mr-2"></i>
                O que será verificado:
            </h3>
            <ul class="space-y-2 text-sm text-blue-700 dark:text-blue-300">
                <li><i class="fas fa-check mr-2"></i>Versão e configuração do PHP</li>
                <li><i class="fas fa-check mr-2"></i>Extensões necessárias</li>
                <li><i class="fas fa-check mr-2"></i>Conexão e estrutura do banco de dados</li>
                <li><i class="fas fa-check mr-2"></i>Arquivos e permissões do sistema</li>
                <li><i class="fas fa-check mr-2"></i>Conectividade com Evolution API</li>
                <li><i class="fas fa-check mr-2"></i>Configuração de webhooks</li>
                <li><i class="fas fa-check mr-2"></i>Recursos do servidor</li>
            </ul>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer_spa.php'; ?>
