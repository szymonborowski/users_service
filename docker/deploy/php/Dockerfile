# =========================================================
# Stage 1: Frontend build (Vite)
# =========================================================
FROM node:22-alpine AS build-frontend

WORKDIR /app

COPY src/package.json ./
RUN npm install --no-audit

COPY src/resources ./resources
COPY src/vite.config.* ./

RUN npm run build


# =========================================================
# Stage 2: PHP base with extensions
# =========================================================
FROM php:8.5-fpm-alpine AS php-base

RUN apk add --no-cache \
        libpng \
        libzip \
        icu-libs \
        oniguruma \
    && apk add --no-cache --virtual .build-deps \
        libpng-dev \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
        libxml2-dev \
        linux-headers \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mbstring zip exif pcntl bcmath intl sockets \
    && apk del .build-deps \
    && rm -rf /tmp/*


# =========================================================
# Stage 3: Composer dependencies
# =========================================================
FROM php-base AS build-composer

RUN apk add --no-cache git unzip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY ./src .

RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-ansi


# =========================================================
# Stage 4: Production runtime
# =========================================================
FROM php-base AS php-runtime

LABEL org.opencontainers.image.title="users-service" \
      org.opencontainers.image.authors="decybell" \
      org.opencontainers.image.source="https://github.com/decybell/portfolio"

# PHP-FPM healthcheck endpoint
RUN printf '[www]\nping.path = /ping\nping.response = pong\n' \
    > /usr/local/etc/php-fpm.d/zz-healthcheck.conf

WORKDIR /var/www/html

COPY --from=build-composer /var/www/html .
COPY --from=build-frontend /app/public/build ./public/build

RUN chown -R www-data:www-data storage bootstrap/cache \
 && php artisan view:clear \
 && php artisan config:clear \
 && php artisan route:clear

EXPOSE 9000

STOPSIGNAL SIGQUIT

HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
  CMD php -r 'exit(0);' || exit 1

USER www-data
