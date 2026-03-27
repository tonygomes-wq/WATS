-- =====================================================
-- MIGRATION: Suporte Evolution Go API
-- Data: 2026-03-27
-- Descrição: Adiciona suporte para Evolution Go API como provider
-- =====================================================

-- 1. Atualizar ENUM whatsapp_provider para incluir 'evolution-go'
ALTER TABLE users 
MODIFY COLUMN whatsapp_provider 
ENUM('evolution', 'zapi', 'meta', 'baileys', 'evolution-go') 
DEFAULT 'evolution' 
COMMENT 'Provider de WhatsApp (evolution, evolution-go, zapi, meta, baileys)';

-- 2. Adicionar colunas para Evolution Go
-- Verificar se coluna evolution_go_instance já existe
SET @dbname = DATABASE();
SET @tablename = 'users';
SET @columnname = 'evolution_go_instance';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  'ALTER TABLE users ADD COLUMN evolution_go_instance VARCHAR(100) NULL COMMENT ''Instance ID da Evolution Go'' AFTER zapi_client_token'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Adicionar coluna evolution_go_token
SET @columnname = 'evolution_go_token';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  'ALTER TABLE users ADD COLUMN evolution_go_token VARCHAR(255) NULL COMMENT ''Token/API Key da Evolution Go'' AFTER evolution_go_instance'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 3. Criar índice para performance
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
    WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'idx_evolution_go_instance');
SET @sqlstmt := IF(@exist > 0, 'SELECT ''Index exists.''', 
    'CREATE INDEX idx_evolution_go_instance ON users(evolution_go_instance)');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- VERIFICAÇÃO
-- =====================================================
SELECT '✅ Migration Evolution Go concluída!' AS status;

-- Verificar colunas adicionadas
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    COLUMN_TYPE,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'users'
  AND COLUMN_NAME IN ('whatsapp_provider', 'evolution_go_instance', 'evolution_go_token')
ORDER BY ORDINAL_POSITION;
