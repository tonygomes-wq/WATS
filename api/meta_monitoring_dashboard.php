<?php
/**
 * Dashboard de Monitoramento da Integração Meta API
 * Exibe métricas, status e saúde da integração
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/Meta24HourWindow.php';

requireLogin();

$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'] ?? 'user';

// Apenas admin pode acessar
if (!isAdmin()) {
    header('Location: /dashboard.php');
    exit;
}

// Período de análise (padrão: 7 dias)
$days = isset($_GET['days']) ? (int)$_GET['days'] : 7;

// Buscar configuração Meta do usuário
$stmt = $pdo->prepare("
    SELECT 
        whatsapp_provider,
        meta_phone_number_id,
        meta_business_account_id,
        meta_api_version
    FROM users 
    WHERE id = ?
");
$stmt->execute([$userId]);
$userConfig = $stmt->fetch(PDO::FETCH_ASSOC);

$isMetaConfigured = ($userConfig['whatsapp_provider'] === 'meta' && !empty($userConfig['meta_phone_number_id']));

// Métricas gerais
$metrics = [
    'messages_sent' => 0,
    'messages_received' => 0,
    'messages_failed' => 0,
    'active_conversations' => 0,
    'avg_response_time' => 0,
    'success_rate' => 0
];

if ($isMetaConfigured) {
    // Mensagens enviadas
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM chat_messages
        WHERE user_id = ?
        AND provider = 'meta'
        AND from_me = 1
        AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$userId, $days]);
    $metrics['messages_sent'] = $stmt->fetchColumn();

    // Mensagens recebidas
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM chat_messages
        WHERE user_id = ?
        AND provider = 'meta'
        AND from_me = 0
        AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$userId, $days]);
    $metrics['messages_received'] = $stmt->fetchColumn();

    // Mensagens com falha
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM dispatch_history
        WHERE user_id = ?
        AND status = 'failed'
        AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$userId, $days]);
    $metrics['messages_failed'] = $stmt->fetchColumn();

    // Conversas ativas
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM chat_conversations
        WHERE user_id = ?
        AND provider = 'meta'
        AND last_message_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$userId, $days]);
    $metrics['active_conversations'] = $stmt->fetchColumn();

    // Taxa de sucesso
    $total = $metrics['messages_sent'] + $metrics['messages_failed'];
    if ($total > 0) {
        $metrics['success_rate'] = round(($metrics['messages_sent'] / $total) * 100, 2);
    }
}

// Estatísticas de janela 24h
$windowManager = new Meta24HourWindow($pdo);
$windowStats = $isMetaConfigured ? $windowManager->getWindowStats($userId, $days) : [];

// Últimas mensagens
$recentMessages = [];
if ($isMetaConfigured) {
    $stmt = $pdo->prepare("
        SELECT 
            cm.id,
            cm.message_text,
            cm.from_me,
            cm.status,
            cm.created_at,
            cc.contact_name,
            cc.remote_jid
        FROM chat_messages cm
        JOIN chat_conversations cc ON cm.conversation_id = cc.id
        WHERE cm.user_id = ?
        AND cm.provider = 'meta'
        ORDER BY cm.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$userId]);
    $recentMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Erros recentes
$recentErrors = [];
if ($isMetaConfigured) {
    $stmt = $pdo->prepare("
        SELECT 
            error_message,
            phone,
            created_at
        FROM dispatch_history
        WHERE user_id = ?
        AND status = 'failed'
        AND error_message IS NOT NULL
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $recentErrors = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Detectar se é requisição SPA (AJAX)
$isSPA = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$isSPA) {
    $page_title = 'Monitoramento Meta API';
    require_once '../includes/header_spa.php';
}
?>

<div class="refined-container">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">
                        <i class="fab fa-meta mr-2 text-blue-600"></i>Monitoramento Meta API
                    </h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Últimos <?php echo $days; ?> dias
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="?days=1" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg <?php echo $days === 1 ? 'ring-2 ring-blue-500' : ''; ?>">1d</a>
                    <a href="?days=7" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg <?php echo $days === 7 ? 'ring-2 ring-blue-500' : ''; ?>">7d</a>
                    <a href="?days=30" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg <?php echo $days === 30 ? 'ring-2 ring-blue-500' : ''; ?>">30d</a>
                </div>
            </div>
        </div>

        <?php if (!$isMetaConfigured): ?>
        <!-- Aviso: Meta não configurada -->
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-6 mb-6">
            <div class="flex items-start">
                <i class="fas fa-exclamation-triangle text-yellow-600 text-2xl mr-4"></i>
                <div>
                    <h3 class="text-lg font-semibold text-yellow-800 dark:text-yellow-200">Meta API não configurada</h3>
                    <p class="text-yellow-700 dark:text-yellow-300 mt-1">
                        Configure a API oficial da Meta em <a href="/my_instance.php" class="underline">Minha Instância</a> para visualizar métricas.
                    </p>
                </div>
            </div>
        </div>
        <?php else: ?>

        <!-- Cards de Métricas -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <!-- Mensagens Enviadas -->
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm">Mensagens Enviadas</p>
                        <p class="text-3xl font-bold mt-2"><?php echo number_format($metrics['messages_sent']); ?></p>
                    </div>
                    <i class="fas fa-paper-plane text-4xl opacity-30"></i>
                </div>
            </div>

            <!-- Mensagens Recebidas -->
            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm">Mensagens Recebidas</p>
                        <p class="text-3xl font-bold mt-2"><?php echo number_format($metrics['messages_received']); ?></p>
                    </div>
                    <i class="fas fa-inbox text-4xl opacity-30"></i>
                </div>
            </div>

            <!-- Taxa de Sucesso -->
            <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100 text-sm">Taxa de Sucesso</p>
                        <p class="text-3xl font-bold mt-2"><?php echo $metrics['success_rate']; ?>%</p>
                    </div>
                    <i class="fas fa-check-circle text-4xl opacity-30"></i>
                </div>
            </div>

            <!-- Conversas Ativas -->
            <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-lg shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-orange-100 text-sm">Conversas Ativas</p>
                        <p class="text-3xl font-bold mt-2"><?php echo number_format($metrics['active_conversations']); ?></p>
                    </div>
                    <i class="fas fa-comments text-4xl opacity-30"></i>
                </div>
            </div>
        </div>

        <!-- Janela de 24h -->
        <?php if (!empty($windowStats)): ?>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-4">
                <i class="fas fa-clock mr-2 text-blue-600"></i>Janela de 24 Horas
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                    <p class="text-sm text-green-700 dark:text-green-300">Dentro da Janela</p>
                    <p class="text-2xl font-bold text-green-800 dark:text-green-200"><?php echo $windowStats['within_window']; ?></p>
                </div>
                <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
                    <p class="text-sm text-red-700 dark:text-red-300">Fora da Janela</p>
                    <p class="text-2xl font-bold text-red-800 dark:text-red-200"><?php echo $windowStats['outside_window']; ?></p>
                </div>
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                    <p class="text-sm text-blue-700 dark:text-blue-300">Utilização</p>
                    <p class="text-2xl font-bold text-blue-800 dark:text-blue-200"><?php echo $windowStats['window_utilization']; ?>%</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Mensagens Recentes -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-4">
                    <i class="fas fa-history mr-2 text-blue-600"></i>Mensagens Recentes
                </h2>
                <div class="space-y-3">
                    <?php if (empty($recentMessages)): ?>
                    <p class="text-gray-500 dark:text-gray-400 text-center py-4">Nenhuma mensagem encontrada</p>
                    <?php else: ?>
                    <?php foreach ($recentMessages as $msg): ?>
                    <div class="border-l-4 <?php echo $msg['from_me'] ? 'border-blue-500' : 'border-green-500'; ?> pl-4 py-2">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                    <?php echo $msg['from_me'] ? 'Você' : htmlspecialchars($msg['contact_name']); ?>
                                </p>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    <?php echo htmlspecialchars(substr($msg['message_text'], 0, 100)); ?>
                                </p>
                            </div>
                            <span class="text-xs text-gray-500 ml-2"><?php echo date('H:i', strtotime($msg['created_at'])); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Erros Recentes -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-4">
                    <i class="fas fa-exclamation-circle mr-2 text-red-600"></i>Erros Recentes
                </h2>
                <div class="space-y-3">
                    <?php if (empty($recentErrors)): ?>
                    <p class="text-green-600 dark:text-green-400 text-center py-4">
                        <i class="fas fa-check-circle mr-2"></i>Nenhum erro recente
                    </p>
                    <?php else: ?>
                    <?php foreach ($recentErrors as $error): ?>
                    <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-3">
                        <p class="text-sm font-semibold text-red-700 dark:text-red-300">
                            <?php echo htmlspecialchars($error['phone']); ?>
                        </p>
                        <p class="text-xs text-red-600 dark:text-red-400 mt-1">
                            <?php echo htmlspecialchars($error['error_message']); ?>
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            <?php echo date('d/m/Y H:i', strtotime($error['created_at'])); ?>
                        </p>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Configuração Atual -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mt-6">
            <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-4">
                <i class="fas fa-cog mr-2 text-blue-600"></i>Configuração Atual
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Phone Number ID</p>
                    <p class="font-mono text-sm text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($userConfig['meta_phone_number_id']); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Business Account ID</p>
                    <p class="font-mono text-sm text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($userConfig['meta_business_account_id']); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Versão da API</p>
                    <p class="font-mono text-sm text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($userConfig['meta_api_version']); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Provedor</p>
                    <p class="font-mono text-sm text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($userConfig['whatsapp_provider']); ?></p>
                </div>
            </div>
        </div>

        <?php endif; ?>
    </div>
</div>

<?php 
if (!$isSPA) {
    require_once '../includes/footer.php';
}
?>
