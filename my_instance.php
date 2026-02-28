<?php
$page_title = 'Configurar Minha Instância';
require_once 'includes/header_spa.php';
require_once 'includes/webhook_config.php';

$user_id = $_SESSION['user_id'];

// Buscar dados atuais do usuário
$stmt = $pdo->prepare("SELECT evolution_instance, evolution_token, whatsapp_provider, zapi_instance_id, zapi_token, meta_phone_number_id, meta_business_account_id, meta_app_id, meta_app_secret, meta_permanent_token, meta_webhook_verify_token, meta_api_version FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();
$selected_provider = $user_data['whatsapp_provider'] ?? 'evolution';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $provider = $_POST['whatsapp_provider'] ?? 'evolution';
    
    if ($provider === 'meta') {
        $meta_phone_number_id = sanitize($_POST['meta_phone_number_id'] ?? '');
        $meta_business_account_id = sanitize($_POST['meta_business_account_id'] ?? '');
        $meta_app_id = sanitize($_POST['meta_app_id'] ?? '');
        $meta_app_secret = trim($_POST['meta_app_secret'] ?? '');
        $meta_permanent_token = trim($_POST['meta_permanent_token'] ?? '');
        $meta_webhook_verify_token = trim($_POST['meta_webhook_verify_token'] ?? '');
        $meta_api_version = sanitize($_POST['meta_api_version'] ?? 'v19.0');
        $meta_api_version = $meta_api_version ?: 'v19.0';
        
        $user_data['meta_phone_number_id'] = $meta_phone_number_id;
        $user_data['meta_business_account_id'] = $meta_business_account_id;
        $user_data['meta_app_id'] = $meta_app_id;
        $user_data['meta_app_secret'] = $meta_app_secret;
        $user_data['meta_permanent_token'] = $meta_permanent_token;
        $user_data['meta_webhook_verify_token'] = $meta_webhook_verify_token;
        $user_data['meta_api_version'] = $meta_api_version;
        
        $requiredMeta = [
            'meta_phone_number_id' => 'ID do Número do WhatsApp',
            'meta_business_account_id' => 'ID da Conta Comercial',
            'meta_permanent_token' => 'Token de Acesso Permanente',
            'meta_webhook_verify_token' => 'Token de Verificação do Webhook'
        ];
        
        $missing = [];
        foreach ($requiredMeta as $field => $label) {
            if (empty($$field)) {
                $missing[] = $label;
            }
        }
        
        if (!empty($missing)) {
            setError('Preencha os campos obrigatórios: ' . implode(', ', $missing));
        } else {
            $stmt = $pdo->prepare("UPDATE users SET whatsapp_provider = 'meta', meta_phone_number_id = ?, meta_business_account_id = ?, meta_app_id = ?, meta_app_secret = ?, meta_permanent_token = ?, meta_webhook_verify_token = ?, meta_api_version = ? WHERE id = ?");
            if ($stmt->execute([
                $meta_phone_number_id,
                $meta_business_account_id,
                $meta_app_id,
                $meta_app_secret,
                $meta_permanent_token,
                $meta_webhook_verify_token,
                $meta_api_version,
                $user_id
            ])) {
                setSuccess('Configurações da API oficial salvas com sucesso!');
                header('Location: /my_instance.php');
                exit;
            } else {
                setError('Erro ao salvar configurações da API oficial.');
            }
        }
        
        $selected_provider = 'meta';
    } elseif ($provider === 'zapi') {
        // Processar Z-API
        $zapi_instance_id = sanitize($_POST['zapi_instance_id'] ?? '');
        $zapi_token = sanitize($_POST['zapi_token'] ?? '');
        
        if (empty($zapi_instance_id) || empty($zapi_token)) {
            setError('Por favor, preencha o Instance ID e Token da Z-API.');
        } else {
            $stmt = $pdo->prepare("UPDATE users SET whatsapp_provider = 'zapi', zapi_instance_id = ?, zapi_token = ? WHERE id = ?");
            if ($stmt->execute([$zapi_instance_id, $zapi_token, $user_id])) {
                setSuccess('Configurações da Z-API salvas com sucesso! ✅');
                header('Location: /my_instance.php');
                exit;
            } else {
                setError('Erro ao salvar configurações da Z-API.');
            }
        }
        
        $selected_provider = 'zapi';
    } else {
        $evolution_instance = sanitize($_POST['evolution_instance'] ?? '');
        $evolution_token = sanitize($_POST['evolution_token'] ?? '');
        
        if (empty($evolution_instance) || empty($evolution_token)) {
            setError('Por favor, preencha todos os campos.');
        } else {
            $stmt = $pdo->prepare("UPDATE users SET evolution_instance = ?, evolution_token = ?, whatsapp_provider = 'evolution' WHERE id = ?");
            if ($stmt->execute([$evolution_instance, $evolution_token, $user_id])) {
                
                // Configurar webhook automaticamente
                $webhookResult = configureWebhookForInstance(
                    $evolution_instance, 
                    $evolution_token, 
                    EVOLUTION_API_URL
                );
                
                if ($webhookResult['success']) {
                    setSuccess('Instância e webhook configurados com sucesso! ✅');
                    error_log("WEBHOOK AUTO-CONFIG: Sucesso para instância $evolution_instance");
                } else {
                    setSuccess('Instância configurada! ⚠️ Webhook precisa ser configurado manualmente.');
                    error_log("WEBHOOK AUTO-CONFIG: Falha para instância $evolution_instance - " . ($webhookResult['message'] ?? 'Erro desconhecido'));
                }
                
                header('Location: /my_instance.php');
                exit;
            } else {
                setError('Erro ao salvar configurações.');
            }
        }
        
        $selected_provider = 'evolution';
    }
}
?>

