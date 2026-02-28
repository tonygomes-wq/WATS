-- =====================================================
-- Tabelas para Sistema de Retenção de Dados
-- Sistema WATS - WhatsApp Sender
-- MACIP Tecnologia LTDA
-- =====================================================

-- Tabela de Histórico de Limpezas
CREATE TABLE IF NOT EXISTS cleanup_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    total_deleted INT NOT NULL DEFAULT 0,
    execution_time DECIMAL(10,2) NOT NULL,
    storage_size_mb DECIMAL(10,2) NOT NULL,
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Histórico de execuções de limpeza automática';

-- Tabela de Verificações de Storage
CREATE TABLE IF NOT EXISTS storage_checks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    total_size_mb DECIMAL(10,2) NOT NULL,
    alerts_count INT NOT NULL DEFAULT 0,
    critical_count INT NOT NULL DEFAULT 0,
    warning_count INT NOT NULL DEFAULT 0,
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Histórico de verificações de storage';

-- Tabela de Uso de Storage por Usuário
CREATE TABLE IF NOT EXISTS user_storage_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    database_size_mb DECIMAL(10,2) NOT NULL DEFAULT 0,
    media_size_mb DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_size_mb DECIMAL(10,2) NOT NULL DEFAULT 0,
    percentage_used DECIMAL(5,2) NOT NULL DEFAULT 0,
    last_checked TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user (user_id),
    INDEX idx_total_size (total_size_mb DESC),
    INDEX idx_percentage (percentage_used DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Uso de storage por usuário';

-- Tabela de Sessões (se não existir)
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    payload TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Sessões de usuários';

-- Tabela de Tokens de Reset de Senha (se não existir)
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tokens de recuperação de senha';

-- Tabela de Tentativas de Login (se não existir)
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    success BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_ip (ip_address),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Histórico de tentativas de login';

-- Tabela de Logs de Auditoria (se não existir)
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    log_data JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Logs de auditoria do sistema';

-- Adicionar coluna 'plan' na tabela users (se não existir)
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE table_schema = DATABASE() 
    AND table_name = 'users' 
    AND column_name = 'plan'
);

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE users ADD COLUMN plan VARCHAR(50) DEFAULT ''basic'' AFTER is_admin', 
    'SELECT ''Coluna plan já existe'' AS info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar coluna 'is_active' na tabela users (se não existir)
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE table_schema = DATABASE() 
    AND table_name = 'users' 
    AND column_name = 'is_active'
);

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE users ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER plan', 
    'SELECT ''Coluna is_active já existe'' AS info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- Inserir dados iniciais
-- =====================================================

-- Criar diretórios de logs (via PHP, não SQL)
-- mkdir -p logs/
-- chmod 755 logs/

-- =====================================================
-- Views úteis para monitoramento
-- =====================================================

-- View: Resumo de Storage por Usuário
CREATE OR REPLACE VIEW v_user_storage_summary AS
SELECT 
    u.id,
    u.name,
    u.email,
    u.plan,
    COALESCE(usu.total_size_mb, 0) as used_mb,
    CASE u.plan
        WHEN 'free' THEN 50
        WHEN 'basic' THEN 500
        WHEN 'professional' THEN 5000
        WHEN 'enterprise' THEN -1
        ELSE 500
    END as limit_mb,
    COALESCE(usu.percentage_used, 0) as percentage_used,
    CASE 
        WHEN COALESCE(usu.percentage_used, 0) >= 95 THEN 'critical'
        WHEN COALESCE(usu.percentage_used, 0) >= 80 THEN 'warning'
        ELSE 'ok'
    END as status,
    usu.last_checked
FROM users u
LEFT JOIN user_storage_usage usu ON u.id = usu.user_id
WHERE u.is_active = 1;

-- View: Estatísticas de Limpeza
CREATE OR REPLACE VIEW v_cleanup_stats AS
SELECT 
    DATE(created_at) as date,
    COUNT(*) as executions,
    SUM(total_deleted) as total_deleted,
    AVG(execution_time) as avg_execution_time,
    MIN(storage_size_mb) as min_storage_mb,
    MAX(storage_size_mb) as max_storage_mb,
    AVG(storage_size_mb) as avg_storage_mb
FROM cleanup_history
GROUP BY DATE(created_at)
ORDER BY date DESC;

-- View: Alertas de Storage Ativos
CREATE OR REPLACE VIEW v_active_storage_alerts AS
SELECT 
    u.id,
    u.name,
    u.email,
    u.plan,
    usu.total_size_mb as used_mb,
    usu.percentage_used,
    CASE 
        WHEN usu.percentage_used >= 95 THEN 'critical'
        WHEN usu.percentage_used >= 80 THEN 'warning'
    END as alert_level,
    usu.last_checked
