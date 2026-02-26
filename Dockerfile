FROM php:8.3-apache

# Metadados da imagem
LABEL maintainer="MAC-IP TECNOLOGIA LTDA <suporte@macip.com.br>"
LABEL description="WATS - Sistema de Atendimento Multicanal"
LABEL version="1.0.0"

# Variáveis de ambiente padrão
ENV DEBIAN_FRONTEND=noninteractive \
    APACHE_DOCUMENT_ROOT=/var/www/html \
    PHP_MEMORY_LIMIT=256M \
    PHP_UPLOAD_MAX_FILESIZE=50M \
    PHP_POST_MAX_SIZE=50M \
    PHP_MAX_EXECUTION_TIME=300

# Instalar dependências do sistema e extensões PHP
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    git \
    unzip \
    curl \
    supervisor \
    cron \
    default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mysqli \
        gd \
        zip \
        mbstring \
        xml \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Habilitar módulos do Apache
RUN a2enmod rewrite headers expires deflate

# Configurar OPcache para produção
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo 'opcache.revalidate_freq=2'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'opcache.enable_cli=0'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Configurar PHP
RUN { \
    echo "upload_max_filesize = ${PHP_UPLOAD_MAX_FILESIZE}"; \
    echo "post_max_size = ${PHP_POST_MAX_SIZE}"; \
    echo "max_execution_time = ${PHP_MAX_EXECUTION_TIME}"; \
    echo "memory_limit = ${PHP_MEMORY_LIMIT}"; \
    echo "date.timezone = America/Sao_Paulo"; \
    echo "display_errors = Off"; \
    echo "log_errors = On"; \
    echo "error_log = /var/log/php_errors.log"; \
    } > /usr/local/etc/php/conf.d/custom.ini

# Configurar Apache VirtualHost otimizado
RUN { \
    echo '<VirtualHost *:80>'; \
    echo '    ServerAdmin suporte@macip.com.br'; \
    echo '    DocumentRoot /var/www/html'; \
    echo '    <Directory /var/www/html>'; \
    echo '        Options -Indexes +FollowSymLinks'; \
    echo '        AllowOverride All'; \
    echo '        Require all granted'; \
    echo '        # Security headers'; \
    echo '        Header always set X-Content-Type-Options "nosniff"'; \
    echo '        Header always set X-Frame-Options "SAMEORIGIN"'; \
    echo '        Header always set X-XSS-Protection "1; mode=block"'; \
    echo '    </Directory>'; \
    echo '    # Compressão Gzip'; \
    echo '    <IfModule mod_deflate.c>'; \
    echo '        AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json'; \
    echo '    </IfModule>'; \
    echo '    # Cache para assets estáticos'; \
    echo '    <IfModule mod_expires.c>'; \
    echo '        ExpiresActive On'; \
    echo '        ExpiresByType image/jpg "access plus 1 year"'; \
    echo '        ExpiresByType image/jpeg "access plus 1 year"'; \
    echo '        ExpiresByType image/gif "access plus 1 year"'; \
    echo '        ExpiresByType image/png "access plus 1 year"'; \
    echo '        ExpiresByType text/css "access plus 1 month"'; \
    echo '        ExpiresByType application/javascript "access plus 1 month"'; \
    echo '    </IfModule>'; \
    echo '    ErrorLog ${APACHE_LOG_DIR}/error.log'; \
    echo '    CustomLog ${APACHE_LOG_DIR}/access.log combined'; \
    echo '</VirtualHost>'; \
    } > /etc/apache2/sites-available/000-default.conf

# Criar diretórios necessários
RUN mkdir -p \
    /var/www/html/storage/cache \
    /var/www/html/storage/logs \
    /var/www/html/storage/temp \
    /var/www/html/uploads \
    /var/www/html/backups \
    /var/log/supervisor

# Definir diretório de trabalho
WORKDIR /var/www/html

# Copiar arquivos de configuração primeiro (melhor cache)
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/crontab /etc/cron.d/wats-cron

# Copiar aplicação
COPY --chown=www-data:www-data . /var/www/html/

# Configurar crontab e criar diretórios de log
RUN chmod 0644 /etc/cron.d/wats-cron \
    && crontab /etc/cron.d/wats-cron \
    && mkdir -p /var/log/cron \
    && touch /var/log/cron/cron.log

# Ajustar permissões
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/uploads \
    && chmod -R 775 /var/www/html/backups

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Expor porta
EXPOSE 80

# Volumes recomendados (documentação)
VOLUME ["/var/www/html/uploads", "/var/www/html/backups", "/var/www/html/storage"]

# Iniciar Supervisor (gerencia Apache + Cron)
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
