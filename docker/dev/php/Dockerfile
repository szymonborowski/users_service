FROM php:8.5-fpm AS php-runtime

ARG UID=1000
ARG GID=1000

RUN groupadd -g ${GID} app \
 && useradd -u ${UID} -g app -m app

RUN apt-get update && apt-get install -y \
    git zip unzip curl libpng-dev libonig-dev libxml2-dev libzip-dev libicu-dev libsqlite3-dev \
    && docker-php-ext-install pdo_mysql pdo_sqlite mbstring zip exif pcntl bcmath intl sockets \
    && pecl install redis pcov xdebug \
    && docker-php-ext-enable redis pcov xdebug

RUN printf '[xdebug]\n\
xdebug.mode=${XDEBUG_MODE:-off}\n\
xdebug.client_host=host.docker.internal\n\
xdebug.client_port=9003\n\
xdebug.start_with_request=yes\n\
xdebug.idekey=VSCODE\n' > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY ../../../src .

RUN composer install --no-dev --optimize-autoloader

RUN chown -R www-data:www-data storage bootstrap/cache

RUN php artisan view:clear \
 && php artisan config:clear \
 && php artisan route:clear

USER app