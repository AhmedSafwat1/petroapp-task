#!/usr/bin/env sh
set -e
cd "$(dirname "$0")/.."
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8010}"
