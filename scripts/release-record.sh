#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")/.."
COMPOSE="docker compose --env-file .env.production -f docker-compose.prod.yml"
version="${1:-1.2.0-dev}"
commit="${RELEASE_GIT_COMMIT:-}"
$COMPOSE exec -T app jaringanku-cli php artisan jaringanku:release-record --version="$version" --commit="$commit" --notes="Manual release record"
