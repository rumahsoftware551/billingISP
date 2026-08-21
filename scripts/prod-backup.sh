#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")/.."
[ -s .env.production ] || { echo '.env.production missing' >&2; exit 1; }
[ -s secrets/db_password.txt ] || { echo 'db_password secret missing' >&2; exit 1; }
mkdir -p backups
umask 077
stamp=$(date -u +%Y%m%dT%H%M%SZ)
file="backups/jaringanku-manual-${stamp}.dump"
storage_file="backups/jaringanku-manual-${stamp}.storage.tar.gz"
manifest_file="backups/jaringanku-manual-${stamp}.manifest.sha256"
COMPOSE="docker compose --env-file .env.production -f docker-compose.prod.yml"
$COMPOSE exec -T postgres sh -lc 'PGPASSWORD=$(cat /run/secrets/db_password) pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' > "$file" || { rm -f "$file"; echo 'Database backup failed.' >&2; exit 1; }
[ -s "$file" ] || { rm -f "$file"; echo 'Database backup is empty.' >&2; exit 1; }
if ! $COMPOSE exec -T app sh -lc 'tar -C /var/www/html/storage/app -czf - .' > "$storage_file"; then
  rm -f "$file" "$storage_file"
  echo 'Persistent-storage backup failed; incomplete backup removed.' >&2
  exit 1
fi
[ -s "$storage_file" ] || { rm -f "$file" "$storage_file"; echo 'Persistent-storage backup is empty.' >&2; exit 1; }
sha256sum "$file" > "${file}.sha256"
sha256sum "$storage_file" > "${storage_file}.sha256"
(
  cd backups
  sha256sum "$(basename "$file")" "$(basename "$storage_file")"
) > "$manifest_file"
echo "Backup created: $file + $storage_file"
