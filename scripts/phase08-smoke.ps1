$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot
docker compose exec -T app php artisan jaringanku:phase08-smoke
if ($LASTEXITCODE -ne 0) { throw "Phase 08 production readiness smoke test gagal." }
