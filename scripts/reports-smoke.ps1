$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

docker compose exec -T app php artisan jaringanku:reports-smoke
if ($LASTEXITCODE -ne 0) { throw "Phase 07 reports smoke test gagal." }
