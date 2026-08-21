param(
    [string]$Tenant = ""
)
$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot
$argsList = @('compose','exec','-T','app','php','artisan','jaringanku:automation-run','--source=manual')
if ($Tenant) { $argsList += "--tenant=$Tenant" }
& docker @argsList
exit $LASTEXITCODE
