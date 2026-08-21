$ErrorActionPreference = "Stop"

$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

function Step([string]$Message) { Write-Host "`n==> $Message" -ForegroundColor Cyan }
function Ok([string]$Message) { Write-Host "[OK] $Message" -ForegroundColor Green }
function Warn([string]$Message) { Write-Host "[WARN] $Message" -ForegroundColor Yellow }

function Invoke-DockerQuiet {
    param([Parameter(Mandatory = $true)][string[]]$DockerArgs)

    # Docker/Compose writes normal progress (for example "Container ... Stopping")
    # to STDERR. With $ErrorActionPreference = "Stop", Windows PowerShell 5.1 can
    # promote that harmless STDERR output to NativeCommandError even when Docker
    # exits successfully. Run intentionally-silent Docker commands with a temporary
    # non-terminating policy, discard both native streams, and decide success only
    # from the native exit code.
    $previousErrorActionPreference = $ErrorActionPreference
    $exitCode = 1
    try {
        $ErrorActionPreference = "Continue"
        & docker @DockerArgs 1>$null 2>$null
        $exitCode = $LASTEXITCODE
    }
    finally {
        $ErrorActionPreference = $previousErrorActionPreference
    }

    return [int]$exitCode
}

function Logs([string[]]$Services) {
    foreach ($service in $Services) {
        Write-Host "`n--- Log $service ---" -ForegroundColor Yellow
        docker compose logs --tail=180 $service
    }
}

function ContainerId([string]$Service) {
    $id = docker compose ps -q $Service
    if ($LASTEXITCODE -ne 0) { return "" }
    return (($id | Select-Object -First 1) -as [string]).Trim()
}

function WaitHealthy([string]$Service, [int]$TimeoutSeconds = 300) {
    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)
    while ((Get-Date) -lt $deadline) {
        $id = ContainerId $Service
        if ($id) {
            $state = docker inspect -f '{{.State.Status}}' $id 2>$null
            $health = docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' $id 2>$null
            if ($state -eq 'running' -and ($health -eq 'healthy' -or $health -eq 'none')) {
                Ok "$Service running ($health)"
                return
            }
            if ($state -in @('exited','dead','restarting')) {
                Logs @($Service)
                throw "$Service gagal startup: state=$state health=$health"
            }
        }
        Start-Sleep -Seconds 2
    }
    Logs @($Service)
    throw "$Service belum healthy setelah $TimeoutSeconds detik."
}

Step "Memeriksa Docker Desktop"
if (-not (Get-Command docker -ErrorAction SilentlyContinue)) { throw "Docker tidak ditemukan." }
$dockerInfoExit = Invoke-DockerQuiet -DockerArgs @('info')
if ($dockerInfoExit -ne 0) { throw "Docker Engine belum aktif." }
Ok "Docker Engine aktif"

Step "Memverifikasi checksum source release"
& "$PSScriptRoot\verify-release.ps1"
if ($LASTEXITCODE -ne 0) { throw "Release checksum verification gagal." }
Ok "Source checksum Phase 16 valid"

