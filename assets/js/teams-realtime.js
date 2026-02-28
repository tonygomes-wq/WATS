/**
 * TEAMS REALTIME INTEGRATION
 * Polling para receber mensagens do Teams em tempo real
 * 
 * @author MAC-IP TECNOLOGIA
 * @version 1.0
 */

(function() {
    'use strict';
    
    // Configurações
    const POLL_INTERVAL = 10000; // 10 segundos
    const MAX_RETRIES = 3;
    
    let pollTimer = null;
    let retryCount = 0;
    let isPolling = false;
    let lastPollTime = 0;
    
    /**
     * Iniciar polling do Teams
     */
    function startTeamsPolling() {
        console.log('[Teams Realtime] Iniciando polling...');
        
        // Limpar timer anterior se existir
        if (pollTimer) {
            clearInterval(pollTimer);
        }
        
        // Fazer primeira busca imediatamente
        pollTeamsMessages();
        
        // Configurar polling periódico
        pollTimer = setInterval(pollTeamsMessages, POLL_INTERVAL);
    }
    
    /**
     * Parar polling do Teams
     */
    function stopTeamsPolling() {
        console.log('[Teams Realtime] Parando polling...');
        
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        
        isPolling = false;
    }
    
    /**
     * Buscar novas mensagens do Teams
     */
    async function pollTeamsMessages() {
        // Evitar múltiplas requisições simultâneas
        if (isPolling) {
            console.log('[Teams Realtime] Polling já em andamento, pulando...');
            return;
        }
        
        // Throttle: não fazer polling mais de uma vez a cada 5 segundos
        const now = Date.now();
        if (now - lastPollTime < 5000) {
            return;
        }
        
        isPolling = true;
        lastPollTime = now;
        
        try {
            const response = await fetch('/api/chat_poll_teams.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && data.has_updates) {
                console.log('[Teams Realtime] Novas mensagens encontradas:', data.updates.length);
                
                // Processar cada atualização
                for (const update of data.updates) {
                    handleTeamsUpdate(update);
                }
                
                // Resetar contador de erros
                retryCount = 0;
            }
            
        } catch (error) {
            console.error('[Teams Realtime] Erro no polling:', error);
            retryCount++;
            
            // Se muitos erros consecutivos, aumentar intervalo
            if (retryCount >= MAX_RETRIES) {
                console.warn('[Teams Realtime] Muitos erros, reduzindo frequência de polling');
                stopTeamsPolling();
                
                // Reiniciar com intervalo maior após 30 segundos
                setTimeout(() => {
                    retryCount = 0;
                    startTeamsPolling();
                }, 30000);
            }
        } finally {
            isPolling = false;
        }
    }
    
    /**
     * Processar atualização de mensagem do Teams
     */
    function handleTeamsUpdate(update) {
        const conversationId = update.conversation_id;
        
        console.log('[Teams Realtime] Processando atualização:', update);
        
        // Verificar se a conversa está aberta no momento
        const currentConversationId = window.currentConversationId || null;
        
        if (currentConversationId === conversationId) {
            // Conversa está aberta: recarregar mensagens
            console.log('[Teams Realtime] Conversa aberta, recarregando mensagens...');
            
            if (typeof window.loadMessages === 'function') {
                window.loadMessages(conversationId);
            }
        } else {
            // Conversa não está aberta: atualizar contador e lista
            console.log('[Teams Realtime] Atualizando lista de conversas...');
            
            // Atualizar contador de não lidas
            updateUnreadCount(conversationId, update.new_messages_count);
            
            // Recarregar lista de conversas se a função existir
            if (typeof window.loadConversations === 'function') {
                window.loadConversations();
            }
        }
        
        // Tocar som de notificação se configurado
        if (typeof window.playNotificationSound === 'function') {
            window.playNotificationSound();
        }
        
        // Mostrar notificação desktop se permitido
        if (typeof window.showDesktopNotification === 'function') {
            window.showDesktopNotification({
                title: update.contact_name || 'Nova mensagem do Teams',
                body: `${update.new_messages_count} nova(s) mensagem(ns)`,
                icon: update.profile_pic_url || '/assets/images/teams-icon.png'
            });
        }
    }
    
    /**
     * Atualizar contador de mensagens não lidas
     */
    function updateUnreadCount(conversationId, count) {
        // Buscar elemento da conversa na lista
        const conversationElement = document.querySelector(`[data-conversation-id="${conversationId}"]`);
        
        if (conversationElement) {
            // Atualizar badge de não lidas
            let badge = conversationElement.querySelector('.unread-badge');
            
            if (count > 0) {
                if (!badge) {
                    // Criar badge se não existir
                    badge = document.createElement('span');
                    badge.className = 'unread-badge';
                    conversationElement.appendChild(badge);
                }
                badge.textContent = count;
                badge.style.display = 'inline-block';
                
                // Adicionar classe de não lida
                conversationElement.classList.add('has-unread');
            } else {
                // Remover badge se count = 0
                if (badge) {
                    badge.remove();
                }
                conversationElement.classList.remove('has-unread');
            }
            
            // Mover conversa para o topo da lista
            const conversationList = conversationElement.parentElement;
            if (conversationList) {
                conversationList.insertBefore(conversationElement, conversationList.firstChild);
            }
        }
    }
    
    /**
     * Verificar se Teams está autenticado
     */
    async function checkTeamsAuth() {
        try {
            const response = await fetch('/api/teams_sync_messages.php?check_auth=1', {
                method: 'GET',
                credentials: 'same-origin'
            });
            
            if (response.ok) {
                const data = await response.json();
                return data.authenticated === true;
            }
        } catch (error) {
            console.error('[Teams Realtime] Erro ao verificar autenticação:', error);
        }
        
        return false;
    }
    
    // Expor funções globalmente
    window.TeamsRealtime = {
        start: startTeamsPolling,
        stop: stopTeamsPolling,
        checkAuth: checkTeamsAuth
    };
    
    // Auto-iniciar quando o documento estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            console.log('[Teams Realtime] Documento carregado, iniciando polling...');
            startTeamsPolling();
        });
    } else {
        console.log('[Teams Realtime] Documento já carregado, iniciando polling...');
        startTeamsPolling();
    }
    
    // Parar polling quando a página for fechada
    window.addEventListener('beforeunload', stopTeamsPolling);
    
    // Pausar polling quando a aba não estiver visível (economizar recursos)
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            console.log('[Teams Realtime] Aba oculta, pausando polling...');
            stopTeamsPolling();
        } else {
            console.log('[Teams Realtime] Aba visível, retomando polling...');
            startTeamsPolling();
        }
    });
    
})();
