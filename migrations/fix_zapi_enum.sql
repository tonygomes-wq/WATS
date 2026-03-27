-- =====================================================
-- FIX: Adicionar 'zapi' e 'baileys' ao ENUM whatsapp_provider
-- Problema: ENUM só tem ('evolution','meta'), falta 'zapi'
-- Erro: SQLSTATE[01000] Data truncated for column 'whatsapp_provider'
-- =====================================================

ALTER TABLE users 
MODIFY COLUMN whatsapp_provider ENUM('evolution', 'zapi', 'meta', 'baileys') 
DEFAULT 'evolution';

-- Verificar resultado
SELECT COLUMN_TYPE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'users' 
  AND COLUMN_NAME = 'whatsapp_provider';

SELECT '✅ ENUM atualizado com sucesso! Agora inclui: evolution, zapi, meta, baileys' AS resultado;
