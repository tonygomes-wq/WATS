# ============================================
# WATS - Sistema Multi-Canal
# Dockerfile para Easypanel
# ============================================
# Suporta: PHP 8.2 + Apache + Node.js + Cron
# ============================================

FROM php:8.2-apache

LABEL maintainer="MACIP Tecnologia <suporte@macip.com.br>"
LABEL description="WATS - Sistema de Chat Multi-Canal (WhatsApp, Teams, Email)"

# ============================================
# VARIÁVEIS DE AMBIENTE
# ============================================
ENV DEBIAN_FRONTEND=noninteractive \
    APACHE_DOCUMENT_ROOT=/var/www/html \
    TZ=America/Sao_Paulo

# ============================================
# INSTALAR DEPENDÊNCIAS DO SISTEMA
# ============================================
RUN apt-get update && apt-get install -y \
    # Ferramentas básicas
    curl \
    wget \
    git \
    unzip \
    vim \
    cron \
    supervisor \
    # Bibliotecas para PHP
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    # Limpeza
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ============================================
# INSTALAR EXTENSÕES PHP
# ============================================
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    mysqli \
    gd \
    zip \
    mbstring \
    xml \
    curl \
    opcache

# ============================================
# INSTALAR COMPOSER
# ============================================
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ============================================
# INSTALAR NODE.JS 20.x
# ============================================
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g npm@latest

# ============================================
# CONFIGURAR TIMEZONE
# ============================================
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime \
    && echo $TZ > /etc/timezone

# ============================================
# CONFIGURAR PHP
# ============================================
RUN { \
    echo 'memory_limit = 256M'; \
    echo 'upload_max_filesize = 10M'; \
    echo 'post_max_size = 10M'; \
    echo 'max_execution_time = 300'; \
    echo 'max_input_time = 300'; \
    echo 'date.timezone = America/Sao_Paulo'; \
    echo 'display_errors = Off'; \
    echo 'log_errors = On'; \
    echo 'error_log = /var/log/php_errors.log'; \
    echo 'opcache.enable = 1'; \
    echo 'opcache.memory_consumption = 128'; \
    echo 'opcache.interned_strings_buffer = 8'; \
    echo 'opcache.max_accelerated_files = 10000'; \
    echo 'opcache.revalidate_freq = 2'; \
} > /usr/local/etc/php/conf.d/custom.ini

# ============================================
# CONFIGURAR APACHE
# ============================================
RUN a2enmod rewrite headers expires deflate

# Configurar DocumentRoot
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Criar arquivo de configuração do Apache
RUN { \
    echo '<VirtualHost *:80>'; \
    echo '    ServerAdmin suporte@macip.com.br'; \
    echo '    DocumentRoot /var/www/html'; \
    echo ''; \
    echo '    <Directory /var/www/html>'; \
    echo '        Options -Indexes +FollowSymLinks'; \
    echo '        AllowOverride All'; \
    echo '        Require all granted'; \
    echo '    </Directory>'; \
    echo ''; \
    echo '    # Logs'; \
    echo '    ErrorLog ${APACHE_LOG_DIR}/error.log'; \
    echo '    CustomLog ${APACHE_LOG_DIR}/access.log combined'; \
    echo ''; \
    echo '    # Security Headers'; \
    echo '    Header always set X-Content-Type-Options "nosniff"'; \
    echo '    Header always set X-Frame-Options "SAMEORIGIN"'; \
    echo '    Header always set X-XSS-Protection "1; mode=block"'; \
    echo ''; \
    echo '    # Compression'; \
    echo '    <IfModule mod_deflate.c>'; \
    echo '        AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json'; \
    echo '    </IfModule>'; \
    echo '</VirtualHost>'; \
} > /etc/apache2/sites-available/000-default.conf

# ============================================
# CRIAR DIRETÓRIOS NECESSÁRIOS
# ============================================
RUN mkdir -p \
    /var/www/html/uploads \
    /var/www/html/logs \
    /var/www/html/storage/cache \
    /var/log/supervisor

# ============================================
# COPIAR CÓDIGO DA APLICAÇÃO
# ============================================
WORKDIR /var/www/html

# Copiar arquivos de dependências primeiro (cache layer)
COPY composer.json composer.lock* ./
COPY package.json package-lock.json ./

# Instalar dependências PHP
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction || true

# Instalar dependências Node.js
RUN npm ci --production --silent || npm install --production --silent

# Copiar resto do código
COPY . .

