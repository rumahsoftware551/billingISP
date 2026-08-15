$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)
$envRaw = Get-Content '.env' -Raw
$demoPassword = ([regex]::Match($envRaw, '(?m)^PHASE3_DEMO_PPPOE_PASSWORD=(.*)$')).Groups[1].Value.Trim()
$radiusSecret = ([regex]::Match($envRaw, '(?m)^RADIUS_SHARED_SECRET=(.*)$')).Groups[1].Value.Trim()
if (-not $demoPassword) { $demoPassword = 'Phase3Demo123!' }
Write-Host "Testing Customer Service PPPoE user phase3-demo..." -ForegroundColor Cyan
docker compose exec -T app radtest phase3-demo $demoPassword radius 0 $radiusSecret
Write-Host "`nJika hasil Access-Accept, customer_services -> RADIUS projection -> FreeRADIUS -> PostgreSQL sudah benar." -ForegroundColor Green
