# MikroTik Local Test — Jaringanku Phase 03

Phase 03 meneruskan dua integrasi Network Core dari Phase 02:

1. **RADIUS** untuk autentikasi/accounting PPP/PPPoE.
2. **RouterOS REST API** untuk health check dan informasi router.

## A. Siapkan NAS di Jaringanku

1. Login ke `http://localhost:8080`.
2. Buka **Network**.
3. Pada bagian **NAS / RADIUS Clients**, isi:
   - NAS IP: IP MikroTik yang menjadi sumber request RADIUS, contoh `192.168.1.1`
   - Shortname: mis. `router-utama`
   - Type: `mikrotik`
   - Shared secret: buat secret kuat dan samakan dengan RouterOS.
4. Klik **Tambah & Sync NAS**.
5. Jalankan di PowerShell:

```powershell
docker compose restart radius
```

FreeRADIUS 3.2 memuat SQL clients ketika server startup, jadi restart diperlukan setelah NAS baru ditambahkan.

## B. Arahkan MikroTik ke PC yang menjalankan Docker Desktop

Cari IP LAN Windows, bukan `127.0.0.1`:

```powershell
ipconfig
```

Misalnya PC Anda `192.168.1.10`. Di terminal RouterOS:

```routeros
/radius add service=ppp address=192.168.1.10 authentication-port=1812 accounting-port=1813 secret=GANTI_DENGAN_SECRET_NAS
/ppp aaa set use-radius=yes accounting=yes interim-update=5m
```

Pastikan UDP 1812 dan 1813 dari MikroTik dapat mencapai PC Windows. Jika Windows Firewall meminta izin untuk Docker Desktop, izinkan pada jaringan Private yang Anda percayai.

> RouterOS akan menggunakan user lokal jika ada record lokal yang cocok. Untuk pengujian RADIUS, gunakan username yang tidak ada di `/ppp secret` lokal.

## C. Test user Customer Service Phase 03

Seeder menyediakan:

- Username: `phase3-demo`
- Password: `Phase3Demo123!`

User ini hanya untuk verifikasi FreeRADIUS SQL. Mulai Phase 03, customer service + PPPoE credential dikelola langsung oleh Jaringanku.

## D. RouterOS REST API

Untuk test tombol **Test** pada daftar Router:

- RouterOS harus menyediakan REST API.
- Jaringanku menggunakan HTTPS ke `/rest/system/resource`.
- Gunakan akun RouterOS khusus integrasi; jangan gunakan akun admin utama untuk production.
- Untuk local dengan sertifikat self-signed, `Verify TLS` dapat dimatikan sementara.
- Untuk production, aktifkan `www-ssl`, gunakan sertifikat valid, batasi service hanya dari IP server Jaringanku, dan aktifkan Verify TLS.

Di RouterOS, REST tersedia melalui service `www-ssl`. Contoh konsep pembatasan service:

```routeros
/ip service set www-ssl disabled=no port=443 address=192.168.1.10/32
```

Sesuaikan IP server Jaringanku dengan jaringan Anda.

## E. Diagnosis

Test RADIUS internal Docker:

```powershell
.\scripts\radius-test.ps1
```

Debug FreeRADIUS interaktif:

```powershell
.\scripts\radius-debug.ps1
```

Log normal:

```powershell
docker compose logs --tail=150 radius
```

Status semua service:

```powershell
.\scripts\local-status.ps1
```
