/**
 * VoIP Dialer - JavaScript
 * Interface de discagem estilo MicroSIP
 */

class VoIPDialer {
    constructor() {
        this.currentNumber = '';
        this.currentCall = null;
        this.isMuted = false;
        this.isOnHold = false;
        this.callTimer = null;
        this.callDuration = 0;
        this.voipClient = null;
        this.inputVolume = 80;
        this.outputVolume = 80;
        this.isInputMuted = false;
        this.isOutputMuted = false;
        
        this.init();
    }
    
    async init() {
        console.log('[VoIP Dialer] Inicializando...');
        
        // Inicializar cliente WebRTC
        if (typeof VoIPWebRTCClient !== 'undefined') {
            this.voipClient = new VoIPWebRTCClient();
            await this.voipClient.init();
            this.setupCallbacks();
        }
        
        // Carregar histórico de números discados
        this.loadDialedHistory();
        
        // Verificar status da conexão
        this.checkConnectionStatus();
        
        // Event listeners
        this.setupEventListeners();
    }
    
    setupCallbacks() {
        if (!this.voipClient) return;
        
        this.voipClient.onRegistered = () => {
            this.updateStatus('online', 'Conectado');
        };
        
        this.voipClient.onUnregistered = () => {
            this.updateStatus('offline', 'Desconectado');
        };
        
        this.voipClient.onIncomingCall = (call) => {
            this.handleIncomingCall(call);
        };
        
        this.voipClient.onCallAnswered = () => {
            this.updateCallStatus('active');
        };
        
        this.voipClient.onCallEnded = () => {
            this.endCall();
        };
    }
    
