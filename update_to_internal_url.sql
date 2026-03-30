-- ============================================
-- ATUALIZAR URL DA EVOLUTION API PARA INTERNA
-- ============================================
-- Execute este SQL no banco de dados do WATS
-- Isso vai fazer o sistema usar comunicação interna (super rápido!)

-- 1. Verificar configuração atual
SELECT 
    id,
    name,
    evolution_api_url,
    evolution_instance,
    whatsapp_provider
FROM users 
WHERE id = 1;

-- 2. Atualizar para URL interna
-- IMPORTANTE: O nome do serviço é 'evolution-api' (porta 8080)
UPDATE users 
SET evolution_api_url = 'http://evolution-api:8080' 
WHERE id = 1;

-- 3. Verificar se foi atualizado
SELECT 
    id,
    name,
    evolution_api_url,
    evolution_instance,
    whatsapp_provider
FROM users 
WHERE id = 1;

-- 4. (OPCIONAL) Atualizar para todos os usuários
-- Descomente a linha abaixo se quiser atualizar todos os usuários
-- UPDATE users SET evolution_api_url = 'http://evolution-api:8080' WHERE evolution_api_url IS NOT NULL;

-- ============================================
-- RESULTADO ESPERADO:
-- ============================================
-- evolution_api_url deve estar: http://evolution-api:8080
-- 
-- ANTES: https://evolution.macip.com.br (externa - lenta)
-- DEPOIS: http://evolution-api:8080 (interna - super rápida)
-- ============================================
