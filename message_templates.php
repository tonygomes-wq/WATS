<?php
$page_title = 'Modelos de Mensagem';
require_once 'includes/header_spa.php';

$userId = $_SESSION['user_id'];

// Templates do sistema
$systemTemplates = [
    'Vendas' => [
        'color' => '#10B981',
        'templates' => [
            [
                'name' => 'Boas-vindas Cliente Novo',
                'content' => "Olá {{nome}}! 👋\n\nSeja muito bem-vindo(a) à {{empresa}}!\n\nEstamos muito felizes em tê-lo(a) conosco. Nossa equipe está à disposição para ajudá-lo(a) no que precisar.\n\nQualquer dúvida, é só chamar! 😊",
                'variables' => ['nome', 'empresa']
            ],
            [
                'name' => 'Oferta Especial',
                'content' => "🎉 OFERTA ESPECIAL para você, {{nome}}!\n\n{{produto}} com {{desconto}}% de desconto!\n\nDe: R\$ {{preco_original}}\nPor: R\$ {{preco_final}}\n\n⏰ Válido até {{data_validade}}\n\nGaranta já o seu!",
                'variables' => ['nome', 'produto', 'desconto', 'preco_original', 'preco_final', 'data_validade']
            ],
            [
                'name' => 'Carrinho Abandonado',
                'content' => "Oi {{nome}}! 🛒\n\nNotamos que você deixou alguns itens no carrinho:\n\n{{itens_carrinho}}\n\nQue tal finalizar sua compra? Temos condições especiais esperando por você!\n\n🔗 {{link_carrinho}}",
                'variables' => ['nome', 'itens_carrinho', 'link_carrinho']
            ]
        ]
    ],
    'Atendimento' => [
        'color' => '#3B82F6',
        'templates' => [
            [
                'name' => 'Confirmação de Agendamento',
                'content' => "✅ Agendamento Confirmado!\n\nOlá {{nome}},\n\nSeu agendamento foi confirmado:\n\n📅 Data: {{data}}\n🕐 Horário: {{horario}}\n📍 Local: {{local}}\n\nNos vemos em breve! 😊",
                'variables' => ['nome', 'data', 'horario', 'local']
            ],
            [
                'name' => 'Lembrete de Consulta',
                'content' => "⏰ Lembrete!\n\nOlá {{nome}},\n\nLembramos que você tem uma consulta agendada:\n\n📅 Amanhã às {{horario}}\n📍 {{local}}\n\nPor favor, chegue com 10 minutos de antecedência.\n\nAté breve! 👋",
                'variables' => ['nome', 'horario', 'local']
            ]
        ]
    ],
    'Cobrança' => [
        'color' => '#F59E0B',
        'templates' => [
            [
                'name' => 'Lembrete de Pagamento',
                'content' => "💰 Lembrete de Pagamento\n\nOlá {{nome}},\n\nSua fatura vence em {{dias_vencimento}} dias:\n\n🧾 Valor: R\$ {{valor}}\n📅 Vencimento: {{data_vencimento}}\n\n🔗 Pagar agora: {{link_pagamento}}\n\nEvite juros e multas! 😊",
                'variables' => ['nome', 'dias_vencimento', 'valor', 'data_vencimento', 'link_pagamento']
            ],
            [
                'name' => 'Pagamento Confirmado',
                'content' => "✅ Pagamento Confirmado!\n\nOlá {{nome}},\n\nRecebemos seu pagamento:\n\n💰 Valor: R\$ {{valor}}\n📅 Data: {{data_pagamento}}\n🧾 Recibo: {{numero_recibo}}\n\nObrigado pela preferência! 🙏",
                'variables' => ['nome', 'valor', 'data_pagamento', 'numero_recibo']
            ]
        ]
    ],
    'Marketing' => [
        'color' => '#8B5CF6',
        'templates' => [
            [
                'name' => 'Lançamento de Produto',
                'content' => "🚀 NOVIDADE!\n\nOlá {{nome}}!\n\nTemos o prazer de apresentar:\n\n✨ {{produto}}\n\n{{descricao}}\n\n🎁 Oferta de lançamento: {{desconto}}% OFF\n\n🔗 Saiba mais: {{link}}\n\nSeja um dos primeiros! 🌟",
                'variables' => ['nome', 'produto', 'descricao', 'desconto', 'link']
            ]
        ]
    ]
];

// Buscar modelos do usuário (requer tabelas do banco)
$userTemplates = [];
$categories = [];