if (-not (Test-Path ".env")) {
    Step "Membuat konfigurasi local Phase 16"
    Copy-Item ".env.local.example" ".env"

    $parent = Split-Path -Parent $ProjectRoot
    $previousEnv = Get-ChildItem $parent -Directory -ErrorAction SilentlyContinue |
        Where-Object { $_.FullName -ne $ProjectRoot -and ($_.Name -like 'phase-15*' -or $_.Name -like 'phase-14*' -or $_.Name -like 'phase-13*' -or $_.Name -like 'phase-12*' -or $_.Name -like 'phase-11*' -or $_.Name -like 'phase-10*' -or $_.Name -like 'phase-09*' -or $_.Name -like 'phase-08*' -or $_.Name -like 'phase-07*' -or $_.Name -like 'phase-06*' -or $_.Name -like 'phase-05*' -or $_.Name -like 'phase-04*' -or $_.Name -like 'phase-03*' -or $_.Name -like 'phase-02*' -or $_.Name -like 'phase-01*') } |
        Sort-Object @{Expression={ if ($_.Name -like 'phase-15*') {-1} elseif ($_.Name -like 'phase-14*') {0} elseif ($_.Name -like 'phase-13*') {1} elseif ($_.Name -like 'phase-12*') {2} elseif ($_.Name -like 'phase-11*') {3} elseif ($_.Name -like 'phase-10*') {4} elseif ($_.Name -like 'phase-09*') {5} elseif ($_.Name -like 'phase-08*') {6} elseif ($_.Name -like 'phase-07*') {7} elseif ($_.Name -like 'phase-06*') {8} elseif ($_.Name -like 'phase-05*') {9} elseif ($_.Name -like 'phase-04*') {10} elseif ($_.Name -like 'phase-03*') {11} elseif ($_.Name -like 'phase-02*') {12} else {13} }}, LastWriteTime -Descending |
        ForEach-Object { Join-Path $_.FullName '.env' } |
        Where-Object { Test-Path $_ } |
        Select-Object -First 1

    if ($previousEnv) {
        Write-Host "Mengambil secret dan credential dari $previousEnv" -ForegroundColor DarkGray
        $newRaw = Get-Content '.env' -Raw
        $oldRaw = Get-Content $previousEnv -Raw
        foreach ($keyName in @(
            'APP_KEY','APP_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD',
            'SEED_ADMIN_EMAIL','SEED_ADMIN_PASSWORD','SEED_TENANT_NAME','SEED_TENANT_SLUG','SEED_DEMO_DATA',
            'RADIUS_SHARED_SECRET','RADIUS_TEST_PASSWORD','RADIUS_AUTH_PORT','RADIUS_ACCT_PORT',
            'POSTGRES_VOLUME_NAME','REDIS_VOLUME_NAME','STORAGE_VOLUME_NAME','PHASE4_ACCOUNTING_SMOKE','PHASE5_BILLING_SMOKE','PHASE6_AUTOMATION_SMOKE','PHASE7_REPORTS_SMOKE','PHASE8_PRODUCTION_SMOKE','PHASE8_SMOKE_TOKEN','PHASE9_PAYMENT_SMOKE','PHASE10_PORTAL_SMOKE','PHASE10_PORTAL_PASSWORD','PHASE11_OPERATIONS_SMOKE','PHASE12_FINAL_SMOKE','PHASE13_PARTNER_PASSWORD','PHASE13_PARTNER_SMOKE','PHASE14_INVENTORY_PASSWORD','PHASE14_INVENTORY_SMOKE','PHASE15_FINAL_SMOKE','PHASE16_COMMERCIAL_SMOKE'
        )) {
            $m = [regex]::Match($oldRaw, "(?m)^$keyName=(.*)$")
            if ($m.Success -and -not [string]::IsNullOrWhiteSpace($m.Groups[1].Value)) {
                $value = $m.Groups[1].Value.Trim()

                $newRaw = [regex]::Replace($newRaw, "(?m)^$keyName=.*$", "$keyName=$value")
            }
        }
        $utf8 = New-Object System.Text.UTF8Encoding($false)
        [System.IO.File]::WriteAllText((Resolve-Path '.env'), $newRaw, $utf8)
    }
}

$envContent = Get-Content ".env" -Raw
$keyMatch = [regex]::Match($envContent, '(?m)^APP_KEY=(.*)$')
$currentKey = if ($keyMatch.Success) { $keyMatch.Groups[1].Value.Trim() } else { "" }
if ([string]::IsNullOrWhiteSpace($currentKey) -or $currentKey -match '^base64:A{20,}') {
    Step "Membuat APP_KEY"
    $bytes = New-Object byte[] 32
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try { $rng.GetBytes($bytes) } finally { $rng.Dispose() }
    $key = "base64:" + [Convert]::ToBase64String($bytes)
    if ($keyMatch.Success) { $envContent = [regex]::Replace($envContent, '(?m)^APP_KEY=.*$', "APP_KEY=$key") }
    else { $envContent = "APP_KEY=$key`r`n" + $envContent }
    $utf8 = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText((Resolve-Path ".env"), $envContent, $utf8)
}

