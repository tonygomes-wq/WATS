<?php
session_start();
require_once 'config/database.php';

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_email = $_SESSION['email'] ?? '';

// Verificar se é supervisor ou admin
function canAccessReports() {
    $is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
    $is_supervisor = isset($_SESSION['is_supervisor']) && $_SESSION['is_supervisor'] == 1;
    return $is_admin || $is_supervisor;
}

if (!canAccessReports()) {
    header('Location: dashboard.php');
    exit;
}

// Definir variável para o header
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;

// Buscar lista de usuários para o filtro
try {
    // Verificar se coluna is_active existe
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_active'");
    $has_is_active = $stmt->rowCount() > 0;
    
    if ($has_is_active) {
        $stmt = $pdo->query("SELECT id, name, email FROM users WHERE is_active = 1 ORDER BY name");
    } else {
        $stmt = $pdo->query("SELECT id, name, email FROM users ORDER BY name");
    }
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
}

$pageTitle = 'Relatórios';
include 'includes/header_spa.php';
?>

<style>
    .metric-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        padding: 1.5rem;
        color: white;
        transition: transform 0.3s ease;
    }
    .metric-card:hover {
        transform: translateY(-5px);
    }
    .metric-card.green {
        background: linear-gradient(135deg, #10B981 0%, #059669 100%);
    }
    .metric-card.blue {
        background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
    }
    .metric-card.orange {
        background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
    }
    .metric-card.purple {
        background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);
    }
    
    .chart-container {
        position: relative;
        height: 350px;
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    
    .chart-container canvas {
        max-height: 280px !important;
    }
    
    .filter-section {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .export-dropdown {
        position: relative;
        display: inline-block;
    }
    
    .export-menu {
        display: none;
        position: absolute;
        right: 0;
        top: 100%;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        min-width: 150px;
        z-index: 1000;
        margin-top: 0.5rem;
    }
    
    .export-menu.show {
        display: block;
    }
    
    .export-menu a {
        display: block;
        padding: 0.75rem 1rem;
        color: #374151;
        text-decoration: none;
        transition: background 0.2s;
    }
    
    .export-menu a:hover {
        background: #F3F4F6;
    }
    
    .export-menu a:first-child {
        border-radius: 8px 8px 0 0;
    }
    
    .export-menu a:last-child {
        border-radius: 0 0 8px 8px;
    }
    
    /* Suporte ao Modo Escuro */
    :root[data-theme="dark"] .chart-container {
        background: #1f2937 !important;
        color: #f3f4f6 !important;
    }
    
    :root[data-theme="dark"] .chart-container h3 {
        color: #f3f4f6 !important;
    }
    
    /* Cards do Kanban no modo escuro */
    :root[data-theme="dark"] .bg-white.rounded-lg.shadow.p-4 {
        background-color: #1f2937 !important;
    }
    
    :root[data-theme="dark"] .bg-white.rounded-lg.shadow.p-4 .text-gray-500 {
        color: #9ca3af !important;
    }
    
    :root[data-theme="dark"] .bg-white.rounded-lg.shadow.p-4 .text-gray-800 {
        color: #f3f4f6 !important;
    }
    
    :root[data-theme="dark"] .filter-section {
        background: #1f2937 !important;
        border: 1px solid #374151;
    }
    
    :root[data-theme="dark"] .filter-section label {
        color: #d1d5db !important;
    }
    
    :root[data-theme="dark"] .filter-section select,
    :root[data-theme="dark"] .filter-section input {
        background: #374151 !important;
        color: #f3f4f6 !important;
        border-color: #4b5563 !important;
    }
    
    :root[data-theme="dark"] .export-menu {
        background: #1f2937 !important;
        border: 1px solid #374151;
    }
    
    :root[data-theme="dark"] .export-menu a {
        color: #f3f4f6 !important;
    }
    
    :root[data-theme="dark"] .export-menu a:hover {
        background: #374151 !important;
    }
    
    :root[data-theme="dark"] .bg-gray-50 {
        background-color: #0f172a !important;
    }
    
    :root[data-theme="dark"] .text-gray-800 {
        color: #f3f4f6 !important;
    }
    
    :root[data-theme="dark"] .text-gray-600 {
        color: #d1d5db !important;
    }
    
    :root[data-theme="dark"] .text-gray-500 {
        color: #9ca3af !important;
    }
    
    :root[data-theme="dark"] .bg-white {
        background-color: #1f2937 !important;
    }
    
    :root[data-theme="dark"] .text-gray-700 {
        color: #d1d5db !important;
    }
    
    :root[data-theme="dark"] .text-gray-900 {
        color: #f3f4f6 !important;
    }
    
    :root[data-theme="dark"] .border-gray-200 {
        border-color: #374151 !important;
    }
    
    :root[data-theme="dark"] .divide-gray-200 > :not([hidden]) ~ :not([hidden]) {
        border-color: #374151 !important;
    }
    
    :root[data-theme="dark"] table thead {
        background-color: #374151 !important;
    }
    
    :root[data-theme="dark"] table tbody tr:hover {
        background-color: #374151 !important;
    }
</style>

<div class="refined-container bg-gray-50 min-h-screen">
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">
                <i class="fas fa-chart-line text-green-600"></i>
                Relatórios
            </h1>
            <p class="text-gray-600 mt-1">Dashboard completo de análise de atendimentos</p>
        </div>
        
        <!-- Botão Exportar -->
        <div class="export-dropdown">
            <button onclick="toggleExportMenu()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg flex items-center gap-2 transition">
                <i class="fas fa-download"></i>
                <span>Exportar</span>
                <i class="fas fa-chevron-down text-sm"></i>
            </button>
            <div id="exportMenu" class="export-menu">
                <a href="#" onclick="exportReport('excel'); return false;">
                    <i class="fas fa-file-excel text-green-600"></i> Excel (.xlsx)
                </a>
                <a href="#" onclick="exportReport('csv'); return false;">
                    <i class="fas fa-file-csv text-blue-600"></i> CSV
                </a>
                <a href="#" onclick="exportReport('pdf'); return false;">
                    <i class="fas fa-file-pdf text-red-600"></i> PDF
                </a>
            </div>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="filter-section">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Período -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Período</label>
                <select id="filterPeriod" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                    <option value="today">Hoje</option>
                    <option value="yesterday">Ontem</option>
                    <option value="week" selected>Última Semana</option>
                    <option value="month">Último Mês</option>
                    <option value="custom">Personalizado</option>
                </select>
            </div>
            
            <!-- Usuário -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Usuário</label>
                <select id="filterUser" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                    <option value="">Todos os usuários</option>
                    <?php foreach ($users as $user): ?>
                    <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Status -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select id="filterStatus" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                    <option value="">Todos os status</option>
                    <option value="active">Ativos</option>
                    <option value="resolved">Resolvidos</option>
                    <option value="closed">Encerrados</option>
                </select>
            </div>
            
            <!-- Botão Aplicar -->
            <div class="flex items-end">
                <button onclick="applyFilters()" class="w-full bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg transition flex items-center justify-center gap-2">
                    <i class="fas fa-filter"></i>
                    Aplicar Filtros
                </button>
            </div>
        </div>
        
        <!-- Datas personalizadas (oculto por padrão) -->
        <div id="customDates" class="mt-4 hidden">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Data Inicial</label>
                    <input type="date" id="startDate" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Data Final</label>
                    <input type="date" id="endDate" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cards de Métricas -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- Total de Atendimentos -->
        <div class="metric-card green">
            <div class="flex items-center justify-between mb-2">
                <i class="fas fa-comments text-3xl opacity-80"></i>
                <span class="text-sm opacity-80">Total</span>
            </div>
            <div class="text-4xl font-bold mb-1" id="metricTotal">-</div>
            <div class="text-sm opacity-90">Atendimentos</div>
        </div>
        
        <!-- Ativos -->
        <div class="metric-card blue">
            <div class="flex items-center justify-between mb-2">
                <i class="fas fa-clock text-3xl opacity-80"></i>
                <span class="text-sm opacity-80">Em Andamento</span>
            </div>
            <div class="text-4xl font-bold mb-1" id="metricActive">-</div>
            <div class="text-sm opacity-90">Ativos</div>
        </div>
        
        <!-- Resolvidos -->
        <div class="metric-card orange">
            <div class="flex items-center justify-between mb-2">
                <i class="fas fa-check-circle text-3xl opacity-80"></i>
                <span class="text-sm opacity-80">Finalizados</span>
            </div>
            <div class="text-4xl font-bold mb-1" id="metricResolved">-</div>
            <div class="text-sm opacity-90">Resolvidos</div>
        </div>
        
        <!-- Tempo Médio -->
        <div class="metric-card purple">
            <div class="flex items-center justify-between mb-2">
                <i class="fas fa-hourglass-half text-3xl opacity-80"></i>
                <span class="text-sm opacity-80">Duração</span>
            </div>
            <div class="text-4xl font-bold mb-1" id="metricAvgTime">-</div>
            <div class="text-sm opacity-90">Tempo Médio</div>
        </div>
    </div>
    
    <!-- Seção Kanban -->
    <div id="kanbanSection" class="mb-6" style="display: none;">
        <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-columns text-purple-600"></i>
            Métricas do Kanban
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <!-- Total Cards -->
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Total de Cards</p>
                        <p class="text-2xl font-bold text-gray-800" id="kanbanTotalCards">0</p>
                    </div>
                    <i class="fas fa-th-large text-purple-500 text-2xl"></i>
                </div>
            </div>
            
            <!-- Valor Total -->
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Valor Total</p>
                        <p class="text-2xl font-bold text-gray-800" id="kanbanTotalValue">R$ 0</p>
                    </div>
                    <i class="fas fa-dollar-sign text-green-500 text-2xl"></i>
                </div>
            </div>
            
            <!-- Criados no Período -->
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Criados</p>
                        <p class="text-2xl font-bold text-gray-800" id="kanbanCreated">0</p>
                    </div>
                    <i class="fas fa-plus-circle text-blue-500 text-2xl"></i>
                </div>
            </div>
            
            <!-- Finalizados -->
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-emerald-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Finalizados</p>
                        <p class="text-2xl font-bold text-gray-800" id="kanbanCompleted">0</p>
                    </div>
                    <i class="fas fa-check-circle text-emerald-500 text-2xl"></i>
                </div>
            </div>
            
            <!-- Vencidos -->
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Vencidos</p>
                        <p class="text-2xl font-bold text-gray-800" id="kanbanOverdue">0</p>
                    </div>
                    <i class="fas fa-exclamation-triangle text-red-500 text-2xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Gráficos Kanban -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Cards por Coluna -->
            <div class="chart-container">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-chart-bar text-purple-600"></i>
                    Cards por Coluna
                </h3>
                <canvas id="chartKanbanColumns"></canvas>
            </div>
            
            <!-- Cards por Prioridade -->
            <div class="chart-container">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-chart-pie text-orange-600"></i>
                    Cards por Prioridade
                </h3>
                <canvas id="chartKanbanPriority"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Gráficos -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Atendimentos por Dia -->
        <div class="chart-container">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-chart-line text-green-600"></i>
                Atendimentos por Dia
            </h3>
            <canvas id="chartDaily"></canvas>
        </div>
        
        <!-- Atendimentos por Usuário -->
        <div class="chart-container">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-chart-bar text-blue-600"></i>
                Atendimentos por Usuário
            </h3>
            <canvas id="chartUsers"></canvas>
        </div>
        
        <!-- Atendimentos por Status -->
        <div class="chart-container">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-chart-pie text-orange-600"></i>
                Distribuição por Status
            </h3>
            <canvas id="chartStatus"></canvas>
        </div>
        
        <!-- Tempo Médio por Usuário -->
        <div class="chart-container">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-chart-area text-purple-600"></i>
                Tempo Médio por Usuário
            </h3>
            <canvas id="chartAvgTime"></canvas>
        </div>
    </div>
    
    <!-- Tabela de Atendimentos -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="refined-container border-b border-gray-200">
            <h3 class="text-xl font-semibold text-gray-800">
                <i class="fas fa-table text-green-600"></i>
                Detalhes dos Atendimentos
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuário</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mensagens</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duração</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody id="tableBody" class="bg-white divide-y divide-gray-200">
                    <tr>
                        <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                            <i class="fas fa-spinner fa-spin mr-2"></i>
                            Carregando dados...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Paginação -->
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
            <div class="text-sm text-gray-700">
                Mostrando <span id="pageInfo">0</span> resultados
            </div>
            <div class="flex gap-2" id="pagination">
                <!-- Botões de paginação serão inseridos aqui -->
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// Variáveis globais
let charts = {};
let currentPage = 1;
let totalPages = 1;

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    // Listener para período personalizado
    document.getElementById('filterPeriod').addEventListener('change', function() {
        const customDates = document.getElementById('customDates');
        if (this.value === 'custom') {
            customDates.classList.remove('hidden');
        } else {
            customDates.classList.add('hidden');
        }
    });
    
    // Carregar dados iniciais
    loadReportData();
});

