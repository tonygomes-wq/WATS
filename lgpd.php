<?php
require_once __DIR__ . '/includes/init.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LGPD - Lei Geral de Proteção de Dados - WATS - Atendimento Multicanal</title>
    
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
        
        .rights-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
        }
        
        .right-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .right-card:hover {
            background: rgba(16, 185, 129, 0.1);
            border-color: rgba(16, 185, 129, 0.3);
            transform: translateY(-2px);
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                Lei nº 13.709/2018
            </div>
            <h1 class="text-4xl md:text-5xl font-black mb-4">
                <span class="text-primary">LGPD</span> - Proteção de Dados
            </h1>
            <p class="text-gray-400 text-lg">Última atualização: <?php echo date('d/m/Y'); ?></p>
        </div>

        <!-- Introduction -->
        <div class="section-card bg-gradient-to-br from-primary/10 to-neon/10 border-primary/20">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 bg-primary/20 rounded-xl flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-white text-xl font-bold mb-2">Compromisso com a LGPD</h2>
                    <p class="text-gray-300 leading-relaxed">
                        O WATS está em conformidade com a Lei Geral de Proteção de Dados Pessoais (Lei nº 13.709/2018 - LGPD), garantindo transparência e segurança no tratamento de dados pessoais.
                    </p>
                </div>
            </div>
        </div>

        <!-- Controlador -->
        <div class="section-card">
            <h2 class="section-title">
                <span class="section-number">1</span>
                Controlador de Dados
            </h2>
            <p class="text-gray-400 leading-relaxed mb-4">
                MACIP Tecnologia LTDA é o controlador dos dados pessoais coletados através da plataforma WATS.
            </p>
            <div class="bg-white/5 border border-white/10 rounded-xl p-6">
                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <div class="text-xs text-gray-500 mb-1">Razão Social</div>
                        <div class="text-white font-semibold">MACIP Tecnologia LTDA</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 mb-1">Localização</div>
                        <div class="text-white font-semibold">Londrina - PR</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 mb-1">Contato</div>
                        <div class="text-white font-semibold">(43) 3025-7412</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 mb-1">Email</div>
                        <div class="text-white font-semibold">contato@macip.com.br</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Base Legal -->
        <div class="section-card">
            <h2 class="section-title">
                <span class="section-number">2</span>
                Base Legal para Tratamento
            </h2>
            <p class="text-gray-400 leading-relaxed mb-4">
                Tratamos seus dados pessoais com base em:
            </p>
            <div class="space-y-3">
                <div class="flex items-start gap-3 bg-white/5 border border-white/10 rounded-xl p-4">
                    <div class="w-8 h-8 bg-primary/20 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-white font-semibold mb-1">Execução de contrato</h3>
                        <p class="text-gray-400 text-sm">Para fornecer nossos serviços conforme contratado</p>
                    </div>
                </div>
                <div class="flex items-start gap-3 bg-white/5 border border-white/10 rounded-xl p-4">
                    <div class="w-8 h-8 bg-primary/20 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-white font-semibold mb-1">Consentimento</h3>
                        <p class="text-gray-400 text-sm">Para comunicações de marketing e newsletters</p>
                    </div>
                </div>
                <div class="flex items-start gap-3 bg-white/5 border border-white/10 rounded-xl p-4">
                    <div class="w-8 h-8 bg-primary/20 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-white font-semibold mb-1">Obrigação legal</h3>
                        <p class="text-gray-400 text-sm">Para cumprimento de obrigações legais e regulatórias</p>
                    </div>
                </div>
                <div class="flex items-start gap-3 bg-white/5 border border-white/10 rounded-xl p-4">
                    <div class="w-8 h-8 bg-primary/20 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-white font-semibold mb-1">Interesse legítimo</h3>
                        <p class="text-gray-400 text-sm">Para melhorias na plataforma e segurança</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Direitos do Titular -->
        <div class="section-card">
            <h2 class="section-title">
                <span class="section-number">3</span>
                Seus Direitos (Art. 18 LGPD)
            </h2>
            <p class="text-gray-400 leading-relaxed mb-6">
                Como titular de dados, você tem direito a:
            </p>
            <div class="rights-grid">
                <div class="right-card">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-primary/20 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </div>
                        <h3 class="text-white font-bold">Acesso</h3>
                    </div>
                    <p class="text-gray-400 text-sm">Solicitar quais dados pessoais tratamos sobre você</p>
                </div>

                <div class="right-card">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-primary/20 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </div>
                        <h3 class="text-white font-bold">Correção</h3>
                    </div>
                    <p class="text-gray-400 text-sm">Corrigir dados incompletos, inexatos ou desatualizados</p>
                </div>

                <div class="right-card">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-primary/20 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </div>
                        <h3 class="text-white font-bold">Exclusão</h3>
                    </div>
                    <p class="text-gray-400 text-sm">Solicitar a eliminação dos seus dados pessoais</p>
                </div>

                <div class="right-card">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-primary/20 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                            </svg>
                        </div>
                        <h3 class="text-white font-bold">Portabilidade</h3>
                    </div>
                    <p class="text-gray-400 text-sm">Receber seus dados em formato estruturado</p>
                </div>

                <div class="right-card">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-primary/20 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                            </svg>
                        </div>
                        <h3 class="text-white font-bold">Oposição</h3>
                    </div>
                    <p class="text-gray-400 text-sm">Opor-se ao tratamento de dados</p>
                </div>

                <div class="right-card">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-primary/20 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                            </svg>
                        </div>
                        <h3 class="text-white font-bold">Revogação</h3>
                    </div>
                    <p class="text-gray-400 text-sm">Revogar o consentimento a qualquer momento</p>
                </div>
            </div>
        </div>

        <!-- Exercer Direitos -->
        <div class="section-card bg-gradient-to-br from-primary/10 to-neon/10 border-primary/20">
            <h2 class="section-title">
                <span class="section-number">4</span>
                Como Exercer seus Direitos
            </h2>
            <p class="text-gray-400 leading-relaxed mb-6">
                Para exercer qualquer um dos seus direitos, entre em contato conosco:
            </p>
            <div class="flex flex-col sm:flex-row gap-4">
                <a href="https://wa.me/554330257412?text=Ol%C3%A1!%20Gostaria%20de%20exercer%20meus%20direitos%20de%20titular%20de%20dados%20(LGPD)." target="_blank" class="flex-1 flex items-center justify-center gap-2 px-6 py-4 bg-[#25D366] text-white font-bold rounded-xl hover:bg-[#20BA5A] transition-all">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                    </svg>
                    Solicitar via WhatsApp
                </a>
            </div>
            <div class="mt-6 bg-white/5 border border-white/10 rounded-xl p-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-primary flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <h3 class="text-white font-semibold mb-1">Prazo de Resposta</h3>
                        <p class="text-gray-400 text-sm">Suas solicitações serão respondidas em até 15 dias úteis, conforme Art. 19 da LGPD.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Artigos Específicos da LGPD -->
        <div class="section-card">
            <h2 class="section-title">
                <span class="section-number">5</span>
                Artigos Específicos da LGPD
            </h2>
            <p class="text-gray-400 leading-relaxed mb-4">
                Nossa conformidade com a LGPD baseia-se nos seguintes artigos:
            </p>
            <div class="space-y-3">
                <div class="bg-white/5 border border-white/10 rounded-xl p-4">
                    <h3 class="text-white font-semibold mb-2">Art. 6º - Princípios</h3>
                    <p class="text-gray-400 text-sm">Finalidade, adequação, necessidade, livre acesso, qualidade dos dados, transparência, segurança, prevenção, não discriminação e responsabilização</p>
                </div>
                <div class="bg-white/5 border border-white/10 rounded-xl p-4">
                    <h3 class="text-white font-semibold mb-2">Art. 7º - Bases Legais</h3>
                    <p class="text-gray-400 text-sm">Consentimento, execução de contrato, cumprimento de obrigação legal, exercício regular de direitos, proteção da vida, tutela da saúde, interesse legítimo, proteção do crédito</p>
                </div>
                <div class="bg-white/5 border border-white/10 rounded-xl p-4">
                    <h3 class="text-white font-semibold mb-2">Art. 18 - Direitos do Titular</h3>
                    <p class="text-gray-400 text-sm">Confirmação, acesso, correção, anonimização/bloqueio/eliminação, portabilidade, informação sobre compartilhamento, revogação do consentimento</p>
                </div>
                <div class="bg-white/5 border border-white/10 rounded-xl p-4">
                    <h3 class="text-white font-semibold mb-2">Art. 46 - Segurança</h3>
                    <p class="text-gray-400 text-sm">Medidas técnicas e administrativas para proteger dados de acessos não autorizados e situações acidentais ou ilícitas</p>
                </div>
            </div>
        </div>

        <!-- Prazos por Tipo de Solicitação -->
        <div class="section-card">
            <h2 class="section-title">
                <span class="section-number">6</span>
                Prazos por Tipo de Solicitação
            </h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div class="bg-white/5 border border-white/10 rounded-xl p-4">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-primary/20 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-white font-semibold">Acesso aos Dados</h3>
                            <p class="text-primary text-sm font-semibold">Até 15 dias úteis</p>
                        </div>
                    </div>
                    <p class="text-gray-400 text-sm">Fornecimento de cópia dos seus dados pessoais</p>
                </div>

                <div class="bg-white/5 border border-white/10 rounded-xl p-4">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-primary/20 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-white font-semibold">Correção</h3>
                            <p class="text-primary text-sm font-semibold">Até 5 dias úteis</p>
                        </div>
                    </div>
                    <p class="text-gray-400 text-sm">Atualização de dados incorretos ou desatualizados</p>
                </div>

                <div class="bg-white/5 border border-white/10 rounded-xl p-4">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-primary/20 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-white font-semibold">Exclusão</h3>
                            <p class="text-primary text-sm font-semibold">Até 30 dias úteis</p>
                        </div>
                    </div>
                    <p class="text-gray-400 text-sm">Remoção completa dos dados pessoais</p>
                </div>

                <div class="bg-white/5 border border-white/10 rounded-xl p-4">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-primary/20 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-white font-semibold">Portabilidade</h3>
                            <p class="text-primary text-sm font-semibold">Até 15 dias úteis</p>
                        </div>
                    </div>
                    <p class="text-gray-400 text-sm">Exportação dos dados em formato estruturado</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-black border-t border-white/10 py-8 mt-16">
        <div class="container mx-auto px-4 text-center">
            <div class="flex flex-wrap justify-center gap-6 mb-6">
                <a href="termos-de-uso.php" class="text-gray-400 hover:text-primary transition-colors">Termos de Uso</a>
                <a href="privacidade.php" class="text-gray-400 hover:text-primary transition-colors">Privacidade</a>
                <a href="lgpd.php" class="text-primary hover:text-neon transition-colors">LGPD</a>
            </div>
            <p class="text-gray-500 text-sm">
                &copy; <?php echo date('Y'); ?> WATS - MACIP Tecnologia LTDA. Todos os direitos reservados.
            </p>
        </div>
    </footer>
</body>

</html>