$envContent = Get-Content ".env" -Raw
foreach ($secretName in @(
    'DB_PASSWORD','SEED_ADMIN_PASSWORD','PHASE3_DEMO_PPPOE_PASSWORD',
    'RADIUS_SHARED_SECRET','RADIUS_TEST_PASSWORD','PHASE10_PORTAL_PASSWORD',
    'PHASE8_SMOKE_TOKEN','HEALTH_TOKEN','PHASE13_PARTNER_PASSWORD','PHASE14_INVENTORY_PASSWORD'
)) {
    $secretMatch = [regex]::Match($envContent, "(?m)^$secretName=(.*)$")
    $secretValue = if ($secretMatch.Success) { $secretMatch.Groups[1].Value.Trim() } else { "" }
    if ([string]::IsNullOrWhiteSpace($secretValue) -or $secretValue -match '^(CHANGE_ME|DISABLED)$') {
        $secretBytes = New-Object byte[] 24
        $secretRng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
        try { $secretRng.GetBytes($secretBytes) } finally { $secretRng.Dispose() }
        $generatedSecret = "local-" + [Convert]::ToBase64String($secretBytes).TrimEnd('=')
        if ($secretMatch.Success) { $envContent = [regex]::Replace($envContent, "(?m)^$secretName=.*$", "$secretName=$generatedSecret") }
        else { $envContent += "`r`n$secretName=$generatedSecret" }
    }
}
$utf8 = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText((Resolve-Path ".env"), $envContent, $utf8)

Step "Menghentikan container Phase 01-15 lama tanpa menghapus volume"
foreach ($oldProject in @('jaringanku-phase01','jaringanku-phase02','jaringanku-phase03','jaringanku-phase04','jaringanku-phase05','jaringanku-phase06','jaringanku-phase07','jaringanku-phase08','jaringanku-phase09','jaringanku-phase10','jaringanku-phase11','jaringanku-phase12','jaringanku-phase13','jaringanku-phase14','jaringanku-phase15')) {
    $oldIds = docker ps -aq --filter "label=com.docker.compose.project=$oldProject"
    if ($oldIds) {
        docker rm -f $oldIds | Out-Null
        Ok "Container $oldProject dibersihkan; named volume tetap dipertahankan"
    }

    # Compose networks are not removed by docker rm.  A stale Phase 01/02
    # network with a fixed subnet can otherwise collide with the next phase.
    $oldNetworks = docker network ls -q --filter "label=com.docker.compose.project=$oldProject"
    if ($oldNetworks) {
        foreach ($networkId in $oldNetworks) {
            $networkRemoveExit = Invoke-DockerQuiet -DockerArgs @('network', 'rm', $networkId)
            if ($networkRemoveExit -ne 0) { Warn "Network lama $oldProject ($networkId) sudah tidak ada atau tidak dapat dihapus; lanjut." }
        }
        Ok "Network lama $oldProject dibersihkan"
    }
}

Step "Validasi Compose"
docker compose config --quiet
if ($LASTEXITCODE -ne 0) { throw "docker-compose.yml tidak valid." }
Ok "Compose valid"

Step "Membersihkan container Phase 16 dari percobaan sebelumnya (volume tetap aman)"
$phase16DownExit = Invoke-DockerQuiet -DockerArgs @('compose', 'down', '--remove-orphans')
if ($phase16DownExit -ne 0) { throw "Gagal membersihkan container Phase 16 lama (docker compose down exit $phase16DownExit)." }
Ok "Container Phase 16 lama dibersihkan tanpa menghapus volume"

