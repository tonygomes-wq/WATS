/**
 * WATS - Visual Enhancements 2026
 * Melhorias visuais interativas e micro-interações
 * Apenas alterações visuais - sem mudanças de funcionalidade
 * 
 * IMPORTANTE: Este arquivo NÃO afeta a página de chat (chat.php)
 */

(function() {
    'use strict';

    // ============================================
    // VERIFICAÇÃO: DESABILITAR NO CHAT
    // ============================================
    // Se estamos na página de chat, não executar nenhuma melhoria
    if (document.querySelector('.chat-page-wrapper') || 
        document.querySelector('.chat-main-container') ||
        window.location.pathname.includes('chat.php')) {
        console.log('[Visual Enhancements] Desabilitado na página de chat');
        return; // Sair imediatamente
    }

    // ============================================
    // 1. PAGE ENTER ANIMATION
    // ============================================
    function initPageEnterAnimation() {
        const mainContent = document.getElementById('mainContent') || document.querySelector('.main-content');
        if (mainContent && !mainContent.classList.contains('page-enter')) {
            mainContent.classList.add('page-enter');
        }
    }

    // ============================================
    // 2. TOAST NOTIFICATION SYSTEM
    // ============================================
    window.showToast = function(message, type = 'success', duration = 3000) {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <div style="display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}" 
                   style="font-size: 20px; color: ${type === 'success' ? 'var(--accent-primary)' : type === 'error' ? '#ef4444' : '#f59e0b'};"></i>
                <span style="font-size: 14px; font-weight: 500; color: var(--text-primary);">${message}</span>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        // Auto remove
        setTimeout(() => {
            toast.style.animation = 'toastSlideOut 0.3s forwards';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    };

    // Toast slide out animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes toastSlideOut {
            to {
                opacity: 0;
                transform: translateX(100px);
            }
        }
    `;
    document.head.appendChild(style);

    // ============================================
    // 3. LOADING STATE FOR BUTTONS
    // ============================================
    window.setButtonLoading = function(button, loading = true) {
        if (loading) {
            button.classList.add('loading');
            button.disabled = true;
            button.dataset.originalText = button.textContent;
        } else {
            button.classList.remove('loading');
            button.disabled = false;
            if (button.dataset.originalText) {
                button.textContent = button.dataset.originalText;
            }
        }
    };

    // ============================================
    // 4. SMOOTH SCROLL TO TOP
    // ============================================
    function createScrollToTopButton() {
        const button = document.createElement('button');
        button.id = 'scrollToTop';
        button.innerHTML = '<i class="fas fa-arrow-up"></i>';
        button.style.cssText = `
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--accent-primary);
            color: white;
            border: none;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            cursor: pointer;
            opacity: 0;
            transform: scale(0);
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            z-index: 1000;
        `;

        button.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        document.body.appendChild(button);

        // Show/hide based on scroll
        const mainContent = document.querySelector('.main-content');
        const scrollContainer = mainContent || window;
        
        scrollContainer.addEventListener('scroll', () => {
            const scrollTop = mainContent ? mainContent.scrollTop : window.pageYOffset;
            if (scrollTop > 300) {
                button.style.opacity = '1';
                button.style.transform = 'scale(1)';
            } else {
                button.style.opacity = '0';
                button.style.transform = 'scale(0)';
            }
        });
    }

    // ============================================
    // 5. ENHANCED METRIC CARDS
    // ============================================
    function enhanceMetricCards() {
        const metricCards = document.querySelectorAll('.metric-card');
        
        metricCards.forEach((card, index) => {
            // Stagger animation
            card.style.animationDelay = `${index * 0.05}s`;
            
            // Add featured class to first card
            if (index === 0) {
                card.classList.add('metric-card-featured');
            }
        });
    }

    // ============================================
    // 6. SKELETON LOADING
    // ============================================
    window.showSkeleton = function(container) {
        const skeleton = document.createElement('div');
        skeleton.className = 'skeleton';
        skeleton.style.cssText = `
            width: 100%;
            height: 100px;
            margin-bottom: 16px;
        `;
        container.appendChild(skeleton);
        return skeleton;
    };

    window.removeSkeleton = function(skeleton) {
        if (skeleton && skeleton.parentNode) {
            skeleton.style.animation = 'fadeOut 0.3s forwards';
            setTimeout(() => skeleton.remove(), 300);
        }
    };

    // ============================================
    // 7. FORM VALIDATION VISUAL FEEDBACK
    // ============================================
    function enhanceFormValidation() {
        const inputs = document.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            input.addEventListener('invalid', (e) => {
                e.preventDefault();
                input.classList.add('error-shake');
                setTimeout(() => input.classList.remove('error-shake'), 500);
            });

            input.addEventListener('input', () => {
                if (input.validity.valid) {
                    input.classList.add('success-state');
                    setTimeout(() => input.classList.remove('success-state'), 600);
                }
            });
        });
    }

    // ============================================
    // 8. ENHANCED SIDEBAR INTERACTIONS
    // ============================================
    function enhanceSidebar() {
        const sidebarItems = document.querySelectorAll('.sidebar-item');
        
        sidebarItems.forEach(item => {
            // Add ripple effect on click
            item.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                ripple.style.cssText = `
                    position: absolute;
                    border-radius: 50%;
                    background: rgba(16, 185, 129, 0.3);
                    width: 100px;
                    height: 100px;
                    margin-top: -50px;
                    margin-left: -50px;
                    animation: ripple 0.6s;
                    pointer-events: none;
                `;
                ripple.style.left = e.clientX - this.offsetLeft + 'px';
                ripple.style.top = e.clientY - this.offsetTop + 'px';
                
                this.appendChild(ripple);
                setTimeout(() => ripple.remove(), 600);
            });
        });

        // Add ripple animation
        const rippleStyle = document.createElement('style');
        rippleStyle.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(rippleStyle);
    }

    // ============================================
    // 9. CHART ENHANCEMENTS
    // ============================================
    window.enhanceChart = function(chartInstance) {
        if (!chartInstance) return;

        // Add modern tooltip styling
        if (chartInstance.options && chartInstance.options.plugins) {
            chartInstance.options.plugins.tooltip = {
                ...chartInstance.options.plugins.tooltip,
                backgroundColor: 'rgba(15, 23, 42, 0.95)',
                padding: 16,
                borderRadius: 8,
                titleFont: { size: 13, weight: '600' },
                bodyFont: { size: 12 },
                displayColors: false,
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        if (label) {
                            label += ': ';
                        }
                        label += new Intl.NumberFormat('pt-BR').format(context.parsed.y);
                        return label;
                    }
                }
            };
            chartInstance.update();
        }
    };

    // ============================================
    // 10. INTERSECTION OBSERVER FOR ANIMATIONS
    // ============================================
    function initIntersectionObserver() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animation = 'fadeInUp 0.6s forwards';
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        // Observe metric cards
        document.querySelectorAll('.metric-card').forEach(card => {
            card.style.opacity = '0';
            observer.observe(card);
        });

        // Add animation
        const animStyle = document.createElement('style');
        animStyle.textContent = `
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(animStyle);
    }

    // ============================================
    // 11. COPY TO CLIPBOARD WITH FEEDBACK
    // ============================================
    window.copyToClipboard = function(text, button) {
        navigator.clipboard.writeText(text).then(() => {
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i> Copiado!';
            button.classList.add('success-state');
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.classList.remove('success-state');
            }, 2000);
            
            showToast('Copiado para área de transferência!', 'success', 2000);
        });
    };

    // ============================================
    // 12. ENHANCED MODAL INTERACTIONS
    // ============================================
    function enhanceModals() {
        // Close modal on backdrop click
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay') || 
                e.target.classList.contains('refined-modal-overlay')) {
                const modal = e.target.querySelector('.modal, .refined-modal');
                if (modal) {
                    modal.style.animation = 'slideDown 0.3s forwards';
                    e.target.style.animation = 'fadeOut 0.3s forwards';
                    setTimeout(() => e.target.remove(), 300);
                }
            }
        });

        // Add animations
        const modalStyle = document.createElement('style');
        modalStyle.textContent = `
            @keyframes slideDown {
                to {
                    opacity: 0;
                    transform: translateY(20px) scale(0.95);
                }
            }
            @keyframes fadeOut {
                to { opacity: 0; }
            }
        `;
        document.head.appendChild(modalStyle);
    }

    // ============================================
    // 13. PROGRESS BAR ANIMATION
    // ============================================
    window.animateProgressBar = function(element, targetValue, duration = 1000) {
        const start = parseFloat(element.style.width) || 0;
        const end = targetValue;
        const startTime = performance.now();

        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // Easing function
            const easeOutCubic = 1 - Math.pow(1 - progress, 3);
            const current = start + (end - start) * easeOutCubic;
            
            element.style.width = current + '%';
            
            if (progress < 1) {
                requestAnimationFrame(update);
            }
        }
        
        requestAnimationFrame(update);
    };

    // ============================================
    // 14. NUMBER COUNTER ANIMATION
    // ============================================
    window.animateNumber = function(element, targetValue, duration = 1000) {
        const start = parseFloat(element.textContent.replace(/[^\d.-]/g, '')) || 0;
        const end = targetValue;
        const startTime = performance.now();

        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            const easeOutCubic = 1 - Math.pow(1 - progress, 3);
            const current = start + (end - start) * easeOutCubic;
            
            element.textContent = new Intl.NumberFormat('pt-BR').format(Math.round(current));
            
            if (progress < 1) {
                requestAnimationFrame(update);
            }
        }
        
        requestAnimationFrame(update);
    };

    // ============================================
    // 15. KEYBOARD SHORTCUTS
    // ============================================
    function initKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + K for search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const searchInput = document.querySelector('input[type="search"], input[placeholder*="Pesquisar"]');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }
            
            // ESC to close modals
            if (e.key === 'Escape') {
                const modal = document.querySelector('.modal-overlay, .refined-modal-overlay');
                if (modal) {
                    modal.click();
                }
            }
        });
    }

    // ============================================
    // INITIALIZATION
    // ============================================
    function init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
            return;
        }

        console.log('🎨 Visual Enhancements 2026 - Initialized');

        // Initialize all enhancements
        initPageEnterAnimation();
        createScrollToTopButton();
        enhanceMetricCards();
        enhanceFormValidation();
        enhanceSidebar();
        initIntersectionObserver();
        enhanceModals();
        initKeyboardShortcuts();

        // Enhance existing charts if Chart.js is loaded
        if (typeof Chart !== 'undefined') {
            setTimeout(() => {
                Object.values(Chart.instances).forEach(chart => {
                    enhanceChart(chart);
                });
            }, 1000);
        }
    }

    // Start initialization
    init();

})();
