<?php
/**
 * Widget Visual para Mostrar e Trocar Provider de API
 * Exibe no topo do chat qual API está em uso
 */

require_once __DIR__ . '/api_provider_detector.php';

function renderApiProviderWidget($userId) {
    global $pdo;
    
    $detector = new ApiProviderDetector($pdo);
    $info = $detector->getProviderInfo($userId);
    
    if (!$info['configured']) {
        return renderNotConfiguredWidget();
    }
    
    return renderConfiguredWidget($info);
}

function renderNotConfiguredWidget() {
    return '
    <div class="api-provider-widget not-configured" style="background: #fee; border-left: 4px solid #f44; padding: 12px; margin-bottom: 16px; border-radius: 8px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 24px;">⚠️</span>
            <div style="flex: 1;">
                <div style="font-weight: 600; color: #c33;">Nenhuma API Configurada</div>
                <div style="font-size: 13px; color: #666; margin-top: 4px;">
                    Configure uma API em <a href="/my_instance.php" style="color: #2563eb; text-decoration: underline;">Minha Instância</a>
                </div>
            </div>
        </div>
    </div>
    ';
}

function renderConfiguredWidget($info) {
    $provider = $info['provider'];
    $icon = $info['icon'];
    $name = $info['name'];
    $description = $info['description'];
    
    $bgColor = $provider === 'meta' ? '#e0f2fe' : '#dbeafe';
    $borderColor = $provider === 'meta' ? '#0ea5e9' : '#3b82f6';
    $textColor = $provider === 'meta' ? '#0c4a6e' : '#1e40af';
    
    $otherProvider = $provider === 'meta' ? 'evolution' : 'meta';
    $otherName = $provider === 'meta' ? 'Evolution API' : 'Meta API';
    
    return '
    <div class="api-provider-widget configured" style="background: ' . $bgColor . '; border-left: 4px solid ' . $borderColor . '; padding: 12px; margin-bottom: 16px; border-radius: 8px;">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
                <span style="font-size: 24px;">' . $icon . '</span>
                <div>
                    <div style="font-weight: 600; color: ' . $textColor . ';">' . $name . '</div>
                    <div style="font-size: 13px; color: #666; margin-top: 2px;">' . $description . '</div>
                </div>
            </div>
            <button 
                onclick="showProviderSwitchModal()" 
                class="btn-switch-provider"
                style="background: white; border: 1px solid ' . $borderColor . '; color: ' . $textColor . '; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.2s;"
                onmouseover="this.style.background=\'' . $borderColor . '\'; this.style.color=\'white\';"
                onmouseout="this.style.background=\'white\'; this.style.color=\'' . $textColor . '\';">
                <i class="fas fa-exchange-alt" style="margin-right: 6px;"></i>
                Trocar para ' . $otherName . '
            </button>
        </div>
    </div>
    
    <script>
    function showProviderSwitchModal() {
        const currentProvider = "' . $provider . '";
        const newProvider = currentProvider === "meta" ? "evolution" : "meta";
        const newProviderName = newProvider === "meta" ? "Meta API (WhatsApp Business API)" : "Evolution API";
        
        if (confirm("Deseja trocar para " + newProviderName + "?\\n\\nO chat será recarregado após a troca.")) {
            switchApiProvider(newProvider);
        }
    }
    
    async function switchApiProvider(newProvider) {
        try {
            // Validar se o novo provider está configurado
            const validateResponse = await fetch("/api/sync_provider.php?action=validate&provider=" + newProvider);
            const validateData = await validateResponse.json();
            
            if (!validateData.is_configured) {
                alert("❌ " + (newProvider === "meta" ? "Meta API" : "Evolution API") + " não está configurada.\\n\\nCampos faltando:\\n- " + validateData.missing_fields.join("\\n- ") + "\\n\\nConfigure em Minha Instância primeiro.");
                window.location.href = "/my_instance.php";
                return;
            }
            
            // Trocar provider
            const formData = new FormData();
            formData.append("action", "switch");
            formData.append("provider", newProvider);
            
            const response = await fetch("/api/sync_provider.php", {
                method: "POST",
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert("✅ Provider alterado com sucesso!\\n\\nO chat será recarregado.");
                window.location.reload();
            } else {
                alert("❌ Erro ao trocar provider:\\n" + data.error);
            }
        } catch (error) {
            console.error("Erro ao trocar provider:", error);
            alert("❌ Erro ao trocar provider. Verifique o console.");
        }
    }
    </script>
    ';
}