$appPort = ([regex]::Match((Get-Content '.env' -Raw), '(?m)^APP_PORT=(\d+)')).Groups[1].Value
if (-not $appPort) { $appPort = '8080' }
$conflicts = docker ps --format '{{.Names}}|{{.Ports}}' | Where-Object { $_ -match (":" + [regex]::Escape($appPort) + "->") }
if ($conflicts) {
    Write-Host "Port $appPort sedang dipakai:" -ForegroundColor Red
    $conflicts | ForEach-Object { Write-Host $_ -ForegroundColor Red }
    throw "Bebaskan APP_PORT=$appPort atau ubah APP_PORT di .env."
}

Step "Menyalakan PostgreSQL dan Redis"
docker compose up -d postgres redis
WaitHealthy postgres
WaitHealthy redis

Step "Build Laravel Phase 16, Nginx, dan FreeRADIUS"
docker compose build app nginx radius
if ($LASTEXITCODE -ne 0) { throw "Build image Phase 16 gagal." }
Ok "Semua image berhasil dibangun"

Step "Menjalankan Laravel dan migration Phase 16"
docker compose up -d --force-recreate app
WaitHealthy app

Step "Preflight schema + Eloquent mapping Phase 09"
docker compose exec -T app php artisan jaringanku:phase09-preflight
if ($LASTEXITCODE -ne 0) { Logs @('app'); throw "Phase 09 database preflight gagal." }
Ok "Schema + model table mapping Phase 09 valid"

Step "Preflight Customer Portal Phase 10"
docker compose exec -T app php artisan jaringanku:phase10-preflight
if ($LASTEXITCODE -ne 0) { Logs @('app'); throw "Phase 10 database/route preflight gagal." }
Ok "Schema + model + route mapping Phase 10 valid"

Step "Preflight ISP Operations Phase 11"
docker compose exec -T app php artisan jaringanku:phase11-preflight
if ($LASTEXITCODE -ne 0) { Logs @('app'); throw "Phase 11 database/route preflight gagal." }
Ok "Schema + model + route mapping Phase 11 valid"

Step "Menjalankan seeder idempotent"
docker compose exec -T app php artisan db:seed --force
if ($LASTEXITCODE -ne 0) { Logs @('app'); throw "Seeder gagal." }
Ok "Seeder berhasil"

Step "Preflight SaaS & Production Final Phase 12"
docker compose exec -T app php artisan jaringanku:phase12-preflight
if ($LASTEXITCODE -ne 0) { Logs @('app'); throw "Phase 12 preflight gagal." }
Ok "SaaS schema + subscription + routes + release metadata valid"

Step "Preflight Portal Mitra Phase 13"
docker compose exec -T app php artisan jaringanku:phase13-preflight
if ($LASTEXITCODE -ne 0) { Logs @('app'); throw "Phase 13 preflight gagal." }
Ok "Partner schema + model mapping + routes valid"

Step "Preflight Portal Inventory Phase 14"
docker compose exec -T app php artisan jaringanku:phase14-preflight
if ($LASTEXITCODE -ne 0) { Logs @('app'); throw "Phase 14 preflight gagal." }
Ok "Inventory schema + model mapping + routes valid"

Step "Preflight Phase 15 Baseline Regression"
docker compose exec -T app php artisan jaringanku:phase15-preflight
if ($LASTEXITCODE -ne 0) { Logs @('app'); throw "Phase 15 preflight gagal." }
Ok "Phase 15 baseline schema + routes + release compatibility valid"

Step "Preflight Commercial Readiness Phase 16"
docker compose exec -T app php artisan jaringanku:phase16-preflight
if ($LASTEXITCODE -ne 0) { Logs @('app'); throw "Phase 16 preflight gagal." }
Ok "Branding + payment custom + RBAC + portal access schema valid"

Step "Resync seluruh service aktif ke RADIUS projection cumulative release"
docker compose exec -T app php artisan jaringanku:radius-resync
if ($LASTEXITCODE -ne 0) { Logs @('app'); throw "RADIUS resync gagal." }
Ok "RADIUS projection aktif siap untuk lifecycle automation"

Step "Menjalankan FreeRADIUS"
docker compose up -d --force-recreate radius
WaitHealthy radius 300

