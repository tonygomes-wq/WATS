<?php
/**
 * Página de Configuração de Instância WhatsApp do Atendente
 * Permite que o atendente conecte seu próprio WhatsApp
 * Baseado no my_instance.php do supervisor
 */

$page_title = 'Minha Instância WhatsApp';
require_once 'includes/header_spa.php';

// Verificar se é atendente (pode estar em user_id ou attendant_id dependendo do login)
$attendantId = $_SESSION['attendant_id'] ?? $_SESSION['user_id'] ?? null;

if (!$attendantId) {
    header('Location: login.php');
    exit;
}

// Buscar dados do atendente
// Verificar se tabela attendant_instances existe
$tableExists = false;
try {
    $checkTable = $pdo->query("SHOW TABLES LIKE 'attendant_instances'");
    $tableExists = $checkTable->rowCount() > 0;
} catch (Exception $e) {
    $tableExists = false;
}

if ($tableExists) {
    $stmt = $pdo->prepare("
        SELECT su.*, ai.instance_name, ai.status as instance_status, 
               ai.phone_number, ai.phone_name, ai.connected_at, ai.last_activity
        FROM supervisor_users su
        LEFT JOIN attendant_instances ai ON su.id = ai.attendant_id
        WHERE su.id = ?
    ");
} else {
    $stmt = $pdo->prepare("
        SELECT su.*, NULL as instance_name, NULL as instance_status, 
               NULL as phone_number, NULL as phone_name, NULL as connected_at, NULL as last_activity
        FROM supervisor_users su
        WHERE su.id = ?
    ");
}
$stmt->execute([$attendantId]);
$attendant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attendant) {
    header('Location: login.php');
    exit;
}

// Verificar se tem permissão para configurar instância
if (!$attendant['use_own_instance'] || !$attendant['instance_config_allowed']) {
    header('Location: chat.php');
    exit;
}
?>

<div class="refined-container">
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-800">
                <i class="fas fa-plug mr-2 text-green-600"></i>Minha Instância WhatsApp
            </h1>
        </div>
        
        <?php if (empty($attendant['instance_name'])): ?>
        <!-- Criar nova instância -->
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-6">
            <h3 class="font-bold text-gray-800 mb-4">
                <i class="fas fa-whatsapp mr-2 text-green-600"></i>Conectar WhatsApp
            </h3>
            
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="instance_name">
                    <i class="fas fa-tag mr-2"></i>Nome da sua instância
                </label>
                <input 
                    type="text" 
                    id="instance_name" 
                    name="instance_name" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                    placeholder="Ex: meu-whatsapp, celular-trabalho, etc."
                    maxlength="50"
                >
                <p class="text-xs text-gray-500 mt-1">
                    Escolha um nome único para identificar sua instância (apenas letras, números e hífen)
                </p>
            </div>
            
            <div class="text-center">
                <button 
                    onclick="createInstanceAndQR()" 
                    class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg transition duration-200"
                    id="createQRBtn"
                >
                    <i class="fas fa-qrcode mr-2"></i>Gerar QR Code para Conectar
                </button>
            </div>
            
            <div id="qrCodeContainer" class="text-center mt-6">
                <!-- QR Code será exibido aqui -->
            </div>
        </div>
        <?php else: ?>
        <!-- Instância já configurada -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
            <h3 class="font-bold text-blue-800 mb-3">
                <i class="fas fa-whatsapp mr-2"></i>Sua Instância WhatsApp
            </h3>
            <p class="text-sm text-blue-700 mb-4">
                <strong>Nome da Instância:</strong> <?php echo htmlspecialchars($attendant['instance_name']); ?>
            </p>
            
            <div id="instanceStatus" class="mb-4">
                <!-- Status será carregado via JavaScript -->
            </div>
            
            <!-- Botão para gerar QR Code -->
            <div class="text-center">
                <button 
                    onclick="generateQRForExisting()" 
                    class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg transition duration-200"
                    id="generateQRBtn"
                >
                    <i class="fas fa-qrcode mr-2"></i>Gerar QR Code para Conectar
                </button>
            </div>
            
            <div id="qrCodeContainer" class="text-center mt-6">
                <!-- QR Code será exibido aqui -->
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Instruções -->
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-6">
            <h3 class="font-bold text-gray-800 mb-3">
                <i class="fas fa-info-circle mr-2 text-blue-600"></i>Como conectar seu WhatsApp
            </h3>
            <ol class="list-decimal list-inside space-y-2 text-gray-700 text-sm">
                <li>Digite um nome para sua instância (se ainda não tiver uma)</li>
                <li>Clique em "Gerar QR Code para Conectar"</li>
                <li>Abra o WhatsApp no seu celular</li>
                <li>Toque no menu (⋮) > "Dispositivos conectados"</li>
                <li>Toque em "Conectar dispositivo"</li>
                <li>Escaneie o QR Code exibido na tela</li>
            </ol>
            <p class="text-xs text-gray-500 mt-4">
                <i class="fas fa-exclamation-triangle mr-1 text-yellow-500"></i>
                <strong>Importante:</strong> Mantenha seu celular conectado à internet para que o WhatsApp funcione corretamente.
            </p>
        </div>
    </div>
</div>
</div>

<script>
// Carregar status da instância ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($attendant['instance_name'])): ?>
    loadInstanceStatus();
    <?php endif; ?>
});

