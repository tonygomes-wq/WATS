/**
 * VoIP Settings - Configurações do Provedor VoIP
 */

// Salvar configurações do provedor
document.getElementById('provider-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    
    try {
        const response = await fetch('/api/voip/save_provider.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Configurações salvas com sucesso!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(result.error || 'Erro ao salvar configurações', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Erro ao salvar configurações', 'error');
    }
});

// Testar conexão VoIP
async function testVoIPConnection() {
    const btn = event.target.closest('button');
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testando...';
    btn.disabled = true;
    
    try {
        const response = await fetch('/api/voip/test_connection.php');
        const result = await response.json();
        
        if (result.success) {
            showNotification('Conexão estabelecida com sucesso!', 'success');
        } else {
            showNotification(result.error || 'Falha na conexão', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Erro ao testar conexão', 'error');
    } finally {
        btn.innerHTML = originalHTML;
        btn.disabled = false;
    }
}

// Criar conta VoIP do usuário
async function createVoIPAccount() {
    if (!confirm('Deseja criar sua conta VoIP? Um ramal será gerado automaticamente.')) {
        return;
    }
    
    try {
        const response = await fetch('/api/voip/create_account.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(`Conta criada! Ramal: ${result.extension}`, 'success');
            
            // Mostrar senha SIP em modal
            showPasswordModal(result.extension, result.sip_password);
            
            setTimeout(() => location.reload(), 3000);
        } else {
            showNotification(result.error || 'Erro ao criar conta', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Erro ao criar conta', 'error');
    }
}

// Regenerar senha SIP
async function regeneratePassword() {
    if (!confirm('Deseja regenerar a senha SIP? A senha atual será invalidada.')) {
        return;
    }
    
    try {
        const response = await fetch('/api/voip/regenerate_password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Senha regenerada com sucesso!', 'success');
            showPasswordModal(result.extension, result.new_password);
        } else {
            showNotification(result.error || 'Erro ao regenerar senha', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Erro ao regenerar senha', 'error');
    }
}

// Excluir conta VoIP
async function deleteAccount() {
    if (!confirm('Deseja realmente excluir sua conta VoIP? Esta ação não pode ser desfeita.')) {
        return;
    }
    
    try {
        const response = await fetch('/api/voip/delete_account.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' }
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Conta excluída com sucesso!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(result.error || 'Erro ao excluir conta', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Erro ao excluir conta', 'error');
    }
}

// Mostrar modal com senha SIP
function showPasswordModal(extension, password) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">
                <i class="fas fa-key text-yellow-600 mr-2"></i>
                Credenciais SIP
            </h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ramal</label>
                    <div class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg font-mono flex items-center justify-between">
                        <span>${extension}</span>
                        <button onclick="copyToClipboard('${extension}')" class="text-blue-600 hover:text-blue-700">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Senha SIP</label>
                    <div class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg font-mono flex items-center justify-between">
                        <span class="break-all">${password}</span>
                        <button onclick="copyToClipboard('${password}')" class="text-blue-600 hover:text-blue-700">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg p-4">
                    <p class="text-sm text-yellow-800 dark:text-yellow-200">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <strong>Importante:</strong> Guarde esta senha em local seguro. Ela não será exibida novamente.
                    </p>
                </div>
            </div>
            <div class="mt-6 flex justify-end">
                <button onclick="this.closest('.fixed').remove()" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium transition-colors">
                    Entendi
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}

// Copiar para clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showNotification('Copiado para área de transferência!', 'success');
    });
}

// Reset form
function resetForm() {
    if (confirm('Deseja descartar as alterações?')) {
        location.reload();
    }
}

// Notificações
function showNotification(message, type = 'info') {
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        info: 'bg-blue-500',
        warning: 'bg-yellow-500'
    };
    
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50 animate-slide-in`;
    notification.innerHTML = `
        <div class="flex items-center gap-2">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slide-out 0.3s ease-out';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}
