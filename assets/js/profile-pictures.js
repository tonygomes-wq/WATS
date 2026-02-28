/**
 * Sistema de Fotos de Perfil do WhatsApp
 * Busca e exibe fotos reais dos contatos
 */

// Fila de requisições para não sobrecarregar a API
const photoQueue = [];
let isProcessingQueue = false;

// Cache de fotos que falharam para não tentar novamente
const failedPhotos = {};

/**
 * Buscar foto de perfil de um contato
 */
async function fetchProfilePicture(phone) {
    try {
        const formData = new FormData();
        formData.append('phone', phone);
        
        const response = await fetch('/api/fetch_profile_picture.php', {
            method: 'POST',
            body: formData
        });
        
        // Se servidor retornou erro, marcar como falha
        if (!response.ok) {
            failedPhotos[phone] = true;
            return null;
        }
        
        const data = await response.json();
        
        if (data.success && data.profile_picture_url) {
            // Atualizar avatar na interface
            updateAvatar(phone, data.profile_picture_url);
            return data.profile_picture_url;
        }
        
        // Marcar como falha para não tentar novamente
        failedPhotos[phone] = true;
        return null;
        
    } catch (error) {
        // Marcar como falha para não tentar novamente
        failedPhotos[phone] = true;
        return null;
    }
}

/**
 * Atualizar avatar na interface
 */
function updateAvatar(phone, pictureUrl) {
    // Salvar no cache global (se existir)
    if (typeof profilePicturesCache !== 'undefined') {
        profilePicturesCache[phone] = pictureUrl;
    }
    
    // Buscar todos os elementos com este telefone
    const elements = document.querySelectorAll(`[data-phone="${phone}"]`);
    
    elements.forEach(element => {
        const avatarContainer = element.querySelector('.avatar-container');
        if (avatarContainer) {
            // Verificar se já tem imagem
            let img = avatarContainer.querySelector('img');
            
            if (!img) {
                // Criar elemento de imagem
                img = document.createElement('img');
                img.alt = 'Avatar';
                img.className = 'w-12 h-12 rounded-full object-cover border-2 border-gray-200';
                
                // Fallback se imagem falhar
                img.onerror = function() {
                    this.style.display = 'none';
                    const fallback = avatarContainer.querySelector('.avatar-fallback');
                    if (fallback) {
                        fallback.style.display = 'flex';
                    }
                };
                
                // Adicionar imagem
                avatarContainer.insertBefore(img, avatarContainer.firstChild);
            }
            
            // Atualizar src
            img.src = pictureUrl;
            img.style.display = 'block';
            
            // Esconder fallback
            const fallback = avatarContainer.querySelector('.avatar-fallback');
            if (fallback) {
                fallback.style.display = 'none';
            }
        }
    });
}

/**
 * Obter iniciais do nome
 */
function getInitials(name) {
    if (!name) return '??';
    return name
        .split(' ')
        .map(n => n[0])
        .join('')
        .substring(0, 2)
        .toUpperCase();
}

/**
 * Gerar cor baseada no nome
 */
function getColorFromName(name) {
    const colors = [
        '#10b981', // green
        '#3b82f6', // blue
        '#8b5cf6', // purple
        '#f59e0b', // amber
        '#ef4444', // red
        '#06b6d4', // cyan
        '#ec4899', // pink
        '#84cc16'  // lime
    ];
    
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    
    return colors[Math.abs(hash) % colors.length];
}

/**
 * Criar avatar (com foto ou fallback)
 */
function createAvatar(contact) {
    const initials = getInitials(contact.name || contact.contact_name);
    const color = getColorFromName(contact.name || contact.contact_name);
    const phone = contact.phone || contact.contact_number;
    
    // Se já tem foto, criar apenas com imagem
    if (contact.profile_picture_url) {
        return `
            <div class="avatar-container relative" data-phone="${phone}">
                <img src="${contact.profile_picture_url}" 
                     alt="${contact.name || contact.contact_name}" 
                     class="w-12 h-12 rounded-full object-cover border-2 border-gray-200"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="avatar-fallback w-12 h-12 rounded-full flex items-center justify-center text-white font-bold text-sm"
                     style="background-color: ${color}; display: none;">
                    ${initials}
                </div>
            </div>
        `;
    }
    
    // Se não tem foto, criar apenas com fallback
    return `
        <div class="avatar-container relative" data-phone="${phone}">
            <div class="avatar-fallback w-12 h-12 rounded-full flex items-center justify-center text-white font-bold text-sm"
                 style="background-color: ${color};">
                ${initials}
            </div>
        </div>
    `;
}

/**
 * Adicionar contato à fila de busca de fotos
 */
function queuePhotoFetch(phone) {
    if (!photoQueue.includes(phone)) {
        photoQueue.push(phone);
    }
    
    if (!isProcessingQueue) {
        processPhotoQueue();
    }
}

/**
 * Processar fila de fotos (em lotes de 3 para ser mais rápido)
 */
async function processPhotoQueue() {
    if (photoQueue.length === 0) {
        isProcessingQueue = false;
        return;
    }
    
    isProcessingQueue = true;
    
    // Processar 3 fotos em paralelo para ser mais rápido
    const batch = photoQueue.splice(0, 3);
    
    try {
        await Promise.all(batch.map(phone => fetchProfilePicture(phone)));
    } catch (e) {
        // Ignorar erros silenciosamente
    }
    
    // Aguardar 300ms antes do próximo lote
    setTimeout(() => {
        processPhotoQueue();
    }, 300);
}

/**
 * Buscar fotos de todos os contatos visíveis (limitado a 10 por vez)
 */
function fetchVisibleProfilePictures() {
    const contacts = document.querySelectorAll('[data-phone]');
    let count = 0;
    const maxPhotos = 10; // Limitar para não sobrecarregar
    
    contacts.forEach(contact => {
        if (count >= maxPhotos) return;
        
        const phone = contact.getAttribute('data-phone');
        const hasPhoto = contact.querySelector('img[src*="http"]');
        
        // Só buscar se não tem foto ainda e não está no cache de "não disponível"
        if (!hasPhoto && phone && !failedPhotos[phone]) {
            queuePhotoFetch(phone);
            count++;
        }
    });
}

// Exportar funções
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        fetchProfilePicture,
        updateAvatar,
        createAvatar,
        getInitials,
        getColorFromName,
        fetchVisibleProfilePictures
    };
}
