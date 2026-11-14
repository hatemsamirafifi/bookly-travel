# EGM-003 execution file and test manifest

Date: 2026-11-14. Baseline `f07d3d0`. [Technical design v3](supplier-portal-egm003-governance-v3.md).
Status: prepared before implementation; contract synchronization and the final independent pre-code gate passed both Goldfish reviewers on 2026-11-14. Feature verification remains pending.

The orchestrator owns review, commands/evidence, scope corrections and commits. OpenCode MiniMax M3.1 Flash Free (`opencode/minimax-m3.1-flash-free`, `max`) owns implementation in this manifest. It must preserve concurrent edits and stop before an unlisted path is needed; the orchestrator first updates the manifest from actual caller evidence. Do not reset/stash/clean/switch branches or commit from the implementer.

## S1 — PostgreSQL storage and faithful baseline

Create:

- `backend/database/migrations/2026_11_14_000001_create_tour_content_governance.php`
- `backend/app/Models/TourContentRevision.php`
- `backend/app/Models/TourRevisionRequest.php`
- `backend/tests/Support/TourRevisionFixtures.php`
- `backend/tests/Feature/Partner/TourRevisionGovernanceTest.php`

Modify:

- `backend/app/Models/Tour.php`
- `backend/app/Models/TourTranslationState.php`

This test file owns faithful/invalid-legacy baseline, lazy/batched idempotence, required pointer scope, immutable payload replacement, partial unique active-candidate constraint and disposable migration up/down/up before adoption. S1 executes storage/constraint checks only; lazy capture/replacement workflow cases are added and executed with the S2 service, not claimed early. Do not overwrite existing `TourContentRevisionTest.php`, which tests the pre-existing source hash and content-lock contract.

## S2 — Domain service, writer fences and publication compatibility

Create:

- `backend/app/Domains/Partner/Services/TourRevisionService.php`
- `backend/app/Domains/Partner/Services/TourRevisionPayloadService.php`
- `backend/app/Domains/Partner/Exceptions/TourRevisionException.php`
- `backend/app/Console/Commands/BackfillTourContentRevisions.php`
- `backend/tests/Feature/Admin/TourRevisionGovernanceTest.php`
- `backend/tests/Feature/Admin/TourRevisionConcurrencyTest.php`
- `backend/tests/Support/TourRevisionWorker.php`

Modify:

- `backend/app/Domains/Partner/Services/TourService.php`
- `backend/app/Domains/Partner/Requests/TourContentRules.php`
- `backend/app/Domains/Admin/Actions/ApproveTourAction.php`
- `backend/app/Domains/Admin/Services/TourPublicationGuardService.php`
- `backend/tests/Feature/Partner/TourContentInputTest.php`
- `backend/tests/Feature/Partner/TourOptionsTest.php`
- `backend/tests/Feature/Admin/TourPublicationInvariantsTest.php`
- `backend/tests/Feature/Admin/Filament/TourPublicationGuardTest.php`
- `backend/tests/Feature/Admin/Filament/TourResourceTest.php`

The existing first-publication action remains authorized/transactional and records one `tour.publish` event. Update only old positive expectations that the approved EGM-003 policy deliberately invalidates, such as ordinary admin source editing. Keep every security incident regression, required-source validation, failed ownership attempt, duplicate-effect assertion and source-locale case.

The shared input boundary rejects attempts to author the new platform-owned pointer/version/generation fields as well as the existing hash/readiness keys. Observed base/hash/epoch tokens are accepted only by their dedicated revision/response contracts; they never assign the stored counters.

The shared revision service owns immutable creation/replacement/submission/withdrawal/response, initial versus published requests, promotion, obsolete reconciliation and full payload projections. The payload service owns normalization/sanitization/hash/diff and source/media/text-only option materialization; never call full `syncOptions()` with text-only values. Keep controller/Filament callbacks thin. Do not add a caller-controlled trusted-write flag, GUC or trigger bypass.

