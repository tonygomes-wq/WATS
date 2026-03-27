-- =====================================================
-- MIGRATION: Suporte Evolution Go API
-- Data: 2026-03-27
-- Descrição: Adiciona suporte para Evolution Go API como provider
-- VERSÃO SIMPLIFICADA (sem verificações INFORMATION_SCHEMA)
-- =====================================================

-- 1. Atualizar ENUM whatsapp_provider para incluir 'evolution-go'
-- NOTA: Se der erro "Duplicate entry", ignore - significa que já existe
ALTER TABLE users 
MODIFY COLUMN whatsapp_provider 
ENUM('evolution', 'zapi', 'meta', 'baileys', 'evolution-go') 
DEFAULT 'evolution' 
COMMENT 'Provider de WhatsApp (evolution, evolution-go, zapi, meta, baileys)';

-- 2. Adicionar coluna evolution_go_instance
-- NOTA: Se der erro "Duplicate column", ignore - significa que já existe
ALTER TABLE users 
ADD COLUMN evolution_go_instance VARCHAR(100) NULL 
COMMENT 'Instance ID da Evolution Go' 
AFTER zapi_client_token;

-- 3. Adicionar coluna evolution_go_token
-- NOTA: Se der erro "Duplicate column", ignore - significa que já existe
ALTER TABLE users 
ADD COLUMN evolution_go_token VARCHAR(255) NULL 
COMMENT 'Token/API Key da Evolution Go' 
AFTER evolution_go_instance;

-- 4. Criar índice para performance
-- NOTA: Se der erro "Duplicate key", ignore - significa que já existe
CREATE INDEX idx_evolution_go_instance ON users(evolution_go_instance);

-- =====================================================
-- VERIFICAÇÃO
-- =====================================================
SELECT '✅ Migration Evolution Go concluída!' AS status;

-- Verificar se as colunas foram criadas
SHOW COLUMNS FROM users LIKE 'evolution_go%';
