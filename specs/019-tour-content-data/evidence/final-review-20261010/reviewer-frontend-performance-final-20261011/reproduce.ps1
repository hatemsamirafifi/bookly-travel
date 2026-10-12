$ErrorActionPreference='Stop'
Set-Location -LiteralPath 'F:/Travel Website/bookly travel'
$evidenceRoot='C:/Users/HaTeM/.codex/delegation/bookly-spec019-20261003/reviewer-frontend-performance-final-20261011'
New-Item -ItemType Directory -Force -Path $evidenceRoot | Out-Null
function Invoke-VerifiedGate {
 param([string]$Name,[string[]]$CommandArguments)
 $started=[DateTimeOffset]::UtcNow.ToString('o');$prior=$ErrorActionPreference
 try {$ErrorActionPreference='Continue'; & docker @CommandArguments 2>&1 | Tee-Object -FilePath "$evidenceRoot/$Name.txt"; $code=$LASTEXITCODE} finally {$ErrorActionPreference=$prior}
 "NUMERIC_EXIT=$code" | Add-Content -LiteralPath "$evidenceRoot/$Name.txt"
 [pscustomobject]@{name=$Name;started_at=$started;finished_at=[DateTimeOffset]::UtcNow.ToString('o');command=(@('docker')+$CommandArguments);exit_code=[int]$code} | ConvertTo-Json -Compress | Add-Content -LiteralPath "$evidenceRoot/manifest.jsonl"
 if($code -ne 0){throw "$Name failed with exit $code"}
}
$front=@('exec','bookly-spec019-browser-nextjs-1')
Invoke-VerifiedGate 'frontend-lint' ($front+@('npm','run','lint'))
Invoke-VerifiedGate 'frontend-typecheck' ($front+@('npm','run','typecheck'))
Invoke-VerifiedGate 'frontend-full-jest' ($front+@('npm','test','--','--runInBand'))
Invoke-VerifiedGate 'frontend-production-build' ($front+@('npm','run','build'))
'REVIEWER_US4_SOURCE_GATES_PASSED_BROWSER_STILL_REQUIRED'
