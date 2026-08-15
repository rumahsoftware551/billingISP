$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot
docker compose exec -T app php artisan jaringanku:phase10-smoke
if ($LASTEXITCODE -ne 0) { throw "Phase 10 Customer Portal smoke test gagal." }
