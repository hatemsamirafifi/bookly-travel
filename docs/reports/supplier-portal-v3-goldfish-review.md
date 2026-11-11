# Bookly Supplier Portal Goldfish Review

**Review date** 2026-11-10  
**Latest follow-up** 2026-11-11, EGM-002 publication remediation  
**Implementation baseline** `a1e878e`, with the current working copy identified separately where relevant  
**Governance** Constitution v3.0.0 and the approved EGM-001 decision. [Constitution](../../.specify/memory/constitution.md#tour-content-source-and-derived-locales) · [EGM-001 decision](../../goldfish_review_report.md) · [Supplier Portal plan](../superpowers/plans/2026-11-10-bookly-supplier-portal.md)

## Review outcome

**Documentation consistency passed for the source-locale amendment. Phase A readiness failed.** The three independent EGM Stage 9 reviewers used fresh contexts and read the local plan, relevant specifications and contracts, and repository code. The review corrected conflicting English-only wording in active documents, the PRD version, the historical Spec 019 merge status, the distinction between the committed and local wizard entry steps, and the option-text and checkout contracts. The original acceptance records and spec and requirement numbers were preserved. Spec 019's historical PR #29 merge is visible in local Git at `9e241b9`, an ancestor of the audited baseline. [Supplier Constitution Check](../superpowers/plans/2026-11-10-bookly-supplier-portal.md#L177) · [Spec 019 traceability and acceptance](../../specs/019-tour-content-data/spec.md#amendment-record-constitution-v300-2026-11-10)

| Goldfish | Findings incorporated |
|---|---|
| Implementation readiness | Updated Spec 018 fallback and Spec 019 implementation slices. Identified A2 source-choice restoration and browser acceptance gaps, plus decisions needed for drafts, departures, capacity, and review state. |
| Codebase details | Corrected the Spec 019 draft-materialization rule, localized option response, and Spec 020 option handoff. Documented the general Filament status and translation-write paths separately from the guarded approval action. |
| Style | Corrected active governance version references, scoped approval and generation claims to their evidenced entry points, and separated dated evidence from current acceptance. |

OpenCode Go rejected the first dispatch because its API key had expired. The documentation dispatch completed using the owner-selected `ollama-cloud/glm-5.3-flash`. Codex verified its diff, completed the contract and acceptance corrections, and ran the local checks below.

## Findings and policy decisions

| Finding | Evidence and required resolution |
|---|---|
| **EGM-002 · Resolved · A4 publication bypass** | The original Livewire probe published an `es`-source draft without its source row or audit event. Permanent regressions reproduced that defect before remediation. The general forms now reject lifecycle changes server-side, and publication goes through the authorized, transactional domain action. The demo web seeder is temporarily refused. The dated follow-up records the runtime inventory, 146 passing focused cases, independent review, and remaining broader verification failures. [Publication follow-up](#egm-002-publication-follow-up-2026-11-11) |
| **EGM-003 · Blocker · A4 administrative translation writes** | The Filament repeater writes translation rows directly. Its save hook calls the field translation service, which skips tours with non-English sources or configured options. Static inspection also shows that a manually changed derivative can retain an existing ready state if its source hash still matches. The owner needs to decide whether admins may edit canonical content, and whether any derivative editing is permitted under the approved policy. A permitted workflow needs revision validation, regeneration, authorization, and audit rules. Treatment of edits to already-published content also needs a decision before review snapshots are built. [Repeater](../../backend/app/Filament/Resources/TourResource.php#L133-L157) · [Save hook](../../backend/app/Filament/Resources/TourResource/Pages/EditTour.php#L61-L69) · [Field engine](../../backend/app/Domains/Translation/Services/AutoTranslationService.php#L124-L128) · [Current derivative selection](../../backend/app/Domains/Partner/Services/TourTranslationService.php#L631-L650) |
| **EGM-004 · Resolved · A2 source selection on restore** | The original inspection found that restoring a browser snapshot converted an explicit `null` source to `en`. Existing browser cases also expected the details screen before language selection. The hydration fix and the scoped authenticated browser acceptance below resolve this finding. [Store normalization](../../frontend/src/lib/stores/tourWizard.ts#L153-L163) · [Browser acceptance](#a2-browser-restoration-follow-up) |
| **EGM-005 · Blocker for affected future slices · A2–A5 contracts** | Server draft resume needs an explicit revision and first-save retry contract. Departures and shared capacity need schema constraints, recurrence bounds, API contracts, and capacity handling across payment expiry and cancellation. A4 needs review snapshot and supersession rules, including option review and published-content edits. A5 needs precise departure and pricing error and replay contracts, plus deployment and rollback sequencing. These are design decisions to record before the affected slices start. [Proposed data and booking model](../superpowers/plans/2026-11-10-bookly-supplier-portal.md#٣-البيانات-وقواعد-الحجز) · [Phase A plan](../superpowers/plans/2026-11-10-bookly-supplier-portal.md#L88) |

## Phase A gap matrix

“Implemented” identifies behavior found in repository code. “Unverified” identifies checks for which this review has no current result.

| Slice | Implemented | Partial | Missing | Unverified |
|---|---|---|---|---|
| **A1 · Navigation and access** | Partner routes, approval middleware, and ownership boundaries. | Existing sidebar links support the main partner surfaces. The target menu and restricted-account guidance are incomplete. | Target navigation hierarchy and access guidance in the creation flow. | Desktop and mobile navigation, direct-route restrictions, two-supplier isolation across the proposed flow, and target-environment record counts. [Routes](../../backend/routes/api/partner.php#L27-L49) · [Partner sidebar](../../frontend/src/components/partner/layout/PartnerSidebar.tsx#L11-L35) |
| **A2 · Wizard and drafts** | Language-first start, unselected-draft hydration, fixed source authoring, local draft fields, and in-session reuse of materialized tour and option IDs. The targeted browser suite passed for source selection and restore. | Server draft endpoints exist for known tour IDs. The wizard resume contract remains incomplete. | Intro and activity-type steps, server step resume, competing-tab handling, and first-save replay. | Cross-session server recovery. [Wizard materialization](../../frontend/src/components/partner/tours/TourWizard.tsx#L288-L307) · [Browser acceptance](#a2-browser-restoration-follow-up) |
| **A3 · Options and departures** | Stable option IDs, create/edit and retirement, option schedule and unit price, per-option/per-date capacity, and revision-bound localized option text. | Existing availability uses the tour schedule and option weekdays. | Explicit departures, shared capacity pools, group pricing, time-zone rules, and an operating calendar. | Recurrence across daylight-saving changes, overlapping inventory, and last-seat concurrency for the proposed model. [Tour option model](../../backend/app/Models/TourOption.php) · [Option tests](../../backend/tests/Feature/Partner/TourOptionsTest.php) |
| **A4 · Review and publication** | Source validation, per-item authorization, lifecycle guards, atomic publication timestamp/audit, and transactional general-form protection. The scoped EGM-002 regressions pass, including owner-suspension races. | Existing moderation reads source content. Administrative content/derivative editing remains unresolved under EGM-003, with a temporary guard against destructive published-source edits. Demo web generation is temporarily blocked. | EGM-003 owner decision and remediation, review versions, change-request and final-rejection flow, and review of new options for published products. | Supersession handling, consistent public projections, and full CI/release gates. [Publication follow-up](#egm-002-publication-follow-up-2026-11-11) · [Supplier A4](../superpowers/plans/2026-11-10-bookly-supplier-portal.md#أ٤-مراجعة-الإدارة-والنشر-وقائمة-المنتجات) |
| **A5 · Checkout and voucher** | The selected option reaches checkout. Backend price and capacity checks and booking text snapshots exist. Travelers see the snapshot, and the supplier detail API returns it. | The voucher resolves tour data and the supplier UI does not display the stored option snapshot. | Purchased option and time in the PDF and supplier list/detail, plus checkout for explicit departures and group pricing. | Integrated supplier-to-traveler checkout, Stripe test confirmation/refund, voucher freshness, and A3-dependent race checks. [Booking action](../../backend/app/Domains/Booking/Actions/CreateBookingAction.php#L91-L154) · [Supplier detail response](../../backend/app/Domains/Booking/Resources/PartnerBookingResource.php#L41-L55) · [Voucher service](../../backend/app/Domains/Booking/Services/VoucherService.php#L132-L181) |

## A2 slice selected by the original review

**A2 · Preserve deliberate source selection across refresh.** Bound the slice to wizard hydration, its store and component tests, and the existing partner create browser spec. With clean storage the user sees the language picker before content fields. Refreshing an unselected new draft keeps the selection empty. A known legacy record retains its English default. After selecting Spanish the browser sends `source_locale: es` and writes to `translations.es`. The A4 publication bypass remains a release blocker independently of this slice. [Wizard store](../../frontend/src/lib/stores/tourWizard.ts#L153-L163) · [Partner create browser spec](../../frontend/tests/e2e/partner/tour-create.spec.ts#L77-L88)

## Checks run in this review

- **Laravel on disposable PostgreSQL** — `TourOptionsTest`, `TourTranslationTest`, `TourTranslationConcurrencyTest`, and `TourDetailTest` passed 83 tests with 1,642 assertions. The run includes stale-job races, source immutability, and pending-derivative publication for Spanish and Italian. Pest warned that it could not write its result cache in the read-only vendor mount. [Backend acceptance](../../backend/tests/Feature/Partner/TourOptionsTest.php#L616-L671) · [Concurrency tests](../../backend/tests/Feature/Partner/TourTranslationConcurrencyTest.php)
- **Jest** — Four focused suites passed 146 tests. A focused rerun of `TourDetail.test.tsx` passed 61 tests, including the Spanish and Italian fallback notice assertions. Scoped ESLint, PHP syntax, and Pint checks passed. [Fallback notice tests](../../frontend/src/components/tour/__tests__/TourDetail.test.tsx#L181-L198)
- **Filament characterization probe** — One isolated probe with six assertions reproduced EGM-002. Its assertion confirmed the defect on disposable test data. The probe used a temporary overlay and the repository test source stayed intact. [General status field](../../backend/app/Filament/Resources/TourResource.php#L93-L103)
- **Documentation** — Scoped whitespace, local references, FR/SC identifiers, and unchanged historical evidence were checked. Browser acceptance, live Gemini/Stripe staging, full CI, and release verification were still open at this stage.

## A2 implementation follow-up

The creation wizard starts on Language. A new or unbound draft with a missing or explicit null source returns to Language with no selection, even when the stored step is later. Previously entered text and option identifiers stay available. Selecting a source preserves it through reload, and a materialized draft keeps its source locked after source text is cleared. An identifiable existing legacy tour still opens directly in its English editor. The edit screen uses the existing localized locked-source notice. [Store](../../frontend/src/lib/stores/tourWizard.ts) · [Wizard](../../frontend/src/components/partner/tours/TourWizard.tsx) · [Existing tour editor](../../frontend/src/app/%5Blocale%5D/%28partner%29/partner/tours/%5Bid%5D/edit/page.tsx)

OpenCode produced the store and component changes and their regression tests. Its relay timed out during a verification probe. Codex restored the verified files from the probe backups and completed the browser fixtures, edit coverage, and materialized-draft source lock. An independent fresh-context Goldfish reviewed the diff and rechecked the resulting fixes. It found no remaining static A2 blocker and required browser evidence for closure. [Store tests](../../frontend/src/lib/stores/__tests__/tourWizard.test.ts) · [Wizard tests](../../frontend/src/components/partner/tours/__tests__/TourWizard.test.tsx) · [Partner creation](../../frontend/tests/e2e/partner/tour-create.spec.ts) · [Partner accessibility](../../frontend/tests/e2e/a11y/partner-a11y.spec.ts)

| Check | Evidence |
|---|---|
| Before production edits | Six Jest failures reproduced the implicit English default, skipped Language step, and disabled picker. A separate test reproduced unlocking the source after a successful save. |
| After the fixes | Six focused Jest suites passed all 181 tests, including Spanish writes, tour and option ID reuse within a wizard session, and existing English and Spanish editing. |
| Backend source and translation regression | `TourOptionsTest` and `TourTranslationTest` passed all 37 tests with 471 assertions on disposable PostgreSQL. |
| Static gates | Scoped ESLint, frontend typecheck, and `git diff --check` passed. |
| Browser | Partner authentication passed in the Docker test runtime. All five targeted partner creation and accessibility cases failed before the wizard rendered because the webpack client bundle could not resolve Node modules such as `child_process`. The native runtime timed out. The separate Docker Turbopack retry timed out navigating to the creation route after 180 seconds. The remaining retry cases were stopped while that route was still compiling. |

At this point EGM-004 remained open pending browser acceptance. The later result appears in the [browser restoration follow-up](#a2-browser-restoration-follow-up). EGM-002 and EGM-003 remain open under A4.

### Phase A gaps recorded before browser restoration

| Slice | Evidence and remaining work |
|---|---|
| **A1 · Navigation and access** | Existing routes and access guards are present. Target menu, restricted-account guidance, and cross-supplier browser acceptance remain open. |
| **A2 · Wizard and drafts** | Source selection, hydration, fixed source, and same-session ID reuse have local test coverage. Browser acceptance and the separate intro, activity type, server resume, competing-tab, and first-save replay work remain open. |
| **A3 · Options and departures** | Existing option CRUD, localized text, and option-day capacity remain the baseline. Explicit departures, shared pools, group pricing, and calendar acceptance remain open. |
| **A4 · Review and publication** | Dedicated approval and source validation exist. General Filament publication and administrative translation policy remain blocked under EGM-002 and EGM-003. Review snapshots and transitions remain open. |
| **A5 · Checkout and voucher** | The selected option reaches checkout and the booking retains its text snapshot. Supplier and voucher presentation, departure and group-pricing integration, and integrated payment acceptance remain open. |

## A2 browser restoration follow-up

### Webpack compilation failure

The saved Playwright error context contains this compiler diagnostic and its full import trace. The wizard imports a shared image placeholder module for preview rendering. An unused export in that module dynamically imported `plaiceholder`, which reaches native `sharp` and `detect-libc`. The runtime `window` guard could only run after bundling. Webpack still had to resolve the dynamic import in the client graph. This was an application module boundary failure.

```text
./node_modules/detect-libc/lib/detect-libc.js (6:1)
Module not found: Can't resolve 'child_process'
  4 | 'use strict';
  5 |
> 6 | const childProcess = require('child_process');
    | ^
  7 | const { isLinux, getReport } = require('./process');
  8 | const { LDD_PATH, SELF_PATH, readFile, readFileSync } = require('./filesystem');
  9 | const { interpreterPath } = require('./elf');

Import trace for requested module:
./node_modules/sharp/lib/utility.js
./node_modules/sharp/lib/index.js
./node_modules/plaiceholder/dist/plaiceholder.esm.js
./src/lib/images.ts
./src/components/tour/TourGallery.tsx
./src/components/tour/ImageGallery.tsx
./src/components/partner/tours/TourContentPreview.tsx
./src/components/partner/tours/TourWizard.tsx
```

OpenCode used `ollama-cloud/glm-5.3-flash` to identify the unused export and remove it and its direct dependency. The relay timed out during verification. The subsequent checks in this report were run independently of the relay. The placeholder helper still uses the supplied image blur data or the built-in gradient. Next retains its optional `sharp` dependency for server-side image optimization. [Image helper](../../frontend/src/lib/images.ts) · [Dependency manifest](../../frontend/package.json) · [Lockfile](../../frontend/package-lock.json)

On the fixed snapshot, `node node_modules/next/dist/bin/next dev --webpack --hostname 0.0.0.0` reported ready in 2.7 s. Host `curl.exe` requests to `http://localhost:3006/en/partner/tours/create` returned 200 in 116.570 s cold and 2.316 s warm. The compiler still emitted a nonfatal dynamic dependency warning through `ErrorBoundary`, Sentry, Prisma and OpenTelemetry. The `child_process` error was absent from the fixed log. The browser acceptance below used Turbopack.

A second attempt to reintroduce only the old helper in the disposable webpack runtime hit its 90 s request limit before producing a fresh compilation result. The original saved browser overlay remains the evidence for the failing trace. The disposable helper was restored after the probe.

### Turbopack startup and route compilation

The baseline Next 16.2.3 Linux snapshot reported ready in 403 ms. The first manual request to `/en/partner/tours/create` timed out after 110.011 s with zero bytes. The log showed proxy and route compilation and a 9,399 ms slow-filesystem benchmark. The sampled container memory events reported zero OOMs or kills, and CPU counters showed zero throttling. Both snapshots used copied files in the container filesystem. The original Turbopack root resolved to `/` in that environment. The changed configuration scopes it to the frontend project containing its own dependencies and lockfile. [Next config](../../frontend/next.config.ts)

With the image fix and scoped root, Next reported ready in 380 ms. The cold client request still reached its 130 s limit. Next then logged HTTP 200 for the route in about 2.2 minutes. Warm requests returned HTTP 200 in 6.344 s and 0.400 s. The Spanish and Italian routes and the English edit route also returned HTTP 200 in warm manual checks. This establishes that the changed snapshot can serve the route after compilation. The timing comparison changes both the root and the client graph, so it does not isolate either change's contribution or establish a universal cold-start time. Existing Playwright timeouts are unchanged.

### Acceptance checks

The partner creation spec exercises the authenticated route and real wizard. It checks a clean and restored unselected draft, Spanish reload, a real create under `translations.es`, a repeat save on the same tour ID, and rejection of a direct source-locale change. It also opens a legacy edit-response fixture with the source locale omitted. The tests wait for the wizard's controls after `DOMContentLoaded` because a completed route response did not guarantee the browser `load` event within the test budget. The focused accessibility cases cover localized labels and source notice, plus error announcement and focus. The validation test selects the wizard's exact Next button inside `main` to distinguish it from the Next.js developer-tools button. [Browser creation](../../frontend/tests/e2e/partner/tour-create.spec.ts) · [Browser accessibility](../../frontend/tests/e2e/a11y/partner-a11y.spec.ts)

The first direct-port setup timed out during route warm-up, and the five read-only cases subsequently failed while the test API was unavailable or rejected the stored session. Through nginx on the seeded disposable stack, partner setup passed in 15.1 s. The first six-case run passed three cases and exposed authentication startup, reload, and Next button locator failures. After the test adjustments, the retry passed five cases. The Spanish POST reached PHP and returned 201 after the browser had already timed out and nginx recorded 499. The disposable database contained a new Spanish-source tour, but this retry did not verify the repeat save or lock assertions.

The first Docker-profile run passed partner setup and five targeted tests. The Spanish test caught a representation difference in the rich-text editor, which serialized accented letters as HTML entities. The assertion now decodes the submitted HTML and compares the full original text, while checking that Spanish is the only authored translation key. A second run passed the API assertions and reached the editor, which displayed `Español` as the language name. The test now checks that exact notice and the `(ES)` field label.

### Verification commands

Ran from `frontend` on the host.

```powershell
node node_modules/jest/bin/jest.js --runInBand --runTestsByPath src/lib/stores/__tests__/tourWizard.test.ts src/components/partner/tours/__tests__/TourWizard.test.tsx src/components/partner/tours/__tests__/TourWizard.locale.test.tsx src/lib/validators/__tests__/partner.test.ts src/components/tour/__tests__/TourDetail.test.tsx src/components/search/__tests__/TourCard.test.tsx 'src/app/[locale]/(partner)/partner/tours/[id]/edit/__tests__/page.test.tsx'
npm run typecheck
npm run lint -- src/lib/images.ts tests/e2e/partner/tour-create.spec.ts tests/e2e/a11y/partner-a11y.spec.ts next.config.ts
```

Jest passed seven suites and 194 tests. TypeScript and scoped ESLint exited 0. The backend application source stayed unchanged during this follow-up. The earlier 37-test backend result is recorded above.

Ran with Playwright 1.60.0 and Chromium in the copied Next.js 16.2.3 dev runtime, using Node 20.20.2. The current frontend files were copied onto the container filesystem. The runtime joined the isolated network for the `docker-compose.ci.yml` services. Laravel ran `php artisan migrate --force --seed` against disposable PostgreSQL with the database queue driver. Nginx served the frontend and API on one origin. Manual route requests checked that compilation had finished before acceptance. The commands use the existing Docker Playwright settings and saved partner authentication state.

```sh
DOCKER_ENV=true PLAYWRIGHT_BASE_URL=http://nginx node node_modules/@playwright/test/cli.js test --project=setup --grep 'authenticate as partner' --reporter=line --output /tmp/a2-docker-setup
DOCKER_ENV=true PLAYWRIGHT_BASE_URL=http://nginx node node_modules/@playwright/test/cli.js test tests/e2e/partner/tour-create.spec.ts tests/e2e/a11y/partner-a11y.spec.ts --project=chromium-partner --project=a11y-partner --no-deps --grep 'Supplier A2 source selection|difficulty and guide-language|english-source notice|empty wizard next' --reporter=line --output /tmp/a2-docker-targeted-acceptance
```

Partner setup passed in 34.3 s. All six targeted tests passed in 30.4 s. The real Spanish create returned 201. The repeat write to the same tour returned 200 with no source-locale field in the request. A direct attempt to change it to English returned 422 with a `source_locale` validation error. The saved tour reopened with its Spanish title and lock notice. The legacy case tested the current edit component with a missing-locale response fixture.

### Independent review findings

The fresh-context Codebase Details Goldfish confirmed the webpack import trace and the six-case browser result. It found a gap in that result. The wizard used nonempty source text to lock the picker, so clearing all text after a successful save could re-enable the other choices in the current session. The existing edit page and the backend still kept the source fixed. The wizard now also uses the saved draft ID to keep its source locked. The regression covers Save Draft, clearing the authored fields, and Back to Language. [Wizard](../../frontend/src/components/partner/tours/TourWizard.tsx) · [Wizard regression](../../frontend/src/components/partner/tours/__tests__/TourWizard.test.tsx) · [Browser creation](../../frontend/tests/e2e/partner/tour-create.spec.ts)

The reviewer also found that introducing Language had moved the existing locale and viewport Axe checks off Details. Those checks now enter Details, and the validation test checks announcements, focus, and Axe on both Language and Details. [Browser accessibility](../../frontend/tests/e2e/a11y/partner-a11y.spec.ts)

The following isolated pre-fix test reproduced the unlocked English radio after clearing a saved Spanish draft. It exited 1 with one assertion failure. The disposable runtime still had the old wizard source for this run.

```sh
node node_modules/jest/bin/jest.js --runInBand --runTestsByPath src/components/partner/tours/__tests__/TourWizard.test.tsx -t 'keeps the source fixed after a saved draft'
```

The final scoped browser run exited 0 with eight passing cases in 3.3 minutes. It reused the same authenticated Docker profile. A manual request warmed the rebuilt creation route first and returned HTTP 200 in 46.948 s. The Spanish case confirmed that the alternative languages stayed disabled after Save Draft, clearing the authored fields, and Back. The final run also exercised the restored Details Axe scans and the missing-title focus and announcement checks.

```sh
DOCKER_ENV=true PLAYWRIGHT_BASE_URL=http://nginx node node_modules/@playwright/test/cli.js test tests/e2e/partner/tour-create.spec.ts tests/e2e/a11y/partner-a11y.spec.ts --project=chromium-partner --project=a11y-partner --no-deps --grep 'Supplier A2 source selection|wizard create .*axe|difficulty and guide-language|english-source notice|empty wizard next' --reporter=line --output /tmp/a2-docker-targeted-lock
```

The independent reviewer rechecked the actual lock predicate, regression assertions, and final browser log. **EGM-004 is closed for the source-selection, hydration, and fixed-source browser acceptance.** The legacy case covers the existing editor's response fallback. Cross-session server draft recovery and first-save replay are separate A2 work. EGM-002 and EGM-003 remain open. This was local acceptance on a warmed, disposable stack. Full CI and release verification remain open.

The final rerun of the seven Jest suites listed above exited 0 with 195 passing tests. The final `npm run typecheck`, the expanded scoped ESLint command below, and `git diff --check` for the changed tracked files exited 0.

```powershell
npm run lint -- src/lib/images.ts src/components/partner/tours/TourWizard.tsx src/components/partner/tours/__tests__/TourWizard.test.tsx tests/e2e/partner/tour-create.spec.ts tests/e2e/a11y/partner-a11y.spec.ts next.config.ts
```

### Files changed in the browser follow-up

| Area | Files |
|---|---|
| Client-safe image placeholders | [images.ts](../../frontend/src/lib/images.ts), [package.json](../../frontend/package.json), [package-lock.json](../../frontend/package-lock.json), and [library README](../../frontend/src/lib/README.md) |
| Bundler root | [next.config.ts](../../frontend/next.config.ts) |
| Saved source lock and regression | [TourWizard.tsx](../../frontend/src/components/partner/tours/TourWizard.tsx) and [TourWizard.test.tsx](../../frontend/src/components/partner/tours/__tests__/TourWizard.test.tsx) |
| Browser acceptance | [tour-create.spec.ts](../../frontend/tests/e2e/partner/tour-create.spec.ts) and [partner-a11y.spec.ts](../../frontend/tests/e2e/a11y/partner-a11y.spec.ts) |
| Findings and evidence | This review report |

## EGM-002 publication follow-up, 2026-11-11

The pre-change inventory and regression evidence are recorded in the [investigation](supplier-portal-egm002-investigation.md). The runtime gateways are the general Filament create and edit pages, individual and bulk approval, the domain approval action, and the web-triggered demo seeder. Partner creation and updates already prohibit a published status. Partner submission also needs a lifecycle guard to prevent an archived tour from returning to review and later publication.

The first independent implementation review found two blockers. Record-specific authorization on the first bulk item could abort otherwise eligible later items. The approval action also read its owner without serializing against partner suspension. Suspension selects only tours already stored as published, so its query could omit a pending tour whose approval was in progress. Published-owner reassignment had the equivalent race against suspension of the target partner. A real PostgreSQL worker regression reproduced the missing owner serialization before the correction.

The temporary EGM-003 intersection guard protects the required source fields and approved ownership of a published tour. The web demo-generation action is refused because its seeders publish and rewrite tour content outside the governed workflow. Other existing admin translation editing behavior remains subject to the unresolved EGM-003 owner decision.

### Root cause and secured entry points

The general form persisted `status` directly, outside the approval action. The action itself trusted the supplied actor and could fall back to a stale model when the database row disappeared. Owner eligibility was checked without locking the partner. Filament also saves relationship rows before updating the main record, so validation confined to the final update would leave a source-content bypass unless the whole save rolled back.

| Runtime entry point | Current enforcement and evidence |
|---|---|
| Filament general create | Read-only, non-dehydrated status display plus server-side refusal of non-draft input; creation forces draft and discards `published_at`. Real Livewire regressions cover forced publication for `en/es/it`. |
| Filament general edit | Locks persisted owners and tour before relationship persistence. Refuses lifecycle changes, including stale form state. A transaction rolls back metadata and relationships if a published tour loses required source content or approved ownership. Valid published metadata and nonblank source edits retain the existing lifecycle, stamp and audit count. |
| Individual approval and direct domain invocation | `ApproveTourAction` reloads and authorizes the actor against the locked record, validates pending-review eligibility, approved owner and required canonical title/description, then atomically publishes and audits. A deleted record cannot fall back to an old instance. |
| Bulk approval | Authorizes `bulkPublish` for the class and applies the same action independently to every selected record. Mixed valid, invalid and record-denied items are tested with the denied item ordered first and last. Refused items keep their state and receive no publication event. |
| Demo generation from the admin page | The actual mounted action returns 403 before media deployment, seeders or cache operations. This is a temporary fail-closed workflow restriction, not a resolution of EGM-003. The page, read-only data and separate cache action remain available. |
| Partner create/update APIs | Existing requests prohibit platform-owned status and source-locale mutations; creation forces draft and update does not expose lifecycle writes. Existing request and ownership regressions pass in the broader run. |
| Partner review submission | Rejects archived or published records before writing; draft/rejected submission and valid pending-review retries retain their existing source-content validation. This closes the archived-to-review-to-publication route. Partner archive redesign remains outside this fix. |
| Other application surfaces | No additional tour-publishing writer was found in admin REST, availability management, scheduled commands, booking code or queue jobs. Standalone setup seeders, factories and direct database operator access are not runtime approval endpoints. The web-triggered seeder was explicitly included above. |

Generated derivatives are never consulted by the publication gate. The matrix covers all three source locales with absent, pending, stale and failed derivatives. Source-locale immutability is unchanged. Current and proposed partner rows are locked in stable ID order before the tour, matching suspension's lock order. The first publication stamps `published_at`; re-publication after a legitimate resubmission preserves it. Repeated or stale approval cannot create a second effect. An audit-write failure rolls back publication and its timestamp.

### Files changed for EGM-002

| Purpose | Files |
|---|---|
| Domain publication and shared guards | [ApproveTourAction.php](../../backend/app/Domains/Admin/Actions/ApproveTourAction.php), new [TourPublicationGuardService.php](../../backend/app/Domains/Admin/Services/TourPublicationGuardService.php) |
| Filament enforcement | [TourResource.php](../../backend/app/Filament/Resources/TourResource.php), [CreateTour.php](../../backend/app/Filament/Resources/TourResource/Pages/CreateTour.php), [EditTour.php](../../backend/app/Filament/Resources/TourResource/Pages/EditTour.php), [DemoContentManager.php](../../backend/app/Filament/Pages/DemoContentManager.php) |
| Review lifecycle boundary | [TourService.php](../../backend/app/Domains/Partner/Services/TourService.php) |
| Permanent regressions | New [TourPublicationGuardTest.php](../../backend/tests/Feature/Admin/Filament/TourPublicationGuardTest.php), [DemoPublicationGuardTest.php](../../backend/tests/Feature/Admin/Filament/DemoPublicationGuardTest.php), [TourPublicationInvariantsTest.php](../../backend/tests/Feature/Admin/TourPublicationInvariantsTest.php), [TourPublicationConcurrencyTest.php](../../backend/tests/Feature/Admin/TourPublicationConcurrencyTest.php), and [TourApprovalWorker.php](../../backend/tests/Support/TourApprovalWorker.php) |
| Existing fixture authorization | [TourOptionsTest.php](../../backend/tests/Feature/Partner/TourOptionsTest.php): grant its admin the required `manage_tours` flag. Other pre-existing working-copy edits in this file belong to earlier source-locale work. |
| Investigation and current status | [Pre-fix investigation](supplier-portal-egm002-investigation.md) and this report |

### Executed verification

OpenCode implementation used the previously owner-approved `ollama-cloud/glm-5.3-flash`. The orchestrator reviewed and corrected its diff, including first-item bulk authorization and owner locking. The checks below were executed independently on a working-copy snapshot in the disposable `bookly-egm002-tests` container, PHP 8.3.36, with the repository test bootstrap pinned to `bookly-test-postgres/bookly_test`. The development database was not used. Commands are shown relative to `backend` inside that runner; the host invocation prefixes each with `docker exec bookly-egm002-tests`.

```sh
php vendor/bin/pest tests/Feature/Admin/Filament/TourPublicationGuardTest.php tests/Feature/Admin/Filament/DemoPublicationGuardTest.php tests/Feature/Admin/TourPublicationInvariantsTest.php tests/Feature/Admin/TourPublicationConcurrencyTest.php tests/Feature/Admin/Filament/TourResourceTest.php tests/Feature/Admin/TourPublishingSecurityTest.php tests/Feature/Admin/TourModerationTest.php tests/Feature/Admin/GovernanceAuditTest.php --compact --do-not-cache-result
php vendor/bin/pest tests/Feature/Admin tests/Feature/Partner --compact --do-not-cache-result
php vendor/bin/phpstan analyse --no-progress --memory-limit=1G
php vendor/bin/pint --test app/Domains/Admin/Actions/ApproveTourAction.php app/Domains/Admin/Services/TourPublicationGuardService.php app/Domains/Partner/Services/TourService.php app/Filament/Pages/DemoContentManager.php app/Filament/Resources/TourResource.php app/Filament/Resources/TourResource/Pages/CreateTour.php app/Filament/Resources/TourResource/Pages/EditTour.php tests/Feature/Admin/Filament/TourPublicationGuardTest.php tests/Feature/Admin/Filament/DemoPublicationGuardTest.php tests/Feature/Admin/TourPublicationInvariantsTest.php tests/Feature/Admin/TourPublicationConcurrencyTest.php tests/Support/TourApprovalWorker.php tests/Feature/Partner/TourOptionsTest.php
```

| Check | Actual result |
|---|---|
| Final focused publication, Filament, moderation and audit suites | **146 passed, 792 assertions, 45.30 s**, exit 0. Includes real Livewire create/edit, individual/bulk approval, rollback, all source locales, derivative independence and two independent-process PostgreSQL races. |
| Broader Admin and Partner suites | **684 passed, 2 failed, 3,601 assertions, 183.51 s**, exit 2. Both failures are in unchanged `AdminE2EFixtureSeederTest`: its seeder calls unavailable `imagejpeg()`. Direct runtime checks confirmed `function_exists('imagejpeg') === false` and GD `JPEG Support === false`. No assertions were skipped or weakened. |
| PHP syntax | `php -l` passed for all 13 PHP files listed in the Pint command. |
| Pint | Final command above passed all 13 files. |
| PHPStan | **21 errors before and after**. Comparing outputs found the same diagnostics; only an existing `TourService` error moved from line 647 to 655. Errors are in booking, partner/search translation and option code. The repository configuration excludes Filament. This is not a passing full static-analysis gate. |
| Diff whitespace | Scoped `git diff --check` passed for the changed tracked production files. |

The first post-fix focused run passed 144 and failed two Demo tests because Filament's convenience helper accessed a null component after the expected 403. The final tests mount the real action and invoke `callMountedAction` through Livewire; the server refusal and absence of tour/audit writes remain asserted. The final 146-case result supersedes that harness failure. Pest still emits a result-cache warning because the vendor mount is read-only, despite `--do-not-cache-result`.

Raw local run output is retained at `C:/Users/HaTeM/AppData/Local/Temp/bookly-egm002-focused-final.log`, `bookly-egm002-admin-partner-after.log`, `bookly-egm002-phpstan-baseline.log`, `bookly-egm002-phpstan-after.log` and `bookly-egm002-syntax-pint.log`. These are local execution artifacts, not a CI result. No frontend code changed in this slice, so frontend suites were not rerun for it. No merge or release gate is claimed.

### Independent Goldfish and remaining boundaries

The fresh-context Codebase Details Goldfish read the actual implementation, entry-point inventory, regressions and execution evidence. Its initial blockers were the bulk first-item authorization and owner-suspension race described above. Both were corrected. The independent-process tests observe `pg_blocking_pids` before committing suspended partner state and verify post-release refusal of approval and real Livewire owner reassignment. The parent transaction writes suspended state directly; these tests do not call `SuspendPartnerAction` itself.

After inspecting the persisted final 146-case result and qualified broader results, the independent reviewer approved **EGM-002 closure for the inspected publication scope**, with no remaining publication blocker found. It explicitly did not approve full verification or release readiness. The checks remain local; no CI, merge or release result is inferred.

**EGM-003 stays open.** The guard rejects only destructive required-source or invalid-owner changes on published tours. It does not authorize a new admin canonical-content policy or resolve direct derivative writes, revision synchronization, regeneration or translation audit policy. Existing nonblank admin source edits are deliberately preserved and tested. Demo regeneration requires a separately approved workflow before it can be restored. Existing direct-actor authorization concerns in reject/unpublish/archive actions are adjacent follow-up work; those actions do not transition a tour into published.

No additional application-controlled publication bypass was found within the inspected runtime inventory. This does not certify Phase A or release readiness: EGM-003, EGM-005, the broader JPEG environment failures, existing PHPStan errors and full CI/release verification remain open. The current A1–A5 matrix above incorporates this publication remediation; the dated earlier A2 matrices remain historical evidence.

The next smallest independent implementation candidate remains A1 navigation/access guidance, after its exact acceptance criteria are recorded. Further A2 draft recovery needs the EGM-005 revision/retry contract first. No A3, A5, Phase B or Phase C work was started here.