// Toggle menu de exportação
function toggleExportMenu() {
    const menu = document.getElementById('exportMenu');
    menu.classList.toggle('show');
}

// Fechar menu ao clicar fora
document.addEventListener('click', function(event) {
    const dropdown = document.querySelector('.export-dropdown');
    if (dropdown && !dropdown.contains(event.target)) {
        document.getElementById('exportMenu').classList.remove('show');
    }
});

// Aplicar filtros
function applyFilters() {
    currentPage = 1;
    loadReportData();
}

// Carregar dados do relatório
async function loadReportData() {
    try {
        const filters = getFilters();
        
        const response = await fetch('api/supervisor_reports.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(filters)
        });
        
        const data = await response.json();
        
        if (data.success) {
            updateMetrics(data.metrics);
            updateCharts(data.charts);
            updateTable(data.conversations);
            
            // Atualizar métricas do Kanban se disponíveis
            if (data.kanban) {
                updateKanbanMetrics(data.kanban);
            }
        } else {
            showError(data.error || 'Erro ao carregar dados');
        }
    } catch (error) {
        console.error('Erro:', error);
        showError('Erro ao conectar com o servidor');
    }
}

// Obter filtros atuais
function getFilters() {
    return {
        period: document.getElementById('filterPeriod').value,
        user_id: document.getElementById('filterUser').value,
        status: document.getElementById('filterStatus').value,
        start_date: document.getElementById('startDate').value,
        end_date: document.getElementById('endDate').value,
        page: currentPage
    };
}

