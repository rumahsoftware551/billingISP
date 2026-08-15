$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)
docker compose exec -T app php artisan jaringanku:billing-smoke
if ($LASTEXITCODE -ne 0) { throw "Billing smoke test gagal." }
