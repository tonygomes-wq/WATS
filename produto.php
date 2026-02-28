<?php
require_once __DIR__ . '/includes/init.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produto - WATS - Atendimento Multicanal</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/assets/images/whatsapp-automation.png">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
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
        
        .screenshot-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s ease;
        }
        
        .screenshot-card:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(16, 185, 129, 0.3);
            transform: translateY(-4px);
        }
        
        .screenshot-placeholder {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(52, 211, 153, 0.1));
            border: 2px dashed rgba(16, 185, 129, 0.3);
            border-radius: 12px;
            min-height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(16, 185, 129, 0.5);
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

    <main class="container mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16 max-w-7xl">
        <!-- Page Header -->
        <div class="text-center mb-16">
            <div class="inline-flex items-center gap-2 px-4 py-2 bg-primary/10 border border-primary/20 rounded-full text-primary text-sm font-semibold mb-6">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                Galeria de Imagens
            </div>
            <h1 class="text-4xl md:text-5xl font-black mb-4">
                Conheça o <span class="text-primary">WATS</span>
            </h1>
            <p class="text-gray-400 text-lg max-w-3xl mx-auto">
                Veja como nossa plataforma pode transformar o atendimento da sua empresa
            </p>
        </div>

        <!-- Screenshots Grid -->
        <div class="grid md:grid-cols-2 gap-8 mb-12">
            <!-- Screenshot 1 -->
            <div class="screenshot-card">
                <h3 class="text-xl font-bold text-white mb-4">Dashboard Principal</h3>
                <div class="screenshot-placeholder">
                    <div class="text-center">
                        <i class="fas fa-image text-6xl mb-4"></i>
                        <p class="text-lg">Imagem em breve</p>
                    </div>
                </div>
                <p class="text-gray-400 mt-4">Visão geral completa de todas as suas conversas e métricas em tempo real</p>
            </div>

            <!-- Screenshot 2 -->
            <div class="screenshot-card">
                <h3 class="text-xl font-bold text-white mb-4">Chat Multicanal</h3>
                <div class="screenshot-placeholder">
                    <div class="text-center">
                        <i class="fas fa-image text-6xl mb-4"></i>
                        <p class="text-lg">Imagem em breve</p>
                    </div>
                </div>
                <p class="text-gray-400 mt-4">Atenda WhatsApp, Email, Telegram e Teams em uma única interface</p>
            </div>

            <!-- Screenshot 3 -->
            <div class="screenshot-card">
                <h3 class="text-xl font-bold text-white mb-4">Automação de Mensagens</h3>
                <div class="screenshot-placeholder">
                    <div class="text-center">
                        <i class="fas fa-image text-6xl mb-4"></i>
                        <p class="text-lg">Imagem em breve</p>
                    </div>
                </div>
                <p class="text-gray-400 mt-4">Configure respostas automáticas e fluxos de atendimento inteligentes</p>
            </div>

            <!-- Screenshot 4 -->
            <div class="screenshot-card">
                <h3 class="text-xl font-bold text-white mb-4">Relatórios Avançados</h3>
                <div class="screenshot-placeholder">
                    <div class="text-center">
                        <i class="fas fa-image text-6xl mb-4"></i>
                        <p class="text-lg">Imagem em breve</p>
                    </div>
                </div>
                <p class="text-gray-400 mt-4">Análise detalhada de performance e métricas de atendimento</p>
            </div>

            <!-- Screenshot 5 -->
            <div class="screenshot-card">
                <h3 class="text-xl font-bold text-white mb-4">Disparo em Massa</h3>
                <div class="screenshot-placeholder">
                    <div class="text-center">
                        <i class="fas fa-image text-6xl mb-4"></i>
                        <p class="text-lg">Imagem em breve</p>
                    </div>
                </div>
                <p class="text-gray-400 mt-4">Envie campanhas personalizadas para milhares de contatos</p>
            </div>

            <!-- Screenshot 6 -->
            <div class="screenshot-card">
                <h3 class="text-xl font-bold text-white mb-4">Gestão de Equipe</h3>
                <div class="screenshot-placeholder">
                    <div class="text-center">
                        <i class="fas fa-image text-6xl mb-4"></i>
                        <p class="text-lg">Imagem em breve</p>
                    </div>
                </div>
                <p class="text-gray-400 mt-4">Gerencie atendentes, departamentos e permissões de acesso</p>
            </div>
        </div>

        <!-- CTA Section -->
        <div class="bg-gradient-to-br from-primary/10 to-neon/10 border border-primary/20 rounded-3xl p-8 md:p-12 text-center">
            <h2 class="text-3xl md:text-4xl font-black mb-4">
                Pronto para <span class="text-primary">Começar?</span>
            </h2>
            <p class="text-gray-300 text-lg mb-8 max-w-2xl mx-auto">
                Teste gratuitamente por 15 dias e veja como o WATS pode transformar o atendimento da sua empresa
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="landing_page.php#planos" class="px-8 py-4 bg-gradient-to-r from-primary to-neon text-white font-bold rounded-xl hover:shadow-[0_0_30px_rgba(16,185,129,0.5)] transition-all">
                    Ver Planos
                </a>
                <a href="https://wa.me/554330257412" target="_blank" class="px-8 py-4 bg-white/5 border border-white/10 text-white font-bold rounded-xl hover:bg-white/10 transition-all">
                    Falar com Vendas
                </a>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-black border-t border-white/10 py-8 mt-16">
        <div class="container mx-auto px-4 text-center">
            <div class="flex flex-wrap justify-center gap-6 mb-6">
                <a href="landing_page.php" class="text-gray-400 hover:text-primary transition-colors">Home</a>
                <a href="produto.php" class="text-primary hover:text-neon transition-colors">Produto</a>
                <a href="termos-de-uso.php" class="text-gray-400 hover:text-primary transition-colors">Termos de Uso</a>
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
