$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root
$manifest = Join-Path $root 'RELEASE-SHA256SUMS.txt'
if (-not (Test-Path $manifest)) { throw "RELEASE-SHA256SUMS.txt tidak ditemukan." }
$failed = 0
foreach ($line in Get-Content $manifest) {
    if ([string]::IsNullOrWhiteSpace($line)) { continue }
    $parts = $line -split '\s+', 2
    if ($parts.Count -ne 2) { throw "Format checksum tidak valid: $line" }
    $expected = $parts[0].Trim().ToLowerInvariant()
    $relative = $parts[1].Trim().TrimStart([char[]]"* ")
    $path = Join-Path $root $relative
    if (-not (Test-Path $path)) { Write-Host "MISSING $relative" -ForegroundColor Red; $failed++; continue }
    $actual = (Get-FileHash -Algorithm SHA256 $path).Hash.ToLowerInvariant()
    if ($actual -ne $expected) { Write-Host "FAILED  $relative" -ForegroundColor Red; $failed++ }
}
if ($failed -gt 0) { throw "$failed file gagal checksum." }
Write-Host "JARINGANKU RELEASE SOURCE CHECKSUM PASSED" -ForegroundColor Green
