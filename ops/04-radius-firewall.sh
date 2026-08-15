#!/usr/bin/env bash
set -euo pipefail
[ "${EUID}" -eq 0 ] || { echo "Jalankan dengan sudo/root." >&2; exit 1; }

C=""
while [ $# -gt 0 ]; do
  case "$1" in
    --allow) C="${2:-}"; shift 2 ;;
    *) echo "Usage: $0 --allow '203.0.113.10/32,198.51.100.0/24'" >&2; exit 1 ;;
  esac
done
[ -n "$C" ] || { echo "--allow wajib. Jangan membuka RADIUS ke seluruh internet." >&2; exit 1; }
IFS=',' read -r -a CIDRS <<< "$C"

cat >/usr/local/sbin/jaringanku-radius-firewall <<'SCRIPT'
#!/usr/bin/env bash
set -euo pipefail
CIDRS_FILE=/etc/jaringanku-radius-cidrs
mapfile -t CIDRS < <(grep -Ev '^[[:space:]]*(#|$)' "$CIDRS_FILE")
iptables -N JARINGANKU-RADIUS 2>/dev/null || true
iptables -F JARINGANKU-RADIUS
for cidr in "${CIDRS[@]}"; do
  iptables -A JARINGANKU-RADIUS -s "$cidr" -p udp -m multiport --dports 1812,1813 -j ACCEPT
done
iptables -A JARINGANKU-RADIUS -p udp -m multiport --dports 1812,1813 -j DROP
iptables -C DOCKER-USER -p udp -m multiport --dports 1812,1813 -j JARINGANKU-RADIUS 2>/dev/null || \
  iptables -I DOCKER-USER 1 -p udp -m multiport --dports 1812,1813 -j JARINGANKU-RADIUS
SCRIPT
chmod 0755 /usr/local/sbin/jaringanku-radius-firewall

: >/etc/jaringanku-radius-cidrs
for cidr in "${CIDRS[@]}"; do
  cidr="${cidr//[[:space:]]/}"
  [ -n "$cidr" ] && echo "$cidr" >>/etc/jaringanku-radius-cidrs
done
chmod 600 /etc/jaringanku-radius-cidrs

cat >/etc/systemd/system/jaringanku-radius-firewall.service <<'UNIT'
[Unit]
Description=Jaringanku RADIUS Docker firewall
After=docker.service
Requires=docker.service

[Service]
Type=oneshot
ExecStart=/usr/local/sbin/jaringanku-radius-firewall
RemainAfterExit=yes

[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload
systemctl enable --now jaringanku-radius-firewall.service

iptables -S JARINGANKU-RADIUS

echo "[OK] UDP 1812/1813 dibatasi ke CIDR terpercaya."
echo "Tetap atur firewall/security-group provider VPS dengan aturan yang sama."
