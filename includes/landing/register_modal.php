<!-- Register Modal (Light Theme) -->
<div id="registerModal" class="hidden fixed inset-0 z-[100] overflow-y-auto" aria-labelledby="register-modal-title" role="dialog" aria-modal="true">

    <!-- Backdrop with click to close -->
    <div class="modal-backdrop modal-backdrop--blur" onclick="closeRegisterModal(event)">
        <!-- Modal Card -->
        <div class="modal-container modal-container--large" onclick="event.stopPropagation()">

            <!-- STEP 1: REGISTRATION FORM -->
            <div id="registerStep1" class="register-step">
                <!-- Modal Header -->
                <div class="modal-container__header">
                    <h2 class="modal-container__title" id="register-modal-title">Criar Conta Grátis</h2>
                    <button type="button" onclick="closeRegisterModal()" class="modal-container__close" aria-label="Fechar modal">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="modal-container__body">
                    <p style="text-align: center; color: var(--modal-text-muted); font-size: var(--modal-text-label); margin-bottom: var(--modal-section-gap);">
                        Comece seu teste de 15 dias agora! Sem compromisso.
                    </p>

                    <!-- Alerts -->
                    <div id="registerAlert" class="hidden mb-4 p-4 rounded-md text-sm" role="alert" aria-live="polite"></div>

                    <!-- Form -->
                    <form id="registerForm">
                        <!-- Name & Company -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="reg_name" class="form-group__label form-group__label--required">Nome Completo</label>
                                <input 
                                    type="text" 
                                    name="name" 
                                    id="reg_name"
                                    class="form-group__input"
                                    placeholder="Seu nome completo" 
                                    required
                                    autocomplete="name"
                                    aria-required="true"
                                    aria-describedby="reg-name-error">
                                <span id="reg-name-error" class="form-group__error form-group__error--hidden" role="alert"></span>
                            </div>
                            <div class="form-group">
                                <label for="reg_company" class="form-group__label">Nome da Empresa</label>
                                <input 
                                    type="text" 
                                    name="company_name" 
                                    id="reg_company"
                                    class="form-group__input"
                                    placeholder="Sua empresa"
                                    autocomplete="organization"
                                    aria-describedby="reg-company-error">
                                <span id="reg-company-error" class="form-group__error form-group__error--hidden" role="alert"></span>
                            </div>
                        </div>

                        <!-- Email & Phone -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="reg_email" class="form-group__label form-group__label--required">Email</label>
                                <input 
                                    type="email" 
                                    name="email" 
                                    id="reg_email"
                                    class="form-group__input"
                                    placeholder="seu@email.com" 
                                    required
                                    autocomplete="email"
                                    aria-required="true"
                                    aria-describedby="reg-email-error">
                                <span id="reg-email-error" class="form-group__error form-group__error--hidden" role="alert"></span>
                            </div>
                            <div class="form-group">
                                <label for="reg_phone" class="form-group__label">Telefone</label>
                                <input 
                                    type="tel" 
                                    name="phone" 
                                    id="reg_phone"
                                    class="form-group__input"
                                    placeholder="(11) 99999-9999"
                                    autocomplete="tel"
                                    aria-describedby="reg-phone-error">
                                <span id="reg-phone-error" class="form-group__error form-group__error--hidden" role="alert"></span>
                            </div>
                        </div>

                        <!-- Document Type & Number -->
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-group__label form-group__label--required">Tipo de Documento</label>
                                <select 
                                    name="document_type" 
                                    id="reg_document_type"
                                    class="form-group__input"
                                    onchange="updateDocumentMask()"
                                    required
                                    aria-required="true"
                                    aria-describedby="reg-document-type-error">
                                    <option value="cpf">CPF</option>
                                    <option value="cnpj">CNPJ</option>
                                </select>
                                <span id="reg-document-type-error" class="form-group__error form-group__error--hidden" role="alert"></span>
                            </div>
                            <div class="form-group">
                                <label for="reg_document" class="form-group__label form-group__label--required">Número do Documento</label>
                                <input 
                                    type="text" 
                                    name="document" 
                                    id="reg_document"
                                    class="form-group__input"
                                    placeholder="000.000.000-00" 
                                    required
                                    aria-required="true"
                                    aria-describedby="reg-document-error">
                                <span id="reg-document-error" class="form-group__error form-group__error--hidden" role="alert"></span>
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="form-group">
                            <label for="reg_password" class="form-group__label form-group__label--required">Senha</label>
                            <input 
                                type="password" 
                                name="password" 
                                id="reg_password"
                                class="form-group__input"
                                placeholder="Mínimo 6 caracteres" 
                                required 
                                minlength="6"
                                autocomplete="new-password"
                                aria-required="true"
                                aria-describedby="reg-password-error reg-password-strength">
                            <span id="reg-password-error" class="form-group__error form-group__error--hidden" role="alert"></span>
                            
                            <!-- Password Strength Indicator -->
                            <div id="reg-password-strength" class="password-strength" style="margin-top: 8px; height: 4px; background: var(--modal-border-default); border-radius: 2px; overflow: hidden;">
                                <div class="password-strength__bar" style="height: 100%; width: 0%; transition: all 0.3s ease; background: var(--modal-error);"></div>
                            </div>
                            <span class="password-strength__text" style="display: block; margin-top: 4px; font-size: var(--modal-text-small); color: var(--modal-text-muted);"></span>
                        </div>

                        <!-- Terms -->
                        <div class="form-group">
                            <label class="form-group__checkbox">
                                <input 
                                    id="reg_terms" 
                                    name="terms" 
                                    type="checkbox"
                                    required
                                    aria-required="true"
                                    aria-describedby="reg-terms-error">
                                <span class="form-group__checkbox-label">
                                    Eu concordo com os <a href="#" class="btn--link" style="font-size: inherit; text-decoration: underline;">Termos de Uso</a> e <a href="#" class="btn--link" style="font-size: inherit; text-decoration: underline;">Política de Privacidade</a>
                                </span>
                            </label>
                            <span id="reg-terms-error" class="form-group__error form-group__error--hidden" role="alert"></span>
                        </div>

                        <!-- Submit -->
                        <button type="submit" id="registerBtn" class="btn btn--primary btn--full-width" aria-busy="false">
                            <span class="btn__text">Criar Conta Grátis</span>
                        </button>
                    </form>

                    <!-- Modal Footer -->
                    <div class="modal-container__footer" style="text-align: center;">
                        <p style="font-size: var(--modal-text-label); color: var(--modal-text-muted); margin: 0;">
                            Já tem uma conta? 
                            <a href="#" onclick="switchToLogin(); return false;" class="btn--link" style="font-size: var(--modal-text-label); font-weight: var(--modal-weight-semibold); text-decoration: none;">
                                Fazer Login
                            </a>
                        </p>
                    </div>
                </div>
            </div>

            <!-- STEP 2: PLANS -->
            <div id="registerStep2" class="register-step hidden">
                <!-- Back Button -->
                <button type="button" onclick="backToRegisterStep1()" class="btn btn--ghost" style="margin-bottom: var(--modal-section-gap); padding-left: 0;">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 20px; height: 20px; margin-right: 8px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    <span>Voltar</span>
                </button>

                <!-- Modal Header -->
                <div class="modal-container__header" style="padding-bottom: 0; margin-bottom: var(--space-4);">
                    <div>
                        <h2 class="modal-container__title">Escolha seu Plano</h2>
                        <p style="font-size: var(--modal-text-label); color: var(--modal-text-muted); margin-top: 8px;">
                            Selecione o plano que melhor atende suas necessidades
                        </p>
                    </div>
                </div>

                <!-- Modal Body -->
                <div class="modal-container__body">
                    <!-- Success Alert -->
                    <div class="mb-4 p-4 rounded-md text-sm bg-green-50 border border-green-200 text-green-800" style="display: flex; align-items: start; gap: 12px;">
                        <svg class="w-5 h-5 text-green-400" style="flex-shrink: 0; margin-top: 2px;" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <p>Cadastro realizado com sucesso! Escolha um plano abaixo.</p>
                    </div>

                    <!-- Plan Alert -->
                    <div id="planAlert" class="hidden mb-4 p-4 rounded-md text-sm" role="alert" aria-live="polite"></div>

                    <!-- Plans Grid -->
                    <div id="plansGrid" class="plans-grid">
                        <!-- Plans loaded via JS -->
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
