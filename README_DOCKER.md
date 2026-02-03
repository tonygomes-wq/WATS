# 🐳 WATS - Docker & Easypanel

Sistema Multi-Canal (WhatsApp, Teams, Email) containerizado para deploy no Easypanel.

## 🚀 Quick Start

### Testar Localmente

```bash
# 1. Copiar variáveis de ambiente
cp .env.easypanel.example .env

# 2. Editar .env com suas credenciais
nano .env

# 3. Iniciar com Docker Compose
docker-compose up -d

# 4. Acessar aplicação
http://localhost:8080

# 5. Acessar phpMyAdmin
http://localhost:8081
```

### Deploy no Easypanel

Siga o guia completo: **[docs/GUIA_DEPLOY_EASYPANEL.md](docs/GUIA_DEPLOY_EASYPANEL.md)**

**Resumo:**
1. Criar projeto no Easypanel
2. Adicionar serviço MySQL
3. Importar backup SQL
4. Adicionar serviço App (Dockerfile)
5. Configurar variáveis de ambiente
6. Configurar volumes persistentes
7. Deploy!

## 📦 Arquitetura

```
WATS App (Dockerfile)
├── PHP 8.2 + Apache
├── Node.js 20 (WebSocket)
├── Cron Jobs (13 jobs)
└── Supervisor (gerencia processos)

MySQL (Serviço separado)
└── Banco de dados persistente

Volumes
├── /uploads (arquivos de usuários)
├── /logs (logs da aplicação)
└── /storage (cache)
```

## 🔧 Arquivos Importantes

- `Dockerfile` - Imagem Docker da aplicação
- `docker-compose.yml` - Para testes locais
- `.dockerignore` - Arquivos ignorados no build
- `.env.easypanel.example` - Template de variáveis
- `health.php` - Health check endpoint
- `docs/GUIA_DEPLOY_EASYPANEL.md` - Guia completo

## 📝 Comandos Úteis

```bash
# Build local
docker build -t wats:latest .

# Rodar local
docker run -p 8080:80 wats:latest

# Ver logs
docker logs -f wats_app

# Acessar terminal
docker exec -it wats_app bash

# Parar tudo
docker-compose down

# Limpar volumes
docker-compose down -v
```

## 🔍 Health Check

```bash
curl http://localhost:8080/health.php
```

## 📚 Documentação

- [Guia de Deploy Easypanel](docs/GUIA_DEPLOY_EASYPANEL.md)
- [Análise de Containerização](docs/ANALISE_CONTAINERIZACAO_EASYPANEL.md)

## 🆘 Suporte

**Email:** suporte@macip.com.br  
**Docs Easypanel:** https://easypanel.io/docs
