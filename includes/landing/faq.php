<!-- FAQ Section - Redesigned -->
<section id="faq" class="py-24 bg-gradient-to-b from-black to-gray-900 relative overflow-hidden" itemscope
    itemtype="https://schema.org/FAQPage">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <!-- Section Header -->
        <div class="text-center mb-16" data-aos="fade-up">
            <div
                class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-primary/10 border border-primary/20 mb-4">
                <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm font-medium text-gray-300">Dúvidas?</span>
            </div>

            <h2 class="text-4xl sm:text-5xl lg:text-6xl font-black mb-6 leading-tight">
                Perguntas
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-neon">Frequentes</span>
            </h2>

            <p class="text-xl text-gray-400 max-w-2xl mx-auto">
                Tudo que você precisa saber sobre o WATS
            </p>
        </div>

        <!-- FAQ Items -->
        <div class="max-w-3xl mx-auto space-y-4">
            <!-- FAQ 1 -->
            <div class="bg-gradient-to-br from-gray-900 to-gray-950 border border-white/10 rounded-2xl overflow-hidden"
                data-aos="fade-up" data-aos-delay="100" itemscope itemprop="mainEntity"
                itemtype="https://schema.org/Question">
                <button class="faq-toggle w-full px-6 py-5 flex justify-between items-center" onclick="toggleFAQ(1)"
                    aria-expanded="false" aria-controls="faq-answer-1" id="faq-button-1">
                    <h3 class="text-lg font-bold text-left" itemprop="name">O sistema é seguro para usar no WhatsApp?
                    </h3>
                    <svg class="w-5 h-5 text-primary transform transition-transform" id="faq-icon-1" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div class="faq-content hidden px-6 pb-5" id="faq-answer-1" role="region" aria-labelledby="faq-button-1"
                    itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                    <p class="text-gray-400 leading-relaxed" itemprop="text">
                        Sim! Nosso sistema possui <span class="text-primary font-medium">tecnologia
                            anti-banimento</span> que respeita os limites do WhatsApp, usa intervalos aleatórios entre
                        mensagens e simula comportamento humano para garantir máxima segurança. Também oferecemos
                        suporte à API Oficial da Meta para empresas que precisam de conformidade total.
                    </p>
                </div>
            </div>

            <!-- FAQ 2 -->
            <div class="bg-gradient-to-br from-gray-900 to-gray-950 border border-white/10 rounded-2xl overflow-hidden"
                data-aos="fade-up" data-aos-delay="150" itemscope itemprop="mainEntity"
                itemtype="https://schema.org/Question">
                <button class="faq-toggle w-full px-6 py-5 flex justify-between items-center" onclick="toggleFAQ(2)"
                    aria-expanded="false" aria-controls="faq-answer-2" id="faq-button-2">
                    <h3 class="text-lg font-bold text-left" itemprop="name">Posso enviar para listas grandes?</h3>
                    <svg class="w-5 h-5 text-primary transform transition-transform" id="faq-icon-2" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div class="faq-content hidden px-6 pb-5" id="faq-answer-2" role="region" aria-labelledby="faq-button-2"
                    itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                    <p class="text-gray-400 leading-relaxed" itemprop="text">
                        Sim! Você pode importar listas com <span class="text-primary font-medium">milhares de
                            contatos</span> via CSV/Excel e fazer disparos em massa. O sistema gerencia automaticamente
                        a fila de envio com intervalos inteligentes para maximizar entrega e minimizar riscos.
                    </p>
                </div>
            </div>

            <!-- FAQ 3 -->
            <div class="bg-gradient-to-br from-gray-900 to-gray-950 border border-white/10 rounded-2xl overflow-hidden"
                data-aos="fade-up" data-aos-delay="200" itemscope itemprop="mainEntity"
                itemtype="https://schema.org/Question">
                <button class="faq-toggle w-full px-6 py-5 flex justify-between items-center" onclick="toggleFAQ(3)"
                    aria-expanded="false" aria-controls="faq-answer-3" id="faq-button-3">
                    <h3 class="text-lg font-bold text-left" itemprop="name">Preciso instalar algo no meu computador?
                    </h3>
                    <svg class="w-5 h-5 text-primary transform transition-transform" id="faq-icon-3" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div class="faq-content hidden px-6 pb-5" id="faq-answer-3" role="region" aria-labelledby="faq-button-3"
                    itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                    <p class="text-gray-400 leading-relaxed" itemprop="text">
                        Não! O WATS é <span class="text-primary font-medium">100% online</span> e funciona direto no
                        navegador. Basta criar sua conta, conectar seu WhatsApp via QR Code e começar a usar
                        imediatamente. Funciona em qualquer dispositivo.
                    </p>
                </div>
            </div>

            <!-- FAQ 4 -->
            <div class="bg-gradient-to-br from-gray-900 to-gray-950 border border-white/10 rounded-2xl overflow-hidden"
                data-aos="fade-up" data-aos-delay="250" itemscope itemprop="mainEntity"
                itemtype="https://schema.org/Question">
                <button class="faq-toggle w-full px-6 py-5 flex justify-between items-center" onclick="toggleFAQ(4)"
                    aria-expanded="false" aria-controls="faq-answer-4" id="faq-button-4">
                    <h3 class="text-lg font-bold text-left" itemprop="name">Qual o suporte oferecido?</h3>
                    <svg class="w-5 h-5 text-primary transform transition-transform" id="faq-icon-4" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div class="faq-content hidden px-6 pb-5" id="faq-answer-4" role="region" aria-labelledby="faq-button-4"
                    itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                    <p class="text-gray-400 leading-relaxed" itemprop="text">
                        Oferecemos suporte via email para o plano Starter. Planos Professional e Business têm <span
                            class="text-primary font-medium">suporte via WhatsApp 24/7</span> com atendimento
                        prioritário. O plano Business inclui gerente de conta dedicado.
                    </p>
                </div>
            </div>

            <!-- FAQ 5 -->
            <div class="bg-gradient-to-br from-gray-900 to-gray-950 border border-white/10 rounded-2xl overflow-hidden"
                data-aos="fade-up" data-aos-delay="300" itemscope itemprop="mainEntity"
                itemtype="https://schema.org/Question">
                <button class="faq-toggle w-full px-6 py-5 flex justify-between items-center" onclick="toggleFAQ(5)"
                    aria-expanded="false" aria-controls="faq-answer-5" id="faq-button-5">
                    <h3 class="text-lg font-bold text-left" itemprop="name">Funciona com API para integrações?</h3>
                    <svg class="w-5 h-5 text-primary transform transition-transform" id="faq-icon-5" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div class="faq-content hidden px-6 pb-5" id="faq-answer-5" role="region" aria-labelledby="faq-button-5"
                    itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                    <p class="text-gray-400 leading-relaxed" itemprop="text">
                        Sim! Planos Professional e Business incluem acesso completo à nossa <span
                            class="text-primary font-medium">API REST + Webhooks</span> para integrar o WATS com seus
                        sistemas, CRMs, ERPs, e-commerces e outras ferramentas.
                    </p>
                </div>
            </div>

            <!-- FAQ 6 -->
            <div class="bg-gradient-to-br from-gray-900 to-gray-950 border border-white/10 rounded-2xl overflow-hidden"
                data-aos="fade-up" data-aos-delay="350" itemscope itemprop="mainEntity"
                itemtype="https://schema.org/Question">
                <button class="faq-toggle w-full px-6 py-5 flex justify-between items-center" onclick="toggleFAQ(6)"
                    aria-expanded="false" aria-controls="faq-answer-6" id="faq-button-6">
                    <h3 class="text-lg font-bold text-left" itemprop="name">Posso testar antes de assinar?</h3>
                    <svg class="w-5 h-5 text-primary transform transition-transform" id="faq-icon-6" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div class="faq-content hidden px-6 pb-5" id="faq-answer-6" role="region" aria-labelledby="faq-button-6"
                    itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                    <p class="text-gray-400 leading-relaxed" itemprop="text">
                        Sim! Oferecemos <span class="text-primary font-medium">teste gratuito de 7 dias</span> em todos
                        os planos. Não é necessário cartão de crédito para começar. Experimente todas as funcionalidades
                        sem compromisso e cancele quando quiser.
                    </p>
                </div>
            </div>
        </div>

        <!-- Still have questions CTA -->
        <div class="text-center mt-12" data-aos="fade-up">
            <p class="text-gray-400 mb-4">Ainda tem dúvidas?</p>
            <a href="https://wa.me/554330257412?text=Ol%C3%A1!%20Tenho%20uma%20d%C3%BAvida%20sobre%20o%20WATS."
                target="_blank"
                class="inline-flex items-center gap-2 px-6 py-3 bg-white/5 border border-white/10 rounded-xl hover:bg-white/10 transition-colors">
                <svg class="w-5 h-5 text-[#25D366]" fill="currentColor" viewBox="0 0 24 24">
                    <path
                        d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                </svg>
                <span class="font-semibold text-white">Fale Conosco</span>
            </a>
        </div>
    </div>
