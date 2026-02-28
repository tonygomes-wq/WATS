/**
 * Sistema Multi-Canal para Chat
 * Adiciona suporte para Telegram, Facebook e outros canais
 */

// Configuração de canais
const channelConfig = {
    whatsapp: {
        icon: 'fab fa-whatsapp',
        color: '#25d366',
        gradient: 'linear-gradient(135deg, #25d366, #128c7e)',
        name: 'WhatsApp'
    },
    teams: {
        icon: 'fas fa-users',
        color: '#5b5fc7',
        gradient: 'linear-gradient(135deg, #5b5fc7, #464ac9)',
        name: 'Microsoft Teams'
    },
    telegram: {
        icon: 'fab fa-telegram',
        color: '#0088cc',
        gradient: 'linear-gradient(135deg, #0088cc, #006699)',
        name: 'Telegram'
    },
    facebook: {
        icon: 'fab fa-facebook-messenger',
        color: '#0084ff',
        gradient: 'linear-gradient(135deg, #0084ff, #0063d1)',
        name: 'Facebook'
    },
    instagram: {
        icon: 'fab fa-instagram',
        color: '#e1306c',
        gradient: 'linear-gradient(135deg, #e1306c, #c13584)',
        name: 'Instagram'
    },
    email: {
        icon: 'fas fa-envelope',
        color: '#ea4335',
        gradient: 'linear-gradient(135deg, #ea4335, #c5221f)',
        name: 'Email'
    },
    twitter: {
        icon: 'fab fa-twitter',
        color: '#1da1f2',
        gradient: 'linear-gradient(135deg, #1da1f2, #0d8bd9)',
        name: 'Twitter'
    }
};

// Filtro de canal atual (GLOBAL)
window.currentChannelFilter = 'all';
let currentChannelFilter = 'all';

// Contadores de conversas por canal
let channelCounts = {
    all: 0,
    whatsapp: 0,
    telegram: 0,
    facebook: 0,
    instagram: 0,
    email: 0,
    teams: 0
};

/**
 * Obter configuração do canal
 */
function getChannelConfig(source) {
    return channelConfig[source] || channelConfig.whatsapp;
}

/**
 * Calcular contadores de conversas por canal
 */
function calculateChannelCounts(conversations) {
    // Resetar contadores
    Object.keys(channelCounts).forEach(key => {
        channelCounts[key] = 0;
    });
    
    if (!conversations || !Array.isArray(conversations)) {
        return channelCounts;
    }
    
    channelCounts.all = conversations.length;
    
    conversations.forEach(conv => {
        const source = (conv.source || conv.channel_type || 'whatsapp').toLowerCase();
        if (channelCounts.hasOwnProperty(source)) {
            channelCounts[source]++;
        }
    });
    
    return channelCounts;
}

/**
 * Atualizar contadores visuais no dropdown
 */
function updateChannelCountsUI() {
    Object.keys(channelCounts).forEach(channel => {
        const count = channelCounts[channel];
        const item = document.querySelector(`[data-channel="${channel}"]`);
        
        if (item) {
            // Remover contador existente
            const existingCount = item.querySelector('.channel-count');
            if (existingCount) {
                existingCount.remove();
            }
            
            // Adicionar novo contador se > 0
            if (count > 0) {
                const countSpan = document.createElement('span');
                countSpan.className = 'channel-count';
                countSpan.textContent = count;
                countSpan.style.cssText = `
                    margin-left: auto;
                    background: #e5e7eb;
                    color: #6b7280;
                    padding: 2px 8px;
                    border-radius: 12px;
                    font-size: 11px;
                    font-weight: 600;
                `;
                
                // Inserir antes do check icon
                const checkIcon = item.querySelector('.channel-check');
                if (checkIcon) {
                    item.insertBefore(countSpan, checkIcon);
                } else {
                    item.appendChild(countSpan);
                }
            }
        }
    });
    
    console.log('[MULTICHANNEL] Contadores atualizados:', channelCounts);
}

