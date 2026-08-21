$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)
Write-Host "Jaringanku Phase 04 - RADIUS Accounting Start/Interim/Stop" -ForegroundColor Cyan
docker compose exec -T app php artisan jaringanku:accounting-smoke
if ($LASTEXITCODE -ne 0) { throw "Accounting smoke test gagal." }
