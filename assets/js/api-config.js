/**
 * Configuração de Endpoints da API
 * 
 * MACIP Tecnologia LTDA
 * 
 * Este arquivo permite alternar entre endpoints antigos e novos
 * de forma simples e segura, facilitando rollback em caso de problemas.
 * 
 * COMO USAR:
 * 1. Para usar endpoints antigos: USE_NEW_API = false
 * 2. Para usar endpoints novos (MVC): USE_NEW_API = true
 * 
 * ROLLBACK:
 * Em caso de problemas, basta mudar USE_NEW_API para false
 */

const API_CONFIG = {
    /**
     * Switch principal: true = nova arquitetura, false = arquitetura antiga
     * 
     * ATENÇÃO: Mudar este valor afeta TODOS os endpoints do chat
     */
    USE_NEW_API: false, // ⭐ DESATIVADO - Usando arquitetura antiga (ROLLBACK)
    
    /**
     * Endpoints da arquitetura ANTIGA (atual)
     */
    OLD_API: {
        conversations: '/api/chat_conversations.php',
        messages: '/api/chat_messages.php',
        send: '/api/chat_send.php',
        delete: '/api/chat_delete_message.php',
        actions: '/api/conversation_actions.php'
    },
    
    /**
     * Endpoints da arquitetura NOVA (MVC - Fase 3)
     */
    NEW_API: {
        conversations: '/api/chat_v2.php?action=conversations',
        messages: '/api/chat_v2.php?action=messages',
        send: '/api/chat_v2.php?action=send',
        delete: '/api/chat_v2.php?action=delete',
        actions: '/api/chat_v2.php?action=actions'
    },
    
    /**
     * Função helper para obter o endpoint correto
     * 
     * @param {string} type - Tipo de endpoint (conversations, messages, send, etc)
     * @returns {string} URL do endpoint
     * 
     * Exemplo:
     * const url = API_CONFIG.getEndpoint('conversations');
     */
    getEndpoint: function(type) {
        const endpoints = this.USE_NEW_API ? this.NEW_API : this.OLD_API;
        
        if (!endpoints[type]) {
            console.error(`Endpoint '${type}' não encontrado!`);
            return null;
        }
        
        return endpoints[type];
    },
    
    /**
     * Função helper para fazer requisições
     * 
     * @param {string} type - Tipo de endpoint
     * @param {object} options - Opções do fetch (method, body, etc)
     * @returns {Promise} Promise do fetch
     * 
     * Exemplo:
     * API_CONFIG.request('conversations', { method: 'GET' })
     *     .then(response => response.json())
     *     .then(data => console.log(data));
     */
    request: function(type, options = {}) {
        const url = this.getEndpoint(type);
        
        if (!url) {
            return Promise.reject(new Error(`Endpoint '${type}' não encontrado`));
        }
        
        // Configurações padrão
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        };
        
        // Merge de opções
        const finalOptions = { ...defaultOptions, ...options };
        
        // Log para debug (remover em produção)
        if (this.DEBUG) {
            console.log(`[API] ${finalOptions.method} ${url}`, finalOptions);
        }
        
        return fetch(url, finalOptions);
    },
    
    /**
     * Modo debug (ativa logs no console)
     */
    DEBUG: false,
    
    /**
     * Informações sobre a API atual
     */
    getInfo: function() {
        return {
            version: this.USE_NEW_API ? 'MVC (v2)' : 'Legacy (v1)',
            endpoints: this.USE_NEW_API ? this.NEW_API : this.OLD_API,
            debug: this.DEBUG
        };
    },
    
    /**
     * Testar conectividade com a API
     */
    test: async function() {
        console.log('🧪 Testando API...');
        console.log('Versão:', this.getInfo().version);
        
        try {
            const response = await this.request('conversations');
            const data = await response.json();
            
            if (data.success) {
                console.log('✅ API funcionando!');
                console.log('Total de conversas:', data.total);
                return true;
            } else {
                console.error('❌ API retornou erro:', data.message);
                return false;
            }
        } catch (error) {
            console.error('❌ Erro ao testar API:', error);
            return false;
        }
    }
};

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.API_CONFIG = API_CONFIG;
}

// Log de inicialização
console.log('📡 API Config carregado:', API_CONFIG.getInfo().version);
