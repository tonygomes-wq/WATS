/**
 * Sistema melhorado de verificação de conexão WhatsApp
 * Corrige problemas de status inconsistente
 */

class ConnectionChecker {
    constructor() {
        this.checkInterval = null;
        this.lastStatus = null;
        this.isChecking = false;
    }

    /**
     * Inicializar verificação automática
     */
    init() {
        this.checkConnectionStatus();
        
        // Verificar a cada 30 segundos
        this.checkInterval = setInterval(() => {
            this.checkConnectionStatus();
        }, 30000);
        
        // Parar verificação quando sair da página
        window.addEventListener('beforeunload', () => {
            this.stop();
        });
    }

    /**
     * Parar verificação automática
     */
    stop() {
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
            this.checkInterval = null;
        }
    }

    /**
     * Verificar status da conexão
     */
    async checkConnectionStatus() {
        if (this.isChecking) return;
        
        this.isChecking = true;
        
        try {
            const response = await fetch('/fix_connection_status.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json();
            this.handleStatusResponse(data);
            
        } catch (error) {
            console.error('Erro ao verificar status:', error);
            this.showConnectionError(error.message);
        } finally {
            this.isChecking = false;
        }
    }

    /**
     * Processar resposta do status
     */
    handleStatusResponse(data) {
        if (!data.success) {
            this.showConnectionError(data.error || 'Erro desconhecido');
            return;
        }

        const status = data.status;
        
        // Só atualizar se o status mudou
        if (this.lastStatus !== status) {
            this.lastStatus = status;
            this.updateConnectionDisplay(data);
        }
    }

    /**
     * Atualizar display de conexão
     */
    updateConnectionDisplay(data) {
        // Remover alertas antigos de conexão
        this.removeOldAlerts();
        
        if (data.connected) {
            this.showConnectedStatus(data);
        } else {
            this.showDisconnectedStatus(data);
        }
        
        // Atualizar botões de ação
        this.updateActionButtons(data.can_send);
    }

    /**
     * Mostrar status conectado
     */
    showConnectedStatus(data) {
        const alertHtml = `
            <div class="connection-alert bg-green-50 border border-green-200 rounded-lg p-4 mb-4" id="connectionAlert">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                    <div>
                        <h4 class="font-bold text-green-800">WhatsApp Conectado</h4>
                        <p class="text-green-700 text-sm">${data.message}</p>
                        <p class="text-green-600 text-xs mt-1">Instância: ${data.instance}</p>
                    </div>
                </div>
            </div>
        `;
        
        this.insertAlert(alertHtml);
        
        // Esconder alertas de erro do navegador
        this.suppressBrowserAlerts();
    }

    /**
     * Mostrar status desconectado
     */
    showDisconnectedStatus(data) {
        let alertClass, iconClass, titleColor, textColor;
        
        switch (data.status) {
            case 'connecting':
                alertClass = 'bg-yellow-50 border-yellow-200';
                iconClass = 'fas fa-clock text-yellow-500';
                titleColor = 'text-yellow-800';
                textColor = 'text-yellow-700';
                break;
            default:
                alertClass = 'bg-red-50 border-red-200';
                iconClass = 'fas fa-exclamation-triangle text-red-500';
                titleColor = 'text-red-800';
                textColor = 'text-red-700';
        }
        
        const alertHtml = `
            <div class="connection-alert ${alertClass} border rounded-lg p-4 mb-4" id="connectionAlert">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="${iconClass} text-xl mr-3"></i>
                        <div>
                            <h4 class="font-bold ${titleColor}">WhatsApp Desconectado</h4>
                            <p class="${textColor} text-sm">${data.message}</p>
                            <p class="text-gray-600 text-xs mt-1">Instância: ${data.instance}</p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="window.location.href='/my_instance.php'" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">
                            <i class="fas fa-qrcode mr-1"></i>Conectar
                        </button>
                        <button onclick="connectionChecker.checkConnectionStatus()" 
                                class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded text-sm">
                            <i class="fas fa-sync mr-1"></i>Verificar
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        this.insertAlert(alertHtml);
    }

    /**
     * Mostrar erro de conexão
     */
    showConnectionError(error) {
        const alertHtml = `
            <div class="connection-alert bg-red-50 border border-red-200 rounded-lg p-4 mb-4" id="connectionAlert">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 text-xl mr-3"></i>
                    <div>
                        <h4 class="font-bold text-red-800">Erro de Verificação</h4>
                        <p class="text-red-700 text-sm">${error}</p>
                    </div>
                </div>
            </div>
        `;
        
        this.insertAlert(alertHtml);
    }

    /**
     * Inserir alerta na página
     */
    insertAlert(html) {
        const container = document.querySelector('.max-w-4xl, .container, main') || document.body;
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        
        // Inserir no início do container
        if (container.firstChild) {
            container.insertBefore(tempDiv.firstChild, container.firstChild);
        } else {
            container.appendChild(tempDiv.firstChild);
        }
    }

    /**
     * Remover alertas antigos
     */
    removeOldAlerts() {
        const oldAlerts = document.querySelectorAll('.connection-alert');
        oldAlerts.forEach(alert => alert.remove());
    }

    /**
     * Atualizar botões de ação
     */
    updateActionButtons(canSend) {
        const dispatchButtons = document.querySelectorAll('[data-requires-connection]');
        
        dispatchButtons.forEach(button => {
            if (canSend) {
                button.disabled = false;
                button.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                button.disabled = true;
                button.classList.add('opacity-50', 'cursor-not-allowed');
            }
        });
    }

    /**
     * Suprimir alertas automáticos do navegador
     */
    suppressBrowserAlerts() {
        // Já implementado no dispatch.php, mas garantir que está ativo
        if (typeof window.originalAlert === 'undefined') {
            window.originalAlert = window.alert;
            window.originalConfirm = window.confirm;
            
            window.alert = function(message) {
                if (message && (
                    message.includes('WhatsApp não está conectado') ||
                    message.includes('Gere um novo QR Code') ||
                    message.includes('instância não está conectada')
                )) {
                    console.log('Alerta suprimido:', message);
                    return;
                }
                return window.originalAlert.call(this, message);
            };
            
            window.confirm = function(message) {
                if (message && (
                    message.includes('WhatsApp não está conectado') ||
                    message.includes('Gere um novo QR Code')
                )) {
                    console.log('Confirm suprimido:', message);
                    return false;
                }
                return window.originalConfirm.call(this, message);
            };
        }
    }
}

// Inicializar automaticamente quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    window.connectionChecker = new ConnectionChecker();
    window.connectionChecker.init();
});

// Verificar novamente quando a página ficar visível
document.addEventListener('visibilitychange', function() {
    if (!document.hidden && window.connectionChecker) {
        setTimeout(() => {
            window.connectionChecker.checkConnectionStatus();
        }, 1000);
    }
});
