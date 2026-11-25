#!/bin/sh
set -e

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY não definido no .env. Gere um com: make prod-key" >&2
    exit 1
fi

mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache

php artisan package:discover --ansi
[ -e public/storage ] || php artisan storage:link

# Só o container da API migra o banco; a fila usa a mesma imagem sem migrar.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
