/**
 * Resource Cards Micro-Interactions
 * Parallax effects and advanced interactions for resource cards
 */

(function() {
    'use strict';

    /**
     * Initialize parallax effect on cursor movement
     * Moves icon container based on cursor position relative to card center
     */
    function initParallaxEffect() {
        // Check for required APIs
        if (!('addEventListener' in window) || 
            !('getBoundingClientRect' in Element.prototype)) {
            console.warn('Parallax effects not supported in this browser');
            return;
        }

        // Check if user prefers reduced motion
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (prefersReducedMotion) {
            console.log('Parallax effects disabled due to prefers-reduced-motion');
            return;
        }

        try {
            const cards = document.querySelectorAll('.resource-card');
            
            cards.forEach(card => {
                card.addEventListener('mousemove', handleParallax);
                card.addEventListener('mouseleave', resetParallax);
            });
        } catch (error) {
            console.error('Failed to initialize parallax effects:', error);
            // Fail silently - cards still work without parallax
        }
    }

    /**
     * Handle parallax effect on mouse move
     * @param {MouseEvent} e - Mouse event
     */
    function handleParallax(e) {
        const card = e.currentTarget;
        const icon = card.querySelector('.icon-container');
        
        if (!icon) return;

        const rect = card.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        const centerX = rect.width / 2;
        const centerY = rect.height / 2;
        
        // Calculate offset as percentage from center (-1 to 1)
        const deltaX = (x - centerX) / centerX;
        const deltaY = (y - centerY) / centerY;
        
        // Apply transform with 5-10px movement range
        const moveX = deltaX * 7; // 7px max movement
        const moveY = deltaY * 7;
        
        icon.style.transform = `translate(${moveX}px, ${moveY}px) scale(1.1) rotate(3deg)`;
    }

    /**
     * Reset parallax effect on mouse leave
     * @param {MouseEvent} e - Mouse event
     */
    function resetParallax(e) {
        const card = e.currentTarget;
        const icon = card.querySelector('.icon-container');
        
        if (!icon) return;
        
        // Reset to default hover state (scale and rotate only)
        icon.style.transform = '';
    }

    /**
     * Initialize all micro-interactions
     */
    function init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initParallaxEffect);
        } else {
            initParallaxEffect();
        }
    }

    // Initialize
    init();
})();
