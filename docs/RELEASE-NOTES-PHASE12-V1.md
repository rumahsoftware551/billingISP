# Jaringanku Phase 12 — SaaS & Production Final

Phase 12 menutup roadmap utama Jaringanku v1.0.0 dan membawa control-plane SaaS serta release gate production.

## Fitur baru

- Platform Super Admin di `/platform`.
- Paket SaaS STARTER, GROWTH, PRO, ENTERPRISE.
- Subscription lifecycle: trialing, active, past_due, suspended, canceled.
- Kuota tenant untuk customer, service, router, dan user.
- Tenant provisioning dengan owner + trial 14 hari.
- Platform event log dan release history.
- Endpoint versi publik minimal di `/version`.
- `jaringanku:production-preflight` untuk fail-fast production checks.
- `jaringanku:phase12-preflight` dan `jaringanku:phase12-smoke`.
- Release record otomatis setelah deployment berhasil.
- `prod-final-check.sh` untuk final release verification.

## Prinsip upgrade

Phase 12 memakai named volume PostgreSQL/Redis/storage yang sama. Jangan menjalankan `docker compose down -v` saat upgrade.
