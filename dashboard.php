<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();
requirePasswordChange();

// Redirecionar URLs antigas do sistema SPA para os novos arquivos dedicados
if (isset($_GET['page'])) {
    if ($_GET['page'] === 'meta_monitoring' || $_GET['page'] === 'meta_analytics') {
        $days = isset($_GET['days']) ? '?days=' . (int)$_GET['days'] : '';
        header('Location: /meta_monitoring.php' . $days);
        exit;
    }
}

// Atendentes não têm acesso ao dashboard - redirecionar para o chat
$userType = $_SESSION['user_type'] ?? 'user';
if ($userType === 'attendant') {
    header('Location: /chat.php');
    exit;
}

$page_title = 'Indicadores da Campanha';

// Estatísticas do usuário
$userId = $_SESSION['user_id'];
$user_email = $_SESSION['user_email'] ?? '';

// Buscar informações do plano do usuário (com verificação de colunas)
try {
    $stmt = $pdo->prepare("SELECT 
        COALESCE(plan, 'free') as plan,
        COALESCE(plan_limit, 500) as plan_limit,
        COALESCE(messages_sent, 0) as messages_sent,
        COALESCE(plan_expires_at, NULL) as plan_expires_at,
        two_factor_enabled 
        FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userPlan = $stmt->fetch();
} catch (Exception $e) {
    // Fallback se as colunas não existirem
    $stmt = $pdo->prepare("SELECT two_factor_enabled FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    $userPlan = [
        'plan' => 'free',
        'plan_limit' => 500,
        'messages_sent' => 0,
        'plan_expires_at' => null,
        'two_factor_enabled' => $user['two_factor_enabled'] ?? 0
    ];
}

// Contatos na campanha
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT phone) as count FROM dispatch_history WHERE user_id = ?");
$stmt->execute([$userId]);
$contactsInCampaign = $stmt->fetchColumn();

// Contatos agendados
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM contacts WHERE user_id = ?");
$stmt->execute([$userId]);
$contactsScheduled = $stmt->fetchColumn();

// Contatos que não receberam
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM contacts WHERE user_id = ? AND id NOT IN (SELECT DISTINCT contact_id FROM dispatch_history WHERE user_id = ? AND contact_id IS NOT NULL)");
$stmt->execute([$userId, $userId]);
$contactsNotReceived = $stmt->fetchColumn();

// Contatos que receberam
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT phone) as count FROM dispatch_history WHERE user_id = ? AND status = 'sent'");
$stmt->execute([$userId]);
$contactsReceived = $stmt->fetchColumn();

// Taxa de sucesso
$successRate = $contactsInCampaign > 0 ? round(($contactsReceived / $contactsInCampaign) * 100) : 0;

// Agendamentos pendentes
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM dispatch_history WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$userId]);
$pendingSchedules = $stmt->fetchColumn();

// Visualizações (simulado - pode ser implementado futuramente)
$visualizations = round($contactsReceived * 0.6); // 60% dos enviados visualizaram

// Dados para gráfico de envios por tempo (últimos 7 dias)
$stmt = $pdo->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as count
    FROM dispatch_history 
    WHERE user_id = ? 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$stmt->execute([$userId]);
$timeData = $stmt->fetchAll();

