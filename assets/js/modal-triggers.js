/**
 * Modal Trigger Functions
 * Connects landing page buttons to the modal animation system
 * Provides backward compatibility with existing onclick handlers
 */

(function() {
    'use strict';

    /**
     * Open Login Modal
     */
    window.openLoginModal = function() {
        console.log('[Modal Triggers] openLoginModal called');
        
        const modal = document.getElementById('loginModal');
        if (!modal) {
            console.error('[Modal Triggers] Login modal not found');
            alert('ERRO: Modal de login não encontrado no DOM!');
            return;
        }

        console.log('[Modal Triggers] Login modal found:', modal);

        // Remove hidden class
        modal.classList.remove('hidden');
        
        // Use modal manager if available
        if (window.modalManager) {
            console.log('[Modal Triggers] Using modal manager');
            window.modalManager.openModal('loginModal');
        } else {
            console.warn('[Modal Triggers] Modal manager not available, using fallback');
            // Fallback: simple show
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            // Focus first input
            setTimeout(() => {
                const emailInput = document.getElementById('modal_email');
                if (emailInput) {
                    emailInput.focus();
                }
            }, 100);
        }
        
        console.log('[Modal Triggers] openLoginModal completed');
    };

    /**
     * Close Login Modal
     */
    window.closeLoginModal = function(event) {
        // If event is provided and it's a backdrop click, check if target is backdrop
        if (event && event.target && !event.target.classList.contains('modal-backdrop')) {
            return;
        }

        const modal = document.getElementById('loginModal');
        if (!modal) return;

        // Use modal manager if available
        if (window.modalManager) {
            window.modalManager.closeModal('loginModal');
        } else {
            // Fallback: simple hide
            modal.classList.add('hidden');
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }

        // Reset login form if manager exists
        if (window.loginFormManager) {
            window.loginFormManager.reset();
        }
    };

    /**
     * Open Register Modal
     */
    window.openRegisterModal = function() {
        console.log('[Modal Triggers] openRegisterModal called');
        
        const modal = document.getElementById('registerModal');
        if (!modal) {
            console.error('[Modal Triggers] Register modal not found');
            alert('ERRO: Modal de cadastro não encontrado no DOM!');
            return;
        }

        console.log('[Modal Triggers] Register modal found:', modal);
        console.log('[Modal Triggers] Modal classes before:', modal.className);

        // Remove hidden class
        modal.classList.remove('hidden');
        
        console.log('[Modal Triggers] Modal classes after removing hidden:', modal.className);
        
        // Use modal manager if available
        if (window.modalManager) {
            console.log('[Modal Triggers] Using modal manager');
            window.modalManager.openModal('registerModal');
        } else {
            console.warn('[Modal Triggers] Modal manager not available, using fallback');
            // Fallback: simple show
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            // Focus first input
            setTimeout(() => {
                const nameInput = document.getElementById('reg_name');
                if (nameInput) {
                    nameInput.focus();
                }
            }, 100);
        }
        
        console.log('[Modal Triggers] openRegisterModal completed');
    };

    /**
     * Close Register Modal
     */
    window.closeRegisterModal = function(event) {
        // If event is provided and it's a backdrop click, check if target is backdrop
        if (event && event.target && !event.target.classList.contains('modal-backdrop')) {
            return;
        }

        const modal = document.getElementById('registerModal');
        if (!modal) return;

        // Use modal manager if available
        if (window.modalManager) {
            window.modalManager.closeModal('registerModal');
        } else {
            // Fallback: simple hide
            modal.classList.add('hidden');
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }

        // Reset registration form if manager exists
        if (window.registrationFormManager) {
            window.registrationFormManager.reset();
        }
    };

    /**
     * Open Forgot Password Modal (from login)
     */
    window.openForgotPasswordFromLogin = function() {
        // Close login modal first
        closeLoginModal();
        
        // Open forgot password modal
        setTimeout(() => {
            const modal = document.getElementById('forgotPasswordModal');
            if (!modal) {
                console.error('Forgot password modal not found');
                return;
            }

            // Remove hidden class
            modal.classList.remove('hidden');
            
            // Use modal manager if available
            if (window.modalManager) {
                window.modalManager.openModal('forgotPasswordModal');
            } else {
                // Fallback: simple show
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
                
                // Focus email input
                setTimeout(() => {
                    const emailInput = document.getElementById('forgot_email');
                    if (emailInput) {
                        emailInput.focus();
                    }
                }, 100);
            }
        }, 300);
    };

    /**
     * Switch from Register to Login
     */
    window.switchToLogin = function() {
        closeRegisterModal();
        setTimeout(() => {
            openLoginModal();
        }, 300);
    };

    /**
     * Initialize modal triggers on page load
     */
    function initializeModalTriggers() {
        console.log('Modal triggers initialized');
        
        // Add escape key handler for all modals
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                // Close any open modal
                const openModals = document.querySelectorAll('[role="dialog"]:not(.hidden)');
                openModals.forEach(modal => {
                    if (modal.id === 'loginModal') {
                        closeLoginModal();
                    } else if (modal.id === 'registerModal') {
                        closeRegisterModal();
                    }
                });
            }
        });
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeModalTriggers);
    } else {
        initializeModalTriggers();
    }

})();
