param(
    [string]$Period = (Get-Date -Format 'yyyy-MM')
)
$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)
docker compose exec -T app php artisan jaringanku:billing-run $Period --tenant=demo-isp
if ($LASTEXITCODE -ne 0) { throw "Billing run gagal." }
