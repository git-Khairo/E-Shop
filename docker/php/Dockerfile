FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git curl zip unzip libpng-dev libzip-dev icu-dev oniguruma-dev \
    mysql-client redis

# Install PHP extensions needed by Laravel
RUN docker-php-ext-install \
    pdo_mysql mbstring zip exif pcntl bcmath gd intl opcache

# Install Redis PHP extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP-FPM configuration for resource management (Req 2)
# pm.max_children = 10: limits concurrent PHP processes per container
# This prevents memory exhaustion under high load
RUN echo '[www]' > /usr/local/etc/php-fpm.d/zz-eshop.conf && \
    echo 'pm = dynamic' >> /usr/local/etc/php-fpm.d/zz-eshop.conf && \
    echo 'pm.max_children = 10' >> /usr/local/etc/php-fpm.d/zz-eshop.conf && \
    echo 'pm.start_servers = 3' >> /usr/local/etc/php-fpm.d/zz-eshop.conf && \
    echo 'pm.min_spare_servers = 2' >> /usr/local/etc/php-fpm.d/zz-eshop.conf && \
    echo 'pm.max_spare_servers = 5' >> /usr/local/etc/php-fpm.d/zz-eshop.conf && \
    echo 'pm.max_requests = 1000' >> /usr/local/etc/php-fpm.d/zz-eshop.conf

# OPcache for production performance
RUN echo 'opcache.enable=1' > /usr/local/etc/php/conf.d/opcache.ini && \
    echo 'opcache.memory_consumption=128' >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo 'opcache.interned_strings_buffer=8' >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo 'opcache.max_accelerated_files=10000' >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo 'opcache.validate_timestamps=0' >> /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

# Copy application
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null || true

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
