/**
 * WATS VoIP Client - WebRTC/SIP.js Integration
 * Inspirado no layout do MicroSIP
 */

class WATSVoIPClient {
    constructor(config) {
        this.config = config;
        this.simpleUser = null;
        this.currentCall = null;
        this.activeCalls = new Map();
        this.isRegistered = false;
        this.audioContext = null;
        this.localStream = null;
        
        this.init();
    }
    
    /**
     * Inicializar cliente VoIP
     */
    async init() {
        try {
            // Criar contexto de áudio
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
            
            // Configurar SIP.js SimpleUser
            const server = `wss://${this.config.domain}:8083`;
            const aor = `sip:${this.config.extension}@${this.config.domain}`;
            
            this.simpleUser = new SimpleUser(server, {
                aor: aor,
                media: {
                    constraints: {
                        audio: true,
                        video: false
                    },
                    remote: {
                        audio: document.getElementById('remoteAudio')
                    }
                },
                userAgentOptions: {
                    authorizationUsername: this.config.extension,
                    authorizationPassword: this.config.password,
                    displayName: this.config.displayName || this.config.extension,
                    logLevel: 'warn',
                    sessionDescriptionHandlerFactoryOptions: {
                        peerConnectionConfiguration: {
                            iceServers: [
                                { urls: 'stun:stun.l.google.com:19302' }
                            ]
                        }
                    }
                },
                delegate: {
                    onCallReceived: () => this.handleIncomingCall(),
                    onCallCreated: () => this.handleCallCreated(),
                    onCallAnswered: () => this.handleCallAnswered(),
                    onCallHangup: () => this.handleCallHangup(),
                    onRegistered: () => this.handleRegistered(),
                    onUnregistered: () => this.handleUnregistered(),
                    onServerConnect: () => this.handleServerConnect(),
                    onServerDisconnect: () => this.handleServerDisconnect()
                }
            });
            
            console.log('VoIP Client initialized');
            
        } catch (error) {
            console.error('Failed to initialize VoIP client:', error);
            this.showError('Erro ao inicializar cliente VoIP');
        }
    }
    
    /**
     * Conectar e registrar
     */
    async connect() {
        try {
            await this.simpleUser.connect();
            await this.simpleUser.register();
            console.log('Connected and registered');
        } catch (error) {
            console.error('Connection failed:', error);
            this.showError('Falha ao conectar');
        }
    }
    
    /**
     * Desconectar
     */
    async disconnect() {
        try {
            await this.simpleUser.unregister();
            await this.simpleUser.disconnect();
            console.log('Disconnected');
        } catch (error) {
            console.error('Disconnect failed:', error);
        }
    }
    
    /**
     * Fazer chamada
     */
    async call(destination) {
        try {
            if (!this.isRegistered) {
                throw new Error('Not registered');
            }
            
            const target = `sip:${destination}@${this.config.domain}`;
            await this.simpleUser.call(target);
            
            // Registrar chamada no backend
            await this.registerCallInBackend({
                direction: 'outbound',
                callee_number: destination
            });
            
            console.log(`Calling ${destination}`);
            
        } catch (error) {
            console.error('Call failed:', error);
            this.showError('Falha ao fazer chamada');
        }
    }
    
    /**
     * Atender chamada
     */
    async answer() {
        try {
            await this.simpleUser.answer();
            console.log('Call answered');
        } catch (error) {
            console.error('Answer failed:', error);
            this.showError('Falha ao atender');
        }
    }
    
    /**
     * Desligar chamada
     */
    async hangup() {
        try {
            await this.simpleUser.hangup();
            console.log('Call ended');
        } catch (error) {
            console.error('Hangup failed:', error);
        }
    }
    
    /**
     * Hold/Unhold
     */
    async toggleHold() {
        try {
            if (this.simpleUser.isHeld()) {
                await this.simpleUser.unhold();
                console.log('Call resumed');
            } else {
                await this.simpleUser.hold();
                console.log('Call on hold');
            }
        } catch (error) {
            console.error('Hold toggle failed:', error);
        }
    }
    
