# Jaringanku Phase 16 FULL V2 — Regression Compatibility Hotfix

## Masalah V1
Phase 16 menetapkan versi kumulatif `1.2.0-dev` dan channel `development`, tetapi preflight/audit warisan Phase 15 masih memaksa metadata harus persis `1.1.0 / stable`. Akibatnya `scripts/local-up.ps1` berhenti pada `Phase 15 preflight gagal` meskipun container aplikasi, PostgreSQL, dan Redis sehat.

## Perbaikan V2
- Phase 15 preflight diperlakukan sebagai **baseline regression** pada release kumulatif.
- Versi `>= 1.1.0` diterima; `1.1.0` sendiri tetap wajib `stable`.
- Release yang lebih baru boleh memakai channel `development` atau `stable`.
- ReleaseAcceptanceService menggunakan aturan kompatibilitas yang sama sehingga Phase 15 security/smoke tidak gagal hanya karena Phase 16 memiliki versi lebih baru.
- Default release record helper dan fallback release channel diselaraskan dengan Phase 16.
- Tidak ada migrasi database baru pada hotfix ini. Named volumes dan data Phase sebelumnya tetap dipertahankan.

## Expected marker
`PHASE 15 BASELINE RELEASE PREFLIGHT PASSED` kemudian `PHASE 16 COMMERCIAL READINESS PREFLIGHT PASSED`.
