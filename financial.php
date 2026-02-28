<?php
$page_title = 'Financeiro - Planos e Faturamento';
require_once 'includes/header_spa.php';

// Verificar se é o usuário autorizado (suporte@macip.com.br)
$isFinancialUser = ($user_email === 'suporte@macip.com.br');

if (!$isFinancialUser) {
    echo '<div class="refined-container">
        <div class="bg-red-50 border-l-4 border-red-500 p-4">
            <div class="flex">
                <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                <div>
                    <h3 class="text-lg font-medium text-red-800">Acesso Negado</h3>
                    <p class="text-red-700">Você não tem permissão para acessar esta página.</p>
                </div>
            </div>
        </div>
    </div>';
    require_once 'includes/footer_spa.php';
    exit;
}

$pricingPlans = getPricingPlans();
$activePlans = array_values(array_filter($pricingPlans, function ($plan) {
    return (int) ($plan['is_active'] ?? 1) === 1;
}));

if (empty($activePlans)) {
    $activePlans = $pricingPlans;
}

$planMeta = [];
foreach ($pricingPlans as $plan) {
    $planMeta[$plan['slug']] = [
        'name' => $plan['name'],
        'price' => (float) $plan['price'],
        'limit' => (int) $plan['message_limit'],
        'color' => getPlanColor($plan['slug'])
    ];
}

