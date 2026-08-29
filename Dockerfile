# ==============================================================================
# STAGE 1: Build Frontend Assets (Vite, Tailwind v4, Filament Assets)
# ==============================================================================
FROM node:22-alpine AS frontend
WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY resources/ ./resources/
COPY vite.config.js ./
RUN npm run build

# ==============================================================================
# STAGE 2: Production PHP-FPM + Nginx Environment
# ==============================================================================
FROM php:8.4-fpm-alpine AS production

# Install PHP Extension Installer Helper
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# Install System Dependencies & PHP Extensions
RUN chmod +x /usr/local/bin/install-php-extensions && \
    apk add --no-cache \
        nginx \
        supervisor \
        curl \
        git \
        unzip \
        bash \
        tzdata \
        sqlite \
        ca-certificates && \
    install-php-extensions \
        bcmath \
        ctype \
        curl \
        dom \
        exif \
        fileinfo \
        gd \
        intl \
        mbstring \
        opcache \
        openssl \
        pcntl \
        pdo \
        pdo_sqlite \
        pdo_mysql \
        redis \
        session \
        tokenizer \
        xml \
        zip

# Copy Composer binary
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure PHP for Production
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=8'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.revalidate_freq=0'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'upload_max_filesize=20M'; \
        echo 'post_max_size=25M'; \
        echo 'memory_limit=256M'; \
        echo 'max_execution_time=120'; \
    } > /usr/local/etc/php/conf.d/custom-production.ini

# Set Working Directory
WORKDIR /var/www/html

# Copy composer files and install dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Copy Application Source Code
COPY . .

# Copy compiled frontend assets from STAGE 1
COPY --from=frontend /app/public/build ./public/build

# Finish Composer Scripts
RUN composer dump-autoload --optimize --no-dev

# Setup Nginx & Supervisor configuration
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Ensure storage and bootstrap directories exist with proper ownership
RUN mkdir -p /var/www/html/storage/framework/cache \
             /var/www/html/storage/framework/sessions \
             /var/www/html/storage/framework/views \
             /var/www/html/storage/logs \
             /var/www/html/bootstrap/cache \
             /var/www/html/database && \
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
