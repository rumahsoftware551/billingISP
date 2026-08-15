$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot
docker compose exec -T app php artisan jaringanku:phase14-preflight
if ($LASTEXITCODE -ne 0) { throw "Phase 14 preflight gagal." }
docker compose exec -T app php artisan jaringanku:phase14-smoke
if ($LASTEXITCODE -ne 0) { throw "Phase 14 smoke test gagal." }
Write-Host "[OK] PHASE 14 INVENTORY PORTAL SMOKE TEST PASSED" -ForegroundColor Green
