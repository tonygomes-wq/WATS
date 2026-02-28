<?php
/**
 * Dashboard Unificado de Monitoramento Meta API
 * Combina monitoramento básico + analytics avançado em abas
 */

// As classes já foram carregadas no meta_monitoring.php
$rateLimiter = new MetaRateLimiter($pdo);

// Obter estatísticas de rate limiting
$usageStats = $isMetaConfigured ? $rateLimiter->getUsageStats($userId) : null;

// Obter estatísticas de janela 24h
$windowStats = $isMetaConfigured ? $windowManager->getWindowStats($userId, $days) : [];

// Métricas avançadas (últimos 30 dias)
$dailyMetrics = [];
$totals = ['sent' => 0, 'received' => 0, 'delivered' => 0, 'read' => 0, 'failed' => 0];
$deliveryRate = 0;
$readRate = 0;
$failureRate = 0;
$avgResponseTime = 0;
$activeConversations = 0;
$topTemplates = [];
$recentAlerts = [];

if ($isMetaConfigured) {
    // Métricas diárias
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as total_messages,
            SUM(CASE WHEN from_me = 1 THEN 1 ELSE 0 END) as sent_messages,
            SUM(CASE WHEN from_me = 0 THEN 1 ELSE 0 END) as received_messages,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_messages,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
        FROM chat_messages
        WHERE user_id = ?
        AND provider = 'meta'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    $stmt->execute([$userId]);
    $dailyMetrics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular totais
    foreach ($dailyMetrics as $metric) {
        $totals['sent'] += (int)$metric['sent_messages'];
        $totals['received'] += (int)$metric['received_messages'];
        $totals['delivered'] += (int)$metric['delivered'];
        $totals['read'] += (int)$metric['read_messages'];
        $totals['failed'] += (int)$metric['failed'];
    }
    
    // Taxas
    $deliveryRate = $totals['sent'] > 0 ? ($totals['delivered'] / $totals['sent']) * 100 : 0;
    $readRate = $totals['delivered'] > 0 ? ($totals['read'] / $totals['delivered']) * 100 : 0;
    $failureRate = $totals['sent'] > 0 ? ($totals['failed'] / $totals['sent']) * 100 : 0;
    
    // Tempo médio de resposta
    $stmt = $pdo->prepare("
        SELECT AVG(response_time) as avg_response_time
        FROM (
            SELECT 
                TIMESTAMPDIFF(SECOND, 
                    (SELECT MAX(created_at) FROM chat_messages m2 
                     WHERE m2.conversation_id = m1.conversation_id 
                     AND m2.from_me = 1 
                     AND m2.created_at < m1.created_at),
                    m1.created_at
                ) as response_time
            FROM chat_messages m1
            WHERE m1.user_id = ?
            AND m1.from_me = 0
            AND m1.provider = 'meta'
            AND m1.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            HAVING response_time IS NOT NULL AND response_time < 86400
        ) as responses
    ");
    $stmt->execute([$userId]);
    $avgResponse = $stmt->fetch(PDO::FETCH_ASSOC);
    $avgResponseTime = $avgResponse['avg_response_time'] ?? 0;
    
    // Conversas ativas
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT conversation_id) as active_conversations
        FROM chat_messages
        WHERE user_id = ?
        AND provider = 'meta'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute([$userId]);
    $activeConversations = $stmt->fetch(PDO::FETCH_ASSOC)['active_conversations'] ?? 0;
    
    // Templates mais usados
    $stmt = $pdo->prepare("
        SELECT 
            template_name,
            COUNT(*) as usage_count,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_count
        FROM dispatch_history
        WHERE user_id = ?
        AND template_name IS NOT NULL
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY template_name
        ORDER BY usage_count DESC
        LIMIT 10
    ");
    $stmt->execute([$userId]);
    $topTemplates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Alertas de rate limiting
    $stmt = $pdo->prepare("
        SELECT alert_level, message, created_at
        FROM meta_rate_limit_alerts
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$userId]);
    $recentAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Mensagens e erros recentes
$recentMessages = [];
$recentErrors = [];

if ($isMetaConfigured) {
    $stmt = $pdo->prepare("
        SELECT 
            cm.id, cm.message_text, cm.from_me, cm.status, cm.created_at,
            cc.contact_name, cc.remote_jid
        FROM chat_messages cm
        JOIN chat_conversations cc ON cm.conversation_id = cc.id
        WHERE cm.user_id = ? AND cm.provider = 'meta'
        ORDER BY cm.created_at DESC LIMIT 10
    ");
    $stmt->execute([$userId]);
    $recentMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("
        SELECT error_message, phone, created_at
        FROM dispatch_history
        WHERE user_id = ? AND status = 'failed' AND error_message IS NOT NULL
        ORDER BY created_at DESC LIMIT 5
    ");
    $stmt->execute([$userId]);
    $recentErrors = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<main class="flex-1 overflow-y-auto bg-gray-50">
<div class="p-24">
    <!-- Header -->
    <div class="mb-24">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-18 font-semibold text-gray-900 dark:text-gray-100 tracking-tight" style="letter-spacing: -0.02em;">
                    Monitoramento Meta API
                </h1>
                <p class="text-13 text-gray-500 dark:text-gray-400 mt-4">
                    Dashboard completo com métricas e analytics
                </p>
            </div>
        </div>
    </div>

    <?php if (!$isMetaConfigured): ?>
    <!-- Aviso: Meta não configurada -->
    <div class="bg-yellow-50 dark:bg-yellow-900/10 border border-yellow-200 dark:border-yellow-800/30 rounded-6 p-16" style="border-width: 0.5px;">
        <div class="flex items-start gap-12">
            <div class="flex-shrink-0 w-20 h-20 flex items-center justify-center bg-yellow-100 dark:bg-yellow-900/20 rounded-4">
                <i class="fas fa-exclamation-triangle text-yellow-600 dark:text-yellow-500 text-14"></i>
            </div>
            <div class="flex-1">
                <h3 class="text-14 font-semibold text-yellow-900 dark:text-yellow-100 tracking-tight" style="letter-spacing: -0.01em;">Meta API não configurada</h3>
                <p class="text-13 text-yellow-700 dark:text-yellow-300/90 mt-4 leading-relaxed">
                    Configure a API oficial da Meta em <a href="/my_instance.php" class="font-medium underline hover:no-underline">Minha Instância</a> para visualizar métricas.
                </p>
            </div>
        </div>
    </div>
    <?php else: ?>

    <!-- Tabs de Navegação -->
    <div class="mb-24 border-b border-gray-200 dark:border-gray-700" style="border-width: 0.5px;">
        <nav class="flex gap-16" role="tablist">
            <button onclick="switchTab('overview')" id="tab-overview" class="tab-button px-12 py-8 text-13 font-medium border-b-2 transition-all" style="border-color: #3b82f6; color: #3b82f6;">
                Visão Geral
            </button>
            <button onclick="switchTab('analytics')" id="tab-analytics" class="tab-button px-12 py-8 text-13 font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 transition-all">
                Analytics Avançado
            </button>
            <button onclick="switchTab('ratelimit')" id="tab-ratelimit" class="tab-button px-12 py-8 text-13 font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 transition-all">
                Rate Limiting
            </button>
            <button onclick="switchTab('templates')" id="tab-templates" class="tab-button px-12 py-8 text-13 font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 transition-all">
                Templates
            </button>
        </nav>
    </div>

    <!-- Tab: Visão Geral -->
    <div id="content-overview" class="tab-content">
        <!-- Cards de Métricas Básicas -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-24">
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-6 p-16" style="border-width: 0.5px;">
                <div class="flex items-start justify-between mb-12">
                    <div class="flex-shrink-0 w-32 h-32 flex items-center justify-center bg-blue-50 dark:bg-blue-900/10 rounded-4">
                        <i class="fas fa-paper-plane text-blue-600 dark:text-blue-400 text-14"></i>
                    </div>
                </div>
                <div>
                    <p class="text-11 font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Enviadas</p>
                    <p class="text-32 font-semibold text-gray-900 dark:text-gray-100 mt-8 tracking-tight font-mono tabular-nums" style="letter-spacing: -0.02em;"><?php echo number_format($metrics['messages_sent']); ?></p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-6 p-16" style="border-width: 0.5px;">
                <div class="flex items-start justify-between mb-12">
                    <div class="flex-shrink-0 w-32 h-32 flex items-center justify-center bg-green-50 dark:bg-green-900/10 rounded-4">
                        <i class="fas fa-inbox text-green-600 dark:text-green-400 text-14"></i>
                    </div>
                </div>
                <div>
                    <p class="text-11 font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Recebidas</p>
                    <p class="text-32 font-semibold text-gray-900 dark:text-gray-100 mt-8 tracking-tight font-mono tabular-nums" style="letter-spacing: -0.02em;"><?php echo number_format($metrics['messages_received']); ?></p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-6 p-16" style="border-width: 0.5px;">
                <div class="flex items-start justify-between mb-12">
                    <div class="flex-shrink-0 w-32 h-32 flex items-center justify-center bg-purple-50 dark:bg-purple-900/10 rounded-4">
                        <i class="fas fa-comments text-purple-600 dark:text-purple-400 text-14"></i>
                    </div>
                </div>
                <div>
                    <p class="text-11 font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Conversas Ativas</p>
                    <p class="text-32 font-semibold text-gray-900 dark:text-gray-100 mt-8 tracking-tight font-mono tabular-nums" style="letter-spacing: -0.02em;"><?php echo number_format($metrics['active_conversations']); ?></p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-6 p-16" style="border-width: 0.5px;">
                <div class="flex items-start justify-between mb-12">
                    <div class="flex-shrink-0 w-32 h-32 flex items-center justify-center bg-<?php echo $metrics['success_rate'] >= 90 ? 'green' : ($metrics['success_rate'] >= 70 ? 'yellow' : 'red'); ?>-50 dark:bg-<?php echo $metrics['success_rate'] >= 90 ? 'green' : ($metrics['success_rate'] >= 70 ? 'yellow' : 'red'); ?>-900/10 rounded-4">
                        <i class="fas fa-check-circle text-<?php echo $metrics['success_rate'] >= 90 ? 'green' : ($metrics['success_rate'] >= 70 ? 'yellow' : 'red'); ?>-600 dark:text-<?php echo $metrics['success_rate'] >= 90 ? 'green' : ($metrics['success_rate'] >= 70 ? 'yellow' : 'red'); ?>-400 text-14"></i>
                    </div>
                </div>
                <div>
                    <p class="text-11 font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Taxa de Sucesso</p>
                    <p class="text-32 font-semibold text-gray-900 dark:text-gray-100 mt-8 tracking-tight font-mono tabular-nums" style="letter-spacing: -0.02em;"><?php echo number_format($metrics['success_rate'], 1); ?>%</p>
                </div>
            </div>
        </div>

        <!-- Janela 24h e Mensagens Recentes -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 mb-24">
            <!-- Janela 24h -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-6 p-20" style="border-width: 0.5px;">
                <h3 class="text-14 font-semibold text-gray-900 dark:text-gray-100 mb-16 tracking-tight" style="letter-spacing: -0.01em;">
                    <i class="fas fa-clock text-gray-400 mr-8"></i>Janela de 24 Horas
                </h3>
                <?php if (!empty($windowStats)): ?>
                <div class="space-y-12">
                    <div class="flex justify-between items-center">
                        <span class="text-13 text-gray-600 dark:text-gray-400">Conversas dentro da janela</span>
                        <span class="text-13 font-mono font-semibold text-green-600 dark:text-green-400"><?php echo $windowStats['within_window'] ?? 0; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-13 text-gray-600 dark:text-gray-400">Conversas fora da janela</span>
                        <span class="text-13 font-mono font-semibold text-red-600 dark:text-red-400"><?php echo $windowStats['outside_window'] ?? 0; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-13 text-gray-600 dark:text-gray-400">Violações registradas</span>
                        <span class="text-13 font-mono font-semibold text-yellow-600 dark:text-yellow-400"><?php echo $windowStats['violations'] ?? 0; ?></span>
                    </div>
                </div>
                <?php else: ?>
                <p class="text-13 text-gray-500">Nenhum dado disponível</p>
                <?php endif; ?>
            </div>

            <!-- Mensagens Recentes -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-6 p-20" style="border-width: 0.5px;">
                <h3 class="text-14 font-semibold text-gray-900 dark:text-gray-100 mb-16 tracking-tight" style="letter-spacing: -0.01em;">
                    <i class="fas fa-comment-dots text-gray-400 mr-8"></i>Mensagens Recentes
                </h3>
                <div class="space-y-8 max-h-200 overflow-y-auto">
                    <?php foreach (array_slice($recentMessages, 0, 5) as $msg): ?>
                    <div class="flex items-start gap-8 pb-8 border-b border-gray-100 dark:border-gray-700" style="border-width: 0.5px;">
                        <div class="flex-shrink-0 w-6 h-6 rounded-full <?php echo $msg['from_me'] ? 'bg-blue-500' : 'bg-green-500'; ?>"></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-12 font-medium text-gray-900 dark:text-gray-100 truncate"><?php echo htmlspecialchars($msg['contact_name'] ?? $msg['remote_jid']); ?></p>
                            <p class="text-11 text-gray-500 dark:text-gray-400 truncate"><?php echo htmlspecialchars(substr($msg['message_text'] ?? '', 0, 50)); ?></p>
                        </div>
                        <span class="text-10 text-gray-400"><?php echo date('H:i', strtotime($msg['created_at'])); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab: Analytics Avançado -->
    <div id="content-analytics" class="tab-content hidden">
        <!-- KPIs Principais -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-24">
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-6 p-20" style="border-width: 0.5px;">
                <div class="text-13 text-gray-500 dark:text-gray-400 mb-8">Taxa de Entrega</div>
                <div class="text-32 font-semibold font-mono text-green-600 dark:text-green-400 mb-4"><?php echo number_format($deliveryRate, 1); ?>%</div>
                <div class="text-12 text-gray-500"><?php echo number_format($totals['delivered']); ?> de <?php echo number_format($totals['sent']); ?> enviadas</div>
            </div>

            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-6 p-20" style="border-width: 0.5px;">
                <div class="text-13 text-gray-500 dark:text-gray-400 mb-8">Taxa de Leitura</div>
                <div class="text-32 font-semibold font-mono text-blue-600 dark:text-blue-400 mb-4"><?php echo number_format($readRate, 1); ?>%</div>
                <div class="text-12 text-gray-500"><?php echo number_format($totals['read']); ?> lidas</div>
            </div>

            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-6 p-20" style="border-width: 0.5px;">
                <div class="text-13 text-gray-500 dark:text-gray-400 mb-8">Taxa de Falha</div>
                <div class="text-32 font-semibold font-mono text-<?php echo $failureRate > 5 ? 'red' : 'gray'; ?>-600 dark:text-<?php echo $failureRate > 5 ? 'red' : 'gray'; ?>-400 mb-4"><?php echo number_format($failureRate, 1); ?>%</div>
                <div class="text-12 text-gray-500"><?php echo number_format($totals['failed']); ?> falhas</div>
            </div>

            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-6 p-20" style="border-width: 0.5px;">
                <div class="text-13 text-gray-500 dark:text-gray-400 mb-8">Tempo Médio de Resposta</div>
                <div class="text-32 font-semibold font-mono text-purple-600 dark:text-purple-400 mb-4">
                    <?php 
                    if ($avgResponseTime < 60) {
                        echo number_format($avgResponseTime) . 's';
                    } elseif ($avgResponseTime < 3600) {
                        echo number_format($avgResponseTime / 60, 1) . 'm';
                    } else {
                        echo number_format($avgResponseTime / 3600, 1) . 'h';
                    }
                    ?>
                </div>
                <div class="text-12 text-gray-500">Últimos 30 dias</div>
            </div>
        </div>
    </div>

    <!-- Tab: Rate Limiting -->
    <div id="content-ratelimit" class="tab-content hidden">
        <?php if ($usageStats): ?>
        <!-- Status de Rate Limiting -->
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-6 p-24 mb-24" style="border-width: 0.5px;">
            <h2 class="text-18 font-semibold text-gray-900 dark:text-gray-100 mb-16">Status de Rate Limiting</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-16 mb-16">
                <div class="p-16 bg-gray-50 dark:bg-gray-700/50 rounded-6">
                    <div class="text-12 text-gray-500 dark:text-gray-400 mb-4">Tier Atual</div>
                    <div class="text-24 font-semibold font-mono text-gray-900 dark:text-gray-100"><?php echo strtoupper($usageStats['tier']); ?></div>
                </div>
                
                <div class="p-16 bg-gray-50 dark:bg-gray-700/50 rounded-6">
                    <div class="text-12 text-gray-500 dark:text-gray-400 mb-4">Limite Diário</div>
                    <div class="text-24 font-semibold font-mono text-gray-900 dark:text-gray-100"><?php echo number_format($usageStats['limits']['daily']); ?></div>
                </div>
                
                <div class="p-16 bg-gray-50 dark:bg-gray-700/50 rounded-6">
                    <div class="text-12 text-gray-500 dark:text-gray-400 mb-4">Conversas Únicas (24h)</div>
                    <div class="text-24 font-semibold font-mono text-gray-900 dark:text-gray-100"><?php echo number_format($usageStats['usage_24h']['unique_conversations']); ?></div>
                    <div class="text-12 text-gray-500 dark:text-gray-400 mt-4"><?php echo $usageStats['usage_24h']['percentage']; ?>% do limite</div>
                </div>
                
                <div class="p-16 bg-gray-50 dark:bg-gray-700/50 rounded-6">
                    <div class="text-12 text-gray-500 dark:text-gray-400 mb-4">Status</div>
                    <div class="text-18 font-semibold">
                        <?php
                        $statusColors = ['normal' => '#10b981', 'warning' => '#f59e0b', 'critical' => '#ef4444'];
                        $statusLabels = ['normal' => '✅ Normal', 'warning' => '⚠️ Atenção', 'critical' => '🚨 Crítico'];
                        $status = $usageStats['status'];
                        ?>
                        <span style="color: <?php echo $statusColors[$status]; ?>;"><?php echo $statusLabels[$status]; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Barra de Progresso -->
            <div class="bg-gray-200 dark:bg-gray-700 h-8 rounded-4 overflow-hidden">
                <div style="background: <?php echo $statusColors[$status]; ?>; height: 100%; width: <?php echo min($usageStats['usage_24h']['percentage'], 100); ?>%; transition: width 0.3s;"></div>
            </div>
        </div>

        <!-- Alertas Recentes -->
        <?php if (!empty($recentAlerts)): ?>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-6 p-24" style="border-width: 0.5px;">
            <h2 class="text-18 font-semibold text-gray-900 dark:text-gray-100 mb-16">Alertas de Rate Limiting</h2>
            <div class="space-y-8">
                <?php foreach ($recentAlerts as $alert): 
                    $levelColors = [
                        'warning' => ['bg' => '#fef3c7', 'border' => '#fbbf24', 'text' => '#92400e'],
                        'critical' => ['bg' => '#fee2e2', 'border' => '#ef4444', 'text' => '#991b1b']
                    ];
                    $colors = $levelColors[$alert['alert_level']];
                ?>
                <div style="background: <?php echo $colors['bg']; ?>; border-left: 4px solid <?php echo $colors['border']; ?>; padding: 12px; border-radius: 4px;">
                    <div class="flex justify-between items-start">
                        <div style="color: <?php echo $colors['text']; ?>; font-size: 14px;">
                            <?php echo htmlspecialchars($alert['message']); ?>
                        </div>
                        <div style="color: <?php echo $colors['text']; ?>; font-size: 12px; opacity: 0.7; white-space: nowrap; margin-left: 16px;">
                            <?php echo date('d/m/Y H:i', strtotime($alert['created_at'])); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Tab: Templates -->
    <div id="content-templates" class="tab-content hidden">
        <?php if (!empty($topTemplates)): ?>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-6 p-24" style="border-width: 0.5px;">
            <h2 class="text-18 font-semibold text-gray-900 dark:text-gray-100 mb-16">Templates Mais Usados</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700" style="border-width: 0.5px;">
                            <th class="text-left py-12 px-12 text-12 font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Template</th>
                            <th class="text-right py-12 px-12 text-12 font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Usos</th>
                            <th class="text-right py-12 px-12 text-12 font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Entregues</th>
                            <th class="text-right py-12 px-12 text-12 font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Taxa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topTemplates as $template): 
                            $rate = $template['usage_count'] > 0 ? ($template['delivered_count'] / $template['usage_count']) * 100 : 0;
                        ?>
                        <tr class="border-b border-gray-100 dark:border-gray-700/50" style="border-width: 0.5px;">
                            <td class="py-12 px-12 text-14 text-gray-900 dark:text-gray-100"><?php echo htmlspecialchars($template['template_name']); ?></td>
                            <td class="py-12 px-12 text-right font-mono text-gray-600 dark:text-gray-400"><?php echo number_format($template['usage_count']); ?></td>
                            <td class="py-12 px-12 text-right font-mono text-gray-600 dark:text-gray-400"><?php echo number_format($template['delivered_count']); ?></td>
                            <td class="py-12 px-12 text-right font-mono" style="color: <?php echo $rate >= 90 ? '#10b981' : '#64748b'; ?>;">
                                <?php echo number_format($rate, 1); ?>%
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-6 p-24 text-center" style="border-width: 0.5px;">
            <p class="text-gray-500">Nenhum template utilizado nos últimos 30 dias</p>
        </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</div>

<script>
function switchTab(tabName) {
    // Ocultar todos os conteúdos
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    // Remover estilo ativo de todos os botões
    document.querySelectorAll('.tab-button').forEach(button => {
        button.style.borderColor = 'transparent';
        button.style.color = '#6b7280';
    });
    
    // Mostrar conteúdo selecionado
    document.getElementById('content-' + tabName).classList.remove('hidden');
    
    // Ativar botão selecionado
    const activeButton = document.getElementById('tab-' + tabName);
    activeButton.style.borderColor = '#3b82f6';
    activeButton.style.color = '#3b82f6';
}
</script>
</div>
</main>
