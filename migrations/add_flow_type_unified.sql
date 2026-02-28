-- =====================================================
-- MIGRATION: Unificar Bot Flows e Automation Flows
-- =====================================================
-- Adiciona coluna flow_type para permitir interface unificada
-- =====================================================

-- =====================================================
-- 1. Adicionar coluna flow_type em bot_flows
-- =====================================================
-- Verificar se a coluna já existe antes de adicionar
SET @dbname = DATABASE();
SET @tablename = 'bot_flows';
SET @columnname = 'flow_type';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  'SELECT 1', -- Coluna já existe, não faz nada
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' ENUM(''conversational'', ''automation'') DEFAULT ''conversational'' COMMENT ''Tipo do fluxo: conversacional (visual) ou automation (IA)'' AFTER status')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- =====================================================
-- 2. Atualizar flows existentes
-- =====================================================
-- Todos os bot_flows existentes são conversacionais
UPDATE bot_flows 
SET flow_type = 'conversational' 
WHERE flow_type IS NULL;

-- =====================================================
-- 3. Criar índice para performance
-- =====================================================
-- Verificar se o índice já existe antes de criar
SET @dbname = DATABASE();
SET @tablename = 'bot_flows';
SET @indexname = 'idx_bot_flows_type';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (INDEX_NAME = @indexname)
  ) > 0,
  'SELECT 1', -- Índice já existe, não faz nada
  CONCAT('CREATE INDEX ', @indexname, ' ON ', @tablename, '(user_id, flow_type, status)')
));
PREPARE createIndexIfNotExists FROM @preparedStatement;
EXECUTE createIndexIfNotExists;
DEALLOCATE PREPARE createIndexIfNotExists;

-- =====================================================
-- 4. Criar view unificada (opcional - para relatórios)
-- =====================================================
CREATE OR REPLACE VIEW unified_flows_view AS
SELECT 
    CONCAT('bot_', id) as unified_id,
    id as original_id,
    'bot_flows' COLLATE utf8mb4_unicode_ci as source_table,
    user_id,
    name COLLATE utf8mb4_unicode_ci as name,
    description COLLATE utf8mb4_unicode_ci as description,
    status COLLATE utf8mb4_unicode_ci as status,
    'conversational' COLLATE utf8mb4_unicode_ci as flow_type,
    created_at,
    updated_at
FROM bot_flows
UNION ALL
SELECT 
    CONCAT('auto_', id) as unified_id,
    id as original_id,
    'automation_flows' COLLATE utf8mb4_unicode_ci as source_table,
    user_id,
    name COLLATE utf8mb4_unicode_ci as name,
    description COLLATE utf8mb4_unicode_ci as description,
    status COLLATE utf8mb4_unicode_ci as status,
    'automation' COLLATE utf8mb4_unicode_ci as flow_type,
    created_at,
    updated_at
FROM automation_flows;

-- =====================================================
-- VERIFICAÇÃO
-- =====================================================
SELECT '✅ Migração concluída!' AS status;

-- Verificar se a tabela bot_flows existe
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN '✅ Tabela bot_flows existe'
        ELSE '⚠️ Tabela bot_flows não existe - integração não necessária'
    END as resultado
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'bot_flows';

-- Se bot_flows existir, verificar coluna flow_type
SELECT 
    COLUMN_NAME,
    COLUMN_TYPE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'bot_flows'
  AND COLUMN_NAME = 'flow_type';

-- Contar flows por tipo (só se bot_flows existir)
-- SELECT 
--     flow_type,
--     COUNT(*) as total
-- FROM bot_flows
-- GROUP BY flow_type;

SELECT '✅ Script executado com sucesso!' AS resultado;

-- =====================================================
-- FIM DA MIGRATION
-- =====================================================
