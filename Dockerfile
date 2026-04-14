FROM php:8.2-apache

WORKDIR /var/www/html

# System packages and PHP extensions for PostgreSQL/Supabase.
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Install Composer from official image.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install PHP dependencies first for better layer caching.
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

# Copy application source.
COPY . .

# Ensure runtime-writable paths exist.
RUN mkdir -p logs uploads/appointments uploads/evidence \
    && touch uploads/appointments/.gitkeep uploads/evidence/.gitkeep \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]