/**
 * Registration Form Interactions
 * Handles registration form validation, password strength, and plan selection
 * Based on: .kiro/specs/modal-redesign/tasks.md - Tasks 10 & 11
 */

(function() {
    'use strict';

    /**
     * Registration Form Manager Class
     * Manages registration form interactions, validation, and transitions
     */
    class RegistrationFormManager {
        constructor() {
            this.form = null;
            this.step1 = null;
            this.step2 = null;
            this.isSubmitting = false;
            this.selectedPlan = null;
            
            // Form inputs
            this.inputs = {
                name: null,
                company: null,
                email: null,
                phone: null,
                documentType: null,
                document: null,
                password: null,
                terms: null
            };
            
            // Bind methods
            this.handleSubmit = this.handleSubmit.bind(this);
            this.handlePasswordInput = this.handlePasswordInput.bind(this);
            this.handlePlanSelection = this.handlePlanSelection.bind(this);
        }

        /**
         * Initialize the registration form manager
         */
        init() {
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
            this.form = document.getElementById('registerForm');
            this.step1 = document.getElementById('registerStep1');
            this.step2 = document.getElementById('registerStep2');
            
            // Get all inputs
            this.inputs.name = document.getElementById('reg_name');
            this.inputs.company = document.getElementById('reg_company');
            this.inputs.email = document.getElementById('reg_email');
            this.inputs.phone = document.getElementById('reg_phone');
            this.inputs.documentType = document.getElementById('reg_document_type');
            this.inputs.document = document.getElementById('reg_document');
            this.inputs.password = document.getElementById('reg_password');
            this.inputs.terms = document.getElementById('reg_terms');

            if (!this.form) {
                console.warn('Registration form not found');
                return;
            }

            // Add event listeners
            if (this.inputs.name) {
                this.inputs.name.addEventListener('input', () => this.validateField('name'));
                this.inputs.name.addEventListener('blur', () => this.validateField('name', true));
            }
            
            if (this.inputs.email) {
                this.inputs.email.addEventListener('input', () => this.validateField('email'));
                this.inputs.email.addEventListener('blur', () => this.validateField('email', true));
            }
            
            if (this.inputs.phone) {
                this.inputs.phone.addEventListener('input', () => this.formatPhone());
            }
            
            if (this.inputs.document) {
                this.inputs.document.addEventListener('input', () => this.validateField('document'));
                this.inputs.document.addEventListener('blur', () => this.validateField('document', true));
            }
            
            if (this.inputs.password) {
                this.inputs.password.addEventListener('input', this.handlePasswordInput);
                this.inputs.password.addEventListener('blur', () => this.validateField('password', true));
            }
            
            // Form submission
            this.form.addEventListener('submit', this.handleSubmit);
        }

        /**
         * Handle password input and update strength indicator
         */
        handlePasswordInput() {
            const password = this.inputs.password.value;
            const strength = this.calculatePasswordStrength(password);
            
            // Update strength indicator
            const strengthBar = document.querySelector('.password-strength__bar');
            const strengthText = document.querySelector('.password-strength__text');
            
            if (strengthBar && strengthText) {
                // Remove all strength classes
                strengthBar.classList.remove('password-strength__bar--weak', 'password-strength__bar--medium', 'password-strength__bar--strong');
                
                if (password.length === 0) {
                    strengthBar.style.width = '0%';
                    strengthText.textContent = '';
                } else if (strength.score === 1) {
                    strengthBar.classList.add('password-strength__bar--weak');
                    strengthText.textContent = 'Fraca';
                    strengthText.style.color = 'var(--modal-error)';
                } else if (strength.score === 2) {
                    strengthBar.classList.add('password-strength__bar--medium');
                    strengthText.textContent = 'Média';
                    strengthText.style.color = '#f59e0b';
                } else {
                    strengthBar.classList.add('password-strength__bar--strong');
                    strengthText.textContent = 'Forte';
                    strengthText.style.color = 'var(--modal-success)';
                }
            }
            
            // Clear error on input
            this.clearFieldError(this.inputs.password);
        }

        /**
         * Calculate password strength
         * @param {string} password
         * @returns {Object} - {score: 1-3, feedback: string}
         */
        calculatePasswordStrength(password) {
            let score = 0;
            
            if (password.length >= 6) score++;
            if (password.length >= 10) score++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
            if (/\d/.test(password)) score++;
            if (/[^a-zA-Z0-9]/.test(password)) score++;
            
            // Normalize score to 1-3
            if (score <= 2) return { score: 1, feedback: 'Fraca' };
            if (score <= 4) return { score: 2, feedback: 'Média' };
            return { score: 3, feedback: 'Forte' };
        }

        /**
         * Format phone number
         */
        formatPhone() {
            if (!this.inputs.phone) return;
            
            let value = this.inputs.phone.value.replace(/\D/g, '');
            
            if (value.length <= 10) {
                // (11) 9999-9999
                value = value.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
            } else {
                // (11) 99999-9999
                value = value.replace(/^(\d{2})(\d{5})(\d{0,4}).*/, '($1) $2-$3');
            }
            
            this.inputs.phone.value = value;
        }

        /**
         * Validate a specific field
         * @param {string} fieldName
         * @param {boolean} showError
         * @returns {boolean}
         */
        validateField(fieldName, showError = false) {
            const input = this.inputs[fieldName];
            if (!input) return true;
            
            let isValid = true;
            let errorMessage = '';
            
            switch (fieldName) {
                case 'name':
                    if (!input.value.trim()) {
                        isValid = false;
                        errorMessage = 'Nome é obrigatório';
                    } else if (input.value.trim().length < 3) {
                        isValid = false;
                        errorMessage = 'Nome deve ter pelo menos 3 caracteres';
                    }
                    break;
                    
                case 'email':
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!input.value.trim()) {
                        isValid = false;
                        errorMessage = 'Email é obrigatório';
                    } else if (!emailRegex.test(input.value)) {
                        isValid = false;
                        errorMessage = 'Digite um email válido';
                    }
                    break;
                    
                case 'document':
                    const docType = this.inputs.documentType?.value || 'cpf';
                    const docValue = input.value.replace(/\D/g, '');
                    
                    if (!docValue) {
                        isValid = false;
                        errorMessage = 'Documento é obrigatório';
                    } else if (docType === 'cpf' && !this.validateCPF(docValue)) {
                        isValid = false;
                        errorMessage = 'CPF inválido';
                    } else if (docType === 'cnpj' && !this.validateCNPJ(docValue)) {
                        isValid = false;
                        errorMessage = 'CNPJ inválido';
                    }
                    break;
                    
                case 'password':
                    if (!input.value) {
                        isValid = false;
                        errorMessage = 'Senha é obrigatória';
                    } else if (input.value.length < 6) {
                        isValid = false;
                        errorMessage = 'Senha deve ter pelo menos 6 caracteres';
                    }
                    break;
            }
            
            if (!isValid && showError) {
                this.setFieldError(input, errorMessage);
            } else if (isValid) {
                this.setFieldState(input, 'valid');
            }
            
            return isValid;
        }

        /**
         * Validate CPF
         * @param {string} cpf
         * @returns {boolean}
         */
        validateCPF(cpf) {
            if (cpf.length !== 11) return false;
            if (/^(\d)\1+$/.test(cpf)) return false;
            
            let sum = 0;
            for (let i = 0; i < 9; i++) {
                sum += parseInt(cpf.charAt(i)) * (10 - i);
            }
            let digit = 11 - (sum % 11);
            if (digit >= 10) digit = 0;
            if (digit !== parseInt(cpf.charAt(9))) return false;
            
            sum = 0;
            for (let i = 0; i < 10; i++) {
                sum += parseInt(cpf.charAt(i)) * (11 - i);
            }
            digit = 11 - (sum % 11);
            if (digit >= 10) digit = 0;
            if (digit !== parseInt(cpf.charAt(10))) return false;
            
            return true;
        }

        /**
         * Validate CNPJ
         * @param {string} cnpj
         * @returns {boolean}
         */
        validateCNPJ(cnpj) {
            if (cnpj.length !== 14) return false;
            if (/^(\d)\1+$/.test(cnpj)) return false;
            
            let size = cnpj.length - 2;
            let numbers = cnpj.substring(0, size);
            let digits = cnpj.substring(size);
            let sum = 0;
            let pos = size - 7;
            
            for (let i = size; i >= 1; i--) {
                sum += numbers.charAt(size - i) * pos--;
                if (pos < 2) pos = 9;
            }
            
            let result = sum % 11 < 2 ? 0 : 11 - (sum % 11);
            if (result !== parseInt(digits.charAt(0))) return false;
            
            size = size + 1;
            numbers = cnpj.substring(0, size);
            sum = 0;
            pos = size - 7;
            
            for (let i = size; i >= 1; i--) {
                sum += numbers.charAt(size - i) * pos--;
                if (pos < 2) pos = 9;
            }
            
            result = sum % 11 < 2 ? 0 : 11 - (sum % 11);
            if (result !== parseInt(digits.charAt(1))) return false;
            
            return true;
        }

        /**
         * Set field error state
         * @param {HTMLElement} input
         * @param {string} message
         */
        setFieldError(input, message) {
            input.classList.add('form-group__input--error');
            input.classList.remove('form-group__input--valid');
            input.setAttribute('aria-invalid', 'true');
            
            const errorId = input.getAttribute('aria-describedby')?.split(' ')[0];
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
         * @param {HTMLElement} input
         */
        clearFieldError(input) {
            input.classList.remove('form-group__input--error');
            input.setAttribute('aria-invalid', 'false');
            
            const errorId = input.getAttribute('aria-describedby')?.split(' ')[0];
            const errorElement = errorId ? document.getElementById(errorId) : null;
            
            if (errorElement) {
                errorElement.textContent = '';
                errorElement.classList.add('form-group__error--hidden');
            }
        }

        /**
         * Set field state
         * @param {HTMLElement} input
         * @param {string} state
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
         * Show loading state
         */
        showLoadingState() {
            this.isSubmitting = true;
            const submitButton = document.getElementById('registerBtn');
            
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.classList.add('btn--loading');
                submitButton.setAttribute('aria-busy', 'true');
                
                const buttonText = submitButton.querySelector('.btn__text');
                if (buttonText) {
                    buttonText.textContent = 'Criando conta...';
                }
                
                if (!submitButton.querySelector('.btn__spinner')) {
                    const spinner = document.createElement('span');
                    spinner.className = 'btn__spinner';
                    submitButton.insertBefore(spinner, submitButton.firstChild);
                }
            }
        }

        /**
         * Hide loading state
         */
        hideLoadingState() {
            this.isSubmitting = false;
            const submitButton = document.getElementById('registerBtn');
            
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.classList.remove('btn--loading');
                submitButton.removeAttribute('aria-busy');
                
                const buttonText = submitButton.querySelector('.btn__text');
                if (buttonText) {
                    buttonText.textContent = 'Criar Conta Grátis';
                }
                
                const spinner = submitButton.querySelector('.btn__spinner');
                if (spinner) {
                    spinner.remove();
                }
            }
        }

        /**
         * Show error message
         * @param {string} message
         */
        showErrorMessage(message) {
            const alertElement = document.getElementById('registerAlert');
            if (!alertElement) return;
            
            alertElement.className = 'mb-4 p-4 rounded-md text-sm bg-red-50 border border-red-200 text-red-800';
            alertElement.textContent = message;
            alertElement.classList.remove('hidden');
            alertElement.classList.add('error-appear');
            
            setTimeout(() => {
                alertElement.classList.remove('error-appear');
            }, 200);
        }

        /**
         * Hide alert
         */
        hideAlert() {
            const alertElement = document.getElementById('registerAlert');
            if (alertElement) {
                alertElement.classList.add('hidden');
                alertElement.textContent = '';
            }
        }

        /**
         * Handle form submission
         * @param {Event} event
         */
        async handleSubmit(event) {
            event.preventDefault();
            
            if (this.isSubmitting) return;
            
            this.hideAlert();
            
            // Validate all fields
            const validations = [
                this.validateField('name', true),
                this.validateField('email', true),
                this.validateField('document', true),
                this.validateField('password', true)
            ];
            
            // Check terms
            if (!this.inputs.terms.checked) {
                const termsError = document.getElementById('reg-terms-error');
                if (termsError) {
                    termsError.textContent = 'Você deve aceitar os termos';
                    termsError.classList.remove('form-group__error--hidden');
                }
                validations.push(false);
            }
            
            if (validations.some(v => !v)) {
                // Focus first invalid field
                for (const key in this.inputs) {
                    if (this.inputs[key] && this.inputs[key].classList.contains('form-group__input--error')) {
                        this.inputs[key].focus();
                        break;
                    }
                }
                return;
            }
            
            this.showLoadingState();
            
            // Prepare form data
            const formData = {
                name: this.inputs.name.value.trim(),
                company_name: this.inputs.company.value.trim(),
                email: this.inputs.email.value.trim(),
                phone: this.inputs.phone.value.trim(),
                document_type: this.inputs.documentType.value,
                document: this.inputs.document.value.replace(/\D/g, ''),
                password: this.inputs.password.value
            };
            
            try {
                const response = await fetch('api/register_ajax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'register',
                        ...formData
                    })
                });
                
                const data = await response.json();
                
                this.hideLoadingState();
                
                if (data.success) {
                    // Transition to step 2 (plan selection)
                    this.showStep2();
                } else {
                    this.showErrorMessage(data.message || 'Erro ao criar conta. Tente novamente.');
                }
            } catch (error) {
                console.error('Registration error:', error);
                this.hideLoadingState();
                this.showErrorMessage('Erro de conexão. Verifique sua internet e tente novamente.');
            }
        }

        /**
         * Show step 2 (plan selection)
         */
        showStep2() {
            if (!this.step1 || !this.step2) return;
            
            if (window.modalManager) {
                window.modalManager.transitionView(this.step1, this.step2, 'left');
            } else {
                this.step1.classList.add('hidden');
                this.step2.classList.remove('hidden');
            }
            
            // Load plans if not already loaded
            setTimeout(() => {
                this.loadPlans();
            }, 300);
        }

        /**
         * Load plans
         */
        async loadPlans() {
            const plansGrid = document.getElementById('plansGrid');
            if (!plansGrid) return;
            
            try {
                const response = await fetch('api/get_plans.php');
                const data = await response.json();
                
                if (data.success && data.plans) {
                    this.renderPlans(data.plans);
                }
            } catch (error) {
                console.error('Error loading plans:', error);
            }
        }

        /**
         * Render plans
         * @param {Array} plans
         */
        renderPlans(plans) {
            const plansGrid = document.getElementById('plansGrid');
            if (!plansGrid) return;
            
            plansGrid.innerHTML = plans.map((plan, index) => `
                <div class="plan-card ${plan.popular ? 'plan-card--popular' : ''}" data-plan-id="${plan.id}" onclick="window.registrationFormManager.handlePlanSelection(${plan.id})">
                    <div class="plan-card__header">
                        <h3 class="plan-card__name">${plan.name}</h3>
                        <div class="plan-card__price">
                            <span class="plan-card__price-currency">R$</span> ${plan.price}
                            <span class="plan-card__price-period">/mês</span>
                        </div>
                    </div>
                    <ul class="plan-card__features">
                        ${plan.features.map(feature => `
                            <li class="plan-card__feature">${feature}</li>
                        `).join('')}
                    </ul>
                    <button type="button" class="btn btn--primary plan-card__button">
                        Selecionar Plano
                    </button>
                </div>
            `).join('');
        }

        /**
         * Handle plan selection
         * @param {number} planId
         */
        async handlePlanSelection(planId) {
            this.selectedPlan = planId;
            
            // Visual feedback
            document.querySelectorAll('.plan-card').forEach(card => {
                card.classList.remove('plan-card--selected');
            });
            
            const selectedCard = document.querySelector(`[data-plan-id="${planId}"]`);
            if (selectedCard) {
                selectedCard.classList.add('plan-card--selected');
            }
            
            // Submit plan selection
            try {
                const response = await fetch('api/select_plan.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'select_plan',
                        plan_id: planId
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Redirect to dashboard or payment
                    setTimeout(() => {
                        window.location.href = data.redirect || 'dashboard.php';
                    }, 1000);
                }
            } catch (error) {
                console.error('Plan selection error:', error);
            }
        }

        /**
         * Reset form
         */
        reset() {
            if (this.form) {
                this.form.reset();
            }
            
            // Clear all errors
            for (const key in this.inputs) {
                if (this.inputs[key]) {
                    this.clearFieldError(this.inputs[key]);
                    this.setFieldState(this.inputs[key], 'default');
                }
            }
            
            // Reset password strength
            const strengthBar = document.querySelector('.password-strength__bar');
            const strengthText = document.querySelector('.password-strength__text');
            if (strengthBar) strengthBar.style.width = '0%';
            if (strengthText) strengthText.textContent = '';
            
            this.hideAlert();
            this.hideLoadingState();
            
            // Show step 1
            if (this.step1 && this.step2) {
                this.step1.classList.remove('hidden');
                this.step2.classList.add('hidden');
            }
        }
    }

    // Create global instance
    window.registrationFormManager = new RegistrationFormManager();
    
    // Initialize on page load
    window.registrationFormManager.init();
    
    // Expose functions for backward compatibility
    window.backToRegisterStep1 = function() {
        if (window.registrationFormManager && window.registrationFormManager.step1 && window.registrationFormManager.step2) {
            const step1 = window.registrationFormManager.step1;
            const step2 = window.registrationFormManager.step2;
            
            if (window.modalManager) {
                window.modalManager.transitionView(step2, step1, 'right');
            } else {
                step2.classList.add('hidden');
                step1.classList.remove('hidden');
            }
        }
    };

})();
