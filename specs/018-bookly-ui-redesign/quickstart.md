# Validation Quickstart

This guide validates the translation vertical slice and then the broader UI redesign. It does not require a live Gemini request in automated tests.

## Prerequisites

- PostgreSQL/Redis test services and backend/frontend dependencies are installed.
- Apply additive migrations before running the new backend code.
- For deployment, replace the exposed key with a **newly rotated** `GEMINI_API_KEY` in the backend secret environment. Never put it in frontend environment, tests, logs or Git. At the user's explicit request, the exposed key is temporarily configured in the ignored local `backend/.env` for continuous local translation; it should not be reused for production.
- Start the Laravel queue worker for the translation queue. Keep tests on a fake provider/queue.

## Focused automated checks

```text
backend: php artisan test tests/Feature/Partner/TourCreateTest.php tests/Feature/Search/TourDetailTest.php
backend: php artisan test tests/Feature/Partner/TourTranslationTest.php
backend: php artisan test tests/Feature/Admin/TourModerationTest.php
backend: vendor/bin/pint --test
backend: vendor/bin/phpstan analyse
frontend: npm test -- --runInBand
frontend: npm run typecheck
frontend: npm run lint
frontend: npm run test:e2e -- tests/e2e/tour-detail.spec.ts tests/e2e/partner/tour-edit.spec.ts
```

Use the actual test filenames created by implementation if they differ. Record pass/fail and environment in release evidence.

## Local verification record (2026-09-25)

### Current implementation check