/**
 * Adicionar badge de canal ao avatar da conversa
 */
function addChannelBadgeToAvatar(avatarElement, source) {
    const channel = getChannelConfig(source || 'whatsapp');
    
    // Remover badge existente se houver
    const existingBadge = avatarElement.querySelector('.channel-badge');
    if (existingBadge) {
        existingBadge.remove();
    }
    
    // Criar novo badge
    const badge = document.createElement('div');
    badge.className = 'channel-badge';
    badge.style.cssText = `
        position: absolute;
        bottom: -2px;
        right: -2px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: ${channel.gradient};
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        z-index: 10;
    `;
    badge.innerHTML = `<i class="${channel.icon}" style="font-size: 10px; color: white;"></i>`;
    
    avatarElement.style.position = 'relative';
    avatarElement.appendChild(badge);
}

/**
 * Adicionar indicador de canal ao nome do contato
 */
function addChannelIndicatorToName(nameElement, source) {
    const channel = getChannelConfig(source || 'whatsapp');
    
    // Remover indicador existente
    const existingIndicator = nameElement.querySelector('.channel-indicator');
    if (existingIndicator) {
        existingIndicator.remove();
    }
    
    // Criar indicador
    const indicator = document.createElement('span');
    indicator.className = 'channel-indicator';
    indicator.style.cssText = `
        color: ${channel.color};
        font-size: 12px;
        margin-left: 6px;
    `;
    indicator.innerHTML = `<i class="${channel.icon}"></i>`;
    
    nameElement.appendChild(indicator);
}

/**
 * Adicionar indicador de canal na mensagem
 */
function addChannelIndicatorToMessage(messageElement, channelType, fromMe) {
    if (fromMe) return; // Não mostrar para mensagens enviadas
    
    const channel = getChannelConfig(channelType || 'whatsapp');
    
    // Criar indicador
    const indicator = document.createElement('div');
    indicator.className = 'message-channel-indicator';
    indicator.style.cssText = `
        font-size: 10px;
        color: ${channel.color};
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 4px;
    `;
    indicator.innerHTML = `
        <i class="${channel.icon}"></i>
        <span>${channel.name}</span>
    `;
    
    // Inserir antes do bubble
    const bubble = messageElement.querySelector('.chat-message-bubble');
    if (bubble) {
        messageElement.insertBefore(indicator, bubble);
    }
}

/**
 * Adicionar borda colorida na mensagem recebida
 */
function addChannelBorderToMessage(bubbleElement, channelType, fromMe) {
    if (fromMe) return;
    
    const channel = getChannelConfig(channelType || 'whatsapp');
    bubbleElement.style.borderLeft = `3px solid ${channel.color}`;
}

/**
 * Atualizar header do chat com informação do canal
 */
function updateChatHeaderWithChannel(contactName, source) {
    const channel = getChannelConfig(source || 'whatsapp');
    const nameElement = document.getElementById('chat-contact-name');
    
    if (nameElement) {
        nameElement.innerHTML = `
            ${contactName}
            <span style="
                display: inline-flex;
                align-items: center;
                gap: 4px;
                font-size: 12px;
                color: ${channel.color};
                margin-left: 8px;
                padding: 2px 8px;
                background: ${channel.color}15;
                border-radius: 12px;
            ">
                <i class="${channel.icon}"></i>
                ${channel.name}
            </span>
        `;
    }
}

/**
 * Atualizar placeholder do input com canal
 */
function updateInputPlaceholder(channelType) {
    const channel = getChannelConfig(channelType || 'whatsapp');
    const input = document.getElementById('message-input');
    
    if (input) {
        input.placeholder = `Enviar mensagem via ${channel.name}...`;
    }
}

/**
 * Filtrar conversas por canal
 */
