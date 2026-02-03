# 📦 Análise de Containerização - Easypanel

**Data:** 02/02/2026  
**Projeto:** WATS - Sistema Multi-Canal (WhatsApp, Teams, Email)  
**Objetivo:** Avaliar viabilidade de deploy no Easypanel

---

## 🎯 Resumo Executivo

**✅ VIÁVEL COM RESSALVAS**

O projeto pode ser containerizado e deployado no Easypanel, mas requer adaptações significativas devido à sua arquitetura atual baseada em cPanel/shared hosting.

**Complexidade:** 🟡 Média-Alta  
**Esforço estimado:** 3-5 dias  
**Recomendação:** Criar Dockerfile customizado

---

## 📊 Análise do Projeto Atual

### Stack Tecnológico

```yaml
Backend:
  - PHP 7.4+ (sem framework)
  - Arquitetura procedural com includes
  - PDO para MySQL
  - Composer (mínimo, apenas PHPUnit)

Frontend:
  - HTML/CSS/JavaScript vanilla
  - jQuery
  - Bootstrap

Database:
  - MySQL/MariaDB
  - Conexão remota (162.241.3.9)

Integrações:
  - Evolution API (WhatsApp)
  - Microsoft Teams Graph API
  - Meta API (WhatsApp Business)
  - Email (SMTP)

Background Jobs:
  - 13 cron jobs PHP
  - 1 Node.js WebSocket client

Storage:
  - Uploads locais (/uploads)
  - Profile pictures
  - Teams media
  - Backups
```

### Estrutura de Arquivos

```
wats/
├── api/              # Endpoints REST
├── assets/           # CSS, JS, imagens
├── config/           # Configurações
├── includes/         # Classes e funções
├── cron/             # Jobs agendados (13 arquivos)
├── uploads/          # Arquivos de usuários
├── vendor/           # Dependências Composer
├── node_modules/     # Dependências Node.js
├── .env              # Variáveis de ambiente
└── *.php             # Páginas principais
```

---

## 🔍 Builders Disponíveis no Easypanel

### 1. **Nixpacks** ⭐ (Recomendado pela Railway)
- ✅ Suporta PHP
- ✅ Detecção automática
- ❌ Pode não detectar arquitetura complexa
- ❌ Não gerencia cron jobs automaticamente

### 2. **Heroku Buildpacks**
- ✅ Suporta PHP
- ✅ Buildpack oficial PHP
- ❌ Configuração manual necessária
- ❌ Cron jobs requerem worker separado

### 3. **Paketo Buildpacks**
- ✅ Suporta PHP
- ✅ Cloud Native
- ❌ Mais complexo de configurar

### 4. **Dockerfile** ⭐⭐ (RECOMENDADO)
- ✅ Controle total
- ✅ Suporta multi-stage builds
- ✅ Pode incluir cron jobs
- ✅ Pode incluir Node.js + PHP
- ✅ Configuração de volumes

---

## 🚧 Desafios Identificados

### 1. **Cron Jobs** 🔴 CRÍTICO
```
13 cron jobs PHP precisam rodar:
- sync_teams_messages.php (a cada 5 min)
- fetch_emails.php
- backup_database.php
- cleanup_old_data.php
- process_scheduled_dispatches.php
- calculate_analytics.php
- etc.
```

**Soluções:**
- Usar `cron` dentro do container
- Ou migrar para workers separados no Easypanel
- Ou usar serviços externos (Cron-job.org, EasyCron)

### 2. **Uploads e Storage** 🟡 IMPORTANTE
```
/uploads/
├── user_1/
│   ├── teams_media/
│   └── profile_pictures/
├── user_2/
└── ...
```

**Soluções:**
- Usar volumes persistentes do Docker
- Ou migrar para S3/CloudFlare R2
- Ou usar NFS compartilhado

### 3. **Banco de Dados Remoto** 🟢 OK
- Já usa conexão remota (162.241.3.9)
- Não precisa de container MySQL
- ✅ Pronto para containerização

