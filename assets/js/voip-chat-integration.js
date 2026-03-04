/**
 * VoIP Chat Integration - WATS
 * Integração do VoIP com o sistema de chat
 * 
 * @author Winston (Arquiteto WATS)
 * @date 2026-03-03
 */

class VoIPChatIntegration {
    constructor() {
        this.voipClient = null;
        this.isInitialized = false;
        this.hasAccount = false;
        this.currentCallModal = null;
    }
    
    /**
     * Inicializar integração
     */
    async init() {
        try {
            console.log('[VoIP Integration] Inicializando...');
            
            // Verificar se usuário tem conta VoIP
            const response = await fetch('/api/voip/get_credentials.php');
            const data = await response.json();
            
            if (data.success && data.has_account) {
                this.hasAccount = true;
                console.log('[VoIP Integration] Conta VoIP encontrada');
                
                // Verificar se VoIPWebRTCClient está disponível
                if (typeof VoIPWebRTCClient !== 'undefined') {
                    // Criar cliente WebRTC
                    this.voipClient = new VoIPWebRTCClient();
                    
                    // Configurar callbacks
                    this.setupCallbacks();
                    
                    // Inicializar cliente
                    await this.voipClient.init();
                    
                    // Conectar ao servidor (se provedor configurado)
                    if (data.provider_configured) {
                        await this.voipClient.connect();
                        this.showStatusIndicator('online');
                    } else {
                        console.log('[VoIP Integration] Provedor não configurado');
                        this.showStatusIndicator('warning');
                    }
                    
                    this.isInitialized = true;
                    console.log('[VoIP Integration] Inicializado com sucesso');
                } else {
                    console.warn('[VoIP Integration] VoIPWebRTCClient não disponível');
                    this.hasAccount = true;
                    this.isInitialized = false;
                }
                
            } else {
                console.log('[VoIP Integration] Conta VoIP não configurada');
                this.hasAccount = false;
                this.isInitialized = false;
            }
            
        } catch (error) {
            console.error('[VoIP Integration] Erro ao inicializar:', error);
            this.hasAccount = false;
            this.isInitialized = false;
        }
    }
    
    /**
     * Configurar callbacks do cliente
     */
    setupCallbacks() {
        this.voipClient.onRegistered = () => {
            console.log('[VoIP Integration] Registrado no servidor');
            this.showStatusIndicator('online');
            this.showNotification('VoIP conectado', 'success');
        };
        
        this.voipClient.onUnregistered = () => {
            console.log('[VoIP Integration] Desregistrado do servidor');
            this.showStatusIndicator('offline');
        };
        
        this.voipClient.onIncomingCall = (call) => {
            console.log('[VoIP Integration] Chamada recebida:', call);
            this.showIncomingCallModal(call);
        };
        
        this.voipClient.onCallAnswered = (call) => {
            console.log('[VoIP Integration] Chamada atendida:', call);
            if (this.currentCallModal) {
                this.updateCallModal('active');
            }
        };
        
        this.voipClient.onCallEnded = (call) => {
            console.log('[VoIP Integration] Chamada encerrada:', call);
            this.closeCallModal();
        };
        
        this.voipClient.onError = (error) => {
            console.error('[VoIP Integration] Erro:', error);
            this.showNotification('Erro VoIP: ' + error.message, 'error');
        };
    }
    
    /**
     * Iniciar chamada para um contato
     */
    async call(phoneNumber, contactName = null) {
        // Verificar se tem conta configurada
        if (!this.hasAccount) {
            this.showConfigurationModal();
            return;
        }
        
        // Verificar se está inicializado
        if (!this.isInitialized || !this.voipClient) {
            this.showNotification('VoIP não está inicializado. Configure sua conta primeiro.', 'warning');
            this.showConfigurationModal();
            return;
        }
        
        try {
            console.log('[VoIP Integration] Iniciando chamada para:', phoneNumber);
            
            // Mostrar modal de chamada
            this.showOutgoingCallModal(phoneNumber, contactName);
            
            // Iniciar chamada
            const call = await this.voipClient.call(phoneNumber);
            
            console.log('[VoIP Integration] Chamada iniciada:', call);
            
        } catch (error) {
            console.error('[VoIP Integration] Erro ao iniciar chamada:', error);
            this.showNotification('Erro ao iniciar chamada: ' + error.message, 'error');
            this.closeCallModal();
        }
    }
    
    /**
     * Mostrar modal de configuração
     */
    showConfigurationModal() {
        // Carregar formulário via AJAX
        this.loadAccountSettingsModal();
    }
    
