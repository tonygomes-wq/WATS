<?php
require_once __DIR__ . '/includes/init.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Termos de Uso - WATS - Atendimento Multicanal</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/assets/images/whatsapp-automation.png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#10B981',
                        'dark': '#0F172A',
                        'neon': '#34D399'
                    }
                }
            }
        }
    </script>
    <style>
        @font-face {
            font-family: 'Daytona Pro';
            src: url('/fonts/DaytonaPro-Regular.ttf') format('truetype');
            font-weight: 400;
            font-display: swap;
        }

        @font-face {
            font-family: 'Daytona Pro';
            src: url('/fonts/DaytonaPro-Bold.ttf') format('truetype');
            font-weight: 700;
            font-display: swap;
        }

        body {
            font-family: 'Daytona Pro', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        
        .section-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 24px;
            transition: all 0.3s ease;
        }
        
        .section-card:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(16, 185, 129, 0.3);
        }
        
        .section-title {
            color: #10B981;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-number {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #10B981, #34D399);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 700;
            color: white;
        }
    </style>
</head>

<body class="bg-gray-900 text-white">
    <!-- Header -->
    <nav class="bg-black/90 backdrop-blur-xl border-b border-white/10 sticky top-0 z-50">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <a href="landing_page.php" class="flex items-center">
                    <img src="/assets/images/logo-landing.png" alt="WATS MACIP" class="h-12 md:h-14">
                </a>
                <a href="landing_page.php" class="flex items-center gap-2 text-gray-400 hover:text-primary transition-colors px-4 py-2 rounded-lg hover:bg-white/5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Voltar
                </a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16 max-w-5xl">
        <!-- Page Header -->
        <div class="text-center mb-12">
            <div class="inline-flex items-center gap-2 px-4 py-2 bg-primary/10 border border-primary/20 rounded-full text-primary text-sm font-semibold mb-6">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Documentação Legal
            </div>
            <h1 class="text-4xl md:text-5xl font-black mb-4">
                Termos de <span class="text-primary">Uso</span>
            </h1>
            <p class="text-gray-400 text-lg">Última atualização: <?php echo date('d/m/Y'); ?></p>
        </div>

        <!-- Introduction -->
        <div class="section-card">
            <p class="text-gray-300 leading-relaxed text-lg">
                Ao acessar e usar a plataforma WATS, você concorda em cumprir estes Termos de Uso e todas as leis e regulamentos aplicáveis. Se você não concordar com algum destes termos, está proibido de usar ou acessar este site.
            </p>
        </div>

        <!-- Sections -->
        <div class="section-card">
            <h2 class="section-title">
                <span class="section-number">1</span>
                Uso do Serviço
            </h2>
            <p class="text-gray-400 leading-relaxed mb-4">
                O WATS é uma plataforma de atendimento multicanal que permite o envio de mensagens via WhatsApp e outros canais de comunicação. Você concorda em:
            </p>
            <ul class="space-y-3 text-gray-400">
                <li class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-primary flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Não utilizar o serviço para envio de SPAM ou mensagens não solicitadas</span>
                </li>
                <li class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-primary flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Respeitar as políticas do WhatsApp e outros provedores</span>
                </li>
                <li class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-primary flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Não enviar conteúdo ilegal, ofensivo ou que viole direitos de terceiros</span>
                </li>
                <li class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-primary flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Manter suas credenciais de acesso seguras</span>
                </li>
            </ul>
        </div>

        <div class="section-card">
            <h2 class="section-title">
                <span class="section-number">2</span>
                Conta de Usuário
            </h2>
            <p class="text-gray-400 leading-relaxed">
                Para usar nossos serviços, você deve criar uma conta fornecendo informações precisas e completas. Você é responsável por manter a confidencialidade de sua senha e por todas as atividades que ocorram em sua conta.
            </p>
        </div>

        <div class="section-card">
            <h2 class="section-title">
                <span class="section-number">3</span>
                Assinatura e Pagamento
            </h2>
            <p class="text-gray-400 leading-relaxed">
                Alguns recursos do WATS requerem assinatura paga. Os preços e condições de pagamento estão disponíveis em nossa página de planos. O cancelamento pode ser feito a qualquer momento, sem multas ou fidelidade.
            </p>
        </div>

        <div class="section-card">
            <h2 class="section-title">
                <span class="section-number">4</span>
                Propriedade Intelectual
            </h2>
            <p class="text-gray-400 leading-relaxed">
                Todo o conteúdo da plataforma WATS, incluindo textos, gráficos, logos, ícones e software, é de propriedade da MACIP Tecnologia LTDA e está protegido por leis de direitos autorais.
            </p>
        </div>

        <div class="section-card">
            <h2 class="section-title">
                <span class="section-number">5</span>
                Limitação de Responsabilidade
            </h2>
            <p class="text-gray-400 leading-relaxed">
                O WATS não se responsabiliza por danos decorrentes do uso inadequado da plataforma, incluindo banimento de contas WhatsApp por violação das políticas do provedor.
            </p>
        </div>

        <div class="section-card">
            <h2 class="section-title">
                <span class="section-number">6</span>
                Modificações
            </h2>
            <p class="text-gray-400 leading-relaxed">
                Reservamo-nos o direito de modificar estes termos a qualquer momento. As alterações entram em vigor imediatamente após a publicação nesta página.
            </p>
        </div>

        <div class="section-card">
            <h2 class="section-title">
                <span class="section-number">7</span>
                Contato
            </h2>
            <p class="text-gray-400 leading-relaxed">
                Para dúvidas sobre estes Termos de Uso, entre em contato conosco:
            </p>
            <a href="https://wa.me/554330257412" target="_blank" class="inline-flex items-center gap-2 mt-4 px-6 py-3 bg-[#25D366] text-white font-semibold rounded-xl hover:bg-[#20BA5A] transition-all">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                </svg>
                (43) 3025-7412
            </a>
        </div>

        <div class="section-card">
            <h2 class="section-title">
                <span class="section-number">8</span>
                Base Legal
            </h2>
            <p class="text-gray-400 leading-relaxed mb-4">
                Estes Termos de Uso estão em conformidade com:
            </p>
            <ul class="space-y-3 text-gray-400">
                <li class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-primary flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <div>
                        <strong class="text-white">Lei nº 12.965/2014</strong> - Marco Civil da Internet
                        <p class="text-sm mt-1">Estabelece princípios, garantias, direitos e deveres para o uso da Internet no Brasil</p>
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-primary flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <div>
                        <strong class="text-white">Lei nº 13.709/2018</strong> - LGPD
                        <p class="text-sm mt-1">Lei Geral de Proteção de Dados Pessoais</p>
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-primary flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <div>
                        <strong class="text-white">Lei nº 8.078/1990</strong> - Código de Defesa do Consumidor
                        <p class="text-sm mt-1">Proteção dos direitos do consumidor</p>
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-primary flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <div>
                        <strong class="text-white">Lei nº 9.609/1998</strong> - Lei do Software
                        <p class="text-sm mt-1">Proteção da propriedade intelectual de programa de computador</p>
                    </div>
                </li>
            </ul>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-black border-t border-white/10 py-8 mt-16">
        <div class="container mx-auto px-4 text-center">
            <div class="flex flex-wrap justify-center gap-6 mb-6">
                <a href="termos-de-uso.php" class="text-primary hover:text-neon transition-colors">Termos de Uso</a>
                <a href="privacidade.php" class="text-gray-400 hover:text-primary transition-colors">Privacidade</a>
                <a href="lgpd.php" class="text-gray-400 hover:text-primary transition-colors">LGPD</a>
            </div>
            <p class="text-gray-500 text-sm">
                &copy; <?php echo date('Y'); ?> WATS - MACIP Tecnologia LTDA. Todos os direitos reservados.
            </p>
        </div>
    </footer>
</body>

</html>