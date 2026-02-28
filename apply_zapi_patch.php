<?php
/**
 * Script para aplicar patch Z-API no my_instance.php
 * Execute: php apply_zapi_patch.php
 */

echo "=== APLICANDO PATCH Z-API ===\n\n";

$file = 'my_instance.php';

if (!file_exists($file)) {
    die("❌ Erro: Arquivo $file não encontrado!\n");
}

// Fazer backup
$backup = $file . '.backup.' . date('YmdHis');
if (!copy($file, $backup)) {
    die("❌ Erro ao criar backup!\n");
}
echo "✅ Backup criado: $backup\n";

// Ler conteúdo
$content = file_get_contents($file);

// 1. Atualizar SELECT
echo "Aplicando patch 1/7: Atualizar SELECT...\n";
$content = str_replace(
    'SELECT evolution_instance, evolution_token, whatsapp_provider, meta_phone_number_id',
    'SELECT evolution_instance, evolution_token, whatsapp_provider, zapi_instance_id, zapi_token, meta_phone_number_id',
    $content
);

// 2. Adicionar opção Z-API no select
echo "Aplicando patch 2/7: Adicionar opção Z-API...\n";
$content = str_replace(
    '<option value="evolution" <?php echo $selected_provider === \'evolution\' ? \'selected\' : \'\'; ?>>Evolution API (Baileys)</option>
                    <option value="meta" <?php echo $selected_provider === \'meta\' ? \'selected\' : \'\'; ?>>API Oficial do WhatsApp (Meta)</option>',
    '<option value="evolution" <?php echo $selected_provider === \'evolution\' ? \'selected\' : \'\'; ?>>Evolution API (Baileys)</option>
                    <option value="zapi" <?php echo $selected_provider === \'zapi\' ? \'selected\' : \'\'; ?>>Z-API</option>
                    <option value="meta" <?php echo $selected_provider === \'meta\' ? \'selected\' : \'\'; ?>>API Oficial do WhatsApp (Meta)</option>',
    $content
);

