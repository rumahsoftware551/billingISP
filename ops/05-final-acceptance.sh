#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
[ "${EUID}" -eq 0 ] || { echo "Jalankan dengan sudo/root." >&2; exit 1; }

DOMAIN=""
while [ $# -gt 0 ]; do
  case "$1" in
    --domain) DOMAIN="${2:-}"; shift 2 ;;
    *) echo "Usage: $0 --domain billing.example.com" >&2; exit 1 ;;
  esac
done
[ -n "$DOMAIN" ] || { echo "--domain wajib." >&2; exit 1; }

./scripts/verify-release.sh
./scripts/prod-final-check.sh

COMPOSE=(docker compose --env-file .env.production -f docker-compose.prod.yml)
for svc in postgres redis app radius queue scheduler nginx backup; do
  id=$("${COMPOSE[@]}" ps -q "$svc")
  [ -n "$id" ] || { echo "FAIL: service $svc tidak memiliki container." >&2; exit 1; }
  state=$(docker inspect -f '{{.State.Status}}' "$id")
  [ "$state" = running ] || { echo "FAIL: $svc state=$state" >&2; exit 1; }
done

curl -fsS "https://${DOMAIN}/health/live" >/dev/null
curl -fsS "https://${DOMAIN}/version" | grep -q '1.3.0-rc1'
HEALTH_TOKEN=$(cat secrets/health_token.txt)
curl -fsS -H "X-Health-Token: ${HEALTH_TOKEN}" "https://${DOMAIN}/health/ready" >/dev/null

echo | openssl s_client -connect "${DOMAIN}:443" -servername "$DOMAIN" -verify_return_error 2>/dev/null | grep -q "Verify return code: 0 (ok)"

./scripts/prod-backup.sh
latest=$(ls -1t backups/jaringanku-manual-*.dump | head -n1)
sha256sum -c "${latest}.sha256"

printf '\n[OK] JARINGANKU PHASE 16 VPS PILOT ACCEPTANCE PASSED\n'
printf 'URL       : https://%s\n' "$DOMAIN"
printf 'Version   : 1.3.0-rc1 / release-candidate (V1.3 RC1)\n'
printf 'Backup    : %s\n' "$latest"
printf 'Next      : hubungkan MikroTik produksi dan uji 3-10 pelanggan pilot sebelum mass onboarding.\n'
