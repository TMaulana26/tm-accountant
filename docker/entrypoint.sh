#!/bin/sh
set -e

# Pastikan folder dan database SQLite ada
mkdir -p /var/www/html/database
if [ ! -f /var/www/html/database/database.sqlite ]; then
    touch /var/www/html/database/database.sqlite
fi

# Set permission storage, cache, database, dan .env untuk www-data
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

if [ -f /var/www/html/.env ]; then
    chown www-data:www-data /var/www/html/.env 2>/dev/null || true
    chmod 666 /var/www/html/.env 2>/dev/null || true
fi

# Setup cron job Laravel Scheduler
echo "* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1" | crontab -u www-data -

# Pastikan APP_KEY terpasang sebelum migrasi atau caching
if [ -f /var/www/html/.env ]; then
    if ! grep -E -q '^APP_KEY=[a-zA-Z0-9:+/=_]+' /var/www/html/.env; then
        echo "== APP_KEY belum disetel atau kosong. Meng-generate application key..."
        php /var/www/html/artisan key:generate --force
    fi
fi

# Jalankan optimasi & migrasi database Laravel
echo "== Menjalankan migrasi database..."
php /var/www/html/artisan storage:link --force || true
php /var/www/html/artisan migrate --force || true

echo "== Cache konfigurasi, routes, dan views..."
php /var/www/html/artisan optimize:clear
php /var/www/html/artisan config:cache
php /var/www/html/artisan route:cache
php /var/www/html/artisan view:cache
php /var/www/html/artisan filament:cache-components || true

echo "== Memulai supervisord..."
exec "$@"