<div class="refined-container">
<div class="max-w-4xl mx-auto">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">
                <i class="fas fa-plug mr-2 text-green-600"></i>Configurar Minha Instância
            </h1>
        </div>
        
        <form method="POST" id="providerForm" class="border border-gray-200 dark:border-gray-700 rounded-lg p-6 mb-6">
            <div class="mb-4">
                <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2" for="providerSelect">
                    <i class="fas fa-exchange-alt mr-2"></i>Selecione o provedor de envio
                </label>
                <select 
                    name="whatsapp_provider" 
                    id="providerSelect" 
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                >
                    <option value="evolution" <?php echo $selected_provider === 'evolution' ? 'selected' : ''; ?>>Evolution API (Baileys)</option>
                    <option value="zapi" <?php echo $selected_provider === 'zapi' ? 'selected' : ''; ?>>Z-API</option>
                    <option value="meta" <?php echo $selected_provider === 'meta' ? 'selected' : ''; ?>>API Oficial do WhatsApp (Meta)</option>
                </select>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Mantenha a Evolution API para continuar usando instâncias com QR Code ou selecione a API oficial para usar credenciais fornecidas pela Meta.</p>
            </div>

            <input type="hidden" id="evolution_instance" name="evolution_instance" value="<?php echo htmlspecialchars($user_data['evolution_instance'] ?? ''); ?>">
            <input type="hidden" id="evolution_token" name="evolution_token" value="<?php echo htmlspecialchars($user_data['evolution_token'] ?? ''); ?>">

            <div id="metaSettings" class="<?php echo $selected_provider === 'meta' ? '' : 'hidden'; ?>">
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">ID do Número do WhatsApp *</label>
                        <input type="text" name="meta_phone_number_id" value="<?php echo htmlspecialchars($user_data['meta_phone_number_id'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Disponível no painel da Meta como <strong>Phone Number ID</strong>.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">ID da Conta Comercial *</label>
                        <input type="text" name="meta_business_account_id" value="<?php echo htmlspecialchars($user_data['meta_business_account_id'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Identificador do WhatsApp Business Account (WABA).</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">App ID (opcional)</label>
                        <input type="text" name="meta_app_id" value="<?php echo htmlspecialchars($user_data['meta_app_id'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">App Secret (opcional)</label>
                        <input type="text" name="meta_app_secret" value="<?php echo htmlspecialchars($user_data['meta_app_secret'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Token de Acesso Permanente *</label>
                        <textarea name="meta_permanent_token" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="EAAJ..."><?php echo htmlspecialchars($user_data['meta_permanent_token'] ?? ''); ?></textarea>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Guarde este token com segurança. Use tokens de longa duração.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Token de Verificação do Webhook *</label>
                        <input type="text" name="meta_webhook_verify_token" value="<?php echo htmlspecialchars($user_data['meta_webhook_verify_token'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Use o mesmo token configurado no Meta Developers.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Versão da API</label>
                        <input type="text" name="meta_api_version" value="<?php echo htmlspecialchars($user_data['meta_api_version'] ?? 'v19.0'); ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Ex: v19.0. Atualize conforme a versão da Graph API utilizada.</p>
                    </div>
                </div>
            </div>

            <div id="zapiSettings" class="<?php echo $selected_provider === 'zapi' ? '' : 'hidden'; ?>">
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-4">
                    <h4 class="font-semibold text-blue-800 dark:text-blue-300 mb-2">
                        <i class="fas fa-info-circle mr-2"></i>Sobre a Z-API
                    </h4>
                    <p class="text-sm text-blue-700 dark:text-blue-400">
                        A Z-API é um serviço gerenciado que facilita a integração com WhatsApp. 
                        Você precisa ter uma conta ativa na Z-API para usar este provider.
                    </p>
                </div>
                
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Instance ID *</label>
                        <input type="text" name="zapi_instance_id" value="<?php echo htmlspecialchars($user_data['zapi_instance_id'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="Ex: 3F2504E0-4F89-11D3">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Disponível no painel da Z-API</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Token *</label>
                        <input type="text" name="zapi_token" value="<?php echo htmlspecialchars($user_data['zapi_token'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="Token da Z-API">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Token de autenticação da sua instância</p>
                    </div>
                </div>
                
                <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mt-4">
                    <h4 class="font-semibold text-yellow-800 dark:text-yellow-300 mb-2">
                        <i class="fas fa-exclamation-triangle mr-2"></i>Importante
                    </h4>
                    <ul class="text-sm text-yellow-700 dark:text-yellow-400 list-disc list-inside space-y-1">
                        <li>Certifique-se de que sua instância Z-API está ativa</li>
                        <li>O número WhatsApp deve estar conectado no painel da Z-API</li>
                        <li>Configure o webhook no painel da Z-API apontando para este sistema</li>
                    </ul>
                </div>
            </div>

            <div class="flex justify-end mt-6">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-lg transition duration-200">
                    <i class="fas fa-save mr-2"></i>Salvar Configurações
                </button>
            </div>
        </form>

        <div id="evolutionSection" class="<?php echo $selected_provider === 'meta' ? 'hidden' : ''; ?>">
        <!-- Configurar Instância WhatsApp -->
        <?php if (empty($user_data['evolution_instance'])): ?>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6 mb-6">
            <h3 class="font-bold text-gray-800 dark:text-gray-100 mb-4">
                <i class="fas fa-whatsapp mr-2 text-green-600"></i>Conectar WhatsApp
            </h3>
            
            <div class="mb-4">
                <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2" for="instance_name">
                    <i class="fas fa-tag mr-2"></i>Nome da sua instância
                </label>
                <input 
                    type="text" 
                    id="instance_name" 
                    name="instance_name" 
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                    placeholder="Ex: meu-whatsapp, empresa-vendas, etc."
                    maxlength="50"
                >
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Escolha um nome único para identificar sua instância
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
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6 mb-6">
            <h3 class="font-bold text-blue-800 dark:text-blue-300 mb-3">
                <i class="fas fa-whatsapp mr-2"></i>Sua Instância WhatsApp
            </h3>
            <p class="text-sm text-blue-700 dark:text-blue-400 mb-4">
                <strong>Nome da Instância:</strong> <?php echo htmlspecialchars($user_data['evolution_instance']); ?>
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
        
        <!-- Gerenciar Instância Existente -->
        <?php if (!empty($user_data['evolution_instance'])): ?>
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-6 mt-6">
            <h3 class="font-bold text-red-800 dark:text-red-300 mb-3">
                <i class="fas fa-trash mr-2"></i>Gerenciar Instância
            </h3>
            <p class="text-sm text-red-700 dark:text-red-400 mb-4">
                Se sua instância foi deletada no painel da Evolution API ou você quer reconfigurá-la, 
                você pode removê-la aqui e criar uma nova.
            </p>
            <div class="flex space-x-3">
                <button 
                    onclick="checkInstanceExists()" 
                    class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200"
                    id="checkInstanceBtn"
                >
                    <i class="fas fa-search mr-2"></i>Verificar se Existe
                </button>
                <button 
                    onclick="deleteInstance()" 
                    class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200"
                    id="deleteInstanceBtn"
                >
                    <i class="fas fa-trash mr-2"></i>Remover Instância
                </button>
            </div>
        </div>
        <?php endif; ?>
        </div>
        
        <div id="metaSection" class="<?php echo $selected_provider === 'meta' ? '' : 'hidden'; ?>">
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6 mb-6">
                <h3 class="font-bold text-blue-800 dark:text-blue-300 mb-3">
                    <i class="fas fa-shield-alt mr-2"></i>API Oficial do WhatsApp (Meta)
                </h3>
                <p class="text-sm text-blue-700 dark:text-blue-400 mb-4">
                    Utilize as credenciais acima para enviar mensagens diretamente pela API oficial da Meta. Certifique-se de que o número esteja aprovado e que o webhook esteja configurado corretamente.
                </p>
                <div class="grid md:grid-cols-2 gap-4 text-sm">
                    <div class="bg-white dark:bg-gray-700 border border-blue-100 dark:border-gray-600 rounded-lg p-4">
                        <p class="text-gray-500 dark:text-gray-400 uppercase text-xs mb-1">Phone Number ID</p>
                        <p class="font-semibold text-gray-800 dark:text-gray-100"><?php echo !empty($user_data['meta_phone_number_id']) ? htmlspecialchars($user_data['meta_phone_number_id']) : '—'; ?></p>
                    </div>
                    <div class="bg-white dark:bg-gray-700 border border-blue-100 dark:border-gray-600 rounded-lg p-4">
                        <p class="text-gray-500 dark:text-gray-400 uppercase text-xs mb-1">Business Account ID</p>
                        <p class="font-semibold text-gray-800 dark:text-gray-100"><?php echo !empty($user_data['meta_business_account_id']) ? htmlspecialchars($user_data['meta_business_account_id']) : '—'; ?></p>
                    </div>
                    <div class="bg-white dark:bg-gray-700 border border-blue-100 dark:border-gray-600 rounded-lg p-4">
                        <p class="text-gray-500 dark:text-gray-400 uppercase text-xs mb-1">App ID</p>
                        <p class="font-semibold text-gray-800 dark:text-gray-100"><?php echo !empty($user_data['meta_app_id']) ? htmlspecialchars($user_data['meta_app_id']) : '—'; ?></p>
                    </div>
                    <div class="bg-white dark:bg-gray-700 border border-blue-100 dark:border-gray-600 rounded-lg p-4">
                        <p class="text-gray-500 dark:text-gray-400 uppercase text-xs mb-1">Versão da API</p>
                        <p class="font-semibold text-gray-800 dark:text-gray-100"><?php echo htmlspecialchars($user_data['meta_api_version'] ?? 'v19.0'); ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 border border-blue-100 dark:border-gray-700 rounded-lg p-6">
                <h4 class="font-semibold text-gray-800 dark:text-gray-100 mb-2">
                    <i class="fas fa-plug mr-2 text-green-600"></i>URL do Webhook
                </h4>
                <p class="text-sm text-gray-700 dark:text-gray-300 mb-4">
                    Use a URL abaixo no Meta Developers (<strong>Callback URL</strong>). Configure o token de verificação exatamente igual ao preenchido acima.
                </p>
                <div class="bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-4 font-mono text-sm break-all text-gray-800 dark:text-gray-100">
                    <?php echo htmlspecialchars(rtrim(SITE_URL, '/') . '/api/meta_webhook.php'); ?>
                </div>
                <ul class="list-disc list-inside text-sm text-gray-700 dark:text-gray-300 mt-4 space-y-1">
                    <li>Após salvar, realize o teste de verificação diretamente no painel da Meta.</li>
                    <li>Os eventos recebidos são registrados no log (error_log) e podem ser estendidos para CRM/chat.</li>
                    <li>Mantenha o token em segredo. Troque periodicamente por segurança.</li>
                </ul>
            </div>
        </div>

        <div id="zapiSection" class="<?php echo $selected_provider === 'zapi' ? '' : 'hidden'; ?>">
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6 mb-6">
                <h3 class="font-bold text-blue-800 dark:text-blue-300 mb-3">
                    <i class="fas fa-cloud mr-2"></i>Z-API Configurada
                </h3>
                <p class="text-sm text-blue-700 dark:text-blue-400 mb-4">
                    Sua instância Z-API está configurada e pronta para enviar mensagens.
                </p>
                <div class="grid md:grid-cols-2 gap-4 text-sm">
                    <div class="bg-white dark:bg-gray-700 border border-blue-100 dark:border-gray-600 rounded-lg p-4">
                        <p class="text-gray-500 dark:text-gray-400 uppercase text-xs mb-1">Instance ID</p>
                        <p class="font-semibold text-gray-800 dark:text-gray-100"><?php echo !empty($user_data['zapi_instance_id']) ? htmlspecialchars($user_data['zapi_instance_id']) : '—'; ?></p>
                    </div>
                    <div class="bg-white dark:bg-gray-700 border border-blue-100 dark:border-gray-600 rounded-lg p-4">
                        <p class="text-gray-500 dark:text-gray-400 uppercase text-xs mb-1">Status</p>
                        <p class="font-semibold text-green-600 dark:text-green-400">
                            <i class="fas fa-check-circle mr-1"></i>Configurado
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 border border-blue-100 dark:border-gray-700 rounded-lg p-6">
                <h4 class="font-semibold text-gray-800 dark:text-gray-100 mb-2">
                    <i class="fas fa-plug mr-2 text-green-600"></i>Webhook da Z-API
                </h4>
                <p class="text-sm text-gray-700 dark:text-gray-300 mb-4">
                    Configure este webhook no painel da Z-API para receber mensagens:
                </p>
                <div class="bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-4 font-mono text-sm break-all text-gray-800 dark:text-gray-100">
                    <?php echo htmlspecialchars(rtrim(SITE_URL, '/') . '/api/zapi_webhook.php'); ?>
                </div>
                <ul class="list-disc list-inside text-sm text-gray-700 dark:text-gray-300 mt-4 space-y-1">
                    <li>Acesse o painel da Z-API</li>
                    <li>Vá em Configurações > Webhooks</li>
                    <li>Cole a URL acima no campo de webhook</li>
                    <li>Ative os eventos de mensagens</li>
                </ul>
            </div>
        </div>

