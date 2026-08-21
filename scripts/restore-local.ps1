param([Parameter(Mandatory=$true)][string]$BackupFile)
$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

$resolved = (Resolve-Path $BackupFile -ErrorAction Stop).Path
$containerFile = "/tmp/jaringanku-restore.dump"
Write-Host "PERINGATAN: restore akan mengganti isi database Jaringanku local." -ForegroundColor Red
$confirm = Read-Host "Ketik RESTORE untuk melanjutkan"
if ($confirm -ne 'RESTORE') { throw "Restore dibatalkan." }

docker compose stop app queue scheduler radius nginx | Out-Null
try {
    docker compose cp $resolved "postgres:$containerFile"
    if ($LASTEXITCODE -ne 0) { throw "Copy backup ke container gagal." }
    docker compose exec -T postgres sh -lc "PGPASSWORD=`"`$POSTGRES_PASSWORD`" pg_restore -U `"`$POSTGRES_USER`" -d `"`$POSTGRES_DB`" --clean --if-exists --no-owner --no-privileges '$containerFile'"
    if ($LASTEXITCODE -ne 0) { throw "pg_restore gagal." }
    docker compose exec -T postgres rm -f $containerFile | Out-Null
} finally {
    docker compose up -d app radius queue scheduler nginx | Out-Null
}
Write-Host "[OK] Restore selesai." -ForegroundColor Green