async function filterByChannel(channel) {
    console.log('[MULTICHANNEL] ===== FILTRO DE CANAL ACIONADO =====');
    console.log('[MULTICHANNEL] Canal selecionado:', channel);
    console.log('[MULTICHANNEL] Canal anterior:', currentChannelFilter);
    
    // IMPORTANTE: Limpar localStorage ANTES de atualizar o filtro
    try {
        localStorage.removeItem('wats_selected_channel');
        console.log('[MULTICHANNEL] localStorage limpo');
    } catch (e) {
        console.warn('[MULTICHANNEL] Erro ao limpar localStorage:', e);
    }
    
    // Atualizar AMBAS as variáveis (local e global)
    currentChannelFilter = channel;
    window.currentChannelFilter = channel;
    
    console.log('[MULTICHANNEL] currentChannelFilter atualizado para:', currentChannelFilter);
    console.log('[MULTICHANNEL] window.currentChannelFilter:', window.currentChannelFilter);
    
    // Atualizar botões ativos
    document.querySelectorAll('.chat-channel-filter').forEach(btn => {
        btn.classList.remove('active');
    });
    
    const activeBtn = document.querySelector(`[data-channel="${channel}"]`);
    if (activeBtn) {
        activeBtn.classList.add('active');
        console.log('[MULTICHANNEL] Botão ativo atualizado:', channel);
    }
    
    // Atualizar indicador visual do canal ativo
    console.log('[MULTICHANNEL] Chamando updateActiveChannelIndicator com:', channel);
    updateActiveChannelIndicator(channel);
    
    // Se for email, redirecionar para página dedicada
    if (channel === 'email') {
        console.log('[MULTICHANNEL] Redirecionando para email_chat.php');
        window.location.href = 'email_chat.php';
        return;
    }
    
    // PROTEÇÃO: Não re-renderizar se estiver enviando mídia
    if (window.sendingMedia) {
        console.log('[MULTICHANNEL] ⚠️ Enviando mídia - adiando re-renderização');
        // Agendar re-renderização para depois que o envio terminar
        setTimeout(() => {
            if (!window.sendingMedia) {
                console.log('[MULTICHANNEL] Executando re-renderização adiada');
                if (typeof loadConversations === 'function') {
                    loadConversations();
                } else if (typeof renderConversations === 'function') {
                    renderConversations();
                }
            }
        }, 3000);
        return;
    }
    
    // Para outros canais (WhatsApp, Teams, etc)
    console.log('[MULTICHANNEL] Recarregando conversas com filtro:', currentChannelFilter);
    
    if (typeof loadConversations === 'function') {
        console.log('[MULTICHANNEL] Chamando loadConversations()');
        await loadConversations();
    } else if (typeof renderConversations === 'function') {
        console.log('[MULTICHANNEL] Chamando renderConversations()');
        renderConversations();
    } else {
        console.log('[MULTICHANNEL] Recarregando página');
        window.location.reload();
    }
    
    console.log('[MULTICHANNEL] ===== FIM DO FILTRO =====');
}

/**
 * Restaurar filtro salvo do localStorage
 */
