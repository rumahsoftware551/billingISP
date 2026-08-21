#!/usr/bin/env sh
set -eu

cd "$(dirname "$0")/.."

for f in .env.production secrets/app_key.txt secrets/db_password.txt secrets/radius_shared_secret.txt; do
  [ -s "$f" ] || { echo "Missing required production file: $f" >&2; exit 1; }
done

COMPOSE="docker compose --env-file .env.production -f docker-compose.prod.yml"
$COMPOSE config --quiet
$COMPOSE up -d postgres redis

# Migrations are an explicit, single maintenance action. They never run merely
# because an app/queue/scheduler container is restarted.
$COMPOSE run --rm --no-deps migrate
