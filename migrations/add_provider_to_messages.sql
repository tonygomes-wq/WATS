-- Migração: Adicionar campo provider para suportar múltiplas APIs
-- Data: 2026-01-13

-- Adicionar coluna provider na tabela chat_messages
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS 
     WHERE table_name = 'chat_messages' 
     AND table_schema = DATABASE() 
     AND column_name = 'provider') > 0,
    'SELECT "Coluna provider já existe"',
    "ALTER TABLE chat_messages ADD COLUMN provider ENUM('evolution', 'meta') DEFAULT 'evolution' COMMENT 'API provider usada para esta mensagem'"
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar coluna external_message_id para armazenar ID da Meta API
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS 
     WHERE table_name = 'chat_messages' 
     AND table_schema = DATABASE() 
     AND column_name = 'external_message_id') > 0,
    'SELECT "Coluna external_message_id já existe"',
    "ALTER TABLE chat_messages ADD COLUMN external_message_id VARCHAR(255) NULL COMMENT 'ID externo da mensagem (Meta API)'"
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Criar índice para external_message_id
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS 
     WHERE table_name = 'chat_messages' 
     AND table_schema = DATABASE() 
     AND index_name = 'idx_external_message_id') > 0,
    'SELECT "Índice idx_external_message_id já existe"',
    "CREATE INDEX idx_external_message_id ON chat_messages(external_message_id)"
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar coluna provider na tabela chat_conversations
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS 
     WHERE table_name = 'chat_conversations' 
     AND table_schema = DATABASE() 
     AND column_name = 'provider') > 0,
    'SELECT "Coluna provider já existe em chat_conversations"',
    "ALTER TABLE chat_conversations ADD COLUMN provider ENUM('evolution', 'meta') DEFAULT 'evolution' COMMENT 'API provider usada para esta conversa'"
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Migração concluída com sucesso!' as status;