function restoreSavedChannelFilter() {
    // DESABILITADO: Não restaurar filtro automaticamente
    // Isso estava causando problemas ao carregar a página
    console.log('[MULTICHANNEL] Restauração automática de filtro DESABILITADA');
    console.log('[MULTICHANNEL] Usuário deve selecionar filtro manualmente');
    return;
    
    /* CÓDIGO ORIGINAL COMENTADO:
    try {
        const savedChannel = localStorage.getItem('wats_selected_channel');
        if (savedChannel && savedChannel !== 'all') {
            console.log('[MULTICHANNEL] Restaurando filtro salvo:', savedChannel);
            
            // Aplicar filtro sem recarregar
            currentChannelFilter = savedChannel;
            
            // Atualizar UI
            const activeBtn = document.querySelector(`[data-channel="${savedChannel}"]`);
            if (activeBtn) {
                activeBtn.classList.add('active');
                document.querySelector('[data-channel="all"]')?.classList.remove('active');
            }
            
            // Atualizar botão principal do dropdown
            const channelData = {
                'whatsapp': { icon: 'fab fa-whatsapp', label: 'WhatsApp' },
                'telegram': { icon: 'fab fa-telegram', label: 'Telegram' },
                'facebook': { icon: 'fab fa-facebook-messenger', label: 'Facebook' },
                'instagram': { icon: 'fab fa-instagram', label: 'Instagram' },
                'teams': { icon: 'fas fa-users', label: 'Microsoft Teams' }
            };
            
            if (channelData[savedChannel]) {
                const channelIcon = document.getElementById('channel-icon');
                const channelLabel = document.getElementById('channel-label');
                if (channelIcon) channelIcon.className = channelData[savedChannel].icon;
                if (channelLabel) channelLabel.textContent = channelData[savedChannel].label;
            }
            
            updateActiveChannelIndicator(savedChannel);
        }
    } catch (e) {
        console.warn('[MULTICHANNEL] Erro ao restaurar filtro:', e);
    }
    */
}

/**
 * Atualizar indicador visual do canal ativo
 */
function updateActiveChannelIndicator(channel) {
    console.log('[MULTICHANNEL] Atualizando indicador para canal:', channel);
    
    // Remover indicador existente
    const existingIndicator = document.querySelector('.active-channel-indicator');
    if (existingIndicator) {
        existingIndicator.remove();
    }
    
    // Se for "all", não mostrar indicador
    if (channel === 'all') {
        console.log('[MULTICHANNEL] Canal "all" - não mostrar indicador');
        return;
    }
    
    const config = getChannelConfig(channel);
    console.log('[MULTICHANNEL] Config do canal:', config);
    
    // Criar novo indicador
    const indicator = document.createElement('div');
    indicator.className = 'active-channel-indicator';
    indicator.style.cssText = `
        position: fixed;
        top: 80px;
        right: 20px;
        background: ${config.gradient};
        color: white;
        padding: 8px 16px;
        border-radius: 20px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 1000;
        animation: slideInRight 0.3s ease-out;
    `;
    
    indicator.innerHTML = `
        <i class="${config.icon}"></i>
        <span>Filtrando: ${config.name}</span>
        <button onclick="clearChannelFilter()" style="
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            margin-left: 4px;
            transition: background 0.2s;
        " onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
            ×
        </button>
    `;
    
    document.body.appendChild(indicator);
    
    // Remover automaticamente após 5 segundos
    setTimeout(() => {
        if (indicator && indicator.parentNode) {
            indicator.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(() => {
                if (indicator.parentNode) {
                    indicator.remove();
                }
            }, 300);
        }
    }, 5000);
    
    console.log('[MULTICHANNEL] Indicador criado e será removido em 5 segundos');
}

/**
 * Limpar filtro de canal
 */
function clearChannelFilter() {
    filterByChannel('all');
    
    // Atualizar botão principal do dropdown
    const channelIcon = document.getElementById('channel-icon');
    const channelLabel = document.getElementById('channel-label');
    if (channelIcon) channelIcon.className = 'fas fa-globe';
    if (channelLabel) channelLabel.textContent = 'Todos os Canais';
}

/**
 * Carregar conversas de email
 */
async function loadEmailConversations() {
    console.log('[EMAIL] Carregando conversas de email...');
    
    try {
        const response = await fetch('api/email_conversations.php');
        const data = await response.json();
        
        console.log('[EMAIL] Resposta da API:', data);
        
        if (data.success && data.conversations) {
            console.log('[EMAIL] Conversas recebidas:', data.conversations.length);
            renderEmailConversations(data.conversations);
        } else {
            console.error('[EMAIL] Erro ao carregar conversas:', data.error);
            showNoConversationsMessage('Nenhuma conversa de email encontrada');
        }
    } catch (error) {
        console.error('[EMAIL] Erro:', error);
        showNoConversationsMessage('Erro ao carregar emails');
    }
}

