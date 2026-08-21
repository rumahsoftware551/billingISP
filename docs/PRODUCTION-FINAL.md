# Jaringanku v1.0 Production Final Checklist

Sebelum go-live:

1. Gunakan Linux server yang didukung Docker Engine/Compose.
2. Jalankan `./scripts/prod-init.sh` satu kali.
3. Isi `.env.production` dengan hostname HTTPS nyata, email admin, trusted proxy, serta CIDR/IP MikroTik.
4. Simpan `secrets/*.txt` dengan permission 0600 dan backup offline terenkripsi.
5. Pastikan `SEED_DEMO_DATA=false`, `APP_DEBUG=false`, `FORCE_HTTPS=true`, dan secure session aktif.
6. Deploy pertama: jalankan `BOOTSTRAP_PRODUCTION=true ./scripts/prod-up.sh`. Update berikutnya: `./scripts/prod-up.sh`.
7. Jalankan `./scripts/prod-final-check.sh`.
8. Verifikasi RADIUS authentication/accounting dari MikroTik nyata.
9. Verifikasi satu invoice, pembayaran sandbox/production, notifikasi, dan Customer Portal.
10. Jalankan backup manual database + `storage/app`, verifikasi SHA-256, lalu lakukan restore drill pada environment non-production sebelum menerima traffic production.

## Rollback aplikasi

Rollback source/image tidak boleh otomatis rollback schema destruktif. Ambil backup sebelum migration. Untuk masalah setelah release, kembalikan image/source sebelumnya yang kompatibel dengan schema terbaru atau lakukan restore database hanya dalam maintenance window setelah validasi checksum.

## Restore

Gunakan script restore yang sudah tersedia dan lakukan restore drill di environment non-production. Jangan menjalankan restore otomatis pada database aktif.

Setelah subscription platform digunakan, jalankan `php artisan jaringanku:saas-sweep` melalui scheduler (sudah terjadwal hourly) untuk memajukan status trial/past-due secara otomatis.
