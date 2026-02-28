<!-- Hero Section - Redesigned 2026 (Smooth Gray Gradient) -->
<section class="relative overflow-hidden min-h-[70vh] flex items-center" style="background: linear-gradient(180deg, #1a1a1a 0%, #0a0a0a 50%, #000000 100%); z-index: 10;">

    <div class="container mx-auto px-4 sm:px-6 lg:px-8 relative py-16 pt-32" style="z-index: 20;">
        <!-- Main Hero Content - Centered Layout -->
        <div class="max-w-7xl mx-auto text-center">
            <!-- Headline -->
            <h1 class="text-4xl sm:text-5xl lg:text-6xl xl:text-7xl font-black mb-6 leading-[1.2] tracking-tight text-white" data-aos="fade-up" data-aos-duration="1000">
                Atendimento <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary via-neon to-primary bg-[length:200%_auto] animate-gradient">Multicanal</span>
            </h1>

            <!-- Subheadline -->
            <p
                class="text-lg sm:text-xl lg:text-2xl text-gray-400 mb-8 max-w-4xl mx-auto leading-relaxed font-light" data-aos="fade-up" data-aos-delay="100">
                Centralize sua comunicação e potencialize seus resultados.
                <strong class="text-white font-semibold">Atendimento abrangente para todos os setores da sua
                    empresa.</strong>
            </p>

            <!-- CTAs -->
            <div class="flex flex-col sm:flex-row gap-4 mb-12 justify-center" data-aos="fade-up" data-aos-delay="200">
                <button type="button" onclick="openRegisterModal()"
                    class="group relative px-8 py-4 bg-primary text-white font-bold rounded-2xl overflow-hidden transition-all duration-300 hover:shadow-[0_0_40px_rgba(16,185,129,0.4)] hover:-translate-y-1">
                    <div
                        class="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300">
                    </div>
                    <span class="relative z-10 flex items-center justify-center gap-2 text-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Começar Agora
                    </span>
                </button>

                <button type="button" onclick="window.location.href='#recursos'"
                    class="px-8 py-4 glass-panel text-white font-semibold rounded-2xl hover:bg-white/5 hover:border-white/30 transition-all duration-300 hover:-translate-y-1">
                    <span class="flex items-center justify-center gap-2 text-lg">
                        Ver Tour
                        <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </span>
                </button>
            </div>

            <!-- Social Proof Metrics -->
            <div class="grid grid-cols-3 gap-12 max-w-3xl mx-auto pt-8 border-t border-white/10 mb-12 relative" style="z-index: 30;"
                data-aos="fade-up" data-aos-delay="300">
                <div class="text-center">
                    <div class="text-3xl font-black text-white mb-1"><span class="counter"
                            data-target="10">0</span>mil+</div>
                    <div class="text-xs text-gray-400 uppercase tracking-widest font-semibold">Mensagens</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-black text-white mb-1"><span class="counter"
                            data-target="200">0</span>+</div>
                    <div class="text-xs text-gray-400 uppercase tracking-widest font-semibold">Empresas</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-black text-white mb-1"><span class="counter"
                            data-target="99.9">0</span>%</div>
                    <div class="text-xs text-gray-400 uppercase tracking-widest font-semibold">Uptime</div>
                </div>
            </div>

            <!-- Channel Logos -->
            <div class="flex flex-wrap items-center justify-center gap-12 mt-8 max-w-4xl mx-auto" data-aos="fade-up" data-aos-delay="400">
                <div class="channel-logo-item">
                    <img src="assets/images/whatsapp.png" alt="WhatsApp" loading="lazy" decoding="async" class="w-16 h-16 lg:w-20 lg:h-20 object-contain">
                </div>
                <div class="channel-logo-item">
                    <img src="assets/images/instagram.png" alt="Instagram" loading="lazy" decoding="async" class="w-16 h-16 lg:w-20 lg:h-20 object-contain">
                </div>
                <div class="channel-logo-item">
                    <img src="assets/images/telegram.png" alt="Telegram" loading="lazy" decoding="async" class="w-16 h-16 lg:w-20 lg:h-20 object-contain">
                </div>
                <div class="channel-logo-item">
                    <img src="assets/images/teams.png" alt="Microsoft Teams" loading="lazy" decoding="async" class="w-16 h-16 lg:w-20 lg:h-20 object-contain">
                </div>
            </div>
        </div>

        <!-- Scroll Indicator -->
        <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 animate-bounce">
            <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
            </svg>
        </div>
</section>

<!-- Channel Logo Styles -->
<style>
    /* Channel Logos */
    .channel-logo-item {
        transition: transform 0.3s ease, filter 0.3s ease;
        cursor: pointer;
    }

    .channel-logo-item:hover {
        transform: scale(1.15) translateY(-5px);
    }

    .channel-logo-item:hover img {
        filter: drop-shadow(0 0 20px rgba(16, 185, 129, 0.6));
    }

    /* Responsive adjustments */
    @media (max-width: 640px) {
        .channel-logo-item img {
            width: 48px !important;
            height: 48px !important;
        }
    }
</style>