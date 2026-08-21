#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")/.."
[ -s .env.production ] || { echo '.env.production missing' >&2; exit 1; }
[ -s secrets/db_password.txt ] || { echo 'db_password secret missing' >&2; exit 1; }
mkdir -p backups
umask 077
stamp=$(date -u +%Y%m%dT%H%M%SZ)
database_file="backups/jaringanku-manual-${stamp}.dump"
storage_file="backups/jaringanku-manual-${stamp}.storage.tar.gz"
manifest_file="backups/jaringanku-manual-${stamp}.manifest.sha256"
COMPOSE="docker compose --env-file .env.production -f docker-compose.prod.yml"
$COMPOSE up -d postgres backup
$COMPOSE exec -T postgres sh -lc 'PGPASSWORD=$(cat /run/secrets/db_password) pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' > "$database_file"
[ -s "$database_file" ] || { rm -f "$database_file"; echo 'Database backup failed or empty' >&2; exit 1; }

backup_id=$($COMPOSE ps -q backup)
[ -n "$backup_id" ] || { rm -f "$database_file"; echo 'Backup container is not running' >&2; exit 1; }
docker exec "$backup_id" tar -C /storage -czf "/backups/$(basename "$storage_file")" .
docker cp "$backup_id:/backups/$(basename "$storage_file")" "$storage_file"
[ -s "$storage_file" ] || { rm -f "$database_file" "$storage_file"; echo 'Storage backup failed or empty' >&2; exit 1; }

(
  cd backups
  sha256sum "$(basename "$database_file")" "$(basename "$storage_file")"
) > "$manifest_file"
echo "Backup created: $database_file, $storage_file, $manifest_file"