- Reproduced a failing optional-EN-field clear test: sending `translations.en.meeting_point: null` left the prior value in place. Updated the partner service to preserve explicit nulls for optional translated fields. The focused partner creation/translation suites then passed: **19 tests / 97 assertions**. `TourCreateTest` now fakes the queue so tests cannot invoke the live Gemini adapter through the local synchronous test queue.
- Added shared-primitive accessibility assertions and Bookly font/focus/motion tokens. The new Jest suite passed **3 tests**, TypeScript typecheck passed, and the Next.js production build passed.
- Added explicit public tour-detail and partner content-response contract assertions. The focused partner-create and public-detail suites passed: **27 tests / 168 assertions**.
- Verified the tour-detail component keeps ordered localized itinerary and guide languages while omitting empty optional sections and retaining the booking action: **11 Jest tests passed**. The browser-level tour-detail checks remain open.
- The guest mobile navigation Playwright test passed on Chromium at 390px with reduced motion: focus enters the dialog, wraps, Escape closes it and focus returns to the trigger. Scoped ESLint, TypeScript typecheck and `git diff --check` passed. An earlier run inside the existing authenticated group failed in its login `beforeEach`, before reaching the new menu assertions; the guest test was separated from that fixture.
- Reproduced the missing post-ready search refresh and added an after-commit index job when a revision-current derivative becomes ready. The focused projection test passed **1 test / 8 assertions**, checking ready ES in the search document/card and EN fallback after the source edit. This does not substitute for a live Meilisearch integration run.
- Shared public/auth/traveler/partner shells now use the Bookly palette and a localized footer/navigation where applicable. Browser checks passed for EN/ES/IT auth shells (**3**), authenticated traveler shell (**1 plus 2 setup**), partner mobile open/close (**1 each plus 2 setup each**), and guest mobile focus/reduced-motion (**1**). The partner dashboard keeps its distinct operational sidebar rather than duplicating the public header. Full four-width/locale visual acceptance is still outstanding.
- The complete current Chromium homepage/search browser files passed **34 tests** after preserving existing accessible filter labels, with new back/forward, query-preserving clear, mobile filter dialog and ES/IT heading checks. Scoped Jest passed **19 tests across 3 suites** (`useFilters`, design primitives and tour detail); discovery typecheck and ESLint passed. Homepage editorial/partner-invitation and full visual acceptance (T010) remain open.
- Tour cards now keep the wishlist button outside the tour link, omit unearned zero-review ratings and use shared surface/border tokens. Category/destination listing shells and metadata use localized UI labels, while preserving the existing slug-based scope and search API contract. Pagination labels are localized. Focused Jest passed **14 tests**, TypeScript typecheck and scoped ESLint passed, the production build completed, and category/destination Chromium plus Axe checks passed **9 tests** including EN/ES/IT listing headings. Category/destination names remain backend-provided or slug-derived; this slice does not add translated category taxonomy data.
- Homepage discovery now includes eligible public-blog editorial cards only when the blog API returns posts, plus a localized secondary partner-registration invitation. Blog failure no longer removes the working tour discovery sections. Three deterministic homepage Jest tests, scoped ESLint/typecheck, the production build, and **8 Chromium homepage browser tests** passed. This closes the implementation slice T010; the full visual/performance release matrix remains open.
- Difficulty and ordered gallery media now round-trip through validated partner create/update; selected covers lead the public images array, and a legacy cover alone still renders. The new behavior was first reproduced as failing tests, then the focused partner/detail suites passed **32 tests / 194 assertions**. Scoped Pint passed after formatting, and scoped PHPStan passed with a 1G per-process limit (the default 128M run exhausted memory before analysis completed). The test PostgreSQL container was started for these checks and remains running. Operator/related-tour work is a separate unfinished slice.
- Public tour detail now returns a nullable, approved-only operator summary from an explicit safe-field allowlist. Its review aggregate uses only visible/flagged reviews on bookable tours. Related cards exclude the current, unpublished, unbookable and irrelevant tours, ranking destination then category then review evidence. These were covered by initially failing feature tests; the expanded partner/detail suites passed **35 tests / 211 assertions**, scoped frontend typecheck and Pint passed, and scoped PHPStan passed after typing the existing partner relation. Backend-to-browser presentation of these new fields remains in T054–T055.
- Tour detail now has a focusable gallery trigger, focus-trapped keyboard lightbox with Escape focus return, visible-section navigation with scroll offsets, genuine facts, optional public operator/related sections, and a mobile booking link gated by a real date and price. The focused tour Jest suites passed **16 tests**, scoped frontend typecheck and ESLint passed, the Next.js production build completed, the full Chromium tour-detail file passed **16 tests** after updating an obsolete no-review expectation, a focused mobile booking browser test passed, and the tour-detail Axe check passed **1 test**. The fixture has no genuine reviews, so the review section/nav are omitted. The browser fallback-to-ready translation transition and full viewport/locale visual matrix are still outstanding.
- Tour structured data and metadata now use the actual content locale, ordered itinerary positions, safe complete image URLs, truthful meeting-point description and review/availability claims gated by real data. JSON-LD escapes markup-bearing text. The new SEO Jest suites passed **6 tests**, scoped TypeScript and ESLint passed, the production build completed, and the new Chromium structured-data assertion passed **1 test**. This closes T056 but not the full SEO/performance release gates.
- The previously heading-only partner analytics route now renders the existing data-backed summary and bookings chart in the shared partner shell, including loading/error/empty states. A browser regression with a mocked `12345` minor-unit revenue confirms `€123.45`, and missing conversion denominator renders “Not available” rather than `0.0%`. A 390px browser check exposed missing Escape/focus restoration in the mobile partner drawer; that was fixed in the shared layout. The analytics browser file passed **12 tests** before the new money/mobile assertions; their first focused run passed the money assertion but failed focus restoration, and the corrected 390px rerun passed **3 tests including authentication setup**. Scoped TypeScript, ESLint and the production build passed. Full partner navigation/browser release gates remain open.
- New checkout browser tests exposed a price-drift control bug: the form set the payment step before the traveler accepted the new backend price, hiding the price-change dialog. Payment state is now deferred until explicit confirmation. A failed cancellation retains the dialog with a visible retry error rather than silently dismissing it; successful cancellation rotates the selection's idempotency key. The first browser run reproduced both failures; after repair, the focused checkout run passed **4 tests including authentication setup**, with scoped TypeScript/ESLint and the production build passing. T019–T021 remain open pending the rest of checkout/payment/confirmation coverage and layout work.
- Checkout now uses a mobile-first summary and desktop two-column layout, with localized date, participant and price labels in EN/ES/IT. The payment step retains the tour summary and displays the confirmed backend total; Stripe Elements and the existing idempotent booking request remain in place. The expanded checkout/payment Chromium run passed **15 of 16 tests**; the only failure was an ambiguous Playwright alert locator (the Next route announcer also has `role=alert`), not a product assertion. After narrowing the locator, the focused payment retry rerun passed **3 tests including setup**. The existing BookingForm Jest regression passed **1 test**; scoped TypeScript, ESLint and production build passed. T019–T020 are closed; final confirmation/cancellation state coverage T021 remains open. This is not a live Stripe charge test.
- Confirmation now reserves the success heading for genuinely confirmed/completed bookings; pending, cancelled, expired and unexpected statuses cannot claim success, and the static tab title is status-neutral. The unknown-status case failed against the old UI first. After the fix, the four non-success browser states passed **6 tests including authentication setup**, and the confirmed-state browser check passed **3 tests including setup**. Scoped TypeScript, ESLint and production build passed. T021 is closed; these status-presentation mocks do not replace the existing real booking/idempotency E2E test or a Stripe live run.
- Traveler guard browser regressions now exercise EN/ES/IT return paths, narrow/wide account routes and login return-to-origin. The first six-test focused run passed five and exposed a real redirect race: after login, the guest-page guard sent the user home while the login form sent them back to the protected page. Both now use a shared same-origin, active-locale return URL validator; its **6 Jest cases** reject external/cross-locale targets. Scoped TypeScript, ESLint and production build passed, and the previously failing login-return browser test passed on rerun. T022 is closed; broader account-page redesign T023 and eligibility states T024 remain open.
- Profile and my-bookings pages now share a responsive traveler page shell; profile/prefs/password inputs use Bookly form surfaces and focus styles, with field errors tied to their inputs. A failed password update now retains entered fields; its two Jest success/failure cases passed. Scoped TypeScript, ESLint and production build passed. Chromium account-page overflow checks passed at **390px and 1440px**, and the authenticated profile navigation/settings/marketing checks passed **5 tests including setup**. T023 is closed; wishlist/review eligibility and auth/recovery form routes remain separate tasks.
- The blog index now renders its heading, count and empty-state recovery in the selected EN/ES/IT UI locale rather than hardcoded English; existing server-side unavailable handling is preserved. Terms now use the shared public surface/spacing tokens without changing the legal copy. Scoped TypeScript, ESLint and production build passed, and the combined blog-index plus mobile terms Chromium suite passed **8 tests**. T044 is closed; blog-detail/voucher and other supporting-route visual work remain open.
- Supporting-page browser regressions passed on Chromium: the ES blog article remains readable at 390px, and the root voucher uses real ES/IT browser locale negotiation while preserving noindex, read-only public navigation and no horizontal overflow (**3 targeted tests passed**). The existing blog-detail and voucher suites had also passed **8 tests** before the new cases. T043 is closed; visual integration of the remaining supporting routes is tracked separately in T061.
- Privacy, root voucher, blog-preview and locale 404 surfaces now use the Bookly public design tokens; the preview keeps its existing token guard/noindex and the root voucher remains unauthenticated, read-only and locale-negotiated. Scoped TypeScript and ESLint passed, the production Next.js build completed, and combined Chromium supporting/preview/invalid-slug/voucher suites passed **21 tests** including EN/ES/IT privacy and 404 mobile checks. T061 is closed.
- Login, registration, forgot-password and reset-password pages now share one responsive auth shell and the existing shared input/button primitives. The forgotten-password control is again a real link; registration validates its return destination using the same active-locale helper as login. Existing form validation/redirect unit suites passed **18 tests**, scoped TypeScript/ESLint passed, the production build completed, and EN/ES/IT auth-shell plus 390px recovery/navigation browser checks passed **6 tests**. T057 is closed; this does not claim a live password-reset email or registration transaction.
- Partner tour creation and editing now expose ordered English itinerary days/stops, image reordering, cover selection and a content/gallery preview. The edit page round-trips media order and cover through the existing partner API; creation sends structured itinerary from the wizard. The API validates nested itinerary fields on both create and update. The browser edit regression initially failed on the absent editor, then passed after implementation including a mocked save/reload round-trip (**3 tests including partner/traveler setup**); the draft-create payload regression also passed (**3 including setup**). Partner backend creation/update tests passed **14 tests / 62 assertions**, itinerary validator Jest passed **19 tests**, scoped TypeScript/ESLint, Pint, `git diff --check` and the production frontend build passed. A previously inconsistent frontend description minimum was aligned to the backend's 100-character rule. T058–T059 are closed; this evidence does not assert a live object-storage upload.
- Filament tour moderation now separates the required EN title/description/itinerary from ES/IT AI-derived status; raw hashes and errors are not rendered. The admin panel uses the shared Bookly navy/gold palette, Plus Jakarta Sans, a branded logo, surface/shadow/focus tokens and reduced-motion CSS through Filament 3's panel hooks, without moving admin routes or changing policy. Browser authorization regressions cover guest redirects and denied partner login. The full admin browser suite passed **7 Chromium tests**; backend moderation plus Filament action/permission suites passed **13 tests / 68 assertions**, including one audit record per bulk decision. Scoped Pint passed. The first browser theme assertion failed because the running PHP-FPM container had OPcache timestamp validation disabled; restarting that container loaded the changed panel provider and the full suite then passed. T041–T042 and T060 are closed. This does not claim a manual visual sign-off at every viewport.
- A deterministic SSR browser fixture now checks both ES and IT tour pages while their derivative is pending (current English title/description/itinerary, visible locale-specific notice and independent localized guide-language labels), then switches the isolated API response to ready and reloads to confirm the localized title/itinerary, absent fallback notice and correct JSON-LD `inLanguage`. It runs a separate local Next process because Playwright browser request interception cannot override server-side page fetching; it never mutates shared tour rows. This new Chromium regression passed **1 test**; scoped TypeScript and ESLint passed. Existing detail browser checks cover keyboard gallery focus return and real offered-date booking controls; the partner edit browser test checks EN-only authoring and derived statuses. T018 and T026 are closed. The isolated fixture verifies frontend response handling, while backend feature tests verify how real translation states produce those responses.
- Stronger traveler browser assertions exposed that the previous review test targeted a confirmed booking and skipped the absent form. The corrected test targets seeded completed booking `BKO-TEST02`, verifies confirmed `BKO-TEST01` cannot submit, and checks ES/IT translated controls. It also exposed duplicate top-level `reviews` JSON keys that hid the full EN/ES/IT review dictionary; the duplicate overrides were removed. Wishlist removal revealed that `apiClient` parsed the backend's successful empty `204` as JSON, preventing query invalidation; it now handles bodyless success, while tour cards hydrate saved state from the existing status endpoint. Focused API/wishlist Jest passed **6 tests**; scoped TypeScript/ESLint and production build passed. Combined Chromium authenticated wishlist/review run passed **10 tests including setup**, and ES/IT review controls passed **4 including setup**. T024 is closed.
- Search now has browser-verified empty-state recovery, a deterministic validation-error state without fake result counts, and a visible localized pending announcement during same-route client navigation. The old `loading.tsx` alone did not appear for query-only transitions, so SearchBar uses a React transition and `aria-busy`. Focused TypeScript/ESLint and the production build passed. The complete Chromium search file passed **29 tests**, including mobile filter focus/close, three viewport widths, EN/ES/IT queries, URL/back/forward, and the new empty/error/loading cases. T012 is closed.
- These chronological notes include superseded "open" statements for slices completed later in this record. T026 and the other implementation slices are now checked; full backend/frontend gates and Lighthouse/four-width release evidence are still pending. Do not read focused passes as a full-phase pass.

