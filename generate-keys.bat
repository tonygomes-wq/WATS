@echo off
REM ============================================
REM Gerador de Chaves de Segurança - WATS
REM Para Windows
REM ============================================

echo 🔐 Gerando chaves de segurança para WATS...
echo.

echo APP_KEY:
echo base64:%RANDOM%%RANDOM%%RANDOM%%RANDOM%%RANDOM%%RANDOM%%RANDOM%%RANDOM%
echo.

echo ENCRYPTION_KEY:
echo base64:%RANDOM%%RANDOM%%RANDOM%%RANDOM%%RANDOM%%RANDOM%%RANDOM%%RANDOM%
echo.

echo WEBHOOK_SECRET:
echo %RANDOM%%RANDOM%%RANDOM%%RANDOM%%RANDOM%%RANDOM%%RANDOM%%RANDOM%%RANDOM%%RANDOM%%RANDOM%%RANDOM%%RANDOM%%RANDOM%%RANDOM%%RANDOM%
echo.

echo MySQL Password (DB_PASS):
echo %RANDOM%%RANDOM%%RANDOM%%RANDOM%%RANDOM%%RANDOM%
echo.

echo MySQL Root Password:
echo %RANDOM%%RANDOM%%RANDOM%%RANDOM%%RANDOM%%RANDOM%
echo.

echo ✅ Chaves geradas com sucesso!
echo.
echo ⚠️  IMPORTANTE:
echo 1. Copie estas chaves para o arquivo .env ou Environment Variables no Easypanel
echo 2. NUNCA commite estas chaves no Git
echo 3. Guarde em local seguro (1Password, LastPass, etc)
echo.
echo NOTA: Para chaves mais seguras, use o script Linux (generate-keys.sh) ou
echo       gere manualmente com: openssl rand -base64 32
echo.

pause
