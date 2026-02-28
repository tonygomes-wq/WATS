/**
 * Microsoft Teams Message Sync
 * Sincroniza mensagens do Teams automaticamente
 * OTIMIZADO: Inicia apenas após carregamento completo do chat
 */

class TeamsSyncManager {
    constructor() {
        this.syncInterval = 30000; // 30 segundos (aumentado de 10s)
        this.isRunning = false;
        this.intervalId = null;
        this.isTeamsConnected = false;
    }
    
    /**
     * Verificar se Teams está conectado
     */
    async checkConnection() {
        try {
            const response = await fetch('/api/teams_graph_config.php?action=get_user_info', {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            });
            
            if (!response.ok) {
                return false;
            }
            
            const data = await response.json();
            this.isTeamsConnected = data.success === true;
            return this.isTeamsConnected;
            
        } catch (error) {
            console.log('[Teams Sync] Erro ao verificar conexão:', error);
            this.isTeamsConnected = false;
            return false;
        }
    }
    
    /**
     * Iniciar sincronização automática
     */
    async start() {
        if (this.isRunning) {
            return;
        }
        
        // Verificar se Teams está conectado antes de iniciar
        const isConnected = await this.checkConnection();
        
        if (!isConnected) {
            console.log('[Teams Sync] Teams não conectado, sincronização desabilitada');
            return;
        }
        
        console.log('[Teams Sync] Iniciando sincronização automática...');
        this.isRunning = true;
        
        // AGUARDAR 5 segundos antes da primeira sincronização
        // para não bloquear carregamento inicial do chat
        setTimeout(() => {
            this.sync();
            
            // Configurar intervalo
            this.intervalId = setInterval(() => {
                this.sync();
            }, this.syncInterval);
        }, 5000);
    }
    
    /**
     * Parar sincronização automática
     */
    stop() {
        if (!this.isRunning) {
            return;
        }
        
        console.log('[Teams Sync] Parando sincronização automática...');
        this.isRunning = false;
        
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
    }
    
    /**
     * Sincronizar mensagens
     */
    async sync() {
        if (!this.isTeamsConnected) {
            return;
        }
        
        try {
            const response = await fetch('/api/teams_sync_messages.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                if (data.synced_messages > 0 || data.new_conversations > 0) {
                    console.log(`[Teams Sync] Sincronizadas ${data.synced_messages} mensagens, ${data.new_conversations} novas conversas`);
                    
                    // NÃO recarregar lista completa (evita notificações indesejadas)
                    // Apenas recarregar mensagens se uma conversa estiver aberta
                    if (typeof currentConversationId !== 'undefined' && currentConversationId) {
                        if (typeof fetchMessagesFromServer === 'function') {
                            fetchMessagesFromServer(currentConversationId, false, false); // false = não mostrar loading, não forçar scroll
                        }
                    }
                }
            } else {
                console.error('[Teams Sync] Erro:', data.error);
            }
            
        } catch (error) {
            console.error('[Teams Sync] Erro ao sincronizar:', error);
        }
    }
    
    /**
     * Sincronizar manualmente
     */
    async syncNow() {
        console.log('[Teams Sync] Sincronização manual iniciada...');
        await this.sync();
    }
}

// Instância global
const teamsSync = new TeamsSyncManager();

// Iniciar APENAS após o chat estar completamente carregado
// Aguardar 3 segundos após DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('[Teams Sync] Aguardando carregamento completo do chat...');
    
    setTimeout(() => {
        teamsSync.start();
    }, 3000); // 3 segundos de delay
});

// Parar sincronização quando sair da página
window.addEventListener('beforeunload', function() {
    teamsSync.stop();
});

// Expor globalmente para uso manual
window.teamsSync = teamsSync;
