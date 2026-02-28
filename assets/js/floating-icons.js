/**
 * Floating Icons Background Effect
 * Gera ícones flutuantes animados no fundo da landing page
 */

(function() {
    'use strict';

    // Configuração dos ícones
    const icons = [
        // WhatsApp
        { icon: '💬', class: 'whatsapp', weight: 3 },
        { icon: '📱', class: 'whatsapp', weight: 2 },
        { icon: '✉️', class: 'email', weight: 2 },
        { icon: '📧', class: 'email', weight: 2 },
        { icon: '💌', class: 'email', weight: 1 },
        { icon: '📨', class: 'email', weight: 1 },
        { icon: '✨', class: 'emoji', weight: 2 },
        { icon: '⭐', class: 'emoji', weight: 2 },
        { icon: '💡', class: 'emoji', weight: 1 },
        { icon: '🚀', class: 'emoji', weight: 2 },
        { icon: '💼', class: 'chat', weight: 1 },
        { icon: '👥', class: 'chat', weight: 1 },
        { icon: '🎯', class: 'emoji', weight: 1 },
        { icon: '📊', class: 'chat', weight: 1 },
        { icon: '💻', class: 'teams', weight: 1 },
        { icon: '🔔', class: 'chat', weight: 1 },
        { icon: '👍', class: 'emoji', weight: 1 },
        { icon: '❤️', class: 'emoji', weight: 1 },
        { icon: '🎉', class: 'emoji', weight: 1 },
        { icon: '⚡', class: 'emoji', weight: 2 }
    ];

    // Configuração de zonas
    const zones = {
        hero: { top: 0, bottom: 30, count: 20 }, // 30% da página (hero)
        middle: { top: 30, bottom: 70, count: 15 }, // 40% da página
        bottom: { top: 70, bottom: 85, count: 10 } // 15% da página (antes do footer - parar em 85%)
    };

    // Função para criar um ícone flutuante
    function createFloatingIcon(zone, zoneConfig) {
        const iconData = icons[Math.floor(Math.random() * icons.length)];
        const icon = document.createElement('div');
        icon.className = 'floating-icon';
        icon.textContent = iconData.icon;
        icon.setAttribute('aria-hidden', 'true');

        // Adicionar classe de cor
        icon.classList.add(iconData.class);

        // Adicionar classe de zona (hero tem mais opacidade)
        if (zone === 'hero') {
            icon.classList.add('hero-zone');
        }

        // Tamanho aleatório
        const sizes = ['small', 'medium-size', 'large'];
        const sizeWeights = [5, 3, 2]; // Mais ícones pequenos
        const size = weightedRandom(sizes, sizeWeights);
        icon.classList.add(size);

        // Velocidade aleatória
        const speeds = ['slow', 'medium', 'fast'];
        const speed = speeds[Math.floor(Math.random() * speeds.length)];
        icon.classList.add(speed);

        // Posição horizontal aleatória
        const left = Math.random() * 100;
        icon.style.left = `${left}%`;

        // Posição vertical inicial - COMEÇA DE BAIXO
        icon.style.bottom = '-10vh';

        // Delay aleatório para animação (espalhar os ícones)
        const delay = -(Math.random() * 30); // Delay negativo para começar em pontos diferentes
        icon.style.animationDelay = `${delay}s`;

        return icon;
    }

    // Função para escolha aleatória ponderada
    function weightedRandom(items, weights) {
        const totalWeight = weights.reduce((sum, weight) => sum + weight, 0);
        let random = Math.random() * totalWeight;
        
        for (let i = 0; i < items.length; i++) {
            random -= weights[i];
            if (random <= 0) {
                return items[i];
            }
        }
        
        return items[0];
    }

    // Função para inicializar os ícones
    function initFloatingIcons() {
        // Verificar se usuário prefere movimento reduzido
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (prefersReducedMotion) {
            console.log('Floating icons: Reduced motion detected, limiting animations');
        }

        // Criar container
        const container = document.createElement('div');
        container.className = 'floating-icons-container';
        container.setAttribute('aria-hidden', 'true');

        // Gerar ícones para cada zona
        Object.entries(zones).forEach(([zoneName, zoneConfig]) => {
            const count = prefersReducedMotion ? Math.floor(zoneConfig.count / 3) : zoneConfig.count;
            
            for (let i = 0; i < count; i++) {
                const icon = createFloatingIcon(zoneName, zoneConfig);
                container.appendChild(icon);
            }
        });

        // Adicionar ao body (antes do conteúdo)
        document.body.insertBefore(container, document.body.firstChild);

        console.log(`Floating icons initialized: ${container.children.length} icons created`);
    }

    // Inicializar quando DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFloatingIcons);
    } else {
        initFloatingIcons();
    }

    // Recriar ícones ao redimensionar (debounced)
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            const container = document.querySelector('.floating-icons-container');
            if (container) {
                container.remove();
                initFloatingIcons();
            }
        }, 500);
    });

})();
