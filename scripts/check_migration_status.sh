#!/bin/bash
# ============================================
# Script de Verificação de Status - Migração WATS
# ============================================

# Cores
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}============================================${NC}"
echo -e "${BLUE}  WATS - Status da Migração${NC}"
echo -e "${BLUE}============================================${NC}"
echo ""

# Função para verificar serviço
check_service() {
    if systemctl is-active --quiet $1; then
        echo -e "${GREEN}✓${NC} $2: ${GREEN}Rodando${NC}"
        return 0
    else
        echo -e "${RED}✗${NC} $2: ${RED}Parado${NC}"
        return 1
    fi
}

# Função para verificar porta
check_port() {
    if netstat -tuln | grep -q ":$1 "; then
        echo -e "${GREEN}✓${NC} Porta $1: ${GREEN}Aberta${NC}"
        return 0
    else
        echo -e "${RED}✗${NC} Porta $1: ${RED}Fechada${NC}"
        return 1
    fi
}

# Função para verificar arquivo
check_file() {
    if [ -f "$1" ]; then
        echo -e "${GREEN}✓${NC} $2: ${GREEN}Existe${NC}"
        return 0
    else
        echo -e "${RED}✗${NC} $2: ${RED}Não encontrado${NC}"
        return 1
    fi
}

# Função para verificar diretório
check_dir() {
    if [ -d "$1" ]; then
        echo -e "${GREEN}✓${NC} $2: ${GREEN}Existe${NC}"
        return 0
    else
        echo -e "${RED}✗${NC} $2: ${RED}Não encontrado${NC}"
        return 1
    fi
}

echo -e "${YELLOW}1. Serviços${NC}"
check_service nginx "Nginx"
check_service php8.3-fpm "PHP-FPM"
check_service mysql "MySQL"
echo ""

echo -e "${YELLOW}2. Portas${NC}"
check_port 80
check_port 443
check_port 3306
echo ""

echo -e "${YELLOW}3. PHP${NC}"
if command -v php &> /dev/null; then
    PHP_VERSION=$(php -v | head -n 1)
    echo -e "${GREEN}✓${NC} PHP instalado: ${GREEN}$PHP_VERSION${NC}"
else
    echo -e "${RED}✗${NC} PHP: ${RED}Não instalado${NC}"
fi
echo ""

echo -e "${YELLOW}4. Estrutura de Diretórios${NC}"
check_dir "/var/www/wats" "Diretório da aplicação"
check_dir "/var/www/wats/storage" "Storage"
check_dir "/var/www/wats/uploads" "Uploads"
check_dir "/var/www/wats/backups" "Backups"
check_dir "/var/log/wats" "Logs"
echo ""

echo -e "${YELLOW}5. Arquivos de Configuração${NC}"
check_file "/etc/nginx/sites-enabled/wats.macip.com.br" "Nginx config"
check_file "/var/www/wats/.env" ".env"
check_file "/var/www/wats/config/database.php" "Database config"
echo ""

echo -e "${YELLOW}6. SSL${NC}"
if [ -f "/etc/letsencrypt/live/wats.macip.com.br/fullchain.pem" ]; then
    CERT_EXPIRY=$(openssl x509 -enddate -noout -in /etc/letsencrypt/live/wats.macip.com.br/fullchain.pem | cut -d= -f2)
    echo -e "${GREEN}✓${NC} Certificado SSL: ${GREEN}Instalado${NC}"
    echo -e "  Expira em: $CERT_EXPIRY"
else
    echo -e "${RED}✗${NC} Certificado SSL: ${RED}Não instalado${NC}"
fi
echo ""

echo -e "${YELLOW}7. Banco de Dados${NC}"
if mysql -u faceso56_watsdb -p'V%(zAeG87;OTvv7^' -e "USE faceso56_watsdb; SELECT COUNT(*) FROM users;" &> /dev/null; then
    USER_COUNT=$(mysql -u faceso56_watsdb -p'V%(zAeG87;OTvv7^' -e "USE faceso56_watsdb; SELECT COUNT(*) FROM users;" -s -N)
    echo -e "${GREEN}✓${NC} Conexão MySQL: ${GREEN}OK${NC}"
    echo -e "  Usuários cadastrados: $USER_COUNT"
