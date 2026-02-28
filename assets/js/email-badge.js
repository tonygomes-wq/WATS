/**
 * Sistema de Badge de Email para Avatares
 * Adiciona indicador visual de canal de email nas conversas
 */

class EmailBadge {
    constructor() {
        this.init();
    }
    
    init() {
        // Observar mudanças no DOM para adicionar badges dinamicamente
        this.observeConversations();
    }
    
    /**
     * Adicionar badge de email a um avatar
     */
    addBadge(avatarElement, channelType = 'email') {
        if (!avatarElement || avatarElement.querySelector('.channel-badge')) {
            return; // Já tem badge
        }
        
        const badge = document.createElement('div');
        badge.className = 'channel-badge email-badge';
        badge.innerHTML = this.getBadgeIcon(channelType);
        badge.title = this.getBadgeTitle(channelType);
        
        // Adicionar estilos inline se CSS não estiver carregado
        badge.style.cssText = `
            position: absolute;
            bottom: -2px;
            right: -2px;
            width: 20px;
            height: 20px;
            background: #EA4335;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            border: 2px solid white;
            z-index: 10;
        `;
        
        avatarElement.style.position = 'relative';
        avatarElement.appendChild(badge);
    }
    
    /**
     * Obter ícone do badge baseado no tipo de canal
     */
    getBadgeIcon(channelType) {
        const icons = {
            'email': '📧',
            'whatsapp': '💬',
            'telegram': '✈️',
            'facebook': '👤',
            'instagram': '📷'
        };
        return icons[channelType] || '📧';
    }
    
    /**
     * Obter título do badge
     */
    getBadgeTitle(channelType) {
        const titles = {
            'email': 'Email',
            'whatsapp': 'WhatsApp',
            'telegram': 'Telegram',
            'facebook': 'Facebook Messenger',
            'instagram': 'Instagram DM'
        };
        return titles[channelType] || 'Email';
    }
    
    /**
     * Observar conversas no DOM
     */
    observeConversations() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) { // Element node
                        this.processNode(node);
                    }
                });
            });
        });
        
        // Observar lista de conversas
        const conversationList = document.querySelector('.conversations-list, #conversations-list, [data-conversations]');
        if (conversationList) {
            observer.observe(conversationList, {
                childList: true,
                subtree: true
            });
        }
        
        // Processar conversas existentes
        this.processExistingConversations();
    }
    
    /**
     * Processar nó do DOM
     */
    processNode(node) {
        // Verificar se o nó tem atributo data-channel-type
        if (node.dataset && node.dataset.channelType === 'email') {
            const avatar = node.querySelector('.avatar, .contact-avatar, [data-avatar]');
            if (avatar) {
                this.addBadge(avatar, 'email');
            }
        }
        
        // Verificar filhos
        const emailConversations = node.querySelectorAll('[data-channel-type="email"]');
        emailConversations.forEach(conv => {
            const avatar = conv.querySelector('.avatar, .contact-avatar, [data-avatar]');
            if (avatar) {
                this.addBadge(avatar, 'email');
            }
        });
    }
    
    /**
     * Processar conversas existentes no carregamento
     */
    processExistingConversations() {
        const emailConversations = document.querySelectorAll('[data-channel-type="email"]');
        emailConversations.forEach(conv => {
            const avatar = conv.querySelector('.avatar, .contact-avatar, [data-avatar]');
            if (avatar) {
                this.addBadge(avatar, 'email');
            }
        });
    }
}

// Inicializar quando DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.emailBadge = new EmailBadge();
    });
} else {
    window.emailBadge = new EmailBadge();
}
