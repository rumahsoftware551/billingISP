$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot
docker compose exec -T app php artisan jaringanku:automation-smoke
exit $LASTEXITCODE
