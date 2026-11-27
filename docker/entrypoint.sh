#!/bin/sh
set -eu

APP_ROOT=/var/www/html
cd "$APP_ROOT"

# Render supplies PORT; plain Docker falls back to 8080.
export PORT="${PORT:-8080}"
export DB_CONNECTION="${DB_CONNECTION:-sqlite}"

if [ -z "${APP_KEY:-}" ]; then
    echo "[entrypoint] APP_KEY is required. Supply it as a runtime secret." >&2
    exit 1
fi

# Render PORT -> nginx listen directive. Only PORT is substituted, so nginx
# variables such as $uri and $document_root are left untouched.
envsubst '${PORT}' \
    < /etc/nginx/templates/default.conf.template \
    > /etc/nginx/conf.d/default.conf

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/testing \
    storage/app/public \
    storage/logs \
    bootstrap/cache \
    database

chown -R www-data:www-data storage bootstrap/cache database
chmod -R ug+rwX storage bootstrap/cache database

if [ ! -L public/storage ]; then
    php artisan storage:link --force >/dev/null
fi

# An absolute path keeps SQLite behaviour identical across hosts.
DB_DATABASE="${DB_DATABASE:-$APP_ROOT/database/database.sqlite}"
case "$DB_DATABASE" in
    /*) ;;
    *) echo "[entrypoint] DB_DATABASE must be an absolute path." >&2; exit 1 ;;
esac
export DB_DATABASE

# For the temporary preview the database lives in the writable app directory.
# It is intentionally ephemeral: no persistent disk is required.
if [ "$DB_CONNECTION" = "sqlite" ]; then
    mkdir -p "$(dirname "$DB_DATABASE")"
    touch "$DB_DATABASE"
    chown www-data:www-data "$DB_DATABASE"
    chmod 0664 "$DB_DATABASE"
fi

# Explicit preview initialization: migrations always run; seeders run only
# once and only when PCDR_PREVIEW_INIT=true. No destructive commands are used.
if [ "${RUN_INITIALIZATION:-1}" = "1" ]; then
    echo "[entrypoint] Initializing preview database..."
    su-exec www-data php artisan pcdr:preview:initialize --no-interaction
elif [ "${RUN_MIGRATIONS:-1}" = "1" ]; then
    echo "[entrypoint] Applying Laravel migrations (no seeding)..."
    su-exec www-data php artisan migrate --force --no-interaction
else
    echo "[entrypoint] Skipping database initialization."
fi

# Warm caches on every boot so runtime environment values are captured.
su-exec www-data php artisan config:cache
su-exec www-data php artisan view:cache

echo "[entrypoint] Starting nginx on port ${PORT} and PHP-FPM."
exec "$@"