    /**
     * Enviar DTMF
     */
    sendDTMF(digit) {
        try {
            if (this.simpleUser && this.simpleUser.session) {
                this.simpleUser.session.sessionDescriptionHandler.sendDtmf(digit);
                console.log(`DTMF sent: ${digit}`);
                
                // Tocar som DTMF localmente
                this.playDTMFTone(digit);
            }
        } catch (error) {
            console.error('DTMF failed:', error);
        }
    }
    
    /**
     * Tocar tom DTMF
     */
    playDTMFTone(digit) {
        const frequencies = {
            '1': [697, 1209], '2': [697, 1336], '3': [697, 1477],
            '4': [770, 1209], '5': [770, 1336], '6': [770, 1477],
            '7': [852, 1209], '8': [852, 1336], '9': [852, 1477],
            '*': [941, 1209], '0': [941, 1336], '#': [941, 1477]
        };
        
        if (!frequencies[digit]) return;
        
        const [freq1, freq2] = frequencies[digit];
        const duration = 0.1;
        
        const oscillator1 = this.audioContext.createOscillator();
        const oscillator2 = this.audioContext.createOscillator();
        const gainNode = this.audioContext.createGain();
        
        oscillator1.frequency.value = freq1;
        oscillator2.frequency.value = freq2;
        gainNode.gain.value = 0.1;
        
        oscillator1.connect(gainNode);
        oscillator2.connect(gainNode);
        gainNode.connect(this.audioContext.destination);
        
        oscillator1.start();
        oscillator2.start();
        
        setTimeout(() => {
            oscillator1.stop();
            oscillator2.stop();
        }, duration * 1000);
    }
    
    /**
     * Handlers de eventos
     */
    handleIncomingCall() {
        console.log('Incoming call');
        this.showIncomingCallUI();
        this.playRingtone();
    }
    
    handleCallCreated() {
        console.log('Call created');
        this.updateUI('calling');
    }
    
    handleCallAnswered() {
        console.log('Call answered');
        this.stopRingtone();
        this.updateUI('active');
        this.startCallTimer();
    }
    
    handleCallHangup() {
        console.log('Call hangup');
        this.stopRingtone();
        this.stopCallTimer();
        this.updateUI('idle');
    }
    
    handleRegistered() {
        console.log('Registered');
        this.isRegistered = true;
        this.updateStatusUI('online');
    }
    
    handleUnregistered() {
        console.log('Unregistered');
        this.isRegistered = false;
        this.updateStatusUI('offline');
    }
    
    handleServerConnect() {
        console.log('Server connected');
    }
    
    handleServerDisconnect() {
        console.log('Server disconnected');
        this.updateStatusUI('offline');
    }
    
    /**
     * Registrar chamada no backend
     */
    async registerCallInBackend(callData) {
        try {
            const response = await fetch('/api/voip/call.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(callData)
            });
            
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Failed to register call:', error);
        }
    }
    
    /**
     * UI Helpers
     */
    updateUI(state) {
        window.dispatchEvent(new CustomEvent('voip:stateChange', { detail: { state } }));
    }
    
    updateStatusUI(status) {
        window.dispatchEvent(new CustomEvent('voip:statusChange', { detail: { status } }));
    }
    
    showIncomingCallUI() {
        window.dispatchEvent(new CustomEvent('voip:incomingCall'));
    }
    
    playRingtone() {
        const audio = document.getElementById('ringtoneAudio');
        if (audio) {
            audio.loop = true;
            audio.play();
        }
    }
    
    stopRingtone() {
        const audio = document.getElementById('ringtoneAudio');
        if (audio) {
            audio.pause();
            audio.currentTime = 0;
        }
    }
    
    startCallTimer() {
        this.callStartTime = Date.now();
        this.callTimerInterval = setInterval(() => {
            const duration = Math.floor((Date.now() - this.callStartTime) / 1000);
            window.dispatchEvent(new CustomEvent('voip:timerUpdate', { detail: { duration } }));
        }, 1000);
    }
    
    stopCallTimer() {
        if (this.callTimerInterval) {
            clearInterval(this.callTimerInterval);
            this.callTimerInterval = null;
        }
    }
    
    showError(message) {
        window.dispatchEvent(new CustomEvent('voip:error', { detail: { message } }));
    }
}

// Exportar para uso global
window.WATSVoIPClient = WATSVoIPClient;
