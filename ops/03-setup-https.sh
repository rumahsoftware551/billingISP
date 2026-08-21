#!/usr/bin/env bash
set -euo pipefail
[ "${EUID}" -eq 0 ] || { echo "Jalankan dengan sudo/root." >&2; exit 1; }

DOMAIN=""; EMAIL=""
while [ $# -gt 0 ]; do
  case "$1" in
    --domain) DOMAIN="${2:-}"; shift 2 ;;
    --email) EMAIL="${2:-}"; shift 2 ;;
    *) echo "Usage: $0 --domain billing.example.com --email admin@example.com" >&2; exit 1 ;;
  esac
done
[ -n "$DOMAIN" ] && [ -n "$EMAIL" ] || { echo "--domain dan --email wajib." >&2; exit 1; }

CONF="/etc/nginx/sites-available/jaringanku.conf"
cat >"$CONF" <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};

    client_max_body_size 32m;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header X-Forwarded-Host \$host;
        proxy_set_header X-Forwarded-Port \$server_port;
        proxy_read_timeout 120s;
        proxy_send_timeout 120s;
    }
}
EOF
ln -sf "$CONF" /etc/nginx/sites-enabled/jaringanku.conf
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

# Certbot nginx plugin obtains, installs and redirects HTTP -> HTTPS.
certbot --nginx --non-interactive --agree-tos --redirect --email "$EMAIL" -d "$DOMAIN"
nginx -t
systemctl reload nginx

curl -fsS "https://${DOMAIN}/health/live" >/dev/null
curl -fsS "https://${DOMAIN}/version" >/dev/null

echo "[OK] HTTPS aktif: https://${DOMAIN}"
echo "Uji renewal: sudo certbot renew --dry-run"
