[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$frontendDirectory = Split-Path -Parent $PSScriptRoot
$memory = Get-CimInstance Win32_PerfFormattedData_PerfOS_Memory
if ($memory.CommitLimit -le 0 -or $memory.CommittedBytes -le 0 -or
    $memory.CommittedBytes / $memory.CommitLimit -ge 0.85 -or
    $memory.CommitLimit - $memory.CommittedBytes -lt 4GB -or
    $memory.AvailableMBytes -lt 2048) {
    throw 'Windows resource preflight blocked before starting Docker: require commit <85%, commit headroom >=4GB and available RAM >=2GB.'
}
$snapshot = @{
    availableMB = [double]$memory.AvailableMBytes
    committedBytes = [double]$memory.CommittedBytes
    commitLimitBytes = [double]$memory.CommitLimit
} | ConvertTo-Json -Compress

# One Docker client for all eight audits: Chrome and Lighthouse children run
# serially inside Linux. Do not pause services, change pagefiles, or close apps.
Push-Location -LiteralPath $frontendDirectory
try {
    & docker exec -e "BOOKLY_AUDIT_HOST_METRICS=$snapshot" bookly-frontend node scripts/lighthouse-audit.mjs
    if ($LASTEXITCODE -ne 0) {
        throw "Performance gate failed or preflight blocked (exit $LASTEXITCODE). All completed reports are retained."
    }
}
finally {
    Pop-Location
}