/**
 * Renderizar conversas de email
 */
function renderEmailConversations(conversations) {
    console.log('[EMAIL] Renderizando', conversations.length, 'conversas');
    
    const container = document.getElementById('conversations-list');
    if (!container) {
        console.error('[EMAIL] Container conversations-list não encontrado!');
        return;
    }
    
    if (conversations.length === 0) {
        showNoConversationsMessage('Nenhuma conversa de email');
        return;
    }
    
    container.innerHTML = '';
    
    // Remover listeners antigos
    const oldContainer = container.cloneNode(false);
    container.parentNode.replaceChild(oldContainer, container);
    
    conversations.forEach((conv, index) => {
        console.log(`[EMAIL] Renderizando conversa ${index + 1}:`, conv.id, conv.contact_name);
        
        const div = document.createElement('div');
        div.className = 'conversation-item email-conversation-item';
        div.style.cursor = 'pointer';
        div.setAttribute('data-email-conv-id', conv.id);
        div.setAttribute('data-email-name', conv.contact_name || conv.display_name);
        
        // Extrair assunto do email
        const subject = conv.email_subject || 'Sem assunto';
        const preview = conv.last_message_text || '';
        
        div.innerHTML = `
            <div class="conversation-avatar">
                <div class="avatar-circle" style="background: linear-gradient(135deg, #ea4335, #c5221f);">
                    <i class="fas fa-envelope" style="color: white;"></i>
                </div>
            </div>
            <div class="conversation-info">
                <div class="conversation-header">
                    <span class="conversation-name">
                        ${conv.display_name || conv.contact_name || conv.phone}
                        <i class="fas fa-envelope" style="color: #ea4335; font-size: 12px; margin-left: 6px;"></i>
                    </span>
                    <span class="conversation-time">${conv.last_message_time_formatted || ''}</span>
                </div>
                <div class="conversation-preview">
                    <strong style="color: #374151;">${subject}</strong><br>
                    <span style="color: #6b7280;">${preview.substring(0, 60)}...</span>
                </div>
                ${conv.unread_count > 0 ? `<span class="unread-badge">${conv.unread_count}</span>` : ''}
            </div>
        `;
        
        oldContainer.appendChild(div);
        
        // Guardar dados da conversa no elemento
        div._emailConvData = conv;
    });
    
    // Adicionar listener de delegação no container
    oldContainer.addEventListener('click', function(e) {
        console.log('[EMAIL] Click no container detectado');
        
        // Encontrar o elemento da conversa
        let target = e.target;
        while (target && target !== oldContainer) {
            if (target.classList && target.classList.contains('email-conversation-item')) {
                console.log('[EMAIL] ===== CLIQUE NA CONVERSA =====');
                const conv = target._emailConvData;
                if (conv) {
                    console.log('[EMAIL] Dados da conversa:', conv);
                    e.preventDefault();
                    e.stopPropagation();
                    openEmailConversation(conv);
                    return;
                }
            }
            target = target.parentElement;
        }
    }, true);
    
    console.log('[EMAIL] ✅ Conversas renderizadas com sucesso');
    console.log('[EMAIL] ✅ Event listener adicionado ao container');
}

/**
 * Abrir conversa de email
 */
