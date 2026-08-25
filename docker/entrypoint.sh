#!/bin/bash
set -e

cd /var/www/html

# Cache config/routes/views for production speed
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations (safe to run on every deploy; --force skips the confirmation prompt)
php artisan migrate --force

# Link storage (safe if already linked)
php artisan storage:link || true

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
