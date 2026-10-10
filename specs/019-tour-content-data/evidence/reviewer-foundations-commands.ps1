$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath 'F:/Travel Website/bookly travel'
$evidenceRoot = 'C:/Users/HaTeM/.codex/delegation/bookly-spec019-20261003/reviewer-foundation-gates'
New-Item -ItemType Directory -Force -Path $evidenceRoot | Out-Null
function Invoke-VerifiedGate {
    param([string]$Name, [string[]]$CommandArguments)
    $gateStartedAt = [DateTimeOffset]::UtcNow.ToString('o')
    $gateErrorPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        & docker @CommandArguments 2>&1 | Tee-Object -FilePath "$evidenceRoot/$Name.txt"
        $gateExit = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $gateErrorPreference
    }
    "NUMERIC_EXIT=$gateExit" | Add-Content -LiteralPath "$evidenceRoot/$Name.txt"
    [pscustomobject]@{name=$Name;started_at=$gateStartedAt;finished_at=[DateTimeOffset]::UtcNow.ToString('o');exit_code=[int]$gateExit} | ConvertTo-Json -Compress | Add-Content -LiteralPath "$evidenceRoot/manifest.jsonl"
    if ($gateExit -ne 0) { throw "$Name failed with exit $gateExit" }
}
Invoke-VerifiedGate -Name 'backend-focused' -CommandArguments @('compose','exec','-T','-e','GEMINI_API_KEY=','laravel','php','-d','memory_limit=512M','vendor/bin/pest','--configuration=phpunit.pgsql.xml','tests/Feature/Partner/TourContentRevisionTest.php','tests/Feature/Partner/TourContentInputTest.php','tests/Feature/Search/TourMediaReaderTest.php','tests/Feature/Partner/TourCreateTest.php','tests/Feature/Partner/TourDraftTest.php','tests/Feature/Partner/TourTranslationTest.php','tests/Feature/Search/TourDetailTest.php','tests/Feature/Admin/TourModerationTest.php')
Invoke-VerifiedGate -Name 'pint' -CommandArguments @('compose','exec','-T','laravel','php','vendor/bin/pint','--test','app/Domains/Partner/Models/TourMedia.php','app/Domains/Partner/Requests/StoreTourRequest.php','app/Domains/Partner/Requests/UpdateTourRequest.php','app/Domains/Partner/Requests/TourContentRules.php','app/Domains/Partner/Services/TourTranslationService.php','app/Models/Tour.php','tests/Feature/Partner/TourContentRevisionTest.php','tests/Feature/Partner/TourContentInputTest.php','tests/Feature/Search/TourMediaReaderTest.php','tests/Support')
Invoke-VerifiedGate -Name 'phpstan' -CommandArguments @('compose','exec','-T','laravel','php','-d','memory_limit=512M','vendor/bin/phpstan','analyse','--no-progress','app/Domains/Partner/Models/TourMedia.php','app/Domains/Partner/Requests/StoreTourRequest.php','app/Domains/Partner/Requests/UpdateTourRequest.php','app/Domains/Partner/Requests/TourContentRules.php','app/Domains/Partner/Services/TourTranslationService.php','app/Models/Tour.php','tests/Support')
$frontendPrefix = @('compose','--env-file','C:/Users/HaTeM/.codex/delegation/bookly-spec019-20261003/browser.env','-p','bookly-spec019-browser','--project-directory','F:/Travel Website/bookly travel','-f','docker-compose.ci.yml','-f','C:/Users/HaTeM/.codex/delegation/bookly-spec019-20261003/browser-overrides.yml','exec','-T','nextjs')
Invoke-VerifiedGate -Name 'frontend-lint' -CommandArguments ($frontendPrefix + @('npm','run','lint'))
Invoke-VerifiedGate -Name 'frontend-typecheck' -CommandArguments ($frontendPrefix + @('npm','run','typecheck'))
Invoke-VerifiedGate -Name 'frontend-focused-jest' -CommandArguments ($frontendPrefix + @('npm','test','--','--runInBand','--runTestsByPath','src/lib/validators/__tests__/partner.test.ts','src/components/partner/tours/__tests__/TourCard.test.tsx','src/components/partner/tours/__tests__/ImageUploader.test.tsx','src/components/partner/tours/__tests__/PricingTierForm.test.tsx','src/components/partner/tours/__tests__/AvailabilityCalendar.test.tsx','src/components/tour/__tests__/TourDetail.test.tsx','src/components/tour/__tests__/ImageGallery.test.tsx','src/lib/api/__tests__/client.test.ts'))
'REVIEWER_FOUNDATION_GATES_PASSED'