$statsTotalsStmt = $pdo->query("
    SELECT 
        COUNT(*) as total_users,
        SUM(messages_sent) as total_messages
    FROM users 
    WHERE is_admin = 0
");
$statsTotals = $statsTotalsStmt->fetch() ?: [];

$planCountsStmt = $pdo->query("
    SELECT plan, COUNT(*) as count
    FROM users
    WHERE is_admin = 0
    GROUP BY plan
");
$planUserCounts = [];
foreach ($planCountsStmt as $row) {
    $slug = $row['plan'] ?: 'free';
    $planUserCounts[$slug] = (int) $row['count'];
}

$stats = [
    'total_users' => (int) ($statsTotals['total_users'] ?? 0),
    'total_messages' => (int) ($statsTotals['total_messages'] ?? 0),
];

$mrr = 0;
foreach ($pricingPlans as $plan) {
    $count = $planUserCounts[$plan['slug']] ?? 0;
    $mrr += $planMeta[$plan['slug']]['price'] * $count;
}

// Calcular faturamento total (considerando mensagens enviadas)
$totalRevenue = $mrr; // Simplificado - em produção, buscar do histórico de pagamentos

// Buscar histórico de mensagens dos últimos 30 dias para gráfico de linha
$stmt = $pdo->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as messages
    FROM dispatch_history
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$messageHistory = $stmt->fetchAll();

// Buscar top 10 clientes que mais enviam mensagens
$stmt = $pdo->query("
    SELECT 
        u.name,
        u.email,
        u.plan,
        u.messages_sent,
        u.plan_limit
    FROM users u
    WHERE u.is_admin = 0
    ORDER BY u.messages_sent DESC
    LIMIT 10
");
$topUsers = $stmt->fetchAll();

// Preparar dados para gráficos
$chartDates = [];
$chartMessages = [];
foreach ($messageHistory as $row) {
    $chartDates[] = date('d/m', strtotime($row['date']));
    $chartMessages[] = $row['messages'];
}

$topUserNames = [];
$topUserMessages = [];
foreach ($topUsers as $user) {
    $topUserNames[] = substr($user['name'], 0, 20);
    $topUserMessages[] = $user['messages_sent'];
}
?>

<div class="refined-container">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">
            <i class="fas fa-chart-line text-green-600 mr-3"></i>Financeiro
        </h1>
        <p class="text-gray-600">Visão completa de planos, faturamento e métricas financeiras</p>
    </div>

    <!-- Cards de Resumo -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- MRR -->
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-lg p-6 text-white">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium opacity-90">MRR (Receita Mensal)</h3>
                <i class="fas fa-dollar-sign text-2xl opacity-80"></i>
            </div>
            <p class="text-3xl font-bold">R$ <?php echo number_format($mrr, 2, ',', '.'); ?></p>
            <p class="text-sm opacity-80 mt-2">Receita recorrente mensal</p>
        </div>

        <!-- Total de Clientes -->
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-lg p-6 text-white">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium opacity-90">Total de Clientes</h3>
                <i class="fas fa-users text-2xl opacity-80"></i>
            </div>
            <p class="text-3xl font-bold"><?php echo number_format($stats['total_users']); ?></p>
            <p class="text-sm opacity-80 mt-2">Usuários cadastrados</p>
        </div>

        <!-- Total de Mensagens -->
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow-lg p-6 text-white">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium opacity-90">Total de Mensagens</h3>
                <i class="fas fa-paper-plane text-2xl opacity-80"></i>
            </div>
            <p class="text-3xl font-bold"><?php echo number_format($stats['total_messages']); ?></p>
            <p class="text-sm opacity-80 mt-2">Mensagens enviadas</p>
        </div>

        <!-- Faturamento Total -->
        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-lg shadow-lg p-6 text-white">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium opacity-90">Faturamento Total</h3>
                <i class="fas fa-chart-bar text-2xl opacity-80"></i>
            </div>
            <p class="text-3xl font-bold">R$ <?php echo number_format($totalRevenue, 2, ',', '.'); ?></p>
            <p class="text-sm opacity-80 mt-2">Receita acumulada</p>
        </div>
    </div>

    <!-- Planos e Valores -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold text-gray-800">
                <i class="fas fa-tags text-green-600 mr-2"></i>Planos e Valores
            </h2>
            <button onclick="openPlanManager()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition">
                <i class="fas fa-cog mr-2"></i>Gerenciar Planos
            </button>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-stretch">
            <?php foreach ($pricingPlans as $plan): 
                $slug = $plan['slug'];
                $meta = $planMeta[$slug];
                $userCount = $planUserCounts[$slug] ?? 0;
                $revenue = $meta['price'] * $userCount;
            ?>
            <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-green-500 transition flex flex-col">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($meta['name']); ?></h3>
                    <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                        <?php echo $userCount . ' ' . ($userCount == 1 ? 'usuário' : 'usuários'); ?>
                    </span>
                </div>
                
                <div class="mb-3 flex-grow">
                    <p class="text-3xl font-bold text-green-600">
                        R$ <?php echo number_format($meta['price'], 2, ',', '.'); ?>
                    </p>
                    <p class="text-sm text-gray-500">por mês</p>
                </div>
                
                <div class="border-t pt-3 mt-auto">
                    <p class="text-sm text-gray-600 mb-1">
                        <i class="fas fa-check text-green-500 mr-2"></i>
                        <?php echo number_format($meta['limit']); ?> mensagens
                    </p>
                    <p class="text-sm font-medium text-gray-700">
                        Faturamento: R$ <?php echo number_format($revenue, 2, ',', '.'); ?>
                    </p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Gráfico de Linha - Mensagens ao Longo do Tempo -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-chart-line text-blue-600 mr-2"></i>Mensagens nos Últimos 30 Dias
            </h2>
            <div style="position: relative; height: 300px;">
                <canvas id="messagesChart"></canvas>
            </div>
        </div>

        <!-- Gráfico de Barras - Top 10 Clientes -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-chart-bar text-purple-600 mr-2"></i>Top 10 Clientes por Mensagens
            </h2>
            <div style="position: relative; height: 300px;">
                <canvas id="topUsersChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Distribuição de Planos -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">
            <i class="fas fa-pie-chart text-orange-600 mr-2"></i>Distribuição de Clientes por Plano
        </h2>
        <div class="flex justify-center">
            <div style="position: relative; width: 400px; height: 400px; max-width: 100%;">
                <canvas id="planDistributionChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Tabela Detalhada - Top Clientes -->
    <div class="bg-white rounded-lg shadow-lg p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">
            <i class="fas fa-trophy text-yellow-600 mr-2"></i>Detalhamento dos Top Clientes
        </h2>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Plano</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mensagens Enviadas</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Limite</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Uso</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($topUsers as $index => $user): 
                        $usage = ($user['messages_sent'] / max($user['plan_limit'], 1)) * 100;
                        $usageColor = $usage >= 90 ? 'red' : ($usage >= 70 ? 'yellow' : 'green');
                        $userPlanSlug = $user['plan'] ?: 'free';
                        $userPlanName = $planMeta[$userPlanSlug]['name'] ?? ucfirst($userPlanSlug);
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            <?php echo $index + 1; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($user['name']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            <?php echo htmlspecialchars($user['email']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-medium">
                                <?php echo htmlspecialchars($userPlanName); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            <?php echo number_format($user['messages_sent']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            <?php echo number_format($user['plan_limit']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <div class="flex items-center">
                                <div class="w-full bg-gray-200 rounded-full h-2 mr-2">
                                    <div class="bg-<?php echo $usageColor; ?>-500 h-2 rounded-full" 
                                         style="width: <?php echo min($usage, 100); ?>%"></div>
                                </div>
                                <span class="text-<?php echo $usageColor; ?>-600 font-medium">
                                    <?php echo number_format($usage, 1); ?>%
                                </span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- CSS e JS do Modal de Planos com Abas -->
<link rel="stylesheet" href="assets/css/plan-modal-tabs.css">
<script src="assets/js/plan-modal-tabs.js"></script>

<script>
// Gráfico de Linha - Mensagens ao Longo do Tempo
const messagesCtx = document.getElementById('messagesChart').getContext('2d');
new Chart(messagesCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chartDates); ?>,
        datasets: [{
            label: 'Mensagens Enviadas',
            data: <?php echo json_encode($chartMessages); ?>,
            borderColor: 'rgb(59, 130, 246)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            },
            tooltip: {
                mode: 'index',
                intersect: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString();
                    }
                }
            }
        }
    }
});

// Gráfico de Barras - Top 10 Clientes
const topUsersCtx = document.getElementById('topUsersChart').getContext('2d');
new Chart(topUsersCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($topUserNames); ?>,
        datasets: [{
            label: 'Mensagens Enviadas',
            data: <?php echo json_encode($topUserMessages); ?>,
            backgroundColor: 'rgba(147, 51, 234, 0.8)',
            borderColor: 'rgb(147, 51, 234)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.parsed.y.toLocaleString() + ' mensagens';
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString();
                    }
                }
            },
            x: {
                ticks: {
                    maxRotation: 45,
                    minRotation: 45
                }
            }
        }
    }
});

