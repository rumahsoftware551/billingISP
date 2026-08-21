# Local Windows — Jaringanku Phase 16 FULL V2

1. Extract ZIP ke `C:\Jaringanku\phase-16-commercial-readiness`.
2. Buka PowerShell pada folder tersebut.
3. Jalankan:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\local-up.ps1
```

Named volume Phase sebelumnya dipertahankan. Jangan menjalankan `docker compose down -v`.

Setelah startup berhasil, buka `http://localhost:8080/access`. Dari sini pilih Admin ISP, Customer Portal, Portal Mitra, atau Portal Inventory.

Akun demo local (`SEED_DEMO_DATA=true`):

- Admin: `admin@jaringanku.local` / `Jaringanku123!`
- Customer: `demo@jaringanku.local` / `PortalDemo123!`
- Mitra: `mitra@jaringanku.local` / `MitraDemo123!`
- Inventory: `inventory@jaringanku.local` / `InventoryDemo123!`

Acceptance manual:

```powershell
.\scripts\phase16-smoke.ps1
.\scripts\final-regression.ps1
```

Target banner:

```text
JARINGANKU PHASE 16 SIAP · v1.2.0 DEVELOPMENT
```


## Jika sebelumnya Phase 16 V1 berhenti di Phase 15 preflight
Gunakan FULL V2 ini. Error tersebut berasal dari validasi versi Phase 15 yang terlalu ketat dan sudah diperbaiki. Anda tidak perlu menghapus volume database.

## FULL V3 Windows PowerShell hotfix
FULL V3 fixes a Windows PowerShell 5.1 behavior where Docker Compose progress written to STDERR (for example `Container ... Stopping`) could be treated as a terminating `NativeCommandError` because the startup script uses `$ErrorActionPreference = "Stop"`. The startup script now evaluates Docker's native exit code for intentionally-silent cleanup commands. This does not delete named volumes.

