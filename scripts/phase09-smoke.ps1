$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot
docker compose exec -T app php artisan jaringanku:phase09-smoke
if ($LASTEXITCODE -ne 0) { throw "Phase 09 payment + WhatsApp smoke test gagal." }
