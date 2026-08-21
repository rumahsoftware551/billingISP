#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")/.."

[ -f .env.production ] || cp .env.production.example .env.production
mkdir -p secrets backups
umask 077

if [ ! -f secrets/app_key.txt ]; then
  key=$(php -r '$b=random_bytes(32); echo "base64:".base64_encode($b);' 2>/dev/null || true)
  if [ -z "$key" ]; then
    key="base64:$(openssl rand -base64 32)"
  fi
  printf '%s\n' "$key" > secrets/app_key.txt
fi
[ -f secrets/db_password.txt ] || openssl rand -base64 36 | tr -d '\n' > secrets/db_password.txt
[ -f secrets/radius_shared_secret.txt ] || openssl rand -base64 48 | tr -d '\n' > secrets/radius_shared_secret.txt
[ -f secrets/admin_password.txt ] || openssl rand -base64 24 | tr -d '\n' > secrets/admin_password.txt
[ -f secrets/health_token.txt ] || openssl rand -base64 36 | tr -d '\n' > secrets/health_token.txt
chmod 600 secrets/*.txt

echo "Production files initialized."
echo "1) Edit .env.production (APP_URL, TRUSTED_PROXIES, RADIUS_CLIENT_NETWORK, mail)."
echo "2) Keep secrets/*.txt chmod 600 and never commit them."
echo "3) First deploy: BOOTSTRAP_PRODUCTION=true ./scripts/prod-up.sh"
echo "4) Later updates: ./scripts/prod-up.sh"