// Gráfico de Pizza - Distribuição de Planos
const planDistCtx = document.getElementById('planDistributionChart').getContext('2d');
new Chart(planDistCtx, {
    type: 'doughnut',
    data: {
        labels: ['Gratuito', 'Básico', 'Pro', 'Enterprise'],
        datasets: [{
            data: [
                <?php echo $stats['free_users']; ?>,
                <?php echo $stats['basic_users']; ?>,
                <?php echo $stats['pro_users']; ?>,
                <?php echo $stats['enterprise_users']; ?>
            ],
            backgroundColor: [
                'rgba(156, 163, 175, 0.8)',
                'rgba(59, 130, 246, 0.8)',
                'rgba(147, 51, 234, 0.8)',
                'rgba(16, 185, 129, 0.8)'
            ],
            borderColor: [
                'rgb(156, 163, 175)',
                'rgb(59, 130, 246)',
                'rgb(147, 51, 234)',
                'rgb(16, 185, 129)'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    font: {
                        size: 14
                    }
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.parsed || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((value / total) * 100).toFixed(1);
                        return label + ': ' + value + ' (' + percentage + '%)';
                    }
                }
            }
        }
    }
});
</script>

<!-- Modal de Gerenciamento de Planos -->
<div id="planManagerModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" onclick="closePlanManager(event)">
    <div class="bg-white rounded-2xl shadow-2xl max-w-6xl w-full max-h-[90vh] overflow-hidden" onclick="event.stopPropagation()">
        <!-- Header -->
        <div class="bg-gradient-to-r from-green-600 to-green-500 p-6 text-white">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-bold">
                        <i class="fas fa-cog mr-2"></i>Gerenciamento de Planos
                    </h2>
                    <p class="text-green-100 text-sm mt-1">Edite os planos exibidos na landing page</p>
                </div>
                <button onclick="closePlanManager()" class="text-white hover:text-gray-200 transition">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Conteúdo -->
        <div class="p-6 overflow-y-auto" style="max-height: calc(90vh - 140px);">
            <!-- Alertas -->
            <div id="planAlert" class="hidden mb-4 p-4 rounded-lg"></div>
            
            <!-- Botão Adicionar Novo Plano -->
            <div class="mb-6">
                <button onclick="openPlanForm()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition">
                    <i class="fas fa-plus mr-2"></i>Adicionar Novo Plano
                </button>
            </div>
            
            <!-- Lista de Planos -->
            <div id="plansList" class="space-y-4">
                <!-- Será preenchido via JavaScript -->
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/plan_modal_with_tabs.php'; ?>

