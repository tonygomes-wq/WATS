-- =====================================================
-- FIX: Adicionar coluna zapi_client_token na tabela users
-- Problema: Z-API requer header Client-Token nas requisições
-- Erro: HTTP 400 "your client-token is not configured"
-- =====================================================

SET @dbname = DATABASE();
SET @tablename = 'users';
SET @columnname = 'zapi_client_token';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  'ALTER TABLE users ADD COLUMN zapi_client_token VARCHAR(255) DEFAULT NULL AFTER zapi_token'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar
SELECT COLUMN_NAME, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME LIKE 'zapi%';

SELECT '✅ Coluna zapi_client_token adicionada!' AS resultado;