<script>
// Carregar status da instância ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    initProviderToggle();
    if (getSelectedProvider() === 'evolution') {
        loadInstanceStatus();
    }
});

function loadInstanceStatus() {
    if (!ensureEvolutionProvider()) {
        return;
    }
    // Primeiro verificar se a instância ainda existe na Evolution API
    fetch('/api/instance_manager.php?action=check_exists')
        .then(response => response.json())
        .then(checkData => {
            if (checkData.success && !checkData.exists && checkData.cleaned) {
                // Instância foi deletada e limpeza automática foi feita
                showMessage('warning', 'Sua instância foi removida do painel da Evolution API. A página será recarregada para você configurar uma nova.');
                setTimeout(() => {
                    window.location.reload();
                }, 3000);
                return;
            }
            
            // Se chegou aqui, a instância existe ou não há instância configurada
            // Continuar com verificação de status normal
            fetch('/api/instance_manager.php?action=status')
                .then(response => response.json())
                .then(data => {
                    updateInstanceStatus(data);
                })
                .catch(error => {
                    console.error('Erro ao carregar status:', error);
                });
        })
        .catch(error => {
            console.error('Erro ao verificar existência da instância:', error);
            // Em caso de erro na verificação, continuar com status normal
            fetch('/api/instance_manager.php?action=status')
                .then(response => response.json())
                .then(data => {
                    updateInstanceStatus(data);
                })
                .catch(error => {
                    console.error('Erro ao carregar status:', error);
                });
        });
}

