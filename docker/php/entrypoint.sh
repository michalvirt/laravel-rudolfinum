#!/bin/sh
set -eu

cd /var/www/html

mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache

if [ "${RUN_OPTIMIZE:-1}" = "1" ]; then
    su-exec www-data php artisan optimize --no-interaction
fi

if [ "${1:-}" = "php-fpm" ]; then
    exec php-fpm
fi

exec su-exec www-data "$@"