// Atualizar métricas
function updateMetrics(metrics) {
    document.getElementById('metricTotal').textContent = metrics.total || 0;
    document.getElementById('metricActive').textContent = metrics.active || 0;
    document.getElementById('metricResolved').textContent = metrics.resolved || 0;
    document.getElementById('metricAvgTime').textContent = metrics.avg_time || '0min';
}

// Atualizar gráficos
function updateCharts(chartsData) {
    // Gráfico de linha - Atendimentos por dia
    updateLineChart('chartDaily', chartsData.daily);
    
    // Gráfico de barras - Por usuário
    updateBarChart('chartUsers', chartsData.by_user);
    
    // Gráfico de pizza - Por status
    updatePieChart('chartStatus', chartsData.by_status);
    
    // Gráfico de barras - Tempo médio
    updateBarChart('chartAvgTime', chartsData.avg_time_by_user);
}

// Detectar modo escuro
function isDarkMode() {
    return document.documentElement.getAttribute('data-theme') === 'dark';
}

// Obter cores para os gráficos baseado no tema
function getChartColors() {
    const dark = isDarkMode();
    return {
        gridColor: dark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)',
        textColor: dark ? '#d1d5db' : '#374151',
        legendColor: dark ? '#f3f4f6' : '#374151'
    };
}