Step "Test PPPoE projection + authentication"
$envRaw = Get-Content '.env' -Raw
$demoPassword = ([regex]::Match($envRaw, '(?m)^PHASE3_DEMO_PPPOE_PASSWORD=(.*)$')).Groups[1].Value.Trim()
$radiusSecret = ([regex]::Match($envRaw, '(?m)^RADIUS_SHARED_SECRET=(.*)$')).Groups[1].Value.Trim()
$seedDemo = ([regex]::Match($envRaw, '(?m)^SEED_DEMO_DATA=(.*)$')).Groups[1].Value.Trim()
$tenantSlug = ([regex]::Match($envRaw, '(?m)^SEED_TENANT_SLUG=(.*)$')).Groups[1].Value.Trim()
if (-not $tenantSlug) { $tenantSlug = 'demo-isp' }
if (-not $demoPassword) { throw 'PHASE3_DEMO_PPPOE_PASSWORD kosong di .env.' }
if (-not $radiusSecret) { throw 'RADIUS_SHARED_SECRET kosong di .env.' }
if ($seedDemo -match '^(?i:true|1|yes)$') {
    $radiusTest = docker compose exec -T app radtest phase3-demo $demoPassword radius 0 $radiusSecret 2>&1
    $radiusText = ($radiusTest | Out-String)
    Write-Host $radiusText
    if ($radiusText -match 'Access-Accept') { Ok "PPPoE demo menerima Access-Accept" }
    else { Warn "FreeRADIUS hidup, tetapi phase3-demo belum Access-Accept. Jalankan .\scripts\radius-test.ps1." }
} else {
    Warn "SEED_DEMO_DATA=false, smoke test phase3-demo dilewati."
}

$phase4Smoke = ([regex]::Match($envRaw, '(?m)^PHASE4_ACCOUNTING_SMOKE=(.*)$')).Groups[1].Value.Trim()
if (-not $phase4Smoke -or $phase4Smoke -match '^(?i:true|1|yes)$') {
    Step "Regression test RADIUS Accounting Phase 04"
    docker compose exec -T app php artisan jaringanku:accounting-smoke
    if ($LASTEXITCODE -ne 0) {
        Logs @('radius','app')
        throw "Accounting regression test gagal."
    }
    Ok "Accounting Start/Interim/Stop -> radacct berhasil"
} else {
    Warn "PHASE4_ACCOUNTING_SMOKE=false, accounting smoke test dilewati."
}

$phase5Smoke = ([regex]::Match($envRaw, '(?m)^PHASE5_BILLING_SMOKE=(.*)$')).Groups[1].Value.Trim()
if (-not $phase5Smoke -or $phase5Smoke -match '^(?i:true|1|yes)$') {
    Step "Regression test Billing Engine Phase 05"
    docker compose exec -T app php artisan jaringanku:billing-smoke
    if ($LASTEXITCODE -ne 0) {
        Logs @('app')
        throw "Billing regression test gagal."
    }
    Ok "Invoice generation + idempotency Phase 05 tetap valid"
} else {
    Warn "PHASE5_BILLING_SMOKE=false, billing smoke test dilewati."
}

$phase6Smoke = ([regex]::Match($envRaw, '(?m)^PHASE6_AUTOMATION_SMOKE=(.*)$')).Groups[1].Value.Trim()
if (-not $phase6Smoke -or $phase6Smoke -match '^(?i:true|1|yes)$') {
    Step "Regression test Automation Phase 06"
    docker compose exec -T app php artisan jaringanku:automation-smoke
    if ($LASTEXITCODE -ne 0) {
        Logs @('radius','app')
        throw "Automation regression test gagal."
    }
    Ok "Overdue -> suspend -> Access-Reject -> payment -> reactivate -> Access-Accept berhasil"
} else {
    Warn "PHASE6_AUTOMATION_SMOKE=false, automation smoke test dilewati."
}

