<?php
$page_title = 'Relatórios de Disparo';
require_once 'includes/header_spa.php';

$userId = $_SESSION['user_id'];

// Buscar estatísticas gerais
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_campaigns,
        SUM(sent_count) as total_sent,
        SUM(failed_count) as total_failed,
        SUM(response_count) as total_responses,
        SUM(total_contacts) as total_contacts
    FROM dispatch_campaigns
    WHERE user_id = ?
");
$stmt->execute([$userId]);
$generalStats = $stmt->fetch(PDO::FETCH_ASSOC);

// Calcular taxas
$responseRate = $generalStats['total_sent'] > 0 
    ? round(($generalStats['total_responses'] / $generalStats['total_sent']) * 100, 2) 
    : 0;

$successRate = $generalStats['total_contacts'] > 0
    ? round(($generalStats['total_sent'] / $generalStats['total_contacts']) * 100, 2)
    : 0;

// Buscar campanhas recentes
$stmt = $pdo->prepare("
    SELECT 
        dc.*,
        c.name as category_name,
        c.color as category_color,
        CASE 
            WHEN dc.total_contacts > 0 
            THEN ROUND((dc.sent_count / dc.total_contacts) * 100, 2)
            ELSE 0 
        END as progress_percent,
        CASE 
            WHEN dc.response_count > 0 AND dc.sent_count > 0
            THEN ROUND((dc.response_count / dc.sent_count) * 100, 2)
            ELSE 0 
        END as response_rate
    FROM dispatch_campaigns dc
    LEFT JOIN categories c ON dc.category_id = c.id
    WHERE dc.user_id = ?
    ORDER BY dc.created_at DESC
    LIMIT 10
");
$stmt->execute([$userId]);
$recentCampaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar respostas recentes
$stmt = $pdo->prepare("
    SELECT 
        dr.*,
        c.name as contact_name,
        dc.name as campaign_name
    FROM dispatch_responses dr
    LEFT JOIN contacts c ON dr.contact_id = c.id
    LEFT JOIN dispatch_campaigns dc ON dr.campaign_id = dc.id
    WHERE dr.user_id = ?
    ORDER BY dr.received_at DESC
    LIMIT 20
");
$stmt->execute([$userId]);
$recentResponses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas de sentimento
$stmt = $pdo->prepare("
    SELECT 
        sentiment,
        COUNT(*) as count
    FROM dispatch_responses
    WHERE user_id = ? AND sentiment != 'unknown'
    GROUP BY sentiment
");
$stmt->execute([$userId]);
$sentimentStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sentimentData = [
    'positive' => 0,
    'neutral' => 0,
    'negative' => 0
];
foreach ($sentimentStats as $stat) {
    $sentimentData[$stat['sentiment']] = (int)$stat['count'];
}

// Dados para gráfico de disparos nos últimos 30 dias
$stmt = $pdo->prepare("
    SELECT 
        DATE(sent_at) as date,
        COUNT(*) as sent,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
    FROM dispatch_history
    WHERE user_id = ? AND sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(sent_at)
    ORDER BY date ASC
");
$stmt->execute([$userId]);
$dispatchTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="refined-container p-4 md:p-6">
    <!-- Mobile Menu Toggle -->
    <div class="lg:hidden mb-4">
        <button id="mobileMenuToggle" onclick="toggleMobileMenu()" 
                class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg flex items-center justify-between shadow-lg">
            <span class="flex items-center">
                <i class="fas fa-chart-line mr-2"></i>
                <span class="font-semibold">Menu de Relatórios</span>
            </span>
            <i id="mobileMenuIcon" class="fas fa-chevron-down transition-transform duration-300"></i>
        </button>
        <div id="mobileMenu" class="hidden mt-2 bg-white rounded-lg shadow-lg overflow-hidden">
            <a href="#stats" class="block px-4 py-3 border-b hover:bg-gray-50 text-gray-700">
                <i class="fas fa-chart-bar mr-2 text-blue-600"></i>Estatísticas Gerais
            </a>
            <a href="#charts" class="block px-4 py-3 border-b hover:bg-gray-50 text-gray-700">
                <i class="fas fa-chart-area mr-2 text-green-600"></i>Gráficos
            </a>
            <a href="#campaigns" class="block px-4 py-3 border-b hover:bg-gray-50 text-gray-700">
                <i class="fas fa-bullhorn mr-2 text-purple-600"></i>Campanhas Recentes
            </a>
            <a href="#responses" class="block px-4 py-3 hover:bg-gray-50 text-gray-700">
                <i class="fas fa-comments mr-2 text-orange-600"></i>Respostas Recentes
            </a>
        </div>
    </div>

    <!-- Header -->
    <div class="mb-4 md:mb-6" id="stats">
        <h1 class="text-xl md:text-3xl font-bold text-gray-800 mb-1 md:mb-2">
            <i class="fas fa-chart-line mr-2 md:mr-3 text-blue-600"></i>Relatórios de Disparo
        </h1>
        <p class="text-sm md:text-base text-gray-600">Análise completa de campanhas e respostas</p>
    </div>

    <!-- Acesso Rápido às Funcionalidades Avançadas -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 md:gap-4 mb-4 md:mb-6">
        <a href="/dispatch_settings.php" class="bg-white border-2 border-blue-200 hover:border-blue-500 rounded-lg p-3 md:p-4 flex items-center gap-3 transition-all hover:shadow-lg group">
            <div class="w-10 h-10 md:w-12 md:h-12 bg-blue-100 rounded-lg flex items-center justify-center group-hover:bg-blue-500 transition-colors">
                <i class="fas fa-clock text-blue-600 group-hover:text-white text-lg md:text-xl"></i>
            </div>
            <div>
                <div class="font-semibold text-gray-800 text-sm md:text-base">Melhor Horário</div>
                <div class="text-xs text-gray-500">Análise preditiva</div>
            </div>
        </a>
        
        <a href="/dispatch_settings.php#segmentation" onclick="localStorage.setItem('dispatchSettingsTab', 'segmentation')" class="bg-white border-2 border-green-200 hover:border-green-500 rounded-lg p-3 md:p-4 flex items-center gap-3 transition-all hover:shadow-lg group">
            <div class="w-10 h-10 md:w-12 md:h-12 bg-green-100 rounded-lg flex items-center justify-center group-hover:bg-green-500 transition-colors">
                <i class="fas fa-users text-green-600 group-hover:text-white text-lg md:text-xl"></i>
            </div>
            <div>
                <div class="font-semibold text-gray-800 text-sm md:text-base">Segmentação</div>
                <div class="text-xs text-gray-500">Por engajamento</div>
            </div>
        </a>
        
        <a href="/dispatch_settings.php#abtesting" onclick="localStorage.setItem('dispatchSettingsTab', 'abtesting')" class="bg-white border-2 border-purple-200 hover:border-purple-500 rounded-lg p-3 md:p-4 flex items-center gap-3 transition-all hover:shadow-lg group">
            <div class="w-10 h-10 md:w-12 md:h-12 bg-purple-100 rounded-lg flex items-center justify-center group-hover:bg-purple-500 transition-colors">
                <i class="fas fa-flask text-purple-600 group-hover:text-white text-lg md:text-xl"></i>
            </div>
            <div>
                <div class="font-semibold text-gray-800 text-sm md:text-base">A/B Testing</div>
                <div class="text-xs text-gray-500">Testar mensagens</div>
            </div>
        </a>
        
        <a href="/dispatch_settings.php#crm" onclick="localStorage.setItem('dispatchSettingsTab', 'crm')" class="bg-white border-2 border-indigo-200 hover:border-indigo-500 rounded-lg p-3 md:p-4 flex items-center gap-3 transition-all hover:shadow-lg group">
            <div class="w-10 h-10 md:w-12 md:h-12 bg-indigo-100 rounded-lg flex items-center justify-center group-hover:bg-indigo-500 transition-colors">
                <i class="fas fa-plug text-indigo-600 group-hover:text-white text-lg md:text-xl"></i>
            </div>
            <div>
                <div class="font-semibold text-gray-800 text-sm md:text-base">Integração CRM</div>
                <div class="text-xs text-gray-500">Pipedrive, HubSpot</div>
            </div>
        </a>
    </div>

    <!-- Estatísticas Gerais - Cards Empilhados Mobile -->
    <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-2 md:gap-4 mb-4 md:mb-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg p-3 md:p-6 text-white shadow-lg">
            <div class="flex items-center justify-between mb-1 md:mb-2">
                <i class="fas fa-bullhorn text-xl md:text-3xl opacity-80"></i>
                <span class="text-[10px] md:text-xs bg-white/20 px-1.5 md:px-2 py-0.5 md:py-1 rounded">Total</span>
            </div>
            <div class="text-xl md:text-3xl font-bold mb-0.5 md:mb-1"><?php echo number_format($generalStats['total_campaigns']); ?></div>
            <div class="text-xs md:text-sm opacity-90">Campanhas</div>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg p-3 md:p-6 text-white shadow-lg">
            <div class="flex items-center justify-between mb-1 md:mb-2">
                <i class="fas fa-paper-plane text-xl md:text-3xl opacity-80"></i>
                <span class="text-[10px] md:text-xs bg-white/20 px-1.5 md:px-2 py-0.5 md:py-1 rounded"><?php echo $successRate; ?>%</span>
            </div>
            <div class="text-xl md:text-3xl font-bold mb-0.5 md:mb-1"><?php echo number_format($generalStats['total_sent']); ?></div>
            <div class="text-xs md:text-sm opacity-90">Enviadas</div>
        </div>

        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg p-3 md:p-6 text-white shadow-lg">
            <div class="flex items-center justify-between mb-1 md:mb-2">
                <i class="fas fa-reply text-xl md:text-3xl opacity-80"></i>
                <span class="text-[10px] md:text-xs bg-white/20 px-1.5 md:px-2 py-0.5 md:py-1 rounded"><?php echo $responseRate; ?>%</span>
            </div>
            <div class="text-xl md:text-3xl font-bold mb-0.5 md:mb-1"><?php echo number_format($generalStats['total_responses']); ?></div>
            <div class="text-xs md:text-sm opacity-90">Respostas</div>
        </div>

        <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-lg p-3 md:p-6 text-white shadow-lg">
            <div class="flex items-center justify-between mb-1 md:mb-2">
                <i class="fas fa-exclamation-triangle text-xl md:text-3xl opacity-80"></i>
                <span class="text-[10px] md:text-xs bg-white/20 px-1.5 md:px-2 py-0.5 md:py-1 rounded">Falhas</span>
            </div>
            <div class="text-xl md:text-3xl font-bold mb-0.5 md:mb-1"><?php echo number_format($generalStats['total_failed']); ?></div>
            <div class="text-xs md:text-sm opacity-90">Falhados</div>
        </div>

        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-lg p-3 md:p-6 text-white shadow-lg col-span-2 sm:col-span-1">
            <div class="flex items-center justify-between mb-1 md:mb-2">
                <i class="fas fa-percentage text-xl md:text-3xl opacity-80"></i>
                <span class="text-[10px] md:text-xs bg-white/20 px-1.5 md:px-2 py-0.5 md:py-1 rounded">Taxa</span>
            </div>
            <div class="text-xl md:text-3xl font-bold mb-0.5 md:mb-1"><?php echo $responseRate; ?>%</div>
            <div class="text-xs md:text-sm opacity-90">Taxa de Resposta</div>
        </div>
    </div>

    <!-- Gráficos - Responsivos -->
    <div id="charts" class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6 mb-4 md:mb-6">
        <!-- Gráfico de Tendência -->
        <div class="bg-white rounded-lg shadow-lg p-4 md:p-6">
            <h3 class="text-sm md:text-lg font-bold text-gray-800 mb-3 md:mb-4">
                <i class="fas fa-chart-area mr-2 text-blue-600"></i>
                <span class="hidden sm:inline">Tendência de Disparos (30 dias)</span>
                <span class="sm:hidden">Tendência (30d)</span>
            </h3>
            <div class="relative" style="height: 200px; min-height: 200px;">
                <canvas id="dispatchTrendChart"></canvas>
            </div>
        </div>

        <!-- Gráfico de Sentimento -->
        <div class="bg-white rounded-lg shadow-lg p-4 md:p-6">
            <h3 class="text-sm md:text-lg font-bold text-gray-800 mb-3 md:mb-4">
                <i class="fas fa-smile mr-2 text-purple-600"></i>
                <span class="hidden sm:inline">Análise de Sentimento</span>
                <span class="sm:hidden">Sentimento</span>
            </h3>
            <div class="relative" style="height: 200px; min-height: 200px;">
                <canvas id="sentimentChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Campanhas Recentes -->
    <div id="campaigns" class="bg-white rounded-lg shadow-lg p-4 md:p-6 mb-4 md:mb-6">
        <div class="flex items-center justify-between mb-3 md:mb-4">
            <h3 class="text-sm md:text-lg font-bold text-gray-800">
                <i class="fas fa-bullhorn mr-2 text-green-600"></i>
                <span class="hidden sm:inline">Campanhas Recentes</span>
                <span class="sm:hidden">Campanhas</span>
            </h3>
            <a href="/dispatch.php" class="text-blue-600 hover:text-blue-700 text-xs md:text-sm font-medium">
                <i class="fas fa-plus mr-1"></i><span class="hidden sm:inline">Nova Campanha</span><span class="sm:hidden">Nova</span>
            </a>
        </div>

        <?php if (empty($recentCampaigns)): ?>
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-inbox text-4xl mb-3"></i>
                <p>Nenhuma campanha encontrada</p>
                <a href="/dispatch.php" class="text-blue-600 hover:text-blue-700 text-sm mt-2 inline-block">
                    Criar primeira campanha
                </a>
            </div>
        <?php else: ?>
            <!-- Mobile Cards View -->
            <div class="md:hidden space-y-3">
                <?php foreach ($recentCampaigns as $campaign): ?>
                <?php
                $statusColors = [
                    'draft' => 'bg-gray-100 text-gray-800',
                    'in_progress' => 'bg-blue-100 text-blue-800',
                    'completed' => 'bg-green-100 text-green-800',
                    'cancelled' => 'bg-red-100 text-red-800'
                ];
                $statusLabels = [
                    'draft' => 'Rascunho',
                    'in_progress' => 'Em Andamento',
                    'completed' => 'Concluída',
                    'cancelled' => 'Cancelada'
                ];
                $statusClass = $statusColors[$campaign['status']] ?? 'bg-gray-100 text-gray-800';
                $statusLabel = $statusLabels[$campaign['status']] ?? $campaign['status'];
                ?>
                <div class="border border-gray-200 rounded-lg p-4 touch-pan-y">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <div class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($campaign['name']); ?></div>
                            <div class="text-xs text-gray-500"><?php echo date('d/m/Y H:i', strtotime($campaign['created_at'])); ?></div>
                        </div>
                        <span class="px-2 py-1 <?php echo $statusClass; ?> rounded text-xs font-medium">
                            <?php echo $statusLabel; ?>
                        </span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center mb-3">
                        <div class="bg-gray-50 rounded p-2">
                            <div class="text-lg font-bold text-gray-800"><?php echo $campaign['sent_count']; ?></div>
                            <div class="text-[10px] text-gray-500">Enviadas</div>
                        </div>
                        <div class="bg-purple-50 rounded p-2">
                            <div class="text-lg font-bold text-purple-600"><?php echo $campaign['response_count']; ?></div>
                            <div class="text-[10px] text-gray-500">Respostas</div>
                        </div>
                        <div class="bg-green-50 rounded p-2">
                            <div class="text-lg font-bold text-green-600"><?php echo $campaign['response_rate']; ?>%</div>
                            <div class="text-[10px] text-gray-500">Taxa</div>
                        </div>
                    </div>
                    <div class="flex items-center mb-3">
                        <div class="flex-1 bg-gray-200 rounded-full h-2 mr-2">
                            <div class="bg-green-600 h-2 rounded-full transition-all" style="width: <?php echo $campaign['progress_percent']; ?>%"></div>
                        </div>
                        <span class="text-xs text-gray-600 font-medium"><?php echo $campaign['progress_percent']; ?>%</span>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button onclick="viewCampaignDetails(<?php echo $campaign['id']; ?>)" 
                                class="flex items-center text-blue-600 hover:text-blue-700 text-sm py-2 px-3 bg-blue-50 rounded-lg">
                            <i class="fas fa-eye mr-1"></i>Detalhes
                        </button>
                        <button onclick="exportCampaign(<?php echo $campaign['id']; ?>)" 
                                class="flex items-center text-green-600 hover:text-green-700 text-sm py-2 px-3 bg-green-50 rounded-lg">
                            <i class="fas fa-download mr-1"></i>Exportar
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Desktop Table View -->
            <div class="hidden md:block overflow-x-auto -mx-4 md:mx-0">
                <div class="inline-block min-w-full align-middle">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase whitespace-nowrap">Campanha</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase whitespace-nowrap">Status</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase whitespace-nowrap">Enviadas</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase whitespace-nowrap">Respostas</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase whitespace-nowrap">Taxa</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase whitespace-nowrap">Progresso</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase whitespace-nowrap">Ações</th>
                            </tr>
                        </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($recentCampaigns as $campaign): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-800"><?php echo htmlspecialchars($campaign['name']); ?></div>
                                <div class="text-xs text-gray-500">
                                    <?php echo date('d/m/Y H:i', strtotime($campaign['created_at'])); ?>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <?php
                                $statusColors = [
                                    'draft' => 'bg-gray-100 text-gray-800',
                                    'in_progress' => 'bg-blue-100 text-blue-800',
                                    'completed' => 'bg-green-100 text-green-800',
                                    'cancelled' => 'bg-red-100 text-red-800'
                                ];
                                $statusLabels = [
                                    'draft' => 'Rascunho',
                                    'in_progress' => 'Em Andamento',
                                    'completed' => 'Concluída',
                                    'cancelled' => 'Cancelada'
                                ];
                                $statusClass = $statusColors[$campaign['status']] ?? 'bg-gray-100 text-gray-800';
                                $statusLabel = $statusLabels[$campaign['status']] ?? $campaign['status'];
                                ?>
                                <span class="px-2 py-1 <?php echo $statusClass; ?> rounded text-xs font-medium">
                                    <?php echo $statusLabel; ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="font-semibold"><?php echo $campaign['sent_count']; ?></span>
                                <span class="text-gray-500">/ <?php echo $campaign['total_contacts']; ?></span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="font-semibold text-purple-600"><?php echo $campaign['response_count']; ?></span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="font-semibold text-green-600"><?php echo $campaign['response_rate']; ?>%</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center">
                                    <div class="flex-1 bg-gray-200 rounded-full h-2 mr-2">
                                        <div class="bg-green-600 h-2 rounded-full" style="width: <?php echo $campaign['progress_percent']; ?>%"></div>
                                    </div>
                                    <span class="text-xs text-gray-600"><?php echo $campaign['progress_percent']; ?>%</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button onclick="viewCampaignDetails(<?php echo $campaign['id']; ?>)" 
                                        class="text-blue-600 hover:text-blue-700 mr-2" title="Ver detalhes">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button onclick="exportCampaign(<?php echo $campaign['id']; ?>)" 
                                        class="text-green-600 hover:text-green-700" title="Exportar">
                                    <i class="fas fa-download"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Respostas Recentes -->
    <div id="responses" class="bg-white rounded-lg shadow-lg p-4 md:p-6">
        <h3 class="text-sm md:text-lg font-bold text-gray-800 mb-3 md:mb-4">
            <i class="fas fa-comments mr-2 text-purple-600"></i>
            <span class="hidden sm:inline">Respostas Recentes</span>
            <span class="sm:hidden">Respostas</span>
        </h3>

        <?php if (empty($recentResponses)): ?>
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-inbox text-4xl mb-3"></i>
                <p>Nenhuma resposta recebida ainda</p>
            </div>
        <?php else: ?>
            <div class="space-y-3 max-h-[400px] md:max-h-96 overflow-y-auto touch-pan-y" id="responsesContainer">
                <?php foreach ($recentResponses as $response): ?>
                <div class="border border-gray-200 rounded-lg p-3 md:p-4 hover:bg-gray-50 transition touch-manipulation">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-1 mb-1">
                                <span class="font-semibold text-gray-800 text-sm md:text-base truncate">
                                    <?php echo htmlspecialchars($response['contact_name'] ?: 'Contato'); ?>
                                </span>
                                <span class="text-xs md:text-sm text-gray-500"><?php echo $response['phone']; ?></span>
                                <?php if ($response['is_first_response']): ?>
                                <span class="px-1.5 md:px-2 py-0.5 bg-blue-100 text-blue-800 rounded text-[10px] md:text-xs">
                                    1ª resp.
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="text-[10px] md:text-xs text-gray-500 mb-2 flex flex-wrap gap-1">
                                <span class="hidden sm:inline">Campanha: <?php echo htmlspecialchars($response['campaign_name'] ?: 'N/A'); ?> •</span>
                                <span><?php echo date('d/m H:i', strtotime($response['received_at'])); ?></span>
                                <span class="hidden sm:inline">• Tempo: <?php echo gmdate('H:i:s', $response['response_time_seconds']); ?></span>
                            </div>
                        </div>
                        <div class="text-xl md:text-2xl ml-2 flex-shrink-0">
                        <?php
                        $sentimentIcons = [
                            'positive' => '<i class="fas fa-smile text-green-600"></i>',
                            'neutral' => '<i class="fas fa-meh text-gray-600"></i>',
                            'negative' => '<i class="fas fa-frown text-red-600"></i>',
                            'unknown' => '<i class="fas fa-question text-gray-400"></i>'
                        ];
                        echo $sentimentIcons[$response['sentiment']] ?? '';
                        ?>
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded p-2 md:p-3 text-xs md:text-sm text-gray-700 break-words">
                        <?php echo nl2br(htmlspecialchars($response['message_text'])); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Scroll to Top Button (Mobile) -->
    <button id="scrollTopBtn" onclick="scrollToTop()" 
            class="lg:hidden fixed bottom-6 right-6 bg-blue-600 text-white w-12 h-12 rounded-full shadow-lg flex items-center justify-center opacity-0 transition-opacity duration-300 z-40">
        <i class="fas fa-arrow-up"></i>
    </button>
</div>

<!-- Modal de Detalhes da Campanha -->
<div id="campaignModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b px-6 py-4 flex justify-between items-center">
            <h3 class="text-xl font-bold text-gray-800">
                <i class="fas fa-chart-bar mr-2 text-blue-600"></i>Detalhes da Campanha
            </h3>
            <button onclick="closeCampaignModal()" class="text-gray-500 hover:text-gray-700 text-2xl">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="campaignDetails" class="p-6">
            <div class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-4xl text-blue-600"></i>
                <p class="mt-2 text-gray-600">Carregando...</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Dados para gráficos
const dispatchTrendData = <?php echo json_encode($dispatchTrend); ?>;
const sentimentData = <?php echo json_encode($sentimentData); ?>;

// Gráfico de Tendência
const trendCtx = document.getElementById('dispatchTrendChart').getContext('2d');
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: dispatchTrendData.map(d => {
            const date = new Date(d.date);
            return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
        }),
        datasets: [{
            label: 'Enviadas',
            data: dispatchTrendData.map(d => d.sent),
            borderColor: 'rgb(34, 197, 94)',
            backgroundColor: 'rgba(34, 197, 94, 0.1)',
            tension: 0.4,
            fill: true
        }, {
            label: 'Falhadas',
            data: dispatchTrendData.map(d => d.failed),
            borderColor: 'rgb(239, 68, 68)',
            backgroundColor: 'rgba(239, 68, 68, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Gráfico de Sentimento
const sentimentCtx = document.getElementById('sentimentChart').getContext('2d');
new Chart(sentimentCtx, {
    type: 'doughnut',
    data: {
        labels: ['Positivo', 'Neutro', 'Negativo'],
        datasets: [{
            data: [sentimentData.positive, sentimentData.neutral, sentimentData.negative],
            backgroundColor: [
                'rgb(34, 197, 94)',
                'rgb(156, 163, 175)',
                'rgb(239, 68, 68)'
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
            }
        }
    }
});

async function viewCampaignDetails(campaignId) {
    document.getElementById('campaignModal').classList.remove('hidden');
    
    try {
        const response = await fetch(`api/dispatch_campaigns.php?action=stats&id=${campaignId}`);
        const data = await response.json();
        
        if (data.success) {
            displayCampaignDetails(data);
        } else {
            throw new Error(data.error || 'Erro ao carregar detalhes');
        }
    } catch (error) {
        document.getElementById('campaignDetails').innerHTML = `
            <div class="text-center py-8 text-red-600">
                <i class="fas fa-exclamation-triangle text-4xl mb-3"></i>
                <p>${error.message}</p>
            </div>
        `;
    }
}

function displayCampaignDetails(data) {
    const dispatch = data.dispatch_stats;
    const response = data.response_stats;
    
    const html = `
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-green-600">${dispatch.sent || 0}</div>
                <div class="text-sm text-gray-600">Enviadas</div>
            </div>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-blue-600">${dispatch.delivered || 0}</div>
                <div class="text-sm text-gray-600">Entregues</div>
            </div>
            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-purple-600">${dispatch.read || 0}</div>
                <div class="text-sm text-gray-600">Lidas</div>
            </div>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-red-600">${dispatch.failed || 0}</div>
                <div class="text-sm text-gray-600">Falhadas</div>
            </div>
        </div>
        
        <h4 class="font-bold text-gray-800 mb-3">Estatísticas de Resposta</h4>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <div class="text-2xl font-bold text-gray-800">${response.total_responses || 0}</div>
                <div class="text-sm text-gray-600">Total de Respostas</div>
            </div>
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <div class="text-2xl font-bold text-gray-800">${response.first_responses || 0}</div>
                <div class="text-sm text-gray-600">Primeiras Respostas</div>
            </div>
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <div class="text-2xl font-bold text-gray-800">${Math.round(response.avg_response_time / 60) || 0}min</div>
                <div class="text-sm text-gray-600">Tempo Médio</div>
            </div>
        </div>
        
        <h4 class="font-bold text-gray-800 mb-3">Análise de Sentimento</h4>
        <div class="grid grid-cols-3 gap-4">
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                <i class="fas fa-smile text-3xl text-green-600 mb-2"></i>
                <div class="text-2xl font-bold text-green-600">${response.positive || 0}</div>
                <div class="text-sm text-gray-600">Positivas</div>
            </div>
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center">
                <i class="fas fa-meh text-3xl text-gray-600 mb-2"></i>
                <div class="text-2xl font-bold text-gray-600">${response.neutral || 0}</div>
                <div class="text-sm text-gray-600">Neutras</div>
            </div>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">
                <i class="fas fa-frown text-3xl text-red-600 mb-2"></i>
                <div class="text-2xl font-bold text-red-600">${response.negative || 0}</div>
                <div class="text-sm text-gray-600">Negativas</div>
            </div>
        </div>
    `;
    
    document.getElementById('campaignDetails').innerHTML = html;
}

function closeCampaignModal() {
    document.getElementById('campaignModal').classList.add('hidden');
}

async function exportCampaign(campaignId) {
    try {
        const response = await fetch(`api/export_campaign.php?id=${campaignId}`);
        const blob = await response.blob();
        
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `campanha_${campaignId}_${Date.now()}.csv`;
        a.click();
        window.URL.revokeObjectURL(url);
        
        showMessage('success', 'Relatório exportado com sucesso!');
    } catch (error) {
        showMessage('error', 'Erro ao exportar relatório');
    }
}

// ==========================================
// MOBILE MENU & TOUCH GESTURES
// ==========================================

function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    const icon = document.getElementById('mobileMenuIcon');
    
    menu.classList.toggle('hidden');
    icon.classList.toggle('rotate-180');
}

// Close mobile menu when clicking on a link
document.querySelectorAll('#mobileMenu a').forEach(link => {
    link.addEventListener('click', () => {
        document.getElementById('mobileMenu').classList.add('hidden');
        document.getElementById('mobileMenuIcon').classList.remove('rotate-180');
    });
});

// Scroll to Top functionality
function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Show/hide scroll to top button
window.addEventListener('scroll', () => {
    const scrollBtn = document.getElementById('scrollTopBtn');
    if (scrollBtn) {
        if (window.scrollY > 300) {
            scrollBtn.style.opacity = '1';
            scrollBtn.style.pointerEvents = 'auto';
        } else {
            scrollBtn.style.opacity = '0';
            scrollBtn.style.pointerEvents = 'none';
        }
    }
});

// Touch gestures for responses container
let touchStartY = 0;
let touchEndY = 0;

const responsesContainer = document.getElementById('responsesContainer');
if (responsesContainer) {
    responsesContainer.addEventListener('touchstart', (e) => {
        touchStartY = e.changedTouches[0].screenY;
    }, { passive: true });
    
    responsesContainer.addEventListener('touchend', (e) => {
        touchEndY = e.changedTouches[0].screenY;
        handleSwipe();
    }, { passive: true });
}

function handleSwipe() {
    const swipeDistance = touchStartY - touchEndY;
    // Pull to refresh simulation (swipe down at top)
    if (swipeDistance < -100 && responsesContainer.scrollTop === 0) {
        showMessage('info', 'Atualizando respostas...');
        setTimeout(() => location.reload(), 500);
    }
}

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});

// Resize charts on orientation change
window.addEventListener('orientationchange', () => {
    setTimeout(() => {
        window.dispatchEvent(new Event('resize'));
    }, 100);
});

// ==========================================
// TOAST MESSAGES
// ==========================================

function showMessage(type, message) {
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 max-w-sm ${
        type === 'success' ? 'bg-green-500 text-white' :
        type === 'error' ? 'bg-red-500 text-white' :
        'bg-blue-500 text-white'
    }`;
    toast.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}
</script>

<?php require_once 'includes/footer.php'; ?>