- Docker PostgreSQL test database: the seven focused Laravel suites passed, **88 tests / 357 assertions**, covering EN-only authoring/publishing, ES/IT revision states, itinerary and guide-language separation, public fallback, provider failures, search indexing and detail responses.
- Frontend component tests: **12 passed** across `TourDetail` and `StructuredData` suites. TypeScript typecheck and scoped ESLint passed.
- Playwright Chromium partner create/edit: **4 passed including the two authentication setup tests** after rebuilding the local production frontend; the browser assertions confirm the EN-source notice, EN-only submission and visible ES/IT status labels.
- The local Next.js production build completed successfully.
- Laravel Pint check passed on the translation implementation files. The two additive migrations were applied to the local development PostgreSQL database; the isolated test database also exercised them through `RefreshDatabase`.
- Focused PHPStan analysis of the changed models, partner translation path and public tour detail action passed with no errors.
- Live Gemini smoke test: model access returned HTTP 200. The first translation request exposed a REST enum mismatch (`mimeType` sent as `application/json`); after changing it to `APPLICATION_JSON`, a direct request and the actual Laravel translator both returned a Spanish translation of synthetic text. No tour data was sent. Subsequently, at the user's explicit request, the disclosed key was saved in the Git-ignored local `backend/.env` and the Laravel and queue containers were restarted. Both read the configured model/key, and a further live Laravel request translated synthetic text into Italian without a per-command key override. Rotate the exposed key promptly; public fallback/ready transitions are covered by Laravel feature and frontend component tests, while a deterministic public-page browser transition test remains open in T026.
- After the live-discovered correction, the focused translation suite passed again (**10 tests / 61 assertions**), as did scoped Pint, PHPStan and `git diff --check`.
- Continuous local setup: `GEMINI_API_KEY` and `GEMINI_TRANSLATION_MODEL` were set in Git-ignored `backend/.env`; the Laravel and queue containers were restarted and both reported the translation configuration present (without printing the secret). A live Italian translation using only that persisted configuration succeeded. The bounded backfill inspected the three existing EN-source tours, queued six ES/IT jobs, and all six translation states reached `ready`. The published tour detail action returned `source`/`en` for EN and `ready`/matching content locales for ES and IT, with no fallback warning.

## Changed-file inventory

This is the feature worktree inventory captured on 2026-09-25, including earlier guide-language and itinerary edits. It is a scope record, not a statement of current staging or commit status; inspect `git status --short` and Git history for that.

```text
.specify/feature.json
backend/.dockerignore
backend/.env.example
backend/app/Console/Commands/QueueTourTranslations.php
backend/app/Domains/Admin/Actions/ApproveTourAction.php
backend/app/Domains/Partner/Contracts/TourContentTranslator.php
backend/app/Domains/Partner/Controllers/TourController.php
backend/app/Domains/Partner/Jobs/GenerateTourTranslationJob.php
backend/app/Domains/Partner/Services/GeminiTourTranslator.php
backend/app/Domains/Partner/Services/PermanentTranslationException.php
backend/app/Domains/Partner/Services/TourService.php
backend/app/Domains/Partner/Services/TourTranslationService.php
backend/app/Domains/Partner/Services/TransientTranslationException.php
backend/app/Domains/Search/Actions/GetTourDetailAction.php
backend/app/Domains/Search/Transformers/TourCardTransformer.php
backend/app/Models/Tour.php
backend/app/Models/TourTranslation.php
backend/app/Models/TourTranslationState.php
backend/app/Providers/AppServiceProvider.php
backend/config/services.php
backend/database/migrations/2026_09_25_000001_add_guide_languages_and_localized_itinerary.php
backend/database/migrations/2026_09_25_000002_create_tour_translation_states_table.php
backend/phpstan-model-properties.stub
backend/tests/Feature/Admin/TourModerationTest.php
backend/tests/Feature/Partner/TourCreateTest.php
backend/tests/Feature/Partner/TourTranslationTest.php
backend/tests/Feature/Search/TourDetailTest.php
docs/bookly-ui-redesign-spec-kit-master-plan.md
frontend/messages/en.json
frontend/messages/es.json
frontend/messages/it.json
frontend/src/app/[locale]/(partner)/partner/tours/[id]/edit/page.tsx
frontend/src/components/partner/tours/TourWizard.tsx
frontend/src/components/seo/StructuredData.tsx
frontend/src/components/seo/__tests__/StructuredData.test.tsx
frontend/src/components/tour/TourDetail.tsx
frontend/src/components/tour/__tests__/BookingCTA.test.tsx
frontend/src/components/tour/__tests__/TourDetail.test.tsx
frontend/src/lib/api/types.ts
frontend/src/types/partner.ts
frontend/src/types/tour.ts
frontend/tests/e2e/partner/tour-create.spec.ts
frontend/tests/e2e/partner/tour-edit.spec.ts
specs/018-bookly-ui-redesign/checklists/requirements.md
specs/018-bookly-ui-redesign/contracts/partner-tour-content-api.md
specs/018-bookly-ui-redesign/contracts/tour-detail-api.md
specs/018-bookly-ui-redesign/data-model.md
specs/018-bookly-ui-redesign/plan.md
specs/018-bookly-ui-redesign/quickstart.md
specs/018-bookly-ui-redesign/research.md
specs/018-bookly-ui-redesign/spec.md
specs/018-bookly-ui-redesign/tasks.md
```

## Manual acceptance sequence

1. Create a draft with EN title/description and optional itinerary, inclusions, exclusions and important information. Set live-guide codes such as `de,en,es`.
2. Submit and approve before ES/IT jobs finish. Confirm publication succeeds and ES/IT pages show current English with a visible notice, while guide labels/names follow interface locale.
3. Run queued jobs. Refresh ES/IT routes; confirm the translated title, description, itinerary and all supplied traveler-facing fields appear without warning.
4. Edit EN during an in-flight older job. Confirm status becomes stale, old output cannot become ready, and current EN is shown until a new job completes.
5. Simulate provider 429, permanent 4xx and blocked output using fakes. Confirm bounded retry or failed state, no duplicate effect, no secret/error-body leak, and continued EN fallback.
6. Verify EN publishing guard at partner submit and admin approval; verify partner cannot write derived ES/IT; verify private fields never reach Gemini payload or public JSON.

## Full release gates

Run the constitution's complete applicable backend/frontend suites, production build, browser/a11y checks at 390/768/1024/1440px and Lighthouse audits with documented conditions. Keep booking/Stripe regression tests in the release evidence. Do not claim live provider integration verified unless a rotated key is configured and an explicitly authorized non-production smoke test succeeds.

### Release-gate evidence in progress (2026-09-29–30)

Environment: local Windows/Docker Compose, backend PHP 8.3/Laravel 11, frontend Next.js production server and Playwright Chromium, working tree based on `f3e8e25` with uncommitted Phase 0 changes. These are observed results, not a release sign-off.

