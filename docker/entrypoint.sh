#!/bin/bash
# Cron setup - No need for separate scheduler container
echo "* * * * * cd /app && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/mycron
chmod 0644 /etc/cron.d/mycron
crontab /etc/cron.d/mycron

# Start cron service
service cron start

php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan optimize

# Set permission of project folder
chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 755 /app/storage

# Octane
#php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=80 --admin-port=2019 --caddyfile=./docker/caddyfile

# Default Config
/usr/local/bin/docker-php-entrypoint --config /etc/caddy/Caddyfile --adapter caddyfile