<script>
// Gerenciamento de Planos
let currentPlans = [];

function openPlanManager() {
    document.getElementById('planManagerModal').classList.remove('hidden');
    loadPlans();
}

function closePlanManager(event) {
    if (!event || event.target.id === 'planManagerModal') {
        document.getElementById('planManagerModal').classList.add('hidden');
    }
}

function openPlanForm(plan = null) {
    const modal = document.getElementById('planFormModal');
    const form = document.getElementById('planForm');
    const title = document.getElementById('planFormTitle');
    
    form.reset();
    
    // Sempre voltar para a primeira aba
    switchPlanTab('basic');
    
    if (plan) {
        // Editar plano existente
        title.innerHTML = '<i class="fas fa-edit mr-2"></i>Editar Plano';
        document.getElementById('plan_id').value = plan.id;
        document.getElementById('plan_slug').value = plan.slug;
        document.getElementById('plan_name').value = plan.name;
        document.getElementById('plan_price').value = plan.price;
        document.getElementById('plan_sort_order').value = plan.sort_order;
        document.getElementById('plan_is_active').checked = plan.is_active == 1;
        document.getElementById('plan_is_popular').checked = plan.is_popular == 1;
        
        // Carregar features do plano
        loadPlanFeatures(plan.id);
    } else {
        // Novo plano
        title.innerHTML = '<i class="fas fa-plus mr-2"></i>Adicionar Novo Plano';
        document.getElementById('plan_is_active').checked = true;
        
        // Valores padrão para novo plano
        document.getElementById('max_messages').value = 2000;
        document.getElementById('max_attendants').value = 1;
        document.getElementById('max_departments').value = 1;
        document.getElementById('max_contacts').value = 1000;
        document.getElementById('max_whatsapp_instances').value = 1;
        document.getElementById('max_automation_flows').value = 5;
        document.getElementById('max_dispatch_campaigns').value = 10;
        document.getElementById('max_tags').value = 20;
        document.getElementById('max_quick_replies').value = 50;
        document.getElementById('max_file_storage_mb').value = 100;
    }
    
    modal.classList.remove('hidden');
}

function closePlanForm(event) {
    if (!event || event.target.id === 'planFormModal') {
        document.getElementById('planFormModal').classList.add('hidden');
    }
}

async function loadPlans() {
    try {
        const response = await fetch('api/manage_plans.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'list' })
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentPlans = data.plans;
            renderPlans(data.plans);
        } else {
            showPlanAlert(data.message, 'error');
        }
    } catch (error) {
        showPlanAlert('Erro ao carregar planos', 'error');
    }
}

