#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")/.."

for f in .env.production secrets/app_key.txt secrets/db_password.txt secrets/radius_shared_secret.txt secrets/admin_password.txt secrets/health_token.txt; do
  [ -s "$f" ] || { echo "Missing required production file: $f" >&2; exit 1; }
done

read_env() {
  key="$1"
  sed -n "s/^${key}=//p" .env.production | tail -n 1
}

app_env=$(read_env APP_ENV)
app_debug=$(read_env APP_DEBUG)
app_url=$(read_env APP_URL)
force_https=$(read_env FORCE_HTTPS)
secure_cookie=$(read_env SESSION_SECURE_COOKIE)
radius_client=$(read_env RADIUS_CLIENT_NETWORK)
router_cidrs=$(read_env MIKROTIK_ALLOWED_CIDRS)
admin_email=$(read_env SEED_ADMIN_EMAIL)

[ "$app_env" = "production" ] || { echo 'APP_ENV must be production.' >&2; exit 1; }
[ "$app_debug" = "false" ] || { echo 'APP_DEBUG must be false in production.' >&2; exit 1; }
case "$app_url" in
  https://*) ;;
  *) echo 'APP_URL must use https:// in production.' >&2; exit 1 ;;
esac
case "$app_url" in
  *example.com*) echo 'APP_URL still uses example.com. Set the real production hostname.' >&2; exit 1 ;;
esac
[ "$force_https" = "true" ] || { echo 'FORCE_HTTPS must be true in production.' >&2; exit 1; }
[ "$secure_cookie" = "true" ] || { echo 'SESSION_SECURE_COOKIE must be true in production.' >&2; exit 1; }

case "$radius_client" in
  ''|disabled|127.0.0.1|127.0.0.1/32|CHANGE_ME*)
    echo 'WARNING: RADIUS_CLIENT_NETWORK is not configured for a real NAS/MikroTik yet.' >&2
    ;;
esac
case "$router_cidrs" in
  ''|CHANGE_ME*) echo 'MIKROTIK_ALLOWED_CIDRS must contain the exact MikroTik IP/CIDR allowed for REST access.' >&2; exit 1 ;;
esac
case "$admin_email" in
  *@example.com|'') echo 'WARNING: SEED_ADMIN_EMAIL still looks like a placeholder.' >&2 ;;
esac

COMPOSE="docker compose --env-file .env.production -f docker-compose.prod.yml"
./scripts/verify-release.sh
$COMPOSE config --quiet
$COMPOSE build app nginx radius backup
$COMPOSE up -d postgres redis
$COMPOSE run --rm --no-deps -e RUN_MIGRATIONS=false app jaringanku-cli php artisan migrate --force
$COMPOSE up -d app radius queue scheduler nginx backup
if [ "${BOOTSTRAP_PRODUCTION:-false}" = "true" ]; then
  $COMPOSE exec -T app jaringanku-cli php artisan db:seed --force
fi
$COMPOSE exec -T app jaringanku-cli php artisan jaringanku:production-preflight
$COMPOSE exec -T app jaringanku-cli php artisan jaringanku:phase12-preflight
$COMPOSE exec -T app jaringanku-cli php artisan jaringanku:phase13-preflight
$COMPOSE exec -T app jaringanku-cli php artisan jaringanku:phase14-preflight
$COMPOSE exec -T app jaringanku-cli php artisan jaringanku:phase15-preflight
$COMPOSE exec -T app jaringanku-cli php artisan jaringanku:phase16-preflight
$COMPOSE exec -T app jaringanku-cli php artisan jaringanku:phase15-security-audit --strict
$COMPOSE exec -T app jaringanku-cli php artisan jaringanku:phase16-smoke
$COMPOSE exec -T app jaringanku-cli php artisan optimize
$COMPOSE exec -T app jaringanku-cli php artisan queue:restart || true
$COMPOSE exec -T app jaringanku-cli php artisan jaringanku:release-record --version=1.2.0-dev --notes="Jaringanku Phase 16 development production pre-release deploy"
$COMPOSE ps

echo "Production stack started. Verify reverse proxy/TLS and run:"
echo "  $COMPOSE exec -T app jaringanku-cli php artisan jaringanku:health"