function updateInstanceStatus(data) {
    const statusDiv = document.getElementById('instanceStatus');
    
    if (data.success) {
        const status = data.status;
        let statusClass = 'bg-yellow-50 border-yellow-200 text-yellow-700';
        let statusIcon = 'fas fa-clock';
        let statusText = 'Status desconhecido';
        
        switch(status) {
            case 'open':
                statusClass = 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-700 dark:text-green-300';
                statusIcon = 'fas fa-check-circle';
                statusText = 'Conectado e pronto para enviar mensagens!';
                break;
            case 'close':
                statusClass = 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-700 dark:text-red-300';
                statusIcon = 'fas fa-times-circle';
                statusText = 'Desconectado - Escaneie o QR Code para conectar';
                document.getElementById('qrcodeSection').style.display = 'block';
                break;
            case 'connecting':
                statusClass = 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-300';
                statusIcon = 'fas fa-spinner fa-spin';
                statusText = 'Conectando... Aguarde alguns segundos';
                break;
        }
        
        statusDiv.innerHTML = `
            <div class="${statusClass} border rounded-lg p-4">
                <h3 class="font-bold mb-2">
                    <i class="${statusIcon} mr-2"></i>Status da Instância: ${data.instance_name}
                </h3>
                <p class="text-sm">${statusText}</p>
                <button onclick="loadInstanceStatus()" class="mt-2 text-xs underline">
                    <i class="fas fa-sync-alt mr-1"></i>Atualizar Status
                </button>
            </div>
        `;
    } else if (data.status === 'not_configured') {
        statusDiv.innerHTML = `
            <div class="bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                <h3 class="font-bold text-gray-800 dark:text-gray-100 mb-2">
                    <i class="fas fa-exclamation-circle mr-2"></i>Instância Não Configurada
                </h3>
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    Você precisa criar ou configurar uma instância antes de enviar mensagens.
                </p>
            </div>
        `;
    } else {
        statusDiv.innerHTML = `
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                <h3 class="font-bold text-red-800 dark:text-red-300 mb-2">
                    <i class="fas fa-exclamation-triangle mr-2"></i>Erro ao Verificar Status
                </h3>
                <p class="text-sm text-red-700 dark:text-red-400">${data.message}</p>
            </div>
        `;
    }
}

