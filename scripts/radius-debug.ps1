$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)
Write-Host "Stopping normal radius container..." -ForegroundColor Yellow
docker compose stop radius
Write-Host "Starting temporary FreeRADIUS debug (-X). Ctrl+C untuk keluar." -ForegroundColor Cyan
docker compose run --rm --service-ports radius freeradius -X
