/**
 * Login Form Interactions
 * Handles real-time validation, loading states, error messages, and keyboard shortcuts
 * Based on: .kiro/specs/modal-redesign/tasks.md - Task 7
 */

(function() {
    'use strict';

    /**
     * Login Form Manager Class
     * Manages all login form interactions and validation
     */
    class LoginFormManager {
        constructor() {
            this.form = null;
            this.emailInput = null;
            this.passwordInput = null;
            this.submitButton = null;
            this.isSubmitting = false;
            
            // Bind methods
            this.handleSubmit = this.handleSubmit.bind(this);
            this.handleEmailInput = this.handleEmailInput.bind(this);
            this.handlePasswordInput = this.handlePasswordInput.bind(this);
            this.handleKeyPress = this.handleKeyPress.bind(this);
        }

        /**
         * Initialize the login form manager
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
            this.form = document.getElementById('loginForm');
            this.emailInput = document.getElementById('modal_email');
            this.passwordInput = document.getElementById('modal_password');
            this.submitButton = document.getElementById('loginBtn');

            if (!this.form || !this.emailInput || !this.passwordInput || !this.submitButton) {
                console.warn('Login form elements not found');
                return;
            }

            // Add event listeners
            this.emailInput.addEventListener('input', this.handleEmailInput);
            this.emailInput.addEventListener('blur', () => this.validateEmail(true));
            
            this.passwordInput.addEventListener('input', this.handlePasswordInput);
            this.passwordInput.addEventListener('blur', () => this.validatePassword(true));
            
            // Keyboard shortcuts
            this.emailInput.addEventListener('keypress', this.handleKeyPress);
            this.passwordInput.addEventListener('keypress', this.handleKeyPress);
            
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
         * Handle password input changes
         */
        handlePasswordInput() {
            // Clear error state on input
            this.clearFieldError(this.passwordInput);
            
            // Real-time validation (non-intrusive)
            if (this.passwordInput.value.length > 0) {
                this.validatePassword(false);
            }
        }

        /**
         * Handle keyboard shortcuts
         * @param {KeyboardEvent} event
         */
        handleKeyPress(event) {
            // Enter key submits form
            if (event.key === 'Enter' && !this.isSubmitting) {
                event.preventDefault();
                this.form.dispatchEvent(new Event('submit'));
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
         * Validate password field
         * @param {boolean} showError - Whether to show error message
         * @returns {boolean} - Whether password is valid
         */
        validatePassword(showError = false) {
            const password = this.passwordInput.value;
            
            if (!password) {
                if (showError) {
                    this.setFieldError(this.passwordInput, 'Senha é obrigatória');
                }
                return false;
            }
            
            if (password.length < 6) {
                if (showError) {
                    this.setFieldError(this.passwordInput, 'Senha deve ter pelo menos 6 caracteres');
                } else {
                    this.setFieldState(this.passwordInput, 'default');
                }
                return false;
            }
            
            this.setFieldState(this.passwordInput, 'valid');
            return true;
        }

        /**
         * Set field error state
         * @param {HTMLElement} input - Input element
         * @param {string} message - Error message
         */
        setFieldError(input, message) {
            // Add error class to input
            input.classList.add('form-group__input--error');
            input.classList.remove('form-group__input--valid');
            input.setAttribute('aria-invalid', 'true');
            
            // Find or create error message element
            const errorId = input.getAttribute('aria-describedby');
            const errorElement = errorId ? document.getElementById(errorId) : null;
            
            if (errorElement) {
                errorElement.textContent = message;
                errorElement.classList.remove('form-group__error--hidden');
                errorElement.classList.add('error-appear');
                
                // Remove animation class after completion
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
            
            // Update button content
            const buttonText = this.submitButton.querySelector('.btn__text');
            if (buttonText) {
                buttonText.textContent = 'Entrando...';
            }
            
            // Add spinner if not exists
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
            
            // Update button content
            const buttonText = this.submitButton.querySelector('.btn__text');
            if (buttonText) {
                buttonText.textContent = 'Entrar';
            }
            
            // Remove spinner
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
            const alertElement = document.getElementById('loginAlert');
            if (!alertElement) return;
            
            alertElement.className = 'mb-4 p-4 rounded-md text-sm bg-red-50 border border-red-200 text-red-800';
            alertElement.textContent = message;
            alertElement.classList.remove('hidden');
            alertElement.classList.add('error-appear');
            alertElement.setAttribute('role', 'alert');
            alertElement.setAttribute('aria-live', 'polite');
            
            // Remove animation class after completion
            setTimeout(() => {
                alertElement.classList.remove('error-appear');
            }, 200);
        }

        /**
         * Show success message in alert
         * @param {string} message - Success message
         */
        showSuccessMessage(message) {
            const alertElement = document.getElementById('loginAlert');
            if (!alertElement) return;
            
            alertElement.className = 'mb-4 p-4 rounded-md text-sm bg-green-50 border border-green-200 text-green-800';
            alertElement.textContent = message;
            alertElement.classList.remove('hidden');
            alertElement.classList.add('success-bounce');
            alertElement.setAttribute('role', 'alert');
            alertElement.setAttribute('aria-live', 'polite');
            
            // Remove animation class after completion
            setTimeout(() => {
                alertElement.classList.remove('success-bounce');
            }, 400);
        }

        /**
         * Hide alert message
         */
        hideAlert() {
            const alertElement = document.getElementById('loginAlert');
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
            
            // Prevent double submission
            if (this.isSubmitting) {
                return;
            }
            
            // Hide any existing alerts
            this.hideAlert();
            
            // Validate all fields
            const emailValid = this.validateEmail(true);
            const passwordValid = this.validatePassword(true);
            
            if (!emailValid || !passwordValid) {
                // Focus first invalid field
                if (!emailValid) {
                    this.emailInput.focus();
                } else if (!passwordValid) {
                    this.passwordInput.focus();
                }
                return;
            }
            
            // Show loading state
            this.showLoadingState();
            
            // Get form data
            const email = this.emailInput.value.trim();
            const password = this.passwordInput.value;
            const remember = document.getElementById('modal_remember')?.checked || false;
            
            try {
                // Make API request
                const response = await fetch('api/login_ajax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'login',
                        email: email,
                        password: password,
                        remember: remember
                    })
                });
                
                const data = await response.json();
                
                // Hide loading state
                this.hideLoadingState();
                
                if (data.success) {
                    // Check if 2FA is required
                    if (data.require_2fa) {
                        // Show 2FA form
                        this.showSuccessMessage(data.message || 'Código de verificação enviado');
                        
                        // Transition to 2FA form
                        setTimeout(() => {
                            const loginForm = document.getElementById('loginForm');
                            const twoFactorForm = document.getElementById('twoFactorForm');
                            
                            if (loginForm && twoFactorForm && window.modalManager) {
                                window.modalManager.transitionView(loginForm, twoFactorForm, 'left');
                                
                                // Focus on code input
                                setTimeout(() => {
                                    const codeInput = document.getElementById('modal_code');
                                    if (codeInput) {
                                        codeInput.focus();
                                    }
                                }, 300);
                            }
                        }, 1000);
                    } else {
                        // Success - redirect
                        this.showSuccessMessage(data.message || 'Login realizado com sucesso!');
                        
                        setTimeout(() => {
                            window.location.href = data.redirect || 'dashboard.php';
                        }, 1500);
                    }
                } else {
                    // Show error message
                    this.showErrorMessage(data.message || 'Erro ao fazer login. Tente novamente.');
                    
                    // Clear password field on error
                    this.passwordInput.value = '';
                    this.passwordInput.focus();
                }
            } catch (error) {
                console.error('Login error:', error);
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
            this.clearFieldError(this.passwordInput);
            this.setFieldState(this.emailInput, 'default');
            this.setFieldState(this.passwordInput, 'default');
            this.hideAlert();
            this.hideLoadingState();
        }
    }

    // Create global instance
    window.loginFormManager = new LoginFormManager();
    
    // Initialize on page load
    window.loginFormManager.init();
    
    // Expose reset function for backward compatibility
    window.resetLoginForm = function() {
        if (window.loginFormManager) {
            window.loginFormManager.reset();
        }
    };

})();
