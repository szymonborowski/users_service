# =========================================================
# 1 Frontend build (Vite)
# =========================================================
FROM node:22-alpine AS build-frontend

WORKDIR /app

# kopiujemy tylko pliki potrzebne do instalacji zależności
COPY src/package.json src/package-lock.json ./
RUN npm ci

# kopiujemy pliki frontendu
COPY src/certs/* ./certs/
COPY src/resources ./resources
COPY src/vite.config.* ./

# build assetów
RUN npm run build


# =========================================================
# 2 PHP Runtime
# =========================================================
FROM php:8.5-fpm AS php-runtime

ARG UID=1000
ARG GID=1000

RUN groupadd -g ${GID} app \
 && useradd -u ${UID} -g app -m app

RUN apt-get update && apt-get install -y \
    git zip unzip curl libpng-dev libonig-dev libxml2-dev libzip-dev libicu-dev \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath intl


COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY ./src .

COPY --from=build-frontend /app/public/build ./public/build

RUN composer install --no-dev --optimize-autoloader

RUN chown -R www-data:www-data storage bootstrap/cache

RUN php artisan view:clear \
 && php artisan config:clear \
 && php artisan route:clear

USER app