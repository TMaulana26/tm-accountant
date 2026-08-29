#!/bin/sh
set -e

# Pastikan folder dan database SQLite ada
mkdir -p /var/www/html/database
if [ ! -f /var/www/html/database/database.sqlite ]; then
    touch /var/www/html/database/database.sqlite
fi

# Set permission storage, cache, dan database untuk www-data
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

# Setup cron job Laravel Scheduler
echo "* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1" | crontab -u www-data -

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
