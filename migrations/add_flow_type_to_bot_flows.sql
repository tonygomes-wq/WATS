-- =====================================================
-- MIGRATION: Adicionar flow_type em bot_flows
-- =====================================================
-- Permite que bot_flows suporte tanto flows conversacionais
-- quanto automation flows (IA-based)
-- =====================================================

-- =====================================================
-- 1. Adicionar coluna flow_type em bot_flows
-- =====================================================
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
WHERE flow_type IS NULL OR flow_type = '';

-- =====================================================
-- 3. Criar índice para performance
-- =====================================================
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
-- 4. Migrar automation_flows existentes para bot_flows
-- =====================================================
-- Copiar automation_flows para bot_flows com flow_type='automation'
INSERT INTO bot_flows (user_id, name, description, status, flow_type, created_at, updated_at)
SELECT 
    user_id,
    name,
    description,
    status,
    'automation' as flow_type,
    created_at,
    updated_at
FROM automation_flows
WHERE NOT EXISTS (
    SELECT 1 FROM bot_flows bf 
    WHERE bf.name = automation_flows.name 
    AND bf.user_id = automation_flows.user_id 
    AND bf.flow_type = 'automation'
);

-- =====================================================
-- 5. Adicionar coluna bot_flow_id em automation_flows
-- =====================================================
-- Para manter referência entre automation_flows e bot_flows
SET @dbname = DATABASE();
SET @tablename = 'automation_flows';
SET @columnname = 'bot_flow_id';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' BIGINT UNSIGNED NULL AFTER id')
));
PREPARE alterIfNotExists2 FROM @preparedStatement;
EXECUTE alterIfNotExists2;
DEALLOCATE PREPARE alterIfNotExists2;

-- =====================================================
-- 6. Vincular automation_flows com bot_flows
-- =====================================================
UPDATE automation_flows af
INNER JOIN bot_flows bf ON (
    bf.name = af.name 
    AND bf.user_id = af.user_id 
    AND bf.flow_type = 'automation'
)
SET af.bot_flow_id = bf.id
WHERE af.bot_flow_id IS NULL;

-- =====================================================
-- VERIFICAÇÃO
-- =====================================================
SELECT '✅ Migração concluída!' AS status;

-- Verificar coluna flow_type
SELECT 
    COLUMN_NAME,
    COLUMN_TYPE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'bot_flows'
  AND COLUMN_NAME = 'flow_type';

-- Contar flows por tipo
SELECT 
    flow_type,
    COUNT(*) as total
FROM bot_flows
GROUP BY flow_type;

SELECT '✅ Script executado com sucesso!' AS resultado;

-- =====================================================
-- FIM DA MIGRATION
-- =====================================================
