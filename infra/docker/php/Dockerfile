# Imagen PHP para el backend Laravel (app, worker y scheduler usan la misma imagen)
FROM php:8.3-fpm-bookworm AS base

ARG UID=1000
ARG GID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libzip-dev libicu-dev libpng-dev libjpeg62-turbo-dev \
        libfreetype6-dev libwebp-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql zip intl gd bcmath pcntl exif opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY infra/docker/php/php.ini /usr/local/etc/php/conf.d/zz-flux.ini

RUN groupmod -o -g ${GID} www-data && usermod -o -u ${UID} -g www-data www-data

WORKDIR /var/www/backend

# ---- Producción: dependencias y código dentro de la imagen ----
FROM base AS production
COPY --chown=www-data:www-data backend/ /var/www/backend/
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
    && php artisan filament:assets \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
