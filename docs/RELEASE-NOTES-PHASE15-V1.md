# Jaringanku Phase 15 — v1.1.0 Stable

Phase 15 freezes the cumulative Phase 01–14 codebase as Jaringanku v1.1.0 Stable.

## Final gates
- Phase 04–14 regression commands remain executable.
- Phase 15 preflight requires the stable version/channel and final schema.
- Security audit validates route/RBAC guards, tenant-scoped models, hashed portal passwords, encrypted secrets, cross-tenant data consistency, billing allocation integrity, partner commission integrity, and inventory integrity.
- Production strict audit additionally requires HTTPS, secure cookies, health token, closed webhook SSRF controls, and a non-global RADIUS allowlist.
- Acceptance runs and findings are stored for platform audit history.

## Platform UI
`/platform/release` is available to Platform Super Admin and can run normal or strict-production audit.

## CLI
```bash
php artisan jaringanku:phase15-preflight
php artisan jaringanku:phase15-security-audit
php artisan jaringanku:phase15-security-audit --strict
php artisan jaringanku:phase15-smoke
```

## Windows local
```powershell
.\scripts\phase15-smoke.ps1
.\scripts\final-regression.ps1
```
