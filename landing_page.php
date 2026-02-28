<?php
require_once __DIR__ . '/includes/init.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="WATS - Plataforma de Atendimento Multicanal. Centralize WhatsApp, Email, Telegram e Microsoft Teams. Automação inteligente, relatórios completos e integração com CRM. Teste grátis por 15 dias!">
    <meta name="keywords" content="atendimento multicanal, whatsapp business, chatbot, automação de atendimento, crm, help desk, microsoft teams, telegram, omnichannel, wats, saas">
    <meta name="author" content="MACIP Tecnologia LTDA">
    <meta name="theme-color" content="#10B981">
    <meta name="robots" content="index, follow">
    <title>WATS - Atendimento Multicanal</title>

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://wats.macip.com.br/">
    <meta property="og:title" content="WATS MACIP - Atendimento Multicanal que Converte">
    <meta property="og:description"
        content="Centralize WhatsApp, Email, Telegram e mais em uma única plataforma. Automação inteligente que economiza tempo e aumenta vendas.">
    <meta property="og:image" content="/assets/images/og-image.png">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="WATS MACIP">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="WATS MACIP - Atendimento Multicanal que Converte">
    <meta name="twitter:description" content="Centralize WhatsApp, Email, Telegram e mais em uma única plataforma.">
    <meta name="twitter:image" content="/assets/images/og-image.png">

    <!-- Preconnect to external resources -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">

    <!-- Canonical URL -->
    <link rel="canonical" href="https://wats.macip.com.br/">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/assets/images/whatsapp-automation.png">
    <link rel="apple-touch-icon" href="/assets/images/whatsapp-automation.png">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Alpine JS -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#10B981',
                        'dark': '#0F172A',
                        'neon': '#34D399'
                    },
                    fontFamily: {
                        'daytona': ['Daytona Pro', 'Inter', 'sans-serif'],
                        'tech': ['Daytona Pro', 'Inter', 'sans-serif']
                    }
                }
            }
        }
    </script>

    <!-- Local Fonts - Daytona Pro -->
    <style>
        @font-face {
            font-family: 'Daytona Pro';
            src: url('/fonts/DaytonaPro-Regular.ttf') format('truetype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: 'Daytona Pro';
            src: url('/fonts/DaytonaPro-Bold.ttf') format('truetype');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: 'Daytona Pro';
            src: url('/fonts/DaytonaPro-Semibold.ttf') format('truetype');
            font-weight: 600;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: 'Daytona Pro';
            src: url('/fonts/DaytonaPro-Fat.ttf') format('truetype');
            font-weight: 800;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: 'Daytona Pro';
            src: url('/fonts/DaytonaPro-Fat.ttf') format('truetype');
            font-weight: 900;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: 'Daytona Pro';
            src: url('/fonts/DaytonaPro-Light.ttf') format('truetype');
            font-weight: 300;
            font-style: normal;
            font-display: swap;
        }

        *,
        *::before,
        *::after {
            font-family: 'Daytona Pro', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif !important;
        }

        body,
        html {
            font-family: 'Daytona Pro', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif !important;
        }
    </style>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- AOS Animation Library -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css">

    <!-- Google Fonts - Fallback -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/landing.css">
    
    <!-- Modal Redesign CSS -->
    <link rel="stylesheet" href="assets/css/modal-redesign.css?v=<?php echo time(); ?>">
    
    <!-- Resource Cards Redesign CSS -->
    <link rel="stylesheet" href="assets/css/resource-cards.css?v=<?php echo time(); ?>">
    
    <!-- Benefits Cards Redesign CSS -->
    <link rel="stylesheet" href="assets/css/benefits-cards.css?v=<?php echo time(); ?>">
    
    <!-- Floating Icons CSS -->
    <link rel="stylesheet" href="assets/css/floating-icons.css?v=<?php echo time(); ?>">>

    <!-- Login Modal Script (must load before body) -->
    <script>
        // Abrir modal de login
        window.openLoginModal = function () {
            const modal = document.getElementById('loginModal');
            if (!modal) return;
            modal.classList.remove('hidden');
            const emailInput = document.getElementById('modal_email');
            if (emailInput) {
                emailInput.focus();
            }
        }

        // Fechar modal de login
        window.closeLoginModal = function (event) {
            const modal = document.getElementById('loginModal');
            if (!modal) return;
            if (!event || event.target.id === 'loginModal') {
                modal.classList.add('hidden');
                window.resetLoginModal();
            }
        }

        // Resetar modal
        window.resetLoginModal = function () {
            const loginForm = document.getElementById('loginForm');
            const twoFactorForm = document.getElementById('twoFactorForm');
            const alertBox = document.getElementById('loginAlert');
            if (loginForm) {
                loginForm.reset();
                loginForm.classList.remove('hidden');
            }
            if (twoFactorForm) {
                twoFactorForm.reset();
                twoFactorForm.classList.add('hidden');
            }
            if (alertBox) {
                alertBox.classList.add('hidden');
            }
        }

        // Voltar para login
        window.backToLogin = function () {
            const loginForm = document.getElementById('loginForm');
            const twoFactorForm = document.getElementById('twoFactorForm');
            const alertBox = document.getElementById('loginAlert');
            if (loginForm) loginForm.classList.remove('hidden');
            if (twoFactorForm) twoFactorForm.classList.add('hidden');
            if (alertBox) alertBox.classList.add('hidden');
        }

        // Mostrar alerta
        window.showAlert = function (message, type = 'error') {
            const alertDiv = document.getElementById('loginAlert');
            if (!alertDiv) return;
            alertDiv.classList.remove('hidden', 'bg-red-100', 'border-red-400', 'text-red-700', 'bg-green-100', 'border-green-400', 'text-green-700', 'bg-blue-100', 'border-blue-400', 'text-blue-700');

            if (type === 'error') {
                alertDiv.classList.add('bg-red-100', 'border', 'border-red-400', 'text-red-700');
                alertDiv.innerHTML = '<i class="fas fa-exclamation-circle mr-2"></i>' + message;
            } else if (type === 'success') {
                alertDiv.classList.add('bg-green-100', 'border', 'border-green-400', 'text-green-700');
                alertDiv.innerHTML = '<i class="fas fa-check-circle mr-2"></i>' + message;
            } else if (type === 'info') {
                alertDiv.classList.add('bg-blue-100', 'border', 'border-blue-400', 'text-blue-700');
                alertDiv.innerHTML = '<i class="fas fa-info-circle mr-2"></i>' + message;
            }
        }

        // Abrir modal "Esqueci minha senha" a partir do login
        window.openForgotPasswordFromLogin = function () {
            window.closeLoginModal();
            setTimeout(() => {
                window.openForgotPasswordModal();
            }, 200);
        }

        window.openForgotPasswordModal = function () {
            const modal = document.getElementById('forgotPasswordModal');
            if (!modal) return;
            modal.classList.remove('hidden');
            const emailInput = document.getElementById('forgot_email');
            if (emailInput) emailInput.focus();
        }

        window.closeForgotPasswordModal = function (event) {
            const modal = document.getElementById('forgotPasswordModal');
            if (!modal) return;
            
            // Use Modal Manager if available
            if (window.modalManager) {
                window.modalManager.closeModal('forgotPasswordModal');
            } else {
                // Fallback
                modal.classList.add('hidden');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }
            
            const form = document.getElementById('forgotPasswordForm');
            const messageDiv = document.getElementById('forgotPasswordMessage');
            if (form) form.reset();
            if (messageDiv) messageDiv.classList.add('hidden');
        }
        
        window.backToLoginFromForgotPassword = function () {
            const forgotModal = document.getElementById('forgotPasswordModal');
            
            // Force close forgot password modal immediately
            if (forgotModal) {
                forgotModal.classList.add('hidden');
                forgotModal.setAttribute('aria-hidden', 'true');
                
                // Reset form
                const form = document.getElementById('forgotPasswordForm');
                const messageDiv = document.getElementById('forgotPasswordMessage');
                if (form) form.reset();
                if (messageDiv) messageDiv.classList.add('hidden');
            }
            
            // Restore body scroll
            document.body.style.overflow = '';
            
            // Open login modal after a short delay
            setTimeout(() => {
                window.openLoginModal();
            }, 100);
        }

        window.submitForgotPassword = async function (event) {
            event.preventDefault();

            const form = event.target;
            const email = form.email.value;
            const btn = document.getElementById('forgotPasswordBtn');
            const messageDiv = document.getElementById('forgotPasswordMessage');

            if (!btn || !messageDiv) return;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Enviando...';

            try {
                const response = await fetch('api/forgot_password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ email })
                });

                const data = await response.json();

                messageDiv.classList.remove('hidden');

                if (data.success) {
                    messageDiv.className = 'bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4';
                    messageDiv.innerHTML = '<i class="fas fa-check-circle mr-2"></i>' + data.message;
                    form.reset();

                    setTimeout(() => {
                        window.closeForgotPasswordModal();
                    }, 3000);
                } else {
                    messageDiv.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4';
                    messageDiv.innerHTML = '<i class="fas fa-exclamation-circle mr-2"></i>' + data.message;
                }
            } catch (error) {
                messageDiv.classList.remove('hidden');
                messageDiv.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4';
                messageDiv.innerHTML = '<i class="fas fa-exclamation-circle mr-2"></i>Erro ao enviar email. Tente novamente.';
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Enviar Link';
            }
        }
    </script>

    <!-- Schema.org Structured Data -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "SoftwareApplication",
      "name": "WATS - Atendimento Multicanal",
      "applicationCategory": "BusinessApplication",
      "operatingSystem": "Web",
      "offers": {
        "@type": "Offer",
        "price": "0",
        "priceCurrency": "BRL",
        "priceValidUntil": "2026-12-31",
        "availability": "https://schema.org/InStock",
        "description": "Teste grátis por 15 dias"
      },
      "aggregateRating": {
        "@type": "AggregateRating",
        "ratingValue": "4.8",
        "ratingCount": "150"
      },
      "provider": {
        "@type": "Organization",
        "name": "MACIP Tecnologia LTDA",
        "url": "https://wats.macip.com.br"
      },
      "description": "Plataforma de atendimento multicanal que centraliza WhatsApp, Email, Telegram e Microsoft Teams em uma única interface."
    }
    </script>
