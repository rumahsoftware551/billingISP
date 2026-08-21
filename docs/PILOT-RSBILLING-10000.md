# Pilot RSBilling untuk RT/RW Net

Dokumen ini adalah runbook awal untuk `rsbilling.rumahsoftware.site` pada Ubuntu 24.04, database baru, PPPoE + Hotspot voucher, dan target data 10.000 pelanggan.

## Status kapasitas

- Paket PRO aplikasi membatasi sampai 10.000 pelanggan dan 12.000 layanan.
- RAM 4 GB dapat dipakai untuk pilot terkendali, tetapi belum menjadi bukti bahwa 10.000 pelanggan aktif bersamaan aman pada satu VPS.
- Sebelum migrasi penuh, lakukan uji beban dengan jumlah sesi RADIUS, invoice bulanan, transaksi pembayaran, vCPU, dan disk yang sebenarnya.
- Jalankan PostgreSQL, Redis, aplikasi, queue, scheduler, FreeRADIUS, Nginx, dan backup dari `docker-compose.prod.yml`.

## Data yang masih wajib diisi

Jangan mengirim password, API key, atau shared secret melalui chat atau issue GitHub.

| Data | Contoh aman | Kegunaan |
|---|---|---|
| Email admin | `admin@domain-anda.tld` | akun bootstrap |
| Nama dan slug usaha | `Rumah Software Net`, `rumah-software-net` | identitas tenant |
| NAS IP/CIDR | `10.88.0.10/32` | membatasi UDP RADIUS 1812/1813 |
| Router v7 management CIDR | `10.88.0.0/24` atau `disabled` | allowlist REST HTTPS |
| vCPU dan disk VPS | `2 vCPU`, `80 GB NVMe` | kapasitas dan retensi backup |
| Arti tanggal 10 | terbit invoice atau jatuh tempo | aturan billing |

## Persiapan DNS dan firewall

1. Buat DNS A/AAAA `rsbilling.rumahsoftware.site` menuju VPS.
2. Publikasikan TCP 80/443 melalui reverse proxy dengan TLS valid.
3. Izinkan UDP 1812/1813 hanya dari IP/CIDR NAS atau tunnel VPN. Jangan membuka RADIUS ke semua alamat Internet.
4. Izinkan koneksi dari aplikasi menuju TCP 443 router v7 hanya pada management CIDR yang ditentukan.
5. Bila Disconnect/CoA digunakan, izinkan UDP 3799 dari VPS menuju NAS.

## Deploy pertama

Jalankan dari checkout release di VPS setelah Docker Engine dan plugin Compose tersedia:

```bash
sudo ./ops/00-bootstrap-ubuntu.sh
sudo ./ops/01-prepare-production.sh \
  --domain rsbilling.rumahsoftware.site \
  --admin-email ADMIN_EMAIL \
  --nas-cidr NAS_IP_OR_CIDR \
  --router-cidrs ROUTER_V7_MANAGEMENT_CIDR_OR_DISABLED \
  --tenant-name "NAMA USAHA" \
  --tenant-slug nama-usaha
sudo ./ops/02-deploy-app.sh
sudo ./ops/03-setup-https.sh \
  --domain rsbilling.rumahsoftware.site \
  --email ADMIN_EMAIL
sudo ./ops/04-radius-firewall.sh --allow 'NAS_IP_OR_CIDR'
sudo ./ops/05-final-acceptance.sh --domain rsbilling.rumahsoftware.site
```

Ambil password bootstrap hanya dari `secrets/admin_password.txt` di VPS. Setelah login pertama, ganti password dan simpan di password manager.

## MikroTik RouterOS v6 dan v7

RouterOS v6 tidak menyediakan REST API. Gunakan RADIUS untuk PPPoE dan Hotspot. RouterOS v7.1 atau lebih baru dapat memakai REST HTTPS untuk fungsi manajemen tambahan; RADIUS tetap menjadi jalur autentikasi dan accounting.

Sesuaikan placeholder berikut di setiap NAS:

```routeros
/radius add service=ppp,hotspot address=RADIUS_VPS_OR_VPN_IP authentication-port=1812 accounting-port=1813 secret=RADIUS_SHARED_SECRET src-address=NAS_SOURCE_IP
/ppp aaa set use-radius=yes accounting=yes interim-update=5m
/ip hotspot profile set [find] use-radius=yes radius-accounting=yes radius-interim-update=5m
/radius incoming set accept=yes port=3799
```

Gunakan shared secret panjang dan unik dari file secret VPS, bukan contoh pada dokumen ini. Pastikan `NAS_SOURCE_IP` termasuk dalam `RADIUS_CLIENT_NETWORK`.

## Alur voucher Hotspot

1. Buat profil voucher: harga, masa aktif sejak login pertama, batas sesi, idle timeout, jumlah login bersamaan, dan rate limit.
2. Generate maksimal 1.000 voucher per batch.
3. Unduh CSV dan lindungi karena berisi username/password. Password disimpan terenkripsi di database aplikasi.
4. Saat menerima cash, transfer, atau QRIS, tandai voucher sebagai terjual. Voucher baru diproyeksikan ke FreeRADIUS setelah penjualan.
5. Accounting-Start pertama mengaktifkan masa berlaku. Scheduler merekonsiliasi aktivasi dan expiry setiap menit.
6. Voucher disabled atau expired diproyeksikan sebagai `Auth-Type := Reject`.

## Billing dan pembayaran

- Sementara `due_day=10` dipakai sebagai asumsi jatuh tempo. Konfirmasi diperlukan bila tanggal 10 sebenarnya tanggal penerbitan invoice.
- Cash dan transfer dicatat manual dengan referensi transaksi.
- QRIS dapat dicatat manual atau menggunakan Midtrans production. Masukkan server/client key hanya melalui halaman pengaturan terenkripsi, bukan source code.
- Jalankan satu siklus uji dengan pelanggan internal: invoice, pembayaran, suspend/restore, portal pelanggan, PPPoE, voucher Hotspot, backup, dan restore.

## Kriteria siap operasional pilot

- CI release hijau dan checksum release lolos.
- DNS, TLS, queue, scheduler, PostgreSQL, Redis, FreeRADIUS, serta backup sehat.
- Uji PPPoE dan Hotspot menghasilkan Accounting-Start/Interim/Stop di `radacct`.
- Satu pembayaran cash, transfer, dan QRIS diuji tanpa duplikasi.
- Backup database + storage dibuat dan satu restore drill berhasil.
- Monitoring disk, RAM, queue, error aplikasi, sesi RADIUS, dan sertifikat TLS aktif.