$phase7Smoke = ([regex]::Match($envRaw, '(?m)^PHASE7_REPORTS_SMOKE=(.*)$')).Groups[1].Value.Trim()
if (-not $phase7Smoke -or $phase7Smoke -match '^(?i:true|1|yes)$') {
    Step "Smoke test Dashboard Analytics & Reports Phase 07"
    docker compose exec -T app php artisan jaringanku:reports-smoke
    if ($LASTEXITCODE -ne 0) {
        Logs @('app')
        throw "Reports smoke test Phase 07 gagal."
    }
    Ok "Analytics query + reconciliation + CSV dataset + audit export berhasil"
} else {
    Warn "PHASE7_REPORTS_SMOKE=false, reports smoke test dilewati."
}

Step "Menyalakan Queue, Scheduler, dan Nginx"
docker compose up -d --force-recreate queue scheduler nginx
WaitHealthy nginx

$phase8Smoke = ([regex]::Match($envRaw, '(?m)^PHASE8_PRODUCTION_SMOKE=(.*)$')).Groups[1].Value.Trim()
if (-not $phase8Smoke -or $phase8Smoke -match '^(?i:true|1|yes)$') {
    Step "Smoke test Production Readiness Phase 08"
    docker compose exec -T app php artisan jaringanku:phase08-smoke
    if ($LASTEXITCODE -ne 0) {
        Logs @('queue','scheduler','nginx','app')
        throw "Production Readiness smoke test Phase 08 gagal."
    }
    Ok "Health + queue + notification + signed webhook + audit berhasil"
} else {
    Warn "PHASE8_PRODUCTION_SMOKE=false, Phase 08 smoke test dilewati."
}

$phase9Smoke = ([regex]::Match($envRaw, '(?m)^PHASE9_PAYMENT_SMOKE=(.*)$')).Groups[1].Value.Trim()
if (-not $phase9Smoke -or $phase9Smoke -match '^(?i:true|1|yes)$') {
    Step "Smoke test Payment Gateway + WhatsApp Phase 09"
    docker compose exec -T app php artisan jaringanku:phase09-smoke
    if ($LASTEXITCODE -ne 0) {
        Logs @('queue','scheduler','nginx','app')
        throw "Payment + WhatsApp smoke test Phase 09 gagal."
    }
    Ok "Mock QRIS + idempotent settlement + WhatsApp adapter berhasil"
} else {
    Warn "PHASE9_PAYMENT_SMOKE=false, Phase 09 smoke test dilewati."
}

$phase10Smoke = ([regex]::Match($envRaw, '(?m)^PHASE10_PORTAL_SMOKE=(.*)$')).Groups[1].Value.Trim()
if (-not $phase10Smoke -or $phase10Smoke -match '^(?i:true|1|yes)$') {
    Step "Smoke test Customer Portal Phase 10"
    docker compose exec -T app php artisan jaringanku:phase10-smoke
    if ($LASTEXITCODE -ne 0) {
        Logs @('nginx','app')
        throw "Customer Portal smoke test Phase 10 gagal."
    }
    Ok "Portal account + tenant scope + invoice PDF + PWA assets berhasil"
} else {
    Warn "PHASE10_PORTAL_SMOKE=false, Phase 10 smoke test dilewati."
}

$phase11Smoke = ([regex]::Match($envRaw, '(?m)^PHASE11_OPERATIONS_SMOKE=(.*)$')).Groups[1].Value.Trim()
if (-not $phase11Smoke -or $phase11Smoke -match '^(?i:true|1|yes)$') {
    Step "Smoke test ISP Operations Phase 11"
    docker compose exec -T app php artisan jaringanku:phase11-smoke
    if ($LASTEXITCODE -ne 0) {
        Logs @('nginx','app')
        throw "ISP Operations smoke test Phase 11 gagal."
    }
    Ok "Ticket + work order + installation + inventory + network mapping berhasil"
} else {
    Warn "PHASE11_OPERATIONS_SMOKE=false, Phase 11 smoke test dilewati."
}

