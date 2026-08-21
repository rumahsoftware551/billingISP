#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
[ "${EUID}" -eq 0 ] || { echo "Jalankan dengan sudo/root." >&2; exit 1; }
for f in secrets/admin_password.txt secrets/radius_shared_secret.txt secrets/health_token.txt; do
  [ -r "$f" ] || { echo "Missing $f" >&2; exit 1; }
done
printf 'Admin email       : %s\n' "$(sed -n 's/^SEED_ADMIN_EMAIL=//p' .env.production | tail -n1)"
printf 'Admin password    : %s\n' "$(cat secrets/admin_password.txt)"
printf 'RADIUS secret     : %s\n' "$(cat secrets/radius_shared_secret.txt)"
printf 'Health token      : %s\n' "$(cat secrets/health_token.txt)"
echo ""
echo "Simpan credential ini di password manager. Jangan kirim screenshot credential ke chat/group."
