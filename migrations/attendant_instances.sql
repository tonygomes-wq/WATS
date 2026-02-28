-- ============================================
-- SISTEMA DE INSTÂNCIAS POR ATENDENTE
-- Permite que atendentes tenham suas próprias
-- instâncias WhatsApp ou usem a do supervisor
-- Criado em: 17 de Dezembro de 2025
-- ============================================

-- Tabela de Instâncias de Atendentes
CREATE TABLE IF NOT EXISTS attendant_instances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attendant_id INT NOT NULL COMMENT 'ID do supervisor_users (atendente)',
    supervisor_id INT NOT NULL COMMENT 'ID do supervisor que criou',
    instance_name VARCHAR(100) NOT NULL COMMENT 'Nome da instância na Evolution API',
    instance_token VARCHAR(255) NULL COMMENT 'Token específico da instância',
    status ENUM('disconnected', 'connecting', 'connected', 'error') DEFAULT 'disconnected',
    qr_code LONGTEXT NULL COMMENT 'QR Code base64 para conexão',
    qr_code_expires_at TIMESTAMP NULL COMMENT 'Expiração do QR Code',
    phone_number VARCHAR(20) NULL COMMENT 'Número conectado',
    phone_name VARCHAR(100) NULL COMMENT 'Nome do perfil WhatsApp',
    connected_at TIMESTAMP NULL,
    disconnected_at TIMESTAMP NULL,
    last_activity TIMESTAMP NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (attendant_id) REFERENCES supervisor_users(id) ON DELETE CASCADE,
    FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendant (attendant_id),
    INDEX idx_supervisor (supervisor_id),
    INDEX idx_status (status),
    INDEX idx_instance_name (instance_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- IMPORTANTE: Execute estes comandos UM POR VEZ
-- Se a coluna já existir, ignore o erro e continue
-- =====================================================

-- Comando 1: Adicionar coluna use_own_instance
-- (Ignore erro "Duplicate column name" se já existir)
ALTER TABLE supervisor_users 
ADD COLUMN use_own_instance TINYINT(1) DEFAULT 0 
COMMENT 'Se 1, atendente usa sua própria instância. Se 0, usa a do supervisor';

-- Comando 2: Adicionar coluna instance_config_allowed
-- (Ignore erro "Duplicate column name" se já existir)
ALTER TABLE supervisor_users 
ADD COLUMN instance_config_allowed TINYINT(1) DEFAULT 0 
COMMENT 'Se o atendente pode acessar a configuração de instância';

-- Tabela de Logs de Conexão de Instâncias
CREATE TABLE IF NOT EXISTS instance_connection_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attendant_id INT NOT NULL,
    action ENUM('connect', 'disconnect', 'qr_generated', 'qr_scanned', 'error', 'reconnect') NOT NULL,
    performed_by_type ENUM('attendant', 'supervisor', 'admin', 'system') NOT NULL,
    performed_by_id INT NULL,
    details JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (attendant_id) REFERENCES supervisor_users(id) ON DELETE CASCADE,
    INDEX idx_attendant (attendant_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Estatísticas de Atendimento por Instância
CREATE TABLE IF NOT EXISTS attendant_instance_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attendant_id INT NOT NULL,
    date DATE NOT NULL,
    messages_sent INT DEFAULT 0,
    messages_received INT DEFAULT 0,
    conversations_started INT DEFAULT 0,
    conversations_closed INT DEFAULT 0,
    avg_response_time_seconds INT DEFAULT 0,
    total_online_minutes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (attendant_id) REFERENCES supervisor_users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendant_date (attendant_id, date),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adicionar permissões de menu para configuração de instância
-- Verificar se a coluna menu_permissions existe
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'supervisor_users' 
    AND COLUMN_NAME = 'menu_permissions'
);

-- Se não existir, criar
SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE supervisor_users ADD COLUMN menu_permissions JSON NULL COMMENT ''Permissões de menu do atendente''',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
