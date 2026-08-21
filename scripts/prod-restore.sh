#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")/.."
file="${1:-}"
[ -n "$file" ] && [ -r "$file" ] || { echo "Usage: $0 backups/file.dump" >&2; exit 1; }
storage_file="${file%.dump}.storage.tar.gz"
[ -r "$storage_file" ] || { echo "Companion persistent-storage backup is required: $storage_file" >&2; exit 1; }
if [ -r "${file}.sha256" ]; then
  sha256sum -c "${file}.sha256"
fi
if [ -r "${storage_file}.sha256" ]; then
  sha256sum -c "${storage_file}.sha256"
fi
tar -tzf "$storage_file" >/dev/null
printf 'DANGER: this replaces the production database and persistent uploads. Type RESTORE: '
read answer
[ "$answer" = 'RESTORE' ] || { echo 'Cancelled.'; exit 1; }
COMPOSE="docker compose --env-file .env.production -f docker-compose.prod.yml"
$COMPOSE stop app queue scheduler radius nginx backup
cleanup() { $COMPOSE up -d app radius queue scheduler nginx backup >/dev/null 2>&1 || true; }
trap cleanup EXIT INT TERM
cat "$file" | $COMPOSE exec -T postgres sh -lc 'PGPASSWORD=$(cat /run/secrets/db_password) pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists --no-owner --no-privileges'
cat "$storage_file" | $COMPOSE run --rm -T --no-deps --entrypoint sh app -lc 'find /var/www/html/storage/app -mindepth 1 -maxdepth 1 -exec rm -rf -- {} + && tar -xzf - -C /var/www/html/storage/app && chown -R www-data:www-data /var/www/html/storage/app'
$COMPOSE up -d app radius queue scheduler nginx backup
trap - EXIT INT TERM
echo 'Database and persistent-storage restore completed. Run application health checks immediately.'
