#!/bin/bash
# ============================================
# Script de Migração Automatizada - WATS
# VPS: 163.176.167.219
# ============================================

set -e  # Parar em caso de erro

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Variáveis
VPS_IP="163.176.167.219"
VPS_USER="wats"
APP_DIR="/var/www/wats"
DB_NAME="faceso56_watsdb"
DB_USER="faceso56_watsdb"
DB_PASS="V%(zAeG87;OTvv7^"

echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}  WATS - Script de Migração para VPS${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""

# Função para exibir mensagens
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Verificar se está rodando como root
if [ "$EUID" -ne 0 ]; then 
    log_error "Este script deve ser executado como root"
    exit 1
fi

# Menu principal
echo "Escolha uma opção:"
echo "1) Preparar VPS (Fase 1-2)"
echo "2) Configurar Nginx (Fase 5)"
echo "3) Configurar SSL (Fase 7)"
echo "4) Configurar Cron Jobs (Fase 8)"
echo "5) Instalação Completa (Todas as fases)"
echo "0) Sair"
echo ""
read -p "Opção: " option

case $option in
    1)
        log_info "Iniciando preparação da VPS..."
        
        # Atualizar sistema
        log_info "Atualizando sistema..."
        apt update && apt upgrade -y
        
        # Instalar utilitários
        log_info "Instalando utilitários..."
        apt install -y curl wget git unzip vim htop ufw
        
        # Configurar firewall
        log_info "Configurando firewall..."
        ufw allow OpenSSH
        ufw allow 80/tcp
        ufw allow 443/tcp
        ufw --force enable
        
        # Configurar swap
        log_info "Configurando swap (4GB)..."
        if [ ! -f /swapfile ]; then
            fallocate -l 4G /swapfile
            chmod 600 /swapfile
            mkswap /swapfile
            swapon /swapfile
            echo '/swapfile none swap sw 0 0' >> /etc/fstab
        fi
        
        # Instalar Nginx
        log_info "Instalando Nginx..."
        apt install -y nginx
        systemctl start nginx
        systemctl enable nginx
        
        # Instalar PHP 8.3
        log_info "Instalando PHP 8.3..."
        add-apt-repository ppa:ondrej/php -y
        apt update
        apt install -y php8.3-fpm php8.3-cli php8.3-common \
            php8.3-mysql php8.3-zip php8.3-gd php8.3-mbstring \
            php8.3-curl php8.3-xml php8.3-bcmath php8.3-json \
            php8.3-intl php8.3-soap php8.3-imap php8.3-redis
        
        # Configurar PHP
        log_info "Configurando PHP..."
        sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 50M/' /etc/php/8.3/fpm/php.ini
        sed -i 's/post_max_size = 8M/post_max_size = 50M/' /etc/php/8.3/fpm/php.ini
        sed -i 's/max_execution_time = 30/max_execution_time = 300/' /etc/php/8.3/fpm/php.ini
        sed -i 's/memory_limit = 128M/memory_limit = 512M/' /etc/php/8.3/fpm/php.ini
        systemctl restart php8.3-fpm
        
        # Instalar Composer
        log_info "Instalando Composer..."
        curl -sS https://getcomposer.org/installer | php
        mv composer.phar /usr/local/bin/composer
        chmod +x /usr/local/bin/composer
        
        log_info "Preparação concluída!"
        ;;
        
    2)
        log_info "Configurando Nginx..."
        
        # Criar diretório da aplicação
        mkdir -p $APP_DIR
        
        # Criar configuração do Nginx
        cat > /etc/nginx/sites-available/wats.macip.com.br <<'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name wats.macip.com.br;
    
    root /var/www/wats;
    index index.php index.html;
    
    access_log /var/log/nginx/wats_access.log;
    error_log /var/log/nginx/wats_error.log;
    
    client_max_body_size 50M;
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ /\.env {
        deny all;
    }
    
    location ~ /\.git {
        deny all;
    }
}
EOF
        
        # Habilitar site
        ln -sf /etc/nginx/sites-available/wats.macip.com.br /etc/nginx/sites-enabled/
        
        # Testar e recarregar
        nginx -t && systemctl reload nginx
        
        log_info "Nginx configurado!"
        ;;
        
    3)
        log_info "Configurando SSL..."
        
        # Instalar Certbot
        apt install -y certbot python3-certbot-nginx
        
        # Obter certificado
        systemctl stop nginx
        certbot certonly --standalone -d wats.macip.com.br
        systemctl start nginx
        
        # Atualizar configuração Nginx para HTTPS
        cat > /etc/nginx/sites-available/wats.macip.com.br <<'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name wats.macip.com.br;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name wats.macip.com.br;
    
    root /var/www/wats;
    index index.php index.html;
    
    ssl_certificate /etc/letsencrypt/live/wats.macip.com.br/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/wats.macip.com.br/privkey.pem;
    
    access_log /var/log/nginx/wats_access.log;
    error_log /var/log/nginx/wats_error.log;
    
    client_max_body_size 50M;
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ /\.env {
        deny all;
    }
}
EOF
        
        nginx -t && systemctl reload nginx
        
        log_info "SSL configurado!"
        ;;
        
    4)
        log_info "Configurando Cron Jobs..."
        
        # Criar diretório de logs
        mkdir -p /var/log/wats
        chown www-data:www-data /var/log/wats
        
        # Adicionar cron jobs
        (crontab -l 2>/dev/null; cat <<'EOF'
# WATS Cron Jobs
*/5 * * * * /usr/bin/php /var/www/wats/cron/sync_teams_messages.php >> /var/log/wats/teams_sync.log 2>&1
*/5 * * * * /usr/bin/php /var/www/wats/cron/fetch_emails.php >> /var/log/wats/email_fetch.log 2>&1
* * * * * /usr/bin/php /var/www/wats/cron/process_scheduled_dispatches.php >> /var/log/wats/dispatches.log 2>&1
0 2 * * * /usr/bin/php /var/www/wats/cron/backup_database.php >> /var/log/wats/backup.log 2>&1
0 3 * * 0 find /var/log/wats -name "*.log" -mtime +30 -delete
EOF
        ) | crontab -
        
        log_info "Cron jobs configurados!"
        ;;
        
    5)
        log_info "Iniciando instalação completa..."
        
        # Executar todas as opções
        $0 1
        $0 2
        
        log_info "Instalação completa!"
        log_warn "Próximos passos:"
        log_warn "1. Transferir arquivos da aplicação para $APP_DIR"
        log_warn "2. Importar banco de dados"
        log_warn "3. Configurar SSL (opção 3)"
        log_warn "4. Configurar Cron Jobs (opção 4)"
        log_warn "5. Atualizar DNS"
        ;;
        
    0)
        log_info "Saindo..."
        exit 0
        ;;
        
    *)
        log_error "Opção inválida!"
        exit 1
        ;;
esac

echo ""
log_info "Operação concluída com sucesso!"
