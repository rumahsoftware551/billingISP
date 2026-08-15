#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")/.."
[ -s .env.production ] || { echo '.env.production missing' >&2; exit 1; }
[ -s secrets/db_password.txt ] || { echo 'db_password secret missing' >&2; exit 1; }
mkdir -p backups
umask 077
stamp=$(date -u +%Y%m%dT%H%M%SZ)
file="backups/jaringanku-manual-${stamp}.dump"
COMPOSE="docker compose --env-file .env.production -f docker-compose.prod.yml"
$COMPOSE exec -T postgres sh -lc 'PGPASSWORD=$(cat /run/secrets/db_password) pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' > "$file"
[ -s "$file" ] || { rm -f "$file"; echo 'Backup failed or empty' >&2; exit 1; }
sha256sum "$file" > "${file}.sha256"
echo "Backup created: $file"
