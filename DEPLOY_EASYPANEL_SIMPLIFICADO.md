# 🚀 Deploy WATS no Easypanel - Guia Simplificado

## ✅ Pré-requisitos Confirmados

- ✅ **MySQL já instalado** no Easypanel
- ✅ **Banco de dados já importado** (migrado da Hostgator)
- ✅ **Traefik já configurado** (reverse proxy)

**Credenciais MySQL:**
- Host Interno: `n8n_mysql`
- Porta: `3306`
- Database: `faceso56_watsdb`
- User: `faceso56_watsdb`
- Password: `V%(zAeG87;OTvv7^`

---

## 🎯 Arquivos Prontos para Deploy

Todos os arquivos necessários já foram criados:

- ✅ **`Dockerfile`** - Otimizado para produção
- ✅ **`docker/supervisord.conf`** - Gerenciador de processos
- ✅ **`.dockerignore`** - Otimização de build
- ✅ **`docker-compose.yml`** - Para testes locais (opcional)

---

## 🚀 Deploy em 5 Passos (15 minutos)

### **Passo 1: Commit e Push para GitHub** (2 min)

```bash
# Adicionar arquivos ao Git
git add Dockerfile docker/ .dockerignore

# Commit
git commit -m "feat: configuração Docker para Easypanel"

# Push
git push origin main
```

---

### **Passo 2: Criar Serviço no Easypanel** (2 min)

1. Acesse o Easypanel
2. Clique em **"+ New Service"**
3. Escolha **"App"**
4. Selecione **"GitHub"** como fonte

**Configuração:**
```yaml
Repository: https://github.com/seu-usuario/wats.git
Branch: main
Build Context: /
Dockerfile Path: ./Dockerfile
```

---

### **Passo 3: Configurar Variáveis de Ambiente** (3 min)

Copie e cole todas as variáveis abaixo no Easypanel:

```env
# Banco de Dados (JÁ CONFIGURADO)
DB_HOST=n8n_mysql
DB_PORT=3306
DB_NAME=faceso56_watsdb
DB_USER=faceso56_watsdb
DB_PASS=V%(zAeG87;OTvv7^
DB_CHARSET=utf8mb4

# Aplicação
APP_NAME=MAC-IP TECNOLOGIA
APP_ENV=production
APP_DEBUG=false
APP_URL=https://wats.macip.com.br
APP_KEY=base64:7K9mN2pQ4rT6wY8zB1cD3eF5gH7jL0nP2qR4sT6uV8xZ
ENCRYPTION_KEY=base64:OVes9DvKtR6uLIcfn020HySEdjts4KAFIsg4wcZLecw=
SESSION_LIFETIME=480

# Evolution API
EVOLUTION_API_URL=https://evolution.macip.com.br
EVOLUTION_API_KEY=h3V49T8vMi7TKRPePCYs7szpqwtXQwew
EVOLUTION_INSTANCE=macip_instance

# Meta WhatsApp
META_API_VERSION=v24.0
META_GRAPH_API_URL=https://graph.facebook.com
META_WEBHOOK_VERIFY_TOKEN=wats_meta_webhook_secure_token_2026

# Segurança
WEBHOOK_SECRET=1218e42749aead68707d5d52f3b72a9f966b254a06e6fefe8f7be960816c1fba
WEBHOOK_SIGNATURE_HEADER=X-Webhook-Signature
WEBHOOK_RATE_LIMIT=100
WEBHOOK_RATE_WINDOW=60
ENCRYPT_TOKENS=true

# Limites
MAX_UPLOAD_SIZE=10
REQUEST_TIMEOUT=30
LOG_LEVEL=error
CACHE_DRIVER=file

# Google AI
GOOGLE_AI_API_KEY=AIzaSyDNOcXvO-4E8vfmntfTEiPE7siWKUDUkKo
GOOGLE_AI_MODEL=gemini-2.5-flash
GOOGLE_AI_ENDPOINT=https://generativelanguage.googleapis.com/v1beta/models

# VoIP (se aplicável)
VOIP_ENABLED=true
VOIP_PROVIDER=freeswitch
VOIP_SERVER_HOST=voip.macip.com.br
VOIP_WSS_PORT=8083
VOIP_SIP_DOMAIN=wats.macip.com.br
VOIP_ESL_PORT=8021
VOIP_ESL_PASSWORD=ClueCon
VOIP_STUN_SERVER=stun:stun.l.google.com:19302
```

---

### **Passo 4: Configurar Volumes Persistentes** (3 min)

Adicione 3 volumes do tipo **"Volume"**:

1. **Volume 1:**
   - Mount Path: `/var/www/html/uploads`
   - Volume Name: `wats-uploads`

2. **Volume 2:**
   - Mount Path: `/var/www/html/backups`
   - Volume Name: `wats-backups`

3. **Volume 3:**
   - Mount Path: `/var/www/html/storage`
   - Volume Name: `wats-storage`

---

### **Passo 5: Configurar Domínio e Deploy** (5 min)

**Configuração de Rede:**
```yaml
Porta do Container: 80
Protocolo: HTTP
```

**Domínio:**
```yaml
Domain: wats.macip.com.br
SSL (Let's Encrypt): ✅ Habilitado
```

**Deploy:**
1. Clique em **"Save"**
2. Clique em **"Deploy"**
3. Aguarde o build (5-8 minutos)

