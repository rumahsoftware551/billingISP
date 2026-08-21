$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot
docker compose exec -T app php artisan jaringanku:phase15-preflight
if ($LASTEXITCODE -ne 0) { throw "Phase 15 preflight gagal." }
docker compose exec -T app php artisan jaringanku:phase15-security-audit --no-persist
if ($LASTEXITCODE -ne 0) { throw "Phase 15 security audit gagal." }
docker compose exec -T app php artisan jaringanku:phase15-smoke
if ($LASTEXITCODE -ne 0) { throw "Phase 15 smoke test gagal." }
Write-Host "[OK] PHASE 15 BASELINE REGRESSION PASSED" -ForegroundColor Green
