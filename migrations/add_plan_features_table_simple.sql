-- ============================================================================
-- Migration SIMPLIFICADA: Sistema de Permissões Granulares para Planos
-- Data: 2026-02-25
-- Descrição: Versão sem população automática de dados
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
    UNIQUE KEY unique_plan (plan_id),
    INDEX idx_plan (plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- NOTA: População de dados deve ser feita manualmente ou via interface
-- ============================================================================
-- Após criar a tabela, use a interface em financial.php para configurar
-- as permissões de cada plano.
-- ============================================================================