else
    echo -e "${RED}✗${NC} Conexão MySQL: ${RED}Falhou${NC}"
fi
echo ""

echo -e "${YELLOW}8. Cron Jobs${NC}"
CRON_COUNT=$(crontab -l 2>/dev/null | grep -c "/var/www/wats/cron")
if [ $CRON_COUNT -gt 0 ]; then
    echo -e "${GREEN}✓${NC} Cron jobs: ${GREEN}$CRON_COUNT configurados${NC}"
else
    echo -e "${RED}✗${NC} Cron jobs: ${RED}Não configurados${NC}"
fi
echo ""

echo -e "${YELLOW}9. Recursos do Sistema${NC}"
echo -e "  CPU: $(nproc) núcleos"
echo -e "  RAM: $(free -h | awk '/^Mem:/ {print $2}')"
echo -e "  Disco: $(df -h / | awk 'NR==2 {print $4}') disponível"
echo -e "  Swap: $(free -h | awk '/^Swap:/ {print $2}')"
echo ""

echo -e "${YELLOW}10. DNS${NC}"
CURRENT_IP=$(dig +short wats.macip.com.br @8.8.8.8 | tail -n1)
if [ "$CURRENT_IP" == "163.176.167.219" ]; then
    echo -e "${GREEN}✓${NC} DNS: ${GREEN}Apontando para VPS (163.176.167.219)${NC}"
elif [ -z "$CURRENT_IP" ]; then
    echo -e "${YELLOW}⚠${NC} DNS: ${YELLOW}Não resolvido${NC}"
else
    echo -e "${YELLOW}⚠${NC} DNS: ${YELLOW}Apontando para $CURRENT_IP${NC}"
fi
echo ""

echo -e "${BLUE}============================================${NC}"
echo -e "${BLUE}  Resumo${NC}"
echo -e "${BLUE}============================================${NC}"

# Calcular score
TOTAL=0
PASSED=0

# Serviços (3 pontos)
systemctl is-active --quiet nginx && ((PASSED++))
systemctl is-active --quiet php8.3-fpm && ((PASSED++))
systemctl is-active --quiet mysql && ((PASSED++))
TOTAL=$((TOTAL + 3))

# Diretórios (4 pontos)
[ -d "/var/www/wats" ] && ((PASSED++))
[ -d "/var/www/wats/storage" ] && ((PASSED++))
[ -d "/var/www/wats/uploads" ] && ((PASSED++))
[ -d "/var/log/wats" ] && ((PASSED++))
TOTAL=$((TOTAL + 4))

# Arquivos (2 pontos)
[ -f "/etc/nginx/sites-enabled/wats.macip.com.br" ] && ((PASSED++))
[ -f "/var/www/wats/.env" ] && ((PASSED++))
TOTAL=$((TOTAL + 2))

# SSL (1 ponto)
[ -f "/etc/letsencrypt/live/wats.macip.com.br/fullchain.pem" ] && ((PASSED++))
TOTAL=$((TOTAL + 1))

PERCENTAGE=$((PASSED * 100 / TOTAL))

echo ""
echo -e "Progresso: ${GREEN}$PASSED${NC}/$TOTAL (${GREEN}$PERCENTAGE%${NC})"
echo ""

if [ $PERCENTAGE -eq 100 ]; then
    echo -e "${GREEN}✓ Sistema pronto para produção!${NC}"
elif [ $PERCENTAGE -ge 80 ]; then
    echo -e "${YELLOW}⚠ Sistema quase pronto. Verifique os itens pendentes.${NC}"
else
    echo -e "${RED}✗ Sistema não está pronto. Complete a configuração.${NC}"
fi

echo ""
