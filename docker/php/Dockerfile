FROM node:22-bookworm-slim AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
COPY vite.config.ts tsconfig.json ./
RUN npm run build

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --no-scripts

FROM php:8.5-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpq-dev \
        libicu-dev \
        libzip-dev \
        libonig-dev \
        libcurl4-openssl-dev \
        unzip \
        curl \
        freeradius-utils \
    && docker-php-ext-install -j1 \
        pdo_pgsql \
        bcmath \
        pcntl \
        intl \
        zip \
        curl \
        mbstring \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY docker/php/entrypoint.sh /usr/local/bin/jaringanku-entrypoint
COPY docker/php/cli.sh /usr/local/bin/jaringanku-cli

RUN chmod +x /usr/local/bin/jaringanku-entrypoint /usr/local/bin/jaringanku-cli \
    && mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["jaringanku-entrypoint"]
CMD ["php-fpm"]