function loadInstanceStatus() {
    fetch('/api/attendant_instance.php?action=check_connection')
        .then(response => response.json())
        .then(data => {
            updateInstanceStatus(data);
        })
        .catch(error => {
            console.error('Erro ao carregar status:', error);
        });
}

function updateInstanceStatus(data) {
    const statusDiv = document.getElementById('instanceStatus');
    if (!statusDiv) return;
    
    if (data.success) {
        const status = data.status;
        let statusClass = 'bg-yellow-50 border-yellow-200 text-yellow-700';
        let statusIcon = 'fas fa-clock';
        let statusText = 'Status desconhecido';
        
        switch(status) {
            case 'connected':
            case 'open':
                statusClass = 'bg-green-50 border-green-200 text-green-700';
                statusIcon = 'fas fa-check-circle';
                statusText = 'Conectado e pronto para enviar mensagens!';
                break;
            case 'disconnected':
            case 'close':
                statusClass = 'bg-red-50 border-red-200 text-red-700';
                statusIcon = 'fas fa-times-circle';
                statusText = 'Desconectado - Escaneie o QR Code para conectar';
                break;
            case 'connecting':
                statusClass = 'bg-blue-50 border-blue-200 text-blue-700';
                statusIcon = 'fas fa-spinner fa-spin';
                statusText = 'Conectando... Aguarde alguns segundos';
                break;
        }
        
        statusDiv.innerHTML = `
            <div class="${statusClass} border rounded-lg p-4">
                <h3 class="font-bold mb-2">
                    <i class="${statusIcon} mr-2"></i>Status da Instância
                </h3>
                <p class="text-sm">${statusText}</p>
                ${data.phone_number ? `<p class="text-xs mt-1">Número: ${data.phone_number}</p>` : ''}
                <button onclick="loadInstanceStatus()" class="mt-2 text-xs underline">
                    <i class="fas fa-sync-alt mr-1"></i>Atualizar Status
                </button>
            </div>
        `;
    } else {
        statusDiv.innerHTML = `
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <h3 class="font-bold text-gray-800 mb-2">
                    <i class="fas fa-exclamation-circle mr-2"></i>Verificando Status...
                </h3>
                <p class="text-sm text-gray-700">Clique em "Gerar QR Code" para conectar.</p>
            </div>
        `;
    }
}

