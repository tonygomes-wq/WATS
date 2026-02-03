# ✅ Checklist de Deploy - Easypanel

Use este checklist para garantir que todos os passos foram executados corretamente.

---

## 📋 PRÉ-DEPLOY

### Preparação Local
- [ ] Código commitado no Git
- [ ] Arquivo `Dockerfile` presente na raiz
- [ ] Arquivo `.dockerignore` configurado
- [ ] Arquivo `health.php` criado
- [ ] Backup SQL do banco de dados disponível
- [ ] Chaves de segurança geradas (executar `generate-keys.bat`)

### Conta Easypanel
- [ ] Conta criada no Easypanel
- [ ] Servidor VPS conectado
- [ ] Acesso ao painel funcionando

---

## 🗄️ MYSQL (Serviço 1)

### Criar Serviço
- [ ] Projeto criado: `wats`
- [ ] Serviço MySQL adicionado: `wats-mysql`
- [ ] Nome do banco: `watsdb`
- [ ] Usuário criado: `wats_user`
- [ ] Senha forte configurada
- [ ] Volume persistente configurado: `/var/lib/mysql`

### Importar Dados
- [ ] Backup SQL importado com sucesso
- [ ] Tabelas verificadas (via phpMyAdmin ou terminal)
- [ ] Dados de teste verificados
- [ ] Conexão testada

### Verificação
```bash
# Testar conexão
docker exec -it wats-mysql mysql -u wats_user -p watsdb

# Verificar tabelas
SHOW TABLES;

# Verificar usuários
SELECT id, name, email FROM users LIMIT 5;
```

---

## 🚀 APLICAÇÃO (Serviço 2)

### Source (Git)
- [ ] Repositório conectado
- [ ] Branch configurada: `main`
- [ ] Auto Deploy habilitado

### Build
- [ ] Builder selecionado: `Dockerfile`
- [ ] Dockerfile Path: `Dockerfile`
- [ ] Build Context: `.`

### Environment Variables
Copiar de `.env.easypanel.example` e configurar:

#### Banco de Dados
- [ ] `DB_HOST=wats-mysql`
- [ ] `DB_NAME=watsdb`
- [ ] `DB_USER=wats_user`
- [ ] `DB_PASS=[senha do MySQL]`
- [ ] `DB_CHARSET=utf8mb4`

#### Evolution API
- [ ] `EVOLUTION_API_URL`
- [ ] `EVOLUTION_API_KEY`
- [ ] `EVOLUTION_INSTANCE`

#### Meta API
- [ ] `META_API_VERSION=v19.0`
- [ ] `META_GRAPH_API_URL`
- [ ] `META_WEBHOOK_VERIFY_TOKEN`

#### Aplicação
- [ ] `APP_NAME`
- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL=[seu domínio]`

#### Segurança (GERAR NOVAS!)
- [ ] `APP_KEY=base64:[nova chave]`
- [ ] `ENCRYPTION_KEY=base64:[nova chave]`
- [ ] `SESSION_LIFETIME=480`
- [ ] `WEBHOOK_SECRET=[novo secret]`

#### Logs
- [ ] `LOG_CHANNEL=daily`
- [ ] `LOG_LEVEL=error`

#### Google AI
- [ ] `GOOGLE_AI_API_KEY`
- [ ] `GOOGLE_AI_MODEL=gemini-2.5-flash`

### Volumes (CRÍTICO!)
- [ ] Volume 1: `wats-uploads` → `/var/www/html/uploads`
- [ ] Volume 2: `wats-logs` → `/var/www/html/logs`
- [ ] Volume 3: `wats-storage` → `/var/www/html/storage`

### Domínio
- [ ] Domínio adicionado: `wats.seu-dominio.com`
- [ ] HTTPS habilitado (Let's Encrypt)
- [ ] DNS apontado para o servidor

### Health Check
- [ ] Path: `/health.php`
- [ ] Interval: `30s`
- [ ] Timeout: `10s`
- [ ] Retries: `3`

### Deploy
- [ ] Botão "Deploy" clicado
- [ ] Build iniciado
- [ ] Logs acompanhados
- [ ] Build concluído com sucesso
- [ ] Container iniciado (status verde)

---

## 🧪 TESTES PÓS-DEPLOY

### Health Check
```bash
curl https://wats.seu-dominio.com/health.php
```

Verificar resposta:
- [ ] `"status": "ok"`
- [ ] `"database": "connected"`
- [ ] `"uploads": "writable"`
- [ ] `"logs": "writable"`
- [ ] `"php_extensions": "ok"`

### Aplicação Web
- [ ] Página inicial carrega
- [ ] Login funciona
- [ ] Dashboard carrega
- [ ] CSS/JS carregam corretamente
- [ ] Imagens aparecem

### Funcionalidades
- [ ] Chat carrega conversas
- [ ] Envio de mensagem funciona
- [ ] Recebimento de mensagem funciona
- [ ] Upload de arquivo funciona
- [ ] Teams integração funciona
- [ ] WhatsApp integração funciona

### Cron Jobs
Acessar terminal do container:
```bash
# Ver cron jobs configurados
crontab -l