FROM users u
INNER JOIN user_storage_usage usu ON u.id = usu.user_id
WHERE u.is_active = 1 
AND usu.percentage_used >= 80
ORDER BY usu.percentage_used DESC;

-- =====================================================
-- Procedures úteis
-- =====================================================

DELIMITER $$

-- Procedure: Calcular uso de storage de um usuário
DROP PROCEDURE IF EXISTS sp_calculate_user_storage$$

CREATE PROCEDURE sp_calculate_user_storage(IN p_user_id INT)
BEGIN
    DECLARE v_db_size DECIMAL(10,2);
    DECLARE v_total_size DECIMAL(10,2);
    DECLARE v_limit DECIMAL(10,2);
    DECLARE v_percentage DECIMAL(5,2);
    DECLARE v_plan VARCHAR(50);
    
    -- Obter plano do usuário
    SELECT plan INTO v_plan FROM users WHERE id = p_user_id;
    
    -- Calcular tamanho no banco
    SELECT 
        COALESCE(SUM(LENGTH(message) + LENGTH(COALESCE(response, ''))), 0) / 1024 / 1024
    INTO v_db_size
    FROM dispatch_history
    WHERE user_id = p_user_id;
    
    -- Total (DB + uploads seria calculado via PHP)
    SET v_total_size = v_db_size;
    
    -- Obter limite do plano
    SET v_limit = CASE v_plan
        WHEN 'free' THEN 50
        WHEN 'basic' THEN 500
        WHEN 'professional' THEN 5000
        WHEN 'enterprise' THEN -1
        ELSE 500
    END;
    
    -- Calcular percentual
    IF v_limit > 0 THEN
        SET v_percentage = (v_total_size / v_limit) * 100;
    ELSE
        SET v_percentage = 0;
    END IF;
    
    -- Atualizar ou inserir
    INSERT INTO user_storage_usage (
        user_id, database_size_mb, media_size_mb, total_size_mb, percentage_used
    ) VALUES (
        p_user_id, v_db_size, 0, v_total_size, v_percentage
    )
    ON DUPLICATE KEY UPDATE
        database_size_mb = v_db_size,
        total_size_mb = v_total_size,
        percentage_used = v_percentage,
        last_checked = NOW();
END$$

DELIMITER ;

-- =====================================================
-- Índices adicionais para performance
-- =====================================================

-- Otimizar consultas de limpeza (apenas se as tabelas e colunas existirem)

-- Índice para dispatch_history (se a tabela existir)
SET @table_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
    WHERE table_schema = DATABASE() AND table_name = 'dispatch_history');

SET @sql = IF(@table_exists > 0,
    'ALTER TABLE dispatch_history ADD INDEX IF NOT EXISTS idx_created_user (created_at, user_id)',
    'SELECT "Tabela dispatch_history não existe" AS info'
);

-- Nota: Usar ALTER TABLE ... ADD INDEX sem verificar se já existe pode dar erro
-- Por isso, vamos ignorar erros silenciosamente
SET @sql = IF(@table_exists > 0,
    CONCAT('ALTER TABLE dispatch_history ADD INDEX idx_created_user (created_at, user_id)'),
    'SELECT "Tabela dispatch_history não existe" AS info'
);

-- Executar apenas se a tabela existir e o índice não existir
SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = DATABASE() 
    AND table_name = 'dispatch_history' 
    AND index_name = 'idx_created_user');

SET @sql = IF(@table_exists > 0 AND @index_exists = 0, @sql, 
    'SELECT "Índice já existe ou tabela não existe" AS info');

PREPARE stmt FROM @sql; 
EXECUTE stmt; 
DEALLOCATE PREPARE stmt;

-- Índice para notifications (se a tabela existir)
SET @table_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
    WHERE table_schema = DATABASE() AND table_name = 'notifications');

SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = DATABASE() 
    AND table_name = 'notifications' 
    AND index_name = 'idx_read_date');

SET @sql = IF(@table_exists > 0 AND @index_exists = 0,
    'ALTER TABLE notifications ADD INDEX idx_read_date (is_read, read_at)',
    'SELECT "Índice já existe ou tabela não existe" AS info'
);

PREPARE stmt FROM @sql; 
EXECUTE stmt; 
DEALLOCATE PREPARE stmt;

-- =====================================================
-- Comentários nas tabelas
-- =====================================================

ALTER TABLE cleanup_history 
COMMENT = 'Histórico de execuções de limpeza automática de dados';

ALTER TABLE storage_checks 
COMMENT = 'Histórico de verificações de uso de storage';

ALTER TABLE user_storage_usage 
COMMENT = 'Uso atual de storage por usuário';

-- =====================================================
-- FIM DA MIGRATION
-- =====================================================

SELECT 'Migration concluída com sucesso!' as status;
