<?php
// Planos fixos - sem conexão com banco de dados
$pricingPlans = [
    [
        'name' => 'Iniciante',
        'price' => 197,
        'is_popular' => 0,
        'description' => 'Ideal para pequenos negócios',
        'features' => [
            '5.000 mensagens/mês',
            '1 número conectado',
            '2 usuários inclusos',
            'Suporte via email',
            'Relatórios básicos',
            'Integração WhatsApp Web'
        ]
    ],
    [
        'name' => 'Profissional',
        'price' => 297,
        'is_popular' => 1,
        'description' => 'Para equipes em crescimento',
        'features' => [
            '25.000 mensagens/mês',
            '3 números conectados',
            '5 usuários inclusos',
            'Suporte via WhatsApp',
            'Relatórios avançados',
            'Tags e segmentação'
        ]
    ],
    [
        'name' => 'Business',
        'price' => 497,
        'is_popular' => 0,
        'description' => 'Para empresas que precisam escalar',
        'features' => [
            'Mensagens ilimitadas',
            '10 números conectados',
            'Usuários ilimitados',
            'Suporte Especializado',
            'Gerente de conta dedicado',
            'Treinamento personalizado'
        ]
    ]
];
?>

<!-- Pricing Section - Redesigned -->
<section id="planos" class="py-24 bg-black relative overflow-hidden">
    <!-- Background Effects -->
    <div class="absolute inset-0 opacity-30">
        <div
            class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-primary/10 rounded-full blur-3xl">
        </div>
    </div>

    <div class="container mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <!-- Section Header -->
        <div class="text-center mb-16" data-aos="fade-up">
            <div
                class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-primary/10 border border-primary/20 mb-4">
                <svg class="w-4 h-4 text-primary" fill="currentColor" viewBox="0 0 20 20">
                    <path
                        d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" />
                </svg>
                <span class="text-sm font-medium text-gray-300">Preços Transparentes</span>
            </div>

            <h2 class="text-4xl sm:text-5xl lg:text-6xl font-black mb-6 leading-tight">
                Planos para
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-neon">cada negócio</span>
            </h2>

            <p class="text-xl text-gray-400 max-w-2xl mx-auto">
                Escolha o plano ideal. Cancele quando quiser, sem multas ou fidelidade.
            </p>

            <!-- Billing Toggle -->
            <div class="flex items-center justify-center gap-4 mt-8">
                <span class="text-gray-400 billing-label" id="monthly-label">Mensal</span>
                <button type="button" id="billing-toggle" onclick="toggleBilling()"
                    class="relative w-16 h-8 bg-gray-700 rounded-full transition-colors duration-300 focus:outline-none focus:ring-2 focus:ring-primary/50"
                    role="switch" aria-checked="false" aria-label="Alternar entre cobrança mensal e anual">
                    <span id="toggle-dot"
                        class="absolute left-1 top-1 w-6 h-6 bg-white rounded-full shadow-md transform transition-transform duration-300"></span>
                </button>
                <span class="text-gray-400 billing-label" id="yearly-label">
                    Anual
                    <span class="ml-2 px-2 py-0.5 bg-primary/20 text-primary text-xs font-bold rounded-full">-20%</span>
                </span>
            </div>
        </div>

        <!-- Pricing Cards -->
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 max-w-6xl mx-auto">
            <?php
            $delay = 100;
            foreach ($pricingPlans as $plan):
                $features = $plan['features'];
                $isPopular = $plan['is_popular'] == 1;
                $description = $plan['description'];
                ?>

                <!-- Plan: <?php echo htmlspecialchars($plan['name']); ?> -->
                <div class="relative group" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                    <?php if ($isPopular): ?>
                        <div
                            class="absolute -top-4 left-1/2 -translate-x-1/2 px-4 py-1 bg-gradient-to-r from-primary to-neon text-white text-sm font-bold rounded-full z-10">
                            MAIS POPULAR
                        </div>
                    <?php endif; ?>

                    <div
                        class="h-full bg-gradient-to-br from-gray-900 to-gray-950 border <?php echo $isPopular ? 'border-primary/50 ring-2 ring-primary/20' : 'border-white/10'; ?> rounded-3xl p-8 flex flex-col hover:border-primary/30 transition-all duration-300 <?php echo $isPopular ? 'scale-105' : ''; ?>">

                        <!-- Plan Header with Icons -->
                        <div class="text-center mb-8">
                            <div class="flex items-center justify-center gap-3 mb-2">
                                <?php if ($plan['name'] === 'Iniciante'): ?>
                                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                <?php elseif ($plan['name'] === 'Profissional'): ?>
                                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                <?php elseif ($plan['name'] === 'Business'): ?>
                                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                <?php endif; ?>
                                <h3 class="text-2xl font-black"><?php echo htmlspecialchars($plan['name']); ?></h3>
                            </div>
                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($description); ?></p>

                            <div class="mt-6">
                                <div class="flex items-baseline justify-center gap-1 price-container">
                                    <span class="text-lg text-gray-400">R$</span>
                                    <span
                                        class="text-5xl font-black text-transparent bg-clip-text bg-gradient-to-r from-primary to-neon price-value transition-all duration-300"
                                        data-monthly="<?php echo $plan['price']; ?>"
                                        data-yearly="<?php echo round($plan['price'] * 0.8); ?>">
                                        <?php echo number_format($plan['price'], 0, ',', '.'); ?>
                                    </span>
                                    <span class="text-gray-400 price-period">/mês</span>
                                </div>
                                <div class="text-center mt-2 yearly-savings hidden">
                                    <span class="text-xs text-primary font-medium">Economia de R$
                                        <?php echo number_format($plan['price'] * 12 * 0.2, 0, ',', '.'); ?>/ano</span>
                                </div>
                            </div>
                        </div>

                        <!-- Features -->
                        <ul class="space-y-4 flex-1 mb-8">
                            <?php foreach ($features as $feature): ?>
                                <li class="flex items-start gap-3">
                                    <svg class="w-5 h-5 text-primary mt-0.5 flex-shrink-0" fill="currentColor"
                                        viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    <span class="text-gray-300"><?php echo htmlspecialchars($feature); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <!-- CTA -->
                        <button onclick="openRegisterModal()"
                            class="w-full px-6 py-4 <?php echo $isPopular ? 'bg-gradient-to-r from-primary to-neon text-white hover:shadow-[0_0_30px_rgba(16,185,129,0.5)]' : 'bg-white/5 border border-white/10 text-white hover:bg-white/10'; ?> font-bold rounded-xl transition-all duration-300">
                            Começar Agora
                        </button>
                    </div>
                </div>

                <?php
                $delay += 100;
            endforeach;
            ?>
        </div>

        <!-- Enterprise CTA -->
        <div class="text-center mt-16" data-aos="fade-up">
            <p class="text-gray-400 mb-4">Precisa de mais? Temos planos Enterprise personalizados.</p>
            <a href="https://wa.me/554330257412?text=Ol%C3%A1!%20Gostaria%20de%20saber%20mais%20sobre%20o%20plano%20Enterprise."
                target="_blank" class="inline-flex items-center gap-2 text-primary hover:text-neon transition-colors">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                    <path
                        d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                </svg>
                <span class="font-semibold">Fale com nossa equipe</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 8l4 4m0 0l-4 4m4-4H3" />
                </svg>
            </a>
        </div>
    </div>
</section>