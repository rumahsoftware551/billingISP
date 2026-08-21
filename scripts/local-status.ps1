$ErrorActionPreference = "Continue"
Set-Location (Split-Path -Parent $PSScriptRoot)
docker compose ps
Write-Host "`n--- Health ---" -ForegroundColor Cyan
foreach ($service in @('postgres','redis','app','radius','queue','scheduler','nginx')) {
    $id = (docker compose ps -q $service | Select-Object -First 1)
    if (-not $id) { Write-Host ("{0,-12} missing" -f $service); continue }
    $state = docker inspect -f '{{.State.Status}}' $id 2>$null
    $health = docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' $id 2>$null
    Write-Host ("{0,-12} state={1} health={2}" -f $service,$state,$health)
}