Tests own exact-ID approval, rollback on audit/materialization failure, per-item authorization, first/unpublish/archive/suspend/re-publish cases and real process races. Reuse `tests/Support/Spec019TranslationProcess.php` and the existing publication worker pattern; do not replace the database with mocks. Booking's existing Tour lock is already present and must be verified before adding another lock.

## S3 — Requests, exceptional corrections and real Filament

Create:

- `backend/app/Domains/Admin/Actions/RequestTourChangesAction.php`
- `backend/app/Domains/Admin/Actions/ProposeTourCorrectionAction.php`
- `backend/app/Domains/Admin/Actions/ApproveTourRevisionAction.php`
- `backend/app/Domains/Admin/Actions/RejectTourRevisionAction.php`
- `backend/tests/Feature/Admin/Filament/TourContentGovernanceTest.php`

Modify:

- `backend/app/Domains/Admin/Services/AdminAuthorizationService.php`
- `backend/app/Filament/Resources/TourResource.php`
- `backend/app/Filament/Resources/TourResource/Pages/CreateTour.php`
- `backend/app/Filament/Resources/TourResource/Pages/EditTour.php`
- `backend/app/Filament/Resources/TourResource/Pages/ListTours.php`
- `backend/app/Filament/Resources/TourResource/Pages/ViewTour.php`
- `backend/app/Filament/Resources/AdminRoleResource.php`
- `backend/app/Filament/Resources/AdminUserResource.php`

Permission resources may change only to expose the new explicit correction permission if their actual field lists require it; no default grant or role-template expansion. Tests own forged source/derivative/general-field writes, read-only generated content, mandatory reason/field refs, actual compared revision UI, stale modal approval, permission revocation, correction audit/ordinary review and initial-review requests. Keep demo generation's existing refusal unchanged.

## S4 — Gemini generation epoch and legacy retirement

Create:

- `backend/tests/Feature/Partner/TourRevisionTranslationTest.php`

Modify:

- `backend/app/Domains/Partner/Services/TourTranslationService.php`
- `backend/app/Domains/Partner/Jobs/GenerateTourTranslationJob.php`
- `backend/app/Domains/Translation/Jobs/TranslateContentFieldJob.php`
- `backend/app/Console/Commands/QueueTourTranslations.php`
- `backend/app/Console/Commands/AdoptLegacyTourItineraries.php`
- `backend/tests/Feature/Translation/AutoTranslationEngineTest.php`
- `backend/tests/Feature/Partner/TourTranslationTest.php`
- `backend/tests/Feature/Partner/TourTranslationJobStateTest.php`
- `backend/tests/Feature/Partner/TourTranslationProviderTest.php`
- `backend/tests/Feature/Partner/TourTranslationConcurrencyTest.php`
- `backend/tests/Feature/Partner/TourItineraryAdoptionTest.php`
- `backend/tests/Feature/Search/TourSearchIndexTest.php`
- `backend/tests/Feature/Search/TourProjectionRecoveryTest.php`
- `backend/tests/Support/Spec019TranslationWorker.php`
- `backend/tests/Support/TourContentFixtures.php`

These are actual current job-constructor/translation fixture callers identified by search. Adapt CURRENT-work fixtures to provide the real generation epoch, without weakening source hash, stale-job, queue failure or fallback assertions. Separate legacy-null-job tests must assert no writes. Tests own A-B-A success/failure, same-content presentation approval, first-publish crossing, epoch mismatch recovery, source revision rollback, forced regeneration and no source/guide-language translation. Mock only provider/queue/network boundaries; no external provider credentials or live translation calls in automated tests.

## S5 — Coherent public projections and existing caches

Create:

- `backend/app/Domains/Search/Services/TourPublicCacheService.php`
- `backend/app/Domains/Search/Services/TourPublicProjectionService.php`
- `backend/tests/Feature/Search/TourRevisionProjectionTest.php`
- `backend/tests/Feature/Booking/TourRevisionBookingSnapshotTest.php`

Modify:

- `backend/app/Domains/Search/Controllers/Public/TourDetailController.php`
- `backend/app/Domains/Search/Actions/GetTourDetailAction.php`
- `backend/app/Domains/Search/Actions/SearchToursAction.php`
- `backend/app/Domains/Search/Actions/GetHomepageDataAction.php`
- `backend/app/Domains/Search/Actions/GetRelatedToursAction.php`
- `backend/app/Domains/Search/Actions/IndexTourAction.php`
- `backend/app/Models/TourTranslation.php`
- `backend/app/Models/TourOption.php`
- `backend/app/Models/TourOptionTranslation.php`
- `backend/app/Domains/Partner/Models/TourMedia.php`
- `backend/app/Domains/Partner/Models/PricingTier.php`
- `backend/app/Domains/Partner/Models/AvailabilityRule.php`
- `backend/app/Domains/Partner/Models/AvailabilityException.php`
- `backend/app/Providers/EventServiceProvider.php`
- `backend/app/Domains/Booking/Actions/CreateBookingAction.php`

Tour and translation-state models also appear in S1; their cache hooks belong here. Current direct detail invalidators are in `Tour`, `TourTranslation` and `TourTranslationState`. `EventServiceProvider` independently registers Tour/translation/category saved/deleted homepage tag invalidation; PRESERVE it and place the approved changes after commit. Never replace that behavior with detail-only eviction or global `Cache::flush()`. Inspect option/media/pricing/availability mutation callers to retain or add the current-version detail eviction needed by the new cache keys, without unrelated inventory redesign.

Existing booking creation already locks/reloads Tour and the option. Its code changes only if a reproduced gap remains; otherwise this slice adds a snapshot/concurrency test. No existing booking row, voucher feature or payment path is rewritten. Tests own read/cache-fill versus promotion, cache-hit visibility, price/ownership/unpublish/archive invalidation, failed/reconciled derivative states, coherent cards/index payload, source-locale fallback/SEO, unchanged confirmed option snapshots and concurrent new booking.

## S6 — Partner API, editor and browser acceptance

Create:

- `backend/app/Domains/Partner/Controllers/TourRevisionController.php`
- `backend/app/Domains/Partner/Requests/StoreTourRevisionRequest.php`
- `backend/app/Domains/Partner/Requests/UpdateTourRevisionRequest.php`
- `backend/app/Domains/Partner/Requests/RespondToTourChangeRequest.php`
- `frontend/src/components/partner/tours/TourRevisionPanel.tsx`
- `frontend/src/components/partner/tours/__tests__/TourRevisionPanel.test.tsx`
- `frontend/tests/e2e/partner/tour-revision.spec.ts`

Modify:

- `backend/routes/api/partner.php`
- `backend/app/Domains/Partner/Controllers/TourController.php`
- `frontend/src/lib/api/partner.ts`
- `frontend/src/lib/api/types.ts`
- `frontend/src/types/partner.ts`
- `frontend/src/app/[locale]/(partner)/partner/tours/[id]/edit/page.tsx`
- `frontend/src/app/[locale]/(partner)/partner/tours/[id]/edit/__tests__/page.test.tsx`
- `frontend/messages/en.json`
- `frontend/messages/es.json`
- `frontend/messages/it.json`
- `frontend/tests/e2e/a11y/partner-a11y.spec.ts`

The last file already has a parent-owned exact-main Next locator correction, proven by eight baseline browser passes; preserve it. New tests use the real editor, API and Filament flow. Initial and published request/response, full candidate reload/new-ID replacement, conflicts and separate mixed saves have specific Jest/Pest/browser owners, not API-call-count-only tests. If existing auth/setup fixtures suffice, do not modify them. Admin browser authentication uses the real existing Filament login/setup; no fabricated approval endpoint.

