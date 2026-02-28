-- =====================================================
-- MIGRATION: Automation Flows Tables
-- =====================================================
-- Este arquivo cria as tabelas necessárias para o sistema
-- de Automation Flows do WATS
-- =====================================================
-- Execute este arquivo no phpMyAdmin da Hostgator
-- =====================================================

-- =====================================================
-- TABELA: automation_flows
-- Armazena as configurações dos fluxos de automação
-- =====================================================
CREATE TABLE IF NOT EXISTS `automation_flows` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL COMMENT 'Nome do fluxo de automação',
  `description` TEXT DEFAULT NULL COMMENT 'Descrição do que o fluxo faz',
  `status` ENUM('active', 'paused') DEFAULT 'active' COMMENT 'Status do fluxo',
  `trigger_type` ENUM('keyword', 'first_message', 'off_hours', 'no_response') NOT NULL COMMENT 'Tipo de gatilho',
  `trigger_config` JSON NOT NULL COMMENT 'Configuração do trigger (keywords, horários, etc)',
  `agent_config` JSON DEFAULT NULL COMMENT 'Configuração do agente de IA (provider, model, prompt)',
  `action_config` JSON NOT NULL COMMENT 'Configuração das ações a executar',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_status` (`user_id`, `status`),
  KEY `idx_trigger_type` (`trigger_type`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Fluxos de automação baseados em IA';

-- =====================================================
-- TABELA: automation_flow_logs
-- Armazena logs de execução dos fluxos de automação
-- =====================================================
CREATE TABLE IF NOT EXISTS `automation_flow_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `flow_id` INT UNSIGNED NOT NULL COMMENT 'ID do fluxo executado',
  `conversation_id` INT UNSIGNED DEFAULT NULL COMMENT 'ID da conversa relacionada',
  `trigger_payload` JSON DEFAULT NULL COMMENT 'Dados que acionaram o trigger',
  `agent_prompt` TEXT DEFAULT NULL COMMENT 'Prompt enviado para a IA',
  `agent_response` TEXT DEFAULT NULL COMMENT 'Resposta gerada pela IA',
  `action_results` JSON DEFAULT NULL COMMENT 'Resultados das ações executadas',
  `status` ENUM('success', 'failed') DEFAULT 'success' COMMENT 'Status da execução',
  `error_message` TEXT DEFAULT NULL COMMENT 'Mensagem de erro se falhou',
  `execution_time_ms` INT DEFAULT NULL COMMENT 'Tempo de execução em milissegundos',
  `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/hora da execução',
  PRIMARY KEY (`id`),
  KEY `idx_flow_id` (`flow_id`),
  KEY `idx_conversation_id` (`conversation_id`),
  KEY `idx_status` (`status`),
  KEY `idx_executed_at` (`executed_at`),
  CONSTRAINT `fk_automation_flow_logs_flow` 
    FOREIGN KEY (`flow_id`) 
    REFERENCES `automation_flows` (`id`) 
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Logs de execução dos fluxos de automação';

-- =====================================================
-- ADICIONAR COLUNA custom_fields NA TABELA contacts
-- (Necessária para a ação update_field)
-- =====================================================
-- Verifica se a coluna já existe antes de adicionar
SET @dbname = DATABASE();
SET @tablename = 'contacts';
SET @columnname = 'custom_fields';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  'SELECT 1', -- Coluna já existe, não faz nada
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' JSON DEFAULT NULL COMMENT ''Campos customizados do contato''')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- =====================================================
-- VERIFICAÇÃO DA INSTALAÇÃO
-- =====================================================
SELECT '✅ Tabelas de Automation Flows criadas com sucesso!' AS status;

SELECT 
    TABLE_NAME AS tabela,
    TABLE_ROWS AS linhas_aproximadas,
    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS tamanho_mb
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('automation_flows', 'automation_flow_logs')
ORDER BY TABLE_NAME;

-- Verificar se coluna custom_fields foi adicionada
SELECT 
    COLUMN_NAME AS coluna,
    COLUMN_TYPE AS tipo,
    IS_NULLABLE AS permite_null,
    COLUMN_COMMENT AS comentario
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'contacts'
  AND COLUMN_NAME = 'custom_fields';

SELECT '✅ Migração concluída!' AS resultado;

-- =====================================================
-- FIM DA MIGRATION
-- =====================================================
