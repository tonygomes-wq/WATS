<?php
if (!isset($systemTemplates)) {
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
                'content' => "🎉 OFERTA ESPECIAL para você, {{nome}}!\n\n{{produto}} com {{desconto}}% de desconto!\n\nDe: R$ {{preco_original}}\nPor: R$ {{preco_final}}\n\n⏰ Válido até {{data_validade}}\n\nGaranta já o seu!",
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
                'content' => "💰 Lembrete de Pagamento\n\nOlá {{nome}},\n\nSua fatura vence em {{dias_vencimento}} dias:\n\n🧾 Valor: R$ {{valor}}\n📅 Vencimento: {{data_vencimento}}\n\n🔗 Pagar agora: {{link_pagamento}}\n\nEvite juros e multas! 😊",
                'variables' => ['nome', 'dias_vencimento', 'valor', 'data_vencimento', 'link_pagamento']
            ],
            [
                'name' => 'Pagamento Confirmado',
                'content' => "✅ Pagamento Confirmado!\n\nOlá {{nome}},\n\nRecebemos seu pagamento:\n\n💰 Valor: R$ {{valor}}\n📅 Data: {{data_pagamento}}\n🧾 Recibo: {{numero_recibo}}\n\nObrigado pela preferência! 🙏",
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
}

$systemTemplateList = [];
foreach ($systemTemplates as $categoryName => $categoryData) {
    foreach ($categoryData['templates'] as $template) {
        $systemTemplateList[] = [
            'name' => $template['name'],
            'content' => $template['content'],
            'variables' => $template['variables'],
            'category' => $categoryName,
            'color' => $categoryData['color']
        ];
    }
}
?>