try {
    $stmt = $pdo->prepare("
        SELECT mt.*, mtc.name as category_name, mtc.color as category_color
        FROM message_templates mt
        LEFT JOIN message_template_categories mtc ON mt.category_id = mtc.id
        WHERE mt.user_id = ?
        ORDER BY mtc.name, mt.name
    ");
    $stmt->execute([$userId]);
    $userTemplates = $stmt->fetchAll();

    // Buscar categorias
    $stmt = $pdo->prepare("
        SELECT * FROM message_template_categories 
        WHERE user_id = ? OR is_system = 1
        ORDER BY name
    ");
    $stmt->execute([$userId]);
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    // Tabelas ainda não criadas - usar apenas templates do sistema
    $userTemplates = [];
    $categories = [];
}

// Agrupar templates por categoria
$templatesByCategory = [];
foreach ($userTemplates as $template) {
    $catName = $template['category_name'] ?: 'Sem Categoria';
    if (!isset($templatesByCategory[$catName])) {
        $templatesByCategory[$catName] = [
            'color' => $template['category_color'] ?: '#6B7280',
            'templates' => []
        ];
    }
    $templatesByCategory[$catName]['templates'][] = $template;
}
?>

<style>
    :root[data-theme="dark"] .bg-white {
        background-color: #1f2937 !important;
    }
    :root[data-theme="dark"] .text-gray-800 {
        color: #f3f4f6 !important;
    }
    :root[data-theme="dark"] .text-gray-600 {
        color: #d1d5db !important;
    }
    :root[data-theme="dark"] .border-gray-200 {
        border-color: #374151 !important;
    }
    :root[data-theme="dark"] .bg-gray-50 {
        background-color: #111827 !important;
    }
    :root[data-theme="dark"] .bg-gray-100 {
        background-color: #1f2937 !important;
    }
</style>

<div class="refined-container">
    <div class="refined-card">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">
                    <i class="fas fa-envelope mr-3 text-green-600"></i>Modelos de Mensagem
                </h1>
                <p class="text-gray-600 mt-2">Templates prontos para usar em seus disparos</p>
            </div>
            <button onclick="alert('Funcionalidade em desenvolvimento')" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg transition font-medium">
                <i class="fas fa-plus mr-2"></i>Novo Modelo
            </button>
        </div>

        <!-- Abas -->
        <div class="border-b border-gray-200 mb-6">
            <nav class="flex space-x-4">
                <button onclick="switchTab('system')" id="tab-system" class="tab-button active px-4 py-2 font-medium border-b-2 border-green-600 text-green-600">
                    <i class="fas fa-star mr-2"></i>Modelos do Sistema
                </button>
                <button onclick="switchTab('user')" id="tab-user" class="tab-button px-4 py-2 font-medium border-b-2 border-transparent text-gray-600 hover:text-gray-800">
                    <i class="fas fa-user mr-2"></i>Meus Modelos (<?php echo count($userTemplates); ?>)
                </button>
            </nav>
        </div>

        <!-- Modelos do Sistema -->
        <div id="content-system" class="tab-content">
            <?php foreach ($systemTemplates as $categoryName => $categoryData): ?>
            <div class="mb-8">
                <div class="flex items-center mb-4">
                    <div class="w-1 h-8 rounded-full mr-3" style="background-color: <?php echo $categoryData['color']; ?>"></div>
                    <h2 class="text-2xl font-bold text-gray-800"><?php echo $categoryName; ?></h2>
                    <span class="ml-3 px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-sm font-medium">
                        <?php echo count($categoryData['templates']); ?> modelos
                    </span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($categoryData['templates'] as $template): ?>
                    <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-green-500 transition cursor-pointer" onclick='viewTemplate(<?php echo json_encode($template); ?>, "<?php echo $categoryName; ?>", "<?php echo $categoryData['color']; ?>")'>
                        <div class="flex items-start justify-between mb-3">
                            <h3 class="font-bold text-gray-800 flex-1"><?php echo $template['name']; ?></h3>
                            <i class="fas fa-eye text-gray-400 hover:text-green-600"></i>
                        </div>
                        
                        <p class="text-sm text-gray-600 mb-3 line-clamp-3">
                            <?php echo substr($template['content'], 0, 100) . '...'; ?>
                        </p>
                        
                        <div class="flex flex-wrap gap-1 mb-3">
                            <?php foreach ($template['variables'] as $var): ?>
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-mono">
                                {{<?php echo $var; ?>}}
                            </span>
                            <?php endforeach; ?>
                        </div>
                        
                        <button onclick="event.stopPropagation(); useTemplate(<?php echo htmlspecialchars(json_encode($template)); ?>)" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition text-sm font-medium">
                            <i class="fas fa-copy mr-2"></i>Usar este Modelo
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Meus Modelos -->
        <div id="content-user" class="tab-content hidden">
            <?php if (empty($userTemplates)): ?>
            <div class="text-center py-16">
                <div class="inline-block p-6 bg-gray-100 rounded-full mb-4">
                    <i class="fas fa-envelope text-6xl text-gray-400"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-2">Nenhum modelo criado</h3>
                <p class="text-gray-600 mb-6">Crie seu primeiro modelo personalizado ou use os modelos do sistema</p>
                <button onclick="alert('Funcionalidade em desenvolvimento')" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg transition font-medium inline-flex items-center">
                    <i class="fas fa-plus mr-2"></i>Criar Primeiro Modelo
                </button>
            </div>
            <?php else: ?>
                <?php foreach ($templatesByCategory as $categoryName => $categoryData): ?>
                <div class="mb-8">
                    <div class="flex items-center mb-4">
                        <div class="w-1 h-8 rounded-full mr-3" style="background-color: <?php echo $categoryData['color']; ?>"></div>
                        <h2 class="text-2xl font-bold text-gray-800"><?php echo $categoryName; ?></h2>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($categoryData['templates'] as $template): ?>
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-green-500 transition">
                            <h3 class="font-bold text-gray-800 mb-3"><?php echo htmlspecialchars($template['name']); ?></h3>
                            <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars(substr($template['content'], 0, 100)) . '...'; ?></p>
                            <div class="flex gap-2">
                                <button onclick="alert('Em desenvolvimento')" class="flex-1 bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-lg transition text-sm">
                                    <i class="fas fa-copy mr-1"></i>Usar
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal de Visualização -->
<div id="viewModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" onclick="closeViewModal(event)">
    <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-hidden" onclick="event.stopPropagation()">
        <div id="viewModalHeader" class="bg-gradient-to-r from-green-600 to-green-500 p-6 text-white">
            <div class="flex justify-between items-center">
                <h2 class="text-2xl font-bold" id="viewModalTitle"></h2>
                <button onclick="closeViewModal()" class="text-white hover:text-gray-200 transition">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>
        
        <div class="p-6 overflow-y-auto" style="max-height: calc(90vh - 200px);">
            <div class="mb-4">
                <h3 class="text-sm font-bold text-gray-700 mb-2">CONTEÚDO DA MENSAGEM</h3>
                <div id="viewModalContent" class="bg-gray-50 p-4 rounded-lg whitespace-pre-wrap text-gray-800 border border-gray-200"></div>
            </div>
            
            <div id="viewModalVariables" class="mb-4">
                <h3 class="text-sm font-bold text-gray-700 mb-2">VARIÁVEIS DISPONÍVEIS</h3>
                <div id="viewModalVariablesList" class="flex flex-wrap gap-2"></div>
            </div>
            
            <button onclick="useTemplateFromView()" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg transition">
                <i class="fas fa-copy mr-2"></i>Usar este Modelo
            </button>
        </div>
    </div>
</div>

<script>
let currentTemplate = null;

function switchTab(tab) {
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active', 'border-green-600', 'text-green-600');
        btn.classList.add('border-transparent', 'text-gray-600');
    });
    document.getElementById('tab-' + tab).classList.add('active', 'border-green-600', 'text-green-600');
    document.getElementById('tab-' + tab).classList.remove('border-transparent', 'text-gray-600');
    
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    document.getElementById('content-' + tab).classList.remove('hidden');
}