// Dados para gráfico por estado (baseado no DDD dos telefones)
$stmt = $pdo->prepare("
    SELECT 
        CASE 
            WHEN SUBSTRING(phone, 1, 2) IN ('11', '12', '13', '14', '15', '16', '17', '18', '19') THEN 'SP'
            WHEN SUBSTRING(phone, 1, 2) IN ('21', '22', '24') THEN 'RJ'
            WHEN SUBSTRING(phone, 1, 2) IN ('31', '32', '33', '34', '35', '37', '38') THEN 'MG'
            WHEN SUBSTRING(phone, 1, 2) IN ('41', '42', '43', '44', '45', '46') THEN 'PR'
            WHEN SUBSTRING(phone, 1, 2) IN ('47', '48', '49') THEN 'SC'
            WHEN SUBSTRING(phone, 1, 2) IN ('51', '53', '54', '55') THEN 'RS'
            WHEN SUBSTRING(phone, 1, 2) IN ('61') THEN 'DF'
            WHEN SUBSTRING(phone, 1, 2) IN ('62', '64') THEN 'GO'
            WHEN SUBSTRING(phone, 1, 2) IN ('71', '73', '74', '75', '77') THEN 'BA'
            WHEN SUBSTRING(phone, 1, 2) IN ('81', '87') THEN 'PE'
            ELSE 'Outros'
        END as state,
        COUNT(*) as count
    FROM dispatch_history 
    WHERE user_id = ?
    GROUP BY state
    ORDER BY count DESC
    LIMIT 10
");
$stmt->execute([$userId]);
$stateData = $stmt->fetchAll();

// Próximos disparos agendados
$stmt = $pdo->prepare("
    SELECT * FROM scheduled_dispatches 
    WHERE user_id = ? 
    AND scheduled_for > NOW() 
    AND status IN ('pending', 'processing')
    ORDER BY scheduled_for ASC 
    LIMIT 5
");
$stmt->execute([$userId]);
$upcomingSchedules = $stmt->fetchAll();

// Informações do plano
$planUsage = $userPlan['plan_limit'] > 0 ? ($userPlan['messages_sent'] / $userPlan['plan_limit']) * 100 : 0;
$planMeta = getPricingPlans();
$planLookup = [];
foreach ($planMeta as $plan) {
    $planLookup[$plan['slug']] = $plan['name'];
}
$planName = $planLookup[$userPlan['plan']] ?? ucfirst($userPlan['plan']);

// Incluir o header SPA
require_once 'includes/header_spa.php';
?>

<!-- Indicador de Loading para SPA -->
<div id="loadingIndicator" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg p-6 flex items-center space-x-4">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-600"></div>
        <span class="text-gray-700 font-medium">Carregando...</span>
    </div>
</div>

<!-- Conteúdo Principal -->
<div id="mainContent" style="padding: var(--space-6);">

<style>
/* Metric Cards - Refined Design System */
.metric-card {
    background: var(--bg-card);
    border: 0.5px solid var(--border);
    border-radius: var(--radius-lg);
    padding: var(--space-4);
    transition: all var(--transition-base);
    position: relative;
    overflow: hidden;
}

.metric-card:hover {
    border-color: var(--border-emphasis);
    transform: translateY(-2px);
}

.metric-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: var(--space-4);
}

.metric-icon-container {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: var(--radius-md);
    border: 0.5px solid;
    transition: all var(--transition-fast);
}

.metric-card:hover .metric-icon-container {
    transform: scale(1.05);
}

.metric-label {
    font-size: 11px;
    font-weight: 500;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: var(--space-2);
}

.metric-value {
    font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
    font-size: 32px;
    font-weight: 700;
    line-height: 1;
    letter-spacing: -0.02em;
    color: var(--text-primary);
    font-variant-numeric: tabular-nums;
}

.metric-trend {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 600;
    margin-top: var(--space-2);
    padding: 2px 6px;
    border-radius: var(--radius-sm);
}

.metric-trend.positive {
    color: var(--accent-primary);
    background: var(--accent-subtle);
}

.metric-trend.negative {
    color: #ef4444;
    background: rgba(239, 68, 68, 0.08);
}

/* Accent colors for different metrics */
.metric-blue .metric-icon-container {
    background: rgba(59, 130, 246, 0.08);
    border-color: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
}

.metric-orange .metric-icon-container {
    background: rgba(249, 115, 22, 0.08);
    border-color: rgba(249, 115, 22, 0.2);
    color: #f97316;
}

.metric-red .metric-icon-container {
    background: rgba(239, 68, 68, 0.08);
    border-color: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.metric-green .metric-icon-container {
    background: var(--accent-subtle);
    border-color: rgba(16, 185, 129, 0.2);
    color: var(--accent-primary);
}

.metric-purple .metric-icon-container {
    background: rgba(139, 92, 246, 0.08);
    border-color: rgba(139, 92, 246, 0.2);
    color: #8b5cf6;
}

.metric-yellow .metric-icon-container {
    background: rgba(234, 179, 8, 0.08);
    border-color: rgba(234, 179, 8, 0.2);
    color: #eab308;
}

/* Info button */
.metric-info-btn {
    width: 20px;
    height: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    color: var(--text-muted);
    transition: all var(--transition-fast);
    font-size: 12px;
}

.metric-info-btn:hover {
    background: var(--accent-subtle);
    color: var(--accent-primary);
}

/* Progress Ring - Circular progress indicator */
.progress-ring {
    width: 60px;
    height: 60px;
    position: relative;
}

.progress-ring-circle {
    transform: rotate(-90deg);
    transform-origin: 50% 50%;
}

.progress-ring-bg {
    fill: none;
    stroke: var(--border);
    stroke-width: 4;
}

.progress-ring-progress {
    fill: none;
    stroke-width: 4;
    stroke-linecap: round;
    transition: stroke-dashoffset 0.5s ease;
}

/* Sparkline - Mini line chart */
.sparkline {
    width: 100%;
    height: 40px;
    margin-top: var(--space-3);
}

.sparkline-bar {
    display: inline-block;
    width: 8px;
    margin: 0 1px;
    border-radius: 2px;
    background: var(--border);
    transition: all var(--transition-fast);
}

.metric-card:hover .sparkline-bar {
    background: var(--accent-subtle);
}

/* Mini bar chart */
.mini-bars {
    display: flex;
    align-items: flex-end;
    gap: 4px;
    height: 48px;
    margin-top: var(--space-3);
}

.mini-bar {
    flex: 1;
    background: var(--border);
    border-radius: 2px 2px 0 0;
    transition: all var(--transition-fast);
    min-height: 4px;
}

.metric-card:hover .mini-bar {
    background: var(--accent-subtle);
}
</style>

<!-- Grid de Cards de Estatísticas -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6);">
    
    <!-- Card 1: Total de contatos na campanha -->
    <div class="metric-card metric-blue">
        <div class="metric-card-header">
            <div class="metric-icon-container">
                <i class="fas fa-users" style="font-size: 18px;"></i>
            </div>
            <button class="metric-info-btn" title="Total de contatos únicos na campanha">
                <i class="fas fa-info-circle"></i>
            </button>
        </div>
        <div class="metric-label">Total na Campanha</div>
        <div class="metric-value"><?php echo number_format($contactsInCampaign, 0, ',', '.'); ?></div>
    </div>

    <!-- Card 2: Contatos agendados -->
    <div class="metric-card metric-orange">
        <div class="metric-card-header">
            <div class="metric-icon-container">
                <i class="fas fa-calendar-check" style="font-size: 18px;"></i>
            </div>
            <button class="metric-info-btn" title="Contatos com envios agendados">
                <i class="fas fa-info-circle"></i>
            </button>
        </div>
        <div class="metric-label">Agendados</div>
        <div class="metric-value"><?php echo number_format($contactsScheduled, 0, ',', '.'); ?></div>
    </div>

    <!-- Card 3: Contatos que não receberam -->
    <div class="metric-card metric-red">
        <div class="metric-card-header">
            <div class="metric-icon-container">
                <i class="fas fa-times-circle" style="font-size: 18px;"></i>
            </div>
            <button class="metric-info-btn" title="Contatos que não receberam mensagens">
                <i class="fas fa-info-circle"></i>
            </button>
        </div>
        <div class="metric-label">Não Receberam</div>
        <div class="metric-value"><?php echo number_format($contactsNotReceived, 0, ',', '.'); ?></div>
    </div>

    <!-- Card 4: Contatos que receberam -->
    <div class="metric-card metric-green">
        <div class="metric-card-header">
            <div class="metric-icon-container">
                <i class="fas fa-check-circle" style="font-size: 18px;"></i>
            </div>
            <button class="metric-info-btn" title="Contatos que receberam com sucesso">
                <i class="fas fa-info-circle"></i>
            </button>
        </div>
        <div class="metric-label">Receberam</div>
        <div class="metric-value"><?php echo number_format($contactsReceived, 0, ',', '.'); ?></div>
        <div class="metric-trend positive">
            <i class="fas fa-arrow-up" style="font-size: 10px;"></i>
            <span><?php echo $successRate; ?>%</span>
        </div>
    </div>
</div>

<!-- Segunda linha de cards - Métricas secundárias -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6);">
    
    <!-- Taxa de sucesso -->
    <div class="metric-card metric-blue">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div style="flex: 1;">
                <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-3);">
                    <div class="metric-icon-container">
                        <i class="fas fa-chart-line" style="font-size: 18px;"></i>
                    </div>
                    <button class="metric-info-btn" title="Percentual de entregas bem-sucedidas">
                        <i class="fas fa-info-circle"></i>
                    </button>
                </div>
                <div class="metric-label">Taxa de Sucesso</div>
                <div class="metric-value"><?php echo $successRate; ?><span style="font-size: 20px; margin-left: 2px;">%</span></div>
            </div>
            <!-- Progress Ring -->
            <div class="progress-ring">
                <svg width="60" height="60">
                    <circle class="progress-ring-bg" cx="30" cy="30" r="26"></circle>
                    <circle class="progress-ring-circle progress-ring-progress" 
                            cx="30" cy="30" r="26"
                            stroke="#3b82f6"
                            stroke-dasharray="<?php echo 163.36; ?>" 
                            stroke-dashoffset="<?php echo 163.36 * (1 - $successRate / 100); ?>">
                    </circle>
                </svg>
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 14px; font-weight: 700; color: #3b82f6;">
                    <?php echo $successRate; ?>%
                </div>
            </div>
        </div>
    </div>

    <!-- Visualizações -->
    <div class="metric-card metric-purple">
        <div class="metric-card-header">
            <div class="metric-icon-container">
                <i class="fas fa-eye" style="font-size: 18px;"></i>
            </div>
            <button class="metric-info-btn" title="Mensagens visualizadas pelos contatos">
                <i class="fas fa-info-circle"></i>
            </button>
        </div>
        <div class="metric-label">Visualizações</div>
        <div class="metric-value"><?php echo number_format($visualizations, 0, ',', '.'); ?></div>
        <!-- Sparkline -->
        <div class="sparkline">
            <?php 
            $sparklineData = [65, 72, 68, 80, 75, 85, 90, 88, 95, 92, 100];
            foreach ($sparklineData as $value): 
                $height = ($value / 100) * 40;
            ?>
                <div class="sparkline-bar" style="height: <?php echo $height; ?>px; background: rgba(139, 92, 246, <?php echo $value / 150; ?>);"></div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Agendamentos pendentes -->
    <div class="metric-card metric-yellow">
        <div class="metric-card-header">
            <div class="metric-icon-container">
                <i class="fas fa-clock" style="font-size: 18px;"></i>
            </div>
            <button class="metric-info-btn" title="Disparos aguardando processamento">
                <i class="fas fa-info-circle"></i>
            </button>
        </div>
        <div class="metric-label">Pendentes</div>
        <div class="metric-value"><?php echo number_format($pendingSchedules, 0, ',', '.'); ?></div>
        <!-- Mini bars -->
        <div class="mini-bars">
            <?php 
            $barData = [30, 45, 60, 40, 55, 35, 50];
            foreach ($barData as $value): 
                $height = ($value / 60) * 100;
            ?>
                <div class="mini-bar" style="height: <?php echo $height; ?>%; background: rgba(234, 179, 8, <?php echo 0.3 + ($value / 100); ?>);"></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Gráficos -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6);">
    
    <!-- Gráfico de Envios por Tempo -->
    <div class="metric-card">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-4);">
            <div>
                <h3 style="font-size: 14px; font-weight: 600; letter-spacing: -0.01em; color: var(--text-primary); margin-bottom: 2px;">Envios por Tempo</h3>
                <p style="font-size: 11px; color: var(--text-muted);">Últimos 7 dias</p>
            </div>
            <button class="metric-info-btn" title="Histórico de envios dos últimos 7 dias">
                <i class="fas fa-info-circle"></i>
            </button>
        </div>
        <div style="height: 280px;">
            <canvas id="timeChart"></canvas>
        </div>
    </div>

    <!-- Gráfico de Envios por Estado -->
    <div class="metric-card">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-4);">
            <div>
                <h3 style="font-size: 14px; font-weight: 600; letter-spacing: -0.01em; color: var(--text-primary); margin-bottom: 2px;">Envios por Estado</h3>
                <p style="font-size: 11px; color: var(--text-muted);">Distribuição por DDD</p>
            </div>
            <button class="metric-info-btn" title="Distribuição geográfica dos envios">
                <i class="fas fa-info-circle"></i>
            </button>
        </div>
        <div style="height: 280px;">
            <canvas id="stateChart"></canvas>
        </div>
    </div>
