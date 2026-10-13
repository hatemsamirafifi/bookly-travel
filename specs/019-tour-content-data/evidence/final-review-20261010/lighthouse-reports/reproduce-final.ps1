$ErrorActionPreference='Stop'
Set-Location -LiteralPath 'F:/Travel Website/bookly travel'
$proof='C:/Users/HaTeM/.codex/delegation/bookly-spec019-20261003/reviewer-lighthouse-performance-final-20261011'
New-Item -ItemType Directory -Force -Path $proof | Out-Null
$memory=Get-CimInstance Win32_PerfFormattedData_PerfOS_Memory
$snapshot=[ordered]@{availableMB=[double]$memory.AvailableMBytes;committedBytes=[double]$memory.CommittedBytes;commitLimitBytes=[double]$memory.CommitLimit}
$json=$snapshot|ConvertTo-Json -Compress
$json|Set-Content -LiteralPath "$proof/host-memory.json"
$started=[DateTimeOffset]::UtcNow.ToString('o')
$args=@('exec','-e',"BOOKLY_AUDIT_HOST_METRICS=$json",'-e','BOOKLY_AUDIT_BASE_URL=http://nginx','bookly-spec019-browser-nextjs-1','node','scripts/lighthouse-audit.mjs')
$prior=$ErrorActionPreference
try{$ErrorActionPreference='Continue'; & docker @args 2>&1|Tee-Object -FilePath "$proof/lighthouse.txt";$code=$LASTEXITCODE}finally{$ErrorActionPreference=$prior}
"NUMERIC_EXIT=$code"|Add-Content -LiteralPath "$proof/lighthouse.txt"
[ordered]@{started_at=$started;finished_at=[DateTimeOffset]::UtcNow.ToString('o');command=(@('docker')+$args);exit_code=[int]$code}|ConvertTo-Json -Depth 6|Set-Content -LiteralPath "$proof/manifest.json"
if($code -ne 0){throw "Actual Lighthouse gate exit $code"}
