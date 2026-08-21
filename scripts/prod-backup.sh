#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")/.."
[ -s .env.production ] || { echo '.env.production missing' >&2; exit 1; }
[ -s secrets/db_password.txt ] || { echo 'db_password secret missing' >&2; exit 1; }
mkdir -p backups
umask 077
stamp=$(date -u +%Y%m%dT%H%M%SZ)
database_file="backups/jaringanku-manual-${stamp}.dump"
storage_file="backups/jaringanku-storage-manual-${stamp}.tar.gz"
database_temp="${database_file}.tmp"
storage_temp="${storage_file}.tmp"
COMPOSE="docker compose --env-file .env.production -f docker-compose.prod.yml"

cleanup() {
  rm -f "$database_temp" "$storage_temp"
}
trap cleanup EXIT

$COMPOSE exec -T postgres sh -lc 'PGPASSWORD=$(cat /run/secrets/db_password) pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' > "$database_temp"
$COMPOSE exec -T app tar -C /var/www/html/storage -czf - app > "$storage_temp"
[ -s "$database_temp" ] && [ -s "$storage_temp" ] || { echo 'Backup database atau storage gagal/kosong' >&2; exit 1; }

mv "$database_temp" "$database_file"
mv "$storage_temp" "$storage_file"
sha256sum "$database_file" "$storage_file" > "backups/jaringanku-manual-${stamp}.sha256"
trap - EXIT

echo "Backup created: $database_file + $storage_file"
