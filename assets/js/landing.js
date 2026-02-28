// Initialize AOS and Lucide after DOM is ready
document.addEventListener('DOMContentLoaded', function () {
    // Initialize AOS
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 1000,
            once: true,
            offset: 100,
            easing: 'ease-out-cubic'
        });
    }

    // Initialize Lucide Icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // Parallax Effect for Hero
    document.addEventListener('mousemove', (e) => {
        const layers = document.querySelectorAll('.parallax-layer');
        if (layers.length === 0) return;

        const x = (window.innerWidth - e.pageX * 2) / 100;
        const y = (window.innerHeight - e.pageY * 2) / 100;

        layers.forEach(layer => {
            const speed = layer.getAttribute('data-speed') || 1;
            const xOffset = x * speed;
            const yOffset = y * speed;
            layer.style.transform = `translate(${xOffset}px, ${yOffset}px)`;
        });
    });
});

// Scroll Progress Bar
window.addEventListener('scroll', () => {
    const scrollProgress = document.getElementById('scrollProgress');
    const scrollHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
    const scrolled = (window.scrollY / scrollHeight) * 100;
    scrollProgress.style.width = scrolled + '%';
});

// Counter Animation
// Counter Animation
function animateCounter(element) {
    const target = parseFloat(element.getAttribute('data-target'));
    const duration = 2000;
    const step = target / (duration / 16);
    let current = 0;
    const isFloat = target % 1 !== 0;

    const timer = setInterval(() => {
        current += step;
        if (current >= target) {
            element.textContent = isFloat ? target.toFixed(1).replace('.', ',') : target.toLocaleString('pt-BR');
            clearInterval(timer);
        } else {
            const val = isFloat ? current.toFixed(1).replace('.', ',') : Math.floor(current).toLocaleString('pt-BR');
            element.textContent = val;
        }
    }, 16);
}

// Trigger counters when visible (wait for DOM)
document.addEventListener('DOMContentLoaded', function () {
    const counters = document.querySelectorAll('.counter');
    if (counters.length > 0) {
        const counterObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && entry.target.textContent === '0') {
                    animateCounter(entry.target);
                }
            });
        }, { threshold: 0.5 });

        counters.forEach(counter => counterObserver.observe(counter));
    }
});

// FAQ Accordion - Fixed to use 'hidden' class from Tailwind
function toggleFAQ(index) {
    const answer = document.getElementById(`faq-answer-${index}`);
    const icon = document.getElementById(`faq-icon-${index}`);

    if (!answer || !icon) {
        console.error('FAQ elements not found for index:', index);
        return;
    }

    const isHidden = answer.classList.contains('hidden');

    // Close all other FAQs first
    for (let i = 1; i <= 10; i++) {
        if (i !== index) {
            const otherAnswer = document.getElementById(`faq-answer-${i}`);
            const otherIcon = document.getElementById(`faq-icon-${i}`);
            if (otherAnswer) otherAnswer.classList.add('hidden');
            if (otherIcon) otherIcon.style.transform = 'rotate(0deg)';
        }
    }

    // Toggle clicked FAQ
    if (isHidden) {
        answer.classList.remove('hidden');
        icon.style.transform = 'rotate(180deg)';
    } else {
        answer.classList.add('hidden');
        icon.style.transform = 'rotate(0deg)';
    }
}

// Smooth Scroll (wait for DOM)
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
});

// Mobile Menu Toggle
function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    menu.classList.toggle('hidden');
}

// Billing Toggle (Monthly/Yearly)
let isYearly = false;

