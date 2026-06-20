FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    postgresql-dev \
    libzip-dev \
    icu-dev \
    sqlite-dev \
    $PHPIZE_DEPS

# Install PHP extensions (added pdo_sqlite for testing)
RUN docker-php-ext-install pdo_pgsql pdo_mysql pdo_sqlite bcmath gd zip intl

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Create www-data home and set permissions for storage/bootstrap
RUN mkdir -p /var/www/html/storage/logs \
    /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/bootstrap/cache \
    /var/www/html/public && \
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Copy entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# OPcache perf config — see docker/opcache.ini for rationale. Disables
# timestamp revalidation so the bind-mounted vendor/ tree isn't re-statted on
# every request (the cause of multi-second per-request latency + E2E timeouts).
COPY docker/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini

# Expose port 9000 for PHP-FPM
EXPOSE 9000

# Entrypoint fixes permissions on every start (needed for volume mounts)
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Start command
CMD ["php-fpm"]