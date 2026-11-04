#!/bin/sh
# Prepares the SQLite volume and the app key, runs migrations, then starts Apache.
set -e
cd /var/www/html

touch "$DB_DATABASE"
chown www-data:www-data "$DB_DATABASE" /data

# Keep a generated key on the volume so sessions survive container restarts.
if [ -z "$APP_KEY" ]; then
    if [ ! -f /data/app.key ]; then
        php -r 'echo "base64:".base64_encode(random_bytes(32));' > /data/app.key
    fi
    APP_KEY="$(cat /data/app.key)"
    export APP_KEY
fi

php artisan migrate --force --no-interaction

# Demo data only when asked, and only into an empty database.
if [ "$DEMO_SEED" = "true" ] && [ "$(php artisan tinker --execute='echo \App\Models\User::count();')" = "0" ]; then
    php artisan db:seed --force --no-interaction
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache

exec "$@"
