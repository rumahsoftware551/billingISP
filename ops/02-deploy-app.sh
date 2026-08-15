#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
[ "${EUID}" -eq 0 ] || { echo "Jalankan dengan sudo/root." >&2; exit 1; }

./scripts/verify-release.sh
./scripts/prod-up.sh

COMPOSE=(docker compose --env-file .env.production -f docker-compose.prod.yml)
"${COMPOSE[@]}" ps

echo ""
echo "[OK] Container production Jaringanku berjalan."
echo "Aplikasi hanya bind ke 127.0.0.1:8080 sampai HTTPS reverse proxy diaktifkan."
echo "Lanjutkan: sudo ./ops/03-setup-https.sh --domain <domain> --email <email>"
