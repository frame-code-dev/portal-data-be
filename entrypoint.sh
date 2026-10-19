#!/bin/bash
set -e

echo "Waiting for PostgreSQL database at ${DB_HOST}:${DB_PORT}..."
while ! nc -z ${DB_HOST:-porta-data-db} ${DB_PORT:-5432}; do
  sleep 1
done

echo "PostgreSQL is reachable!"

chmod -R 777 storage bootstrap/cache

php artisan migrate --force
php artisan db:seed --force

echo "Starting Laravel API server on port 8000..."
exec php artisan serve --host=0.0.0.0 --port=8000