function openEmailConversation(conv) {
    console.log('[EMAIL] Abrindo conversa:', conv.id, conv.contact_name);
    
    // Marcar conversa como ativa
    document.querySelectorAll('.conversation-item').forEach(item => {
        item.classList.remove('active');
    });
    const activeItem = document.querySelector(`[data-conversation-id="${conv.id}"]`);
    if (activeItem) {
        activeItem.classList.add('active');
    }
    
    // Esconder estado vazio e mostrar área de chat
    const messagesLoading = document.getElementById('messages-loading');
    if (messagesLoading) {
        messagesLoading.style.display = 'none';
    }
    
    // Atualizar header
    const nameElement = document.getElementById('chat-contact-name');
    if (nameElement) {
        nameElement.innerHTML = `
            ${conv.display_name || conv.contact_name}
            <span style="
                display: inline-flex;
                align-items: center;
                gap: 4px;
                font-size: 12px;
                color: #ea4335;
                margin-left: 8px;
                padding: 2px 8px;
                background: #ea433515;
                border-radius: 12px;
            ">
                <i class="fas fa-envelope"></i>
                Email
            </span>
        `;
    }
    
    // Atualizar telefone/email no header
    const phoneElement = document.getElementById('chat-contact-phone');
    if (phoneElement) {
        phoneElement.textContent = conv.phone || conv.contact_number;
    }
    
    // Limpar mensagens antigas e mostrar loading
    const messagesContainer = document.getElementById('chat-messages-container');
    if (messagesContainer) {
        messagesContainer.innerHTML = '<div style="text-align: center; padding: 40px; color: #6b7280;"><i class="fas fa-spinner fa-spin" style="font-size: 24px;"></i><br><br>Carregando mensagens...</div>';
    }
    
    // Carregar mensagens
    console.log('[EMAIL] Carregando mensagens da conversa:', conv.id);
    loadEmailMessages(conv.id);
}

/**
 * Carregar mensagens de email
 */
async function loadEmailMessages(conversationId) {
    try {
        const response = await fetch(`api/email_messages.php?conversation_id=${conversationId}`);
        const data = await response.json();
        
        if (data.success && data.messages) {
            renderEmailMessages(data.messages, data.conversation);
        } else {
            console.error('[EMAIL] Erro:', data.error);
        }
    } catch (error) {
        console.error('[EMAIL] Erro ao carregar mensagens:', error);
    }
}

/**
 * Renderizar mensagens de email
 */
function renderEmailMessages(messages, conversation) {
    const container = document.getElementById('chat-messages-container');
    if (!container) {
        console.error('[EMAIL] Container de mensagens não encontrado');
        return;
    }
    
    container.innerHTML = '';
    
    if (messages.length === 0) {
        container.innerHTML = `
            <div style="
                padding: 40px 20px;
                text-align: center;
                color: #6b7280;
            ">
                <i class="fas fa-envelope-open" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                <p>Nenhuma mensagem nesta conversa</p>
            </div>
        `;
        return;
    }
    
    messages.forEach(msg => {
        const div = document.createElement('div');
        const isFromMe = msg.sender_type === 'user' || msg.from_me;
        div.className = `chat-message ${isFromMe ? 'sent' : 'received'}`;
        
        // Formatar data/hora
        const date = new Date(msg.created_at);
        const timeStr = date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        
        // Extrair assunto se existir
        let subjectHtml = '';
        if (msg.email_subject && !isFromMe) {
            subjectHtml = `
                <div style="
                    font-weight: 600;
                    color: #374151;
                    margin-bottom: 8px;
                    padding-bottom: 8px;
                    border-bottom: 1px solid #e5e7eb;
                ">
                    📧 ${msg.email_subject}
                </div>
            `;
        }
        
        div.innerHTML = `
            <div class="chat-message-bubble" style="${!isFromMe ? 'border-left: 3px solid #ea4335;' : ''}">
                ${subjectHtml}
                <div class="message-text">${msg.message_text}</div>
                <div class="message-time">${timeStr}</div>
            </div>
        `;
        
        container.appendChild(div);
    });
    
    // Scroll para o final
    setTimeout(() => {
        container.scrollTop = container.scrollHeight;
    }, 100);
    
    console.log(`[EMAIL] ${messages.length} mensagens renderizadas`);
}

/**
 * Mostrar mensagem quando não há conversas
 */
function showNoConversationsMessage(message) {
    const container = document.getElementById('conversations-list');
    if (!container) return;
    
    container.innerHTML = `
        <div style="
            padding: 40px 20px;
            text-align: center;
            color: #6b7280;
        ">
            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
            <p>${message}</p>
        </div>
    `;
}