</section>

<script>
    // FAQ Toggle Function - Robust Implementation with Accessibility
    window.toggleFAQ = function (id) {
        const content = document.getElementById('faq-answer-' + id);
        const icon = document.getElementById('faq-icon-' + id);
        const button = document.getElementById('faq-button-' + id);

        if (!content || !icon) {
            console.error('FAQ elements not found for id:', id);
            return;
        }

        // Close all other FAQs first
        for (let i = 1; i <= 10; i++) {
            if (i !== id) {
                const otherContent = document.getElementById('faq-answer-' + i);
                const otherIcon = document.getElementById('faq-icon-' + i);
                const otherButton = document.getElementById('faq-button-' + i);
                if (otherContent) {
                    otherContent.classList.add('hidden');
                    otherContent.style.display = 'none';
                }
                if (otherIcon) otherIcon.style.transform = 'rotate(0deg)';
                if (otherButton) otherButton.setAttribute('aria-expanded', 'false');
            }
        }

        // Toggle current FAQ
        const isHidden = content.classList.contains('hidden');

        if (isHidden) {
            content.classList.remove('hidden');
            content.style.display = 'block';
            icon.style.transform = 'rotate(180deg)';
            if (button) button.setAttribute('aria-expanded', 'true');
        } else {
            content.classList.add('hidden');
            content.style.display = 'none';
            icon.style.transform = 'rotate(0deg)';
            if (button) button.setAttribute('aria-expanded', 'false');
        }
    };

    // Auto-open first FAQ on page load
    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(function () {
            toggleFAQ(1);
        }, 500);
    });
</script>