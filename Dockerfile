# Stage 1: Install PHP dependencies (vendor)
FROM composer:latest AS php-builder
WORKDIR /app
COPY . .
RUN composer install --ignore-platform-reqs --no-dev --optimize-autoloader --no-interaction --no-scripts

# Stage 2: Build frontend assets
FROM node:20-alpine AS assets-builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
# Copy vendor directory containing Ziggy routing helper
COPY --from=php-builder /app/vendor ./vendor
RUN npm run build

# Stage 3: PHP & Apache Production Environment
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libpq-dev \
    libxml2-dev \
    libicu-dev \
    libc-client-dev \
    libkrb5-dev \
    zip \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install -j$(nproc) \
        gd \
        bcmath \
        zip \
        intl \
        pdo \
        pdo_pgsql \
        pgsql \
        opcache \
        exif \
        imap

# Enable Apache rewrite module
RUN a2enmod rewrite

# Configure Apache Document Root to Laravel's public directory
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Copy vendor dependencies from php-builder stage
COPY --from=php-builder /app/vendor ./vendor

# Copy built assets from assets-builder stage
COPY --from=assets-builder /app/public/build ./public/build

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Expose port 80
EXPOSE 80

# Entrypoint script for caching and migration
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
