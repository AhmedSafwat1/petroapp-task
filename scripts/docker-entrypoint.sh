#!/usr/bin/env sh
set -e
cd /var/www/html
php artisan migrate --force
exec php artisan serve --host=0.0.0.0 --port=8010
