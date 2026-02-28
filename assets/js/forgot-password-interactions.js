/**
 * Forgot Password Form Interactions
 * Handles forgot password form interactions, validation, and transitions
 * Based on: .kiro/specs/modal-redesign/tasks.md - Task 9
 */

(function() {
    'use strict';

    /**
     * Forgot Password Form Manager Class
     * Manages forgot password form interactions and validation
     */
    class ForgotPasswordFormManager {
        constructor() {
            this.form = null;
            this.emailInput = null;
            this.submitButton = null;
            this.formView = null;
            this.successView = null;
            this.isSubmitting = false;
            
            // Bind methods
            this.handleSubmit = this.handleSubmit.bind(this);
            this.handleEmailInput = this.handleEmailInput.bind(this);
        }

        /**
         * Initialize the forgot password form manager
         */
        init() {
            // Wait for DOM to be ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.setup());
            } else {
                this.setup();
            }
        }

        /**
         * Setup form elements and event listeners
         */
        setup() {
            this.form = document.getElementById('forgotPasswordForm');
            this.emailInput = document.getElementById('forgot_email');
            this.submitButton = document.getElementById('forgotPasswordBtn');
            this.formView = document.getElementById('forgotPasswordFormView');
            this.successView = document.getElementById('forgotPasswordSuccessView');

            if (!this.form || !this.emailInput || !this.submitButton) {
                console.warn('Forgot password form elements not found');
                return;
            }

            // Add event listeners
            this.emailInput.addEventListener('input', this.handleEmailInput);
            this.emailInput.addEventListener('blur', () => this.validateEmail(true));
            
            // Form submission
            this.form.addEventListener('submit', this.handleSubmit);
        }

        /**
         * Handle email input changes
         */
        handleEmailInput() {
            // Clear error state on input
            this.clearFieldError(this.emailInput);
            
            // Real-time validation (non-intrusive)
            if (this.emailInput.value.length > 0) {
                this.validateEmail(false);
            }
        }

        /**
         * Validate email field
         * @param {boolean} showError - Whether to show error message
         * @returns {boolean} - Whether email is valid
         */
        validateEmail(showError = false) {
            const email = this.emailInput.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (!email) {
                if (showError) {
                    this.setFieldError(this.emailInput, 'Email é obrigatório');
                }
                return false;
            }
            
            if (!emailRegex.test(email)) {
                if (showError) {
                    this.setFieldError(this.emailInput, 'Digite um email válido');
                } else {
                    this.setFieldState(this.emailInput, 'default');
                }
                return false;
            }
            
            this.setFieldState(this.emailInput, 'valid');
            return true;
        }

        /**
         * Set field error state
         * @param {HTMLElement} input - Input element
         * @param {string} message - Error message
         */
        setFieldError(input, message) {
            input.classList.add('form-group__input--error');
            input.classList.remove('form-group__input--valid');
            input.setAttribute('aria-invalid', 'true');
            
            const errorId = input.getAttribute('aria-describedby');
            const errorElement = errorId ? document.getElementById(errorId) : null;
            
            if (errorElement) {
                errorElement.textContent = message;
                errorElement.classList.remove('form-group__error--hidden');
                errorElement.classList.add('error-appear');
                
                setTimeout(() => {
                    errorElement.classList.remove('error-appear');
                }, 200);
            }
        }

        /**
         * Clear field error state
         * @param {HTMLElement} input - Input element
         */
        clearFieldError(input) {
            input.classList.remove('form-group__input--error');
            input.setAttribute('aria-invalid', 'false');
            
            const errorId = input.getAttribute('aria-describedby');
            const errorElement = errorId ? document.getElementById(errorId) : null;
            
            if (errorElement) {
                errorElement.textContent = '';
                errorElement.classList.add('form-group__error--hidden');
            }
        }

        /**
         * Set field state
         * @param {HTMLElement} input - Input element
         * @param {string} state - State: 'default', 'valid', 'error'
         */
        setFieldState(input, state) {
            input.classList.remove('form-group__input--error', 'form-group__input--valid');
            
            if (state === 'valid') {
                input.classList.add('form-group__input--valid');
                input.setAttribute('aria-invalid', 'false');
            } else if (state === 'error') {
                input.classList.add('form-group__input--error');
                input.setAttribute('aria-invalid', 'true');
            } else {
                input.setAttribute('aria-invalid', 'false');
            }
        }

        /**
         * Show loading state on submit button
         */
        showLoadingState() {
            this.isSubmitting = true;
            this.submitButton.disabled = true;
            this.submitButton.classList.add('btn--loading');
            this.submitButton.setAttribute('aria-busy', 'true');
            
            const buttonText = this.submitButton.querySelector('.btn__text');
            if (buttonText) {
                buttonText.textContent = 'Enviando...';
            }
            
            if (!this.submitButton.querySelector('.btn__spinner')) {
                const spinner = document.createElement('span');
                spinner.className = 'btn__spinner';
                this.submitButton.insertBefore(spinner, this.submitButton.firstChild);
            }
        }

        /**
         * Hide loading state on submit button
         */
        hideLoadingState() {
            this.isSubmitting = false;
            this.submitButton.disabled = false;
            this.submitButton.classList.remove('btn--loading');
            this.submitButton.removeAttribute('aria-busy');
            
            const buttonText = this.submitButton.querySelector('.btn__text');
            if (buttonText) {
                buttonText.textContent = 'Enviar Link';
            }
            
            const spinner = this.submitButton.querySelector('.btn__spinner');
            if (spinner) {
                spinner.remove();
            }
        }

        /**
         * Show error message in alert
         * @param {string} message - Error message
         */
        showErrorMessage(message) {
            const alertElement = document.getElementById('forgotPasswordMessage');
            if (!alertElement) return;
            
            alertElement.className = 'mb-4 p-4 rounded-md text-sm bg-red-50 border border-red-200 text-red-800';
            alertElement.textContent = message;
            alertElement.classList.remove('hidden');
            alertElement.classList.add('error-appear');
            alertElement.setAttribute('role', 'alert');
            alertElement.setAttribute('aria-live', 'polite');
            
            setTimeout(() => {
                alertElement.classList.remove('error-appear');
            }, 200);
        }

        /**
         * Hide alert message
         */
        hideAlert() {
            const alertElement = document.getElementById('forgotPasswordMessage');
            if (alertElement) {
                alertElement.classList.add('hidden');
                alertElement.textContent = '';
            }
        }

        /**
         * Show success view with animation
         */
        showSuccessView() {
            if (!this.formView || !this.successView) return;
            
            // Transition from form to success view
            if (window.modalManager) {
                window.modalManager.transitionView(this.formView, this.successView, 'left');
            } else {
                // Fallback without animation
                this.formView.classList.add('hidden');
                this.successView.classList.remove('hidden');
            }
            
            // Animate success icon
            setTimeout(() => {
                const successIcon = this.successView.querySelector('.success-icon');
                if (successIcon) {
                    successIcon.classList.add('success-bounce');
                    
                    setTimeout(() => {
                        successIcon.classList.remove('success-bounce');
                    }, 400);
                }
            }, 250);
        }

        /**
         * Handle form submission
         * @param {Event} event - Submit event
         */
        async handleSubmit(event) {
            event.preventDefault();
            
            if (this.isSubmitting) {
                return;
            }
            
            this.hideAlert();
            
            // Validate email
            if (!this.validateEmail(true)) {
                this.emailInput.focus();
                return;
            }
            
            this.showLoadingState();
            
            const email = this.emailInput.value.trim();
            
            try {
                const response = await fetch('api/forgot_password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'forgot_password',
                        email: email
                    })
                });
                
                const data = await response.json();
                
                this.hideLoadingState();
                
                if (data.success) {
                    // Show success view
                    this.showSuccessView();
                } else {
                    // Show error message
                    this.showErrorMessage(data.message || 'Erro ao enviar email. Tente novamente.');
                }
            } catch (error) {
                console.error('Forgot password error:', error);
                this.hideLoadingState();
                this.showErrorMessage('Erro de conexão. Verifique sua internet e tente novamente.');
            }
        }

        /**
         * Reset form to initial state
         */
        reset() {
            if (this.form) {
                this.form.reset();
            }
            
            this.clearFieldError(this.emailInput);
            this.setFieldState(this.emailInput, 'default');
            this.hideAlert();
            this.hideLoadingState();
            
            // Show form view, hide success view
            if (this.formView && this.successView) {
                this.formView.classList.remove('hidden');
                this.successView.classList.add('hidden');
            }
        }
    }

    // Create global instance
    window.forgotPasswordFormManager = new ForgotPasswordFormManager();
    
    // Initialize on page load
    window.forgotPasswordFormManager.init();
    
    // Expose functions for backward compatibility
    window.submitForgotPassword = function(event) {
        if (event) event.preventDefault();
        if (window.forgotPasswordFormManager && window.forgotPasswordFormManager.form) {
            window.forgotPasswordFormManager.form.dispatchEvent(new Event('submit'));
        }
    };
    
    window.resetForgotPasswordModal = function() {
        if (window.forgotPasswordFormManager) {
            window.forgotPasswordFormManager.reset();
        }
    };

})();
