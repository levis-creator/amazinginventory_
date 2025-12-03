# Multi-stage build for Railway deployment
# Stage 1: Build stage - Install dependencies and build assets
FROM php:8.3-fpm AS builder

# Set working directory
WORKDIR /var/www/html

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    libpq-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js (for Vite build)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy composer files first for better layer caching
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN COMPOSER_MEMORY_LIMIT=-1 composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --no-progress

# Copy package files for NPM
COPY package.json package-lock.json ./

# Install NPM dependencies
RUN npm ci --only=production=false || npm install

# Copy application files
COPY . .

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Create storage directories and set permissions
RUN mkdir -p storage/app/public \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    database \
    && chown -R www-data:www-data storage bootstrap/cache database \
    && chmod -R 775 storage bootstrap/cache database

# Create a minimal .env file for artisan commands during build
RUN if [ ! -f .env ]; then \
        echo "APP_NAME=Laravel" > .env && \
        echo "APP_ENV=production" >> .env && \
        echo "APP_KEY=" >> .env && \
        echo "APP_DEBUG=false" >> .env && \
        echo "APP_URL=http://localhost" >> .env; \
    fi

# Run Composer scripts
RUN composer dump-autoload --optimize --classmap-authoritative

# Run Laravel package discovery
RUN php artisan package:discover --ansi || echo "⚠️  Package discovery skipped"

# Ensure Filament assets are up to date
RUN php artisan filament:upgrade || echo "⚠️  Filament upgrade skipped"

# Publish Filament assets
RUN php artisan filament:assets || echo "⚠️  Filament assets publish skipped"

# Build frontend assets
RUN npm run build

# Verify build output exists
RUN if [ ! -f "public/build/manifest.json" ]; then \
        echo "⚠️  WARNING: Build manifest not found!" && \
        exit 1; \
    fi

# Create storage symbolic link
RUN php artisan storage:link || true

# Stage 2: Production stage - Minimal runtime image
FROM php:8.3-fpm

# Set working directory
WORKDIR /var/www/html

# Install system dependencies and PHP extensions (runtime only)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    libpq-dev \
    nginx \
    supervisor \
    curl \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configure PHP-FPM for production
# Use TCP instead of Unix socket for better compatibility with Railway
RUN sed -i 's/listen = \/run\/php\/php8.3-fpm.sock/listen = 127.0.0.1:9000/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/;clear_env = no/clear_env = no/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/;pm.status_path = \/status/pm.status_path = \/status/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/;ping.path = \/ping/ping.path = \/ping/' /usr/local/etc/php-fpm.d/www.conf

# Configure PHP for production
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=16" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.save_comments=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.fast_shutdown=1" >> /usr/local/etc/php/conf.d/opcache.ini

# Copy built application from builder stage
COPY --from=builder --chown=www-data:www-data /var/www/html /var/www/html

# Copy Nginx configuration
COPY nginx.conf /etc/nginx/sites-available/default

# Copy supervisor configuration for managing PHP-FPM and Nginx
RUN echo "[supervisord]" > /etc/supervisor/conf.d/supervisord.conf \
    && echo "nodaemon=true" >> /etc/supervisor/conf.d/supervisord.conf \
    && echo "" >> /etc/supervisor/conf.d/supervisord.conf \
    && echo "[program:php-fpm]" >> /etc/supervisor/conf.d/supervisord.conf \
    && echo "command=php-fpm" >> /etc/supervisor/conf.d/supervisord.conf \
    && echo "autostart=true" >> /etc/supervisor/conf.d/supervisord.conf \
    && echo "autorestart=true" >> /etc/supervisor/conf.d/supervisord.conf \
    && echo "stderr_logfile=/var/log/php-fpm.err.log" >> /etc/supervisor/conf.d/supervisord.conf \
    && echo "stdout_logfile=/var/log/php-fpm.out.log" >> /etc/supervisor/conf.d/supervisord.conf \
    && echo "" >> /etc/supervisor/conf.d/supervisord.conf \
    && echo "[program:nginx]" >> /etc/supervisor/conf.d/supervisord.conf \
    && echo "command=nginx -g 'daemon off;'" >> /etc/supervisor/conf.d/supervisord.conf \
    && echo "autostart=true" >> /etc/supervisor/conf.d/supervisord.conf \
    && echo "autorestart=true" >> /etc/supervisor/conf.d/supervisord.conf \
    && echo "stderr_logfile=/var/log/nginx.err.log" >> /etc/supervisor/conf.d/supervisord.conf \
    && echo "stdout_logfile=/var/log/nginx.out.log" >> /etc/supervisor/conf.d/supervisord.conf

# Copy and set up entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Create startup script that handles Railway PORT
RUN echo '#!/bin/bash' > /usr/local/bin/start-railway.sh \
    && echo 'set -e' >> /usr/local/bin/start-railway.sh \
    && echo 'PORT=${PORT:-80}' >> /usr/local/bin/start-railway.sh \
    && echo 'echo "🌐 Configuring Nginx to listen on port $PORT"' >> /usr/local/bin/start-railway.sh \
    && echo 'sed -i "s/listen 80;/listen $PORT;/" /etc/nginx/sites-available/default' >> /usr/local/bin/start-railway.sh \
    && echo 'echo "✅ Nginx configured for port $PORT"' >> /usr/local/bin/start-railway.sh \
    && echo 'echo "🔍 Testing Nginx configuration..."' >> /usr/local/bin/start-railway.sh \
    && echo 'nginx -t || (echo "❌ Nginx configuration test failed!" && exit 1)' >> /usr/local/bin/start-railway.sh \
    && echo 'echo "✅ Nginx configuration is valid"' >> /usr/local/bin/start-railway.sh \
    && echo 'echo "🚀 Starting supervisor (PHP-FPM + Nginx)..."' >> /usr/local/bin/start-railway.sh \
    && echo 'exec supervisord -c /etc/supervisor/conf.d/supervisord.conf' >> /usr/local/bin/start-railway.sh \
    && chmod +x /usr/local/bin/start-railway.sh

# Expose port (Railway will use PORT environment variable)
EXPOSE 80

# Health check - Railway checks / by default
# Laravel provides /up endpoint, but we'll check root / as Railway does
HEALTHCHECK --interval=30s --timeout=10s --start-period=90s --retries=5 \
    CMD curl -f http://localhost:${PORT:-80}/ || curl -f http://localhost:${PORT:-80}/up || exit 1

# Use entrypoint script to run migrations on startup
ENTRYPOINT ["docker-entrypoint.sh"]

# Start supervisor to manage PHP-FPM and Nginx
CMD ["/usr/local/bin/start-railway.sh"]
