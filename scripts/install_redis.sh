#!/bin/bash
# Script de Instalação Redis - WATS
# MACIP Tecnologia LTDA

echo "=========================================="
echo "INSTALAÇÃO REDIS - WATS"
echo "=========================================="
echo ""

# Cores
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Verificar se está rodando como root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}❌ Execute como root: sudo bash scripts/install_redis.sh${NC}"
    exit 1
fi

echo -e "${YELLOW}1. Verificando extensão PHP Redis...${NC}"

# Verificar versão do PHP
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
echo "   PHP Version: $PHP_VERSION"

# Verificar se extensão Redis já está instalada
if php -m | grep -q "redis"; then
    echo -e "   ${GREEN}✅ Extensão Redis já instalada${NC}"
else
    echo -e "   ${YELLOW}⚠️  Instalando extensão Redis...${NC}"
    
    # Instalar via PECL
    pecl install redis
    
    # Habilitar extensão
    echo "extension=redis.so" > /etc/php/$PHP_VERSION/mods-available/redis.ini
    phpenmod redis
    
    # Reiniciar PHP-FPM
    systemctl restart php$PHP_VERSION-fpm
    
    echo -e "   ${GREEN}✅ Extensão Redis instalada${NC}"
fi

echo ""
echo -e "${YELLOW}2. Verificando conexão com Redis...${NC}"

# Testar conexão
REDIS_HOST="163.176.167.219"
REDIS_PORT="6379"
REDIS_PASS="nh3V49T8vMi7TKRPePCYs7"

if redis-cli -h $REDIS_HOST -p $REDIS_PORT -a $REDIS_PASS ping > /dev/null 2>&1; then
    echo -e "   ${GREEN}✅ Redis conectado com sucesso${NC}"
else
    echo -e "   ${RED}❌ Não foi possível conectar ao Redis${NC}"
    echo "   Verifique se o Redis está rodando no Easypanel"
    exit 1
fi

echo ""
echo -e "${YELLOW}3. Criando diretório de cache...${NC}"

# Criar diretório storage/cache
mkdir -p storage/cache
chmod 755 storage/cache
chown www-data:www-data storage/cache

echo -e "   ${GREEN}✅ Diretório criado${NC}"

echo ""
echo -e "${YELLOW}4. Executando testes...${NC}"

# Executar script de teste
php tests/test_redis.php

echo ""
echo "=========================================="
echo -e "${GREEN}✅ INSTALAÇÃO CONCLUÍDA${NC}"
echo "=========================================="
echo ""
echo "Próximos passos:"
echo "1. Acesse: http://seu-dominio/admin_redis_dashboard.php"
echo "2. Verifique as estatísticas do Redis"
echo "3. Monitore a performance"
echo ""