$phase12Smoke = ([regex]::Match($envRaw, '(?m)^PHASE12_FINAL_SMOKE=(.*)$')).Groups[1].Value.Trim()
if (-not $phase12Smoke -or $phase12Smoke -match '^(?i:true|1|yes)$') {
    Step "Smoke test SaaS & Production Final Phase 12"
    docker compose exec -T app php artisan jaringanku:phase12-smoke
    if ($LASTEXITCODE -ne 0) {
        Logs @('nginx','app')
        throw "SaaS & Production Final smoke test Phase 12 gagal."
    }
    Ok "SaaS subscription + plan usage + rollback isolation berhasil"
} else {
    Warn "PHASE12_FINAL_SMOKE=false, Phase 12 smoke test dilewati."
}

$phase13Smoke = ([regex]::Match($envRaw, '(?m)^PHASE13_PARTNER_SMOKE=(.*)$')).Groups[1].Value.Trim()
if (-not $phase13Smoke -or $phase13Smoke -match '^(?i:true|1|yes)$') {
    Step "Smoke test Portal Mitra Phase 13"
    docker compose exec -T app php artisan jaringanku:phase13-smoke
    if ($LASTEXITCODE -ne 0) { Logs @('nginx','app'); throw "Portal Mitra smoke test Phase 13 gagal." }
    Ok "Partner scope + payment + commission + withdrawal berhasil"
} else {
    Warn "PHASE13_PARTNER_SMOKE=false, Phase 13 smoke test dilewati."
}

$phase14Smoke = ([regex]::Match($envRaw, '(?m)^PHASE14_INVENTORY_SMOKE=(.*)$')).Groups[1].Value.Trim()
if (-not $phase14Smoke -or $phase14Smoke -match '^(?i:true|1|yes)$') {
    Step "Smoke test Portal Inventory Phase 14"
    docker compose exec -T app php artisan jaringanku:phase14-smoke
    if ($LASTEXITCODE -ne 0) { Logs @('nginx','app'); throw "Portal Inventory smoke test Phase 14 gagal." }
    Ok "Stock ledger + transfer + serialized asset + install/retrieve + opname berhasil"
} else {
    Warn "PHASE14_INVENTORY_SMOKE=false, Phase 14 smoke test dilewati."
}

$phase15Smoke = ([regex]::Match($envRaw, '(?m)^PHASE15_FINAL_SMOKE=(.*)$')).Groups[1].Value.Trim()
if (-not $phase15Smoke -or $phase15Smoke -match '^(?i:true|1|yes)$') {
    Step "Final Integration + Security Audit Phase 15"
    docker compose exec -T app php artisan jaringanku:phase15-security-audit --no-persist
    if ($LASTEXITCODE -ne 0) { Logs @('nginx','app'); throw "Security audit Phase 15 gagal." }
    docker compose exec -T app php artisan jaringanku:phase15-smoke
    if ($LASTEXITCODE -ne 0) { Logs @('nginx','app'); throw "Final smoke test Phase 15 gagal." }
    Ok "Integration + isolation + reconciliation + security audit berhasil"
} else {
    Warn "PHASE15_FINAL_SMOKE=false, Phase 15 smoke test dilewati."
}

$phase16Smoke = ([regex]::Match($envRaw, '(?m)^PHASE16_COMMERCIAL_SMOKE=(.*)$')).Groups[1].Value.Trim()
if (-not $phase16Smoke -or $phase16Smoke -match '^(?i:true|1|yes)$') {
    Step "Commercial Readiness Smoke Test Phase 16"
    docker compose exec -T app php artisan jaringanku:phase16-smoke
    if ($LASTEXITCODE -ne 0) { Logs @('nginx','app'); throw "Commercial readiness smoke test Phase 16 gagal." }
    Ok "Branding + custom payment + RBAC + portal readiness berhasil"
} else {
    Warn "PHASE16_COMMERCIAL_SMOKE=false, Phase 16 smoke test dilewati."
}

Step "Backup command smoke test"
docker compose exec -T postgres sh -lc 'PGPASSWORD="$POSTGRES_PASSWORD" pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --schema-only >/dev/null'
if ($LASTEXITCODE -ne 0) { throw "pg_dump backup smoke test gagal." }
Ok "pg_dump schema backup command valid"

