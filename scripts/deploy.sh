#!/usr/bin/env sh
set -eu
[ -f .env ] || { echo '.env belum ada. Copy .env.example lalu isi secret production.' >&2; exit 1; }
docker compose config --quiet
docker compose up -d postgres redis
docker compose up -d --build app
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan db:seed --force
docker compose exec -T app php artisan jaringanku:radius-resync
docker compose up -d --build radius queue scheduler nginx
docker compose ps
