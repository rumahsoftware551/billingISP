$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot
$commands = @(
  'jaringanku:accounting-smoke','jaringanku:billing-smoke','jaringanku:automation-smoke','jaringanku:reports-smoke',
  'jaringanku:phase08-smoke','jaringanku:phase09-smoke','jaringanku:phase10-smoke','jaringanku:phase11-smoke',
  'jaringanku:phase12-smoke','jaringanku:phase13-smoke','jaringanku:phase14-smoke','jaringanku:phase15-smoke','jaringanku:phase16-smoke'
)
foreach ($command in $commands) {
  Write-Host "==> $command" -ForegroundColor Cyan
  docker compose exec -T app php artisan $command
  if ($LASTEXITCODE -ne 0) { throw "Regression gagal pada $command" }
}
Write-Host "[OK] FULL REGRESSION PHASE 04-16 PASSED" -ForegroundColor Green
