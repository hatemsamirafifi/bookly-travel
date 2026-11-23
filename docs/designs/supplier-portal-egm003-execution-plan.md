# EGM-003 execution file and test manifest

Date: 2026-11-14. Baseline `f07d3d0`. [Technical design v3](supplier-portal-egm003-governance-v3.md).
Status: design/manifest approved before implementation on 2026-11-14; S1–S6 implementation and Round 1 Goldfish remediations are complete as of 2026-11-17, with final verification gates and Round 2 Goldfish audit running for closure. Provider/coordination paragraphs below retain the preceding execution history.

The orchestrator owns review, commands/evidence, scope corrections and commits. OpenCode owns implementation in this manifest. Dispatch started with MiniMax, continued with GLM and then owner-selected Nemotron/OpenRouter-free; each reached its recorded quota limit. The owner then selected `apmix/deepseek-v4.1-flash-1017-free` with `high` reasoning. Execution now proceeds one bounded repair slice at a time, beginning with the owned revision API. Implementers must preserve concurrent edits and stop before an unlisted path is needed; the orchestrator first updates the manifest from actual caller evidence. Do not reset/stash/clean/switch branches or commit from the implementer.

At the in-progress review checkpoint, backend ownership was split without expanding these file boundaries: core governance owns S1–S3 and the backend S6 API; translation/public projection owns S4–S5, including the existing Tour/translation models and public cache hooks; frontend owns frontend S6. Core retains sole execution access to the shared PostgreSQL test runner until explicit release. Projection tests are written separately and executed serially after release. The shared pure `TourTranslationService::sourceHashFromSnapshot(source, options)` contract computes candidate identity with the existing hash bytes; core consumes it without changing the translation implementer's file. This is an implementation coordination checkpoint, not an acceptance or design-policy change.

After the quota failures, the active ApMix slice is limited to the existing Partner revision service/payload/exception, TourService, content/revision Form Requests, partner controllers/routes and their owned API tests/fixtures. It has sole Pest-runner access; the orchestrator owns the separate browser stack. The other S1–S6 integrations remain required subsequent work. The snapshot-hash helper exists from the Nemotron projection attempt; parity and integration acceptance remain pending.

That ApMix dispatch subsequently stopped at its reported monthly quota (HTTP 429), without completing the repair. No implementer retains the runner after process exit; execution ownership must be assigned again on resumption. The bounded slice and all remaining acceptance requirements are unchanged.

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
- `backend/resources/views/filament/tours/revision-comparison.blade.php`

Modify:

- `backend/app/Domains/Admin/Services/AdminAuthorizationService.php`
- `backend/app/Filament/Resources/TourResource.php`
- `backend/app/Filament/Resources/TourResource/Pages/CreateTour.php`
- `backend/app/Filament/Resources/TourResource/Pages/EditTour.php`
- `backend/app/Filament/Resources/TourResource/Pages/ListTours.php`
- `backend/app/Filament/Resources/TourResource/Pages/ViewTour.php` *(unchanged: view screen schema is owned by `TourResource::infolist()`)*
- `backend/app/Filament/Resources/AdminRoleResource.php` *(unchanged conditional path: dynamically consumes `AdminAuthorizationService::FLAGS`)*
- `backend/app/Filament/Resources/AdminUserResource.php` *(unchanged conditional path: dynamically consumes `AdminAuthorizationService::FLAGS`)*

Permission resources may change only to expose the new explicit correction permission if their actual field lists require it; no default grant or role-template expansion. Tests own forged source/derivative/general-field writes, read-only generated content, mandatory reason/field refs, actual compared revision UI, stale modal approval, permission revocation, correction audit/ordinary review and initial-review requests. Keep demo generation's existing refusal unchanged.

The comparison view is a read-only projection of the captured approved snapshot and exact proposed revision, with escaped values for all four governed tiers. EditTour captures the proposed revision ID when opening an approve/reject modal and submits that ID to the existing domain Actions; it never resolves a replacement candidate at confirmation time. Initial-review request controls reuse the documented never-public lifecycle and reason/field-reference contract. This view adds no content editing path, derivative controls or publication bypass.