</div>

<!-- Próximos disparos agendados -->
<div class="metric-card" style="grid-column: 1 / -1;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-4);">
        <div>
            <h3 style="font-size: 14px; font-weight: 600; letter-spacing: -0.01em; color: var(--text-primary); margin-bottom: 2px;">Próximos Disparos</h3>
            <p style="font-size: 11px; color: var(--text-muted);">Agendamentos pendentes</p>
        </div>
        <a href="/scheduled_dispatches.php" style="font-size: 12px; font-weight: 500; color: var(--accent-primary); text-decoration: none; transition: color var(--transition-fast);" onmouseover="this.style.color='var(--accent-hover)'" onmouseout="this.style.color='var(--accent-primary)'">
            Ver todos <i class="fas fa-arrow-right" style="font-size: 10px; margin-left: 4px;"></i>
        </a>
    </div>
    <?php if (empty($upcomingSchedules)): ?>
        <div style="text-align: center; padding: var(--space-6); color: var(--text-muted);">
            <i class="fas fa-calendar-check" style="font-size: 32px; opacity: 0.3; margin-bottom: var(--space-2);"></i>
            <p style="font-size: 13px;">Nenhum disparo agendado para as próximas horas</p>
        </div>
    <?php else: ?>
        <div style="display: grid; gap: var(--space-3);">
            <?php foreach ($upcomingSchedules as $schedule): ?>
                <div style="padding: var(--space-3); border: 0.5px solid var(--border); border-radius: var(--radius-md); border-left: 2px solid var(--accent-primary); transition: all var(--transition-fast);" onmouseover="this.style.borderColor='var(--border-emphasis)'; this.style.backgroundColor='var(--bg-sidebar-hover)'" onmouseout="this.style.borderColor='var(--border)'; this.style.backgroundColor='transparent'">
                    <p style="font-size: 13px; font-weight: 500; color: var(--text-primary); margin-bottom: var(--space-2); line-height: 1.4;">
                        <?php echo htmlspecialchars(mb_strimwidth($schedule['message'] ?? 'Mensagem', 0, 80, '...')); ?>
                    </p>
                    <div style="display: flex; align-items: center; gap: var(--space-4); font-size: 11px; color: var(--text-muted);">
                        <span style="display: flex; align-items: center; gap: 4px;">
                            <i class="fas fa-clock" style="font-size: 10px;"></i>
                            <?php echo date('d/m H:i', strtotime($schedule['scheduled_for'])); ?>
                        </span>
                        <span style="font-family: monospace; font-variant-numeric: tabular-nums;">
                            <?php echo (int)$schedule['sent_count']; ?> / <?php echo (int)$schedule['total_contacts']; ?>
                        </span>
                        <span style="padding: 2px 6px; border-radius: var(--radius-sm); font-weight: 600; font-size: 10px; <?php echo $schedule['status'] === 'pending' ? 'background: rgba(234, 179, 8, 0.08); color: #eab308;' : 'background: var(--accent-subtle); color: var(--accent-primary);'; ?>">
                            <?php echo ucfirst($schedule['status']); ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</div>

