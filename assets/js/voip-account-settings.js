/**
 * VoIP Account Settings - JavaScript
 * Configurações de conta SIP
 */

class VoIPAccountSettings {
    constructor() {
        this.init();
    }
    
    init() {
        console.log('[VoIP Account] Inicializando...');
        this.setupEventListeners();
        this.loadFormData();
    }
    
    setupEventListeners() {
        // Public Address - mostrar/ocultar campo manual
        const publicAddress = document.getElementById('public_address');
        if (publicAddress) {
            publicAddress.addEventListener('change', (e) => {
                const manualInput = document.getElementById('public_address_manual');
                if (manualInput) {
                    manualInput.style.display = e.target.value === 'manual' ? 'block' : 'none';
                }
            });
        }
        
        // Validação de formulário
        const form = document.getElementById('voip-account-form');
        if (form) {
            form.addEventListener('submit', (e) => this.saveAccount(e));
        }
        
        // Validação em tempo real
        this.setupRealtimeValidation();
    }
    
    setupRealtimeValidation() {
        // Username - apenas alfanuméricos
        const username = document.getElementById('username');
        if (username) {
            username.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/[^a-zA-Z0-9_]/g, '');
            });
        }
        
        // Domain - validar formato
        const domain = document.getElementById('domain');
        if (domain) {
            domain.addEventListener('blur', (e) => {
                if (e.target.value && !this.isValidDomain(e.target.value)) {
                    this.showFieldError(e.target, 'Domínio inválido');
                } else {
                    this.clearFieldError(e.target);
                }
            });
        }
        
        // SIP Server - validar formato
        const sipServer = document.getElementById('sip_server');
        if (sipServer) {
            sipServer.addEventListener('blur', (e) => {
                if (e.target.value && !this.isValidHost(e.target.value)) {
                    this.showFieldError(e.target, 'Servidor inválido');
                } else {
                    this.clearFieldError(e.target);
                }
            });
        }
    }
    
    loadFormData() {
        // Dados já vêm do PHP, apenas ajustar UI
        const publicAddress = document.getElementById('public_address');
        if (publicAddress && publicAddress.value === 'manual') {
            document.getElementById('public_address_manual').style.display = 'block';
        }
    }
    
    async saveAccount(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        
        // Validar campos obrigatórios
        if (!this.validateForm(formData)) {
            return;
        }
        
        // Mostrar loading
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Salvando...';
        submitBtn.disabled = true;
        
        try {
            const response = await fetch('/api/voip/save_account.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification('Conta salva com sucesso!', 'success');
                
                // Aguardar 1 segundo e redirecionar
                setTimeout(() => {
                    window.location.href = '/voip_dialer.php';
                }, 1000);
            } else {
                this.showNotification('Erro: ' + data.message, 'error');
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }
        } catch (error) {
            console.error('[VoIP Account] Erro ao salvar:', error);
            this.showNotification('Erro ao salvar conta', 'error');
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    }
    
    validateForm(formData) {
        const required = ['account_name', 'sip_server', 'username', 'domain', 'transport'];
        
        for (const field of required) {
            if (!formData.get(field)) {
                this.showNotification(`Campo obrigatório: ${field}`, 'error');
                document.getElementById(field)?.focus();
                return false;
            }
        }
        
        // Validar password apenas se for nova conta
        const accountId = formData.get('account_id');
        if (!accountId && !formData.get('password')) {
            this.showNotification('Senha é obrigatória para nova conta', 'error');
            document.getElementById('password')?.focus();
            return false;
        }
        
        // Validar formato de domínio
        const domain = formData.get('domain');
        if (!this.isValidDomain(domain)) {
            this.showNotification('Domínio inválido', 'error');
            document.getElementById('domain')?.focus();
            return false;
        }
        
        // Validar formato de servidor
        const sipServer = formData.get('sip_server');
        if (!this.isValidHost(sipServer)) {
            this.showNotification('Servidor SIP inválido', 'error');
            document.getElementById('sip_server')?.focus();
            return false;
        }
        
        return true;
    }
    
    isValidDomain(domain) {
        const regex = /^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?(\.[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?)*$/;
        return regex.test(domain);
    }
    
    isValidHost(host) {
        // Aceitar IP ou hostname
        const ipRegex = /^(\d{1,3}\.){3}\d{1,3}$/;
        const hostnameRegex = /^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?(\.[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?)*$/;
        
        return ipRegex.test(host) || hostnameRegex.test(host);
    }
    
    showFieldError(field, message) {
        field.style.borderColor = '#ef4444';
        
        // Remover erro anterior
        const existingError = field.parentElement.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }
        
        // Adicionar mensagem de erro
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error';
        errorDiv.style.color = '#ef4444';
        errorDiv.style.fontSize = '11px';
        errorDiv.style.marginTop = '4px';
        errorDiv.textContent = message;
        field.parentElement.appendChild(errorDiv);
    }
    
    clearFieldError(field) {
        field.style.borderColor = '';
        const errorDiv = field.parentElement.querySelector('.field-error');
        if (errorDiv) {
            errorDiv.remove();
        }
    }
    
    showNotification(message, type = 'info') {
        console.log(`[VoIP Account] ${type.toUpperCase()}: ${message}`);
        
        // Criar toast notification
        const toast = document.createElement('div');
        toast.className = `voip-toast voip-toast-${type}`;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            animation: slideIn 0.3s ease-out;
        `;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
}

// Funções globais
function togglePasswordVisibility() {
    const input = document.getElementById('password');
    const icon = document.querySelector('.voip-toggle-password i');
    
    if (input && icon) {
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
}

async function deleteAccount(accountId) {
    if (!confirm('Tem certeza que deseja excluir esta conta VoIP?\n\nEsta ação não pode ser desfeita.')) {
        return;
    }
    
    try {
        const response = await fetch('/api/voip/delete_account.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ account_id: accountId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Conta excluída com sucesso!');
            window.location.href = '/voip_dialer.php';
        } else {
            alert('Erro ao excluir conta: ' + data.message);
        }
    } catch (error) {
        console.error('[VoIP Account] Erro ao excluir:', error);
        alert('Erro ao excluir conta');
    }
}

function closeDialog() {
    if (confirm('Descartar alterações?')) {
        window.location.href = '/voip_dialer.php';
    }
}

// Inicializar
let voipAccountSettings;
document.addEventListener('DOMContentLoaded', () => {
    voipAccountSettings = new VoIPAccountSettings();
    window.voipAccountSettings = voipAccountSettings;
});

// Adicionar animações CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
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
