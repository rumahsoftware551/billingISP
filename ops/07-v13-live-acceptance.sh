#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

DOMAIN=""
TENANT=""
PPPOE_USER=""
PPPOE_PASSWORD=""

while [ $# -gt 0 ]; do
  case "$1" in
    --domain) DOMAIN="${2:-}"; shift 2 ;;
    --tenant) TENANT="${2:-}"; shift 2 ;;
    --pppoe-user) PPPOE_USER="${2:-}"; shift 2 ;;
    --pppoe-password) PPPOE_PASSWORD="${2:-}"; shift 2 ;;
    *) echo "Usage: $0 --domain billing.example.com --tenant tenant-slug --pppoe-user user --pppoe-password pass" >&2; exit 1 ;;
  esac
done

[ -n "$DOMAIN" ] || { echo "--domain wajib" >&2; exit 1; }
[ -n "$TENANT" ] || { echo "--tenant wajib" >&2; exit 1; }
[ -n "$PPPOE_USER" ] || { echo "--pppoe-user wajib (gunakan akun acceptance/pilot, bukan password admin)" >&2; exit 1; }
[ -n "$PPPOE_PASSWORD" ] || { echo "--pppoe-password wajib" >&2; exit 1; }

COMPOSE=(docker compose --env-file .env.production -f docker-compose.prod.yml)

echo "[1/7] Release + production gates"
./scripts/release-candidate-check.sh
./scripts/prod-final-check.sh

echo "[2/7] Live MikroTik projection/reachability"
"${COMPOSE[@]}" exec -T app jaringanku-cli php artisan jaringanku:network-acceptance --tenant="$TENANT" --strict --live

echo "[3/7] Real RADIUS authentication packet"
RADIUS_SECRET="$(cat secrets/radius_shared_secret.txt)"
"${COMPOSE[@]}" exec -T app radtest "$PPPOE_USER" "$PPPOE_PASSWORD" radius 0 "$RADIUS_SECRET" | tee /tmp/jaringanku-radius-acceptance.log
grep -q 'Access-Accept' /tmp/jaringanku-radius-acceptance.log || {
  echo "FAIL: RADIUS did not return Access-Accept" >&2
  exit 1
}

echo "[4/7] HTTPS/live version"
curl -fsS "https://${DOMAIN}/health/live" >/dev/null
curl -fsS "https://${DOMAIN}/version" | grep -q '1.3.0-rc2'

echo "[5/7] Readiness endpoint"
HEALTH_TOKEN="$(cat secrets/health_token.txt)"
curl -fsS -H "X-Health-Token: ${HEALTH_TOKEN}" "https://${DOMAIN}/health/ready" >/dev/null

echo "[6/7] Backup integrity"
./scripts/prod-backup.sh
latest="$(ls -1t backups/jaringanku-manual-*.dump | head -n1)"
sha256sum -c "${latest}.sha256"

echo "[7/7] TLS certificate"
echo | openssl s_client -connect "${DOMAIN}:443" -servername "$DOMAIN" -verify_return_error 2>/dev/null | grep -q "Verify return code: 0 (ok)"

echo
echo "===================================================="
echo "JARINGANKU V1.3 RC1 LIVE ACCEPTANCE PASSED"
echo "Domain : https://${DOMAIN}"
echo "Tenant : ${TENANT}"
echo "Backup : ${latest}"
echo "NEXT   : lakukan suspend -> disconnect -> payment -> reactivate pada akun pilot di UI/NOC, lalu promote stable."
echo "===================================================="