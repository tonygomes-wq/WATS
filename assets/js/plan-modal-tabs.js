/**
 * JavaScript para Modal de Planos com Abas
 * Sistema de Permissões Granulares
 * MACIP Tecnologia LTDA
 */

// Alternar entre abas
function switchPlanTab(tabName) {
    // Remover active de todas as abas
    document.querySelectorAll('.plan-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remover active de todos os conteúdos
    document.querySelectorAll('.plan-tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Adicionar active na aba clicada
    document.querySelector(`.plan-tab[data-tab="${tabName}"]`).classList.add('active');
    
    // Adicionar active no conteúdo correspondente
    document.querySelector(`.plan-tab-content[data-tab="${tabName}"]`).classList.add('active');
}

// Salvar plano com features
async function savePlanWithFeatures() {
    const form = document.getElementById('planForm');
    const formData = new FormData(form);
    const planId = document.getElementById('plan_id').value;
    
    // Validar campos obrigatórios da Tab 1
    const slug = formData.get('slug');
    const name = formData.get('name');
    const price = formData.get('price');
    
    if (!slug || !name || !price) {
        showPlanAlert('Preencha todos os campos obrigatórios na aba "Informações Básicas"', 'error');
        switchPlanTab('basic');
        return;
    }
    
    // Preparar dados básicos do plano
    const planData = {
        action: planId ? 'update' : 'create',
        id: planId ? parseInt(planId) : undefined,
        slug: slug,
        name: name,
        price: parseFloat(price),
        message_limit: parseInt(formData.get('max_messages') || 2000), // Usar max_messages como message_limit
        features: [], // Será preenchido pela landing page
        description: formData.get('description') || '',
        is_active: document.getElementById('plan_is_active').checked ? 1 : 0,
        is_popular: document.getElementById('plan_is_popular').checked ? 1 : 0,
        sort_order: parseInt(formData.get('sort_order') || 1)
    };
    
    try {
        // 1. Salvar informações básicas do plano
        const planResponse = await fetch('api/manage_plans.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(planData)
        });
        
        const planResult = await planResponse.json();
        
        if (!planResult.success) {
            showPlanAlert(planResult.message, 'error');
            return;
        }
        
        // Obter ID do plano (novo ou existente)
        const savedPlanId = planId || planResult.plan_id;
        
        // 2. Salvar features do plano
        const featuresData = {
            action: 'update_features',
            plan_id: savedPlanId,
            
            // Limites (Tab 2)
            max_messages: parseInt(formData.get('max_messages') || 2000),
            max_attendants: parseInt(formData.get('max_attendants') || 1),
            max_departments: parseInt(formData.get('max_departments') || 1),
            max_contacts: parseInt(formData.get('max_contacts') || 1000),
            max_whatsapp_instances: parseInt(formData.get('max_whatsapp_instances') || 1),
            max_automation_flows: parseInt(formData.get('max_automation_flows') || 5),
            max_dispatch_campaigns: parseInt(formData.get('max_dispatch_campaigns') || 10),
            max_tags: parseInt(formData.get('max_tags') || 20),
            max_quick_replies: parseInt(formData.get('max_quick_replies') || 50),
            max_file_storage_mb: parseInt(formData.get('max_file_storage_mb') || 100),
            
            // Módulos (Tab 3)
            module_chat: 1, // Sempre habilitado
            module_dashboard: document.getElementById('module_dashboard')?.checked ? 1 : 0,
            module_dispatch: document.getElementById('module_dispatch')?.checked ? 1 : 0,
            module_contacts: document.getElementById('module_contacts')?.checked ? 1 : 0,
            module_kanban: document.getElementById('module_kanban')?.checked ? 1 : 0,
            module_automation: document.getElementById('module_automation')?.checked ? 1 : 0,
            module_reports: document.getElementById('module_reports')?.checked ? 1 : 0,
            module_integrations: document.getElementById('module_integrations')?.checked ? 1 : 0,
            module_api: document.getElementById('module_api')?.checked ? 1 : 0,
            module_webhooks: document.getElementById('module_webhooks')?.checked ? 1 : 0,
            module_ai: document.getElementById('module_ai')?.checked ? 1 : 0,
            
            // Funcionalidades (Tab 4)
            feature_multi_attendant: document.getElementById('feature_multi_attendant')?.checked ? 1 : 0,
            feature_departments: document.getElementById('feature_departments')?.checked ? 1 : 0,
            feature_tags: document.getElementById('feature_tags')?.checked ? 1 : 0,
            feature_quick_replies: document.getElementById('feature_quick_replies')?.checked ? 1 : 0,
            feature_file_upload: document.getElementById('feature_file_upload')?.checked ? 1 : 0,
            feature_media_library: document.getElementById('feature_media_library')?.checked ? 1 : 0,
            feature_custom_fields: document.getElementById('feature_custom_fields')?.checked ? 1 : 0,
            feature_export_data: document.getElementById('feature_export_data')?.checked ? 1 : 0,
            feature_white_label: document.getElementById('feature_white_label')?.checked ? 1 : 0,
            feature_priority_support: document.getElementById('feature_priority_support')?.checked ? 1 : 0,
            
            // Integrações (Tab 5)
            integration_google_sheets: document.getElementById('integration_google_sheets')?.checked ? 1 : 0,
            integration_zapier: document.getElementById('integration_zapier')?.checked ? 1 : 0,
            integration_n8n: document.getElementById('integration_n8n')?.checked ? 1 : 0,
            integration_make: document.getElementById('integration_make')?.checked ? 1 : 0,
            integration_crm: document.getElementById('integration_crm')?.checked ? 1 : 0
        };
        
        const featuresResponse = await fetch('api/manage_plans.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(featuresData)
        });
        
        const featuresResult = await featuresResponse.json();
        
        if (!featuresResult.success) {
            showPlanAlert('Plano salvo, mas houve erro ao salvar features: ' + featuresResult.message, 'error');
            return;
        }
        
        // Sucesso total
        showPlanAlert('Plano e permissões salvos com sucesso!', 'success');
        closePlanForm();
        
        // Recarregar lista de planos
        if (typeof loadPlans === 'function') {
            loadPlans();
        }
        
        // Recarregar página após 1.5 segundos
        setTimeout(() => location.reload(), 1500);
        
    } catch (error) {
        console.error('Erro ao salvar plano:', error);
        showPlanAlert('Erro ao salvar plano: ' + error.message, 'error');
    }
}