## Verification commands and environment

The orchestrator prepared independent copied filesystems and disposable PostgreSQL. `bookly-egm003-tests` uses PHP 8.3.36 with JPEG and the repository-pinned `bookly-test-postgres/bookly_test`; run DB suites serially. `bookly-egm003-app`, `bookly-egm003-postgres`, `bookly-egm003-web`, `bookly-egm003-nginx` form the isolated browser stack. Its database is `bookly_accept`, not development data. Never log provider secrets/tokens. Test artifacts remain local, not CI evidence.

Focused backend command (extend with existing regression paths listed above in the broader pass):

```sh
php vendor/bin/pest tests/Feature/Partner/TourRevisionGovernanceTest.php tests/Feature/Partner/TourRevisionTranslationTest.php tests/Feature/Admin/TourRevisionGovernanceTest.php tests/Feature/Admin/TourRevisionConcurrencyTest.php tests/Feature/Admin/Filament/TourContentGovernanceTest.php tests/Feature/Search/TourRevisionProjectionTest.php tests/Feature/Booking/TourRevisionBookingSnapshotTest.php --compact --do-not-cache-result
php vendor/bin/pest tests/Feature/Admin tests/Feature/Partner tests/Feature/Search tests/Feature/Booking tests/Feature/Translation --compact --do-not-cache-result
php vendor/bin/pint --test
php vendor/bin/phpstan analyse --no-progress --memory-limit=1G
```

Run `php -l` on every changed PHP file after edits. Round-trip the NEW migration on a disposable schema before adopting data. Do not roll back accepted revisions to satisfy a gate. Broaden tests further if cross-domain failures justify it.

From frontend:

```sh
node node_modules/jest/bin/jest.js --runInBand --runTestsByPath src/components/partner/tours/__tests__/TourRevisionPanel.test.tsx 'src/app/[locale]/(partner)/partner/tours/[id]/edit/__tests__/page.test.tsx' src/lib/stores/__tests__/tourWizard.test.ts src/components/partner/tours/__tests__/TourWizard.test.tsx src/components/partner/tours/__tests__/TourWizard.locale.test.tsx
npm run typecheck
npm run lint -- src/components/partner/tours/TourRevisionPanel.tsx 'src/app/[locale]/(partner)/partner/tours/[id]/edit/page.tsx' src/lib/api/partner.ts src/lib/api/types.ts src/types/partner.ts tests/e2e/partner/tour-revision.spec.ts tests/e2e/a11y/partner-a11y.spec.ts
npm run build
```

Broader Jest includes TourDetail/Card and other affected suites. Browser commands run inside the copied web runtime with `DOCKER_ENV=true PLAYWRIGHT_BASE_URL=http://nginx`; warm measured cold routes first without changing test timeouts:

```sh
node node_modules/@playwright/test/cli.js test --project=setup --grep 'authenticate as partner' --reporter=line --output /tmp/egm003-auth
node node_modules/@playwright/test/cli.js test tests/e2e/partner/tour-revision.spec.ts --project=chromium-partner --no-deps --reporter=line --output /tmp/egm003-revisions
node node_modules/@playwright/test/cli.js test tests/e2e/a11y/partner-a11y.spec.ts --project=a11y-partner --no-deps --reporter=line --output /tmp/egm003-a11y
node node_modules/@playwright/test/cli.js test tests/e2e/partner/tour-create.spec.ts --project=chromium-partner --no-deps --grep 'Supplier A2 source selection' --reporter=line --output /tmp/egm003-a2
```

Each run must report actual commands, exit status, counts and warnings. Independently review test edits first, apply clean-code/test/docs guard passes and obtain a NEW fresh-context final implementation Goldfish. No schema/production code may start until this manifest and active companion synchronization gate pass. No current test result is a promise that the new feature works.