function renderPlans(plans) {
    const container = document.getElementById('plansList');
    
    if (plans.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-8">Nenhum plano cadastrado</p>';
        return;
    }
    
    container.innerHTML = plans.map(plan => {
        const features = JSON.parse(plan.features || '[]');
        const statusColor = plan.is_active == 1 ? 'green' : 'red';
        const statusText = plan.is_active == 1 ? 'Ativo' : 'Inativo';
        
        return `
            <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-green-500 transition">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-2">
                            <h3 class="text-lg font-bold text-gray-800">${plan.name}</h3>
                            <span class="px-2 py-1 bg-${statusColor}-100 text-${statusColor}-800 rounded text-xs font-medium">
                                ${statusText}
                            </span>
                            ${plan.is_popular == 1 ? '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs font-medium">Popular</span>' : ''}
                        </div>
                        
                        <p class="text-sm text-gray-600 mb-2">Slug: <code class="bg-gray-100 px-2 py-1 rounded">${plan.slug}</code></p>
                        
                        <div class="flex items-baseline gap-2 mb-3">
                            <span class="text-3xl font-bold text-green-600">R$ ${parseFloat(plan.price).toFixed(2).replace('.', ',')}</span>
                            <span class="text-gray-500">/mês</span>
                            <span class="text-sm text-gray-600 ml-4">
                                <i class="fas fa-envelope mr-1"></i>${parseInt(plan.message_limit).toLocaleString()} mensagens
                            </span>
                        </div>
                        
                        <div class="text-sm text-gray-600">
                            <strong>Recursos:</strong>
                            <ul class="list-disc list-inside ml-2 mt-1">
                                ${features.map(f => `<li>${f}</li>`).join('')}
                            </ul>
                        </div>
                    </div>
                    
                    <div class="flex flex-col gap-2 ml-4">
                        <button onclick='editPlan(${JSON.stringify(plan)})' 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded transition text-sm">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="togglePlanActive(${plan.id})" 
                                class="bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-2 rounded transition text-sm">
                            <i class="fas fa-power-off"></i>
                        </button>
                        <button onclick="deletePlan(${plan.id}, '${plan.name}')" 
                                class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded transition text-sm">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function editPlan(plan) {
    openPlanForm(plan);
}

async function togglePlanActive(id) {
    if (!confirm('Deseja alterar o status deste plano?')) return;
    
    try {
        const response = await fetch('api/manage_plans.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle_active', id: id })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showPlanAlert(data.message, 'success');
            loadPlans();
        } else {
            showPlanAlert(data.message, 'error');
        }
    } catch (error) {
        showPlanAlert('Erro ao atualizar status', 'error');
    }
}

async function deletePlan(id, name) {
    if (!confirm(`Tem certeza que deseja excluir o plano "${name}"?`)) return;
    
    try {
        const response = await fetch('api/manage_plans.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id: id })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showPlanAlert(data.message, 'success');
            loadPlans();
        } else {
            showPlanAlert(data.message, 'error');
        }
    } catch (error) {
        showPlanAlert('Erro ao excluir plano', 'error');
    }
}

function showPlanAlert(message, type = 'error') {
    const alertDiv = document.getElementById('planAlert');
    alertDiv.classList.remove('hidden', 'bg-red-100', 'border-red-400', 'text-red-700', 'bg-green-100', 'border-green-400', 'text-green-700');
    
    if (type === 'error') {
        alertDiv.classList.add('bg-red-100', 'border', 'border-red-400', 'text-red-700');
        alertDiv.innerHTML = '<i class="fas fa-exclamation-circle mr-2"></i>' + message;
    } else {
        alertDiv.classList.add('bg-green-100', 'border', 'border-green-400', 'text-green-700');
        alertDiv.innerHTML = '<i class="fas fa-check-circle mr-2"></i>' + message;
    }
    
    setTimeout(() => alertDiv.classList.add('hidden'), 5000);
}

// Fechar modais com ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closePlanManager();
        closePlanForm();
    }
});
</script>

<?php require_once 'includes/footer_spa.php'; ?>
