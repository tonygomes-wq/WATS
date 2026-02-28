@echo off
REM Script para configurar Task Scheduler no Windows
REM Executa o cron de emails a cada 5 minutos

set SCRIPT_PATH=%~dp0fetch_emails.php
set PHP_PATH=C:\xampp\php\php.exe

REM Criar tarefa no Task Scheduler
schtasks /create /tn "WATS Email Fetch" /tr "%PHP_PATH% %SCRIPT_PATH%" /sc minute /mo 5 /ru SYSTEM /f

echo Tarefa "WATS Email Fetch" criada com sucesso!
echo A tarefa sera executada a cada 5 minutos.
pause
