$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

docker compose exec -T app php artisan jaringanku:phase16-preflight
if ($LASTEXITCODE -ne 0) { throw "Phase 16 preflight gagal." }

docker compose exec -T app php artisan jaringanku:phase16-smoke
if ($LASTEXITCODE -ne 0) { throw "Phase 16 smoke test gagal." }

Write-Host "[OK] PHASE 16 COMMERCIAL READINESS ACCEPTANCE PASSED" -ForegroundColor Green
