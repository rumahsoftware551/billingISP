#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")/.."
database_file="${1:-}"
storage_file="${2:-}"
checksum_file="${3:-${database_file%.dump}.sha256}"
[ -n "$database_file" ] && [ -r "$database_file" ] \
  && [ -n "$storage_file" ] && [ -r "$storage_file" ] \
  || { echo "Usage: $0 backups/database.dump backups/storage.tar.gz [backups/checksum.sha256]" >&2; exit 1; }
[ -r "$checksum_file" ] || { echo "Checksum manifest not found: $checksum_file" >&2; exit 1; }
sha256sum -c "$checksum_file"
printf 'DANGER: this replaces the production database and uploaded files. Type RESTORE: '
read answer
[ "$answer" = 'RESTORE' ] || { echo 'Cancelled.'; exit 1; }
COMPOSE="docker compose --env-file .env.production -f docker-compose.prod.yml"
$COMPOSE stop app queue scheduler radius nginx backup
cleanup() { $COMPOSE up -d app radius queue scheduler nginx backup >/dev/null 2>&1 || true; }
trap cleanup EXIT INT TERM
cat "$database_file" | $COMPOSE exec -T postgres sh -lc 'PGPASSWORD=$(cat /run/secrets/db_password) pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists --no-owner --no-privileges'
$COMPOSE run --rm --no-deps -e RUN_MIGRATIONS=false app jaringanku-cli sh -lc \
  'find /var/www/html/storage/app -mindepth 1 -maxdepth 1 -exec rm -rf -- {} +; tar -C /var/www/html/storage -xzf -' \
  < "$storage_file"
$COMPOSE up -d app radius queue scheduler nginx backup
trap - EXIT INT TERM
echo 'Restore completed. Run application health checks immediately.'