---

## ✅ Verificação Pós-Deploy (5 minutos)

### **1. Verificar Status do Container**

No Easypanel:
- Status: **"Running"** ✅
- CPU: < 20%
- Memória: < 512MB

### **2. Verificar Logs**

Na aba **"Logs"** do Easypanel, você deve ver:

```
[supervisor] apache2 started
[supervisor] cron-teams-sync started
[supervisor] cron-fetch-emails started
[supervisor] cron-scheduled-dispatches started
[supervisor] cron-cleanup started
[supervisor] cron-backup started
[supervisor] cron-analytics started
```

### **3. Testar Acesso Web**

Abra no navegador:
```
https://wats.macip.com.br/
```

Deve exibir a landing page do WATS.

### **4. Testar Login**

```
https://wats.macip.com.br/login.php
```

Faça login com suas credenciais.

### **5. Verificar Conexão com MySQL**

No console do Easypanel (aba "Console"):

```bash
# Testar conexão
php -r "new PDO('mysql:host=n8n_mysql;dbname=faceso56_watsdb', 'faceso56_watsdb', 'V%(zAeG87;OTvv7^'); echo 'Conexão OK\n';"
```

Deve retornar: `Conexão OK`

### **6. Verificar Cron Jobs**

```bash
# Ver status de todos os processos
supervisorctl status
```

Todos devem estar **RUNNING**.

---

## 🔧 Troubleshooting Rápido

### **Container não inicia**

```bash
# Ver logs completos
docker logs wats-app

# Verificar se supervisord.conf existe
ls -la /etc/supervisor/conf.d/
```

### **Erro de permissão em uploads**

```bash
# Corrigir permissões
chown -R www-data:www-data /var/www/html
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/uploads
chmod -R 775 /var/www/html/backups
```

### **Cron job não executa**

```bash
# Reiniciar processo específico
supervisorctl restart cron-teams-sync

# Ver logs do processo
tail -f /var/log/supervisor/cron-teams.log
```

---

## 🎯 Próximos Passos

### **1. Configurar Webhooks** (10 min)

**Evolution API:**
```
URL: https://wats.macip.com.br/api/webhooks/evolution_webhook.php
Events: MESSAGES_UPSERT, MESSAGES_UPDATE, CONNECTION_UPDATE
```

**Meta WhatsApp:**
```
URL: https://wats.macip.com.br/api/webhooks/meta_webhook.php
Verify Token: wats_meta_webhook_secure_token_2026
```

### **2. Habilitar Auto-Deploy** (2 min)

No Easypanel, na aba do serviço:
1. Vá em **"Settings"**
2. Habilite **"Auto Deploy"**
3. Agora, a cada push no GitHub, o deploy será automático

### **3. Configurar Monitoramento** (opcional)

- **UptimeRobot**: Monitorar uptime do site
- **Sentry**: Error tracking
- **Google Analytics**: Métricas de uso

---

## 📊 Arquitetura Final

```
┌─────────────────────────────────────────┐
│         Internet (HTTPS)                 │
└──────────────┬──────────────────────────┘
               │
┌──────────────▼──────────────────────────┐
│    Traefik (SSL/TLS automático)         │
└──────────────┬──────────────────────────┘
               │
┌──────────────▼──────────────────────────┐
│    WATS Container (PHP 8.3 + Apache)    │
│  - Apache (porta 80)                    │
│  - Supervisor (7 cron jobs)             │
│  - Volumes: uploads, backups, storage   │
└──────────────┬──────────────────────────┘
               │
┌──────────────▼──────────────────────────┐
│    MySQL (n8n_mysql) ✅ JÁ CONFIGURADO  │
│  - Database: faceso56_watsdb            │
│  - Dados já importados da Hostgator     │
└─────────────────────────────────────────┘
```

---

## ✅ Checklist Final

- [ ] Commit e push para GitHub
- [ ] Serviço criado no Easypanel
- [ ] Variáveis de ambiente configuradas
- [ ] 3 volumes persistentes criados
- [ ] Domínio configurado
- [ ] SSL habilitado
- [ ] Deploy iniciado
- [ ] Build concluído (5-8 min)
- [ ] Container rodando (status: Running)
- [ ] Site acessível via HTTPS
- [ ] Login funcionando
- [ ] Conexão MySQL OK (banco já importado ✅)
- [ ] Cron jobs executando
- [ ] Webhooks configurados
- [ ] Auto-deploy habilitado

---

## 🎉 Vantagens do Deploy no Easypanel

| Aspecto | Hostgator | Easypanel |
|---------|-----------|-----------|
| **Deploy** | Manual via FTP | Automático via Git |
| **Downtime** | ~30 minutos | ~0 segundos |
| **Rollback** | Impossível | 1 clique |
| **SSL** | Manual | Automático |
| **Cron Jobs** | Limitado | Ilimitado (Supervisor) |
| **Logs** | Dispersos | Centralizados |
| **Escalabilidade** | Limitada | Horizontal |
| **Backup** | Manual | Automático |

---

**Tempo total de deploy:** ~15-20 minutos  
**Dificuldade:** ⭐⭐ Fácil  
**Status:** ✅ Pronto para produção

---

**Desenvolvido com ❤️ por MAC-IP TECNOLOGIA LTDA**
