<!-- Modal de Formulário de Plano com Abas -->
<div id="planFormModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[60] p-4" onclick="closePlanForm(event)">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-5xl w-full max-h-[90vh] overflow-hidden" onclick="event.stopPropagation()">
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-500 p-6 text-white">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-bold" id="planFormTitle">
                    <i class="fas fa-edit mr-2"></i>Editar Plano
                </h3>
                <button onclick="closePlanForm()" class="text-white hover:text-gray-200 transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Tabs Navigation -->
        <div class="bg-gray-100 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
            <div class="flex overflow-x-auto">
                <button type="button" class="plan-tab active" data-tab="basic" onclick="switchPlanTab('basic')">
                    <i class="fas fa-info-circle mr-2"></i>
                    <span>Informações Básicas</span>
                </button>
                <button type="button" class="plan-tab" data-tab="limits" onclick="switchPlanTab('limits')">
                    <i class="fas fa-chart-bar mr-2"></i>
                    <span>Limites</span>
                </button>
                <button type="button" class="plan-tab" data-tab="modules" onclick="switchPlanTab('modules')">
                    <i class="fas fa-th-large mr-2"></i>
                    <span>Módulos</span>
                </button>
                <button type="button" class="plan-tab" data-tab="features" onclick="switchPlanTab('features')">
                    <i class="fas fa-star mr-2"></i>
                    <span>Funcionalidades</span>
                </button>
                <button type="button" class="plan-tab" data-tab="integrations" onclick="switchPlanTab('integrations')">
                    <i class="fas fa-plug mr-2"></i>
                    <span>Integrações</span>
                </button>
            </div>
        </div>
        
        <!-- Formulário -->
        <form id="planForm" class="overflow-y-auto" style="max-height: calc(90vh - 250px);">
            <input type="hidden" id="plan_id" name="plan_id">
            
            <!-- Tab 1: Informações Básicas -->
            <div class="plan-tab-content active" data-tab="basic">
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">
                                Slug (identificador único) *
                            </label>
                            <input type="text" id="plan_slug" name="slug" required
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="ex: profissional">
                            <p class="text-xs text-gray-500 mt-1">Usado na URL e identificação interna</p>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">
                                Nome do Plano *
                            </label>
                            <input type="text" id="plan_name" name="name" required
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="ex: Profissional">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">
                                Preço (R$) *
                            </label>
                            <input type="number" id="plan_price" name="price" step="0.01" min="0" required
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="97.00">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">
                                Ordem de Exibição
                            </label>
                            <input type="number" id="plan_sort_order" name="sort_order" min="0" value="1"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                   placeholder="1">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">
                            Descrição Curta
                        </label>
                        <textarea id="plan_description" name="description" rows="3"
                                  class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                  placeholder="Ideal para pequenas empresas que precisam de mais recursos..."></textarea>
                    </div>
                    
                    <div class="flex items-center space-x-6 pt-2">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" id="plan_is_active" name="is_active" class="mr-2 w-4 h-4">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Ativo</span>
                        </label>
                        
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" id="plan_is_popular" name="is_popular" class="mr-2 w-4 h-4">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Popular</span>
                        </label>
                    </div>
                    
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                        <p class="text-sm text-blue-800 dark:text-blue-200">
                            <i class="fas fa-info-circle mr-2"></i>
                            <strong>Dica:</strong> Configure os limites e permissões nas próximas abas para controlar o que este plano pode acessar.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Limites -->
            <div class="plan-tab-content" data-tab="limits">
                <div class="p-6 space-y-4">
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-4">
                        <p class="text-sm text-yellow-800 dark:text-yellow-200">
                            <i class="fas fa-lightbulb mr-2"></i>
                            <strong>Dica:</strong> Use <strong>-1</strong> para recursos ilimitados ou <strong>0</strong> para desabilitar completamente.
                        </p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">
                                <i class="fas fa-paper-plane text-blue-600 mr-1"></i>
                                Mensagens/Mês
                            </label>
                            <input type="number" id="max_messages" name="max_messages" value="2000"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">
                                <i class="fas fa-users text-green-600 mr-1"></i>
                                Atendentes
                            </label>
                            <input type="number" id="max_attendants" name="max_attendants" value="1"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">
                                <i class="fas fa-building text-purple-600 mr-1"></i>
                                Setores/Departamentos
                            </label>
                            <input type="number" id="max_departments" name="max_departments" value="1"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">
                                <i class="fas fa-address-book text-orange-600 mr-1"></i>
                                Contatos
                            </label>
                            <input type="number" id="max_contacts" name="max_contacts" value="1000"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">
                                <i class="fab fa-whatsapp text-green-600 mr-1"></i>
                                Instâncias WhatsApp
                            </label>
                            <input type="number" id="max_whatsapp_instances" name="max_whatsapp_instances" value="1"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">
                                <i class="fas fa-robot text-indigo-600 mr-1"></i>
                                Fluxos de Automação
                            </label>
                            <input type="number" id="max_automation_flows" name="max_automation_flows" value="5"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">
                                <i class="fas fa-bullhorn text-red-600 mr-1"></i>
                                Campanhas de Disparo/Mês
                            </label>
                            <input type="number" id="max_dispatch_campaigns" name="max_dispatch_campaigns" value="10"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">
                                <i class="fas fa-tags text-pink-600 mr-1"></i>
                                Tags/Etiquetas
                            </label>
                            <input type="number" id="max_tags" name="max_tags" value="20"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">
                                <i class="fas fa-comment-dots text-teal-600 mr-1"></i>
                                Respostas Rápidas
                            </label>
                            <input type="number" id="max_quick_replies" name="max_quick_replies" value="50"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">
                                <i class="fas fa-hdd text-gray-600 mr-1"></i>
                                Armazenamento (MB)
                            </label>
                            <input type="number" id="max_file_storage_mb" name="max_file_storage_mb" value="100"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Módulos -->
            <div class="plan-tab-content" data-tab="modules">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <label class="feature-checkbox-item disabled">
                            <input type="checkbox" name="module_chat" id="module_chat" checked disabled>
                            <div class="feature-info">
                                <i class="fas fa-comments text-green-600"></i>
                                <div>
                                    <span class="font-medium">Atendimento/Chat</span>
                                    <small class="block text-gray-500">(obrigatório)</small>
                                </div>
                            </div>
                        </label>
                        
                        <label class="feature-checkbox-item">
                            <input type="checkbox" name="module_dashboard" id="module_dashboard">
                            <div class="feature-info">
                                <i class="fas fa-chart-line text-blue-600"></i>
                                <div>
                                    <span class="font-medium">Dashboard</span>
                                    <small class="block text-gray-500">Métricas e gráficos</small>
                                </div>
                            </div>
                        </label>
                        
                        <label class="feature-checkbox-item">
                            <input type="checkbox" name="module_dispatch" id="module_dispatch">
                            <div class="feature-info">
                                <i class="fas fa-paper-plane text-orange-600"></i>
                                <div>
                                    <span class="font-medium">Disparo em Massa</span>
                                    <small class="block text-gray-500">Campanhas de mensagens</small>
                                </div>
                            </div>
                        </label>
                        
                        <label class="feature-checkbox-item">
                            <input type="checkbox" name="module_contacts" id="module_contacts">
                            <div class="feature-info">
                                <i class="fas fa-users text-purple-600"></i>
                                <div>
                                    <span class="font-medium">Gerenciar Contatos</span>
                                    <small class="block text-gray-500">Lista de contatos</small>
                                </div>
                            </div>
                        </label>
                        
                        <label class="feature-checkbox-item">
                            <input type="checkbox" name="module_kanban" id="module_kanban">
                            <div class="feature-info">
                                <i class="fas fa-columns text-purple-600"></i>
                                <div>
                                    <span class="font-medium">Kanban/Pipeline</span>
                                    <small class="block text-gray-500">Gestão de vendas</small>
                                </div>
                            </div>
                        </label>
                        
                        <label class="feature-checkbox-item">
                            <input type="checkbox" name="module_automation" id="module_automation">
                            <div class="feature-info">
                                <i class="fas fa-robot text-indigo-600"></i>
                                <div>
                                    <span class="font-medium">Automação/Fluxos</span>
                                    <small class="block text-gray-500">Chatbots e fluxos</small>
                                </div>
                            </div>
                        </label>
                        
                        <label class="feature-checkbox-item">
                            <input type="checkbox" name="module_reports" id="module_reports">
                            <div class="feature-info">
                                <i class="fas fa-chart-bar text-green-600"></i>
                                <div>
                                    <span class="font-medium">Relatórios Avançados</span>
                                    <small class="block text-gray-500">Análises detalhadas</small>
                                </div>
                            </div>
                        </label>
                        
                        <label class="feature-checkbox-item">
                            <input type="checkbox" name="module_integrations" id="module_integrations">
                            <div class="feature-info">
                                <i class="fas fa-plug text-yellow-600"></i>
                                <div>
                                    <span class="font-medium">Integrações</span>
                                    <small class="block text-gray-500">Conectores externos</small>
                                </div>
                            </div>
                        </label>
                        
                        <label class="feature-checkbox-item">
                            <input type="checkbox" name="module_api" id="module_api">
                            <div class="feature-info">
                                <i class="fas fa-code text-red-600"></i>
                                <div>
                                    <span class="font-medium">Acesso à API</span>
                                    <small class="block text-gray-500">API REST completa</small>
                                </div>
                            </div>
                        </label>
                        
                        <label class="feature-checkbox-item">
                            <input type="checkbox" name="module_webhooks" id="module_webhooks">
                            <div class="feature-info">
                                <i class="fas fa-webhook text-teal-600"></i>
                                <div>
                                    <span class="font-medium">Webhooks</span>
                                    <small class="block text-gray-500">Webhooks personalizados</small>
                                </div>
                            </div>
                        </label>
                        
                        <label class="feature-checkbox-item">
                            <input type="checkbox" name="module_ai" id="module_ai">
                            <div class="feature-info">
                                <i class="fas fa-brain text-pink-600"></i>
                                <div>
                                    <span class="font-medium">IA/ChatGPT</span>
                                    <small class="block text-gray-500">Inteligência artificial</small>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Tab 4: Funcionalidades -->
            <div class="plan-tab-content" data-tab="features">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <label class="feature-checkbox-item">
                            <input type="checkbox" name="feature_multi_attendant" id="feature_multi_attendant">
                            <div class="feature-info">
                                <i class="fas fa-user-friends text-blue-600"></i>
                                <div>
                                    <span class="font-medium">Múltiplos Atendentes</span>
                                    <small class="block text-gray-500">Mais de 1 atendente</small>
                                </div>
                            </div>
                        </label>
                        
                        <label class="feature-checkbox-item">
                            <input type="checkbox" name="feature_departments" id="feature_departments">
                            <div class="feature-info">
                                <i class="fas fa-sitemap text-purple-600"></i>
                                <div>
                                    <span class="font-medium">Departamentos/Setores</span>
                                    <small class="block text-gray-500">Organização por setores</small>
                                </div>
                            </div>
                        </label>
                        
                        <label class="feature-checkbox-item">
                            <input type="checkbox" name="feature_tags" id="feature_tags">
                            <div class="feature-info">
                                <i class="fas fa-tags text-pink-600"></i>
                                <div>
                                    <span class="font-medium">Tags/Etiquetas</span>
                                    <small class="block text-gray-500">Organizar conversas</small>
                                </div>
                            </div>
                        </label>
                        
                        <label class="feature-checkbox-item">
                            <input type="checkbox" name="feature_quick_replies" id="feature_quick_replies">
                            <div class="feature-info">
                                <i class="fas fa-comment-dots text-green-600"></i>
                                <div>
                                    <span class="font-medium">Respostas Rápidas</span>
                                    <small class="block text-gray-500">Templates de mensagens</small>
                                </div>
                            </div>
                        </label>
                        
                        <label class="feature-checkbox-item">
                            <input type="checkbox" name="feature_file_upload" id="feature_file_upload">
                            <div class="feature-info">
                                <i class="fas fa-upload text-orange-600"></i>
                                <div>
                                    <span class="font-medium">Upload de Arquivos</span>
                                    <small class="block text-gray-500">Enviar arquivos</small>
                                </div>
                            </div>
                        </label>
                        
                        <label class="feature-checkbox-item">
                            <input type="checkbox" name="feature_media_library" id="feature_media_library">
                            <div class="feature-info">
                                <i class="fas fa-photo-video text-indigo-600"></i>
                                <div>
                                    <span class="font-medium">Biblioteca de Mídia</span>
                                    <small class="block text-gray-500">Gerenciar mídias</small>
                                </div>
                            </div>
                        </label>
                        
                        <label class="feature-checkbox-item">
                            <input type="checkbox" name="feature_custom_fields" id="feature_custom_fields">
                            <div class="feature-info">
                                <i class="fas fa-list-alt text-teal-600"></i>
                                <div>
                                    <span class="font-medium">Campos Personalizados</span>
                                    <small class="block text-gray-500">Campos extras</small>
                                </div>
                            </div>
                        </label>
                        
                        <label class="feature-checkbox-item">
                            <input type="checkbox" name="feature_export_data" id="feature_export_data">
                            <div class="feature-info">
                                <i class="fas fa-file-export text-blue-600"></i>
                                <div>
                                    <span class="font-medium">Exportar Dados</span>
                                    <small class="block text-gray-500">CSV/Excel</small>
                                </div>
                            </div>
                        </label>
                        
                        <label class="feature-checkbox-item">
                            <input type="checkbox" name="feature_white_label" id="feature_white_label">
                            <div class="feature-info">
                                <i class="fas fa-palette text-purple-600"></i>
                                <div>
                                    <span class="font-medium">White Label</span>
                                    <small class="block text-gray-500">Marca própria</small>
                                </div>
                            </div>
                        </label>
                        
                        <label class="feature-checkbox-item">
                            <input type="checkbox" name="feature_priority_support" id="feature_priority_support">
                            <div class="feature-info">
                                <i class="fas fa-headset text-green-600"></i>
                                <div>
                                    <span class="font-medium">Suporte Prioritário</span>
                                    <small class="block text-gray-500">Atendimento VIP</small>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Tab 5: Integrações -->
            <div class="plan-tab-content" data-tab="integrations">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <label class="feature-checkbox-item">
                            <input type="checkbox" name="integration_google_sheets" id="integration_google_sheets">
                            <div class="feature-info">
                                <i class="fas fa-table text-green-600"></i>
                                <div>
                                    <span class="font-medium">Google Sheets</span>
                                    <small class="block text-gray-500">Planilhas Google</small>
                                </div>
                            </div>
                        </label>
                        
                        <label class="feature-checkbox-item">
                            <input type="checkbox" name="integration_zapier" id="integration_zapier">
                            <div class="feature-info">
                                <i class="fas fa-bolt text-orange-600"></i>
                                <div>
                                    <span class="font-medium">Zapier</span>
                                    <small class="block text-gray-500">Automação Zapier</small>
                                </div>
                            </div>
                        </label>
                        
                        <label class="feature-checkbox-item">
                            <input type="checkbox" name="integration_n8n" id="integration_n8n">
                            <div class="feature-info">
                                <i class="fas fa-project-diagram text-purple-600"></i>
                                <div>
                                    <span class="font-medium">N8N</span>
                                    <small class="block text-gray-500">Workflow automation</small>
                                </div>
                            </div>
                        </label>
                        
                        <label class="feature-checkbox-item">
                            <input type="checkbox" name="integration_make" id="integration_make">
                            <div class="feature-info">
                                <i class="fas fa-cogs text-blue-600"></i>
                                <div>
                                    <span class="font-medium">Make (Integromat)</span>
                                    <small class="block text-gray-500">Automação Make</small>
                                </div>
                            </div>
                        </label>
                        
                        <label class="feature-checkbox-item">
                            <input type="checkbox" name="integration_crm" id="integration_crm">
                            <div class="feature-info">
                                <i class="fas fa-handshake text-indigo-600"></i>
                                <div>
                                    <span class="font-medium">CRM Externo</span>
                                    <small class="block text-gray-500">Salesforce, HubSpot, etc</small>
                                </div>
                            </div>
                        </label>
                    </div>
                    
                    <div class="mt-6 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                        <p class="text-sm text-green-800 dark:text-green-200">
                            <i class="fas fa-check-circle mr-2"></i>
                            <strong>Dica:</strong> Habilite integrações para permitir que o plano se conecte com ferramentas externas.
                        </p>
                    </div>
                </div>
            </div>
        </form>
        
        <!-- Footer com Botões -->
        <div class="bg-gray-50 dark:bg-gray-700 px-6 py-4 flex gap-3 border-t border-gray-200 dark:border-gray-600">
            <button type="button" onclick="closePlanForm()" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 px-4 rounded-lg transition">
                <i class="fas fa-times mr-2"></i>Cancelar
            </button>
            <button type="button" onclick="savePlanWithFeatures()" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition">
                <i class="fas fa-save mr-2"></i>Salvar Plano
            </button>
        </div>
    </div>
</div>