| Gate | Observed result | Status |
|---|---|---|
| Backend full feature/unit run | Latest full run including the EN/ES/IT homepage metadata regressions: **725 passed / 2,695 assertions**, 291.88 s. The earlier independent-partner-fixture run passed 725 / 2,689 in 163.12 s; the post-title-fix run also passed in 74.43 s. Test PostgreSQL and Meilisearch remained separate from application data. Includes four booking-list title/locale/fallback regressions. Latest report: `frontend/.phase0-evidence/backend-pest-metadata.xml`; earlier reports retained as `backend-pest-final.xml` and `backend-pest-previous.xml`. | Passed |
| Backend Pint | Fixed the two payment-file formatting issues; **full check passed** with the repository's `pint.json` (567 files in the latest metadata-inclusive gate). | Passed |
| Backend Larastan | Corrected the stale Tour partner relation type and missing migrated Stripe attributes in the existing stub; removed redundant nullsafe operators on expressions already protected by `??`. **Full analysis passed, no errors**, with `--memory-limit=512M`. No error suppression or runtime payment-routing change was introduced. | Passed |
| Frontend Jest, TypeScript, lint, build | Metadata-inclusive rerun: **43 suites / 233 tests passed**, 64.736 s; full TypeScript and lint passed. The preceding priority/contrast run also passed in 25.149 s. The metadata-refreshed production build passed (2.5 min compile; TypeScript/static generation succeeded; ready in 1,080 ms), overlapping backend checks rather than representing a controlled speed measurement. Existing React test-mock/act warnings remain visible. New performance tooling has its own Node runner, excluded explicitly from jsdom discovery: **8 native Node tests passed** and affected-script ESLint passed. | Passed |
| Browser and Axe | Latest complete 785-case run: **742 passed, 3 failed, 40 skipped, 0 not run**, 36.8 min, no retries/flaky cases. All earlier review/partner-fixture/header failures were resolved. Remaining failures: two mobile search cases skipped opening the drawer because `isVisible()` was checked before rendering; one partner drawer assertion still expected the old close-button name. Report: `frontend/.phase0-evidence/playwright-final-localized.json`; traces: `browser-final-localized-traces/`. After test-only corrections, the complete search spec on desktop/mobile and partner-Axe spec passed **73/73**, 1.6 min (`playwright-search-drawer-fixed.json`); affected-file lint and typecheck passed. A fresh full green run is still required to close T046. The older 663/5/40/76 result is retained in `playwright-pre-fix.json` and `browser-pre-fix-traces/`. | Open |
| Lighthouse and manual four-width keyboard/focus matrix | Two planned post-priority/contrast-fix idle rounds passed Performance >=90 for all four primary pages and Accessibility 100; all eight reports and earlier failures remain retained below. All 40 three-locale/four-width automated matrix cases passed on the prior refreshed build with 88 screenshots; the latest build is undergoing a fresh complete browser run. Remaining manual keyboard/focus/state acceptance and deployed HTTPS/public-origin checks are not established by these local scores. | Open |

The exposed Gemini key in ignored local configuration must be rotated before any production deployment. No live provider smoke test is counted as a release gate here.

The review counter's `text-gray-400` on white failed Axe at 2.6:1 (4.5:1 required). A regression test failed before changing the normal/over-limit counter colors to `text-gray-600` / `text-red-600`; all **9 ReviewForm tests passed** afterwards. An explicit completed-booking review-form Axe case was added so coverage does not depend on the status of BKO-TEST01. Database inspection showed BKO-TEST01 was completed with a past date, contrary to the partner spec's confirmed/future assumption; BKO-PART01 is now a dedicated confirmed partner fixture, independent of traveler cancellation tests. The partner navigation assertion now scopes the heading to `header` instead of matching both the header h2 and page h1. No booking eligibility/status rule was relaxed.

The next full-run trace showed that `showFilters()` checked `isVisible()` immediately after navigation, did not click, then waited for the desktop landmark or attempted filling a hidden mobile input. It now uses the actual viewport and the existing 1024px `lg` breakpoint, waiting for the mobile trigger before opening its dialog or for the desktop landmark instead. No timeout/retry was increased and no runtime filter behavior changed. The partner drawer snapshot already exposed a labeled modal and `Close menu` button; its stale test now scopes that exact button to the named dialog, verifies `aria-modal`, closes it and asserts focus returns to the trigger. All 73 focused cases passed with zero retries.

