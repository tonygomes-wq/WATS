-- =====================================================
-- INTEGRAÇÃO MICROSOFT TEAMS
-- Adiciona suporte ao canal Microsoft Teams
-- =====================================================

-- 1. Criar tabela de configuração de canais do Teams
CREATE TABLE IF NOT EXISTS teams_channels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    channel_name VARCHAR(255) NOT NULL COMMENT 'Nome do canal do Teams',
    webhook_url TEXT NOT NULL COMMENT 'URL do webhook do Teams',
    team_name VARCHAR(255) DEFAULT NULL COMMENT 'Nome do time/equipe',
    is_active TINYINT(1) DEFAULT 1 COMMENT '1 = ativo, 0 = inativo',
    last_message_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_user_active (user_id, is_active),
    INDEX idx_last_message (last_message_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Configuração de canais do Microsoft Teams';

-- 2. Adicionar coluna para armazenar ID do canal do Teams nas conversas
-- Verificar se a coluna já existe antes de adicionar
SET @col_exists = (SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'chat_conversations' 
    AND COLUMN_NAME = 'teams_channel_id');

SET @query = IF(@col_exists = 0,
    'ALTER TABLE chat_conversations ADD COLUMN teams_channel_id INT UNSIGNED DEFAULT NULL COMMENT "ID do canal do Teams vinculado" AFTER channel_id',
    'SELECT "Coluna teams_channel_id já existe" AS message');

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Criar índice
SET @index_exists = (SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'chat_conversations' 
    AND INDEX_NAME = 'idx_teams_channel');

SET @query = IF(@index_exists = 0,
    'CREATE INDEX idx_teams_channel ON chat_conversations(teams_channel_id)',
    'SELECT "Índice idx_teams_channel já existe" AS message');

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Adicionar coluna para armazenar dados extras do Teams
SET @col_exists = (SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'chat_messages' 
    AND COLUMN_NAME = 'teams_data');

SET @query = IF(@col_exists = 0,
    'ALTER TABLE chat_messages ADD COLUMN teams_data JSON DEFAULT NULL COMMENT "Dados extras do Teams (activity ID, conversation ID, etc.)" AFTER additional_data',
    'SELECT "Coluna teams_data já existe" AS message');

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Criar tabela de log de webhooks do Teams (para debug)
CREATE TABLE IF NOT EXISTS teams_webhook_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    webhook_url TEXT,
    payload JSON NOT NULL COMMENT 'Payload completo recebido',
    status ENUM('success', 'error', 'ignored') DEFAULT 'success',
    error_message TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_status (user_id, status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Log de webhooks recebidos do Microsoft Teams';

-- 5. Inserir configuração padrão para Teams
INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES
('teams_enabled', '1', 'Habilitar integração com Microsoft Teams'),
('teams_webhook_timeout', '30', 'Timeout em segundos para webhooks do Teams'),
('teams_max_message_length', '4000', 'Tamanho máximo de mensagem do Teams');

-- =====================================================
-- VERIFICAÇÃO
-- =====================================================
SELECT 'Migração do Microsoft Teams concluída com sucesso!' AS status;