function createInstanceAndQR() {
    const instanceName = document.getElementById('instance_name').value.trim();
    const btn = document.getElementById('createQRBtn');
    const container = document.getElementById('qrCodeContainer');
    
    // Validar nome da instância
    if (!instanceName) {
        showMessage('error', 'Por favor, digite um nome para sua instância');
        document.getElementById('instance_name').focus();
        return;
    }
    
    // Validar formato do nome (apenas letras, números e hífen)
    if (!/^[a-zA-Z0-9-_]+$/.test(instanceName)) {
        showMessage('error', 'Nome da instância deve conter apenas letras, números, hífen (-) e underscore (_)');
        document.getElementById('instance_name').focus();
        return;
    }
    
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Criando instância...';
    btn.disabled = true;
    
    // Criar instância com nome personalizado
    const formData = new FormData();
    formData.append('action', 'create_and_qr');
    formData.append('instance_name', instanceName);
    
    fetch('/api/attendant_instance.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.qr_code || data.base64) {
                const qrImage = data.qr_code || `data:image/png;base64,${data.base64}`;
                showQRCodeDisplay(container, qrImage, btn, originalText);
            } else {
                showMessage('success', 'Instância criada! Gerando QR Code...');
                setTimeout(() => {
                    generateQRForExisting();
                }, 1000);
            }
        } else {
            showMessage('error', 'Erro: ' + (data.error || data.message || 'Erro desconhecido'));
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    })
    .catch(error => {
        showMessage('error', 'Erro de conexão: ' + error.message);
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

function generateQRForExisting() {
    const btn = document.getElementById('generateQRBtn');
    const container = document.getElementById('qrCodeContainer');
    const originalText = btn.innerHTML;
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Gerando QR Code...';
    btn.disabled = true;
    
    const formData = new FormData();
    formData.append('action', 'generate_qr');
    
    fetch('/api/attendant_instance.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && (data.qr_code || data.base64)) {
            const qrImage = data.qr_code || `data:image/png;base64,${data.base64}`;
            showQRCodeDisplay(container, qrImage, btn, originalText);
        } else {
            container.innerHTML = `
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <strong>Erro ao gerar QR Code:</strong><br>
                    <span class="text-sm">${data.error || data.message || 'Erro desconhecido'}</span>
                </div>
            `;
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    })
    .catch(error => {
        container.innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
                <i class="fas fa-times-circle mr-2"></i>
                <strong>Erro de conexão:</strong><br>
                <span class="text-sm">${error.message}</span>
            </div>
        `;
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

function showQRCodeDisplay(container, qrImage, btn, originalText) {
    container.innerHTML = `
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
            <h4 class="font-bold text-blue-800 mb-3">
                <i class="fas fa-mobile-alt mr-2"></i>Escaneie com seu WhatsApp
            </h4>
            <div class="bg-white border rounded-lg p-4 inline-block mb-4">
                <img src="${qrImage}" 
                     alt="QR Code WhatsApp" 
                     class="mx-auto"
                     style="max-width: 250px;">
            </div>
            <div class="text-sm text-blue-700">
                <p class="mb-2"><strong>Como conectar:</strong></p>
                <ol class="text-left list-decimal list-inside space-y-1">
                    <li>Abra o WhatsApp no seu celular</li>
                    <li>Toque no menu (⋮) > "Dispositivos conectados"</li>
                    <li>Toque em "Conectar dispositivo"</li>
                    <li>Escaneie este QR Code</li>
                </ol>
                <p class="text-xs text-gray-500 mt-3">
                    O QR Code expira em alguns minutos. A página será recarregada após a conexão.
                </p>
            </div>
        </div>
    `;
    
    btn.innerHTML = originalText;
    btn.disabled = false;
    
    // Auto-refresh para detectar conexão
    const statusInterval = setInterval(() => {
        fetch('/api/attendant_instance.php?action=check_connection')
            .then(response => response.json())
            .then(statusData => {
                if (statusData.success && (statusData.status === 'connected' || statusData.status === 'open')) {
                    clearInterval(statusInterval);
                    showMessage('success', 'WhatsApp conectado com sucesso! Recarregando página...');
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                }
            })
            .catch(error => console.log('Status check error:', error));
    }, 3000);
    
    // Parar verificação após 5 minutos
    setTimeout(() => {
        clearInterval(statusInterval);
    }, 300000);
}

function showMessage(type, message) {
    const colors = {
        success: 'bg-green-100 border-green-400 text-green-700',
        error: 'bg-red-100 border-red-400 text-red-700',
        warning: 'bg-yellow-100 border-yellow-400 text-yellow-700'
    };
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-times-circle',
        warning: 'fa-exclamation-triangle'
    };
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `${colors[type]} border px-4 py-3 rounded relative mb-4`;
    alertDiv.innerHTML = `
        <span class="flex items-center">
            <i class="fas ${icons[type]} mr-2"></i>
            ${message}
        </span>
    `;
    
    const container = document.querySelector('.max-w-4xl');
    container.insertBefore(alertDiv, container.firstChild);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}
</script>

<?php include 'includes/footer_spa.php'; ?>