// Atualizar gráfico de linha
function updateLineChart(canvasId, data) {
    const ctx = document.getElementById(canvasId);
    const colors = getChartColors();
    
    if (charts[canvasId]) {
        charts[canvasId].destroy();
    }
    
    charts[canvasId] = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels || [],
            datasets: [{
                label: 'Atendimentos',
                data: data.values || [],
                borderColor: '#10B981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.4,
                fill: true
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
                x: {
                    grid: {
                        color: colors.gridColor
                    },
                    ticks: {
                        color: colors.textColor,
                        maxRotation: 45,
                        minRotation: 0,
                        font: { size: 10 }
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: colors.gridColor
                    },
                    ticks: {
                        stepSize: 1,
                        color: colors.textColor
                    }
                }
            }
        }
    });
}

// Atualizar gráfico de barras
function updateBarChart(canvasId, data) {
    const ctx = document.getElementById(canvasId);
    const colors = getChartColors();
    
    if (charts[canvasId]) {
        charts[canvasId].destroy();
    }
    
    charts[canvasId] = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels || [],
            datasets: [{
                label: data.label || 'Valores',
                data: data.values || [],
                backgroundColor: '#3B82F6',
                borderRadius: 8
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
                x: {
                    grid: {
                        color: colors.gridColor
                    },
                    ticks: {
                        color: colors.textColor,
                        maxRotation: 45,
                        minRotation: 0,
                        font: { size: 10 }
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: colors.gridColor
                    },
                    ticks: {
                        color: colors.textColor
                    }
                }
            }
        }
    });
}

// Atualizar gráfico de pizza
function updatePieChart(canvasId, data) {
    const ctx = document.getElementById(canvasId);
    const colors = getChartColors();
    
    if (charts[canvasId]) {
        charts[canvasId].destroy();
    }
    
    charts[canvasId] = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels || [],
            datasets: [{
                data: data.values || [],
                backgroundColor: [
                    '#10B981',
                    '#3B82F6',
                    '#F59E0B',
                    '#8B5CF6',
                    '#EF4444'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: colors.legendColor,
                        padding: 15,
                        font: { size: 11 }
                    }
                }
            }
        }
    });
}

