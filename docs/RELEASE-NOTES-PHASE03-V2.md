# Jaringanku Phase 03 FULL V2

## Perbaikan utama

- Menghapus fixed Docker subnet `172.31.240.0/24` dari Compose. Docker sekarang memilih subnet bridge yang tidak overlap secara otomatis.
- FreeRADIUS tidak lagi membutuhkan `RADIUS_DOCKER_CLIENT_NETWORK`. Entry point me-resolve service `app` saat startup dan mendaftarkan IP container tersebut sebagai client `/32` (atau `/128` untuk IPv6).
- `local-up.ps1` membersihkan network Compose Phase 01/02 yang tertinggal setelah container lama dihentikan. Named volumes database/Redis/storage tidak dihapus.
- Upgrade tetap mempertahankan APP_KEY, database, RADIUS secret, dan named volumes Phase 01/02.

## Error yang diperbaiki

`invalid pool request: Pool overlaps with other one on this address space`

Error tersebut muncul ketika network Phase 02 lama masih memiliki subnet yang sama dengan network baru Phase 03.
