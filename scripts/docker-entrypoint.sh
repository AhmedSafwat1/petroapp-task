#!/usr/bin/env sh
set -e
cd /var/www/html
# Bind mount hides the image .env; create one when the host has no .env
if [ ! -f .env ]; then
  cp .env.docker .env
fi
php artisan key:generate --force
php artisan migrate --force
exec php artisan serve --host=0.0.0.0 --port=8010
