#!/bin/bash

# ============================================
# Gerador de Chaves de Segurança - WATS
# Para Linux/Mac
# ============================================

echo "🔐 Gerando chaves de segurança para WATS..."
echo ""

# APP_KEY
echo "APP_KEY:"
echo "base64:$(openssl rand -base64 32)"
echo ""

# ENCRYPTION_KEY
echo "ENCRYPTION_KEY:"
echo "base64:$(openssl rand -base64 32)"
echo ""

# WEBHOOK_SECRET
echo "WEBHOOK_SECRET:"
openssl rand -hex 32
echo ""

# MySQL Password
echo "MySQL Password (DB_PASS):"
openssl rand -base64 24
echo ""

# MySQL Root Password
echo "MySQL Root Password:"
openssl rand -base64 24
echo ""

echo "✅ Chaves geradas com sucesso!"
echo ""
echo "⚠️  IMPORTANTE:"
echo "1. Copie estas chaves para o arquivo .env ou Environment Variables no Easypanel"
echo "2. NUNCA commite estas chaves no Git"
echo "3. Guarde em local seguro (1Password, LastPass, etc)"
echo ""
