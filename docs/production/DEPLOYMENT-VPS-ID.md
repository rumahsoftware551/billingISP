# Panduan Deploy VPS — Jaringanku Phase 16 FULL V3

## Baseline

Disarankan Ubuntu Server 24.04 LTS atau 26.04 LTS, public IPv4, minimal 2 vCPU / 4 GB RAM / 40 GB SSD untuk pilot, domain/subdomain, dan akses root/sudo.

## Sebelum upload ZIP

1. Buat DNS A record, contoh `billing.ispanda.id -> IP_VPS`.
2. Catat IP publik atau IP VPN MikroTik/NAS.
3. Pastikan port TCP 80/443 dapat diakses dari Internet.
4. Jangan membuka PostgreSQL 5432 atau Redis 6379.
5. RADIUS UDP 1812/1813 hanya boleh dari sumber NAS terpercaya.

## Instalasi

```bash
sudo mkdir -p /opt/jaringanku
cd /opt/jaringanku
sudo unzip Jaringanku-v1.2.0-Phase16-GoLive-Pilot.zip
cd Jaringanku-v1.2.0-Phase16-GoLive-Pilot
sudo ./ops/00-bootstrap-ubuntu.sh
sudo ./ops/01-prepare-production.sh --domain billing.ispanda.id --admin-email admin@ispanda.id --nas-cidr 203.0.113.10/32 --tenant-name "ISP Anda" --tenant-slug isp-anda
sudo ./ops/02-deploy-app.sh
sudo ./ops/03-setup-https.sh --domain billing.ispanda.id --email admin@ispanda.id
sudo ./ops/04-radius-firewall.sh --allow '203.0.113.10/32'
sudo ./ops/05-final-acceptance.sh --domain billing.ispanda.id
sudo ./ops/06-show-credentials.sh
```

## Cek service

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml ps
```

Semua service utama harus running: `postgres`, `redis`, `app`, `radius`, `queue`, `scheduler`, `nginx`, dan `backup`.

## Cek aplikasi

```bash
curl -fsS https://billing.ispanda.id/health/live
curl -fsS https://billing.ispanda.id/version
```

Readiness memakai health token:

```bash
TOKEN=$(sudo cat secrets/health_token.txt)
curl -fsS -H "X-Health-Token: $TOKEN" https://billing.ispanda.id/health/ready
```

## Update berikutnya

Selalu backup database sebelum mengganti source/image:

```bash
sudo ./scripts/prod-backup.sh
```

Jangan menghapus named volume production.
