$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot
docker compose exec -T app php artisan jaringanku:phase13-preflight
if ($LASTEXITCODE -ne 0) { throw "Phase 13 preflight gagal." }
docker compose exec -T app php artisan jaringanku:phase13-smoke
if ($LASTEXITCODE -ne 0) { throw "Phase 13 smoke test gagal." }