### 4. **Node.js + PHP** 🟡 IMPORTANTE
- Projeto usa PHP + Node.js (WebSocket client)
- Dockerfile precisa suportar ambos
- Multi-stage build recomendado

### 5. **Sessões PHP** 🟡 IMPORTANTE
- Atualmente usa sessões em arquivo
- Em containers efêmeros, sessões se perdem
- Precisa migrar para Redis ou banco

---

## 📝 Plano de Containerização

### Opção 1: Dockerfile Customizado (RECOMENDADO)

```dockerfile
# Multi-stage build
FROM php:8.2-apache as base

# Instalar extensões PHP
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Instalar Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
RUN apt-get install -y nodejs

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Instalar cron
RUN apt-get update && apt-get install -y cron

# Copiar código
WORKDIR /var/www/html
COPY . .

# Instalar dependências
RUN composer install --no-dev --optimize-autoloader
RUN npm ci --production

# Configurar Apache
RUN a2enmod rewrite
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Configurar cron
COPY docker/crontab /etc/cron.d/wats-cron
RUN chmod 0644 /etc/cron.d/wats-cron
RUN crontab /etc/cron.d/wats-cron

# Permissões
RUN chown -R www-data:www-data /var/www/html/uploads
RUN chmod -R 755 /var/www/html/uploads

# Script de inicialização
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
```

### Opção 2: Nixpacks (Mais Simples)

Criar `nixpacks.toml`:

```toml
[phases.setup]
nixPkgs = ["php82", "php82Packages.composer", "nodejs_20"]

[phases.install]
cmds = [
  "composer install --no-dev",
  "npm ci --production"
]

[phases.build]
cmds = ["echo 'Build complete'"]

[start]
cmd = "apache2-foreground"
```

---

## 🛠️ Arquivos Necessários

### 1. `Dockerfile` (ver acima)

### 2. `docker/entrypoint.sh`
```bash
#!/bin/bash
set -e

# Iniciar cron
service cron start

# Iniciar Node.js WebSocket (background)
node websocket_client.js &

# Iniciar Apache
apache2-foreground
```

### 3. `docker/crontab`
```cron
*/5 * * * * php /var/www/html/cron/sync_teams_messages.php
*/10 * * * * php /var/www/html/cron/fetch_emails.php
0 2 * * * php /var/www/html/cron/backup_database.php
# ... outros cron jobs
```

### 4. `docker/apache.conf`
```apache
<VirtualHost *:80>
    DocumentRoot /var/www/html
    
    <Directory /var/www/html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

### 5. `.dockerignore`
```
.git
.env.local
node_modules
vendor
*.log
.vscode
_debug
_archived
```

---

## 🔧 Configuração no Easypanel

### Passo 1: Criar Projeto
1. Conectar repositório Git
2. Escolher "Dockerfile" como builder
3. Configurar variáveis de ambiente (.env)

### Passo 2: Configurar Volumes
```yaml
volumes:
  - /var/www/html/uploads:/uploads
  - /var/www/html/logs:/logs
