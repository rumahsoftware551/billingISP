# Jaringanku Phase 16 FULL V3 — VPS Go-Live Pilot Bundle

Paket ini adalah source kumulatif Jaringanku Phase 16 FULL V3 yang telah berhasil dijalankan secara lokal oleh pengguna, ditambah tooling deploy VPS, HTTPS, firewall RADIUS, backup/restore, dan manual book.

> Status release aplikasi tetap `1.2.0-dev / development`. Paket ini ditujukan untuk **VPS staging/pilot production dan acceptance**, bukan untuk langsung mass onboarding tanpa pengujian MikroTik/RADIUS nyata.

## Isi penting

- `docker-compose.prod.yml` — stack production VPS.
- `.env.production.example` — contoh environment tanpa secret nyata.
- `ops/00-bootstrap-ubuntu.sh` — instal Docker Engine, Compose, Nginx host, Certbot.
- `ops/01-prepare-production.sh` — membuat `.env.production` dan secret.
- `ops/02-deploy-app.sh` — build/start aplikasi dan menjalankan preflight/audit.
- `ops/03-setup-https.sh` — reverse proxy + Let's Encrypt.
- `ops/04-radius-firewall.sh` — whitelist UDP 1812/1813 melalui `DOCKER-USER`.
- `ops/05-final-acceptance.sh` — acceptance VPS + HTTPS + backup.
- `ops/06-show-credentials.sh` — tampilkan credential yang dihasilkan.
- `scripts/prod-backup.sh` / `scripts/prod-restore.sh` — backup dan restore database.
- `docs/manual/Jaringanku-Manual-Book-Phase16-FULL-V3.pdf` — manual pengguna.

## Urutan deploy

Pastikan DNS A record domain sudah mengarah ke public IP VPS, lalu di VPS:

```bash
sudo ./ops/00-bootstrap-ubuntu.sh
sudo ./ops/01-prepare-production.sh \
  --domain billing.domainanda.com \
  --admin-email admin@domainanda.com \
  --nas-cidr 203.0.113.10/32 \
  --tenant-name "Nama ISP" \
  --tenant-slug nama-isp
sudo ./ops/02-deploy-app.sh
sudo ./ops/03-setup-https.sh --domain billing.domainanda.com --email admin@domainanda.com
sudo ./ops/04-radius-firewall.sh --allow '203.0.113.10/32'
sudo ./ops/05-final-acceptance.sh --domain billing.domainanda.com
sudo ./ops/06-show-credentials.sh
```

Jika belum mempunyai IP MikroTik/NAS yang pasti, jangan menjalankan firewall RADIUS dengan `0.0.0.0/0`. Lebih aman deploy web/HTTPS dahulu, lalu whitelist IP/VPN NAS setelah tersedia.

## URL setelah deploy

- Admin ISP: `https://domain/login`
- Access Center: `https://domain/access`
- Customer: `https://domain/portal/<tenant-slug>/login`
- Mitra: `https://domain/mitra/<tenant-slug>/login`
- Inventory: `https://domain/inventory/<tenant-slug>/login`

## Backup

```bash
sudo ./scripts/prod-backup.sh
```

Restore bersifat destruktif dan meminta konfirmasi `RESTORE`:

```bash
sudo ./scripts/prod-restore.sh backups/jaringanku-manual-YYYYMMDDTHHMMSSZ.dump
```

## Aturan keamanan wajib

- PostgreSQL dan Redis tidak dipublish ke Internet.
- Web container hanya bind `127.0.0.1:8080`; publik melalui Nginx host + HTTPS.
- UDP RADIUS 1812/1813 hanya untuk IP/CIDR NAS terpercaya.
- Jangan gunakan `docker compose down -v` pada production.
- Simpan `secrets/*.txt` di password manager dan jangan kirim screenshot credential.
- Untuk MikroTik di balik CGNAT, gunakan VPN/routable path untuk RADIUS dan CoA/Disconnect.

## Setelah acceptance VPS

Uji end-to-end minimal 3-10 pelanggan: RADIUS accept, accounting, invoice, payment, isolir, reactivate, portal customer, portal mitra, inventory, upload QRIS/logo, role/permission, backup, dan restore test non-production. Setelah seluruhnya PASS, release dapat dipromosikan menjadi stable pada fase acceptance berikutnya.
