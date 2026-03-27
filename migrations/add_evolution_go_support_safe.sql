-- =====================================================
-- MIGRATION: Suporte Evolution Go API - VERSÃO SEGURA
-- Data: 2026-03-27
-- Descrição: Adiciona suporte para Evolution Go API como provider
-- INSTRUÇÕES: Execute comando por comando, ignorando erros de duplicação
-- =====================================================

-- PASSO 1: Atualizar ENUM whatsapp_provider
-- Se der erro, pule para o próximo passo
ALTER TABLE users 
MODIFY COLUMN whatsapp_provider 
ENUM('evolution', 'zapi', 'meta', 'baileys', 'evolution-go') 
DEFAULT 'evolution' 
COMMENT 'Provider de WhatsApp (evolution, evolution-go, zapi, meta, baileys)';

-- PASSO 2: Adicionar coluna evolution_go_instance
-- Se der erro "Duplicate column name", ignore e continue
ALTER TABLE users 
ADD COLUMN evolution_go_instance VARCHAR(100) NULL 
COMMENT 'Instance ID da Evolution Go' 
AFTER zapi_client_token;

-- PASSO 3: Adicionar coluna evolution_go_token
-- Se der erro "Duplicate column name", ignore e continue
ALTER TABLE users 
ADD COLUMN evolution_go_token VARCHAR(255) NULL 
COMMENT 'Token/API Key da Evolution Go' 
AFTER evolution_go_instance;

-- PASSO 4: Criar índice
-- Se der erro "Duplicate key name", ignore e continue
CREATE INDEX idx_evolution_go_instance ON users(evolution_go_instance);

-- PASSO 5: Verificar se tudo foi criado
SELECT '✅ Verificando estrutura...' AS status;

SHOW COLUMNS FROM users LIKE 'evolution_go%';

SELECT '✅ Migration Evolution Go concluída!' AS status;