function createInstance() {
    if (!ensureEvolutionProvider()) {
        return;
    }
    const btn = document.getElementById('createInstanceBtn');
    const originalText = btn.innerHTML;
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Criando instância...';
    btn.disabled = true;
    
    const formData = new FormData();
    formData.append('action', 'create');
    
    fetch('/api/instance_manager.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Atualizar os campos do formulário
            document.getElementById('evolution_instance').value = data.instance_name;
            document.getElementById('evolution_token').value = data.token;
            
            // Mostrar mensagem de sucesso
            showMessage('success', 'Instância criada com sucesso! Os campos foram preenchidos automaticamente.');
            
            // Recarregar a página após 2 segundos
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            showMessage('error', 'Erro ao criar instância: ' + data.message);
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

function createInstanceAndQR() {
    if (!ensureEvolutionProvider()) {
        return;
    }
    console.log('createInstanceAndQR chamada');
    
    const instanceName = document.getElementById('instance_name').value.trim();
    const btn = document.getElementById('createQRBtn');
    const container = document.getElementById('qrCodeContainer');
    
    console.log('Instance name:', instanceName);
    console.log('Button:', btn);
    console.log('Container:', container);
    
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
    formData.append('action', 'create');
    formData.append('instance_name', instanceName);
    
    fetch('/api/instance_manager.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('success', 'Instância criada com sucesso! Gerando QR Code...');
            
            // Aguardar um pouco e gerar QR Code
            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Gerando QR Code...';
                
                // Gerar QR Code
                fetch('/api/instance_manager.php?action=qrcode')
                    .then(response => response.json())
                    .then(qrData => {
                        if (qrData.success) {
                            container.innerHTML = `
                                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6">
                                    <h4 class="font-bold text-blue-800 dark:text-blue-300 mb-3">
                                        <i class="fas fa-mobile-alt mr-2"></i>Escaneie com seu WhatsApp
                                    </h4>
                                    <div class="bg-white dark:bg-gray-800 border dark:border-gray-600 rounded-lg p-4 inline-block mb-4">
                                        <img src="data:image/png;base64,${qrData.base64}" 
                                             alt="QR Code WhatsApp" 
                                             class="mx-auto"
                                             style="max-width: 250px;">
                                    </div>
                                    <div class="text-sm text-blue-700 dark:text-blue-400">
                                        <p class="mb-2"><strong>Como conectar:</strong></p>
                                        <ol class="text-left list-decimal list-inside space-y-1">
                                            <li>Abra o WhatsApp no seu celular</li>
                                            <li>Toque no menu (⋮) > "Dispositivos conectados"</li>
                                            <li>Toque em "Conectar dispositivo"</li>
                                            <li>Escaneie este QR Code</li>
                                        </ol>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-3">
                                            O QR Code expira em alguns minutos. A página será recarregada após a conexão.
                                        </p>
                                    </div>
                                </div>
                            `;
                            
                            // Auto-refresh para detectar conexão
                            const statusInterval = setInterval(() => {
                                fetch('/api/instance_manager.php?action=status')
                                    .then(response => response.json())
                                    .then(statusData => {
                                        if (statusData.success && statusData.status === 'open') {
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
                            
                        } else {
                            container.innerHTML = `
                                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 text-red-700 dark:text-red-400">
                                    <i class="fas fa-exclamation-circle mr-2"></i>
                                    <strong>Erro ao gerar QR Code:</strong><br>
                                    <span class="text-sm">${qrData.message}</span>
                                </div>
                            `;
                        }
                    })
                    .catch(error => {
                        container.innerHTML = `
                            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 text-red-700 dark:text-red-400">
                                <i class="fas fa-times-circle mr-2"></i>
                                <strong>Erro de conexão:</strong><br>
                                <span class="text-sm">${error.message}</span>
                            </div>
                        `;
                    })
                    .finally(() => {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    });
                    
            }, 1000);
            
        } else {
            showMessage('error', 'Erro ao criar instância: ' + data.message);
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
    if (!ensureEvolutionProvider()) {
        return;
    }
    const btn = document.getElementById('generateQRBtn');
    const container = document.getElementById('qrCodeContainer');
    const originalText = btn.innerHTML;
    
    console.log('generateQRForExisting chamada');
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Gerando QR Code...';
    btn.disabled = true;
    
    // Primeiro tentar conectar a instância
    fetch('/api/instance_manager.php?action=connect')
        .then(response => response.json())
        .then(connectData => {
            console.log('Connect response:', connectData);
            
            // Independente do resultado do connect, tentar gerar QR Code
            fetch('/api/instance_manager.php?action=qrcode')
                .then(response => response.json())
                .then(qrData => {
                    console.log('QR response:', qrData);
                    
                    if (qrData.success) {
                        container.innerHTML = `
                            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6">
                                <h4 class="font-bold text-blue-800 dark:text-blue-300 mb-3">
                                    <i class="fas fa-mobile-alt mr-2"></i>Escaneie com seu WhatsApp
                                </h4>
                                <div class="bg-white dark:bg-gray-800 border dark:border-gray-600 rounded-lg p-4 inline-block mb-4">
                                    <img src="data:image/png;base64,${qrData.base64}" 
                                         alt="QR Code WhatsApp" 
                                         class="mx-auto"
                                         style="max-width: 250px;">
                                </div>
                                <div class="text-sm text-blue-700 dark:text-blue-400">
                                    <p class="mb-2"><strong>Como conectar:</strong></p>
                                    <ol class="text-left list-decimal list-inside space-y-1">
                                        <li>Abra o WhatsApp no seu celular</li>
                                        <li>Toque no menu (⋮) > "Dispositivos conectados"</li>
                                        <li>Toque em "Conectar dispositivo"</li>
                                        <li>Escaneie este QR Code</li>
                                    </ol>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-3">
                                        O QR Code expira em alguns minutos. A página será recarregada após a conexão.
                                    </p>
                                </div>
                            </div>
                        `;
                        
                        // Auto-refresh para detectar conexão
                        const statusInterval = setInterval(() => {
                            fetch('/api/instance_manager.php?action=status')
                                .then(response => response.json())
                                .then(statusData => {
                                    if (statusData.success && statusData.status === 'open') {
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
                        
                    } else {
                        container.innerHTML = `
                            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 text-red-700 dark:text-red-400">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                <strong>Erro ao gerar QR Code:</strong><br>
                                <span class="text-sm">${qrData.message}</span>
                                <div class="mt-3">
                                    <button onclick="deleteInstance()" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">
                                        Remover Instância e Criar Nova
                                    </button>
                                </div>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    container.innerHTML = `
                        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 text-red-700 dark:text-red-400">
                            <i class="fas fa-times-circle mr-2"></i>
                            <strong>Erro de conexão:</strong><br>
                            <span class="text-sm">${error.message}</span>
                        </div>
                    `;
                });
        })
        .catch(error => {
            console.log('Connect error:', error);
            // Mesmo com erro no connect, tentar QR Code
            fetch('/api/instance_manager.php?action=qrcode')
                .then(response => response.json())
                .then(qrData => {
                    if (qrData.success) {
                        container.innerHTML = `
                            <div class="bg-white dark:bg-gray-800 border dark:border-gray-600 rounded-lg p-4 inline-block">
                                <img src="data:image/png;base64,${qrData.base64}" 
                                     alt="QR Code WhatsApp" 
                                     style="max-width: 250px;">
                            </div>
                        `;
                    } else {
                        container.innerHTML = `
                            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 text-red-700 dark:text-red-400">
                                <strong>Erro:</strong> ${qrData.message}
                            </div>
                        `;
                    }
                })
                .catch(qrError => {
                    container.innerHTML = `
                        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 text-red-700 dark:text-red-400">
                            <strong>Erro:</strong> ${qrError.message}
                        </div>
                    `;
                });
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
}

function generateQRCode() {
    if (!ensureEvolutionProvider()) {
        return;
    }
    const btn = document.getElementById('qrCodeBtn');
    const container = document.getElementById('qrCodeContainer');
    const originalText = btn.innerHTML;
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Gerando QR Code...';
    btn.disabled = true;
    
    fetch('/api/instance_manager.php?action=qrcode')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                container.innerHTML = `
                    <div class="bg-white border rounded-lg p-4 inline-block">
                        <img src="data:image/png;base64,${data.base64}" 
                             alt="QR Code WhatsApp" 
                             class="mx-auto"
                             style="max-width: 250px;">
                    </div>
                    <p class="text-sm text-blue-600 mt-3">
                        <i class="fas fa-mobile-alt mr-1"></i>
                        <strong>Como conectar:</strong> Abra WhatsApp > Menu (⋮) > Dispositivos conectados > Conectar dispositivo
                    </p>
                    <p class="text-xs text-gray-500 mt-2">
                        O QR Code expira em alguns minutos. Se não funcionar, gere um novo.
                    </p>
                `;
                
                // Auto-refresh do status a cada 5 segundos para detectar conexão
                const statusInterval = setInterval(() => {
                    loadInstanceStatus();
                }, 5000);
                
                // Parar o refresh após 2 minutos
                setTimeout(() => {
                    clearInterval(statusInterval);
                }, 120000);
                
            } else {
                container.innerHTML = `
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <strong>Erro ao gerar QR Code:</strong><br>
                        <span class="text-sm">${data.message}</span>
                    </div>
                `;
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
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
}

function getQRCode() {
    if (!ensureEvolutionProvider()) {
        return;
    }
    const btn = document.getElementById('getQRBtn');
    const container = document.getElementById('qrcodeContainer');
    const originalText = btn.innerHTML;
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Gerando QR Code...';
    btn.disabled = true;
    
    fetch('/api/instance_manager.php?action=qrcode')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                container.innerHTML = `
                    <img src="data:image/png;base64,${data.base64}" 
                         alt="QR Code WhatsApp" 
                         class="mx-auto border rounded-lg shadow-lg"
                         style="max-width: 300px;">
                    <p class="text-sm text-green-600 mt-2">
                        <i class="fas fa-mobile-alt mr-1"></i>
                        Escaneie com seu WhatsApp: Menu > Dispositivos conectados > Conectar dispositivo
                    </p>
                `;
                
                // Auto-refresh do status a cada 5 segundos
                const statusInterval = setInterval(() => {
                    loadInstanceStatus();
                }, 5000);
                
                // Parar o refresh após 2 minutos
                setTimeout(() => {
                    clearInterval(statusInterval);
                }, 120000);
                
            } else {
                container.innerHTML = `
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        ${data.message}
                    </div>
                `;
            }
            
            btn.innerHTML = originalText;
            btn.disabled = false;
        })
        .catch(error => {
            container.innerHTML = `
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
                    <i class="fas fa-times-circle mr-2"></i>
                    Erro ao gerar QR Code: ${error.message}
                </div>
            `;
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
}

function checkInstanceExists() {
    if (!ensureEvolutionProvider()) {
        return;
    }
    const btn = document.getElementById('checkInstanceBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Verificando...';
    btn.disabled = true;
    
    fetch('/api/instance_manager.php?action=check_exists')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.exists) {
                    showMessage('success', 'Instância existe na Evolution API e está funcionando!');
                } else {
                    if (data.cleaned) {
                        showMessage('warning', 'Instância não existe mais na Evolution API. Configuração foi limpa automaticamente. Você pode criar uma nova instância.');
                        // Recarregar a página após 3 segundos
                        setTimeout(() => {
                            window.location.reload();
                        }, 3000);
                    } else {
                        showMessage('info', 'Nenhuma instância configurada.');
                    }
                }
            } else {
                showMessage('error', 'Erro ao verificar instância: ' + data.message);
            }
        })
        .catch(error => {
            showMessage('error', 'Erro de conexão: ' + error.message);
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
}

function deleteInstance() {
    if (!ensureEvolutionProvider()) {
        return;
    }
    if (!confirm('Tem certeza que deseja remover sua instância? Isso irá desconectar seu WhatsApp e você precisará criar uma nova instância.')) {
        return;
    }
    
    const btn = document.getElementById('deleteInstanceBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Removendo...';
    btn.disabled = true;
    
    const formData = new FormData();
    formData.append('action', 'delete');
    
    fetch('/api/instance_manager.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage('success', data.message);
                // Recarregar a página após 2 segundos
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                showMessage('error', 'Erro ao remover instância: ' + data.message);
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

function testConnection() {
    if (!ensureEvolutionProvider()) {
        return;
    }
    const resultDiv = document.getElementById('testResult');
    resultDiv.innerHTML = '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-blue-700"><i class="fas fa-spinner fa-spin mr-2"></i>Testando conexão...</div>';
    
    fetch('/api/test_instance.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resultDiv.innerHTML = `
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-green-700">
                        <i class="fas fa-check-circle mr-2"></i>
                        <strong>Conexão bem-sucedida!</strong><br>
                        <span class="text-sm">${data.message}</span>
                    </div>
                `;
            } else {
                resultDiv.innerHTML = `
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
                        <i class="fas fa-times-circle mr-2"></i>
                        <strong>Erro na conexão!</strong><br>
                        <span class="text-sm">${data.message}</span>
                    </div>
                `;
            }
        })
        .catch(error => {
            resultDiv.innerHTML = `
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
                    <i class="fas fa-times-circle mr-2"></i>
                    <strong>Erro ao testar:</strong><br>
                    <span class="text-sm">${error.message}</span>
                </div>
            `;
        });
}

function showMessage(type, message) {
    let alertClass, icon;
    
    switch(type) {
        case 'success':
            alertClass = 'bg-green-50 border-green-200 text-green-700';
            icon = 'fas fa-check-circle';
            break;
        case 'warning':
            alertClass = 'bg-yellow-50 border-yellow-200 text-yellow-700';
            icon = 'fas fa-exclamation-triangle';
            break;
        case 'info':
            alertClass = 'bg-blue-50 border-blue-200 text-blue-700';
            icon = 'fas fa-info-circle';
            break;
        default:
            alertClass = 'bg-red-50 border-red-200 text-red-700';
            icon = 'fas fa-exclamation-circle';
    }
    
    const messageDiv = document.createElement('div');
    messageDiv.className = `${alertClass} border rounded-lg p-4 mb-4`;
    messageDiv.innerHTML = `
        <i class="${icon} mr-2"></i>
        ${message}
    `;
    
    // Inserir no topo da página
    const container = document.querySelector('.max-w-4xl');
    container.insertBefore(messageDiv, container.firstChild);
    
    // Remover após 5 segundos
    setTimeout(() => {
        messageDiv.remove();
    }, 5000);
}

function getSelectedProvider() {
    const select = document.getElementById('providerSelect');
    return select ? select.value : 'evolution';
}

function initProviderToggle() {
    const select = document.getElementById('providerSelect');
    if (!select) return;
    const metaSettings = document.getElementById('metaSettings');
    const zapiSettings = document.getElementById('zapiSettings');
    const evolutionSection = document.getElementById('evolutionSection');
    const metaSection = document.getElementById('metaSection');
    const zapiSection = document.getElementById('zapiSection');

    const toggle = () => {
        const provider = select.value;
        if (metaSettings) metaSettings.classList.toggle('hidden', provider !== 'meta');
        if (zapiSettings) zapiSettings.classList.toggle('hidden', provider !== 'zapi');
        if (evolutionSection) evolutionSection.classList.toggle('hidden', provider === 'meta' || provider === 'zapi');
        if (metaSection) metaSection.classList.toggle('hidden', provider !== 'meta');
        if (zapiSection) zapiSection.classList.toggle('hidden', provider !== 'zapi');
    };

    select.addEventListener('change', toggle);
    toggle();
}

function ensureEvolutionProvider() {
    if (getSelectedProvider() !== 'evolution') {
        showMessage('info', 'Altere o provedor para "Evolution API" para usar esta funcionalidade.');
        return false;
    }
    return true;
}
</script>

</div>
</div>
<?php require_once 'includes/footer_spa.php'; ?>
