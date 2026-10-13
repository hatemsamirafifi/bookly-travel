$ErrorActionPreference='Stop'
Set-Location -LiteralPath 'F:/Travel Website/bookly travel'
$evidenceRoot='C:/Users/HaTeM/.codex/delegation/bookly-spec019-20261003/reviewer-filament-corrected-final-20261011'
New-Item -ItemType Directory -Force -Path $evidenceRoot | Out-Null
function Invoke-VerifiedGate {
 param([string]$Name,[string[]]$CommandArguments)
 $started=[DateTimeOffset]::UtcNow.ToString('o');$prior=$ErrorActionPreference
 try {$ErrorActionPreference='Continue'; & docker @CommandArguments 2>&1 | Tee-Object -FilePath "$evidenceRoot/$Name.txt"; $code=$LASTEXITCODE} finally {$ErrorActionPreference=$prior}
 "NUMERIC_EXIT=$code" | Add-Content -LiteralPath "$evidenceRoot/$Name.txt"
 [pscustomobject]@{name=$Name;started_at=$started;finished_at=[DateTimeOffset]::UtcNow.ToString('o');command=(@('docker')+$CommandArguments);exit_code=[int]$code} | ConvertTo-Json -Compress | Add-Content -LiteralPath "$evidenceRoot/manifest.jsonl"
 if($code -ne 0){throw "$Name failed with exit $code"}
}

Invoke-VerifiedGate 'filament-browser' @('exec','-e','DOCKER_ENV=true','-e','PLAYWRIGHT_BASE_URL=http://nginx','bookly-spec019-browser-nextjs-1','npx','playwright','test','tests/e2e/filament-admin.spec.ts','--project=chromium','--workers=1','--retries=0','--reporter=line')