# ============================================
# CONFIGURAR CRON JOBS
# ============================================
RUN { \
    echo '# WATS Cron Jobs'; \
    echo '# Sincronizar mensagens do Teams (a cada 5 minutos)'; \
    echo '*/5 * * * * www-data cd /var/www/html && /usr/local/bin/php cron/sync_teams_messages.php >> /var/www/html/logs/cron_teams.log 2>&1'; \
    echo ''; \
    echo '# Buscar emails (a cada 10 minutos)'; \
    echo '*/10 * * * * www-data cd /var/www/html && /usr/local/bin/php cron/fetch_emails.php >> /var/www/html/logs/cron_emails.log 2>&1'; \
    echo ''; \
    echo '# Processar dispatches agendados (a cada 5 minutos)'; \
    echo '*/5 * * * * www-data cd /var/www/html && /usr/local/bin/php cron/process_scheduled_dispatches.php >> /var/www/html/logs/cron_dispatches.log 2>&1'; \
    echo ''; \
    echo '# Calcular analytics (a cada hora)'; \
    echo '0 * * * * www-data cd /var/www/html && /usr/local/bin/php cron/calculate_analytics.php >> /var/www/html/logs/cron_analytics.log 2>&1'; \
    echo ''; \
    echo '# Calcular analytics de tempo (a cada hora)'; \
    echo '0 * * * * www-data cd /var/www/html && /usr/local/bin/php cron/calculate_time_analytics.php >> /var/www/html/logs/cron_time_analytics.log 2>&1'; \
    echo ''; \
    echo '# Backup do banco (diariamente às 2h)'; \
    echo '0 2 * * * www-data cd /var/www/html && /usr/local/bin/php cron/backup_database.php >> /var/www/html/logs/cron_backup.log 2>&1'; \
    echo ''; \
    echo '# Limpeza de dados antigos (diariamente às 3h)'; \
    echo '0 3 * * * www-data cd /var/www/html && /usr/local/bin/php cron/cleanup_old_data.php >> /var/www/html/logs/cron_cleanup.log 2>&1'; \
    echo ''; \
    echo '# Desabilitar usuários expirados (diariamente às 4h)'; \
    echo '0 4 * * * www-data cd /var/www/html && /usr/local/bin/php cron/disable_expired_users.php >> /var/www/html/logs/cron_expired.log 2>&1'; \
    echo ''; \
    echo '# Monitorar storage (a cada 6 horas)'; \
    echo '0 */6 * * * www-data cd /var/www/html && /usr/local/bin/php cron/monitor_storage.php >> /var/www/html/logs/cron_storage.log 2>&1'; \
    echo ''; \
    echo '# Processar sentimentos (a cada hora)'; \
    echo '0 * * * * www-data cd /var/www/html && /usr/local/bin/php cron/process_sentiment.php >> /var/www/html/logs/cron_sentiment.log 2>&1'; \
    echo ''; \
    echo '# Enviar resumos (diariamente às 8h)'; \
    echo '0 8 * * * www-data cd /var/www/html && /usr/local/bin/php cron/send_summaries.php >> /var/www/html/logs/cron_summaries.log 2>&1'; \
    echo ''; \
    echo '# Automação Kanban (a cada 15 minutos)'; \
    echo '*/15 * * * * www-data cd /var/www/html && /usr/local/bin/php cron/kanban_automation.php >> /var/www/html/logs/cron_kanban.log 2>&1'; \
    echo ''; \
    echo '# Linha em branco necessária no final'; \
    echo ''; \
} > /etc/cron.d/wats-cron

RUN chmod 0644 /etc/cron.d/wats-cron \
    && crontab /etc/cron.d/wats-cron

# ============================================
# CONFIGURAR SUPERVISOR
# ============================================
RUN { \
    echo '[supervisord]'; \
    echo 'nodaemon=true'; \
    echo 'logfile=/var/log/supervisor/supervisord.log'; \
    echo 'pidfile=/var/run/supervisord.pid'; \
    echo 'childlogdir=/var/log/supervisor'; \
    echo ''; \
    echo '[program:apache2]'; \
    echo 'command=/usr/sbin/apache2ctl -D FOREGROUND'; \
    echo 'autostart=true'; \
    echo 'autorestart=true'; \
    echo 'stdout_logfile=/dev/stdout'; \
    echo 'stdout_logfile_maxbytes=0'; \
    echo 'stderr_logfile=/dev/stderr'; \
    echo 'stderr_logfile_maxbytes=0'; \
    echo ''; \
    echo '[program:cron]'; \
    echo 'command=/usr/sbin/cron -f'; \
    echo 'autostart=true'; \
    echo 'autorestart=true'; \
    echo 'stdout_logfile=/var/www/html/logs/cron_supervisor.log'; \
    echo 'stderr_logfile=/var/www/html/logs/cron_supervisor_error.log'; \
    echo ''; \
    echo '[program:websocket]'; \
    echo 'command=/usr/bin/node /var/www/html/websocket_client.js'; \
    echo 'directory=/var/www/html'; \
    echo 'autostart=true'; \
    echo 'autorestart=true'; \
    echo 'stdout_logfile=/var/www/html/logs/websocket.log'; \
    echo 'stderr_logfile=/var/www/html/logs/websocket_error.log'; \
    echo 'user=www-data'; \
} > /etc/supervisor/conf.d/wats.conf

# ============================================
# PERMISSÕES
# ============================================
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/uploads \
    && chmod -R 775 /var/www/html/logs \
    && chmod -R 775 /var/www/html/storage

# ============================================
# HEALTH CHECK
# ============================================
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://localhost/health.php || exit 1

# ============================================
# EXPOR PORTA
# ============================================
EXPOSE 80

# ============================================
# INICIAR SUPERVISOR
# ============================================
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
