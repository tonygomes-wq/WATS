/**
 * Sistema de Notificações Popup para Chat
 * Estilo WhatsApp Web - Canto inferior direito
 * Versão: 2.0 - Com suporte a SSE e melhorias de performance
 */

class ChatNotification {
    constructor() {
        this.notifications = [];
        this.maxNotifications = 3;
        this.notificationDuration = 6000; // 6 segundos
        this.soundEnabled = true;
        this.isInitialized = false;
        this.notificationQueue = []; // Fila para notificações pendentes
        this.lastNotificationTime = 0;
        this.minNotificationInterval = 500; // Mínimo 500ms entre notificações
        this.init();
    }

    init() {
        // Aguardar DOM estar pronto
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.initializeComponents());
        } else {
            this.initializeComponents();
        }
    }

    initializeComponents() {
        this.createNotificationContainer();
        this.createNotificationSound();
        this.requestNotificationPermission();
        this.isInitialized = true;
        
        // Processar fila de notificações pendentes
        this.processQueue();
        
        console.log('[ChatNotifications] Sistema inicializado');
    }

    createNotificationSound() {
        // Criar contexto de áudio para som sintetizado (fallback)
        this.audioContext = null;
        this.audioFileAvailable = false;
        
        // Usar arquivo de áudio personalizado (Optimus Prime IA's Voice)
        this.audio = new Audio();
        this.audio.volume = 0.7; // 70% do volume
        this.audio.preload = 'auto';
        
        // Verificar se arquivo existe antes de definir src
        const soundPath = '/assets/sounds/notification.mp3';
        fetch(soundPath, { method: 'HEAD' })
            .then(response => {
                if (response.ok) {
                    this.audio.src = soundPath;
                    this.audioFileAvailable = true;
                    console.log('[ChatNotifications] Áudio de notificação carregado:', soundPath);
                } else {
                    console.warn('[ChatNotifications] Arquivo de áudio não encontrado, usando som sintetizado.');
                    this.initAudioContext();
                }
            })
            .catch(() => {
                console.warn('[ChatNotifications] Erro ao verificar áudio, usando som sintetizado.');
                this.initAudioContext();
            });
    }
    
    initAudioContext() {
        try {
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
        } catch (e) {
            // Web Audio API não suportada - silencioso
        }
    }

    createNotificationContainer() {
        if (document.getElementById('chat-notifications-container')) return;
        
        const container = document.createElement('div');
        container.id = 'chat-notifications-container';
        container.className = 'fixed bottom-4 right-4 z-[9999] space-y-3';
        container.style.cssText = 'max-width: 380px; pointer-events: auto;';
        document.body.appendChild(container);
    }

    requestNotificationPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().then(permission => {
                console.log('[ChatNotifications] Permissão de notificação:', permission);
            });
        }
    }

    // Processar fila de notificações pendentes
    processQueue() {
        if (this.notificationQueue.length > 0 && this.isInitialized) {
            const data = this.notificationQueue.shift();
            this.showNotification(data);
            
            // Processar próxima após intervalo mínimo
            if (this.notificationQueue.length > 0) {
                setTimeout(() => this.processQueue(), this.minNotificationInterval);
            }
        }
    }

    show(data) {
        // Se não inicializado, adicionar à fila
        if (!this.isInitialized) {
            console.log('[ChatNotifications] Adicionando à fila (não inicializado):', data.contactName);
            this.notificationQueue.push(data);
            return;
        }

        // Controle de rate limiting
        const now = Date.now();
        if (now - this.lastNotificationTime < this.minNotificationInterval) {
            this.notificationQueue.push(data);
            setTimeout(() => this.processQueue(), this.minNotificationInterval);
            return;
        }

        this.showNotification(data);
    }

    showNotification(data) {
        const { contactName, contactPhone, message, profilePic, conversationId } = data;
        
        this.lastNotificationTime = Date.now();
        
        console.log('[ChatNotifications] Mostrando notificação:', contactName, message);
        
        // Tocar som de notificação
        this.playNotificationSound();
        
        // Limitar número de notificações
        if (this.notifications.length >= this.maxNotifications) {
            this.removeOldest();
        }

        const notificationId = 'notif-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        const notification = this.createNotificationElement(
            notificationId,
            contactName,
            contactPhone,
            message,
            profilePic,
            conversationId
        );

        const container = document.getElementById('chat-notifications-container');
        if (!container) {
            console.error('[ChatNotifications] Container não encontrado!');
            return;
        }
        
        container.appendChild(notification);
        this.notifications.push(notificationId);

        // Animação de entrada
        requestAnimationFrame(() => {
            notification.classList.add('notification-enter');
        });

        // Auto-remover após duração
        setTimeout(() => {
            this.remove(notificationId);
        }, this.notificationDuration);

        // Notificação do navegador (se permitido e aba não está focada)
        if (document.hidden) {
            this.showBrowserNotification(contactName, message, conversationId);
        }
    }

    playNotificationSound() {
        if (!this.soundEnabled) return;

        try {
            // Tentar tocar arquivo de áudio se disponível
            if (this.audioFileAvailable && this.audio && this.audio.src) {
                this.audio.currentTime = 0;
                this.audio.play().catch(() => {
                    // Fallback silencioso para som sintetizado
                    this.playSynthesizedSound();
                });
            } else {
                // Usar som sintetizado
                this.playSynthesizedSound();
            }
        } catch (error) {
            // Silencioso em caso de erro
            this.playSynthesizedSound();
        }
    }

    playSynthesizedSound() {
        if (!this.audioContext) {
            try {
                this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
            } catch (e) {
                // Web Audio API não suportada - silencioso
                return;
            }
        }

        try {
            const context = this.audioContext;
            
            // Criar oscilador para o som
            const oscillator = context.createOscillator();
            const gainNode = context.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(context.destination);
            
            // Configurar som (estilo WhatsApp)
            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(800, context.currentTime); // Frequência inicial
            oscillator.frequency.exponentialRampToValueAtTime(600, context.currentTime + 0.1); // Rampa descendente
            
            // Envelope de volume
            gainNode.gain.setValueAtTime(0, context.currentTime);
            gainNode.gain.linearRampToValueAtTime(0.3, context.currentTime + 0.01); // Attack
            gainNode.gain.exponentialRampToValueAtTime(0.01, context.currentTime + 0.3); // Decay
            
            // Tocar som
            oscillator.start(context.currentTime);
            oscillator.stop(context.currentTime + 0.3);
            
        } catch (error) {
            console.error('Erro ao gerar som sintetizado:', error);
        }
    }

    toggleSound() {
        this.soundEnabled = !this.soundEnabled;
        return this.soundEnabled;
    }

    setSoundEnabled(enabled) {
        this.soundEnabled = enabled;
    }

    setVolume(volume) {
        if (this.audio) {
            this.audio.volume = Math.max(0, Math.min(1, volume)); // Entre 0 e 1
        }
    }

    createNotificationElement(id, name, phone, message, profilePic, conversationId) {
        const div = document.createElement('div');
        div.id = id;
        div.className = 'notification-popup bg-white dark:bg-gray-800 rounded-lg shadow-2xl overflow-hidden cursor-pointer transform transition-all duration-300 opacity-0 translate-x-full';
        div.style.cssText = 'min-width: 350px; max-width: 380px;';
        
        // Truncar mensagem se muito longa
        const truncatedMessage = message.length > 80 ? message.substring(0, 80) + '...' : message;
        
        // Iniciais do contato para avatar
        const initials = name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
        
        div.innerHTML = `
            <div class="flex items-start p-4 gap-3 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                <!-- Avatar -->
                <div class="flex-shrink-0">
                    ${profilePic ? 
                        `<img src="${profilePic}" alt="${name}" class="w-12 h-12 rounded-full object-cover">` :
                        `<div class="w-12 h-12 rounded-full bg-green-500 flex items-center justify-center text-white font-bold text-sm">
                            ${initials}
                        </div>`
                    }
                </div>
                
                <!-- Conteúdo -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between mb-1">
                        <h4 class="font-semibold text-gray-900 dark:text-white text-sm truncate">
                            ${this.escapeHtml(name)}
                        </h4>
                        <button onclick="chatNotifications.remove('${id}')" 
                                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 ml-2">
                            <i class="fas fa-times text-xs"></i>
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">${phone}</p>
                    <p class="text-sm text-gray-700 dark:text-gray-300 line-clamp-2">
                        ${this.escapeHtml(truncatedMessage)}
                    </p>
                </div>
            </div>
            
            <!-- Barra de progresso -->
            <div class="h-1 bg-gray-200 dark:bg-gray-700">
                <div class="h-full bg-green-500 notification-progress" style="animation: progress ${this.notificationDuration}ms linear;"></div>
            </div>
        `;

        // Click para abrir conversa
        div.addEventListener('click', (e) => {
            if (!e.target.closest('button')) {
                this.openConversation(conversationId);
                this.remove(id);
            }
        });

        return div;
    }

    remove(notificationId) {
        const notification = document.getElementById(notificationId);
        if (!notification) return;

        // Animação de saída
        notification.classList.remove('notification-enter');
        notification.classList.add('notification-exit');

        setTimeout(() => {
            notification.remove();
            this.notifications = this.notifications.filter(id => id !== notificationId);
        }, 300);
    }

    removeOldest() {
        if (this.notifications.length > 0) {
            this.remove(this.notifications[0]);
        }
    }

    openConversation(conversationId) {
        if (typeof selectConversation === 'function') {
            selectConversation(conversationId);
        } else if (typeof loadMessages === 'function') {
            loadMessages(conversationId);
        }
    }

    showBrowserNotification(title, body, conversationId) {
        if ('Notification' in window && Notification.permission === 'granted') {
            try {
                const notification = new Notification(title, {
                    body: body,
                    icon: '/assets/images/logo.png',
                    badge: '/assets/images/logo.png',
                    tag: 'chat-message-' + conversationId,
                    requireInteraction: false,
                    silent: true // Som já é tocado pelo sistema
                });
                
                // Clicar na notificação foca a janela e abre a conversa
                notification.onclick = () => {
                    window.focus();
                    this.openConversation(conversationId);
                    notification.close();
                };
                
                // Auto-fechar após 5 segundos
                setTimeout(() => notification.close(), 5000);
            } catch (e) {
                console.log('[ChatNotifications] Notificação do navegador não suportada:', e);
            }
        }
    }

    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    clearAll() {
        this.notifications.forEach(id => this.remove(id));
    }
}

// Inicializar sistema de notificações
const chatNotifications = new ChatNotification();

// CSS para animações (adicionar ao head)
if (!document.getElementById('chat-notifications-styles')) {
    const style = document.createElement('style');
    style.id = 'chat-notifications-styles';
    style.textContent = `
        .notification-popup {
            pointer-events: auto;
        }
        
        .notification-enter {
            opacity: 1 !important;
            transform: translateX(0) !important;
        }
        
        .notification-exit {
            opacity: 0 !important;
            transform: translateX(100%) !important;
        }
        
        @keyframes progress {
            from { width: 100%; }
            to { width: 0%; }
        }
        
        .notification-progress {
            transition: width 0.1s linear;
        }
        
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Hover effect */
        .notification-popup:hover .notification-progress {
            animation-play-state: paused !important;
        }
    `;
    document.head.appendChild(style);
}

// Exportar para uso global
window.chatNotifications = chatNotifications;