function toggleBilling() {
    isYearly = !isYearly;

    const toggle = document.getElementById('billing-toggle');
    const toggleDot = document.getElementById('toggle-dot');
    const monthlyLabel = document.getElementById('monthly-label');
    const yearlyLabel = document.getElementById('yearly-label');
    const priceValues = document.querySelectorAll('.price-value');
    const pricePeriods = document.querySelectorAll('.price-period');
    const yearlySavings = document.querySelectorAll('.yearly-savings');

    // Update toggle appearance
    if (isYearly) {
        toggle.classList.add('bg-primary');
        toggle.classList.remove('bg-gray-700');
        toggle.setAttribute('aria-checked', 'true');
        toggleDot.style.transform = 'translateX(32px)';
        monthlyLabel.classList.remove('text-white', 'font-semibold');
        monthlyLabel.classList.add('text-gray-400');
        yearlyLabel.classList.add('text-white', 'font-semibold');
        yearlyLabel.classList.remove('text-gray-400');
    } else {
        toggle.classList.remove('bg-primary');
        toggle.classList.add('bg-gray-700');
        toggle.setAttribute('aria-checked', 'false');
        toggleDot.style.transform = 'translateX(0)';
        monthlyLabel.classList.add('text-white', 'font-semibold');
        monthlyLabel.classList.remove('text-gray-400');
        yearlyLabel.classList.remove('text-white', 'font-semibold');
        yearlyLabel.classList.add('text-gray-400');
    }

    // Update prices with animation
    priceValues.forEach(priceEl => {
        const monthlyPrice = parseInt(priceEl.dataset.monthly);
        const yearlyPrice = parseInt(priceEl.dataset.yearly);
        const newPrice = isYearly ? yearlyPrice : monthlyPrice;

        // Animate price change
        priceEl.style.opacity = '0';
        priceEl.style.transform = 'translateY(-10px)';

        setTimeout(() => {
            priceEl.textContent = newPrice.toLocaleString('pt-BR');
            priceEl.style.opacity = '1';
            priceEl.style.transform = 'translateY(0)';
        }, 150);
    });

    // Update period text
    pricePeriods.forEach(period => {
        period.textContent = isYearly ? '/mês (anual)' : '/mês';
    });

    // Toggle yearly savings visibility
    yearlySavings.forEach(savings => {
        if (isYearly) {
            savings.classList.remove('hidden');
        } else {
            savings.classList.add('hidden');
        }
    });
}

// Initialize monthly as default on page load
document.addEventListener('DOMContentLoaded', function () {
    const monthlyLabel = document.getElementById('monthly-label');
    if (monthlyLabel) {
        monthlyLabel.classList.add('text-white', 'font-semibold');
    }
});

// =====================================================
// MODAL CONTROLLERS (LOGIN & PASSWORD)
// =====================================================

// Open Login Modal
window.openLoginModal = function() {
    const modal = document.getElementById('loginModal');
    if (!modal) return;
    
    // Close other modals if open
    if (typeof closeRegisterModal === 'function') closeRegisterModal();
    if (typeof closeForgotPasswordModal === 'function') closeForgotPasswordModal();
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden'; // Prevent scrolling
    
    // Reset form state if needed
    const alert = document.getElementById('loginAlert');
    if (alert) alert.classList.add('hidden');
}

// Close Login Modal
window.closeLoginModal = function(event) {
    const modal = document.getElementById('loginModal');
    if (!modal) return;
    
    // If event is provided (click outside), check target
    if (event && event.target !== modal && !event.target.closest('[onclick="closeLoginModal()"]')) return;
    
    modal.classList.add('hidden');
    document.body.style.overflow = '';
}

// Open Forgot Password Modal
window.openForgotPasswordFromLogin = function() {
    closeLoginModal();
    const modal = document.getElementById('forgotPasswordModal');
    if (modal) modal.classList.remove('hidden');
}

// Close Forgot Password Modal
window.closeForgotPasswordModal = function(event) {
    const modal = document.getElementById('forgotPasswordModal');
    if (!modal) return;
    
    if (event && event.target !== modal && !event.target.closest('[onclick="closeForgotPasswordModal()"]')) return;
    
    modal.classList.add('hidden');
}

// Back to Login from 2FA or Forgot Password
window.backToLogin = function() {
    if (typeof closeForgotPasswordModal === 'function') closeForgotPasswordModal();
    const twoFactorForm = document.getElementById('twoFactorForm');
    const loginForm = document.getElementById('loginForm');
    
    if (twoFactorForm) twoFactorForm.classList.add('hidden');
    if (loginForm) loginForm.classList.remove('hidden');
    
    openLoginModal();
}