// Atualizar tabela
function updateTable(conversations) {
    const tbody = document.getElementById('tableBody');
    
    if (!conversations || conversations.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-2"></i>
                    <p>Nenhum atendimento encontrado</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = conversations.map(conv => `
        <tr class="hover:bg-gray-50">
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">#${conv.id}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${conv.contact_name || 'N/A'}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${conv.user_name || 'N/A'}</td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 py-1 text-xs font-semibold rounded-full ${getStatusClass(conv.status)}">
                    ${getStatusLabel(conv.status)}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${conv.message_count || 0}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${conv.duration || '0min'}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${formatDate(conv.created_at)}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">
                <a href="chat.php?conversation=${conv.id}" class="text-green-600 hover:text-green-900">
                    <i class="fas fa-eye"></i> Ver
                </a>
            </td>
        </tr>
    `).join('');
}

// Obter classe do status
function getStatusClass(status) {
    const classes = {
        'active': 'bg-blue-100 text-blue-800',
        'resolved': 'bg-green-100 text-green-800',
        'closed': 'bg-gray-100 text-gray-800'
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
}

// Obter label do status
function getStatusLabel(status) {
    const labels = {
        'active': 'Ativo',
        'resolved': 'Resolvido',
        'closed': 'Encerrado'
    };
    return labels[status] || status;
}

// Formatar data
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Exportar relatório
async function exportReport(format) {
    try {
        const filters = getFilters();
        
        const response = await fetch('api/export_reports.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                ...filters,
                format: format
            })
        });
        
        if (response.ok) {
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `relatorio_atendimentos_${Date.now()}.${format === 'excel' ? 'xlsx' : format}`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            
            showSuccess('Relatório exportado com sucesso!');
        } else {
            showError('Erro ao exportar relatório');
        }
    } catch (error) {
        console.error('Erro:', error);
        showError('Erro ao exportar relatório');
    }
    
    document.getElementById('exportMenu').classList.remove('show');
}

// Mostrar erro
function showError(message) {
    alert('Erro: ' + message);
}

// Mostrar sucesso
function showSuccess(message) {
    alert(message);
}

// Atualizar métricas do Kanban
function updateKanbanMetrics(kanban) {
    if (!kanban) {
        document.getElementById('kanbanSection').style.display = 'none';
        return;
    }
    
    // Mostrar seção
    document.getElementById('kanbanSection').style.display = 'block';
    
    // Atualizar valores
    document.getElementById('kanbanTotalCards').textContent = kanban.total_cards || 0;
    document.getElementById('kanbanTotalValue').textContent = 'R$ ' + (kanban.total_value || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2});
    document.getElementById('kanbanCreated').textContent = kanban.created_in_period || 0;
    document.getElementById('kanbanCompleted').textContent = kanban.completed_in_period || 0;
    document.getElementById('kanbanOverdue').textContent = kanban.overdue_cards || 0;
    
    // Gráfico de Cards por Coluna
    if (kanban.by_column && kanban.by_column.labels.length > 0) {
        updateKanbanColumnChart(kanban.by_column);
    }
    
    // Gráfico de Cards por Prioridade
    if (kanban.by_priority && kanban.by_priority.labels.length > 0) {
        updateKanbanPriorityChart(kanban.by_priority);
    }
}

// Gráfico de Cards por Coluna
function updateKanbanColumnChart(data) {
    const ctx = document.getElementById('chartKanbanColumns');
    
    if (charts['chartKanbanColumns']) {
        charts['chartKanbanColumns'].destroy();
    }
    
    charts['chartKanbanColumns'] = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels || [],
            datasets: [{
                label: 'Cards',
                data: data.values || [],
                backgroundColor: data.colors || '#8B5CF6',
                borderRadius: 8
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
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

// Gráfico de Cards por Prioridade
function updateKanbanPriorityChart(data) {
    const ctx = document.getElementById('chartKanbanPriority');
    
    if (charts['chartKanbanPriority']) {
        charts['chartKanbanPriority'].destroy();
    }
    
    const priorityColors = ['#10B981', '#3B82F6', '#F59E0B', '#EF4444'];
    
    charts['chartKanbanPriority'] = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels || [],
            datasets: [{
                data: data.values || [],
                backgroundColor: priorityColors.slice(0, data.labels.length),
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}
</script>

</body>
</html>
