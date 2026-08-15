param([string]$OutputDirectory = "backups")
$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

if (-not (Test-Path $OutputDirectory)) { New-Item -ItemType Directory -Path $OutputDirectory | Out-Null }
$stamp = Get-Date -Format "yyyyMMdd-HHmmss"
$file = Join-Path $OutputDirectory "jaringanku-local-$stamp.dump"
$containerFile = "/tmp/jaringanku-local-$stamp.dump"

Write-Host "Membuat PostgreSQL custom-format backup: $file" -ForegroundColor Cyan
docker compose exec -T postgres sh -lc "PGPASSWORD=`"`$POSTGRES_PASSWORD`" pg_dump -U `"`$POSTGRES_USER`" -d `"`$POSTGRES_DB`" -Fc -f '$containerFile'"
if ($LASTEXITCODE -ne 0) { throw "pg_dump gagal." }
docker compose cp "postgres:$containerFile" $file
if ($LASTEXITCODE -ne 0) { throw "docker compose cp backup gagal." }
docker compose exec -T postgres rm -f $containerFile | Out-Null
if ((Get-Item $file).Length -lt 1024) { throw "Backup terlalu kecil / tidak valid." }
$hash = (Get-FileHash $file -Algorithm SHA256).Hash.ToLower()
"$hash  $(Split-Path -Leaf $file)" | Set-Content "$file.sha256" -Encoding ascii
Write-Host "[OK] Backup selesai: $file" -ForegroundColor Green
