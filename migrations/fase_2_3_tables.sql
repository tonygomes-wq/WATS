-- =====================================================
-- FASE 2 e 3 - Tabelas para Melhorias Avançadas
-- Sistema WATS - Disparo em Massa WhatsApp
-- =====================================================

-- Tabela de Notificações
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data JSON,
    priority ENUM('low', 'normal', 'high') DEFAULT 'normal',
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, is_read, created_at),
    INDEX idx_user_created (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Configurações do Usuário (Auto-Reply)
CREATE TABLE IF NOT EXISTS user_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    auto_reply_enabled BOOLEAN DEFAULT FALSE,
    auto_reply_config JSON,
    notification_preferences JSON,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Log de Auto-Reply
CREATE TABLE IF NOT EXISTS auto_reply_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    phone VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    sentiment_trigger VARCHAR(20),
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- FASE 3 - Análise Preditiva
-- =====================================================

-- Tabela de Analytics de Tempo (Melhor Horário)
CREATE TABLE IF NOT EXISTS dispatch_time_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    hour_of_day TINYINT NOT NULL,
    day_of_week TINYINT NOT NULL,
    total_sent INT DEFAULT 0,
    total_delivered INT DEFAULT 0,
    total_read INT DEFAULT 0,
    total_responses INT DEFAULT 0,
    avg_response_time INT DEFAULT 0,
    engagement_score DECIMAL(5,2) DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_time (user_id, hour_of_day, day_of_week),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_score (user_id, engagement_score DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- FASE 3 - Segmentação por Engajamento
-- =====================================================

-- Tabela de Scores de Engajamento por Contato
CREATE TABLE IF NOT EXISTS contact_engagement_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    contact_id INT NOT NULL,
    engagement_level ENUM('high', 'medium', 'low', 'inactive') DEFAULT 'medium',
    total_messages_received INT DEFAULT 0,
    total_responses_sent INT DEFAULT 0,
    avg_response_time INT DEFAULT 0,
    last_interaction TIMESTAMP NULL,
    positive_responses INT DEFAULT 0,
    negative_responses INT DEFAULT 0,
    score DECIMAL(5,2) DEFAULT 50.00,
    last_calculated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_contact (user_id, contact_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
    INDEX idx_user_level (user_id, engagement_level),
    INDEX idx_user_score (user_id, score DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- FASE 3 - A/B Testing
-- =====================================================

-- Tabela de Testes A/B
CREATE TABLE IF NOT EXISTS ab_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('draft', 'running', 'completed', 'cancelled') DEFAULT 'draft',
    variant_a_message TEXT NOT NULL,
    variant_b_message TEXT NOT NULL,
    variant_a_sent INT DEFAULT 0,
    variant_b_sent INT DEFAULT 0,
    variant_a_responses INT DEFAULT 0,
    variant_b_responses INT DEFAULT 0,
    variant_a_positive INT DEFAULT 0,
    variant_b_positive INT DEFAULT 0,
    winner ENUM('a', 'b', 'tie', 'none') DEFAULT 'none',
    confidence_level DECIMAL(5,2) DEFAULT 0,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_status (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Resultados de Testes A/B
CREATE TABLE IF NOT EXISTS ab_test_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_id INT NOT NULL,
    dispatch_id INT NOT NULL,
    variant ENUM('a', 'b') NOT NULL,
    response_received BOOLEAN DEFAULT FALSE,
    response_sentiment ENUM('positive', 'neutral', 'negative', 'unknown') DEFAULT 'unknown',
    response_time_seconds INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (test_id) REFERENCES ab_tests(id) ON DELETE CASCADE,
    FOREIGN KEY (dispatch_id) REFERENCES dispatch_history(id) ON DELETE CASCADE,
    INDEX idx_test_variant (test_id, variant)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- FASE 3 - Integração CRM
-- =====================================================

-- Tabela de Integrações CRM
CREATE TABLE IF NOT EXISTS crm_integrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    crm_type ENUM('pipedrive', 'hubspot', 'salesforce', 'rd_station', 'custom') NOT NULL,
    api_key VARCHAR(255) NOT NULL,
    api_secret VARCHAR(255),
    webhook_url VARCHAR(500),
    sync_enabled BOOLEAN DEFAULT TRUE,
    sync_contacts BOOLEAN DEFAULT TRUE,
    sync_responses BOOLEAN DEFAULT TRUE,
    last_sync TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_crm (user_id, crm_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Log de Sincronização CRM
CREATE TABLE IF NOT EXISTS crm_sync_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    integration_id INT NOT NULL,
    sync_type ENUM('contact', 'response', 'campaign') NOT NULL,
    direction ENUM('to_crm', 'from_crm') NOT NULL,
    status ENUM('success', 'failed', 'partial') NOT NULL,
    records_processed INT DEFAULT 0,
    error_message TEXT,
    synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (integration_id) REFERENCES crm_integrations(id) ON DELETE CASCADE,
    INDEX idx_integration_date (integration_id, synced_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Alterações na tabela dispatch_history (colunas adicionais)
-- Execute este bloco separadamente se der erro de coluna duplicada
-- =====================================================

-- Procedimento para adicionar colunas de forma segura
DROP PROCEDURE IF EXISTS add_dispatch_columns;
DELIMITER //
CREATE PROCEDURE add_dispatch_columns()
BEGIN
    -- campaign_id
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_schema = DATABASE() AND table_name = 'dispatch_history' AND column_name = 'campaign_id') THEN
        ALTER TABLE dispatch_history ADD COLUMN campaign_id INT NULL;
    END IF;
    
    -- phone
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_schema = DATABASE() AND table_name = 'dispatch_history' AND column_name = 'phone') THEN
        ALTER TABLE dispatch_history ADD COLUMN phone VARCHAR(20) NULL;
    END IF;
    
    -- contact_name
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_schema = DATABASE() AND table_name = 'dispatch_history' AND column_name = 'contact_name') THEN
        ALTER TABLE dispatch_history ADD COLUMN contact_name VARCHAR(100) NULL;
    END IF;
    
    -- message_id
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_schema = DATABASE() AND table_name = 'dispatch_history' AND column_name = 'message_id') THEN
        ALTER TABLE dispatch_history ADD COLUMN message_id VARCHAR(100) NULL;
    END IF;
    
    -- has_attachment
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_schema = DATABASE() AND table_name = 'dispatch_history' AND column_name = 'has_attachment') THEN
        ALTER TABLE dispatch_history ADD COLUMN has_attachment BOOLEAN DEFAULT FALSE;
    END IF;
    
    -- attachment_type
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_schema = DATABASE() AND table_name = 'dispatch_history' AND column_name = 'attachment_type') THEN
        ALTER TABLE dispatch_history ADD COLUMN attachment_type VARCHAR(50) NULL;
    END IF;
    
    -- error_message
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_schema = DATABASE() AND table_name = 'dispatch_history' AND column_name = 'error_message') THEN
        ALTER TABLE dispatch_history ADD COLUMN error_message TEXT NULL;
    END IF;
    
    -- delivered_at
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_schema = DATABASE() AND table_name = 'dispatch_history' AND column_name = 'delivered_at') THEN
        ALTER TABLE dispatch_history ADD COLUMN delivered_at TIMESTAMP NULL;
    END IF;
    
    -- read_at
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_schema = DATABASE() AND table_name = 'dispatch_history' AND column_name = 'read_at') THEN
        ALTER TABLE dispatch_history ADD COLUMN read_at TIMESTAMP NULL;
    END IF;