/**
 * Aplicar filtro de canal nas conversas
 */
function applyChannelFilter(conversations) {
    // Usar a variável global se a local não estiver definida
    const activeFilter = window.currentChannelFilter || currentChannelFilter || 'all';
    
    // Verificar se conversations é um array válido
    if (!conversations || !Array.isArray(conversations)) {
        console.warn('[MULTICHANNEL] Conversas inválidas ou vazias:', conversations);
        return [];
    }
    
    console.log('[MULTICHANNEL] ===== APLICANDO FILTRO =====');
    console.log('[MULTICHANNEL] Filtro ativo:', activeFilter);
    console.log('[MULTICHANNEL] Total de conversas antes do filtro:', conversations.length);
    
    // PROTEÇÃO: Não aplicar filtro se estiver enviando mídia
    if (window.sendingMedia) {
        console.log('[MULTICHANNEL] ⚠️ Enviando mídia - retornando conversas sem filtrar para evitar re-renderização');
        return conversations;
    }
    
    // Log das primeiras 3 conversas para debug
    if (conversations.length > 0) {
        console.log('[MULTICHANNEL] Amostra de conversas:');
        conversations.slice(0, 3).forEach((conv, i) => {
            console.log(`  ${i+1}. ${conv.display_name || conv.contact_name} | source: ${conv.source} | channel_type: ${conv.channel_type}`);
        });
    }
    
    if (activeFilter === 'all') {
        console.log('[MULTICHANNEL] Filtro "all" - retornando todas as conversas');
        return conversations;
    }
    
    const filtered = conversations.filter(conv => {
        const source = conv.source || conv.channel_type || 'whatsapp';
        const match = source === activeFilter;
        
        if (match) {
            console.log('[MULTICHANNEL] ✓ Conversa aceita:', conv.display_name || conv.contact_name, '| Canal:', source);
        } else {
            console.log('[MULTICHANNEL] ✗ Conversa rejeitada:', conv.display_name || conv.contact_name, '| Canal:', source, '| Esperado:', activeFilter);
        }
        
        return match;
    });
    
    console.log('[MULTICHANNEL] Total de conversas após filtro:', filtered.length);
    console.log('[MULTICHANNEL] ===== FIM DO FILTRO =====');
    return filtered;
}

/**
 * Enviar mensagem multi-canal
 */
async function sendMultiChannelMessage(contactId, messageText, conversationId) {
    try {
        const formData = new FormData();
        formData.append('contact_id', contactId);
        formData.append('message', messageText);
        formData.append('conversation_id', conversationId);
        
        const response = await fetch('api/chat_send_multichannel.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            console.log(`[MULTICHANNEL] Mensagem enviada via ${data.channel_type}`);
            return data;
        } else {
            throw new Error(data.error || 'Erro ao enviar mensagem');
        }
    } catch (error) {
        console.error('[MULTICHANNEL] Erro:', error);
        throw error;
    }
}

/**
 * Configurar atalhos de teclado para filtros de canal
 */
function setupChannelKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
        // Ctrl/Cmd + número para trocar canal
        if ((e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey) {
            const shortcuts = {
                '1': 'all',
                '2': 'whatsapp',
                '3': 'teams',
                '4': 'telegram',
                '5': 'facebook',
                '6': 'instagram'
            };
            
            if (shortcuts[e.key]) {
                e.preventDefault();
                console.log('[MULTICHANNEL] Atalho acionado: Ctrl+' + e.key);
                filterByChannel(shortcuts[e.key]);
                
                // Atualizar dropdown visualmente
                const channelData = {
                    'all': { icon: 'fas fa-globe', label: 'Todos os Canais' },
                    'whatsapp': { icon: 'fab fa-whatsapp', label: 'WhatsApp' },
                    'teams': { icon: 'fas fa-users', label: 'Microsoft Teams' },
                    'telegram': { icon: 'fab fa-telegram', label: 'Telegram' },
                    'facebook': { icon: 'fab fa-facebook-messenger', label: 'Facebook' },
                    'instagram': { icon: 'fab fa-instagram', label: 'Instagram' }
                };
                
                const channel = shortcuts[e.key];
                if (channelData[channel]) {
                    const channelIcon = document.getElementById('channel-icon');
                    const channelLabel = document.getElementById('channel-label');
                    if (channelIcon) channelIcon.className = channelData[channel].icon;
                    if (channelLabel) channelLabel.textContent = channelData[channel].label;
                }
            }
        }
    });
    
    console.log('[MULTICHANNEL] Atalhos de teclado configurados (Ctrl+1-6)');
}