Step "Mencatat development release Jaringanku v1.2.0-dev"
docker compose exec -T app php artisan jaringanku:release-record --version=1.2.0-dev --notes="Phase 16 commercial readiness local acceptance"
if ($LASTEXITCODE -ne 0) { throw "Release record gagal." }
Ok "Development release v1.2.0-dev tercatat"

Step "Status akhir"
docker compose ps

Write-Host "`n============================================================" -ForegroundColor Green
Write-Host " JARINGANKU PHASE 16 SIAP · v1.2.0 DEVELOPMENT" -ForegroundColor Green
Write-Host "============================================================" -ForegroundColor Green
Write-Host "Web       : http://localhost:$appPort"
Write-Host "Admin     : http://localhost:$appPort/login"
Write-Host "Customers : http://localhost:$appPort/customers"
Write-Host "Sessions  : http://localhost:$appPort/network/sessions"
Write-Host "Billing   : http://localhost:$appPort/billing"
Write-Host "Operations: http://localhost:$appPort/operations"
Write-Host "Reports   : http://localhost:$appPort/reports"
Write-Host "RADIUS    : UDP 1812 auth, UDP 1813 accounting"
Write-Host "Accounting: .\scripts\accounting-test.ps1"
Write-Host "Billing   : docker compose exec -T app php artisan jaringanku:billing-run YYYY-MM"
Write-Host "Automation: .\scripts\automation-run.ps1"
Write-Host "Reports   : .\scripts\reports-smoke.ps1"
Write-Host "System    : http://localhost:$appPort/system"
Write-Host "Integrations: http://localhost:$appPort/integrations"
Write-Host "Phase 08  : .\scripts\phase08-smoke.ps1"
Write-Host "Phase 09  : .\scripts\phase09-smoke.ps1"
Write-Host "Phase 10  : .\scripts\phase10-smoke.ps1"
Write-Host "Phase 11  : .\scripts\phase11-smoke.ps1"
Write-Host "Phase 12  : .\scripts\phase12-smoke.ps1"
Write-Host "Phase 13  : .\scripts\phase13-smoke.ps1"
Write-Host "Phase 14  : .\scripts\phase14-smoke.ps1"
Write-Host "Phase 15  : .\scripts\phase15-smoke.ps1"
Write-Host "Phase 16  : .\scripts\phase16-smoke.ps1"
Write-Host "Access    : http://localhost:$appPort/access"
Write-Host "Settings  : http://localhost:$appPort/settings"
Write-Host "Bukti Bayar: http://localhost:$appPort/billing/manual-payments"
Write-Host "Full regression: .\scripts\final-regression.ps1"
Write-Host "Release Audit: http://localhost:$appPort/platform/release"
Write-Host "Mitra Admin: http://localhost:$appPort/partners"
Write-Host "Portal Mitra: http://localhost:$appPort/mitra/$tenantSlug/login"
Write-Host "Inventory Admin : http://localhost:$appPort/inventory-management"
Write-Host "Portal Inventory: http://localhost:$appPort/inventory/$tenantSlug/login"
Write-Host "Platform  : http://localhost:$appPort/platform"
Write-Host "Version   : http://localhost:$appPort/version"
Write-Host "Field Ops : http://localhost:$appPort/field-operations"
Write-Host "Portal    : http://localhost:$appPort/portal/$tenantSlug/login"
Write-Host "Health    : .\scripts\health.ps1"
if ($seedDemo -match '^(?i:true|1|yes)$') {
    Write-Host "`nDemo local (SEED_DEMO_DATA=true):" -ForegroundColor DarkGray
    Write-Host "  Admin     : admin@jaringanku.local"
    Write-Host "  Customer  : demo@jaringanku.local"
    Write-Host "  Mitra     : mitra@jaringanku.local"
    Write-Host "  Inventory : inventory@jaringanku.local"
    Write-Host "  PPPoE     : phase3-demo"
    Write-Host "  Password acak tersimpan hanya di file .env lokal." -ForegroundColor Yellow
}
