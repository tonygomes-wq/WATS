-- Migration: Adicionar suporte a múltiplos tipos de proprietários (supervisor/atendente)
-- Data: 2026-02-25

-- Adicionar colunas para identificar tipo de proprietário
ALTER TABLE bot_flows 
ADD COLUMN owner_type ENUM('supervisor', 'attendant') DEFAULT 'supervisor' AFTER user_id,
ADD COLUMN owner_id BIGINT UNSIGNED NULL AFTER owner_type;

-- Migrar dados existentes (todos os fluxos atuais são de supervisores)
UPDATE bot_flows SET owner_type = 'supervisor', owner_id = user_id WHERE owner_id IS NULL;

-- Adicionar índice para melhor performance
ALTER TABLE bot_flows 
ADD INDEX idx_owner (owner_type, owner_id);

-- Comentário: user_id é mantido para compatibilidade retroativa
-- Em queries futuras, usar owner_type + owner_id ao invés de user_id

-- Adicionar coluna para identificar se atendente pode gerenciar fluxos
ALTER TABLE supervisor_users
ADD COLUMN can_manage_flows TINYINT(1) DEFAULT 0 AFTER instance_config_allowed;

-- Comentário: can_manage_flows = 1 permite que atendente crie/edite seus próprios fluxos
