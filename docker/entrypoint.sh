#!/bin/sh
set -e

# Only the web container migrates, so replicas don't race each other.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec docker-php-entrypoint "$@"
