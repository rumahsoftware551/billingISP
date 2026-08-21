$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot
docker compose exec -T app php artisan jaringanku:phase11-smoke
if ($LASTEXITCODE -ne 0) { throw "Phase 11 ISP Operations smoke test gagal." }
