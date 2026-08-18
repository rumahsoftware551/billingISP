#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")/.."
COMPOSE="docker compose --env-file .env.production -f docker-compose.prod.yml"
./scripts/verify-release.sh

for f in .env.production secrets/app_key.txt secrets/db_password.txt secrets/radius_shared_secret.txt secrets/admin_password.txt secrets/health_token.txt; do
  [ -s "$f" ] || { echo "FAIL: missing $f" >&2; exit 1; }
done

$COMPOSE config --quiet
$COMPOSE ps
$COMPOSE exec -T app jaringanku-cli php artisan jaringanku:production-preflight
$COMPOSE exec -T app jaringanku-cli php artisan jaringanku:phase12-preflight
$COMPOSE exec -T app jaringanku-cli php artisan jaringanku:phase13-preflight
$COMPOSE exec -T app jaringanku-cli php artisan jaringanku:phase14-preflight
$COMPOSE exec -T app jaringanku-cli php artisan jaringanku:phase15-preflight
$COMPOSE exec -T app jaringanku-cli php artisan jaringanku:phase16-preflight
$COMPOSE exec -T app jaringanku-cli php artisan jaringanku:phase15-security-audit --strict
$COMPOSE exec -T app jaringanku-cli php artisan jaringanku:network-acceptance --strict
$COMPOSE exec -T app jaringanku-cli php artisan jaringanku:phase16-smoke
$COMPOSE exec -T app jaringanku-cli php artisan migrate:status --no-interaction >/dev/null
$COMPOSE exec -T postgres sh -lc 'PGPASSWORD="$POSTGRES_PASSWORD" pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --schema-only >/dev/null'

health_token=$(cat secrets/health_token.txt)
if command -v curl >/dev/null 2>&1; then
  app_port=$(sed -n 's/^APP_PORT=//p' .env.production | tail -n1)
  app_port=${app_port:-8080}
  app_bind=$(sed -n 's/^APP_BIND=//p' .env.production | tail -n1)
  app_bind=${app_bind:-127.0.0.1}
  curl -fsS -H "X-Health-Token: $health_token" "http://${app_bind}:${app_port}/health/ready" >/dev/null || {
    echo "WARNING: local readiness endpoint was not reachable. Check reverse proxy/bind configuration." >&2
  }
fi

echo "JARINGANKU v1.3.0-rc1 PHASE 16 PRODUCTION PRE-RELEASE CHECK PASSED"
