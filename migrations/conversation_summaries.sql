-- ============================================
-- MIGRATION: Conversation Summaries
-- Sistema de Resumo de Conversas para Supervisores
-- Data: 23/12/2025
-- ============================================

-- Tabela principal de resumos
CREATE TABLE IF NOT EXISTS conversation_summaries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    user_id INT NOT NULL,
    attendant_id INT NULL,
    contact_id INT NULL,
    
    -- Dados da conversa
    message_count INT DEFAULT 0,
    duration_seconds INT DEFAULT 0,
    start_time DATETIME NULL,
    end_time DATETIME NULL,
    
    -- Resumo gerado pela IA
    summary_text LONGTEXT NULL,
    summary_json JSON NULL COMMENT 'Estrutura: {motivo, acoes, resultado, sentimento, pontos_atencao, keywords}',
    
    -- Análise
    sentiment ENUM('positive','neutral','negative','mixed') DEFAULT 'neutral',
    keywords JSON NULL,
    topics JSON NULL,
    
    -- Metadados
    generated_by INT NOT NULL COMMENT 'ID do supervisor/admin que gerou',
    generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ai_model VARCHAR(100) DEFAULT 'gemini-2.5-flash',
    processing_time_ms INT DEFAULT 0,
    
    -- Controle
    status ENUM('pending','processing','completed','failed') DEFAULT 'pending',
    error_message TEXT NULL,
    
    -- Índices
    INDEX idx_conversation (conversation_id),
    INDEX idx_user (user_id),
    INDEX idx_attendant (attendant_id),
    INDEX idx_generated_by (generated_by),
    INDEX idx_status (status),
    INDEX idx_generated_at (generated_at),
    INDEX idx_sentiment (sentiment),
    
    -- Chaves estrangeiras
    FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (attendant_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de logs de processamento em lote
CREATE TABLE IF NOT EXISTS conversation_summary_batches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    generated_by INT NOT NULL,
    
    total_conversations INT DEFAULT 0,
    completed_count INT DEFAULT 0,
    failed_count INT DEFAULT 0,
    
    status ENUM('pending','processing','completed','failed','cancelled') DEFAULT 'pending',
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    
    conversation_ids JSON NULL,
    summary_ids JSON NULL,
    
    INDEX idx_user (user_id),
    INDEX idx_generated_by (generated_by),
    INDEX idx_status (status),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adicionar coluna para armazenar ID do último resumo na conversa (opcional)
ALTER TABLE chat_conversations 
ADD COLUMN last_summary_id BIGINT UNSIGNED NULL AFTER bot_session_id,
ADD INDEX idx_last_summary (last_summary_id);

-- Inserir configuração padrão para o sistema de resumos
INSERT IGNORE INTO system_config (config_key, config_value, description, created_at) VALUES
('summary_max_per_minute', '10', 'Máximo de resumos individuais por minuto', NOW()),
('summary_max_batch_per_hour', '50', 'Máximo de resumos em lote por hora', NOW()),
('summary_min_messages', '3', 'Mínimo de mensagens para gerar resumo', NOW()),
('summary_enabled', '1', 'Sistema de resumos habilitado', NOW());
