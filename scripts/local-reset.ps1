$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)
Write-Host "PERINGATAN: perintah ini menghapus container Phase 15, tetapi TIDAK menghapus named volume PostgreSQL/Redis/storage." -ForegroundColor Yellow
docker compose down --remove-orphans
Write-Host "Data local tetap ada. Jangan gunakan 'docker compose down -v' kecuali memang ingin menghapus seluruh data setelah backup." -ForegroundColor Yellow
