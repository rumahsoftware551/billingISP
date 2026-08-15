$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot
docker compose exec -T app php artisan jaringanku:phase12-preflight
if ($LASTEXITCODE -ne 0) { throw "Phase 12 preflight gagal." }
docker compose exec -T app php artisan jaringanku:phase12-smoke
if ($LASTEXITCODE -ne 0) { throw "Phase 12 smoke test gagal." }
Write-Host "PHASE 12 ACCEPTANCE PASSED" -ForegroundColor Green
