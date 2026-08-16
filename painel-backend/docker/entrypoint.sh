#!/bin/sh
set -e

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -q "^APP_KEY=base64" .env 2>/dev/null; then
    php artisan key:generate --ansi --force
fi

mkdir -p storage/framework/{cache,sessions,testing,views} storage/logs bootstrap/cache
if [ ! -e public/storage ]; then
    php artisan storage:link
fi
# 0777 (dev only): www-data (uid interno) e o usuário do host têm UID diferentes
# no bind mount do macOS; sem remapear UID, permissão ampla é o jeito simples
# de evitar erro de escrita nos dois lados. Não usar isso fora de ambiente local.
chmod -R 0777 storage bootstrap/cache

exec "$@"
