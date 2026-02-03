# 🚀 Guia de Deploy no Easypanel

**Projeto:** WATS - Sistema Multi-Canal  
**Data:** 02/02/2026  
**Arquitetura:** Aplicação PHP + MySQL separado

---

## 📋 Pré-requisitos

- ✅ Conta no Easypanel
- ✅ Servidor VPS conectado ao Easypanel
- ✅ Repositório Git do projeto
- ✅ Backup SQL do banco de dados

---

## 🏗️ Arquitetura no Easypanel

```
┌─────────────────────────────────────────┐
│         EASYPANEL PROJECT               │
├─────────────────────────────────────────┤
│                                         │
│  ┌──────────────┐    ┌──────────────┐  │
│  │   WATS APP   │───▶│    MySQL     │  │
│  │  (Dockerfile)│    │   (Service)  │  │
│  │              │    │              │  │
│  │ - PHP 8.2    │    │ - Port 3306  │  │
│  │ - Apache     │    │ - Volume     │  │
│  │ - Node.js    │    │   persistente│  │
│  │ - Cron       │    │              │  │
│  └──────────────┘    └──────────────┘  │
│         │                               │
│         ▼                               │
│  ┌──────────────┐                      │
│  │   Volumes    │                      │
│  │ - /uploads   │                      │
│  │ - /logs      │                      │
│  └──────────────┘                      │
└─────────────────────────────────────────┘
```

---

## 📝 Passo a Passo

### FASE 1: Preparar Repositório Git

#### 1.1 Verificar arquivos criados
```bash
# Arquivos necessários já criados:
✅ Dockerfile
✅ docker-compose.yml (apenas para testes locais)
✅ .dockerignore
✅ .env.easypanel.example
✅ health.php
```

#### 1.2 Commitar arquivos Docker
```bash
git add Dockerfile .dockerignore health.php .env.easypanel.example
git commit -m "feat: adicionar suporte Docker para Easypanel"
git push origin main
```

---

### FASE 2: Criar Projeto no Easypanel

#### 2.1 Acessar Easypanel
1. Acesse seu painel: `https://seu-servidor.com:3000`
2. Faça login

#### 2.2 Criar novo projeto
1. Clique em **"New Project"**
2. Nome: `wats`
3. Clique em **"Create"**

---

### FASE 3: Criar Serviço MySQL

#### 3.1 Adicionar MySQL
1. Dentro do projeto `wats`, clique em **"Add Service"**
2. Escolha **"MySQL"** (template oficial)
3. Configurações:
   ```
   Service Name: wats-mysql
   MySQL Root Password: [gere senha forte]
   MySQL Database: watsdb
   MySQL User: wats_user
   MySQL Password: [gere senha forte]
   ```
4. **Volumes:**
   - Volume Path: `/var/lib/mysql`
   - Mount Path: `/var/lib/mysql`
   - ✅ Persistent: Yes

5. Clique em **"Create"**

#### 3.2 Aguardar MySQL iniciar
- Status deve ficar **"Running"** (verde)
- Pode levar 1-2 minutos

#### 3.3 Importar backup SQL

**Opção A: Via phpMyAdmin (mais fácil)**
1. No serviço MySQL, clique em **"Add Domain"**
2. Configure subdomínio: `mysql.seu-dominio.com`
3. Acesse phpMyAdmin
4. Faça upload do arquivo `backup.sql`

**Opção B: Via terminal**
```bash
# Copiar backup para o container
docker cp backup.sql wats-mysql:/tmp/backup.sql

# Importar
docker exec -i wats-mysql mysql -u wats_user -p watsdb < /tmp/backup.sql
```

**Opção C: Via Easypanel Terminal**
1. No serviço MySQL, clique em **"Terminal"**
2. Execute:
```bash
mysql -u wats_user -p watsdb
# Cole o conteúdo do SQL ou use source /path/to/backup.sql
```

---

### FASE 4: Criar Serviço da Aplicação

#### 4.1 Adicionar App Service
1. No projeto `wats`, clique em **"Add Service"**
2. Escolha **"App"** (para código customizado)
3. Configurações básicas:
   ```
   Service Name: wats-app
   ```

#### 4.2 Configurar Source (Git)
1. Aba **"Source"**
2. **Repository:** `https://github.com/seu-usuario/wats.git`
3. **Branch:** `main`
4. **Auto Deploy:** ✅ Enabled (deploy automático em push)