## S4 — Gemini generation epoch and legacy retirement

Create:

- `backend/tests/Feature/Partner/TourRevisionTranslationTest.php` *(planned placeholder; consolidated into existing `TourTranslationTest.php`, `TourTranslationJobStateTest.php`, `TourTranslationConcurrencyTest.php`, `TourOptionsTest.php`, and `Admin/TourRevisionGovernanceTest.php` — never created separately)*

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
- `backend/app/Domains/Search/Actions/ReconcileTourScoutJobAction.php`
- `backend/tests/Feature/Search/TourRevisionProjectionTest.php`
- `backend/tests/Feature/Booking/TourRevisionBookingSnapshotTest.php` *(planned placeholder; consolidated into existing `Booking/TourOwnerEligibilityTest.php` and `TourRevisionGovernanceTest.php` — never created separately)*

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

Owner-approved public eligibility exception (2026-11-14) additionally affects:

- Modify `backend/app/Domains/Partner/Models/Partner.php` to expose/reuse the approved+active public eligibility predicate and invalidate affected tour representations on an eligibility change after commit.
- Modify the existing public/cache/search/booking files above only where required by that predicate. SSR/SEO consumers and their existing tests may change only after a concrete denial-handling gap is found; record each actual path before editing it.
- Create `backend/tests/Feature/Search/TourOwnerEligibilityTest.php` and `backend/tests/Feature/Booking/TourOwnerEligibilityTest.php` for stale-cache/inconsistent-state, discovery/SEO denial, independent new-booking refusal and existing-snapshot preservation. Extend `backend/tests/Support/TourRevisionWorker.php` only for reproduced eligibility races.
- Update `backend/tests/Feature/Search/TourDetailTest.php` only where the explicit owner exception replaces the old unapproved-operator 200 assertion or a positive public-authoring fixture still uses legacy `complete`; preserve all gallery/source and private-data assertions. Dedicated negative coverage retains the legacy-status denial.
- The broader Search gate additionally affects `backend/tests/Feature/Search/PublicOperatorSummaryTest.php` (two superseded owner-denial expectations) and `backend/tests/Feature/Search/TourAvailabilityLogicTest.php` (explicit eligible/missing/inactive owner facts in its relation-only bookability fixtures). Preserve the existing five-query operator bound and all privacy/availability assertions; reuse the known same-owner relation rather than raising the bound.
- The broader Partner domain gate (`bookly-egm003-broad-domain-gate-512.log`, 3 failures / 1,376 passes) additionally affects `backend/tests/Feature/Partner/TourAfterCommitContainmentTest.php` lines 155 and 180 (`shouldBeSearchable()` now requires an `approved` partner owner, whereas `Spec019CommittedFixtures::createOwner` creates `complete`). Update that test fixture's partner owner to `approved` without weakening `shouldBeSearchable()` or any containment assertion.

Additional actual callers found by the independent owner-exception review:

- Create `backend/app/Domains/Search/Services/TourPublicEligibilityService.php` for the fresh public delivery guards defined in design section 8.1.
- Modify `backend/app/Domains/Search/Controllers/Public/SearchController.php`, `HomepageController.php`, `CategoryController.php`, `DestinationController.php` and `SitemapController.php` in that same directory, preserving response contracts and internal TTLs while guarding cache hits.
- Extend `backend/tests/Feature/Search/SitemapTest.php` with the reviewed 10,000-tour cold/hot query and memory measurements, retaining real XML/route assertions and the existing 500-row generation boundary. Owner-withdrawal/header regressions remain in `TourOwnerEligibilityTest.php`.
- Modify `backend/app/Domains/Search/Actions/GetPublicOperatorSummaryAction.php`, `backend/app/Domains/Search/Actions/RemoveFromIndexAction.php` and `backend/app/Domains/Search/Support/DestinationAggregator.php` *(unchanged conditional path: existing implementation already conforms to eligible public delivery predicate)* only for shared eligibility, batch loading and final-state index/cache correctness.
- Modify `backend/app/Domains/Blog/Actions/GetBlogPostAction.php` *(unchanged conditional path: existing query already restricts to eligible public tour projection)* and `backend/app/Domains/Blog/Transformers/BlogPostDetailTransformer.php` only for eligible public related-tour projection; preserve blog authoring/caching. Extend `backend/tests/Feature/Blog/BlogPostTest.php` for the public related-tour regression.
- The scoped detail Goldfish review found another public slug lookup in `backend/app/Domains/Reviews/Controllers/Public/ReviewController.php::index`. Apply the same fresh eligible-owner query predicate before returning that tour's public review list/aggregates; extend `backend/tests/Feature/Search/TourOwnerEligibilityTest.php`. Preserve review submission/editing and purchased-review authorization, and do not change unrelated lifecycle rules for this endpoint.
- Modify `frontend/src/lib/api/search.ts`, `homepage.ts`, `categories.ts` and `destinations.ts` in that same API directory to disable the outer Next fetch cache for tour-bearing responses. Update the existing `frontend/src/lib/api/__tests__/caching.test.ts` and `frontend/src/app/[locale]/(public)/tours/[slug]/__tests__/page.test.tsx` *(unchanged conditional path: SSR/metadata denial already covered by existing integration suites)* for discovery-cache and SSR/metadata denial; no detail-page redesign is authorized.

