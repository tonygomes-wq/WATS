<?php
$page_title = 'Configurações Avançadas';
require_once 'includes/header_spa.php';

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Usuário';

// Inicializar variáveis com valores padrão
$settings = null;
$crmIntegration = null;
$activeTests = 0;

// Buscar configurações atuais (com tratamento de erro se tabela não existir)
try {
    $stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = ?");
    $stmt->execute([$userId]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Tabela não existe ainda
    $settings = null;
}

// Buscar integração CRM
try {
    $stmt = $pdo->prepare("SELECT * FROM crm_integrations WHERE user_id = ? AND sync_enabled = TRUE LIMIT 1");
    $stmt->execute([$userId]);
    $crmIntegration = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $crmIntegration = null;
}

// Buscar testes A/B ativos
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ab_tests WHERE user_id = ? AND status = 'running'");
    $stmt->execute([$userId]);
    $activeTests = $stmt->fetchColumn();
} catch (PDOException $e) {
    $activeTests = 0;
}
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <div class="container mx-auto px-4 py-6 max-w-7xl">
        <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-6">
            <i class="fas fa-cogs mr-2"></i>Configurações Avançadas
        </h1>
        
        <!-- Tabs de Navegação -->
        <div class="flex flex-wrap gap-2 mb-6 border-b border-gray-200">
            <button onclick="showTab('analytics')" id="tab-analytics" class="tab-btn px-4 py-2 font-medium text-blue-600 border-b-2 border-blue-600">
                <i class="fas fa-chart-line mr-1"></i> Análise Preditiva
            </button>
            <button onclick="showTab('segmentation')" id="tab-segmentation" class="tab-btn px-4 py-2 font-medium text-gray-500 hover:text-gray-700">
                <i class="fas fa-users mr-1"></i> Segmentação
            </button>
            <button onclick="showTab('abtesting')" id="tab-abtesting" class="tab-btn px-4 py-2 font-medium text-gray-500 hover:text-gray-700">
                <i class="fas fa-flask mr-1"></i> A/B Testing
            </button>
            <button onclick="showTab('autoreply')" id="tab-autoreply" class="tab-btn px-4 py-2 font-medium text-gray-500 hover:text-gray-700">
                <i class="fas fa-robot mr-1"></i> Auto-Resposta
            </button>
            <button onclick="showTab('crm')" id="tab-crm" class="tab-btn px-4 py-2 font-medium text-gray-500 hover:text-gray-700">
                <i class="fas fa-plug mr-1"></i> CRM
            </button>
        </div>
        
        <!-- Tab: Análise Preditiva -->
        <div id="content-analytics" class="tab-content">
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">
                    <i class="fas fa-clock text-blue-500 mr-2"></i>Melhor Horário para Envio
                </h2>
                <p class="text-gray-600 mb-4">Análise baseada no histórico de engajamento dos seus contatos.</p>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Melhores Horários -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h3 class="font-medium mb-3">🏆 Top 5 Melhores Horários</h3>
                        <div id="bestTimesContainer" class="space-y-2">
                            <div class="animate-pulse bg-gray-200 h-10 rounded"></div>
                            <div class="animate-pulse bg-gray-200 h-10 rounded"></div>
                            <div class="animate-pulse bg-gray-200 h-10 rounded"></div>
                        </div>
                    </div>
                    
                    <!-- Próximo Melhor Horário -->
                    <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg p-4 text-white">
                        <h3 class="font-medium mb-3">⏰ Próximo Melhor Horário</h3>
                        <div id="nextBestTime" class="text-center py-4">
                            <div class="text-4xl font-bold" id="suggestedTime">--:--</div>
                            <div class="text-blue-100 mt-2" id="suggestedDay">Carregando...</div>
                            <div class="mt-4">
                                <span class="bg-white/20 px-3 py-1 rounded-full text-sm" id="suggestedScore">
                                    Score: --
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Heatmap -->
                <div class="mt-6">
                    <h3 class="font-medium mb-3">📊 Mapa de Calor - Engajamento por Horário</h3>
                    <div class="overflow-x-auto">
                        <div id="heatmapContainer" class="min-w-[600px]">
                            <canvas id="heatmapChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 flex justify-end">
                    <button onclick="recalculateAnalytics()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        <i class="fas fa-sync-alt mr-1"></i> Recalcular Análise
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Tab: Segmentação -->
        <div id="content-segmentation" class="tab-content hidden">
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">
                    <i class="fas fa-layer-group text-green-500 mr-2"></i>Segmentação por Engajamento
                </h2>
                <p class="text-gray-600 mb-4">Seus contatos classificados automaticamente pelo nível de engajamento.</p>
                
                <!-- Resumo -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                        <div class="text-3xl font-bold text-green-600" id="seg-high">-</div>
                        <div class="text-sm text-green-700">Alto Engajamento</div>
                    </div>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
                        <div class="text-3xl font-bold text-blue-600" id="seg-medium">-</div>
                        <div class="text-sm text-blue-700">Médio Engajamento</div>
                    </div>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">
                        <div class="text-3xl font-bold text-yellow-600" id="seg-low">-</div>
                        <div class="text-sm text-yellow-700">Baixo Engajamento</div>
                    </div>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">
                        <div class="text-3xl font-bold text-red-600" id="seg-inactive">-</div>
                        <div class="text-sm text-red-700">Inativos</div>
                    </div>
                </div>
                
                <!-- Top Engajados -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h3 class="font-medium mb-3 text-green-600">🌟 Top 10 Mais Engajados</h3>
                        <div id="topEngagedList" class="space-y-2 max-h-64 overflow-y-auto">
                            <div class="animate-pulse bg-gray-200 h-8 rounded"></div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h3 class="font-medium mb-3 text-red-600">⚠️ Precisam de Atenção</h3>
                        <div id="needAttentionList" class="space-y-2 max-h-64 overflow-y-auto">
                            <div class="animate-pulse bg-gray-200 h-8 rounded"></div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 flex justify-end">
                    <button onclick="recalculateSegmentation()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                        <i class="fas fa-sync-alt mr-1"></i> Recalcular Segmentação
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Tab: A/B Testing -->
        <div id="content-abtesting" class="tab-content hidden">
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold">
                        <i class="fas fa-flask text-purple-500 mr-2"></i>Testes A/B
                    </h2>
                    <button onclick="showCreateTestModal()" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">
                        <i class="fas fa-plus mr-1"></i> Novo Teste
                    </button>
                </div>
                <p class="text-gray-600 mb-4">Compare diferentes versões de mensagens para descobrir qual funciona melhor.</p>
                
                <!-- Lista de Testes -->
                <div id="abTestsList" class="space-y-4">
                    <div class="animate-pulse bg-gray-100 h-24 rounded"></div>
                </div>
            </div>
        </div>
        
        <!-- Tab: Auto-Resposta -->
        <div id="content-autoreply" class="tab-content hidden">
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">
                    <i class="fas fa-robot text-orange-500 mr-2"></i>Respostas Automáticas por Sentimento
                </h2>
                <p class="text-gray-600 mb-4">Configure mensagens automáticas baseadas no sentimento detectado nas respostas.</p>
                
                <div class="mb-6">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" id="autoReplyEnabled" class="sr-only peer" <?= ($settings && isset($settings['auto_reply_enabled']) && $settings['auto_reply_enabled']) ? 'checked' : '' ?>>
                        <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-orange-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-orange-500"></div>
                        <span class="ml-3 text-sm font-medium text-gray-700">Ativar respostas automáticas</span>
                    </label>
                </div>
                
                <div class="space-y-4">
                    <!-- Template Positivo -->
                    <div class="border border-green-200 rounded-lg p-4 bg-green-50">
                        <label class="block text-sm font-medium text-green-700 mb-2">
                            <i class="fas fa-smile mr-1"></i> Resposta para Sentimento Positivo
                        </label>
                        <textarea id="positiveTemplate" rows="3" class="w-full border rounded-lg p-2 text-sm" placeholder="Olá {nome}! 😊 Obrigado pela sua mensagem positiva!"></textarea>
                        <p class="text-xs text-green-600 mt-1">Use {nome} para inserir o nome do contato</p>
                    </div>
                    
                    <!-- Template Negativo -->
                    <div class="border border-red-200 rounded-lg p-4 bg-red-50">
                        <label class="block text-sm font-medium text-red-700 mb-2">
                            <i class="fas fa-frown mr-1"></i> Resposta para Sentimento Negativo
                        </label>
                        <textarea id="negativeTemplate" rows="3" class="w-full border rounded-lg p-2 text-sm" placeholder="Olá {nome}, lamentamos muito. Um atendente entrará em contato em breve."></textarea>
                        <p class="text-xs text-red-600 mt-1">Use {nome} para inserir o nome do contato</p>
                    </div>
                    
                    <!-- Template Neutro -->
                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-meh mr-1"></i> Resposta para Sentimento Neutro
                        </label>
                        <textarea id="neutralTemplate" rows="3" class="w-full border rounded-lg p-2 text-sm" placeholder="Olá {nome}! 👋 Recebemos sua mensagem e retornaremos em breve."></textarea>
                        <p class="text-xs text-gray-600 mt-1">Use {nome} para inserir o nome do contato</p>
                    </div>
                </div>
                
                <div class="mt-4 flex justify-end">
                    <button onclick="saveAutoReplySettings()" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">
                        <i class="fas fa-save mr-1"></i> Salvar Configurações
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Tab: CRM -->
        <div id="content-crm" class="tab-content hidden">
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">
                    <i class="fas fa-plug text-indigo-500 mr-2"></i>Integração com CRM
                </h2>
                <p class="text-gray-600 mb-4">Conecte seu sistema de disparo com seu CRM favorito.</p>
                
                <!-- Status da Integração -->
                <div id="crmStatus" class="mb-6 p-4 rounded-lg <?= $crmIntegration ? 'bg-green-50 border border-green-200' : 'bg-gray-50 border border-gray-200' ?>">
                    <?php if ($crmIntegration): ?>
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-green-600 font-medium">
                                    <i class="fas fa-check-circle mr-1"></i> Conectado ao <?= ucfirst($crmIntegration['crm_type']) ?>
                                </span>
                                <p class="text-sm text-gray-600 mt-1">API Key: <?= substr($crmIntegration['api_key'], 0, 8) ?>********</p>
                            </div>
                            <button onclick="disconnectCRM()" class="text-red-500 hover:text-red-700">
                                <i class="fas fa-unlink"></i> Desconectar
                            </button>
                        </div>
                    <?php else: ?>
                        <span class="text-gray-500">
                            <i class="fas fa-info-circle mr-1"></i> Nenhum CRM conectado
                        </span>
                    <?php endif; ?>
                </div>
                
                <!-- Seleção de CRM -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <button onclick="selectCRM('pipedrive')" class="crm-option border-2 border-gray-200 rounded-lg p-4 text-center hover:border-indigo-500 transition-colors">
                        <img src="https://www.pipedrive.com/favicon.ico" alt="Pipedrive" class="w-10 h-10 mx-auto mb-2">
                        <span class="text-sm font-medium">Pipedrive</span>
                    </button>
                    <button onclick="selectCRM('hubspot')" class="crm-option border-2 border-gray-200 rounded-lg p-4 text-center hover:border-indigo-500 transition-colors">
                        <img src="https://www.hubspot.com/favicon.ico" alt="HubSpot" class="w-10 h-10 mx-auto mb-2">
                        <span class="text-sm font-medium">HubSpot</span>
                    </button>
                    <button onclick="selectCRM('rd_station')" class="crm-option border-2 border-gray-200 rounded-lg p-4 text-center hover:border-indigo-500 transition-colors">
                        <img src="https://www.rdstation.com/favicon.ico" alt="RD Station" class="w-10 h-10 mx-auto mb-2">
                        <span class="text-sm font-medium">RD Station</span>
                    </button>
                    <button onclick="selectCRM('custom')" class="crm-option border-2 border-gray-200 rounded-lg p-4 text-center hover:border-indigo-500 transition-colors">
                        <i class="fas fa-code text-3xl text-gray-400 mb-2"></i>
                        <span class="text-sm font-medium">Webhook</span>
                    </button>
                </div>
                
                <!-- Formulário de Configuração -->
                <div id="crmConfigForm" class="hidden border rounded-lg p-4 bg-gray-50">
                    <h3 class="font-medium mb-4">Configurar <span id="selectedCRMName">CRM</span></h3>
                    <input type="hidden" id="selectedCRMType">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
                            <input type="text" id="crmApiKey" class="w-full border rounded-lg p-2" placeholder="Sua API Key">
                        </div>
                        
                        <div id="webhookUrlField" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Webhook URL</label>
                            <input type="url" id="crmWebhookUrl" class="w-full border rounded-lg p-2" placeholder="https://seu-sistema.com/webhook">
                        </div>
                        
                        <div class="flex items-center gap-4">
                            <label class="flex items-center">
                                <input type="checkbox" id="syncContacts" checked class="mr-2">
                                <span class="text-sm">Sincronizar contatos</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" id="syncResponses" checked class="mr-2">
                                <span class="text-sm">Sincronizar respostas</span>
                            </label>
                        </div>
                        
                        <div class="flex gap-2">
                            <button onclick="testCRMConnection()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                                <i class="fas fa-plug mr-1"></i> Testar Conexão
                            </button>
                            <button onclick="saveCRMConfig()" class="bg-indigo-500 text-white px-4 py-2 rounded hover:bg-indigo-600">
                                <i class="fas fa-save mr-1"></i> Salvar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Criar Teste A/B -->
    <div id="createTestModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-lg mx-4">
            <h3 class="text-lg font-semibold mb-4">Criar Novo Teste A/B</h3>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome do Teste</label>
                    <input type="text" id="testName" class="w-full border rounded-lg p-2" placeholder="Ex: Teste saudação formal vs informal">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Descrição (opcional)</label>
                    <input type="text" id="testDescription" class="w-full border rounded-lg p-2" placeholder="Descrição do objetivo do teste">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-blue-700 mb-1">
                            <i class="fas fa-a mr-1"></i> Variante A
                        </label>
                        <textarea id="variantA" rows="4" class="w-full border border-blue-200 rounded-lg p-2 text-sm" placeholder="Mensagem da variante A..."></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-green-700 mb-1">
                            <i class="fas fa-b mr-1"></i> Variante B
                        </label>
                        <textarea id="variantB" rows="4" class="w-full border border-green-200 rounded-lg p-2 text-sm" placeholder="Mensagem da variante B..."></textarea>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end gap-2 mt-6">
                <button onclick="closeCreateTestModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800">Cancelar</button>
                <button onclick="createABTest()" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">
                    <i class="fas fa-plus mr-1"></i> Criar Teste
                </button>
            </div>
        </div>
    </div>
    
    <!-- Toast -->
    <div id="toast" class="fixed bottom-4 right-4 bg-gray-800 text-white px-4 py-2 rounded-lg shadow-lg hidden z-50"></div>

    <script>
        // ==========================================
        // Navegação por Tabs
        // ==========================================
        function showTab(tabName) {
            // Esconder todos os conteúdos
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            // Remover estilo ativo de todas as tabs
            document.querySelectorAll('.tab-btn').forEach(el => {
                el.classList.remove('text-blue-600', 'border-b-2', 'border-blue-600');
                el.classList.add('text-gray-500');
            });
            
            // Mostrar conteúdo selecionado
            document.getElementById('content-' + tabName).classList.remove('hidden');
            // Ativar tab selecionada
            const activeTab = document.getElementById('tab-' + tabName);
            activeTab.classList.remove('text-gray-500');
            activeTab.classList.add('text-blue-600', 'border-b-2', 'border-blue-600');
            
            // Carregar dados da tab
            loadTabData(tabName);
        }
        
        function loadTabData(tabName) {
            switch(tabName) {
                case 'analytics':
                    loadPredictiveAnalytics();
                    break;
                case 'segmentation':
                    loadSegmentation();
                    break;
                case 'abtesting':
                    loadABTests();
                    break;
                case 'autoreply':
                    loadAutoReplySettings();
                    break;
            }
        }
        
        // ==========================================
        // Análise Preditiva
        // ==========================================
        async function loadPredictiveAnalytics() {
            try {
                // Carregar melhores horários
                const bestTimesRes = await fetch('api/predictive_analytics.php?action=best_times&limit=5');
                
                if (!bestTimesRes.ok) {
                    throw new Error('API não disponível');
                }
                
                const bestTimesData = await bestTimesRes.json();
                
                if (bestTimesData.success && bestTimesData.best_times.length > 0) {
                    const container = document.getElementById('bestTimesContainer');
                    container.innerHTML = bestTimesData.best_times.map((item, index) => `
                        <div class="flex items-center justify-between bg-white p-2 rounded border">
                            <span class="font-medium">${index + 1}. ${getDayName(item.day_of_week)} às ${item.hour_of_day}:00</span>
                            <span class="text-sm text-gray-500">Score: ${item.engagement_score}</span>
                        </div>
                    `).join('');
                } else {
                    document.getElementById('bestTimesContainer').innerHTML = 
                        '<p class="text-gray-500 text-sm">Dados insuficientes para análise. Envie mais mensagens para gerar estatísticas.</p>';
                }
                
                // Carregar sugestão de próximo horário
                const suggestRes = await fetch('api/predictive_analytics.php?action=suggest_next');
                const suggestData = await suggestRes.json();
                
                if (suggestData.success && suggestData.suggestion) {
                    document.getElementById('suggestedTime').textContent = 
                        suggestData.suggestion.hour.toString().padStart(2, '0') + ':00';
                    document.getElementById('suggestedDay').textContent = 
                        getDayName(suggestData.suggestion.day);
                    document.getElementById('suggestedScore').textContent = 
                        'Score: ' + (suggestData.suggestion.score || '--');
                }
                
                // Carregar heatmap
                loadHeatmap();
                
            } catch (error) {
                console.error('Erro ao carregar analytics:', error);
                document.getElementById('bestTimesContainer').innerHTML = 
                    '<div class="bg-yellow-50 border border-yellow-200 rounded p-3 text-sm text-yellow-700"><i class="fas fa-exclamation-triangle mr-2"></i>Funcionalidade em desenvolvimento. Execute o script SQL para ativar.</div>';
                document.getElementById('suggestedTime').textContent = '--:--';
                document.getElementById('suggestedDay').textContent = 'Não disponível';
                document.getElementById('suggestedScore').textContent = 'Score: --';
            }
        }
        
        async function loadHeatmap() {
            try {
                const res = await fetch('api/predictive_analytics.php?action=heatmap');
                const data = await res.json();
                
                if (data.success && data.heatmap) {
                    renderHeatmap(data.heatmap);
                }
            } catch (error) {
                console.error('Erro ao carregar heatmap:', error);
            }
        }
        
        function renderHeatmap(heatmapData) {
            const ctx = document.getElementById('heatmapChart').getContext('2d');
            
            // Preparar dados para o gráfico
            const days = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
            const hours = Array.from({length: 24}, (_, i) => i + 'h');
            
            // Criar datasets por dia
            const datasets = days.map((day, dayIndex) => {
                const dayData = heatmapData.filter(h => h.day_of_week == dayIndex + 1);
                const scores = Array(24).fill(0);
                dayData.forEach(h => {
                    scores[h.hour_of_day] = parseFloat(h.engagement_score) || 0;
                });
                return {
                    label: day,
                    data: scores,
                    borderWidth: 1
                };
            });
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: hours,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: true, position: 'top' }
                    },
                    scales: {
                        y: { beginAtZero: true, title: { display: true, text: 'Score' } }
                    }
                }
            });
        }
        
        function getDayName(dayNum) {
            const days = ['', 'Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
            return days[dayNum] || 'Dia ' + dayNum;
        }
        
        async function recalculateAnalytics() {
            showToast('Recalculando análise...');
            try {
                const res = await fetch('api/predictive_analytics.php?action=recalculate', { method: 'POST' });
                const data = await res.json();
                if (data.success) {
                    showToast('Análise recalculada!', 'success');
                    loadPredictiveAnalytics();
                }
            } catch (error) {
                showToast('Erro ao recalcular', 'error');
            }
        }
        
        // ==========================================
        // Segmentação
        // ==========================================
        async function loadSegmentation() {
            try {
                const res = await fetch('api/engagement_segmentation.php?action=summary');
                const data = await res.json();
                
                if (data.success) {
                    document.getElementById('seg-high').textContent = data.summary.high || 0;
                    document.getElementById('seg-medium').textContent = data.summary.medium || 0;
                    document.getElementById('seg-low').textContent = data.summary.low || 0;
                    document.getElementById('seg-inactive').textContent = data.summary.inactive || 0;
                }
                
                // Top engajados
                const topRes = await fetch('api/engagement_segmentation.php?action=top_engaged&limit=10');
                const topData = await topRes.json();
                
                if (topData.success && topData.contacts) {
                    document.getElementById('topEngagedList').innerHTML = topData.contacts.map(c => `
                        <div class="flex items-center justify-between bg-white p-2 rounded border text-sm">
                            <span>${c.name || c.phone}</span>
                            <span class="text-green-600 font-medium">${c.score}</span>
                        </div>
                    `).join('') || '<p class="text-gray-500 text-sm">Nenhum dado disponível</p>';
                }
                
                // Precisam atenção
                const attRes = await fetch('api/engagement_segmentation.php?action=need_attention');
                const attData = await attRes.json();
                
                if (attData.success && attData.contacts) {
                    document.getElementById('needAttentionList').innerHTML = attData.contacts.map(c => `
                        <div class="flex items-center justify-between bg-white p-2 rounded border text-sm">
                            <span>${c.name || c.phone}</span>
                            <span class="text-red-600 font-medium">${c.score}</span>
                        </div>
                    `).join('') || '<p class="text-gray-500 text-sm">Nenhum contato precisa de atenção</p>';
                }
                
            } catch (error) {
                console.error('Erro ao carregar segmentação:', error);
            }
        }
        
        async function recalculateSegmentation() {
            showToast('Recalculando segmentação...');
            try {
                const res = await fetch('api/engagement_segmentation.php?action=recalculate_all', { method: 'POST' });
                const data = await res.json();
                if (data.success) {
                    showToast('Segmentação recalculada!', 'success');
                    loadSegmentation();
                }
            } catch (error) {
                showToast('Erro ao recalcular', 'error');
            }
        }
        
        // ==========================================
        // A/B Testing
        // ==========================================
        async function loadABTests() {
            try {
                const res = await fetch('api/ab_testing.php?action=list');
                const data = await res.json();
                
                const container = document.getElementById('abTestsList');
                
                if (data.success && data.tests && data.tests.length > 0) {
                    container.innerHTML = data.tests.map(test => `
                        <div class="border rounded-lg p-4 ${getTestStatusClass(test.status)}">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="font-medium">${test.name}</h4>
                                    <p class="text-sm text-gray-500">${test.description || 'Sem descrição'}</p>
                                </div>
                                <span class="px-2 py-1 text-xs rounded ${getStatusBadgeClass(test.status)}">${getStatusLabel(test.status)}</span>
                            </div>
                            <div class="grid grid-cols-2 gap-4 mt-4 text-sm">
                                <div class="bg-blue-50 p-2 rounded">
                                    <strong class="text-blue-700">Variante A:</strong>
                                    <div>Enviados: ${test.variant_a_sent} | Respostas: ${test.variant_a_responses}</div>
                                </div>
                                <div class="bg-green-50 p-2 rounded">
                                    <strong class="text-green-700">Variante B:</strong>
                                    <div>Enviados: ${test.variant_b_sent} | Respostas: ${test.variant_b_responses}</div>
                                </div>
                            </div>
                            ${test.status === 'completed' ? `
                                <div class="mt-3 p-2 bg-purple-50 rounded text-center">
                                    <strong>Vencedor: Variante ${test.winner.toUpperCase()}</strong>
                                    <span class="text-sm text-gray-500">(${test.confidence_level}% confiança)</span>
                                </div>
                            ` : ''}
                            ${test.status === 'draft' ? `
                                <div class="mt-3 flex gap-2">
                                    <button onclick="startTest(${test.id})" class="text-sm bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600">
                                        <i class="fas fa-play mr-1"></i> Iniciar
                                    </button>
                                    <button onclick="cancelTest(${test.id})" class="text-sm bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600">
                                        <i class="fas fa-trash mr-1"></i> Excluir
                                    </button>
                                </div>
                            ` : ''}
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = `
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-flask text-4xl mb-2"></i>
                            <p>Nenhum teste A/B criado ainda</p>
                            <p class="text-sm">Crie seu primeiro teste para comparar mensagens</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Erro ao carregar testes:', error);
            }
        }
        
        function getTestStatusClass(status) {
            const classes = {
                'draft': 'bg-gray-50',
                'running': 'bg-yellow-50 border-yellow-200',
                'completed': 'bg-green-50 border-green-200',
                'cancelled': 'bg-red-50 border-red-200'
            };
            return classes[status] || 'bg-gray-50';
        }
        
        function getStatusBadgeClass(status) {
            const classes = {
                'draft': 'bg-gray-200 text-gray-700',
                'running': 'bg-yellow-200 text-yellow-700',
                'completed': 'bg-green-200 text-green-700',
                'cancelled': 'bg-red-200 text-red-700'
            };
            return classes[status] || 'bg-gray-200';
        }
        
        function getStatusLabel(status) {
            const labels = {
                'draft': 'Rascunho',
                'running': 'Em execução',
                'completed': 'Concluído',
                'cancelled': 'Cancelado'
            };
            return labels[status] || status;
        }
        
        function showCreateTestModal() {
            document.getElementById('createTestModal').classList.remove('hidden');
            document.getElementById('createTestModal').classList.add('flex');
        }
        
        function closeCreateTestModal() {
            document.getElementById('createTestModal').classList.add('hidden');
            document.getElementById('createTestModal').classList.remove('flex');
        }
        
        async function createABTest() {
            const name = document.getElementById('testName').value;
            const description = document.getElementById('testDescription').value;
            const variantA = document.getElementById('variantA').value;
            const variantB = document.getElementById('variantB').value;
            
            if (!name || !variantA || !variantB) {
                showToast('Preencha todos os campos obrigatórios', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'create');
            formData.append('name', name);
            formData.append('description', description);
            formData.append('variant_a_message', variantA);
            formData.append('variant_b_message', variantB);
            
            try {
                const res = await fetch('api/ab_testing.php', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.success) {
                    showToast('Teste criado com sucesso!', 'success');
                    closeCreateTestModal();
                    loadABTests();
                } else {
                    showToast(data.error || 'Erro ao criar teste', 'error');
                }
            } catch (error) {
                showToast('Erro ao criar teste', 'error');
            }
        }
        
        async function startTest(testId) {
            const formData = new FormData();
            formData.append('action', 'start');
            formData.append('test_id', testId);
            
            try {
                const res = await fetch('api/ab_testing.php', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.success) {
                    showToast('Teste iniciado!', 'success');
                    loadABTests();
                }
            } catch (error) {
                showToast('Erro ao iniciar teste', 'error');
            }
        }
        
        async function cancelTest(testId) {
            if (!confirm('Tem certeza que deseja cancelar este teste?')) return;
            
            const formData = new FormData();
            formData.append('action', 'cancel');
            formData.append('test_id', testId);
            
            try {
                const res = await fetch('api/ab_testing.php', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.success) {
                    showToast('Teste cancelado', 'success');
                    loadABTests();
                }
            } catch (error) {
                showToast('Erro ao cancelar teste', 'error');
            }
        }
        
        // ==========================================
        // Auto-Reply
        // ==========================================
        async function loadAutoReplySettings() {
            try {
                const res = await fetch('api/auto_reply.php?action=get_settings');
                const data = await res.json();
                
                if (data.success && data.settings) {
                    document.getElementById('autoReplyEnabled').checked = data.settings.enabled;
                    
                    if (data.settings.config) {
                        document.getElementById('positiveTemplate').value = data.settings.config.positive_template || '';
                        document.getElementById('negativeTemplate').value = data.settings.config.negative_template || '';
                        document.getElementById('neutralTemplate').value = data.settings.config.neutral_template || '';
                    }
                }
            } catch (error) {
                console.error('Erro ao carregar configurações:', error);
            }
        }
        
        async function saveAutoReplySettings() {
            const formData = new FormData();
            formData.append('action', 'update_settings');
            formData.append('enabled', document.getElementById('autoReplyEnabled').checked ? '1' : '0');
            formData.append('positive_template', document.getElementById('positiveTemplate').value);
            formData.append('negative_template', document.getElementById('negativeTemplate').value);
            formData.append('neutral_template', document.getElementById('neutralTemplate').value);
            
            try {
                const res = await fetch('api/auto_reply.php', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.success) {
                    showToast('Configurações salvas!', 'success');
                } else {
                    showToast(data.error || 'Erro ao salvar', 'error');
                }
            } catch (error) {
                showToast('Erro ao salvar configurações', 'error');
            }
        }
        
        // ==========================================
        // CRM
        // ==========================================
        function selectCRM(crmType) {
            document.querySelectorAll('.crm-option').forEach(el => {
                el.classList.remove('border-indigo-500', 'bg-indigo-50');
            });
            event.currentTarget.classList.add('border-indigo-500', 'bg-indigo-50');
            
            document.getElementById('selectedCRMType').value = crmType;
            document.getElementById('selectedCRMName').textContent = crmType === 'custom' ? 'Webhook' : crmType.charAt(0).toUpperCase() + crmType.slice(1);
            document.getElementById('crmConfigForm').classList.remove('hidden');
            
            // Mostrar campo de webhook apenas para custom
            document.getElementById('webhookUrlField').classList.toggle('hidden', crmType !== 'custom');
        }
        
        async function testCRMConnection() {
            showToast('Testando conexão...');
            try {
                const res = await fetch('api/crm_integration.php?action=test');
                const data = await res.json();
                
                if (data.success) {
                    showToast('Conexão bem sucedida!', 'success');
                } else {
                    showToast(data.message || 'Falha na conexão', 'error');
                }
            } catch (error) {
                showToast('Erro ao testar conexão', 'error');
            }
        }
        
        async function saveCRMConfig() {
            const formData = new FormData();
            formData.append('action', 'configure');
            formData.append('crm_type', document.getElementById('selectedCRMType').value);
            formData.append('api_key', document.getElementById('crmApiKey').value);
            formData.append('webhook_url', document.getElementById('crmWebhookUrl').value);
            formData.append('sync_contacts', document.getElementById('syncContacts').checked ? '1' : '0');
            formData.append('sync_responses', document.getElementById('syncResponses').checked ? '1' : '0');
            
            try {
                const res = await fetch('api/crm_integration.php', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.success) {
                    showToast('CRM configurado com sucesso!', 'success');
                    location.reload();
                } else {
                    showToast(data.error || 'Erro ao configurar', 'error');
                }
            } catch (error) {
                showToast('Erro ao salvar configuração', 'error');
            }
        }
        
        async function disconnectCRM() {
            if (!confirm('Deseja desconectar o CRM?')) return;
            
            const formData = new FormData();
            formData.append('action', 'disable');
            
            try {
                const res = await fetch('api/crm_integration.php', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.success) {
                    showToast('CRM desconectado', 'success');
                    location.reload();
                }
            } catch (error) {
                showToast('Erro ao desconectar', 'error');
            }
        }
        
        // ==========================================
        // Utilitários
        // ==========================================
        function showToast(message, type = 'info') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'fixed bottom-4 right-4 px-4 py-2 rounded-lg shadow-lg z-50';
            
            if (type === 'success') toast.classList.add('bg-green-500', 'text-white');
            else if (type === 'error') toast.classList.add('bg-red-500', 'text-white');
            else toast.classList.add('bg-gray-800', 'text-white');
            
            toast.classList.remove('hidden');
            setTimeout(() => toast.classList.add('hidden'), 3000);
        }
        
        // Carregar dados iniciais e verificar navegação por hash/localStorage
        document.addEventListener('DOMContentLoaded', () => {
            // Verificar se há tab salva no localStorage (vindo de dispatch_reports)
            const savedTab = localStorage.getItem('dispatchSettingsTab');
            if (savedTab) {
                showTab(savedTab);
                localStorage.removeItem('dispatchSettingsTab');
            } else {
                // Verificar hash na URL
                const hash = window.location.hash.replace('#', '');
                if (hash && ['analytics', 'segmentation', 'abtesting', 'autoreply', 'crm'].includes(hash)) {
                    showTab(hash);
                } else {
                    loadPredictiveAnalytics();
                }
            }
        });
        
        // Atualizar tab quando hash mudar
        window.addEventListener('hashchange', () => {
            const hash = window.location.hash.replace('#', '');
            if (hash && ['analytics', 'segmentation', 'abtesting', 'autoreply', 'crm'].includes(hash)) {
                showTab(hash);
            }
        });
    </script>

</div><!-- fecha flex-1 overflow-y-auto -->
        </div><!-- fecha flex-1 flex-col main-content -->
    </div><!-- fecha flex h-screen -->
</body>
</html>