<!-- Scripts específicos do Dashboard -->
<script>
// Dados para os gráficos
const timeData = <?php echo json_encode($timeData); ?>;
const stateData = <?php echo json_encode($stateData); ?>;

// Gráfico de Envios por Tempo
const timeCtx = document.getElementById('timeChart').getContext('2d');
const timeChart = new Chart(timeCtx, {
    type: 'line',
    data: {
        labels: timeData.map(item => {
            const date = new Date(item.date);
            return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
        }),
        datasets: [{
            label: 'Recebidos',
            data: timeData.map(item => item.count),
            borderColor: '#10B981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#10B981',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0, 0, 0, 0.1)'
                },
                ticks: {
                    color: '#6B7280'
                }
            },
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    color: '#6B7280'
                }
            }
        }
    }
});

// Gráfico de Envios por Estado (Pizza)
const stateCtx = document.getElementById('stateChart').getContext('2d');
const stateChart = new Chart(stateCtx, {
    type: 'doughnut',
    data: {
        labels: stateData.map(item => item.state),
        datasets: [{
            data: stateData.map(item => item.count),
            backgroundColor: [
                '#10B981', // Verde
                '#3B82F6', // Azul
                '#8B5CF6', // Roxo
                '#F59E0B', // Amarelo
                '#EF4444', // Vermelho
                '#EC4899', // Rosa
                '#14B8A6', // Teal
                '#F97316', // Laranja
                '#6366F1', // Indigo
                '#84CC16'  // Lima
            ],
            borderWidth: 0,
            hoverBorderWidth: 3,
            hoverBorderColor: '#ffffff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    usePointStyle: true,
                    color: '#6B7280',
                    font: {
                        size: 12
                    }
                }
            }
        },
        cutout: '60%'
    }
});

