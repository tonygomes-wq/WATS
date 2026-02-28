/**
 * Sistema de Notificações em Tempo Real - SSE
 * Versão: 2.1 - Apenas SSE para notificações popup (sem UI própria)
 */

class NotificationManager {
    constructor() {
        this.eventSource = null;
        this.isConnected = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 3000;
        this.init();
    }
    
    init() {
        this.connect();
        
        // Reconectar quando a aba ficar visível novamente
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && !this.isConnected) {
                console.log('[NotificationManager] Aba visível, reconectando...');
                this.connect();
            }
        });
    }
    
    connect() {
        if (this.eventSource) {
            this.eventSource.close();
        }
        
        console.log('[NotificationManager] Conectando ao SSE...');
        
        try {
            this.eventSource = new EventSource('/api/notifications_stream.php');
            
            this.eventSource.addEventListener('connected', () => {
                this.isConnected = true;
                this.reconnectAttempts = 0;
                console.log('[NotificationManager] SSE conectado');
            });
            
            this.eventSource.addEventListener('new_message', (e) => {
                try {
                    const data = JSON.parse(e.data);
                    this.handleNewMessage(data);
                } catch (err) {
                    console.error('[NotificationManager] Erro ao processar mensagem:', err);
                }
            });
            
            this.eventSource.addEventListener('heartbeat', () => {
                // Heartbeat recebido - conexão ativa
            });
            
            this.eventSource.addEventListener('reconnect', () => {
                console.log('[NotificationManager] Servidor solicitou reconexão');
                this.reconnect();
            });
            
            this.eventSource.onerror = () => {
                this.isConnected = false;
                this.eventSource.close();
                this.scheduleReconnect();
            };
            
        } catch (error) {
            console.error('[NotificationManager] Erro ao criar EventSource:', error);
            this.scheduleReconnect();
        }
    }
    
    scheduleReconnect() {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            console.log('[NotificationManager] Máximo de tentativas atingido');
            return;
        }
        
        this.reconnectAttempts++;
        const delay = this.reconnectDelay * this.reconnectAttempts;
        
        console.log(`[NotificationManager] Reconectando em ${delay}ms (tentativa ${this.reconnectAttempts})`);
        
        setTimeout(() => {
            if (!this.isConnected) {
                this.connect();
            }
        }, delay);
    }
    
    reconnect() {
        this.eventSource?.close();
        this.isConnected = false;
        setTimeout(() => this.connect(), 1000);
    }
    
    handleNewMessage(data) {
        console.log('[NotificationManager] Nova mensagem via SSE:', data);
        
        // Mostrar notificação popup via ChatNotifications
        if (typeof chatNotifications !== 'undefined') {
            chatNotifications.show({
                contactName: data.contact_name || 'Contato',
                contactPhone: data.phone || '',
                message: data.message || 'Nova mensagem',
                profilePic: data.profile_pic || null,
                conversationId: data.conversation_id
            });
        }
    }
}

// Inicializar ao carregar página
let notificationManager;
document.addEventListener('DOMContentLoaded', () => {
    // Só inicializar se não estiver na página de chat (que já tem polling)
    // O SSE é um complemento, não substitui o polling do chat
    if (!document.getElementById('chat-messages-container')) {
        notificationManager = new NotificationManager();
    }
});