</head>

<body class="bg-gray-900 text-white">

    <!-- Skip Link for Accessibility -->
    <a href="#recursos"
        class="skip-link sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[100] focus:bg-primary focus:text-white focus:px-4 focus:py-2 focus:rounded-lg focus:font-bold">
        Pular para o conteúdo principal
    </a>

    <!-- Scroll Progress Bar -->
    <div class="fixed top-0 left-0 w-full h-1 bg-gray-800 z-[60]">
        <div id="scrollProgress" class="h-full bg-gradient-to-r from-primary to-neon transition-all duration-300"
            style="width: 0%"></div>
    </div>

    <!-- Navigation - Modern Sticky -->
    <nav id="mainNav" class="fixed w-full top-0 z-50 transition-all duration-300">
        <div class="absolute inset-0 bg-black/80 backdrop-blur-xl border-b border-white/10"></div>

        <div class="container mx-auto px-4 sm:px-6 lg:px-8 relative">
            <div class="flex items-center justify-between h-20">
                <!-- Logo -->
                <div class="flex items-center">
                    <a href="/wats/landing_page.php">
                        <img src="/assets/images/logo-landing.png" alt="WATS MACIP" class="h-12 md:h-16">
                    </a>
                </div>

                <!-- Desktop Menu -->
                <div class="hidden lg:flex items-center gap-8">
                    <a href="produto.php"
                        class="text-gray-300 hover:text-white transition-colors duration-200 font-medium flex items-center gap-2 text-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Produto
                    </a>
                    <a href="#recursos"
                        class="text-gray-300 hover:text-white transition-colors duration-200 font-medium flex items-center gap-2 text-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        Recursos
                    </a>
                    <a href="#planos"
                        class="text-gray-300 hover:text-white transition-colors duration-200 font-medium flex items-center gap-2 text-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Planos
                    </a>
                    <a href="#faq"
                        class="text-gray-300 hover:text-white transition-colors duration-200 font-medium flex items-center gap-2 text-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        FAQ
                    </a>
                </div>

                <!-- CTAs - Redesigned -->
                <div class="flex items-center gap-3">
                    <button type="button" onclick="openLoginModal()"
                        class="hidden md:flex items-center gap-2 px-5 py-2.5 text-gray-300 hover:text-white border border-white/20 rounded-full hover:border-white/40 hover:bg-white/5 transition-all duration-300 font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                        </svg>
                        Entrar
                    </button>
                    <button type="button" onclick="openRegisterModal()"
                        class="group flex items-center gap-2 px-3 py-2.5 bg-gradient-to-r from-primary to-neon text-white font-semibold rounded-full hover:shadow-[0_0_25px_rgba(16,185,129,0.5)] transition-all duration-300 hover:scale-105 overflow-hidden hover:px-5">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        <span class="max-w-0 group-hover:max-w-xs overflow-hidden whitespace-nowrap transition-all duration-300 ease-in-out">Começar Grátis</span>
                    </button>

                    <!-- Mobile Menu Button -->
                    <button class="lg:hidden text-white p-2" onclick="toggleMobileMenu()" id="mobileMenuBtn">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path id="menuIcon" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16" />
                            <path id="closeIcon" class="hidden" stroke-linecap="round" stroke-linejoin="round"
                                stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Mobile Menu -->
            <div id="mobileMenu" class="hidden lg:hidden pb-6">
                <div class="flex flex-col gap-4">
                    <a href="produto.php" onclick="toggleMobileMenu()"
                        class="text-gray-300 hover:text-white transition-colors duration-200 font-medium py-2">Produto</a>
                    <a href="#recursos" onclick="toggleMobileMenu()"
                        class="text-gray-300 hover:text-white transition-colors duration-200 font-medium py-2">Recursos</a>
                    <a href="#planos" onclick="toggleMobileMenu()"
                        class="text-gray-300 hover:text-white transition-colors duration-200 font-medium py-2">Planos</a>
                    <a href="#faq" onclick="toggleMobileMenu()"
                        class="text-gray-300 hover:text-white transition-colors duration-200 font-medium py-2">FAQ</a>
                    <div class="h-px bg-white/10 my-2"></div>
                    <button type="button" onclick="openLoginModal()"
                        class="text-gray-300 hover:text-white transition-colors duration-200 font-medium py-2 text-left">Entrar</button>
                    <button type="button" onclick="openRegisterModal()"
                        class="px-6 py-3 bg-gradient-to-r from-primary to-neon text-white font-semibold rounded-xl hover:shadow-[0_0_20px_rgba(16,185,129,0.4)] transition-all duration-300">
                        Começar Grátis
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <script>
        // Scroll Progress
        window.addEventListener('scroll', () => {
            const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
            const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrolled = (winScroll / height) * 100;
            document.getElementById('scrollProgress').style.width = scrolled + '%';
        });

        // Mobile Menu Toggle
        function toggleMobileMenu() {
            const menu = document.getElementById('mobileMenu');
            const menuIcon = document.getElementById('menuIcon');
            const closeIcon = document.getElementById('closeIcon');

            menu.classList.toggle('hidden');
            menuIcon.classList.toggle('hidden');
            closeIcon.classList.toggle('hidden');
        }
    </script>

    <?php
    // Include all landing page sections
    include 'includes/landing/hero.php';
    include 'includes/landing/benefits.php';
    include 'includes/landing/how_it_works.php';
    // include 'includes/landing/video_demo.php'; // Removido conforme solicitado
    include 'includes/landing/resources.php';
    include 'includes/landing/comparison.php';
    // include 'includes/landing/testimonials.php';
    include 'includes/landing/pricing.php';
    include 'includes/landing/faq.php';
    include 'includes/landing/footer.php';
    ?>

    <!-- Login Modal -->
    <div id="loginModal" class="hidden fixed inset-0 z-[100] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div class="modal-backdrop modal-backdrop--blur" onclick="closeLoginModal(event)">
            <div class="modal-container" onclick="event.stopPropagation()">
                
                <!-- Modal Header -->
                <div class="modal-container__header">
                    <h2 class="modal-container__title" id="modal-title">Bem-vindo de Volta</h2>
                    <button type="button" onclick="closeLoginModal()" class="modal-container__close" aria-label="Fechar modal">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="modal-container__body">
                    <p style="text-align: center; color: var(--modal-text-muted); font-size: var(--modal-text-label); margin-bottom: var(--modal-section-gap);">Acesse sua conta para continuar</p>

                    <!-- Alerts -->
                    <div id="loginAlert" class="hidden mb-4 p-4 rounded-md text-sm" role="alert" aria-live="polite"></div>

                    <!-- Login Form -->
                    <form id="loginForm">
                        <div class="form-group">
                            <label for="modal_email" class="form-group__label">Email</label>
                            <input 
                                type="email" 
                                name="email" 
                                id="modal_email" 
                                class="form-group__input" 
                                placeholder="seu@email.com" 
                                required 
                                autocomplete="email"
                                aria-required="true"
                                aria-describedby="email-error">
                            <span id="email-error" class="form-group__error form-group__error--hidden" role="alert"></span>
                        </div>

                        <div class="form-group">
                            <label for="modal_password" class="form-group__label">Senha</label>
                            <input 
                                type="password" 
                                name="password" 
                                id="modal_password" 
                                class="form-group__input" 
                                placeholder="******" 
                                required 
                                autocomplete="current-password"
                                aria-required="true"
                                aria-describedby="password-error">
                            <span id="password-error" class="form-group__error form-group__error--hidden" role="alert"></span>
                        </div>

                        <!-- Form Options: Remember Me & Forgot Password -->
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--modal-form-group-gap);">
                            <label class="form-group__checkbox">
                                <input type="checkbox" name="remember" id="modal_remember">
                                <span class="form-group__checkbox-label">Lembrar-me</span>
                            </label>
                            <a href="#" onclick="window.openForgotPasswordFromLogin(); return false;" class="btn--link" style="font-size: var(--modal-text-label); text-decoration: none;">
                                Esqueci minha senha
                            </a>
                        </div>

                        <button type="submit" id="loginBtn" class="btn btn--primary btn--full-width" aria-busy="false">
                            <span class="btn__text">Entrar</span>
                        </button>
                    </form>

                    <!-- Modal Footer -->
                    <div class="modal-container__footer" style="text-align: center;">
                        <p style="font-size: var(--modal-text-label); color: var(--modal-text-muted); margin: 0;">
                            Não tem uma conta? 
                            <a href="#" onclick="openRegisterModal(); closeLoginModal(); return false;" class="btn--link" style="font-size: var(--modal-text-label); font-weight: var(--modal-weight-semibold); text-decoration: none;">
                                Cadastre-se
                            </a>
                        </p>
                    </div>
                </div>
                    
                <!-- 2FA Form -->
                <div id="twoFactorForm" class="hidden modal-container__body">
                    <!-- Back Button -->
                    <button type="button" onclick="window.backToLogin()" class="btn btn--ghost" style="margin-bottom: var(--modal-section-gap); padding-left: 0;">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 20px; height: 20px; margin-right: 8px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        <span>Voltar</span>
                    </button>

                    <!-- 2FA Header -->
                    <div style="text-align: center; margin-bottom: var(--modal-section-gap);">
                        <h3 style="font-size: var(--modal-text-subheading); font-weight: var(--modal-weight-semibold); color: var(--modal-text-primary); margin-bottom: 8px;">
                            Verificação em Duas Etapas
                        </h3>
                        <p style="font-size: var(--modal-text-label); color: var(--modal-text-muted);">
                            Digite o código de 6 dígitos do seu aplicativo autenticador
                        </p>
                    </div>

                    <!-- Alert -->
                    <div id="twoFactorAlert" class="hidden mb-4 p-4 rounded-md text-sm" role="alert" aria-live="polite"></div>

                    <!-- 2FA Form -->
                    <form id="twoFactorFormElement">
                        <!-- Authenticator Code -->
                        <div class="form-group">
                            <label for="modal_code" class="form-group__label">Código do Authenticator</label>
                            <input 
                                type="text" 
                                id="modal_code" 
                                name="code" 
                                class="form-group__input" 
                                placeholder="000000" 
                                maxlength="6" 
                                inputmode="numeric"
                                style="text-align: center; letter-spacing: var(--modal-tracking-code); font-size: var(--modal-text-code); font-family: monospace;"
                                aria-required="true"
                                aria-describedby="code-error">
                            <span id="code-error" class="form-group__error form-group__error--hidden" role="alert"></span>
                        </div>

                        <!-- Divider -->
                        <div style="text-align: center; margin: var(--modal-form-group-gap) 0; color: var(--modal-text-muted); font-size: var(--modal-text-small);">
                            - OU -
                        </div>

                        <!-- Backup Code -->
                        <div class="form-group">
                            <label for="modal_backup_code" class="form-group__label">Código de Backup</label>
                            <input 
                                type="text" 
                                id="modal_backup_code" 
                                name="backup_code" 
                                class="form-group__input" 
                                placeholder="0000-0000"
                                style="text-align: center; font-family: monospace;"
                                aria-describedby="backup-code-error">
                            <span id="backup-code-error" class="form-group__error form-group__error--hidden" role="alert"></span>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" id="verify2FABtn" class="btn btn--primary btn--full-width" aria-busy="false">
                            <span class="btn__text">Verificar</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Esqueci Minha Senha -->
    <div id="forgotPasswordModal" class="hidden fixed inset-0 z-[100] overflow-y-auto" aria-labelledby="forgot-modal-title" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div class="modal-backdrop modal-backdrop--blur" onclick="closeForgotPasswordModal(event)">
            <div class="modal-container" onclick="event.stopPropagation()">
                
                <!-- Form View -->
                <div id="forgotPasswordFormView">
                    <!-- Back Button -->
                    <button type="button" onclick="backToLoginFromForgotPassword()" class="btn btn--ghost" style="margin-bottom: var(--modal-section-gap); padding-left: 0;">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 20px; height: 20px; margin-right: 8px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        <span>Voltar ao Login</span>
                    </button>

                    <!-- Modal Header -->
                    <div class="modal-container__header" style="padding-bottom: 0; margin-bottom: var(--space-4);">
                        <div>
                            <h2 class="modal-container__title" id="forgot-modal-title">Recuperar Senha</h2>
                            <p style="font-size: var(--modal-text-label); color: var(--modal-text-muted); margin-top: 8px;">
                                Digite seu email para receber um link de redefinição
                            </p>
                        </div>
                    </div>

                    <!-- Modal Body -->
                    <div class="modal-container__body">
                        <!-- Alert -->
                        <div id="forgotPasswordMessage" class="hidden mb-4 p-4 rounded-md text-sm" role="alert" aria-live="polite"></div>
                        
                        <!-- Form -->
                        <form id="forgotPasswordForm">
                            <div class="form-group">
                                <label for="forgot_email" class="form-group__label">Email cadastrado</label>
                                <input 
                                    type="email" 
                                    id="forgot_email" 
                                    name="email" 
                                    class="form-group__input" 
                                    placeholder="seu@email.com" 
                                    required
                                    autocomplete="email"
                                    aria-required="true"
                                    aria-describedby="forgot-email-error">
                                <span id="forgot-email-error" class="form-group__error form-group__error--hidden" role="alert"></span>
                            </div>
                            
                            <button type="submit" id="forgotPasswordBtn" class="btn btn--primary btn--full-width" aria-busy="false">
                                <span class="btn__text">Enviar Link</span>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Success View -->
                <div id="forgotPasswordSuccessView" class="hidden" style="text-align: center; padding: var(--modal-section-gap) 0;">
                    <!-- Success Icon -->
                    <div class="success-icon" style="width: 64px; height: 64px; margin: 0 auto var(--modal-section-gap); background: var(--modal-primary-gradient); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; color: white;">
                        ✓
                    </div>
                    
                    <!-- Success Message -->
                    <h3 style="font-size: var(--modal-text-subheading); font-weight: var(--modal-weight-semibold); color: var(--modal-text-primary); margin-bottom: 12px;">
                        Verifique seu Email
                    </h3>
                    <p style="font-size: var(--modal-text-body); color: var(--modal-text-muted); margin-bottom: var(--modal-section-gap);">
                        Enviamos um link de redefinição de senha para seu email
                    </p>
                    
                    <!-- Back to Login Button -->
                    <button type="button" onclick="backToLoginFromForgotPassword()" class="btn btn--secondary">
                        Voltar ao Login
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Register Modal -->
    <?php include 'includes/landing/register_modal.php'; ?>

    <!-- AOS Library -->
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>

    <!-- Login Form Handlers -->
    <script>
        // Processar login
        document.addEventListener('DOMContentLoaded', function () {
            const loginFormEl = document.getElementById('loginForm');
            if (loginFormEl) {
                loginFormEl.addEventListener('submit', async function (e) {
                    e.preventDefault();

                    const btn = document.getElementById('loginBtn');
                    const email = document.getElementById('modal_email').value;
                    const password = document.getElementById('modal_password').value;

                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Entrando...';

                    try {
                        const response = await fetch('api/login_ajax.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                action: 'login',
                                email: email,
                                password: password
                            })
                        });

                        const data = await response.json();

                        if (data.success) {
                            if (data.require_2fa) {
                                loginFormEl.classList.add('hidden');
                                const twoFactorForm = document.getElementById('twoFactorForm');
                                if (twoFactorForm) twoFactorForm.classList.remove('hidden');
                                showAlert(data.message, 'info');
                                const codeInput = document.getElementById('modal_code');
                                if (codeInput) codeInput.focus();
                            } else {
                                showAlert(data.message, 'success');
                                setTimeout(() => {
                                    window.location.href = data.redirect;
                                }, 500);
                            }
                        } else {
                            showAlert(data.message, 'error');
                        }
                    } catch (error) {
                        showAlert('Erro ao processar login. Tente novamente.', 'error');
                    } finally {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-sign-in-alt mr-2"></i>Entrar';
                    }
                });
            }

            const twoFactorFormEl = document.getElementById('twoFactorForm');
            if (twoFactorFormEl) {
                twoFactorFormEl.addEventListener('submit', async function (e) {
                    e.preventDefault();

                    const btn = document.getElementById('verify2FABtn');
                    const code = document.getElementById('modal_code').value;
                    const backupCode = document.getElementById('modal_backup_code').value;

                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Verificando...';

                    try {
                        const response = await fetch('api/login_ajax.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                action: 'verify_2fa',
                                code: code,
                                backup_code: backupCode
                            })
                        });

                        const data = await response.json();

                        if (data.success) {
                            showAlert(data.message, 'success');
                            setTimeout(() => {
                                window.location.href = data.redirect;
                            }, 500);
                        } else {
                            showAlert(data.message, 'error');
                        }
                    } catch (error) {
                        showAlert('Erro ao verificar código. Tente novamente.', 'error');
                    } finally {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-check mr-2"></i>Verificar';
                    }
                });
            }

            // Fechar modal com ESC
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    window.closeLoginModal();
                }
            });
        });
    </script>

    <!-- Custom JS -->
    <script src="assets/js/landing.js?v=<?php echo time(); ?>"></script>

    <!-- Modal Animation System -->
    <script src="assets/js/modal-animations.js?v=<?php echo time(); ?>"></script>
    
    <!-- Modal Trigger Functions -->
    <script src="assets/js/modal-triggers.js?v=<?php echo time(); ?>"></script>
    
    <!-- Login Form Interactions -->
    <script src="assets/js/login-form-interactions.js?v=<?php echo time(); ?>"></script>
    
    <!-- 2FA Form Interactions -->
    <script src="assets/js/twofa-form-interactions.js?v=<?php echo time(); ?>"></script>
    
    <!-- Forgot Password Form Interactions -->
    <script src="assets/js/forgot-password-interactions.js?v=<?php echo time(); ?>"></script>

    <!-- Register JS -->
    <script src="assets/js/register.js?v=<?php echo time(); ?>"></script>
    
    <!-- Register Form Interactions -->
    <script src="assets/js/register-form-interactions.js?v=<?php echo time(); ?>"></script>

    <!-- Scroll to Top Button -->
    <button id="scrollToTop" 
            class="fixed bottom-8 left-8 w-12 h-12 bg-gradient-to-r from-primary to-neon text-white rounded-full shadow-lg hover:shadow-[0_0_25px_rgba(16,185,129,0.5)] transition-all duration-300 hover:scale-110 opacity-0 pointer-events-none z-50 flex items-center justify-center"
            aria-label="Voltar ao topo"
            onclick="window.scrollTo({top: 0, behavior: 'smooth'})">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
        </svg>
    </button>

    <!-- Cookie Consent Banner -->
    <div id="cookieConsent" class="hidden fixed bottom-0 left-0 right-0 z-[200] bg-gray-900/95 backdrop-blur-xl border-t border-white/10 p-4 md:p-6">
        <div class="container mx-auto max-w-6xl">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex-1">
                    <h3 class="text-white font-bold mb-2">🍪 Cookies e Privacidade</h3>
                    <p class="text-gray-300 text-sm">
                        Utilizamos cookies para melhorar sua experiência, personalizar conteúdo e analisar nosso tráfego. 
                        Ao continuar navegando, você concorda com nossa 
                        <a href="privacidade.php" class="text-primary hover:underline">Política de Privacidade</a> e 
                        <a href="lgpd.php" class="text-primary hover:underline">LGPD</a>.
                    </p>
                </div>
                <div class="flex gap-3">
                    <button onclick="rejectCookies()" class="px-6 py-2 bg-white/5 border border-white/10 text-white rounded-lg hover:bg-white/10 transition-all">
                        Rejeitar
                    </button>
                    <button onclick="acceptCookies()" class="px-6 py-2 bg-gradient-to-r from-primary to-neon text-white font-bold rounded-lg hover:shadow-[0_0_20px_rgba(16,185,129,0.5)] transition-all">
                        Aceitar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Cookie Consent Functions
        function showCookieConsent() {
            const consent = localStorage.getItem('cookieConsent');
            if (!consent) {
                document.getElementById('cookieConsent').classList.remove('hidden');
            }
        }

        function acceptCookies() {
            localStorage.setItem('cookieConsent', 'accepted');
            document.getElementById('cookieConsent').classList.add('hidden');
        }

        function rejectCookies() {
            localStorage.setItem('cookieConsent', 'rejected');
            document.getElementById('cookieConsent').classList.add('hidden');
        }

        // Show consent banner on page load
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(showCookieConsent, 1000);
        });
    </script>

    <script>
        // Show/Hide Scroll to Top Button
        window.addEventListener('scroll', () => {
            const scrollBtn = document.getElementById('scrollToTop');
            if (window.scrollY > 300) {
                scrollBtn.classList.remove('opacity-0', 'pointer-events-none');
                scrollBtn.classList.add('opacity-100');
            } else {
                scrollBtn.classList.add('opacity-0', 'pointer-events-none');
                scrollBtn.classList.remove('opacity-100');
            }
        });
    </script>

    <!-- Resource Cards Micro-Interactions -->
    <script src="assets/js/resource-cards-interactions.js?v=<?php echo time(); ?>"></script>
    
    <!-- Floating Icons Background -->
    <script src="assets/js/floating-icons.js?v=<?php echo time(); ?>"></script>
</body>

</html>