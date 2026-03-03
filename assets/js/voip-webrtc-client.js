/**
 * VoIP WebRTC Client - WATS
 * Cliente WebRTC para chamadas VoIP via FreeSWITCH
 * 
 * @author Winston (Arquiteto WATS)
 * @date 2026-03-03
 */

class VoIPWebRTCClient {
    constructor() {
        this.ws = null;
        this.pc = null; // RTCPeerConnection
        this.localStream = null;
        this.remoteStream = null;
        this.credentials = null;
        this.currentCall = null;
        this.isRegistered = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        
        // Callbacks
        this.onRegistered = null;
        this.onUnregistered = null;
        this.onIncomingCall = null;
        this.onCallAnswered = null;
        this.onCallEnded = null;
        this.onError = null;
    }
    
    /**
     * Inicializar cliente
     */
    async init() {
        try {
            // Obter credenciais do servidor
            const response = await fetch('/api/voip/get_credentials.php');
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Falha ao obter credenciais');
            }
            
            if (!data.has_account) {
                throw new Error('Conta VoIP não encontrada');
            }
            
            if (!data.provider_configured) {
                throw new Error('Provedor VoIP não configurado');
            }
            
            this.credentials = data.credentials;
            
            console.log('[VoIP] Credenciais obtidas:', {
                extension: this.credentials.extension,
                domain: this.credentials.sip_domain
            });
            
            return true;
            
        } catch (error) {
            console.error('[VoIP] Erro ao inicializar:', error);
            if (this.onError) this.onError(error);
            return false;
        }
    }
    
    /**
     * Conectar ao servidor WebSocket
     */
    async connect() {
        if (!this.credentials) {
            throw new Error('Credenciais não carregadas. Execute init() primeiro.');
        }
        
        return new Promise((resolve, reject) => {
            try {
                console.log('[VoIP] Conectando ao WebSocket:', this.credentials.wss_url);
                
                this.ws = new WebSocket(this.credentials.wss_url);
                
                this.ws.onopen = () => {
                    console.log('[VoIP] WebSocket conectado');
                    this.reconnectAttempts = 0;
                    this.register();
                    resolve();
                };
                
                this.ws.onmessage = (event) => {
                    this.handleMessage(JSON.parse(event.data));
                };
                
                this.ws.onerror = (error) => {
                    console.error('[VoIP] Erro no WebSocket:', error);
                    if (this.onError) this.onError(error);
                    reject(error);
                };
                
                this.ws.onclose = () => {
                    console.log('[VoIP] WebSocket desconectado');
                    this.isRegistered = false;
                    if (this.onUnregistered) this.onUnregistered();
                    
                    // Tentar reconectar
                    if (this.reconnectAttempts < this.maxReconnectAttempts) {
                        this.reconnectAttempts++;
                        console.log(`[VoIP] Tentando reconectar (${this.reconnectAttempts}/${this.maxReconnectAttempts})...`);
                        setTimeout(() => this.connect(), 3000);
                    }
                };
                
            } catch (error) {
                console.error('[VoIP] Erro ao conectar:', error);
                reject(error);
            }
        });
    }
    
    /**
     * Registrar no servidor SIP
     */
    register() {
        if (!this.ws || this.ws.readyState !== WebSocket.OPEN) {
            console.error('[VoIP] WebSocket não está conectado');
            return;
        }
        
        const registerMessage = {
            jsonrpc: '2.0',
            method: 'login',
            params: {
                login: this.credentials.extension,
                passwd: this.credentials.password || '',
                sessid: this.generateSessionId()
            },
            id: this.generateId()
        };
        
        console.log('[VoIP] Enviando registro SIP...');
        this.ws.send(JSON.stringify(registerMessage));
    }
    
    /**
     * Processar mensagens do servidor
     */
    handleMessage(message) {
        console.log('[VoIP] Mensagem recebida:', message);
        
        if (message.result) {
            // Resposta de registro
            if (message.result.message === 'logged in') {
                this.isRegistered = true;
                console.log('[VoIP] Registrado com sucesso!');
                if (this.onRegistered) this.onRegistered();
            }
        }
        
        if (message.method) {
            switch (message.method) {
                case 'verto.invite':
                    this.handleIncomingCall(message.params);
                    break;
                    
                case 'verto.answer':
                    this.handleCallAnswered(message.params);
                    break;
                    
                case 'verto.bye':
                    this.handleCallEnded(message.params);
                    break;
                    
                case 'verto.media':
                    this.handleMediaUpdate(message.params);
                    break;
            }
        }
    }
    
    /**
     * Iniciar chamada
     */
    async call(number) {
        if (!this.isRegistered) {
            throw new Error('Não registrado no servidor SIP');
        }
        
        try {
            console.log('[VoIP] Iniciando chamada para:', number);
            
            // Obter stream de mídia local
            this.localStream = await navigator.mediaDevices.getUserMedia({
                audio: true,
                video: false
            });
            
            // Criar PeerConnection
            this.pc = new RTCPeerConnection({
                iceServers: [
                    { urls: this.credentials.stun_server }
                ]
            });
            
            // Adicionar tracks locais
            this.localStream.getTracks().forEach(track => {
                this.pc.addTrack(track, this.localStream);
            });
            
            // Configurar handlers
            this.pc.onicecandidate = (event) => {
                if (event.candidate) {
                    console.log('[VoIP] ICE Candidate:', event.candidate);
                }
            };
            
            this.pc.ontrack = (event) => {
                console.log('[VoIP] Stream remoto recebido');
                this.remoteStream = event.streams[0];
                this.playRemoteAudio();
            };
            
            // Criar oferta
            const offer = await this.pc.createOffer();
            await this.pc.setLocalDescription(offer);
            
            // Enviar convite via WebSocket
            const inviteMessage = {
                jsonrpc: '2.0',
                method: 'verto.invite',
                params: {
                    sdp: offer.sdp,
                    dialogParams: {
                        destination_number: number,
                        caller_id_name: this.credentials.display_name,
                        caller_id_number: this.credentials.extension,
                        remote_caller_id_name: 'Outbound Call',
                        remote_caller_id_number: number
                    }
                },
                id: this.generateId()
            };
            
            this.ws.send(JSON.stringify(inviteMessage));
            
            // Registrar chamada no banco via API
            const response = await fetch('/api/voip/originate_call.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    to: number,
                    contact_id: null
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.currentCall = {
                    id: data.call_id,
                    number: number,
                    direction: 'outbound',
                    status: 'calling'
                };
                
                console.log('[VoIP] Chamada registrada:', this.currentCall);
            }
            
            return this.currentCall;
            
        } catch (error) {
            console.error('[VoIP] Erro ao iniciar chamada:', error);
            this.cleanup();
            throw error;
        }
    }
    
    /**
     * Atender chamada recebida
     */
    async answer() {
        if (!this.currentCall || this.currentCall.direction !== 'inbound') {
            throw new Error('Nenhuma chamada recebida para atender');
        }
        
        try {
            console.log('[VoIP] Atendendo chamada...');
            
            // Obter stream de mídia local
            this.localStream = await navigator.mediaDevices.getUserMedia({
                audio: true,
                video: false
            });
            
            // Adicionar tracks ao PeerConnection existente
            this.localStream.getTracks().forEach(track => {
                this.pc.addTrack(track, this.localStream);
            });
            
            // Criar resposta
            const answer = await this.pc.createAnswer();
            await this.pc.setLocalDescription(answer);
            
            // Enviar resposta via WebSocket
            const answerMessage = {
                jsonrpc: '2.0',
                method: 'verto.answer',
                params: {
                    sdp: answer.sdp,
                    dialogParams: this.currentCall.dialogParams
                },
                id: this.generateId()
            };
            
            this.ws.send(JSON.stringify(answerMessage));
            
            this.currentCall.status = 'active';
            
            if (this.onCallAnswered) {
                this.onCallAnswered(this.currentCall);
            }
            
        } catch (error) {
            console.error('[VoIP] Erro ao atender chamada:', error);
            throw error;
        }
    }
    
    /**
     * Encerrar chamada
     */
    async hangup() {
        if (!this.currentCall) {
            console.log('[VoIP] Nenhuma chamada ativa');
            return;
        }
        
        try {
            console.log('[VoIP] Encerrando chamada...');
            
            // Enviar BYE via WebSocket
            if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                const byeMessage = {
                    jsonrpc: '2.0',
                    method: 'verto.bye',
                    params: {
                        dialogParams: this.currentCall.dialogParams
                    },
                    id: this.generateId()
                };
                
                this.ws.send(JSON.stringify(byeMessage));
            }
            
            // Atualizar no banco via API
            if (this.currentCall.id) {
                await fetch('/api/voip/hangup_call.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        call_id: this.currentCall.id,
                        hangup_cause: 'NORMAL_CLEARING'
                    })
                });
            }
            
            this.cleanup();
            
            if (this.onCallEnded) {
                this.onCallEnded(this.currentCall);
            }
            
            this.currentCall = null;
            
        } catch (error) {
            console.error('[VoIP] Erro ao encerrar chamada:', error);
            this.cleanup();
        }
    }
    
    /**
     * Mute/Unmute microfone
     */
    toggleMute() {
        if (!this.localStream) return false;
        
        const audioTrack = this.localStream.getAudioTracks()[0];
        if (audioTrack) {
            audioTrack.enabled = !audioTrack.enabled;
            return !audioTrack.enabled; // retorna true se mutado
        }
        
        return false;
    }
    
    /**
     * Hold/Unhold chamada
     */
    async toggleHold() {
        if (!this.currentCall) return false;
        
        const isOnHold = this.currentCall.onHold || false;
        const action = isOnHold ? 'unhold' : 'hold';
        
        try {
            const response = await fetch('/api/voip/hold_call.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    call_id: this.currentCall.id,
                    action: action
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.currentCall.onHold = data.on_hold;
                return data.on_hold;
            }
            
            return false;
            
        } catch (error) {
            console.error('[VoIP] Erro ao alternar hold:', error);
            return false;
        }
    }
    
    /**
     * Processar chamada recebida
     */
    handleIncomingCall(params) {
        console.log('[VoIP] Chamada recebida:', params);
        
        // Criar PeerConnection para chamada recebida
        this.pc = new RTCPeerConnection({
            iceServers: [
                { urls: this.credentials.stun_server }
            ]
        });
        
        this.pc.ontrack = (event) => {
            this.remoteStream = event.streams[0];
            this.playRemoteAudio();
        };
        
        // Definir descrição remota
        this.pc.setRemoteDescription(new RTCSessionDescription({
            type: 'offer',
            sdp: params.sdp
        }));
        
        this.currentCall = {
            id: null,
            number: params.dialogParams.caller_id_number,
            callerName: params.dialogParams.caller_id_name,
            direction: 'inbound',
            status: 'ringing',
            dialogParams: params.dialogParams
        };
        
        if (this.onIncomingCall) {
            this.onIncomingCall(this.currentCall);
        }
    }
    
    /**
     * Processar chamada atendida
     */
    handleCallAnswered(params) {
        console.log('[VoIP] Chamada atendida:', params);
        
        if (this.pc && params.sdp) {
            this.pc.setRemoteDescription(new RTCSessionDescription({
                type: 'answer',
                sdp: params.sdp
            }));
        }
        
        if (this.currentCall) {
            this.currentCall.status = 'active';
        }
        
        if (this.onCallAnswered) {
            this.onCallAnswered(this.currentCall);
        }
    }
    
    /**
     * Processar chamada encerrada
     */
    handleCallEnded(params) {
        console.log('[VoIP] Chamada encerrada:', params);
        
        this.cleanup();
        
        if (this.onCallEnded) {
            this.onCallEnded(this.currentCall);
        }
        
        this.currentCall = null;
    }
    
    /**
     * Processar atualização de mídia
     */
    handleMediaUpdate(params) {
        console.log('[VoIP] Atualização de mídia:', params);
    }
    
    /**
     * Reproduzir áudio remoto
     */
    playRemoteAudio() {
        if (!this.remoteStream) return;
        
        let audioElement = document.getElementById('voip-remote-audio');
        
        if (!audioElement) {
            audioElement = document.createElement('audio');
            audioElement.id = 'voip-remote-audio';
            audioElement.autoplay = true;
            document.body.appendChild(audioElement);
        }
        
        audioElement.srcObject = this.remoteStream;
    }
    
    /**
     * Limpar recursos
     */
    cleanup() {
        console.log('[VoIP] Limpando recursos...');
        
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => track.stop());
            this.localStream = null;
        }
        
        if (this.pc) {
            this.pc.close();
            this.pc = null;
        }
        
        this.remoteStream = null;
        
        const audioElement = document.getElementById('voip-remote-audio');
        if (audioElement) {
            audioElement.srcObject = null;
        }
    }
    
    /**
     * Desconectar
     */
    disconnect() {
        console.log('[VoIP] Desconectando...');
        
        if (this.currentCall) {
            this.hangup();
        }
        
        if (this.ws) {
            this.ws.close();
            this.ws = null;
        }
        
        this.isRegistered = false;
        this.cleanup();
    }
    
    /**
     * Gerar ID único
     */
    generateId() {
        return Math.random().toString(36).substring(2, 15);
    }
    
    /**
     * Gerar Session ID
     */
    generateSessionId() {
        return 'sess_' + Date.now() + '_' + Math.random().toString(36).substring(2, 15);
    }
}

// Exportar para uso global
window.VoIPWebRTCClient = VoIPWebRTCClient;
