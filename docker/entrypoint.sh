#!/bin/sh
set -e

# Migrasi hanya dijalankan oleh service app (scheduler mengirim RUN_MIGRATIONS=false)
if [ "$RUN_MIGRATIONS" = "true" ]; then
  echo "[entrypoint] php artisan migrate --force"
  php artisan migrate --force
fi

# Seeder opsional, nyalakan sekali saja (RUN_SEEDER=true) agar data tidak ganda
if [ "$RUN_SEEDER" = "true" ]; then
  echo "[entrypoint] php artisan db:seed --force"
  php artisan db:seed --force
fi

php artisan config:cache

exec "$@"