function viewTemplate(template, category, color) {
    currentTemplate = template;
    const modal = document.getElementById('viewModal');
    const header = document.getElementById('viewModalHeader');
    
    header.style.background = `linear-gradient(to right, ${color}, ${color}dd)`;
    document.getElementById('viewModalTitle').innerHTML = `<i class="fas fa-envelope mr-2"></i>${template.name}`;
    document.getElementById('viewModalContent').textContent = template.content;
    
    const varsList = document.getElementById('viewModalVariablesList');
    varsList.innerHTML = '';
    template.variables.forEach(v => {
        varsList.innerHTML += `<span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-mono">{{${v}}}</span>`;
    });
    
    modal.classList.remove('hidden');
}

function closeViewModal(event) {
    if (!event || event.target.id === 'viewModal') {
        document.getElementById('viewModal').classList.add('hidden');
    }
}

function useTemplateFromView() {
    if (currentTemplate) {
        useTemplate(currentTemplate);
    }
}

function useTemplate(template) {
    navigator.clipboard.writeText(template.content).then(() => {
        alert('✅ Modelo copiado para a área de transferência!\n\nVocê pode colar na página de Disparo.');
        closeViewModal();
    }).catch(() => {
        alert('❌ Erro ao copiar. Por favor, copie manualmente.');
    });
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeViewModal();
    }
});
</script>

<?php require_once 'includes/footer_spa.php'; ?>