This owner decision supersedes the old assumption that every published but unbookable tour remains public: 200/unavailable now requires an eligible owner. It does not authorize A3/A5 or change existing purchased bookings. Reproduce any booking owner-lock gap before adding a lock, following the order in design section 8. No public eligibility implementation pass is claimed.

Tour and translation-state models also appear in S1; their cache hooks belong here. Current direct detail invalidators are in `Tour`, `TourTranslation` and `TourTranslationState`. `EventServiceProvider` independently registers Tour/translation/category saved/deleted homepage tag invalidation; PRESERVE it and place the approved changes after commit. Never replace that behavior with detail-only eviction or global `Cache::flush()`. Inspect option/media/pricing/availability mutation callers to retain or add the current-version detail eviction needed by the new cache keys, without unrelated inventory redesign.

The implementation review found Scout's secondary queue boundary. Design section 8.2 adds the named `ReconcileTourScoutJobAction` handler; its Bus class map belongs in `EventServiceProvider`, batch/single-ID locked projection in `TourPublicProjectionService`, and synchronous worker writes in `IndexTourAction`. Extend the existing `TourOwnerEligibilityTest.php` for actual queue-array configuration, stock serialized command dispatch, observer preservation and bulk batch checks. Preserve the Tour model's observer/queue dispatch containment and all translation epoch behavior; no vendor or global engine replacement is authorized. Obtain the scoped readiness review before implementing this correction.

Existing booking creation locks/reloads Tour and rereads the option; reinspection found no explicit option row lock. Its code changes only for a reproduced gap, including the newly owner-approved eligibility guard and owner-first locking if demonstrated. No existing booking row, voucher feature or payment path is rewritten. Tests own read/cache-fill versus promotion, cache-hit visibility, price/ownership/unpublish/archive invalidation, failed/reconciled derivative states, coherent cards/index payload, source-locale fallback/SEO, unchanged confirmed option snapshots and concurrent new booking.

## S6 — Partner API, editor and browser acceptance

Create:

- `backend/app/Domains/Partner/Controllers/TourRevisionController.php`
- `backend/app/Domains/Partner/Requests/StoreTourRevisionRequest.php`
- `backend/app/Domains/Partner/Requests/UpdateTourRevisionRequest.php`
- `backend/app/Domains/Partner/Requests/RespondToTourChangeRequest.php`
- `frontend/src/components/partner/tours/TourRevisionPanel.tsx`
- `frontend/src/components/partner/tours/__tests__/TourRevisionPanel.test.tsx`
- `frontend/src/components/partner/tours/__tests__/BookingOptionsForm.test.tsx`
- `frontend/tests/e2e/partner/tour-revision.spec.ts`

Modify:

