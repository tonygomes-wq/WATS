/**
 * 2FA Form Interactions
 * Handles 2FA verification form interactions, validation, and transitions
 * Based on: .kiro/specs/modal-redesign/tasks.md - Task 8
 */

(function() {
    'use strict';

    /**
     * 2FA Form Manager Class
     * Manages 2FA form interactions and validation
     */
    class TwoFAFormManager {
        constructor() {
            this.form = null;
            this.codeInput = null;
            this.backupCodeInput = null;
            this.submitButton = null;
            this.isSubmitting = false;
            
            // Bind methods
            this.handleSubmit = this.handleSubmit.bind(this);
            this.handleCodeInput = this.handleCodeInput.bind(this);
            this.handleBackupCodeInput = this.handleBackupCodeInput.bind(this);
            this.handleBackToLogin = this.handleBackToLogin.bind(this);
        }

        /**
         * Initialize the 2FA form manager
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
            this.form = document.getElementById('twoFactorFormElement');
            this.codeInput = document.getElementById('modal_code');
            this.backupCodeInput = document.getElementById('modal_backup_code');
            this.submitButton = document.getElementById('verify2FABtn');

            if (!this.form || !this.codeInput || !this.submitButton) {
                console.warn('2FA form elements not found');
                return;
            }

            // Add event listeners
            this.codeInput.addEventListener('input', this.handleCodeInput);
            
            if (this.backupCodeInput) {
                this.backupCodeInput.addEventListener('input', this.handleBackupCodeInput);
            }
            
            // Form submission
            this.form.addEventListener('submit', this.handleSubmit);
        }

        /**
         * Handle code input changes
         */
        handleCodeInput() {
            // Auto-format: only allow numbers
            let value = this.codeInput.value.replace(/\D/g, '');
            
            // Limit to 6 digits
            if (value.length > 6) {
                value = value.substring(0, 6);
            }
            
            this.codeInput.value = value;
            
            // Clear error state
            this.clearFieldError(this.codeInput);
            
            // Clear backup code if user is typing in code field
            if (value.length > 0 && this.backupCodeInput) {
                this.backupCodeInput.value = '';
                this.clearFieldError(this.backupCodeInput);
            }
            
            // Auto-submit when 6 digits are entered
            if (value.length === 6) {
                this.setFieldState(this.codeInput, 'valid');
                // Auto-submit after a short delay
                setTimeout(() => {
                    if (this.codeInput.value.length === 6 && !this.isSubmitting) {
                        this.form.dispatchEvent(new Event('submit'));
                    }
                }, 300);
            }
        }

        /**
         * Handle backup code input changes
         */
        handleBackupCodeInput() {
            // Auto-format: allow numbers and hyphens
            let value = this.backupCodeInput.value.replace(/[^0-9-]/g, '');
            
            // Auto-add hyphen after 4 digits
            if (value.length === 4 && !value.includes('-')) {
                value = value + '-';
            }
            
            // Limit to 9 characters (0000-0000)
            if (value.length > 9) {
                value = value.substring(0, 9);
            }
            
            this.backupCodeInput.value = value;
            
            // Clear error state
            this.clearFieldError(this.backupCodeInput);
            
            // Clear code if user is typing in backup code field
            if (value.length > 0 && this.codeInput) {
                this.codeInput.value = '';
                this.clearFieldError(this.codeInput);
            }
        }

        /**
         * Validate code input
         * @returns {boolean} - Whether code is valid
         */
        validateCode() {
            const code = this.codeInput.value.trim();
            const backupCode = this.backupCodeInput ? this.backupCodeInput.value.trim() : '';
            
            // Must have either code or backup code
            if (!code && !backupCode) {
                this.setFieldError(this.codeInput, 'Digite o código de verificação ou código de backup');
                return false;
            }
            
            // Validate authenticator code format
            if (code && code.length !== 6) {
                this.setFieldError(this.codeInput, 'O código deve ter 6 dígitos');
                return false;
            }
            
            // Validate backup code format
            if (backupCode && !backupCode.match(/^\d{4}-\d{4}$/)) {
                this.setFieldError(this.backupCodeInput, 'Formato inválido. Use: 0000-0000');
                return false;
            }
            
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
                buttonText.textContent = 'Verificando...';
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
                buttonText.textContent = 'Verificar';
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
            const alertElement = document.getElementById('twoFactorAlert');
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
         * Show success message in alert
         * @param {string} message - Success message
         */
        showSuccessMessage(message) {
            const alertElement = document.getElementById('twoFactorAlert');
            if (!alertElement) return;
            
            alertElement.className = 'mb-4 p-4 rounded-md text-sm bg-green-50 border border-green-200 text-green-800';
            alertElement.textContent = message;
            alertElement.classList.remove('hidden');
            alertElement.classList.add('success-bounce');
            alertElement.setAttribute('role', 'alert');
            alertElement.setAttribute('aria-live', 'polite');
            
            setTimeout(() => {
                alertElement.classList.remove('success-bounce');
            }, 400);
        }

        /**
         * Hide alert message
         */
        hideAlert() {
            const alertElement = document.getElementById('twoFactorAlert');
            if (alertElement) {
                alertElement.classList.add('hidden');
                alertElement.textContent = '';
            }
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
            
            // Validate
            if (!this.validateCode()) {
                return;
            }
            
            this.showLoadingState();
            
            const code = this.codeInput.value.trim();
            const backupCode = this.backupCodeInput ? this.backupCodeInput.value.trim() : '';
            
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
                
                this.hideLoadingState();
                
                if (data.success) {
                    this.showSuccessMessage(data.message || 'Verificação bem-sucedida!');
                    
                    setTimeout(() => {
                        window.location.href = data.redirect || 'dashboard.php';
                    }, 1500);
                } else {
                    this.showErrorMessage(data.message || 'Código inválido. Tente novamente.');
                    
                    // Clear inputs on error
                    this.codeInput.value = '';
                    if (this.backupCodeInput) {
                        this.backupCodeInput.value = '';
                    }
                    this.codeInput.focus();
                }
            } catch (error) {
                console.error('2FA verification error:', error);
                this.hideLoadingState();
                this.showErrorMessage('Erro de conexão. Verifique sua internet e tente novamente.');
            }
        }

        /**
         * Handle back to login button
         */
        handleBackToLogin() {
            const twoFactorForm = document.getElementById('twoFactorForm');
            const loginForm = document.getElementById('loginForm');
            
            if (twoFactorForm && loginForm && window.modalManager) {
                window.modalManager.transitionView(twoFactorForm, loginForm, 'right');
                
                // Reset 2FA form
                this.reset();
                
                // Focus on email input after transition
                setTimeout(() => {
                    const emailInput = document.getElementById('modal_email');
                    if (emailInput) {
                        emailInput.focus();
                    }
                }, 300);
            }
        }

        /**
         * Reset form to initial state
         */
        reset() {
            if (this.form) {
                this.form.reset();
            }
            
            this.clearFieldError(this.codeInput);
            if (this.backupCodeInput) {
                this.clearFieldError(this.backupCodeInput);
            }
            this.setFieldState(this.codeInput, 'default');
            if (this.backupCodeInput) {
                this.setFieldState(this.backupCodeInput, 'default');
            }
            this.hideAlert();
            this.hideLoadingState();
        }
    }

    // Create global instance
    window.twoFAFormManager = new TwoFAFormManager();
    
    // Initialize on page load
    window.twoFAFormManager.init();
    
    // Expose functions for backward compatibility
    window.backToLogin = function() {
        if (window.twoFAFormManager) {
            window.twoFAFormManager.handleBackToLogin();
        }
    };

})();