// Carregar features de um plano ao editar
async function loadPlanFeatures(planId) {
    if (!planId) return;
    
    try {
        const response = await fetch(`api/manage_plans.php?action=get_features&plan_id=${planId}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_features', plan_id: planId })
        });
        
        const data = await response.json();
        
        if (!data.success || !data.features) {
            console.warn('Features não encontradas, usando valores padrão');
            return;
        }
        
        const features = data.features;
        
        // Preencher limites (Tab 2)
        document.getElementById('max_messages').value = features.max_messages || 2000;
        document.getElementById('max_attendants').value = features.max_attendants || 1;
        document.getElementById('max_departments').value = features.max_departments || 1;
        document.getElementById('max_contacts').value = features.max_contacts || 1000;
        document.getElementById('max_whatsapp_instances').value = features.max_whatsapp_instances || 1;
        document.getElementById('max_automation_flows').value = features.max_automation_flows || 5;
        document.getElementById('max_dispatch_campaigns').value = features.max_dispatch_campaigns || 10;
        document.getElementById('max_tags').value = features.max_tags || 20;
        document.getElementById('max_quick_replies').value = features.max_quick_replies || 50;
        document.getElementById('max_file_storage_mb').value = features.max_file_storage_mb || 100;
        
        // Preencher módulos (Tab 3)
        document.getElementById('module_chat').checked = true; // Sempre habilitado
        document.getElementById('module_dashboard').checked = features.module_dashboard == 1;
        document.getElementById('module_dispatch').checked = features.module_dispatch == 1;
        document.getElementById('module_contacts').checked = features.module_contacts == 1;
        document.getElementById('module_kanban').checked = features.module_kanban == 1;
        document.getElementById('module_automation').checked = features.module_automation == 1;
        document.getElementById('module_reports').checked = features.module_reports == 1;
        document.getElementById('module_integrations').checked = features.module_integrations == 1;
        document.getElementById('module_api').checked = features.module_api == 1;
        document.getElementById('module_webhooks').checked = features.module_webhooks == 1;
        document.getElementById('module_ai').checked = features.module_ai == 1;
        
        // Preencher funcionalidades (Tab 4)
        document.getElementById('feature_multi_attendant').checked = features.feature_multi_attendant == 1;
        document.getElementById('feature_departments').checked = features.feature_departments == 1;
        document.getElementById('feature_tags').checked = features.feature_tags == 1;
        document.getElementById('feature_quick_replies').checked = features.feature_quick_replies == 1;
        document.getElementById('feature_file_upload').checked = features.feature_file_upload == 1;
        document.getElementById('feature_media_library').checked = features.feature_media_library == 1;
        document.getElementById('feature_custom_fields').checked = features.feature_custom_fields == 1;
        document.getElementById('feature_export_data').checked = features.feature_export_data == 1;
        document.getElementById('feature_white_label').checked = features.feature_white_label == 1;
        document.getElementById('feature_priority_support').checked = features.feature_priority_support == 1;
        
        // Preencher integrações (Tab 5)
        document.getElementById('integration_google_sheets').checked = features.integration_google_sheets == 1;
        document.getElementById('integration_zapier').checked = features.integration_zapier == 1;
        document.getElementById('integration_n8n').checked = features.integration_n8n == 1;
        document.getElementById('integration_make').checked = features.integration_make == 1;
        document.getElementById('integration_crm').checked = features.integration_crm == 1;
        
    } catch (error) {
        console.error('Erro ao carregar features:', error);
    }
}

// Função auxiliar para mostrar alertas (se não existir)
if (typeof showPlanAlert !== 'function') {
    function showPlanAlert(message, type = 'error') {
        const alertDiv = document.getElementById('planAlert');
        if (!alertDiv) {
            alert(message);
            return;
        }
        
        alertDiv.classList.remove('hidden', 'bg-red-100', 'border-red-400', 'text-red-700', 'bg-green-100', 'border-green-400', 'text-green-700');
        
        if (type === 'error') {
            alertDiv.classList.add('bg-red-100', 'border', 'border-red-400', 'text-red-700');
            alertDiv.innerHTML = '<i class="fas fa-exclamation-circle mr-2"></i>' + message;
        } else {
            alertDiv.classList.add('bg-green-100', 'border', 'border-green-400', 'text-green-700');
            alertDiv.innerHTML = '<i class="fas fa-check-circle mr-2"></i>' + message;
        }
        
        setTimeout(() => alertDiv.classList.add('hidden'), 5000);
    }
}
