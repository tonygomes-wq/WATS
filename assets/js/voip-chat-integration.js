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
            
            if (data.success && data.has_account && data.provider_configured) {
                this.hasAccount = true;
                
                // Criar cliente WebRTC
                this.voipClient = new VoIPWebRTCClient();
                
                // Configurar callbacks
                this.setupCallbacks();
                
                // Inicializar cliente
                await this.voipClient.init();
                
                // Conectar ao servidor
                await this.voipClient.connect();
                
                this.isInitialized = true;
                
                console.log('[VoIP Integration] Inicializado com sucesso');
                
                // Mostrar indicador de status
                this.showStatusIndicator('online');
                
            } else {
                console.log('[VoIP Integration] Conta VoIP não configurada');
                this.hasAccount = false;
            }
            
        } catch (error) {
            console.error('[VoIP Integration] Erro ao inicializar:', error);
            this.showStatusIndicator('offline');
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
        if (!this.isInitialized) {
            this.showNotification('VoIP não está inicializado', 'error');
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
