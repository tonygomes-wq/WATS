# ⚡ Quick Deploy Checklist - WATS no Easypanel

## ✅ Pré-requisitos (JÁ CONFIRMADOS)

- ✅ MySQL instalado no Easypanel
- ✅ Banco de dados importado (Hostgator → Easypanel)
- ✅ Arquivos Docker criados

---

## 🚀 Deploy em 5 Passos

### 1️⃣ Git Push (2 min)
```bash
git add Dockerfile docker/ .dockerignore
git commit -m "feat: Docker config for Easypanel"
git push origin main
```

### 2️⃣ Criar Serviço (2 min)
- Easypanel → **"+ New Service"** → **"App"** → **"GitHub"**
- Repository: `seu-repo/wats.git`
- Branch: `main`
- Dockerfile Path: `./Dockerfile`

### 3️⃣ Variáveis de Ambiente (3 min)
```env
DB_HOST=n8n_mysql
DB_NAME=faceso56_watsdb
DB_USER=faceso56_watsdb
DB_PASS=V%(zAeG87;OTvv7^
APP_URL=https://wats.macip.com.br
# ... (copiar todas do DEPLOY_EASYPANEL_SIMPLIFICADO.md)
```

### 4️⃣ Volumes (3 min)
- `/var/www/html/uploads` → `wats-uploads`
- `/var/www/html/backups` → `wats-backups`
- `/var/www/html/storage` → `wats-storage`

### 5️⃣ Domínio e Deploy (5 min)
- Porta: `80`
- Domínio: `wats.macip.com.br`
- SSL: ✅ Habilitado
- **Deploy!**

---

## ✅ Verificação (5 min)

```bash
# 1. Status
Status: Running ✅

# 2. Logs
[supervisor] apache2 started ✅
[supervisor] cron-teams-sync started ✅

# 3. Web
https://wats.macip.com.br/ ✅

# 4. MySQL
php -r "new PDO('mysql:host=n8n_mysql;dbname=faceso56_watsdb', 'faceso56_watsdb', 'V%(zAeG87;OTvv7^');" ✅

# 5. Cron Jobs
supervisorctl status ✅
```

---

## 🎯 Pós-Deploy

- [ ] Configurar webhooks (Evolution + Meta)
- [ ] Habilitar Auto-Deploy
- [ ] Testar funcionalidades principais

---

**Tempo total:** 15-20 minutos  
**Documentação completa:** `DEPLOY_EASYPANEL_SIMPLIFICADO.md`
