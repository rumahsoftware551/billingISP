param([string]$Tenant = "")
$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot
$args = @('compose','exec','-T','app','php','artisan','jaringanku:payment-reminders')
if ($Tenant) { $args += "--tenant=$Tenant" }
& docker @args
if ($LASTEXITCODE -ne 0) { throw "Payment reminder command gagal." }
