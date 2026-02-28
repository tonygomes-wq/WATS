-- =====================================================
-- MIGRATION: Suporte Multi-Provider + JID/LID
-- Data: 2024
-- Descrição: Adiciona suporte para múltiplos providers WhatsApp
--            (Evolution API + Z-API) e prepara sistema para JID→LID
-- NOTA: Sistema usa tabela 'users' para armazenar instâncias
-- =====================================================

-- 1. Atualizar tabela users para suportar múltiplos providers
-- Verificar se coluna whatsapp_provider já existe (pode já existir)
SET @dbname = DATABASE();
SET @tablename = 'users';
SET @columnname = 'whatsapp_provider';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  'ALTER TABLE users ADD COLUMN whatsapp_provider ENUM(''evolution'', ''zapi'', ''meta'', ''baileys'') DEFAULT ''evolution'' AFTER evolution_token'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Adicionar coluna zapi_instance_id
SET @columnname = 'zapi_instance_id';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  'ALTER TABLE users ADD COLUMN zapi_instance_id VARCHAR(100) NULL COMMENT ''Instance ID da Z-API'' AFTER whatsapp_provider'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Adicionar coluna zapi_token
SET @columnname = 'zapi_token';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  'ALTER TABLE users ADD COLUMN zapi_token VARCHAR(255) NULL COMMENT ''Token da Z-API'' AFTER zapi_instance_id'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Adicionar coluna provider_config (JSON para configs extras)
SET @columnname = 'provider_config';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  'ALTER TABLE users ADD COLUMN provider_config JSON NULL COMMENT ''Configurações específicas do provider'' AFTER zapi_token'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Adicionar coluna supports_lid
SET @columnname = 'supports_lid';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  'ALTER TABLE users ADD COLUMN supports_lid TINYINT(1) DEFAULT 0 COMMENT ''Provider suporta LID'' AFTER provider_config'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Atualizar usuários existentes com Evolution para marcar provider
UPDATE users 
SET whatsapp_provider = 'evolution' 
WHERE evolution_instance IS NOT NULL 
  AND evolution_instance != ''
  AND (whatsapp_provider IS NULL OR whatsapp_provider = '');

-- Atualizar usuários com Meta para marcar provider
UPDATE users 
SET whatsapp_provider = 'meta' 
WHERE meta_phone_number_id IS NOT NULL 
  AND meta_phone_number_id != ''
  AND (whatsapp_provider IS NULL OR whatsapp_provider = '');

-- 2. Criar tabela de identificadores WhatsApp (Phone/JID/LID)
-- Nota: Removida FOREIGN KEY para evitar erro de tipo incompatível
CREATE TABLE IF NOT EXISTS whatsapp_identifiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contact_id INT NOT NULL,
    phone VARCHAR(25) NULL COMMENT 'Número de telefone',
    jid VARCHAR(100) NULL COMMENT 'Jabber ID (formato antigo)',
    lid VARCHAR(100) NULL COMMENT 'Linked ID (formato novo Meta)',
    last_seen_at DATETIME NULL COMMENT 'Última vez que identificador foi visto',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_contact (contact_id),
    INDEX idx_phone (phone),
    INDEX idx_jid (jid),
    INDEX idx_lid (lid),
    UNIQUE KEY unique_contact_phone (contact_id, phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Atualizar tabela contacts para suportar identificadores
SET @tablename = 'contacts';

-- Adicionar primary_identifier
SET @columnname = 'primary_identifier';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  'ALTER TABLE contacts ADD COLUMN primary_identifier VARCHAR(100) NULL COMMENT ''Identificador principal (phone, jid ou lid)'' AFTER phone'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Adicionar identifier_type
SET @columnname = 'identifier_type';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  'ALTER TABLE contacts ADD COLUMN identifier_type ENUM(''phone'', ''jid'', ''lid'') DEFAULT ''phone'' COMMENT ''Tipo do identificador'' AFTER primary_identifier'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Migrar dados existentes em contacts
UPDATE contacts 
SET primary_identifier = phone,
    identifier_type = 'phone'
WHERE primary_identifier IS NULL AND phone IS NOT NULL;

-- 4. Atualizar tabela chat_conversations para suportar identificadores
SET @tablename = 'chat_conversations';

-- Adicionar remote_jid
SET @columnname = 'remote_jid';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  'ALTER TABLE chat_conversations ADD COLUMN remote_jid VARCHAR(100) NULL COMMENT ''JID remoto do WhatsApp'' AFTER phone'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Adicionar identifier_type
SET @columnname = 'identifier_type';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  'ALTER TABLE chat_conversations ADD COLUMN identifier_type ENUM(''phone'', ''jid'', ''lid'') DEFAULT ''phone'' COMMENT ''Tipo do identificador'' AFTER remote_jid'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Migrar dados existentes em chat_conversations
UPDATE chat_conversations 
SET remote_jid = CONCAT(phone, '@s.whatsapp.net'),
    identifier_type = 'phone'
WHERE remote_jid IS NULL AND phone IS NOT NULL;

-- 5. Criar índices para performance
-- Índice em users para whatsapp_provider
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
    WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'idx_whatsapp_provider');
SET @sqlstmt := IF(@exist > 0, 'SELECT ''Index exists.''', 
    'CREATE INDEX idx_whatsapp_provider ON users(whatsapp_provider)');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice em contacts
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
    WHERE table_schema = DATABASE() AND table_name = 'contacts' AND index_name = 'idx_identifier_type');
SET @sqlstmt := IF(@exist > 0, 'SELECT ''Index exists.''', 
    'CREATE INDEX idx_identifier_type ON contacts(identifier_type)');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice em chat_conversations
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
    WHERE table_schema = DATABASE() AND table_name = 'chat_conversations' AND index_name = 'idx_conversation_identifier');
SET @sqlstmt := IF(@exist > 0, 'SELECT ''Index exists.''', 
    'CREATE INDEX idx_conversation_identifier ON chat_conversations(identifier_type, remote_jid)');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- VERIFICAÇÃO
-- =====================================================
SELECT '✅ Migration Multi-Provider concluída!' AS status;

-- Verificar colunas adicionadas
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    COLUMN_TYPE,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('users', 'contacts', 'chat_conversations', 'whatsapp_identifiers')
  AND COLUMN_NAME IN ('whatsapp_provider', 'zapi_instance_id', 'zapi_token', 'provider_config', 'supports_lid', 'primary_identifier', 'identifier_type', 'remote_jid', 'lid')
ORDER BY TABLE_NAME, ORDINAL_POSITION;
