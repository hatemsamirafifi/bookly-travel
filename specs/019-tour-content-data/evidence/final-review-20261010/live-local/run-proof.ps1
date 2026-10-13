param([string[]]$Modes=@('source','recover','worker','inspect'),[switch]$Compatible)
$ErrorActionPreference='Stop'
Set-Location -LiteralPath 'F:/Travel Website/bookly travel'
$proofRoot='C:/Users/HaTeM/.codex/delegation/bookly-spec019-20261003/us6-live-local-20261010'
$common=@('run','--rm','--network','bookly-spec019-browser_default','--entrypoint','php','--workdir','/var/www/html',
 '--env-file',"$proofRoot/worker-private.env",
 '--mount',('type=bind,source=' + $(if($Compatible){"$proofRoot/rollback-compatible/backend"}else{'F:/Travel Website/bookly travel/backend'}) + ',target=/var/www/html'),
 '--mount','type=volume,source=bookly-spec019-browser_vendor,target=/var/www/html/vendor',
 '--mount','type=volume,source=bookly-spec019-browser_browser_storage,target=/var/www/html/storage',
 '--mount','type=volume,source=bookly-spec019-browser_browser_cache,target=/var/www/html/bootstrap/cache',
 '--mount','type=bind,source=C:/Users/HaTeM/.codex/delegation/bookly-spec019-20261003/browser.env,target=/var/www/html/.env,readonly',
 '--mount','type=bind,source=F:/Travel Website/bookly travel/frontend/tests/.auth,target=/auth,readonly',
 '--mount',('type=bind,source='+$proofRoot+',target=/proof'))
foreach($mode in $Modes){
 $start=[DateTimeOffset]::UtcNow.ToString('o')
 $args=$common+@('--name',"bookly-spec019-live-local-$mode",'bookly-spec019-worker-review:20261009','/proof/proof.php',$mode)
 $oldPreference=$ErrorActionPreference
 try{$ErrorActionPreference='Continue'; & docker @args 2>&1 | Tee-Object -FilePath "$proofRoot/$mode.txt"; $native=$LASTEXITCODE}finally{$ErrorActionPreference=$oldPreference}
 "NUMERIC_EXIT=$native" | Add-Content "$proofRoot/$mode.txt"
 [pscustomobject]@{mode=$mode;command=(@('docker')+$args);started_at=$start;finished_at=[DateTimeOffset]::UtcNow.ToString('o');exit_code=$native} | ConvertTo-Json -Compress -Depth 5 | Add-Content "$proofRoot/manifest.jsonl"
 if($native -ne 0){throw "Live local $mode failed native=$native; inspect sanitized result before retry."}
}
