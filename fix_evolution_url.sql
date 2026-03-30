-- Corrigir URL da Evolution API para URL pública
-- Execute este SQL no banco de dados do WATS

-- 1. Verificar configuração atual
SELECT 
    id,
    whatsapp_provider,
    evolution_instance,
    evolution_api_url,
    evolution_token
FROM users 
WHERE id = 1;

-- 2. Atualizar URL para pública (substitua pela URL correta)
UPDATE users 
SET evolution_api_url = 'https://evolution.macip.com.br' 
WHERE id = 1;

-- 3. Verificar se foi atualizado
SELECT 
    id,
    evolution_api_url,
    evolution_instance
FROM users 
WHERE id = 1;
