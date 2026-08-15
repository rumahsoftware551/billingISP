#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")/.."
file="${1:-}"
[ -n "$file" ] && [ -r "$file" ] || { echo "Usage: $0 backups/file.dump" >&2; exit 1; }
if [ -r "${file}.sha256" ]; then
  sha256sum -c "${file}.sha256"
fi
printf 'DANGER: this replaces the production database. Type RESTORE: '
read answer
[ "$answer" = 'RESTORE' ] || { echo 'Cancelled.'; exit 1; }
COMPOSE="docker compose --env-file .env.production -f docker-compose.prod.yml"
$COMPOSE stop app queue scheduler radius nginx backup
cleanup() { $COMPOSE up -d app radius queue scheduler nginx backup >/dev/null 2>&1 || true; }
trap cleanup EXIT INT TERM
cat "$file" | $COMPOSE exec -T postgres sh -lc 'PGPASSWORD=$(cat /run/secrets/db_password) pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists --no-owner --no-privileges'
$COMPOSE up -d app radius queue scheduler nginx backup
trap - EXIT INT TERM
echo 'Restore completed. Run application health checks immediately.'