#### 4.3 Configurar Build
1. Aba **"Build"**
2. **Builder:** `Dockerfile`
3. **Dockerfile Path:** `Dockerfile` (padrão)
4. **Build Context:** `.` (raiz do projeto)

#### 4.4 Configurar Environment Variables
1. Aba **"Environment"**
2. Adicionar variáveis (copie de `.env.easypanel.example`):

```env
# Banco de Dados
DB_HOST=wats-mysql
DB_NAME=watsdb
DB_USER=wats_user
DB_PASS=[senha que você criou no MySQL]
DB_CHARSET=utf8mb4

# Evolution API
EVOLUTION_API_URL=https://evolution.macip.com.br
EVOLUTION_API_KEY=h3V49T8vMi7TKRPePCYs7szpqwtXQwew
EVOLUTION_INSTANCE=macip_instance

# Meta API
META_API_VERSION=v19.0
META_GRAPH_API_URL=https://graph.facebook.com
META_WEBHOOK_VERIFY_TOKEN=wats_meta_webhook_secure_token_2026

# Aplicação
APP_NAME=MAC-IP TECNOLOGIA
APP_ENV=production
APP_DEBUG=false
APP_URL=https://wats.seu-dominio.com

# Segurança (GERE NOVAS CHAVES!)
APP_KEY=base64:[gere com: openssl rand -base64 32]
ENCRYPTION_KEY=base64:[gere com: openssl rand -base64 32]
SESSION_LIFETIME=480

# Webhook
WEBHOOK_SECRET=[gere com: openssl rand -hex 32]
WEBHOOK_RATE_LIMIT=100
WEBHOOK_RATE_WINDOW=60

# Logs
LOG_CHANNEL=daily
LOG_LEVEL=error

# Google AI
GOOGLE_AI_API_KEY=AIzaSyDNOcXvO-4E8vfmntfTEiPE7siWKUDUkKo
GOOGLE_AI_MODEL=gemini-2.5-flash
```

#### 4.5 Configurar Volumes (IMPORTANTE!)
1. Aba **"Mounts"**
2. Adicionar volumes:

**Volume 1: Uploads**
```
Volume Name: wats-uploads
Mount Path: /var/www/html/uploads
```

**Volume 2: Logs**
```
Volume Name: wats-logs
Mount Path: /var/www/html/logs
```

**Volume 3: Storage**
```
Volume Name: wats-storage
Mount Path: /var/www/html/storage
```