```

### Passo 3: Configurar Domínio
- Apontar DNS para Easypanel
- Configurar SSL automático

### Passo 4: Deploy
- Push para Git
- Easypanel faz build automático
- Container inicia com cron + Apache + Node.js

---

## ⚠️ Pontos de Atenção

### 1. **Sessões PHP**
```php
// Migrar de arquivo para Redis
ini_set('session.save_handler', 'redis');
ini_set('session.save_path', 'tcp://redis:6379');
```

### 2. **Uploads**
- Usar volume persistente
- Ou migrar para S3/R2
- Backup regular dos uploads

### 3. **Logs**
- Redirecionar para stdout/stderr
- Ou usar volume para logs
- Integrar com sistema de logs do Easypanel

### 4. **Health Checks**
```php
// Criar /health.php
<?php
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'timestamp' => time(),
    'database' => $pdo ? 'connected' : 'disconnected'
]);
```

### 5. **Escalabilidade**
- Atualmente não suporta múltiplas instâncias
- Sessões em arquivo impedem escala horizontal
- Uploads locais impedem escala horizontal
- **Solução:** Redis + S3 para escalar

---

## 💰 Custos Estimados

### Easypanel (VPS)
- **Básico:** $5-10/mês (1 vCPU, 1GB RAM)
- **Recomendado:** $20-40/mês (2 vCPU, 4GB RAM)
- **Produção:** $40-80/mês (4 vCPU, 8GB RAM)

### Serviços Adicionais
- **Redis:** $5-10/mês (Upstash/Redis Cloud)
- **S3/R2:** $5-15/mês (storage + bandwidth)
- **Backup:** $5-10/mês

**Total estimado:** $35-115/mês

---

## 📋 Checklist de Migração

### Preparação
- [ ] Criar Dockerfile
- [ ] Criar docker-compose.yml (para testes locais)
- [ ] Criar entrypoint.sh
- [ ] Configurar crontab
- [ ] Criar .dockerignore
- [ ] Documentar variáveis de ambiente

### Adaptações no Código
- [ ] Migrar sessões para Redis
- [ ] Configurar uploads para S3 (opcional)
- [ ] Adicionar health check endpoint
- [ ] Configurar logs para stdout
- [ ] Testar conexão com banco remoto
- [ ] Validar cron jobs no container

### Testes
- [ ] Build local com Docker
- [ ] Testar todos os endpoints
- [ ] Validar cron jobs
- [ ] Testar uploads
- [ ] Testar integração Teams
- [ ] Testar integração WhatsApp
- [ ] Load testing básico

### Deploy
- [ ] Configurar Easypanel
- [ ] Configurar volumes
- [ ] Configurar variáveis de ambiente
- [ ] Fazer primeiro deploy
- [ ] Configurar domínio e SSL
- [ ] Monitorar logs
- [ ] Backup inicial

---

## 🎓 Recomendações Finais

### Curto Prazo (Deploy Imediato)
1. ✅ Criar Dockerfile básico
2. ✅ Manter uploads locais com volume
3. ✅ Manter sessões em arquivo (volume)
4. ✅ Deploy no Easypanel
5. ✅ Monitorar e ajustar

### Médio Prazo (1-2 meses)
1. 🔄 Migrar sessões para Redis
2. 🔄 Migrar uploads para S3/R2
3. 🔄 Implementar CI/CD
4. 🔄 Adicionar monitoring (Sentry, etc)
5. 🔄 Otimizar imagem Docker

### Longo Prazo (3-6 meses)
1. 🚀 Refatorar para framework (Laravel/Symfony)
2. 🚀 Separar API do frontend
3. 🚀 Implementar queue system (Redis Queue)
4. 🚀 Escala horizontal
5. 🚀 Kubernetes (se necessário)

---

## 📚 Recursos Úteis

- [Easypanel Docs - Builders](https://easypanel.io/docs/builders)
- [Easypanel Docs - Laravel](https://easypanel.io/docs/quickstarts/laravel) (similar)
- [Docker PHP Best Practices](https://github.com/docker-library/docs/tree/master/php)
- [PHP-FPM + Nginx vs Apache](https://www.cloudways.com/blog/php-fpm-on-cloud/)

---

## ✅ Conclusão

**O projeto PODE ser containerizado no Easypanel**, mas requer:

1. **Dockerfile customizado** (não confiar em auto-detecção)
2. **Adaptações para cron jobs** (incluir no container)
3. **Gestão de volumes** (uploads e logs)
4. **Migração gradual** (sessões → Redis, uploads → S3)

**Benefícios:**
- ✅ Deploy automatizado
- ✅ Rollback fácil
- ✅ Ambiente reproduzível
- ✅ Escalabilidade futura
- ✅ Melhor DevOps

**Desafios:**
- ⚠️ Esforço inicial de setup
- ⚠️ Aprendizado de Docker
- ⚠️ Gestão de volumes
- ⚠️ Cron jobs no container

**Veredicto:** Vale a pena para profissionalizar o deploy e facilitar manutenção futura.

---

**Próximo passo:** Quer que eu crie os arquivos Docker necessários para começar?