// BLOQUEIO SELETIVO DE ALERTAS NO DASHBOARD
// Preservar confirm original para uso em páginas SPA
const originalConfirm = window.confirm.bind(window);
const originalAlert = window.alert.bind(window);

window.alert = function(message) {
    // Bloquear apenas alertas de conexão WhatsApp
    if (message && (message.includes('WhatsApp') || message.includes('conectado') || message.includes('instância'))) {
        console.log('🚫 ALERT BLOQUEADO (WhatsApp):', message);
        return;
    }
    // Permitir outros alertas
    return originalAlert(message);
};

window.confirm = function(message) {
    // Permitir confirms de ações do usuário (excluir, etc)
    if (message && (message.includes('excluir') || message.includes('Excluir') || message.includes('remover') || message.includes('Remover') || message.includes('Deseja') || message.includes('certeza'))) {
        return originalConfirm(message);
    }
    // Bloquear confirms de conexão WhatsApp
    if (message && (message.includes('WhatsApp') || message.includes('conectado') || message.includes('instância'))) {
        console.log('🚫 CONFIRM BLOQUEADO (WhatsApp):', message);
        return false;
    }
    // Permitir outros confirms
    return originalConfirm(message);
};

// Remover qualquer modal existente imediatamente
setTimeout(() => {
    const modals = document.querySelectorAll('div[style*="position: fixed"], div[style*="z-index"]');
    modals.forEach(modal => {
        const text = modal.textContent || '';
        if (text.includes('WhatsApp') || text.includes('conectado')) {
            modal.remove();
            console.log('🗑️ Modal removido no dashboard');
        }
    });
}, 100);