#### 4.6 Configurar Domínio
1. Aba **"Domains"**
2. Clique em **"Add Domain"**
3. Digite: `wats.seu-dominio.com`
4. ✅ Enable HTTPS (Let's Encrypt automático)

#### 4.7 Configurar Health Check
1. Aba **"Advanced"**
2. **Health Check Path:** `/health.php`
3. **Health Check Interval:** `30s`
4. **Health Check Timeout:** `10s`
5. **Health Check Retries:** `3`

#### 4.8 Deploy!
1. Clique em **"Deploy"**
2. Aguarde o build (5-10 minutos na primeira vez)
3. Acompanhe logs em **"Logs"**

---

### FASE 5: Verificação Pós-Deploy

#### 5.1 Verificar Health Check
```bash
curl https://wats.seu-dominio.com/health.php
```

Resposta esperada:
```json
{
  "status": "ok",
  "timestamp": 1738454400,
  "checks": {
    "database": {
      "status": "connected",
      "host": "wats-mysql",
      "name": "watsdb"
    },
    "uploads": {
      "status": "writable"
    },
    "logs": {
      "status": "writable"
    },
    "php_extensions": {
      "status": "ok"
    }
  }
}
```

#### 5.2 Verificar Cron Jobs
1. No serviço `wats-app`, clique em **"Terminal"**
2. Execute:
```bash
# Ver cron jobs configurados
crontab -l

# Ver logs de cron
tail -f /var/www/html/logs/cron_teams.log
```

#### 5.3 Verificar WebSocket
```bash
# Ver logs do WebSocket
tail -f /var/www/html/logs/websocket.log
```

#### 5.4 Testar Login
1. Acesse: `https://wats.seu-dominio.com`
2. Faça login com suas credenciais
3. Verifique se dashboard carrega

---

## 🔧 Comandos Úteis

### Acessar Terminal do Container
```bash
# Via Easypanel: clique em "Terminal" no serviço

# Ou via SSH no servidor:
docker exec -it wats-app bash
```

### Ver Logs em Tempo Real
```bash
# Logs do Apache
tail -f /var/log/apache2/error.log

# Logs do PHP
tail -f /var/log/php_errors.log

# Logs de cron
tail -f /var/www/html/logs/cron_*.log

# Logs do WebSocket
tail -f /var/www/html/logs/websocket.log
```

### Reiniciar Serviços
```bash
# Reiniciar Apache
supervisorctl restart apache2

# Reiniciar Cron
supervisorctl restart cron

# Reiniciar WebSocket
supervisorctl restart websocket

# Reiniciar tudo
supervisorctl restart all
```

### Executar Cron Manualmente
```bash
cd /var/www/html
php cron/sync_teams_messages.php
php cron/fetch_emails.php
```

---

## 🐛 Troubleshooting

### Problema: Build falha

**Solução:**
1. Verifique logs de build no Easypanel
2. Verifique se `Dockerfile` está correto
3. Verifique se `.dockerignore` não está bloqueando arquivos necessários

### Problema: Não conecta no MySQL

**Solução:**
1. Verifique se `DB_HOST=wats-mysql` (nome do serviço)
2. Verifique credenciais em Environment Variables
3. Teste conexão:
```bash
docker exec -it wats-app bash
mysql -h wats-mysql -u wats_user -p watsdb
```

### Problema: Uploads não funcionam

**Solução:**
1. Verifique se volume está montado:
```bash
df -h | grep uploads
```
2. Verifique permissões:
```bash
ls -la /var/www/html/uploads
chown -R www-data:www-data /var/www/html/uploads
```

### Problema: Cron jobs não rodam

**Solução:**
1. Verificar se cron está rodando:
```bash
supervisorctl status cron
```
2. Ver logs:
```bash
tail -f /var/www/html/logs/cron_supervisor.log
```
3. Testar manualmente:
```bash
php /var/www/html/cron/sync_teams_messages.php
```

### Problema: WebSocket não conecta

**Solução:**
1. Verificar se está rodando:
```bash
supervisorctl status websocket
```
2. Ver logs:
```bash
tail -f /var/www/html/logs/websocket.log
```
3. Reiniciar:
```bash
supervisorctl restart websocket
```

---

## 🔄 Atualizações e Rollback

### Deploy de Atualização
```bash
# 1. Fazer alterações no código
# 2. Commitar e push
git add .
git commit -m "feat: nova funcionalidade"
git push origin main

# 3. Easypanel faz deploy automático (se Auto Deploy ativado)
# Ou clique em "Deploy" manualmente no painel
```

### Rollback para Versão Anterior
1. No Easypanel, vá em **"Deployments"**
2. Veja histórico de deploys
3. Clique em **"Rollback"** na versão desejada

---

## 📊 Monitoramento

### Métricas no Easypanel
- CPU Usage
- Memory Usage
- Network I/O
- Disk Usage

### Logs Centralizados
- Application Logs: `/var/www/html/logs/`
- Apache Logs: `/var/log/apache2/`
- PHP Errors: `/var/log/php_errors.log`
- Cron Logs: `/var/www/html/logs/cron_*.log`

### Alertas (Configurar)
1. Aba **"Monitoring"** no serviço
2. Configurar alertas para:
   - CPU > 80%
   - Memory > 90%
   - Disk > 85%
   - Health check failed

---

## 🔐 Segurança

### Checklist de Segurança
- [ ] Senhas fortes para MySQL
- [ ] Chaves APP_KEY e ENCRYPTION_KEY únicas
- [ ] HTTPS habilitado (Let's Encrypt)
- [ ] Firewall configurado no VPS
- [ ] Backups automáticos configurados
- [ ] Logs de acesso monitorados
- [ ] Variáveis sensíveis em Environment (não no código)

### Backup Automático
1. Configurar backup do volume MySQL
2. Configurar backup dos volumes de uploads
3. Agendar backups diários

---

## 📞 Suporte

**Documentação Easypanel:** https://easypanel.io/docs  
**Suporte WATS:** suporte@macip.com.br

---

✅ **Deploy concluído com sucesso!**