/**
 * Inicializar sistema multi-canal
 */
function initMultiChannelSystem() {
    console.log('[MULTICHANNEL] Sistema multi-canal inicializado');
    
    // Adicionar estilos CSS
    const style = document.createElement('style');
    style.textContent = `
        .channel-badge {
            animation: channelBadgePulse 2s ease-in-out infinite;
        }
        
        @keyframes channelBadgePulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .message-channel-indicator {
            opacity: 0.8;
            transition: opacity 0.2s;
        }
        
        .message-channel-indicator:hover {
            opacity: 1;
        }
        
        .chat-channel-filter {
            padding: 8px 16px;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: #6b7280;
        }
        
        .chat-channel-filter:hover {
            background: #f9fafb;
            border-color: #10b981;
        }
        
        .chat-channel-filter.active {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border-color: #10b981;
        }
        
        .chat-channel-filter i {
            font-size: 14px;
        }
        
        .channel-count {
            margin-left: auto;
            background: #e5e7eb;
            color: #6b7280;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .channel-dropdown-item.active .channel-count {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }
        
        :root[data-theme="dark"] .channel-count {
            background: #374151;
            color: #9ca3af;
        }
        
        :root[data-theme="dark"] .channel-dropdown-item.active .channel-count {
            background: rgba(16, 185, 129, 0.3);
            color: #6ee7b7;
        }
    `;
    document.head.appendChild(style);
    
    // Configurar atalhos de teclado
    setupChannelKeyboardShortcuts();
    
    // Restaurar filtro salvo
    setTimeout(() => {
        restoreSavedChannelFilter();
    }, 500);
}

// Exportar funções globalmente
window.channelConfig = channelConfig;
window.getChannelConfig = getChannelConfig;
window.addChannelBadgeToAvatar = addChannelBadgeToAvatar;
window.addChannelIndicatorToName = addChannelIndicatorToName;
window.addChannelIndicatorToMessage = addChannelIndicatorToMessage;
window.addChannelBorderToMessage = addChannelBorderToMessage;
window.updateChatHeaderWithChannel = updateChatHeaderWithChannel;
window.updateInputPlaceholder = updateInputPlaceholder;
window.filterByChannel = filterByChannel;
window.applyChannelFilter = applyChannelFilter;
window.sendMultiChannelMessage = sendMultiChannelMessage;

// Exportar currentChannelFilter como getter/setter para manter sincronizado
Object.defineProperty(window, 'currentChannelFilter', {
    get: function() { return currentChannelFilter; },
    set: function(value) { currentChannelFilter = value; }
});

// Inicializar quando DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMultiChannelSystem);
} else {
    initMultiChannelSystem();
}


// Adicionar animação slideOutRight ao CSS
(function() {
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
})();

// Limpar localStorage ao iniciar (evitar filtros salvos incorretos)
(function() {
    try {
        const savedChannel = localStorage.getItem('wats_selected_channel');
        if (savedChannel && savedChannel !== 'all') {
            console.log('[MULTICHANNEL] LIMPANDO filtro salvo:', savedChannel);
            localStorage.removeItem('wats_selected_channel');
        }
    } catch (e) {
        console.warn('[MULTICHANNEL] Erro ao limpar localStorage:', e);
    }
})();
