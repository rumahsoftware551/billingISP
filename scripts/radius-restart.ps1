$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)
docker compose restart radius
Start-Sleep -Seconds 4
docker compose ps radius
docker compose logs --tail=80 radius