console.log('🛡️ BLOQUEIO SELETIVO ATIVO NO DASHBOARD');

// Sistema SPA - Single Page Application
const SPA = {
    currentPage: 'dashboard',
    
    // Configuração das páginas
    pages: {
        'dashboard': {
            title: 'Indicadores da Campanha',
            url: '/api/spa_content.php?page=dashboard'
        },
        'dispatch': {
            title: 'Campanhas',
            url: '/api/spa_content.php?page=dispatch'
        },
        'categories': {
            title: 'Categorias e Tags',
            url: '/api/spa_content.php?page=categories'
        },
        'contacts': {
            title: 'Meus Contatos',
            url: '/api/spa_content.php?page=contacts'
        },
        'my_instance': {
            title: 'Minha Instância',
            url: '/api/spa_content.php?page=my_instance'
        },
        'setup_2fa': {
            title: 'Configurar 2FA',
            url: '/api/spa_content.php?page=setup_2fa'
        },
        'fields': {
            title: 'Campos Personalizados',
            url: '/api/spa_content.php?page=fields'
        },
        'templates': {
            title: 'Modelos de Mensagem',
            url: '/api/spa_content.php?page=templates'
        },
        'appointments': {
            title: 'Agendamentos',
            url: '/api/spa_content.php?page=appointments'
        },
        'message_templates': {
            title: 'Modelos de Mensagem',
            url: '/api/spa_content.php?page=message_templates'
        },
        'scheduled_dispatches': {
            title: 'Agendamentos de Disparo',
            url: '/scheduled_dispatches.php'
        },
        'chat': {
            title: 'Bate-papo',
            url: '/chat.php'
        },
        'kanban': {
            title: 'Kanban',
            url: '/kanban.php'
        },
        'supervisor_users': {
            title: 'Gerenciar Atendentes',
            url: '/supervisor_users.php'
        },
        'subscription': {
            title: 'Assinatura',
            url: '/api/spa_content.php?page=subscription'
        },
        'flows': {
            title: 'Fluxos de Automação',
            url: '/flows.php'
        },
        'users': {
            title: 'Gerenciar Usuários',
            url: '/api/spa_content.php?page=users'
        },
        'backups': {
            title: 'Backup de Conversas',
            url: '/api/spa_content.php?page=backups'
        }
    },
    
    // Mostrar loading
    showLoading: function() {
        document.getElementById('loadingIndicator').classList.remove('hidden');
    },
    
    // Esconder loading
    hideLoading: function() {
        document.getElementById('loadingIndicator').classList.add('hidden');
    },
    
    // Atualizar título da página
    updateTitle: function(title) {
        document.getElementById('pageTitle').textContent = title;
        document.title = title + ' - MAC-IP TECNOLOGIA';
    },
    
    // Atualizar item ativo na sidebar
    updateActiveMenuItem: function(pageId) {
        // Remover classe active de todos os itens
        document.querySelectorAll('.sidebar-item').forEach(item => {
            item.classList.remove('active');
        });

        // Procurar item da sidebar por data-page/data-spa-page ou pelo onclick
        let targetElement = document.querySelector(`[data-page="${pageId}"]`) ||
            document.querySelector(`[data-spa-page="${pageId}"]`);
        if (targetElement) {
            targetElement = targetElement.classList && targetElement.classList.contains('sidebar-item')
                ? targetElement
                : targetElement.closest('.sidebar-item');
        }

        if (!targetElement) {
            targetElement = document.querySelector(`[onclick*="${pageId}"]`);
        }

        if (targetElement && targetElement.classList && targetElement.classList.contains('sidebar-item')) {
            targetElement.classList.add('active');
        }
    },
    
    // Carregar conteúdo da página
    loadContent: function(pageId) {
        const page = this.pages[pageId];
        if (!page) {
            console.error('Página não encontrada:', pageId);
            return;
        }
        
        const self = this;
        
        this.showLoading();
        this.updateTitle(page.title);
        this.updateActiveMenuItem(pageId);
        this.currentPage = pageId;
        
        // Se for dashboard ou chat, recarregar a página completa
        if (pageId === 'dashboard') {
            window.location.href = '/dashboard.php';
            return;
        }
        
        if (pageId === 'chat') {
            window.location.href = '/chat.php';
            return;
        }
        
        if (pageId === 'kanban') {
            window.location.href = '/kanban.php';
            return;
        }
        
        if (pageId === 'supervisor_users') {
            window.location.href = '/supervisor_users.php';
            return;
        }
        
        // Carregar conteúdo via AJAX
        fetch(page.url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(html => {
                const mainContent = document.getElementById('mainContent');
                if (mainContent) {
                    mainContent.innerHTML = html;
                    self.hideLoading();
                    
                    // Executar scripts da página carregada
                    setTimeout(() => {
                        self.executePageScripts();
                    }, 100);
                } else {
                    throw new Error('Elemento mainContent não encontrado');
                }
            })
            .catch(error => {
                console.error('Erro ao carregar página:', error);
                const mainContent = document.getElementById('mainContent');
                if (mainContent) {
                    mainContent.innerHTML = `
                        <div class="flex items-center justify-center py-12">
                            <div class="text-center">
                                <i class="fas fa-exclamation-triangle text-4xl text-red-500 mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-800 mb-2">Erro ao carregar página</h3>
                                <p class="text-gray-600 mb-4">Não foi possível carregar o conteúdo solicitado.</p>
                                <p class="text-sm text-gray-500 mb-4">${error.message}</p>
                                <button onclick="loadPage('dashboard')" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                                    Voltar ao Dashboard
                                </button>
                            </div>
                        </div>
                    `;
                }
                self.hideLoading();
            });
    },
    
    // Executar scripts específicos da página
    executePageScripts: function() {
        const scripts = document.getElementById('mainContent').querySelectorAll('script');
        scripts.forEach(script => {
            const newScript = document.createElement('script');
            newScript.textContent = script.textContent;
            document.head.appendChild(newScript);
            document.head.removeChild(newScript);
        });
    }
};

// Função global para carregar páginas
function loadPage(pageId, event) {
    // Prevenir comportamento padrão se for um link
    if (event) {
        event.preventDefault();
    }
    
    // Verificar se SPA está definido
    if (typeof SPA === 'undefined' || !SPA.pages) {
        console.error('SPA não inicializado');
        return false;
    }
    
    // Verificar se a página existe
    if (!SPA.pages[pageId]) {
        console.error('Página não encontrada:', pageId);
        return false;
    }
    
    // Carregar conteúdo
    SPA.loadContent(pageId);
    
    // Fechar menu do usuário se estiver aberto
    const userMenu = document.getElementById('userMenu');
    if (userMenu) {
        userMenu.classList.add('hidden');
    }
    
    // Fechar submenus se estiverem abertos
    document.querySelectorAll('.sidebar-submenu').forEach(submenu => {
        if (submenu) {
            submenu.classList.remove('open');
        }
    });
    
    // Resetar setas dos submenus
    document.querySelectorAll('[id$="-arrow"]').forEach(arrow => {
        if (arrow) {
            arrow.style.transform = 'rotate(0deg)';
        }
    });
    
    return false;
}

// Inicializar SPA quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 SPA Inicializada');
    
    // Verificar se há página específica na URL
    const urlParams = new URLSearchParams(window.location.search);
    const pageParam = urlParams.get('page');
    
    if (pageParam && pageParam !== 'dashboard' && typeof SPA !== 'undefined' && SPA.pages && SPA.pages[pageParam]) {
        // Carregar a página específica imediatamente
        console.log('📄 Carregando página:', pageParam);
        loadPage(pageParam);
    } else {
        // Carregar dashboard por padrão apenas se não houver parâmetro page
        if (typeof SPA !== 'undefined') {
            SPA.currentPage = 'dashboard';
            SPA.updateTitle('Indicadores da Campanha');
        }
    }
});

// Tratamento global de erros JavaScript
window.addEventListener('error', function(e) {
    // Ignorar erros de recursos externos bloqueados
    if (e.message && (e.message.includes('googleapis') || e.message.includes('cdnjs'))) {
        e.preventDefault();
        return;
    }
    console.error('Erro capturado:', e.message, 'em', e.filename, 'linha', e.lineno);
});
</script>

<?php require_once 'includes/footer_spa.php'; ?>
