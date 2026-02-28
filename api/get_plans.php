<?php
header('Content-Type: application/json');

require_once '../config/database.php';

try {
    // Buscar todos os planos ativos
    $stmt = $pdo->query("
        SELECT 
            id,
            name,
            description,
            price,
            is_popular,
            features
        FROM pricing_plans 
        WHERE is_active = 1 
        ORDER BY sort_order ASC, price ASC
    ");
    
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Se não houver planos no banco, retornar planos padrão
    if (empty($plans)) {
        $plans = [
            [
                'id' => 0,
                'name' => 'Grátis',
                'description' => 'Teste por 15 dias',
                'price' => '0.00',
                'is_popular' => 0,
                'features' => json_encode([
                    '15 dias de teste',
                    '500 mensagens/mês',
                    '1 número conectado',
                    'Suporte via email',
                    'Recursos básicos'
                ])
            ],
            [
                'id' => 1,
                'name' => 'Iniciante',
                'description' => 'Ideal para começar',
                'price' => '49.00',
                'is_popular' => 0,
                'features' => json_encode([
                    '2.000 mensagens/mês',
                    '1 número conectado',
                    'Suporte via e-mail',
                    'Relatórios básicos'
                ])
            ],
            [
                'id' => 2,
                'name' => 'Profissional',
                'description' => 'Mais vendido',
                'price' => '97.00',
                'is_popular' => 1,
                'features' => json_encode([
                    '10.000 mensagens/mês',
                    '2 números conectados',
                    'API liberada',
                    'Suporte via WhatsApp',
                    'Relatórios avançados',
                    'Funis automáticos'
                ])
            ],
            [
                'id' => 3,
                'name' => 'Empresarial',
                'description' => 'Solução completa',
                'price' => '197.00',
                'is_popular' => 0,
                'features' => json_encode([
                    'Mensagens ilimitadas',
                    '5 números conectados',
                    'API + Webhook',
                    'Suporte prioritário',
                    'Funis automáticos',
                    'Chatbot avançado'
                ])
            ]
        ];
    }
    
    echo json_encode([
        'success' => true,
        'plans' => $plans
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao buscar planos: " . $e->getMessage());
    
    // Retornar planos padrão em caso de erro
    echo json_encode([
        'success' => true,
        'plans' => [
            [
                'id' => 0,
                'name' => 'Grátis',
                'description' => 'Teste por 15 dias',
                'price' => '0.00',
                'is_popular' => 0,
                'features' => json_encode([
                    '15 dias de teste',
                    '500 mensagens/mês',
                    '1 número conectado',
                    'Suporte via email'
                ])
            ],
            [
                'id' => 1,
                'name' => 'Profissional',
                'description' => 'Mais vendido',
                'price' => '97.00',
                'is_popular' => 1,
                'features' => json_encode([
                    '10.000 mensagens/mês',
                    '2 números conectados',
                    'API liberada',
                    'Suporte via WhatsApp'
                ])
            ]
        ]
    ]);
}