    setupEventListeners() {
        // Input de número
        const numberInput = document.getElementById('voip-number-input');
        if (numberInput) {
            numberInput.addEventListener('input', (e) => {
                this.currentNumber = e.target.value;
                this.searchContact(this.currentNumber);
            });
            
            numberInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.makeCall();
                }
            });
        }
        
        // Sliders de volume
        document.getElementById('voip-input-volume')?.addEventListener('input', (e) => {
            this.setVolume('input', e.target.value);
        });
        
        document.getElementById('voip-output-volume')?.addEventListener('input', (e) => {
            this.setVolume('output', e.target.value);
        });
    }
    
    // Discar tecla
    dialKey(key) {
        this.currentNumber += key;
        document.getElementById('voip-number-input').value = this.currentNumber;
        
        // Tocar tom DTMF
        this.playDTMF(key);
        
        // Se em chamada, enviar DTMF
        if (this.currentCall) {
            this.sendDTMF(key);
        }
        
        // Buscar contato
        this.searchContact(this.currentNumber);
    }
    
    // Limpar número
    clearNumber() {
        this.currentNumber = '';
        document.getElementById('voip-number-input').value = '';
        document.getElementById('voip-contact-name').style.display = 'none';
    }
    
    // Fazer chamada
    async makeCall() {
        if (!this.currentNumber) {
            this.showNotification('Digite um número', 'warning');
            return;
        }
        
        if (!this.voipClient || !this.voipClient.isConnected) {
            this.showNotification('VoIP não está conectado', 'error');
            return;
        }
        
        try {
            console.log('[VoIP Dialer] Fazendo chamada para:', this.currentNumber);
            
            // Salvar no histórico
            this.saveToHistory(this.currentNumber);
            
            // Fazer chamada
            this.currentCall = await this.voipClient.call(this.currentNumber);
            
            // Mostrar controles de chamada
            this.showCallControls();
            this.startCallTimer();
            
        } catch (error) {
            console.error('[VoIP Dialer] Erro ao fazer chamada:', error);
            this.showNotification('Erro ao fazer chamada: ' + error.message, 'error');
        }
    }
    
    // Fazer chamada de vídeo
    async makeVideoCall() {
        if (!this.currentNumber) {
            this.showNotification('Digite um número', 'warning');
            return;
        }
        
        try {
            this.currentCall = await this.voipClient.call(this.currentNumber, true);
            this.showCallControls();
            this.startCallTimer();
        } catch (error) {
            console.error('[VoIP Dialer] Erro ao fazer chamada de vídeo:', error);
            this.showNotification('Erro ao fazer chamada de vídeo', 'error');
        }
    }
    
    // Enviar mensagem
    sendMessage() {
        if (!this.currentNumber) {
            this.showNotification('Digite um número', 'warning');
            return;
        }
        
        // Redirecionar para chat
        window.location.href = `/chat.php?number=${encodeURIComponent(this.currentNumber)}`;
    }
    
    // Desligar chamada
    async hangupCall() {
        if (!this.currentCall) return;
        
        try {
            await this.voipClient.hangup();
            this.endCall();
        } catch (error) {
            console.error('[VoIP Dialer] Erro ao desligar:', error);
            this.endCall();
        }
    }
    
    // Toggle mute
    async toggleMute() {
        this.isMuted = !this.isMuted;
        
        const btn = document.getElementById('voip-btn-mute');
        const icon = btn?.querySelector('i');
        
        if (this.isMuted) {
            icon?.classList.replace('fa-microphone', 'fa-microphone-slash');
            btn?.classList.add('active');
        } else {
            icon?.classList.replace('fa-microphone-slash', 'fa-microphone');
            btn?.classList.remove('active');
        }
        
        if (this.voipClient) {
            await this.voipClient.toggleMute();
        }
    }
    
    // Toggle hold
    async toggleHold() {
        this.isOnHold = !this.isOnHold;
        
        const btn = document.getElementById('voip-btn-hold');
        const icon = btn?.querySelector('i');
        
        if (this.isOnHold) {
            icon?.classList.replace('fa-pause', 'fa-play');
            btn?.classList.add('active');
        } else {
            icon?.classList.replace('fa-play', 'fa-pause');
            btn?.classList.remove('active');
        }
        
        if (this.voipClient) {
            await this.voipClient.toggleHold();
        }
    }
    
    // Abrir transferência
    openTransfer() {
        // Implementar modal de transferência
        this.showNotification('Transferência em desenvolvimento', 'info');
    }
    
    // Controles de volume
    setVolume(type, value) {
        if (type === 'input') {
            this.inputVolume = value;
            if (this.voipClient) {
                this.voipClient.setInputVolume(value / 100);
            }
        } else {
            this.outputVolume = value;
            if (this.voipClient) {
                this.voipClient.setOutputVolume(value / 100);
            }
        }
    }
    
    adjustVolume(type, delta) {
        const slider = document.getElementById(`voip-${type}-volume`);
        if (slider) {
            const newValue = Math.max(0, Math.min(100, parseInt(slider.value) + delta));
            slider.value = newValue;
            this.setVolume(type, newValue);
        }
    }
    
    toggleMuteInput() {
        this.isInputMuted = !this.isInputMuted;
        const btn = document.getElementById('voip-mute-input');
        const icon = btn?.querySelector('i');
        
        if (this.isInputMuted) {
            icon?.classList.replace('fa-microphone', 'fa-microphone-slash');
            btn?.classList.add('muted');
            this.setVolume('input', 0);
        } else {
            icon?.classList.replace('fa-microphone-slash', 'fa-microphone');
            btn?.classList.remove('muted');
            this.setVolume('input', this.inputVolume);
        }
    }
    
    toggleMuteOutput() {
        this.isOutputMuted = !this.isOutputMuted;
        const btn = document.getElementById('voip-mute-output');
        const icon = btn?.querySelector('i');
        
        if (this.isOutputMuted) {
            icon?.classList.replace('fa-volume-up', 'fa-volume-mute');
            btn?.classList.add('muted');
            this.setVolume('output', 0);
        } else {
            icon?.classList.replace('fa-volume-mute', 'fa-volume-up');
            btn?.classList.remove('muted');
            this.setVolume('output', this.outputVolume);
        }
    }
    
    // Botões inferiores
    toggleDND() {
        const btn = document.getElementById('voip-btn-dnd');
        btn?.classList.toggle('active');
        
        const isActive = btn?.classList.contains('active');
        this.showNotification(isActive ? 'DND ativado' : 'DND desativado', 'info');
    }
    
    toggleForwarding() {
        const btn = document.getElementById('voip-btn-fwd');
        btn?.classList.toggle('active');
        
        const isActive = btn?.classList.contains('active');
        this.showNotification(isActive ? 'Encaminhamento ativado' : 'Encaminhamento desativado', 'info');
    }
    
    toggleAutoAnswer() {
        const btn = document.getElementById('voip-btn-aa');
        btn?.classList.toggle('active');
        
        const isActive = btn?.classList.contains('active');
        this.showNotification(isActive ? 'Auto-resposta ativada' : 'Auto-resposta desativada', 'info');
    }
    
    toggleRecording() {
        const btn = document.getElementById('voip-btn-rec');
        btn?.classList.toggle('active');
        
        const isActive = btn?.classList.contains('active');
        this.showNotification(isActive ? 'Gravação iniciada' : 'Gravação parada', 'info');
    }
    
    openConference() {
        this.showNotification('Conferência em desenvolvimento', 'info');
    }
    
    // Navegação
    openDialer() {
        window.location.href = '/voip_dialer.php';
    }
    
    openCalls() {
        this.showNotification('Histórico de chamadas em desenvolvimento', 'info');
    }
    
    openContacts() {
        this.showNotification('Contatos em desenvolvimento', 'info');
    }
    
    openSettings() {
        window.location.href = '/voip_settings.php';
    }
    
    // Controles de chamada
    showCallControls() {
        document.getElementById('voip-call-controls')?.style.setProperty('display', 'block');
        document.getElementById('voip-action-buttons')?.style.setProperty('display', 'none');
    }
    
    hideCallControls() {
        document.getElementById('voip-call-controls')?.style.setProperty('display', 'none');
        document.getElementById('voip-action-buttons')?.style.setProperty('display', 'flex');
    }
    
    startCallTimer() {
        this.callDuration = 0;
        this.callTimer = setInterval(() => {
            this.callDuration++;
            const minutes = Math.floor(this.callDuration / 60);
            const seconds = this.callDuration % 60;
            const timerEl = document.getElementById('voip-call-timer');
            if (timerEl) {
                timerEl.textContent = 
                    `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            }
        }, 1000);
    }
    
    stopCallTimer() {
        if (this.callTimer) {
            clearInterval(this.callTimer);
            this.callTimer = null;
        }
    }
    
    endCall() {
        this.currentCall = null;
        this.isMuted = false;
        this.isOnHold = false;
        this.hideCallControls();
        this.stopCallTimer();
    }
    
    // Chamada recebida
    handleIncomingCall(call) {
        // Implementar modal de chamada recebida
        console.log('[VoIP Dialer] Chamada recebida:', call);
    }
    
    // Status
    updateStatus(status, text) {
        const dot = document.getElementById('voip-status-dot');
        const statusText = document.getElementById('voip-status-text');
        const indicator = document.querySelector('.voip-status-indicator');
        
        if (dot) {
            dot.className = 'fas fa-circle';
            dot.style.color = status === 'online' ? '#10b981' : '#999';
        }
        
        if (statusText) {
            statusText.textContent = text;
        }
        
        if (indicator) {
            indicator.classList.toggle('online', status === 'online');
        }
    }
    
    async checkConnectionStatus() {
        try {
            const response = await fetch('/api/voip/test_connection.php');
            const data = await response.json();
            
            if (data.success && data.connected) {
                this.updateStatus('online', 'Conectado');
            } else {
                this.updateStatus('offline', 'Desconectado');
            }
        } catch (error) {
            this.updateStatus('offline', 'Erro de conexão');
        }
    }
    
    // DTMF
    playDTMF(key) {
        // Implementar reprodução de tom DTMF
        console.log('[VoIP Dialer] DTMF:', key);
    }
    
    async sendDTMF(key) {
        if (!this.currentCall) return;
        
        try {
            await fetch('/api/voip/send_dtmf.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    call_id: this.currentCall,
                    digit: key
                })
            });
        } catch (error) {
            console.error('[VoIP Dialer] Erro ao enviar DTMF:', error);
        }
    }
    
    // Buscar contato
    async searchContact(number) {
        if (!number || number.length < 3) {
            document.getElementById('voip-contact-name').style.display = 'none';
            return;
        }
        
        try {
            const response = await fetch(`/api/search_contacts.php?q=${encodeURIComponent(number)}`);
            const data = await response.json();
            
            if (data.length > 0) {
                const contact = data[0];
                document.getElementById('voip-contact-name-text').textContent = contact.name;
                document.getElementById('voip-contact-name').style.display = 'flex';
            } else {
                document.getElementById('voip-contact-name').style.display = 'none';
            }
        } catch (error) {
            console.error('[VoIP Dialer] Erro ao buscar contato:', error);
        }
    }
    
    // Histórico
    loadDialedHistory() {
        // Carregar do localStorage
        const history = JSON.parse(localStorage.getItem('voip_dialed_history') || '[]');
        console.log('[VoIP Dialer] Histórico carregado:', history);
    }
    
    saveToHistory(number) {
        const history = JSON.parse(localStorage.getItem('voip_dialed_history') || '[]');
        
        // Remover duplicatas
        const filtered = history.filter(n => n !== number);
        
        // Adicionar no início
        filtered.unshift(number);
        
        // Limitar a 50 números
        const limited = filtered.slice(0, 50);
        
        localStorage.setItem('voip_dialed_history', JSON.stringify(limited));
    }
    
    // Notificações
    showNotification(message, type = 'info') {
        console.log(`[VoIP Dialer] ${type.toUpperCase()}: ${message}`);
        
        // Implementar toast notification
        alert(message);
    }
}

// Inicializar
let voipDialer;
document.addEventListener('DOMContentLoaded', () => {
    voipDialer = new VoIPDialer();
    window.voipDialer = voipDialer;
});

// Funções globais para onclick
function dialKey(key) {
    window.voipDialer?.dialKey(key);
}

function clearNumber() {
    window.voipDialer?.clearNumber();
}

function makeCall() {
    window.voipDialer?.makeCall();
}

function makeVideoCall() {
    window.voipDialer?.makeVideoCall();
}

function sendMessage() {
    window.voipDialer?.sendMessage();
}

function hangupCall() {
    window.voipDialer?.hangupCall();
}

function toggleMute() {
    window.voipDialer?.toggleMute();
}

function toggleHold() {
    window.voipDialer?.toggleHold();
}

function openTransfer() {
    window.voipDialer?.openTransfer();
}

function setVolume(type, value) {
    window.voipDialer?.setVolume(type, value);
}

function adjustVolume(type, delta) {
    window.voipDialer?.adjustVolume(type, delta);
}

function toggleMuteInput() {
    window.voipDialer?.toggleMuteInput();
}

function toggleMuteOutput() {
    window.voipDialer?.toggleMuteOutput();
}

function toggleDND() {
    window.voipDialer?.toggleDND();
}

function toggleForwarding() {
    window.voipDialer?.toggleForwarding();
}

function toggleAutoAnswer() {
    window.voipDialer?.toggleAutoAnswer();
}

function toggleRecording() {
    window.voipDialer?.toggleRecording();
}

function openConference() {
    window.voipDialer?.openConference();
}

function openDialer() {
    window.voipDialer?.openDialer();
}

function openCalls() {
    window.voipDialer?.openCalls();
}

function openContacts() {
    window.voipDialer?.openContacts();
}

function openSettings() {
    window.voipDialer?.openSettings();
}