# Ver logs de cron
tail -f /var/www/html/logs/cron_teams.log
tail -f /var/www/html/logs/cron_emails.log

# Executar manualmente
php /var/www/html/cron/sync_teams_messages.php
```

Verificar:
- [ ] Cron jobs listados
- [ ] Logs sendo gerados
- [ ] Execução manual funciona
- [ ] Mensagens sendo sincronizadas

### WebSocket
```bash
# Ver logs
tail -f /var/www/html/logs/websocket.log

# Ver status
supervisorctl status websocket
```

Verificar:
- [ ] WebSocket rodando
- [ ] Logs sem erros
- [ ] Conexão com Evolution API ok

### Volumes
```bash
# Verificar uploads
ls -la /var/www/html/uploads/

# Verificar logs
ls -la /var/www/html/logs/

# Verificar permissões
stat /var/www/html/uploads/
```

Verificar:
- [ ] Diretórios existem
- [ ] Permissões corretas (775)
- [ ] Owner: www-data
- [ ] Arquivos podem ser criados

---

## 🔐 SEGURANÇA

### Checklist de Segurança
- [ ] Senhas fortes para MySQL (20+ caracteres)
- [ ] APP_KEY única (não usar exemplo)
- [ ] ENCRYPTION_KEY única (não usar exemplo)
- [ ] WEBHOOK_SECRET único (não usar exemplo)
- [ ] HTTPS habilitado e funcionando
- [ ] Certificado SSL válido
- [ ] APP_DEBUG=false em produção
- [ ] Variáveis sensíveis apenas no Easypanel (não no código)
- [ ] .env não commitado no Git
- [ ] Firewall configurado no VPS
- [ ] Portas desnecessárias fechadas

### Backup
- [ ] Backup automático do MySQL configurado
- [ ] Backup dos volumes configurado
- [ ] Frequência: diária
- [ ] Retenção: 7 dias
- [ ] Teste de restore realizado

---

## 📊 MONITORAMENTO

### Configurar Alertas
- [ ] Alerta de CPU > 80%
- [ ] Alerta de Memory > 90%
- [ ] Alerta de Disk > 85%
- [ ] Alerta de Health Check failed
- [ ] Email de notificação configurado

### Logs
- [ ] Logs centralizados acessíveis
- [ ] Rotação de logs configurada
- [ ] Logs de erro monitorados

### Métricas
- [ ] CPU usage normal (< 50%)
- [ ] Memory usage normal (< 70%)
- [ ] Disk usage normal (< 80%)
- [ ] Response time < 500ms

---

## 📝 DOCUMENTAÇÃO

### Documentar
- [ ] Credenciais salvas em local seguro (1Password, etc)
- [ ] Domínio documentado
- [ ] Variáveis de ambiente documentadas
- [ ] Procedimento de backup documentado
- [ ] Procedimento de rollback documentado
- [ ] Contatos de suporte documentados

### Compartilhar
- [ ] Equipe informada sobre novo ambiente
- [ ] Acesso ao Easypanel compartilhado (se necessário)
- [ ] Documentação compartilhada

---

## 🎉 CONCLUSÃO

### Deploy Completo
- [ ] Todos os itens acima verificados
- [ ] Aplicação funcionando 100%
- [ ] Testes realizados com sucesso
- [ ] Monitoramento ativo
- [ ] Backup configurado
- [ ] Documentação completa

### Próximos Passos
- [ ] Migrar DNS de produção (se aplicável)
- [ ] Monitorar por 24h
- [ ] Ajustar recursos se necessário
- [ ] Configurar CI/CD (opcional)
- [ ] Implementar Redis (futuro)
- [ ] Migrar uploads para S3 (futuro)

---

## 🆘 Em Caso de Problemas

### Rollback Rápido
1. No Easypanel, vá em "Deployments"
2. Clique em "Rollback" na versão anterior
3. Aguarde rollback completar

### Suporte
- **Documentação:** `docs/GUIA_DEPLOY_EASYPANEL.md`
- **Troubleshooting:** Seção específica no guia
- **Easypanel Docs:** https://easypanel.io/docs
- **Suporte WATS:** suporte@macip.com.br

---

✅ **Deploy verificado e aprovado!**

**Data:** ___/___/______  
**Responsável:** _________________  
**Assinatura:** _________________
