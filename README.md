# 🚀 Deploy no Easypanel - WATS

Esta pasta contém todos os arquivos necessários para fazer deploy do WATS no Easypanel.

## 📁 Conteúdo

### Arquivos de Configuração
- `Dockerfile` - Imagem Docker da aplicação
- `docker-compose.yml` - Para testes locais
- `.dockerignore` - Arquivos ignorados no build
- `.env.easypanel.example` - Template de variáveis de ambiente
- `health.php` - Endpoint de health check

### Scripts Auxiliares
- `generate-keys.sh` - Gera chaves de segurança (Linux/Mac)
- `generate-keys.bat` - Gera chaves de segurança (Windows)

### Documentação
- `GUIA_DEPLOY_EASYPANEL.md` - ⭐ Guia completo passo a passo
- `CHECKLIST_DEPLOY_EASYPANEL.md` - ⭐ Checklist de deploy
- `ANALISE_CONTAINERIZACAO_EASYPANEL.md` - Análise técnica
- `RESUMO_ARQUIVOS_DOCKER.md` - Resumo dos arquivos

## 🎯 Quick Start

### 1. Teste Local
```bash
cd easypanel-deploy
cp .env.easypanel.example ../.env
docker-compose up -d
```

### 2. Deploy no Easypanel
Siga o guia completo: `GUIA_DEPLOY_EASYPANEL.md`

## 📚 Ordem de Leitura

1. **RESUMO_ARQUIVOS_DOCKER.md** - Entenda o que cada arquivo faz
2. **ANALISE_CONTAINERIZACAO_EASYPANEL.md** - Entenda a arquitetura
3. **GUIA_DEPLOY_EASYPANEL.md** - Siga o passo a passo
4. **CHECKLIST_DEPLOY_EASYPANEL.md** - Use como checklist

## 🆘 Suporte

**Email:** suporte@macip.com.br  
**Docs Easypanel:** https://easypanel.io/docs

---

✅ Todos os arquivos necessários estão nesta pasta!