    /**
     * Carregar formulário de configuração de conta
     */
    async loadAccountSettingsModal() {
        try {
            // Buscar dados da conta (se existir)
            const response = await fetch('/api/voip/get_credentials.php');
            const data = await response.json();
            
            const account = data.account || {};
            
            // Criar modal com formulário
            const modal = document.createElement('div');
            modal.className = 'voip-modal voip-account-modal';
            modal.innerHTML = `
                <div class="voip-modal-content voip-account-content">
                    <div class="voip-dialog-header">
                        <h3>${account.id ? 'Editar Conta VoIP' : 'Nova Conta VoIP'}</h3>
                        <button class="voip-close-btn" onclick="this.closest('.voip-modal').remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="voip-dialog-content">
                        <form id="voip-account-form-modal" onsubmit="voipIntegration.saveAccountModal(event)">
                            <input type="hidden" name="account_id" value="${account.id || ''}">
                            
                            <!-- Account Name -->
                            <div class="voip-form-group">
                                <label for="account_name">
                                    Account Name
                                    <span class="voip-required">*</span>
                                    <a href="#" class="voip-help-link" title="Nome identificador da sua conta VoIP">
                                        <i class="fas fa-question-circle"></i>
                                    </a>
                                </label>
                                <input type="text" 
                                       id="account_name" 
                                       name="account_name" 
                                       class="voip-input" 
                                       placeholder="Ex: Minha Conta VoIP"
                                       value="${account.account_name || ''}"
                                       required>
                            </div>
                            
                            <!-- SIP Server -->
                            <div class="voip-form-group">
                                <label for="sip_server">
                                    SIP Server
                                    <span class="voip-required">*</span>
                                    <a href="#" class="voip-help-link" title="Endereço do servidor SIP do seu provedor">
                                        <i class="fas fa-question-circle"></i>
                                    </a>
                                </label>
                                <input type="text" 
                                       id="sip_server" 
                                       name="sip_server" 
                                       class="voip-input" 
                                       placeholder="Ex: sip.provedor.com.br"
                                       value="${account.sip_server || ''}"
                                       required>
                            </div>
                            
                            <!-- SIP Proxy -->
                            <div class="voip-form-group">
                                <label for="sip_proxy">
                                    SIP Proxy
                                    <a href="#" class="voip-help-link" title="Servidor proxy SIP (opcional)">
                                        <i class="fas fa-question-circle"></i>
                                    </a>
                                </label>
                                <input type="text" 
                                       id="sip_proxy" 
                                       name="sip_proxy" 
                                       class="voip-input" 
                                       placeholder="Ex: proxy.provedor.com.br"
                                       value="${account.sip_proxy || ''}">
                            </div>
                            
                            <!-- Username -->
                            <div class="voip-form-group">
                                <label for="username">
                                    Username
                                    <span class="voip-required">*</span>
                                    <a href="#" class="voip-help-link" title="Nome de usuário ou ramal">
                                        <i class="fas fa-question-circle"></i>
                                    </a>
                                </label>
                                <input type="text" 
                                       id="username" 
                                       name="username" 
                                       class="voip-input" 
                                       placeholder="Ex: 1001"
                                       value="${account.sip_username || ''}"
                                       required>
                            </div>
                            
                            <!-- Domain -->
                            <div class="voip-form-group">
                                <label for="domain">
                                    Domain
                                    <span class="voip-required">*</span>
                                    <a href="#" class="voip-help-link" title="Domínio SIP">
                                        <i class="fas fa-question-circle"></i>
                                    </a>
                                </label>
                                <input type="text" 
                                       id="domain" 
                                       name="domain" 
                                       class="voip-input" 
                                       placeholder="Ex: provedor.com.br"
                                       value="${account.sip_domain || ''}"
                                       required>
                            </div>
                            
                            <!-- Login -->
                            <div class="voip-form-group">
                                <label for="login">
                                    Login (Auth ID)
                                    <a href="#" class="voip-help-link" title="ID de autenticação (deixe vazio para usar o username)">
                                        <i class="fas fa-question-circle"></i>
                                    </a>
                                </label>
                                <input type="text" 
                                       id="login" 
                                       name="login" 
                                       class="voip-input" 
                                       placeholder="Ex: auth_1001"
                                       value="${account.auth_id || ''}">
                            </div>
                            
                            <!-- Password -->
                            <div class="voip-form-group">
                                <label for="password">
                                    Password
                                    <span class="voip-required">*</span>
                                </label>
                                <div class="voip-password-group">
                                    <input type="password" 
                                           id="password" 
                                           name="password" 
                                           class="voip-input" 
                                           placeholder="${account.id ? '••••••••' : 'Digite a senha'}"
                                           ${account.id ? '' : 'required'}>
                                    <button type="button" 
                                            class="voip-toggle-password" 
                                            onclick="voipIntegration.togglePasswordVisibility('password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                ${account.id ? '<small class="voip-help-text">Deixe em branco para manter a senha atual</small>' : ''}
                            </div>
                            
                            <!-- Display Name -->
                            <div class="voip-form-group">
                                <label for="display_name">
                                    Display Name
                                    <a href="#" class="voip-help-link" title="Nome exibido nas chamadas">
                                        <i class="fas fa-question-circle"></i>
                                    </a>
                                </label>
                                <input type="text" 
                                       id="display_name" 
                                       name="display_name" 
                                       class="voip-input" 
                                       placeholder="Ex: João Silva"
                                       value="${account.display_name || ''}">
                            </div>
                            
                            <!-- Transport -->
                            <div class="voip-form-group">
                                <label for="transport">
                                    Transport
                                    <a href="#" class="voip-help-link" title="Protocolo de transporte">
                                        <i class="fas fa-question-circle"></i>
                                    </a>
                                </label>
                                <select id="transport" name="transport" class="voip-select">
                                    <option value="udp" ${account.transport === 'udp' ? 'selected' : ''}>UDP</option>
                                    <option value="tcp" ${account.transport === 'tcp' ? 'selected' : ''}>TCP</option>
                                    <option value="tls" ${account.transport === 'tls' ? 'selected' : ''}>TLS</option>
                                </select>
                            </div>
                            
                            <!-- Checkboxes -->
                            <div class="voip-form-group">
                                <label class="voip-checkbox-label">
                                    <input type="checkbox" 
                                           name="publish_presence" 
                                           ${account.publish_presence !== 0 ? 'checked' : ''}>
                                    <span>Publish Presence</span>
                                </label>
                            </div>
                            
                            <div class="voip-form-group">
                                <label class="voip-checkbox-label">
                                    <input type="checkbox" 
                                           name="ice" 
                                           ${account.ice !== 0 ? 'checked' : ''}>
                                    <span>Enable ICE</span>
                                </label>
                            </div>
                            
                            <!-- Buttons -->
                            <div class="voip-dialog-footer">
                                <button type="submit" class="voip-btn voip-btn-primary">
                                    <i class="fas fa-save"></i>
                                    Salvar
                                </button>
                                <button type="button" class="voip-btn voip-btn-secondary" onclick="this.closest('.voip-modal').remove()">
                                    <i class="fas fa-times"></i>
                                    Cancelar
                                </button>
                                ${account.id ? `
                                <button type="button" class="voip-btn voip-btn-danger" onclick="voipIntegration.deleteAccount(${account.id})">
                                    <i class="fas fa-trash"></i>
                                    Excluir Conta
                                </button>
                                ` : ''}
                            </div>
                        </form>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            setTimeout(() => modal.classList.add('show'), 10);
            
        } catch (error) {
            console.error('[VoIP Integration] Erro ao carregar formulário:', error);
            this.showNotification('Erro ao carregar formulário de configuração', 'error');
        }
    }
    
    /**
     * Salvar conta via modal
     */
    async saveAccountModal(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        
        try {
            const response = await fetch('/api/voip/save_account.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification('Conta salva com sucesso!', 'success');
                
                // Fechar modal
                const modal = form.closest('.voip-modal');
                if (modal) {
                    modal.remove();
                }
                
                // Reinicializar VoIP
                await this.init();
                
            } else {
                this.showNotification('Erro: ' + data.message, 'error');
            }
            
        } catch (error) {
            console.error('[VoIP Integration] Erro ao salvar conta:', error);
            this.showNotification('Erro ao salvar conta', 'error');
        }
    }
    
    /**
     * Excluir conta
     */
    async deleteAccount(accountId) {
        if (!confirm('Tem certeza que deseja excluir esta conta VoIP?')) {
            return;
        }
        
        try {
            const response = await fetch('/api/voip/delete_account.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ account_id: accountId })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification('Conta excluída com sucesso!', 'success');
                
                // Fechar modal
                const modal = document.querySelector('.voip-account-modal');
                if (modal) {
                    modal.remove();
                }
                
                // Resetar estado
                this.hasAccount = false;
                this.isInitialized = false;
                this.voipClient = null;
                
            } else {
                this.showNotification('Erro: ' + data.message, 'error');
            }
            
        } catch (error) {
            console.error('[VoIP Integration] Erro ao excluir conta:', error);
            this.showNotification('Erro ao excluir conta', 'error');
        }
    }
    
    /**
     * Toggle visibilidade da senha
     */
    togglePasswordVisibility(inputId) {
        const input = document.getElementById(inputId);
        const button = input.nextElementSibling;
        const icon = button.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
    
    /**
     * Mostrar modal de chamada sainte
     */
    showOutgoingCallModal(phoneNumber, contactName) {
        const modal = document.createElement('div');
        modal.id = 'voip-call-modal';
        modal.className = 'voip-modal';
        modal.innerHTML = `
            <div class="voip-modal-content">
                <div class="voip-call-header">
                    <i class="fas fa-phone-alt voip-call-icon calling"></i>
                    <h3 class="voip-call-name">${contactName || phoneNumber}</h3>
                    <p class="voip-call-status">Chamando...</p>
                    ${contactName ? `<p class="voip-call-number">${phoneNumber}</p>` : ''}
                </div>
                
                <div class="voip-call-timer" style="display: none;">
                    <span id="voip-call-duration">00:00</span>
                </div>
                
                <div class="voip-call-controls">
                    <button class="voip-btn voip-btn-mute" onclick="voipIntegration.toggleMute()" title="Mute">
                        <i class="fas fa-microphone"></i>
                    </button>
                    
                    <button class="voip-btn voip-btn-hold" onclick="voipIntegration.toggleHold()" title="Hold">
                        <i class="fas fa-pause"></i>
                    </button>
                    
                    <button class="voip-btn voip-btn-hangup" onclick="voipIntegration.hangup()" title="Desligar">
                        <i class="fas fa-phone-slash"></i>
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        this.currentCallModal = modal;
        
        // Animar entrada
        setTimeout(() => modal.classList.add('show'), 10);
    }
    
    /**
     * Mostrar modal de chamada recebida
     */
    showIncomingCallModal(call) {
        const modal = document.createElement('div');
        modal.id = 'voip-call-modal';
        modal.className = 'voip-modal incoming';
        modal.innerHTML = `
            <div class="voip-modal-content">
                <div class="voip-call-header">
                    <i class="fas fa-phone-alt voip-call-icon ringing"></i>
                    <h3 class="voip-call-name">${call.callerName || 'Desconhecido'}</h3>
                    <p class="voip-call-status">Chamada recebida</p>
                    <p class="voip-call-number">${call.number}</p>
                </div>
                
                <div class="voip-call-controls incoming">
                    <button class="voip-btn voip-btn-answer" onclick="voipIntegration.answer()" title="Atender">
                        <i class="fas fa-phone"></i>
                        <span>Atender</span>
                    </button>
                    
                    <button class="voip-btn voip-btn-reject" onclick="voipIntegration.hangup()" title="Rejeitar">
                        <i class="fas fa-phone-slash"></i>
                        <span>Rejeitar</span>
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        this.currentCallModal = modal;
        
        // Animar entrada
        setTimeout(() => modal.classList.add('show'), 10);
        
        // Tocar som de chamada (opcional)
        this.playRingtone();
    }
    
    /**
     * Atualizar modal de chamada
     */
    updateCallModal(status) {
        if (!this.currentCallModal) return;
        
        const statusElement = this.currentCallModal.querySelector('.voip-call-status');
        const timerElement = this.currentCallModal.querySelector('.voip-call-timer');
        const icon = this.currentCallModal.querySelector('.voip-call-icon');
        
        if (status === 'active') {
            if (statusElement) statusElement.textContent = 'Em chamada';
            if (icon) {
                icon.classList.remove('calling', 'ringing');
                icon.classList.add('active');
            }
            
            // Mostrar timer
            if (timerElement) {
                timerElement.style.display = 'block';
                this.startCallTimer();
            }
            
            // Parar ringtone
            this.stopRingtone();
            
            // Atualizar controles para chamada ativa
            const controls = this.currentCallModal.querySelector('.voip-call-controls');
            if (controls && controls.classList.contains('incoming')) {
                controls.innerHTML = `
                    <button class="voip-btn voip-btn-mute" onclick="voipIntegration.toggleMute()" title="Mute">
                        <i class="fas fa-microphone"></i>
                    </button>
                    
                    <button class="voip-btn voip-btn-hold" onclick="voipIntegration.toggleHold()" title="Hold">
                        <i class="fas fa-pause"></i>
                    </button>
                    
                    <button class="voip-btn voip-btn-hangup" onclick="voipIntegration.hangup()" title="Desligar">
                        <i class="fas fa-phone-slash"></i>
                    </button>
                `;
                controls.classList.remove('incoming');
            }
        }
    }
    
    /**
     * Fechar modal de chamada
     */
    closeCallModal() {
        if (this.currentCallModal) {
            this.currentCallModal.classList.remove('show');
            setTimeout(() => {
                if (this.currentCallModal && this.currentCallModal.parentNode) {
                    this.currentCallModal.parentNode.removeChild(this.currentCallModal);
                }
                this.currentCallModal = null;
            }, 300);
        }
        
        this.stopCallTimer();
        this.stopRingtone();
    }
    
    /**
     * Atender chamada
     */
    async answer() {
        try {
            await this.voipClient.answer();
            this.updateCallModal('active');
        } catch (error) {
            console.error('[VoIP Integration] Erro ao atender:', error);
            this.showNotification('Erro ao atender chamada', 'error');
        }
    }
    
    /**
     * Desligar chamada
     */
    async hangup() {
        try {
            await this.voipClient.hangup();
            this.closeCallModal();
        } catch (error) {
            console.error('[VoIP Integration] Erro ao desligar:', error);
            this.closeCallModal();
        }
    }
    
    /**
     * Toggle mute
     */
    toggleMute() {
        const isMuted = this.voipClient.toggleMute();
        const btn = document.querySelector('.voip-btn-mute');
        
        if (btn) {
            const icon = btn.querySelector('i');
            if (isMuted) {
                icon.classList.remove('fa-microphone');
                icon.classList.add('fa-microphone-slash');
                btn.classList.add('active');
            } else {
                icon.classList.remove('fa-microphone-slash');
                icon.classList.add('fa-microphone');
                btn.classList.remove('active');
            }
        }
    }
    
    /**
     * Toggle hold
     */
    async toggleHold() {
        const isOnHold = await this.voipClient.toggleHold();
        const btn = document.querySelector('.voip-btn-hold');
        
        if (btn) {
            const icon = btn.querySelector('i');
            if (isOnHold) {
                icon.classList.remove('fa-pause');
                icon.classList.add('fa-play');
                btn.classList.add('active');
            } else {
                icon.classList.remove('fa-play');
                icon.classList.add('fa-pause');
                btn.classList.remove('active');
            }
        }
    }
    
    /**
     * Iniciar timer de chamada
     */
    startCallTimer() {
        let seconds = 0;
        
        this.callTimerInterval = setInterval(() => {
            seconds++;
            const minutes = Math.floor(seconds / 60);
            const secs = seconds % 60;
            
            const timerElement = document.getElementById('voip-call-duration');
            if (timerElement) {
                timerElement.textContent = 
                    `${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
            }
        }, 1000);
    }
    
    /**
     * Parar timer de chamada
     */
    stopCallTimer() {
        if (this.callTimerInterval) {
            clearInterval(this.callTimerInterval);
            this.callTimerInterval = null;
        }
    }
    
    /**
     * Tocar ringtone
     */
    playRingtone() {
        // Implementar som de chamada (opcional)
        console.log('[VoIP Integration] Tocando ringtone...');
    }
    
    /**
     * Parar ringtone
     */
    stopRingtone() {
        console.log('[VoIP Integration] Parando ringtone...');
    }
    
    /**
     * Mostrar indicador de status
     */
    showStatusIndicator(status) {
        let indicator = document.getElementById('voip-status-indicator');
        
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'voip-status-indicator';
            indicator.className = 'voip-status-indicator';
            indicator.innerHTML = `
                <i class="fas fa-phone"></i>
                <span class="voip-status-dot"></span>
            `;
            
            // Adicionar ao header ou sidebar
            const header = document.querySelector('.header') || document.querySelector('header');
            if (header) {
                header.appendChild(indicator);
            }
        }
        
        indicator.className = `voip-status-indicator ${status}`;
        indicator.title = status === 'online' ? 'VoIP Online' : 'VoIP Offline';
    }
    
    /**
     * Mostrar notificação
     */
    showNotification(message, type = 'info') {
        console.log(`[VoIP Integration] ${type.toUpperCase()}: ${message}`);
        
        // Usar sistema de notificações existente se disponível
        if (typeof showToast === 'function') {
            showToast(message, type);
        } else {
            alert(message);
        }
    }
}

// Criar instância global
window.voipIntegration = new VoIPChatIntegration();

// Inicializar quando o DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        voipIntegration.init();
    });
} else {
    voipIntegration.init();
}