Latest gate-fix continuation source files (not the entire redesign's changed-file inventory):

```text
backend/database/seeders/DatabaseSeeder.php
backend/lang/en/seo.php
backend/lang/es/seo.php
backend/lang/it/seo.php
backend/tests/Feature/Search/HomepageTest.php
docker-compose.yml
frontend/package.json
frontend/jest.config.mjs
frontend/scripts/lighthouse-audit.mjs
frontend/scripts/lighthouse-audit.test.mjs
frontend/scripts/Invoke-PerformanceAudit.ps1
frontend/scripts/Set-BooklyPagefile.ps1
frontend/src/components/reviews/ReviewForm.tsx
frontend/src/components/reviews/__tests__/ReviewForm.test.tsx
frontend/src/components/my-bookings/BookingCard.tsx
frontend/src/components/my-bookings/BookingStatusBadge.tsx
frontend/src/components/my-bookings/__tests__/BookingCard.test.tsx
frontend/tests/e2e/a11y/booking-detail-a11y.spec.ts
frontend/tests/e2e/partner/bookings.spec.ts
frontend/tests/e2e/partner/navigation.spec.ts
frontend/tests/e2e/visual-acceptance.spec.ts
frontend/tests/e2e/search.spec.ts
frontend/tests/e2e/a11y/partner-a11y.spec.ts
frontend/tests/e2e/a11y/tour-detail-a11y.spec.ts
frontend/tests/e2e/homepage.spec.ts
frontend/src/components/home/FeaturedTours.tsx
frontend/src/components/home/__tests__/FeaturedTours.test.tsx
frontend/src/components/search/TourCard.tsx
frontend/src/components/search/__tests__/TourCard.test.tsx
frontend/src/components/reviews/ReviewCard.tsx
frontend/src/components/reviews/__tests__/ReviewList.test.tsx
specs/018-bookly-ui-redesign/quickstart.md
specs/018-bookly-ui-redesign/tasks.md
```

Backend verification used an exact source copy inside `/tmp/bookly-phase0-gate.nKOCBj` in the PHP container, including `lang`, `pint.json` and the locked Composer dependencies installed with `--no-scripts`. This avoids slow Windows bind-mount reads. Only `.env.example` was copied as its environment file, plus a synthetic test-only APP_KEY; the real `.env` and Gemini key were not copied. `tests/bootstrap.php` / `TestCase.php` still enforced `bookly-test-postgres` / `bookly_test` and test Meilisearch identity. Commands: `vendor/bin/pint --test`, `vendor/bin/phpstan analyse --memory-limit=512M --no-progress`, and `php -d memory_limit=512M vendor/bin/pest --compact --log-junit=/tmp/bookly-phase0-pest-final.xml`. Early scratch runs that lacked `lang`/`pint.json` or used PHP's 128M default were invalid/incomplete and are not counted as gate results. The final run above includes them and passed as one complete suite.

Visual review also found generic `Tour` labels throughout traveler history. The list query loaded `tour` but not `tour.translations`, so the existing DTO returned an empty title. Four EN/ES/IT/missing-IT regressions reproduced the empty response; eager-loading `tour.translations` fixed them, and all 10 traveler-booking tests passed before the full 725-test runs. This preserves the DTO's existing **booking locale** and EN fallback contract, rather than changing historical booking content to follow the current interface locale. The live PHP server was restarted to refresh OPcache, and the focused browser matrix verified nonempty API titles matching rendered headings. That first 28-case focused run had **13 passed / 3 failed / 12 not run**: Italian 390px checkout requests did not finish before the assertion, and the first two Filament viewports received Nginx 504 during cold PHP startup. The trace contained no JavaScript errors; do not assume this proves all timing failures are infrastructure-only. Once `/admin/login` returned HTTP 200, the selected warm rerun passed **17/17** in 2.9 min, including the Italian checkout, all four Filament widths, both booking-detail Axe cases, and partner detail/header assertions on desktop/mobile. Reports: `playwright-post-fix-focused.json` and `playwright-post-warm-focused.json` under the ignored evidence directory. These focused results do not replace the still-open full browser gate.

#### Lighthouse measurements (2026-09-29)

Lighthouse 12.8.2 / HeadlessChrome 148 on the local Docker Linux host, default simulated mobile throttling and viewport, production-built Next.js through the local Nginx reverse proxy (`http://nginx`), one audit at a time with Playwright idle. Base Git commit `f3e8e25` plus uncommitted Phase 0 changes; no stable production release SHA exists yet. Reports are retained under `frontend/.phase0-evidence/lighthouse/` (ignored by Git and not cleared by Playwright). The repeated measurements use the same command/options; only route and output file differ.

| Route | Run | Performance | Accessibility | Best Practices | SEO | LCP | TBT |
|---|---|---:|---:|---:|---:|---:|---:|
| `/en` | post-fix first | 51 | 100 | 79 | 100 | 3.9 s | 3,610 ms |
| `/en` | post-fix repeat | 82 | 100 | 79 | 100 | 2.3 s | 630 ms |
| `/en/search?q=rome` | post-fix first | 75 | 100 | 79 | 100 | 3.6 s | 610 ms |
| `/en/search?q=rome` | post-fix repeat | 71 | 100 | 79 | 100 | 3.5 s | 860 ms |
| `/en/tours/hidden-gems-rome-walking-tour` | post-fix first | 62 | 97 | 79 | 92 | 2.5 s | 2,350 ms |
| `/en/tours/hidden-gems-rome-walking-tour` | post-fix repeat | 96 | 97 | 79 | 92 | 2.0 s | 190 ms |
| `/en/blog/hidden-gems-florence` | post-fix first | 85 | 100 | 79 | 92 | 3.2 s | 360 ms |
| `/en/blog/hidden-gems-florence` | post-fix repeat | 85 | 100 | 79 | 92 | 3.1 s | 370 ms |

The two local Best Practices failures are `is-on-https` and `redirects-http` because the audit endpoint is plain HTTP; verify both on a deployed HTTPS host. The tour/blog SEO audit flags a canonical/hreflang host mismatch between local `localhost` and `nginx` settings; recheck with a single configured public origin before release. Earlier pre-cookie-fix observations included warm scores of 91/91/95/91, but their files were cleared with Playwright's `test-results` directory; they are **not** the retained results for the later build and do not establish acceptance. The retained table above remains failing/variable. A single score >=90 does **not** erase failures or satisfy the visual matrix. Record an idle, reproducible production-like audit with fixed origin before closing T047.

#### Controlled idle baseline (2026-09-30)

After the complete 785-case browser run and the 73-case focused rerun ended, two rounds were planned in advance and run serially on the same production build and fixtures, without Jest/typecheck/build/Playwright running concurrently. The local scheduler remained paused; API, queue, PostgreSQL, test PostgreSQL, Redis and test Meilisearch remained running. Other desktop/host load was not controlled; this is not a dedicated staging host. Lighthouse 12.8.2 / Chrome 148, default simulated mobile throttling, plain HTTP Nginx origin and command flags remained identical to the preceding audits. All reports are retained as `controlled-idle-{home,search,tour,blog}-{1,2}.json` under the same ignored Lighthouse directory.

| Route | Round | Performance | Accessibility | Best Practices | SEO | LCP | TBT |
|---|---|---:|---:|---:|---:|---:|---:|
| `/en` | 1 | 68 | 100 | 79 | 100 | 2.3 s | 7,020 ms |
| `/en/search?q=rome` | 1 | 92 | 100 | 79 | 100 | 3.2 s | 150 ms |
| `/en/tours/hidden-gems-rome-walking-tour` | 1 | 98 | 97 | 79 | 92 | 2.2 s | 120 ms |
| `/en/blog/hidden-gems-florence` | 1 | 94 | 100 | 79 | 92 | 3.0 s | 100 ms |
| `/en` | 2 | 96 | 100 | 79 | 100 | 2.3 s | 190 ms |
| `/en/search?q=rome` | 2 | 92 | 100 | 79 | 100 | 3.0 s | 150 ms |
| `/en/tours/hidden-gems-rome-walking-tour` | 2 | 95 | 97 | 79 | 92 | 2.5 s | 180 ms |
| `/en/blog/hidden-gems-florence` | 2 | 92 | 100 | 79 | 92 | 3.1 s | 180 ms |

These measurements show that the earlier home performance gap is variable, not solved merely by leaving Playwright idle. The home LCP element was the first featured-tour cover at y=568px on the simulated mobile viewport, still marked `loading="lazy"`. Following the resource-prioritization skill, only that first cover now opts into `loading="eager"` / `fetchPriority="high"`; ordinary listing/related/remaining featured covers stay lazy. This uses the existing Next Image component and its [documented loading/fetch-priority behavior](https://nextjs.org/docs/app/api-reference/components/image#preload), not a framework/dependency upgrade or blanket preload. The new TourCard/FeaturedTours regressions failed before the change; production-build measurements after the change must be recorded separately before claiming a speed improvement.

The tour Accessibility 97 result revealed two review-date spans with `text-gray-400` on white (2.6:1, below 4.5:1). The previous Axe test scanned before reviews loaded, so its pass did not cover the populated review list. The strengthened EN/ES/IT browser cases wait for a real API-backed reviewer name; **all three reproduced the serious contrast finding before the fix** (`playwright-review-contrast-before.json`, traces in `browser-review-contrast-before-traces/`). A ReviewList regression also failed on the date's class. The date color now uses `text-gray-600`, following the accessibility skill. No review data, eligibility or date-locale behavior was changed. On the refreshed production build, **all three populated-review Axe cases passed**, 14.0 s (`playwright-review-contrast-after.json`). All **16 homepage cases passed** on desktop/mobile, 30.1 s, including live `loading="eager"` / `fetchpriority="high"` attributes (`playwright-home-image-after.json`). The 43-suite / 233-test full code gate passed.

#### Post-priority/contrast-fix production build (2026-09-30)

The frontend alone was rebuilt/restarted after the two source fixes (16.9 s successful compile, 17.3 s build TypeScript, 89 static pages, production ready in 147 ms). After all Jest/typecheck/lint and focused browser processes finished, two planned serial rounds used the exact same route/order/mobile/throttling/Chrome flags and scheduler conditions as the controlled baseline. No concurrent frontend gate ran during these audits. Reports: `post-priority-{home,search,tour,blog}-{1,2}.json` in the retained ignored Lighthouse directory.

| Route | Round | Performance | Accessibility | Best Practices | SEO | LCP | TBT |
|---|---|---:|---:|---:|---:|---:|---:|
| `/en` | 1 | 98 | 100 | 79 | 100 | 2.2 s | 70 ms |
| `/en/search?q=rome` | 1 | 91 | 100 | 79 | 100 | 3.2 s | 160 ms |
| `/en/tours/hidden-gems-rome-walking-tour` | 1 | 98 | 100 | 79 | 92 | 2.2 s | 120 ms |
| `/en/blog/hidden-gems-florence` | 1 | 94 | 100 | 79 | 92 | 3.0 s | 70 ms |
| `/en` | 2 | 97 | 100 | 79 | 100 | 2.3 s | 140 ms |
| `/en/search?q=rome` | 2 | 92 | 100 | 79 | 100 | 3.2 s | 120 ms |
| `/en/tours/hidden-gems-rome-walking-tour` | 2 | 97 | 100 | 79 | 92 | 2.2 s | 120 ms |
| `/en/blog/hidden-gems-florence` | 2 | 94 | 100 | 79 | 92 | 3.0 s | 100 ms |

All eight current-build measurements meet the specified local primary-page Performance >=90 threshold. Home LCP discovery and lazy-loaded-LCP audits now pass; tour color contrast now passes. This establishes the scoped image/contrast corrections and these reproducible local observations, not that image prioritization alone caused the earlier 7,020 ms JavaScript outlier to disappear. Plain-HTTP Best Practices and tour/blog canonical-origin SEO limitations remain as described above; verify against the configured HTTPS staging host. T047 remains open for the remaining manual acceptance matrix, not for an invented claim that the current local performance scores still fail.

#### Direct browser metadata finding and renewed verification

The additional 390px in-app-browser inspection of `http://localhost:8080/en` exposed the literal tab title `seo.homepage.title`. `GetHomepageDataAction` referenced `seo.homepage.*`, but no `seo.php` catalogs existed in any of the three backend language directories; Laravel returned the nonempty key, so the action's existing `?:` fallback never ran. The backend locale tests previously asserted only HTTP 200. Strengthened EN/ES/IT exact title/description regressions reproduced the key in all three locales, then **all 11 homepage tests / 41 assertions passed** after adding the missing three catalogs. They reuse the truthful translated discovery copy, without introducing fabricated inventory counts or changing the API envelope. EN/ES/IT browser title assertions were added to the existing homepage cases.

The first renewed 787-case browser run was intentionally interrupted to include this metadata fix: **199 passed / 1 interrupted / 587 not run**, 6.8 min; it is not a full gate pass. Report: `playwright-release-candidate.json`; interruption trace: `browser-interrupted-metadata-traces/`. A backend gate attempt started before restarting PHP was also interrupted by that restart and is not counted as a completed Pest run. The new complete backend/frontend/browser verification and metadata-updated performance checks must be recorded after the runtime is ready. The preceding eight performance reports remain evidence for the pre-metadata-fix build, not an invented final-build sign-off.

The renewed metadata-inclusive backend gate finished: **Pint 567 files passed; Larastan no errors; Pest 725 tests / 2,695 assertions passed in 291.88 s** (`backend-pest-metadata.xml`). The refreshed Next.js production build compiled successfully in 2.5 min, completed its TypeScript/static-generation stages and became ready in 1,080 ms. That build duration overlapped backend checks and is not a controlled performance measurement. The strengthened homepage browser file subsequently passed **16/16 desktop/mobile cases**, 1.2 min, including exact EN/ES/IT document titles (`playwright-home-metadata-after.json`). No retry, timeout increase, or API contract change was needed.

#### Windows resource and Docker I/O correction (2026-09-30)

The first metadata-build performance round did **not** meet the gate. All reports remain in `.phase0-evidence/lighthouse/`:

| Report | Performance | Accessibility | LCP | TBT | CPU benchmark index |
|---|---:|---:|---:|---:|---:|
| `post-metadata-home-1.json` | 79 | 100 | 2.8 s | 700 ms | 1050 |
| `post-metadata-search-1.json` | 85 | 100 | 3.2 s | 350 ms | 1429 |
| `post-metadata-tour-1.json` | 70 | 100 | 2.4 s | 800 ms | 909.5 |
| `post-metadata-blog-1.json` | 84 | 100 | 3.2 s | 390 ms | 1494.5 |

The next home audit wrote `post-metadata-home-2.json` (82 Performance, 100 Accessibility, 2.3 s LCP, 350 ms TBT, benchmark 449), but its **Windows Docker CLI exited with `cannot allocate memory`**. The remaining three round-two routes did not run. A report file alone is not proof of a successful command or completed two-round gate. The same main React/Next chunk names appeared in the earlier 98-score home audit (benchmark 1917.5), but this correlation does not prove resource pressure explains every score regression. No speculative runtime micro-optimization was applied.

Windows initially had a fixed `C:\pagefile.sys` of 16,000MB and manual pagefile management. Read-only checks measured about 90% commit use, approximately 2GB available commit, and only about 17GB free on C:. F: was a fixed local drive with roughly 120GB free. After **explicit owner approval**, `Set-BooklyPagefile.ps1` added `F:\pagefile.sys` at **8,192MB initial / 16,384MB maximum**, preserving C:'s exact settings and automatic-management setting. The elevated helper completed successfully; both `Win32_PageFileSetting` and `Win32_PageFileUsage` confirmed the new 8GB file was already active **without a reboot**. Commit limit rose from 33,724,551,168 to 42,314,485,760 bytes; the follow-up sample showed 74% use. This provides allocation headroom, not additional physical RAM or a guaranteed Lighthouse score. No application was killed, database stopped, or OS restart requested.

`Invoke-PerformanceAudit.ps1` now samples Windows resources before starting Docker; it requires commit use <85%, at least 4GB commit headroom and 2GB available physical RAM. A single Docker client runs both four-route rounds serially through `lighthouse-audit.mjs`, using installed Lighthouse/Chromium and the existing production build. It rejects concurrent Playwright/Jest/build/typecheck/lint/Lighthouse workloads, retains low scores without selective retry, fails incomplete/CLI-error runs, and writes a timestamped `summary.json` plus all completed reports. It does not pause the scheduler or modify OS settings. Preflight is an initial snapshot, not a guarantee that other host applications cannot change load later. Eight native Node regressions passed; real invocations reproduced the memory preflight failure before the pagefile change and correctly rejected Playwright concurrency after it.

Run from the project root after other gates finish:

```powershell
./frontend/scripts/Invoke-PerformanceAudit.ps1
docker exec bookly-frontend npm run test:performance-tools
```

A separate PHP I/O comparison used the exact same 169 installed package versions (sorted version-list SHA-256 `aaca3a553f7c6f13a21a8a97c3d2dba2a1786040d14e9e2e2ac0d8e899bc1602`). Three alternating fresh-process autoload samples took **5.985 / 4.674 / 4.935 s** on the Windows vendor bind mount versus **0.154 / 0.373 / 0.183 s** in the Linux-native snapshot. This measured cold-loader penalty supports moving dependencies rather than increasing browser timeouts. The compose change adds shared `backend_vendor` storage and a one-shot `php-dependencies` prerequisite for API/queue/scheduler: it seeds from the read-only existing vendor directory, then runs locked Composer installation with `--no-scripts` before dependent services start. App source remains bind-mounted; `.env` is neither copied to the volume nor printed; no PostgreSQL/Redis data volume changes. `docker compose config --quiet` passed. Application and post-change browser/performance verification must be recorded after the ongoing run ends; configuration validation alone is not runtime acceptance.

The dependency image built successfully. Its first Windows-bind seed copy was explicitly stopped before completion; that attempt is not counted as successful initialization. The existing Linux test snapshot provided an alternative seed: both trees contained the same 169 package names/versions, and the sorted regular-file content manifests matched SHA-256 `f23caefbfb46bc5561f7153629461d40960bc0ace086fb6708bd96e3ec5be1ab`. Only `vendor/` was streamed, without `.env` or application data; no volume was deleted. A subsequent initializer completed with exit 0: Composer verified the lock/platform, reported nothing to install/update/remove and regenerated optimized autoload files. Its two existing PSR-4 warnings concern test helper classes, not a missing production package.

API/queue/scheduler were then recreated with `--no-deps`, because initialization had already completed. Mount inspection confirmed all three use `booklytravel_backend_vendor`; all three were running and unpaused. Nginx was reloaded to resolve the recreated API upstream. Frontend, PostgreSQL, test PostgreSQL, Redis and the unrelated SEO service were not restarted. Three fresh live-API CLI autoload measurements were **0.987 / 0.399 / 0.077 s**, and `/admin/login` returned HTTP 200. These establish the mount and loader improvement, not yet a complete browser/performance gate pass.

For subsequent dependency updates, change `composer.json` / `composer.lock` through the normal dependency workflow. Run `docker compose run --rm --no-deps php-dependencies` and require exit 0 before restarting API/queue/scheduler. Apply the normal Laravel package-discovery/cache procedure when package registrations change: the initializer deliberately uses `--no-scripts`, so it does not run application hooks itself. Restart the existing PHP services after the successful update (`docker compose restart laravel queue scheduler`) to refresh workers and OPcache; simply updating the shared files does not refresh already-running processes. Fresh-stack startup uses the service-completed prerequisite through ordinary `docker compose up`. Do not delete a vendor/data volume to force an update. To revert the local I/O topology, remove the new dependency mounts/prerequisite and recreate the PHP services with the preserved host vendor tree; the unused native vendor volume remains recoverable. This local developer-compose improvement is not a production deployment rehearsal.

The metadata-inclusive full browser run subsequently completed with **745 passed / 2 failed / 40 deliberately skipped / 0 flaky**, 44.6 min, with zero retries (`playwright-final-metadata.json`). Both failures were Filament login/navigation timeouts; the trace showed a 27.329 s Livewire login response in one case. This is not a full passing gate. The unchanged Filament tests must be rerun after the dependency I/O correction; raising timeouts is not the correction. Failure traces are preserved in `browser-metadata-pre-io-traces/`. All 40 viewport/locale matrix cases passed in that run and retained 88 screenshots in `visual-matrix/2026-09-30T14-14-30-070Z/`; this does not complete human acceptance of all states.

The performance-tool-inclusive frontend gate passed **43 Jest suites / 233 tests**, **8 native Node regressions**, TypeScript and ESLint (Jest 138.395 s while the separate dependency-image build was running). This code-gate duration is not a controlled performance benchmark. The application tests were not removed or weakened: the native ESM runner tests have their own Node test command and are excluded only from Jest's jsdom discovery.

#### Post-pagefile/native-vendor verification (2026-09-30)

The unchanged Filament browser file passed **14/14 desktop/mobile cases**, 2.1 min, with zero retries and unchanged timeouts (`playwright-filament-native-vendor.json`). This includes both failures from the full run. It verifies the scoped admin correction, not a replacement for a complete post-change 787-case release gate.

The new performance runner completed both planned serial rounds in 175.45 s, with the scheduler running, no concurrent frontend gates, no CLI/runtime error, no unrun audit and no selective retry. Initial Windows resources were 2,408MB available RAM and 31,229,104,128 / 42,314,485,760 committed bytes. Reports and the failing summary are retained at `.phase0-evidence/lighthouse/run-2026-09-30T15-18-17-480Z/`.

| Page | Round 1 Performance | Round 2 Performance | Accessibility, both rounds |
|---|---:|---:|---:|
| Home | 50 | 91 | 100 |
| Search | 90 | 97 | 100 |
| Tour | 95 | 68 | 100 |
| Blog | 92 | 87 | 100 |

**Performance gate FAIL: only five of eight measurements reached 90.** Best Practices remained 79; SEO was 100 for home/search and 92 for tour/blog, consistent with the previously documented local HTTP/origin limitations. Completion without the allocation error does not establish that the error can never recur or that pagefile changes solved performance.

The first home audit's CPU benchmark was 36 and TBT 9,850ms; the other benchmarks ranged from 1,028.5 to 1,724.5. A mid-run Windows sample showed 2,769 page outputs/sec, but it does not identify which process caused them. Tour round two recorded 1,418.904ms garbage collection versus 16.872ms in round one, while the main React chunk's total work was similar (764.388 versus 740.192ms). These are confirmed observations, not proof that either host pressure or application allocation alone caused the outlier.

A separate, explicitly diagnostic tour run with saved trace/devtools log completed with exit 0: Performance 97, benchmark 1,811.5, TBT 128.5ms and GC 14.612ms (`diagnostic-native-tour.json`, `diagnostic-native-tour-0.trace.json`, `diagnostic-native-tour-0.devtoolslog.json`). It is **not substituted for the failing gate**. The runtime architecture was not changed again. Further attribution needs a controlled profile that captures the actual slow occurrence and compares allocation/scheduling evidence; arbitrary JS changes or timeout increases are not justified by this record. [Lighthouse's official variability guidance](https://github.com/GoogleChrome/lighthouse/blob/main/docs/variability.md) supports isolating competing workloads and distinguishing browser/resource variability from application behavior. A quieter or dedicated acceptance environment is the next verification step; no unrelated application was closed, WSL configuration changed, or OS restarted.

#### Partial visual/keyboard evidence (2026-09-29)

Captured viewport screenshots at 390, 768, 1024 and 1440px for EN home, search, tour detail and blog detail; the retained post-fix set is under `frontend/.phase0-evidence/visual/` (ignored by Git and not cleared by Playwright). Review found no horizontal clipping of these four main-page layouts, but the initial 390px cookie banner occupied about 170px and squeezed its message beside the buttons. Its responsive layout was changed to full-width text with buttons below; the post-fix `home-390.png` shows the shorter banner, and the focused browser test passed **4/4** widths with visible buttons and Enter-key rejection. The reset-password no-token route previously emitted `MISSING_MESSAGE` for EN/ES/IT; the namespace was corrected and its three localized browser assertions passed. These checks do not cover the remaining checkout, traveler, partner and Filament page-family matrix, cross-locale screenshots, or a complete human keyboard/focus sign-off.

The added `frontend/tests/e2e/visual-acceptance.spec.ts` exercises all three locales at each width for home/search/tour/blog, traveler history, checkout and partner dashboard, plus four Filament widths. It waits for loading placeholders to disappear, verifies a real main landmark (rather than accepting a 504 heading), checks no page-level horizontal overflow and Tab/Shift+Tab reachability, and exercises gallery/mobile-drawer Enter/Escape/focus return. It captures ignored artifacts under `.phase0-evidence/visual-matrix/<run-time>/`. It does not submit a booking/payment or mutate tour data. Screenshot review caught a missing partner dashboard heading and English-only account-status/pending messages; they were localized consistently and covered by a three-locale Jest contract test. The first incomplete matrix run is not a sign-off; the complete rerun and visual review must be recorded below before closing T047. During the controlled browser run, the local Bookly scheduler is temporarily paused to avoid overlapping `schedule:run` processes; PostgreSQL/Redis/queue remain running and the scheduler must be unpaused afterwards.

The standalone run completed all **40 desktop matrix tests** (the 40 mobile duplicates are deliberately skipped because each desktop case explicitly covers a prescribed viewport). It retained **88 screenshots** in `frontend/.phase0-evidence/visual-matrix/2026-09-29T22-14-07-651Z/`. Representative public, checkout, traveler and partner screenshots were reviewed; this is not a complete human review of all artifacts/states. A focused refreshed-PHP matrix additionally checks nonempty API booking titles against rendered card headings. Review of `traveler-es-390.png` in the newer `2026-09-29T22-47-39-234Z/` set found a localization defect: `BookingStatusBadge` rendered raw status codes, and `BookingCard` hardcoded participant copy / browser-default date formatting. The card now reuses existing `traveler.dashboard.status`, `booking.participantCount` and locale-aware date formatting, with three-locale singular/plural/date regressions. All **12 BookingCard tests** passed after the failing pre-fix regression run; the complete frontend run passed **42 suites / 229 tests**, typecheck and lint. The booking tour title continuing to follow the historical booking locale is intentional and separate. All **40 matrix cases passed again on the refreshed production build**, including the new API-derived title/status/date/participant assertions in all three locales and four widths. The new **88 screenshots** are retained in `frontend/.phase0-evidence/visual-matrix/2026-09-29T23-25-22-492Z/` (artifact timestamps are UTC; operator date is 2026-09-30 Africa/Cairo). Fresh 390px EN/ES traveler screenshots were reviewed and confirm translated badges/counts/date formatting. Filament's narrow screenshots previously showed its drawer open even when captured before the keyboard check; the test now closes the actual backdrop through its existing UI before capturing the moderation table, without hiding/changing DOM or styles. The four Filament cases passed, and fresh 390/768px screenshots were reviewed with the moderation table unobscured. This still does not establish full manual acceptance of every populated/error/empty/permission state.

## Additive deployment and rollback runbook (T048)

This is the operator procedure, **not evidence that staging or production rollout/rollback has already occurred**. Record the environment, release SHA, operator, backup identifier, timestamps and observed results during an actual rehearsal.

1. **Preflight and secrets.** Take a restorable PostgreSQL backup and record the existing backend/frontend image tags. Revoke the Gemini key previously disclosed in chat and provision a newly rotated `GEMINI_API_KEY` in the backend/worker secret store only; set `GEMINI_TRANSLATION_MODEL` to an approved available model. Do not copy the key into Git, `.env.example`, frontend variables, job payloads, screenshots or logs. Confirm the worker and Laravel application see a nonempty key without printing its value. Local `backend/.env` still contains the disclosed key and is unsuitable for production until replaced.
2. **Deploy backend first.** Run `php artisan migrate --force` with the new backend release. The two Phase 0 migrations add guide-language/itinerary and translation-state storage; do not drop old columns or derived rows in this release. Deploy the backend and restart the API and queue workers (`php artisan queue:restart` after code/config rollout). The Compose worker consumes `default,booking_emails,scout`; translation jobs use the default queue. Keep the old frontend live while checking partner/public API contract responses and EN publishing guard.
3. **Backfill gradually.** Run `php artisan tours:queue-translations --after-id=0 --limit=100`, then continue with the reported `--after-id` cursor in bounded batches. This command inspects tours with an EN translation and queues ES/IT work; it does not delete legacy translations. Watch queue depth and `php artisan queue:failed`; group translation states by `locale,status` using a read-only DB query. Inspect sanitized `last_error_code` aggregates only, never prompt/response bodies or secrets. Alert on persistent `failed`/`stale` growth, Gemini 429/5xx, queue lag, elevated public EN fallback rate, booking errors or API latency. Avoid retrying all failed jobs blindly: confirm a rotated valid key, quotas and failure class first.
4. **Deploy frontend.** Smoke-test `/en`, `/es`, `/it` tour detail, search, booking and admin moderation with representative records. A pending/stale/failed derivative must show current EN with a visible notice; a ready derivative must show matching ES/IT content and itinerary. Guide-language codes remain independent. Verify no provider key or private state appears in browser JSON/bundles. Run the full release gates below before traffic ramp-up.
5. **Rollback rehearsal in staging.** After a snapshot, simulate a failed frontend rollout: return to the prior frontend image, verify booking/search/admin still work, then restore the new frontend. Simulate a backend/worker rollback separately: stop/restart workers on the prior compatible image, restore the prior backend image, and verify old API/booking routes against the additive schema. **Do not run `migrate:rollback` or delete translation rows** as an automatic rollback step. Record whether the prior backend is actually compatible with the additive schema; if not, roll forward from the backup/release pair under the deployment owner instead of assuming compatibility. Re-deploy the new backend, restart workers, and verify stale-hash jobs cannot overwrite newer EN content.

| Rehearsal evidence | Required value |
|---|---|
| Staging release/previous image SHAs and backup ID | Pending operator run |
| Rotated-key installation and no-exposure check | Pending operator run |
| Migration/backfill cursor and state counts before/after | Pending operator run |
| Frontend rollback smoke results | Pending operator run |
| Backend/worker rollback compatibility and booking smoke results | Pending operator run |
| Operator sign-off and date | Pending operator run |

## Phase 0 visual acceptance contract

These are acceptance criteria for future UI implementation, not a claim that the current pages pass. For every row, test EN, ES and IT with a representative populated case and the applicable empty/error/unauthorized case. Record route, data fixture, browser/build version, viewport, result, screenshot or trace path, and defect link. Keep payment tests in a non-production environment with test credentials only.

| Page family | 390px | 768px | 1024px | 1440px | Content and behavior to verify |
|---|---|---|---|---|---|
| Homepage | Search is first actionable element; discovery rails scroll without clipping | Cards reflow without horizontal page scroll | Search, categories and featured tours form clear hierarchy | Wide layout uses bounded content width | Only published/data-backed tours; no unsupported badges, offers or review values; partner invitation is clearly secondary. |
| Search/listing | Filter control opens a labeled drawer; apply/clear preserves query | Drawer or compact filter layout remains usable | Filters and result list coexist without overlap | Sidebar, sort and cards are aligned | URL criteria survive back/forward and locale switch; loading, no-results and API failure have honest recovery. |
| Tour detail | Gallery and booking action are reachable; no sticky control obscures content | Facts and optional sections stack/read cleanly | Booking card may sit beside content without overlap | Gallery, section nav and booking card follow the specified hierarchy | Gallery order/cover and focus; visible guide languages; ready ES/IT vs disclosed EN fallback; optional empty sections/nav omitted; no fabricated availability or price. |
| Checkout | Summary precedes payment; changed-price action fits | Payment controls remain fully visible | Summary and payment may form two columns | Totals and recovery are scannable side by side | Server price/currency, idempotent retry, Stripe failure/confirmation and cancellation semantics remain unchanged. |
| Traveler dashboard | Primary account actions are reachable from mobile navigation | Cards/forms reflow without hidden labels | Booking/profile content uses shared shell | History/detail hierarchy is consistent | Auth guards, wishlist, booking history, eligibility-gated reviews, empty and unauthorized states. |
| Partner dashboard | Navigation reaches tours, booking, analytics and profile | Tables have usable responsive alternative | Operations and summary panels fit | Dense operational layout stays legible | Ownership guard; English-only authoring and ES/IT status; correctly scaled revenue; unavailable conversion is not a measured zero. |
| Filament admin | Existing admin controls remain operable without horizontal traps | Moderation content is inspectable | Review and actions stay associated | Dense tables and detail panels remain coherent | Internal-only access, approval/rejection authorization, itinerary/gallery inspection and audit trail; no public admin exposure. |

### Cross-cutting matrix and evidence rules

| Dimension | Required check | Passing evidence |
|---|---|---|
| Locales | Repeat representative routes in `/en`, `/es`, `/it`; inspect labels, dates, currency, guide-language names, fallback notice, metadata/canonical/alternates | No missing keys, clipped translated labels or unsupported translated-content claims; actual content locale matches visible text. |
| Keyboard and assistive technology | Tab/Shift+Tab through primary path; Enter/Space activation; Escape/focus return for drawer/dialog/lightbox; inspect headings, names and live errors; run Axe | No keyboard trap, hidden focus, unnamed controls, serious/critical Axe finding or sticky heading obstruction. |
| Reduced motion and touch | Enable `prefers-reduced-motion`; exercise 390px touch controls and scroll | Essential information remains available; no forced animation; targets and hit areas are practical. |
| Data integrity | Use fixtures for populated, empty, failure, permission-denied, stale translation, zero reviews and unavailable inventory | No invented aggregate, fee, availability, conversion metric or operator claim. |
| Performance | Run Lighthouse on `npm run build` + production server for home, search, tour detail and blog detail, using the same machine/network profile and fixed fixtures | Record URL, commit, device preset, throttling, date and trace/report. Performance >=90 on each primary page; an unreachable run is not a pass. |
| Screenshots | Capture before/after at 390, 768, 1024 and 1440px for each page family and locale representative | Artifacts are retained with test run/commit identifiers and reviewed against the corresponding row above. |

Suggested reproducible commands from `frontend/`: `npm run build`, `npm run test:e2e`, `npm run test:a11y`, `npm run typecheck`, `npm run lint`, and `npm test -- --runInBand`. The exact Lighthouse invocation, machine conditions and resulting scores must be filled from the release run; this document does not invent a score.

## Master-plan Phase 1 implementation evidence

See [phase-1-verification.md](./phase-1-verification.md) for the design tokens, shared primitives and shells delivered under master-plan Tasks 1.1-1.3, test results, reviewed decisions and remaining release-wide gates.
