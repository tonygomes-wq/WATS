-- ============================================================================
-- Migration: Sistema de Permissões Granulares para Planos
-- Data: 2026-02-25
-- Descrição: Adiciona controle detalhado de funcionalidades por plano
-- ============================================================================

-- Criar tabela de features dos planos
CREATE TABLE IF NOT EXISTS plan_features (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_id BIGINT UNSIGNED NOT NULL,
    
    -- ========================================================================
    -- LIMITES NUMÉRICOS (-1 = ilimitado, 0 = desabilitado)
    -- ========================================================================
    max_messages INT DEFAULT 2000 COMMENT 'Limite de mensagens/mês',
    max_attendants INT DEFAULT 1 COMMENT 'Limite de atendentes',
    max_departments INT DEFAULT 1 COMMENT 'Limite de setores/departamentos',
    max_contacts INT DEFAULT 1000 COMMENT 'Limite de contatos',
    max_whatsapp_instances INT DEFAULT 1 COMMENT 'Limite de instâncias WhatsApp',
    max_automation_flows INT DEFAULT 5 COMMENT 'Limite de fluxos de automação',
    max_dispatch_campaigns INT DEFAULT 10 COMMENT 'Limite de campanhas de disparo/mês',
    max_tags INT DEFAULT 20 COMMENT 'Limite de tags/etiquetas',
    max_quick_replies INT DEFAULT 50 COMMENT 'Limite de respostas rápidas',
    max_file_storage_mb INT DEFAULT 100 COMMENT 'Limite de armazenamento em MB',
    
    -- ========================================================================
    -- MÓDULOS DO SISTEMA (0 = desabilitado, 1 = habilitado)
    -- ========================================================================
    module_chat TINYINT(1) DEFAULT 1 COMMENT 'Atendimento/Chat (obrigatório)',
    module_dashboard TINYINT(1) DEFAULT 1 COMMENT 'Dashboard com métricas',
    module_dispatch TINYINT(1) DEFAULT 0 COMMENT 'Disparo em massa',
    module_contacts TINYINT(1) DEFAULT 1 COMMENT 'Gerenciar contatos',
    module_kanban TINYINT(1) DEFAULT 0 COMMENT 'Kanban/Pipeline de vendas',
    module_automation TINYINT(1) DEFAULT 0 COMMENT 'Automação/Fluxos',
    module_reports TINYINT(1) DEFAULT 0 COMMENT 'Relatórios avançados',
    module_integrations TINYINT(1) DEFAULT 0 COMMENT 'Integrações externas',
    module_api TINYINT(1) DEFAULT 0 COMMENT 'Acesso à API REST',
    module_webhooks TINYINT(1) DEFAULT 0 COMMENT 'Webhooks personalizados',
    module_ai TINYINT(1) DEFAULT 0 COMMENT 'IA/ChatGPT/Gemini',
    
    -- ========================================================================
    -- FUNCIONALIDADES AVANÇADAS (0 = desabilitado, 1 = habilitado)
    -- ========================================================================
    feature_multi_attendant TINYINT(1) DEFAULT 0 COMMENT 'Múltiplos atendentes',
    feature_departments TINYINT(1) DEFAULT 0 COMMENT 'Departamentos/Setores',
    feature_tags TINYINT(1) DEFAULT 0 COMMENT 'Tags/Etiquetas',
    feature_quick_replies TINYINT(1) DEFAULT 1 COMMENT 'Respostas rápidas',
    feature_file_upload TINYINT(1) DEFAULT 1 COMMENT 'Upload de arquivos',
    feature_media_library TINYINT(1) DEFAULT 0 COMMENT 'Biblioteca de mídia',
    feature_custom_fields TINYINT(1) DEFAULT 0 COMMENT 'Campos personalizados',
    feature_export_data TINYINT(1) DEFAULT 0 COMMENT 'Exportar dados (CSV/Excel)',
    feature_white_label TINYINT(1) DEFAULT 0 COMMENT 'White Label (marca própria)',
    feature_priority_support TINYINT(1) DEFAULT 0 COMMENT 'Suporte prioritário',
    
    -- ========================================================================
    -- INTEGRAÇÕES DISPONÍVEIS (0 = desabilitado, 1 = habilitado)
    -- ========================================================================
    integration_google_sheets TINYINT(1) DEFAULT 0 COMMENT 'Google Sheets',
    integration_zapier TINYINT(1) DEFAULT 0 COMMENT 'Zapier',
    integration_n8n TINYINT(1) DEFAULT 0 COMMENT 'N8N',
    integration_make TINYINT(1) DEFAULT 0 COMMENT 'Make (Integromat)',
    integration_crm TINYINT(1) DEFAULT 0 COMMENT 'CRM Externo',
    
    -- ========================================================================
    -- METADADOS
    -- ========================================================================
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- ========================================================================
    -- CONSTRAINTS
    -- ========================================================================
    -- NOTA: Chave estrangeira removida para compatibilidade
    -- A integridade será mantida pela aplicação
    UNIQUE KEY unique_plan (plan_id),
    INDEX idx_plan (plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- POPULAR DADOS PADRÃO PARA PLANOS EXISTENTES
-- ============================================================================

-- Plano Iniciante (slug: iniciante)
INSERT INTO plan_features (
    plan_id, 
    max_messages, max_attendants, max_departments, max_contacts,
    max_whatsapp_instances, max_automation_flows, max_dispatch_campaigns,
    max_tags, max_quick_replies, max_file_storage_mb,
    module_chat, module_dashboard, module_dispatch, module_contacts,
    module_kanban, module_automation, module_reports, module_integrations,
    module_api, module_webhooks, module_ai,
    feature_multi_attendant, feature_departments, feature_tags,
    feature_quick_replies, feature_file_upload, feature_media_library,
    feature_custom_fields, feature_export_data, feature_white_label,
    feature_priority_support,
    integration_google_sheets, integration_zapier, integration_n8n,
    integration_make, integration_crm
)
SELECT 
    id as plan_id,
    2000, 1, 1, 1000,
    1, 5, 10,
    20, 50, 100,
    1, 1, 0, 1,
    0, 0, 0, 0,
    0, 0, 0,
    0, 0, 1,
    1, 1, 0,
    0, 0, 0,
    0,
    0, 0, 0,
    0, 0
FROM pricing_plans 
WHERE slug = 'iniciante'
ON DUPLICATE KEY UPDATE plan_id = plan_id;

-- Plano Profissional (slug: profissional)
INSERT INTO plan_features (
    plan_id,
    max_messages, max_attendants, max_departments, max_contacts,
    max_whatsapp_instances, max_automation_flows, max_dispatch_campaigns,
    max_tags, max_quick_replies, max_file_storage_mb,
    module_chat, module_dashboard, module_dispatch, module_contacts,
    module_kanban, module_automation, module_reports, module_integrations,
    module_api, module_webhooks, module_ai,
    feature_multi_attendant, feature_departments, feature_tags,
    feature_quick_replies, feature_file_upload, feature_media_library,
    feature_custom_fields, feature_export_data, feature_white_label,
    feature_priority_support,
    integration_google_sheets, integration_zapier, integration_n8n,
    integration_make, integration_crm
)
SELECT 
    id as plan_id,
    10000, 2, 3, 5000,
    2, 10, 20,
    50, 100, 500,
    1, 1, 1, 1,
    1, 1, 1, 0,
    0, 0, 0,
    1, 1, 1,
    1, 1, 1,
    1, 1, 0,
    1,
    0, 0, 0,
    0, 0
FROM pricing_plans 
WHERE slug = 'profissional'
ON DUPLICATE KEY UPDATE plan_id = plan_id;

-- Plano Empresarial (slug: empresarial)
INSERT INTO plan_features (
    plan_id,
    max_messages, max_attendants, max_departments, max_contacts,
    max_whatsapp_instances, max_automation_flows, max_dispatch_campaigns,
    max_tags, max_quick_replies, max_file_storage_mb,
    module_chat, module_dashboard, module_dispatch, module_contacts,
    module_kanban, module_automation, module_reports, module_integrations,
    module_api, module_webhooks, module_ai,
    feature_multi_attendant, feature_departments, feature_tags,
    feature_quick_replies, feature_file_upload, feature_media_library,
    feature_custom_fields, feature_export_data, feature_white_label,
    feature_priority_support,
    integration_google_sheets, integration_zapier, integration_n8n,
    integration_make, integration_crm
)
SELECT 
    id as plan_id,
    -1, -1, -1, -1,
    -1, -1, -1,
    -1, -1, -1,
    1, 1, 1, 1,
    1, 1, 1, 1,
    1, 1, 1,
    1, 1, 1,
    1, 1, 1,
    1, 1, 1,
    1,
    1, 1, 1,
    1, 1
FROM pricing_plans 
WHERE slug = 'empresarial'
ON DUPLICATE KEY UPDATE plan_id = plan_id;

-- ============================================================================
-- POPULAR FEATURES PARA OUTROS PLANOS (se existirem)
-- ============================================================================
INSERT INTO plan_features (plan_id, max_messages)
SELECT id, message_limit 
FROM pricing_plans 
WHERE id NOT IN (SELECT plan_id FROM plan_features)
ON DUPLICATE KEY UPDATE plan_id = plan_id;

-- ============================================================================
-- FIM DA MIGRAÇÃO
-- ============================================================================