- `backend/routes/api/partner.php`
- `backend/app/Domains/Partner/Controllers/TourController.php`
- `frontend/src/lib/api/partner.ts`
- `frontend/src/lib/api/types.ts`
- `frontend/src/types/partner.ts`
- `frontend/src/components/partner/tours/BookingOptionsForm.tsx`
- `frontend/src/app/[locale]/(partner)/partner/tours/[id]/edit/page.tsx`
- `frontend/src/app/[locale]/(partner)/partner/tours/[id]/edit/__tests__/page.test.tsx`
- `frontend/messages/en.json`
- `frontend/messages/es.json`
- `frontend/messages/it.json`
- `frontend/tests/e2e/a11y/partner-a11y.spec.ts`

The last file already has a parent-owned exact-main Next locator correction, proven by eight baseline browser passes; preserve it. New tests use the real editor, API and Filament flow. Initial and published request/response, full candidate reload/new-ID replacement, conflicts and separate mixed saves have specific Jest/Pest/browser owners, not API-call-count-only tests. If existing auth/setup fixtures suffice, do not modify them. Admin browser authentication uses the real existing Filament login/setup; no fabricated approval endpoint.

Pre-code caller discovery confirmed that `BookingOptionsForm` has only an all-fields `disabled` prop. Add a backward-compatible structure-only control to hide add/remove on published products while keeping permitted text and operational fields editable. Default behavior in the creation wizard must stay unchanged. The named component regression owns both default creation behavior and published structure restrictions; this is the v3 fail-closed boundary, not new A3 functionality.

## Verification commands and environment

The orchestrator prepared independent copied filesystems and disposable PostgreSQL. `bookly-egm003-tests` uses PHP 8.3.36 with JPEG and the repository-pinned `bookly-test-postgres/bookly_test`; run DB suites serially. `bookly-egm003-app`, `bookly-egm003-postgres`, `bookly-egm003-web`, `bookly-egm003-nginx` form the isolated browser stack. Its database is `bookly_accept`, not development data. Never log provider secrets/tokens. Test artifacts remain local, not CI evidence.

Focused backend command (extend with existing regression paths listed above in the broader pass):

```sh
php vendor/bin/pest tests/Feature/Partner/TourRevisionGovernanceTest.php tests/Feature/Partner/TourRevisionTranslationTest.php tests/Feature/Admin/TourRevisionGovernanceTest.php tests/Feature/Admin/TourRevisionConcurrencyTest.php tests/Feature/Admin/Filament/TourContentGovernanceTest.php tests/Feature/Search/TourRevisionProjectionTest.php tests/Feature/Booking/TourRevisionBookingSnapshotTest.php --compact --do-not-cache-result
php vendor/bin/pest tests/Feature/Admin tests/Feature/Partner tests/Feature/Search tests/Feature/Booking tests/Feature/Translation tests/Feature/Blog --compact --do-not-cache-result
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

The owner subsequently selected `agy-delegate` with `gemini-3.8-flash-high` and `high` effort after the documented OpenCode provider limits. The first Antigravity headless run auto-denied a command permission. Later read-only proposals were reviewed and applied by the orchestrator for the owned API, publication sealing and public cards; the verification report records their actual focused results and unresolved integrations. This supersedes the earlier active OpenCode/ApMix assignment without rewriting its history. The orchestrator retains sole shared PostgreSQL-runner access. Owner-detail eligibility is the current bounded proposal after renewed local authentication; no implementation pass follows from dispatch.

The independently reviewed frontend outer-cache change also requires correcting the existing route documentation that states a 300-second revalidation interval. These five documentation paths are added from actual caller evidence; no route implementation is added:

- `frontend/src/app/[locale]/(public)/search/README.md`
- `frontend/src/app/[locale]/(public)/categories/README.md`
- `frontend/src/app/[locale]/(public)/categories/[slug]/README.md`
- `frontend/src/app/[locale]/(public)/destinations/README.md`
- `frontend/src/app/[locale]/(public)/destinations/[slug]/README.md`


Current delegation checkpoint (2026-11-15): after the recorded provider quota failures, the owner selected Antigravity Gemini 3.8 Flash High (`gemini-3.8-flash-high`, high effort). Headless write permission was unavailable, so bounded read-only patch proposals are reviewed and applied by the orchestrator. Native fresh-context reviewers inspect actual code independently. The orchestrator alone runs PostgreSQL tests. No implementer is authorized to commit or alter global permissions.
