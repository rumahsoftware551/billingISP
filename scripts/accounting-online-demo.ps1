$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)
Write-Host "Membuat synthetic ONLINE session untuk demo UI Phase 04..." -ForegroundColor Cyan
docker compose exec -T app php artisan jaringanku:accounting-smoke --leave-open
if ($LASTEXITCODE -ne 0) { throw "Gagal membuat online demo session." }
Write-Host "Buka http://localhost:8080/network/sessions" -ForegroundColor Green
