#!/usr/bin/env bash
set -euo pipefail

if [ "${EUID}" -ne 0 ]; then
  echo "Jalankan sebagai root: sudo bash $0" >&2
  exit 1
fi

if [ ! -r /etc/os-release ]; then
  echo "Tidak dapat mendeteksi OS." >&2
  exit 1
fi
. /etc/os-release
if [ "${ID:-}" != "ubuntu" ]; then
  echo "Script ini ditujukan untuk Ubuntu Server. OS terdeteksi: ${ID:-unknown}" >&2
  exit 1
fi
case "${VERSION_CODENAME:-}" in
  noble|resolute) ;;
  *) echo "PERINGATAN: baseline diuji statis untuk Ubuntu 24.04/26.04. Codename: ${VERSION_CODENAME:-unknown}" >&2 ;;
esac

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y ca-certificates curl gnupg openssl jq unzip nginx snapd python3 iptables

# Docker official apt repository.
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc
cat >/etc/apt/sources.list.d/docker.sources <<EOF
Types: deb
URIs: https://download.docker.com/linux/ubuntu
Suites: ${VERSION_CODENAME}
Components: stable
Architectures: $(dpkg --print-architecture)
Signed-By: /etc/apt/keyrings/docker.asc
EOF
apt-get update
apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
systemctl enable --now docker

# Host nginx reverse proxy.
systemctl enable --now nginx

# Certbot official recommended snap distribution.
systemctl enable --now snapd.socket || true
snap install core >/dev/null 2>&1 || true
snap refresh core >/dev/null 2>&1 || true
if ! snap list certbot >/dev/null 2>&1; then
  snap install --classic certbot
fi
ln -sf /snap/bin/certbot /usr/local/bin/certbot

# Basic host hardening that does not interfere with Docker networking.
install -d -m 0755 /etc/sysctl.d
cat >/etc/sysctl.d/90-jaringanku.conf <<'EOF'
net.ipv4.tcp_syncookies=1
net.ipv4.conf.all.rp_filter=1
net.ipv4.conf.default.rp_filter=1
net.ipv4.conf.all.accept_redirects=0
net.ipv4.conf.default.accept_redirects=0
net.ipv4.conf.all.send_redirects=0
net.ipv4.conf.default.send_redirects=0
EOF
sysctl --system >/dev/null

echo ""
echo "[OK] Bootstrap Ubuntu selesai"
docker --version
docker compose version
nginx -v
echo "Certbot: $(certbot --version 2>/dev/null || true)"
echo ""
echo "Lanjutkan dari folder Jaringanku dengan:"
echo "  sudo ./ops/01-prepare-production.sh --domain billing.example.com --admin-email admin@example.com --nas-cidr 203.0.113.10/32"
