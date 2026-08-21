#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

DOMAIN=""
ADMIN_EMAIL=""
NAS_CIDR="disabled"
ROUTER_CIDRS="disabled"
TENANT_NAME="Jaringanku ISP"
TENANT_SLUG="jaringanku-isp"

usage() {
  cat <<'USAGE'
Usage:
  sudo ./ops/01-prepare-production.sh \
    --domain billing.example.com \
    --admin-email admin@example.com \
    [--nas-cidr 203.0.113.10/32] \
    [--router-cidrs 10.88.0.0/24] \
    [--tenant-name "Nama ISP"] \
    [--tenant-slug nama-isp]

Catatan:
- Masukkan hostname saja tanpa https://.
- --nas-cidr harus IP/CIDR MikroTik/NAS yang dipercaya. Bila melalui VPN,
  gunakan IP/CIDR private VPN. Jika belum ada, gunakan disabled untuk pilot
  web saja dan JANGAN buka UDP 1812/1813 ke seluruh internet.
- --router-cidrs adalah IP/CIDR management RouterOS v7 yang boleh diakses
  aplikasi melalui REST HTTPS. Gunakan disabled bila seluruh router masih v6.
USAGE
}

while [ $# -gt 0 ]; do
  case "$1" in
    --domain) DOMAIN="${2:-}"; shift 2 ;;
    --admin-email) ADMIN_EMAIL="${2:-}"; shift 2 ;;
    --nas-cidr) NAS_CIDR="${2:-}"; shift 2 ;;
    --router-cidrs) ROUTER_CIDRS="${2:-}"; shift 2 ;;
    --tenant-name) TENANT_NAME="${2:-}"; shift 2 ;;
    --tenant-slug) TENANT_SLUG="${2:-}"; shift 2 ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Argumen tidak dikenal: $1" >&2; usage; exit 1 ;;
  esac
done

[ -n "$DOMAIN" ] || { echo "--domain wajib." >&2; exit 1; }
[ -n "$ADMIN_EMAIL" ] || { echo "--admin-email wajib." >&2; exit 1; }
case "$DOMAIN" in
  http://*|https://*|*/*|*:* ) echo "Masukkan hostname saja tanpa protokol/port: $DOMAIN" >&2; exit 1 ;;
esac
case "$ADMIN_EMAIL" in
  *@*.*) ;;
  *) echo "Format email tidak valid: $ADMIN_EMAIL" >&2; exit 1 ;;
esac
case "$TENANT_SLUG" in
  *[!a-z0-9-]*|'') echo "tenant slug hanya boleh a-z, 0-9, dan hyphen." >&2; exit 1 ;;
esac

./scripts/prod-init.sh

python3 - "$DOMAIN" "$ADMIN_EMAIL" "$NAS_CIDR" "$ROUTER_CIDRS" "$TENANT_NAME" "$TENANT_SLUG" <<'PY'
from pathlib import Path
import sys
path=Path('.env.production')
domain,email,nas,router_cidrs,tenant_name,tenant_slug=sys.argv[1:]
updates={
 'APP_NAME':'Jaringanku',
 'APP_ENV':'production',
 'APP_DEBUG':'false',
 'APP_URL':f'https://{domain}',
 'APP_BIND':'127.0.0.1',
 'APP_PORT':'8080',
 'APP_TIMEZONE':'Asia/Jakarta',
 'LOG_LEVEL':'info',
 'SESSION_ENCRYPT':'true',
 'SESSION_SECURE_COOKIE':'true',
 'SESSION_SAME_SITE':'lax',
 'FORCE_HTTPS':'true',
 'TRUSTED_PROXIES':'*',
 'SEED_ADMIN_EMAIL':email,
 'SEED_TENANT_NAME':tenant_name,
 'SEED_TENANT_SLUG':tenant_slug,
 'SEED_DEMO_DATA':'false',
 'RADIUS_CLIENT_NETWORK':nas,
 'MIKROTIK_ALLOWED_CIDRS':router_cidrs,
 'MIKROTIK_VERIFY_TLS':'true',
 'MAIL_FROM_ADDRESS':f'no-reply@{domain}',
 'JARINGANKU_VERSION':'1.2.0-dev',
 'RELEASE_CHANNEL':'development',
 'PHASE4_ACCOUNTING_SMOKE':'false',
 'PHASE5_BILLING_SMOKE':'false',
 'PHASE6_AUTOMATION_SMOKE':'false',
 'PHASE7_REPORTS_SMOKE':'false',
 'PHASE8_PRODUCTION_SMOKE':'false',
 'PHASE9_PAYMENT_SMOKE':'false',
 'PHASE10_PORTAL_SMOKE':'false',
 'PHASE11_OPERATIONS_SMOKE':'false',
 'PHASE12_FINAL_SMOKE':'false',
 'PHASE13_PARTNER_SMOKE':'false',
 'PHASE14_INVENTORY_SMOKE':'false',
 'PHASE15_FINAL_SMOKE':'false',
 'PHASE16_COMMERCIAL_SMOKE':'true',
}
lines=path.read_text().splitlines()
seen=set(); out=[]
for line in lines:
    if '=' in line and not line.lstrip().startswith('#'):
        key=line.split('=',1)[0]
        if key in updates:
            out.append(f'{key}={updates[key]}'); seen.add(key); continue
    out.append(line)
for key,value in updates.items():
    if key not in seen:
        out.append(f'{key}={value}')
path.write_text('\n'.join(out)+'\n')
PY
chmod 600 .env.production secrets/*.txt

if grep -Eq '^APP_URL=https://.*example\.com|^SEED_ADMIN_EMAIL=.*@example\.com' .env.production; then
  echo "Masih ada placeholder production pada .env.production" >&2
  exit 1
fi

echo "[OK] Konfigurasi VPS pilot production disiapkan"
echo "Domain      : $DOMAIN"
echo "Admin email : $ADMIN_EMAIL"
echo "Tenant      : $TENANT_NAME ($TENANT_SLUG)"
echo "NAS CIDR    : $NAS_CIDR"
echo "Router CIDR : $ROUTER_CIDRS"
echo "Release     : Jaringanku 1.2.0-dev Phase 16 FULL V3"
echo ""
echo "Secret admin tersimpan di secrets/admin_password.txt (chmod 600)."
echo "Lanjutkan: sudo ./ops/02-deploy-app.sh"