// 3. Adicionar processamento Z-API
echo "Aplicando patch 3/7: Adicionar processamento Z-API...\n";
$zapiProcessing = "    } elseif (\$provider === 'zapi') {
        // Processar Z-API
        \$zapi_instance_id = sanitize(\$_POST['zapi_instance_id'] ?? '');
        \$zapi_token = sanitize(\$_POST['zapi_token'] ?? '');
        
        if (empty(\$zapi_instance_id) || empty(\$zapi_token)) {
            setError('Por favor, preencha o Instance ID e Token da Z-API.');
        } else {
            \$stmt = \$pdo->prepare(\"UPDATE users SET whatsapp_provider = 'zapi', zapi_instance_id = ?, zapi_token = ? WHERE id = ?\");
            if (\$stmt->execute([\$zapi_instance_id, \$zapi_token, \$user_id])) {
                setSuccess('Configurações da Z-API salvas com sucesso! ✅');
                header('Location: /my_instance.php');
                exit;
            } else {
                setError('Erro ao salvar configurações da Z-API.');
            }
        }
        
        \$selected_provider = 'zapi';
    } else {
        // Processar Evolution API";

$content = str_replace(
    "        \$selected_provider = 'meta';
    } else {",
    "        \$selected_provider = 'meta';
" . $zapiProcessing,
    $content
);

// 4. Adicionar campos Z-API no formulário
echo "Aplicando patch 4/7: Adicionar campos Z-API...\n";
$zapiFields = '
            <div id="zapiSettings" class="<?php echo $selected_provider === \'zapi\' ? \'\' : \'hidden\'; ?>">
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
                        <input type="text" name="zapi_instance_id" value="<?php echo htmlspecialchars($user_data[\'zapi_instance_id\'] ?? \'\'); ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="Ex: 3F2504E0-4F89-11D3">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Disponível no painel da Z-API</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Token *</label>
                        <input type="text" name="zapi_token" value="<?php echo htmlspecialchars($user_data[\'zapi_token\'] ?? \'\'); ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="Token da Z-API">
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
';

$content = str_replace(
    '</div>

            <div class="flex justify-end mt-6">',
    '</div>' . $zapiFields . '

            <div class="flex justify-end mt-6">',
    $content
);

// 5. Atualizar JavaScript initProviderToggle
echo "Aplicando patch 5/7: Atualizar JavaScript...\n";
$content = str_replace(
    'function initProviderToggle() {
    const select = document.getElementById(\'providerSelect\');
    if (!select) return;
    const metaSettings = document.getElementById(\'metaSettings\');
    const evolutionSection = document.getElementById(\'evolutionSection\');
    const metaSection = document.getElementById(\'metaSection\');

    const toggle = () => {
        const provider = select.value;
        if (metaSettings) metaSettings.classList.toggle(\'hidden\', provider !== \'meta\');
        if (evolutionSection) evolutionSection.classList.toggle(\'hidden\', provider === \'meta\');
        if (metaSection) metaSection.classList.toggle(\'hidden\', provider !== \'meta\');
    };

    select.addEventListener(\'change\', toggle);
    toggle();
}',
    'function initProviderToggle() {
    const select = document.getElementById(\'providerSelect\');
    if (!select) return;
    const metaSettings = document.getElementById(\'metaSettings\');
    const zapiSettings = document.getElementById(\'zapiSettings\');
    const evolutionSection = document.getElementById(\'evolutionSection\');
    const metaSection = document.getElementById(\'metaSection\');
    const zapiSection = document.getElementById(\'zapiSection\');

    const toggle = () => {
        const provider = select.value;
        if (metaSettings) metaSettings.classList.toggle(\'hidden\', provider !== \'meta\');
        if (zapiSettings) zapiSettings.classList.toggle(\'hidden\', provider !== \'zapi\');
        if (evolutionSection) evolutionSection.classList.toggle(\'hidden\', provider === \'meta\' || provider === \'zapi\');
        if (metaSection) metaSection.classList.toggle(\'hidden\', provider !== \'meta\');
        if (zapiSection) zapiSection.classList.toggle(\'hidden\', provider !== \'zapi\');
    };

    select.addEventListener(\'change\', toggle);
    toggle();
}',
    $content
);

// 6. Adicionar seção Z-API
echo "Aplicando patch 6/7: Adicionar seção Z-API...\n";
$zapiSection = '
        <div id="zapiSection" class="<?php echo $selected_provider === \'zapi\' ? \'\' : \'hidden\'; ?>">
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
                        <p class="font-semibold text-gray-800 dark:text-gray-100"><?php echo !empty($user_data[\'zapi_instance_id\']) ? htmlspecialchars($user_data[\'zapi_instance_id\']) : \'—\'; ?></p>
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
                    <?php echo htmlspecialchars(rtrim(SITE_URL, \'/\') . \'/api/zapi_webhook.php\'); ?>
                </div>
                <ul class="list-disc list-inside text-sm text-gray-700 dark:text-gray-300 mt-4 space-y-1">
                    <li>Acesse o painel da Z-API</li>
                    <li>Vá em Configurações > Webhooks</li>
                    <li>Cole a URL acima no campo de webhook</li>
                    <li>Ative os eventos de mensagens</li>
                </ul>
            </div>
        </div>
';

$content = str_replace(
    '</div>

<script>',
    '</div>' . $zapiSection . '

<script>',
    $content
);

// 7. Salvar arquivo
echo "Aplicando patch 7/7: Salvando arquivo...\n";
if (file_put_contents($file, $content) === false) {
    die("❌ Erro ao salvar arquivo!\n");
}

echo "\n✅ PATCH APLICADO COM SUCESSO!\n\n";
echo "Backup salvo em: $backup\n";
echo "Arquivo atualizado: $file\n\n";
echo "Próximos passos:\n";
echo "1. Acesse /my_instance.php no navegador\n";
echo "2. Selecione 'Z-API' no dropdown de provider\n";
echo "3. Preencha Instance ID e Token\n";
echo "4. Salve as configurações\n\n";
echo "🎉 Sistema multi-provider pronto para uso!\n";
