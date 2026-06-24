# ============================================
# Symfony Backend - Development FPM
# ============================================
FROM composer:2 AS composer

FROM php:8.3-fpm-alpine AS fpm

# Install PHP extensions
RUN docker-php-ext-install pdo_pgsql opcache intl

# Install Redis extension for Messenger
RUN pecl install redis && docker-php-ext-enable redis

# Install system dependencies
RUN apk add --no-cache \
    git \
    unzip \
    libzip-dev \
    bash \
    && docker-php-ext-install zip

# Install Composer
COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install --no-interaction --no-dev --optimize-autoloader

# Copy application
COPY . .

# Set permissions
RUN chown -R www-data:www-data var/

CMD ["php-fpm"]

# ============================================
# Symfony Backend - Production FPM
# ============================================
FROM fpm AS production

RUN composer install --no-interaction --optimize-autoloader --classmap-authoritative

ENV APP_ENV=prod
ENV APP_DEBUG=0

CMD ["php-fpm"]