END //
DELIMITER ;

CALL add_dispatch_columns();
DROP PROCEDURE IF EXISTS add_dispatch_columns;

-- =====================================================
-- Índices Adicionais para Performance
-- =====================================================

-- Procedimento para adicionar índices de forma segura
DROP PROCEDURE IF EXISTS add_indexes_safe;
DELIMITER //
CREATE PROCEDURE add_indexes_safe()
BEGIN
    -- Índice dispatch_responses.dispatch_id
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics 
                   WHERE table_schema = DATABASE() AND table_name = 'dispatch_responses' AND index_name = 'idx_dr_dispatch_id') THEN
        CREATE INDEX idx_dr_dispatch_id ON dispatch_responses (dispatch_id);
    END IF;
    
    -- Índice dispatch_history.message_id
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics 
                   WHERE table_schema = DATABASE() AND table_name = 'dispatch_history' AND index_name = 'idx_dh_message_id') THEN
        CREATE INDEX idx_dh_message_id ON dispatch_history (message_id);
    END IF;
    
    -- Índice dispatch_history.campaign_id
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics 
                   WHERE table_schema = DATABASE() AND table_name = 'dispatch_history' AND index_name = 'idx_dh_campaign_id') THEN
        CREATE INDEX idx_dh_campaign_id ON dispatch_history (campaign_id);
    END IF;
END //
DELIMITER ;

CALL add_indexes_safe();
DROP PROCEDURE IF EXISTS add_indexes_safe;

-- =====================================================
-- Dados Iniciais (Opcional)
-- =====================================================

-- Inserir configurações padrão para usuários existentes
INSERT IGNORE INTO user_settings (user_id, auto_reply_enabled, auto_reply_config)
SELECT id, FALSE, JSON_OBJECT(
    'positive_template', 'Olá {nome}! 😊 Obrigado pela sua mensagem positiva!',
    'negative_template', 'Olá {nome}, lamentamos muito. Um atendente entrará em contato.',
    'neutral_template', 'Olá {nome}! 👋 Recebemos sua mensagem e retornaremos em breve.'
)
FROM users;

-- =====================================================
-- FIM DO SCRIPT
-- =====================================================
