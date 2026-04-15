#!/bin/bash
set -e

cd /var/www/html
composer install --no-interaction --optimize-autoloader

exec "$@"
