# EGM-003 implementation verification

Date: 2026-11-17 (initial record 2026-11-14). Status: pre-code design gate passed; local implementation, verification gates, and Round 2 fresh-context Goldfish review passed with unanimous PASS (`2026-11-17`). Local verification of EGM-003 is complete.

Policy/design commits: `324cb71` (reviewed design, synchronized contracts and manifest), `b3aed8d` (reproduced A2 browser selector correction).
Source baseline before these documentation/test-only changes: `f07d3d0`.

## Authorized boundaries

Use [v3 design](../designs/supplier-portal-egm003-governance-v3.md), [execution manifest](../designs/supplier-portal-egm003-execution-plan.md), [ADR 0024](../adr/0024-tour-content-governance.md) and [completed independent pre-code review](supplier-portal-egm003-design-review.md). Failed v1/v2 drafts are historical evidence, not implementation options. No production database migration/deployment is authorized by this local development task.

OpenCode started with owner-selected `opencode/minimax-m3.1-flash-free`, variant `max`; the orchestrator reviews and re-runs gates. OpenCode 2.3 requires `provider/model#variant`; a temporary compatibility copy of the relay maps that syntax without changing the project skill. After the reviewed design, both implementation dispatches reached MiniMax's free usage limit (HTTP429, advertised retry after about 8.8 hours). The frontend dispatch wrote no code; the backend dispatch left the initial regression test. Implementation resumed with the separately owner-authorized `ollama-cloud/glm-5.3-flash` from the earlier Supplier Portal authorization. The owner subsequently answered the optional preference question and explicitly reaffirmed that GLM provider/model. No approval was inferred from silence and no unapproved provider was selected.

## Independently executed baseline

| Check | Actual result |
|---|---|
| Pest: TourPublicationGuard, TourPublicationInvariants, TourPublicationConcurrency, TourTranslation | 102 passed, 726 assertions, 97.56s. Copied baseline source, PHP 8.3.36, disposable PostgreSQL. |
| Pest: DemoPublicationGuard and AdminE2EFixtureSeeder | 10 passed, 41 assertions, 25.78s. JPEG-capable PHP 8.3.36 image; disposable PostgreSQL. |
| Focused Jest: wizard store, Wizard, Wizard.locale, existing edit page | 4 suites / 68 passed, 105.787s. |
| Full frontend Jest | 84 suites / 928 passed, 91.562s in copied Linux runtime. Existing jsdom navigation/console warnings are in the output; this is not a warning-free claim. |
| PHPStan | Zero errors on current baseline. The old report's 21-error result remains historical. |
| Full backend Pint | 749 files passed. |
| Frontend TypeScript | `npm run typecheck` exited 0. |
| Scoped ESLint | Existing partner accessibility file exited 0 after selector correction. |
| Browser authentication | Initial cold setup timed out at 60s; after warming routes, unchanged setup passed in 48.2s and saved real partner authentication. |
| EGM-004 browser/Axe baseline | Initial 7 passed / 1 failed due broad `/Next/i` matching both wizard and devtools. Exact wizard Next in main preserved assertions; final 8 passed in 43.0s. |
| Documentation | Both independent reviewers passed v3, manifest and corrected companion SYNC. 221 local links resolved; diff whitespace check passed with line-ending warnings. |

Commands executed relative to backend in the copied PHP runner (host prefix `docker exec bookly-egm002-tests` for the first baseline and PHPStan; `docker exec bookly-egm003-tests` for JPEG/Pint):

```sh
php vendor/bin/pest tests/Feature/Admin/Filament/TourPublicationGuardTest.php tests/Feature/Admin/TourPublicationInvariantsTest.php tests/Feature/Admin/TourPublicationConcurrencyTest.php tests/Feature/Partner/TourTranslationTest.php --compact --do-not-cache-result
php vendor/bin/pest tests/Feature/Admin/Filament/DemoPublicationGuardTest.php tests/Feature/Admin/AdminE2EFixtureSeederTest.php --compact --do-not-cache-result
php vendor/bin/phpstan analyse --no-progress --memory-limit=1G
php vendor/bin/pint --test
```

Both runners use the repository bootstrap pin to `bookly-test-postgres/bookly_test`, not the development database. Pest emits its read-only vendor result-cache warning despite `--do-not-cache-result`. The separate browser stack is `bookly-egm003-app/postgres/web/nginx` on its own network, database `bookly_accept`. No provider key or real personal data was used.

Frontend commands used the repository manifest/configuration:

```sh
node node_modules/jest/bin/jest.js --runInBand --runTestsByPath src/lib/stores/__tests__/tourWizard.test.ts src/components/partner/tours/__tests__/TourWizard.test.tsx src/components/partner/tours/__tests__/TourWizard.locale.test.tsx 'src/app/[locale]/(partner)/partner/tours/[id]/edit/__tests__/page.test.tsx'
node node_modules/jest/bin/jest.js --runInBand
npm run typecheck
node node_modules/eslint/bin/eslint.js tests/e2e/a11y/partner-a11y.spec.ts
```

Browser commands in the copied web runtime, with `DOCKER_ENV=true PLAYWRIGHT_BASE_URL=http://nginx`:

```sh
node node_modules/@playwright/test/cli.js test --project=setup --grep 'authenticate as partner' --reporter=line --output /tmp/egm003-baseline-auth-warm
node node_modules/@playwright/test/cli.js test tests/e2e/partner/tour-create.spec.ts tests/e2e/a11y/partner-a11y.spec.ts --project=chromium-partner --project=a11y-partner --no-deps --grep 'Supplier A2 source selection|wizard create .*axe|difficulty and guide-language|english-source notice|empty wizard next' --reporter=line --output /tmp/egm003-a2-baseline-fixed
```

First manual nginx request hit its 60s upstream limit (504). A direct Next warm-up then returned 200 in 43.973s; later partner routes returned 200. Tests used warmed routes and unchanged timeouts. Cold startup is not certified by this acceptance.

Raw baseline logs are local files under `C:/Users/HaTeM/AppData/Local/Temp/` with prefix `bookly-egm003-`: `baseline.log`, `jpeg-baseline.log`, `jest-baseline.log`, `jest-full-baseline.log`, `phpstan-baseline.log`, `pint-full-baseline.log`, `typecheck-baseline.log`, `browser-auth-warm.log`, `a2-browser-baseline.log`, and `a2-browser-baseline-fixed.log`.

## Feature acceptance

The acceptance checklist below summarizes verified local evidence against the approved design and contracts; earlier failed checkpoints below remain preserved as historical evidence. Preserve EGM-002/004 protections and do not infer CI, merge, Phase A, or production release readiness from local checks; final fresh-context Goldfish review is in progress.

### Acceptance evidence checklist

These checks track the approved scope; an implementation file alone does not satisfy them. Final evidence must identify the actual test and command for each row.

| Acceptance boundary | Current evidence |
|---|---|
| General Filament cannot edit canonical source, derivatives or governed presentation, including forged raw state | Covered by `backend/tests/Feature/Admin/Filament/TourContentGovernanceTest.php` (14 tests) and `TourPublicationInvariantsTest.php` (15 tests), executed inside the post-final-edit broad domain gate (`1,284 passed, 7,434 assertions`, exit 0, `bookly-egm003-broad-domain-gate-final.log`). |
| Partner requests enforce ownership, immutable locale, exact base/hash tokens and new IDs on replacement | Covered by `backend/tests/Feature/Partner/TourRevisionGovernanceTest.php` (48 tests) and the post-final-edit broad domain gate (`bookly-egm003-broad-domain-gate-final.log`). |
| Field-specific mandatory-reason requests and partner responses cover initial and published contexts | Covered by `backend/tests/Feature/Partner/TourRevisionGovernanceTest.php`, `backend/tests/Feature/Admin/TourRevisionGovernanceTest.php` (24 tests), `backend/tests/Feature/Admin/Filament/TourContentGovernanceTest.php`, and real PostgreSQL lock/concurrency tests in `backend/tests/Feature/Admin/TourRevisionConcurrencyTest.php` (3 tests), all passing in `bookly-egm003-broad-domain-gate-final.log`. |
| Published proposal stays private; approval atomically changes source/presentation/media/option text and preserves commerce | Covered by `backend/tests/Feature/Admin/TourRevisionGovernanceTest.php`, `backend/tests/Feature/Partner/TourRevisionGovernanceTest.php`, `backend/tests/Feature/Admin/Filament/TourContentGovernanceTest.php`, and `backend/tests/Feature/Search/TourRevisionProjectionTest.php` (9 tests), passing in `bookly-egm003-broad-domain-gate-final.log`. |
| Superseded review attempts persist reconciliation and return conflict without publication effects | Covered by `backend/tests/Feature/Partner/TourRevisionGovernanceTest.php`, `backend/tests/Feature/Admin/TourRevisionGovernanceTest.php`, `backend/tests/Feature/Admin/TourRevisionConcurrencyTest.php`, and `backend/tests/Feature/Admin/Filament/TourContentGovernanceTest.php` (including stale-modal approval refusal), passing in `bookly-egm003-broad-domain-gate-final.log`. |
| Exceptional corrections require explicit permission, declared fields, audit and ordinary review | Covered by `backend/tests/Feature/Admin/Filament/TourContentGovernanceTest.php` and `backend/tests/Feature/Admin/TourRevisionGovernanceTest.php` (explicit correction permission, all 9 declared canonical field paths, ordinary review requirement, and non-epoch-only response enforcement), passing in `bookly-egm003-broad-domain-gate-final.log`. |
| Gemini jobs fence success and failure by current source hash plus epoch, including A-B-A and legacy jobs | Covered by `backend/tests/Feature/Partner/TourTranslationJobStateTest.php` (15 tests), `TourTranslationConcurrencyTest.php` (5 tests), `TourTranslationTest.php` (23 tests), `TourOptionsTest.php` (13 tests), `Search/TourProjectionRecoveryTest.php` (1 test), and `Translation/AutoTranslationEngineTest.php` (7 tests, including S4 tour field-engine retirement), passing in `bookly-egm003-broad-domain-gate-final.log`. Provider/queue network boundaries use fake drivers/HTTP mocks without live Gemini calls. |
| Public detail, cards, index and SEO observe coherent approved content; cache hits revalidate eligibility | Covered by `backend/tests/Feature/Search/TourOwnerEligibilityTest.php` (31 tests), `TourRevisionProjectionTest.php` (9 tests), `TourSearchIndexTest.php` (10 tests), `TourDetailTest.php`, `SitemapTest.php`, `Blog/BlogPostTest.php`, `Reviews` (`54 passed, 161 assertions`, `bookly-egm003-reviews-final.log`), and frontend outer-cache tests (`caching.test.ts` inside `bookly-egm003-jest-final.log`), passing in `bookly-egm003-broad-domain-gate-final.log`. Search pagination totals retain documented eventual consistency with Scout. |
| Previously confirmed booking snapshots survive promotion; concurrent booking sees one approved option projection | Covered by `backend/tests/Feature/Partner/TourOptionsTest.php` (payment confirmation + option-text promotion snapshot preservation), `backend/tests/Feature/Booking/TourOwnerEligibilityTest.php` (5 tests, including PostgreSQL owner-lock concurrency), `Booking/BookingOptionsTest.php`, and `Booking/ConcurrencyTest.php`, passing in `bookly-egm003-broad-domain-gate-final.log`. |
| Existing EGM-002 publication and EGM-004 source-locale protections | Post-change backend regressions (`TourPublicationInvariantsTest.php`, `TourPublicationGuardTest.php`, `TourPublicationConcurrencyTest.php`, `DemoPublicationGuardTest.php`, `AdminE2EFixtureSeederTest.php`) passed in `bookly-egm003-broad-domain-gate-final.log`; frontend wizard Jest suites (`tourWizard.test.ts`, `TourWizard.test.tsx`, `TourWizard.locale.test.tsx`) passed in `bookly-egm003-jest-final.log`; Playwright A2 (`tour-create.spec.ts` `--grep "Supplier A2 source selection"`, `3 passed`, `bookly-egm003-playwright-a2.log`) and `filament-admin.spec.ts` (`7 passed`, `bookly-egm003-playwright-admin.log`) passed. |
| Real partner/admin/public browser flow, mixed saves, EN/ES/IT, keyboard, four viewports and Axe | Covered by `frontend/tests/e2e/partner/tour-revision.spec.ts` (`6 passed`, `1.8m`, `bookly-egm003-playwright-revisions.log` after final Step 1 & 2 code fixes) and `frontend/tests/e2e/a11y/partner-a11y.spec.ts` (`21 passed`, `1.5m`, `bookly-egm003-playwright-a11y.log`) against isolated `bookly-egm003-app/postgres/web/nginx`. |
| Disposable migration round-trip, backend/static gates, frontend gates and final fresh-context Goldfish | Disposable `bookly_egm003_roundtrip_20261115` `up/down/up` + double adoption rehearsal passed `13/13` checks (`bookly-egm003-migration-rehearsal-final.log`); backend gates passed (`1,284` broad + `54` reviews + `141` extra = `1,479` Pest tests; `PHPStan` 0 errors, `bookly-egm003-phpstan-final.log`; `Pint` 777 files pass, `bookly-egm003-pint-final.log`); frontend gates passed (`86 suites / 985 tests`, `bookly-egm003-jest-final.log`, `typecheck` 0, `lint` 0 errors / 1 warning, 38 Playwright checks); final fresh-context Goldfish review in progress. |

### Permanent security reproduction before production changes

OpenCode wrote `backend/tests/Feature/Admin/Filament/TourContentGovernanceTest.php` using only pre-existing classes and fixtures before its quota error. The orchestrator reviewed it, corrected the derivative dataset to genuinely exercise both ES and IT, removed an unused duplicate dataset, removed the existing Italian fixture before testing addition of that locale, and moved source/derivative persisted-value assertions ahead of the form-error assertion. PHP syntax and scoped Pint passed.

After synchronizing only current backend source and that new test into the isolated runner:

```sh
docker exec bookly-egm003-tests php vendor/bin/pest tests/Feature/Admin/Filament/TourContentGovernanceTest.php --filter 'authored source|generated derivative' --compact --do-not-cache-result
```

Result: **5 failed, 10 assertions, 9.39s**, exit1. All five failures show actual database titles changed from their original values to the forged administrative source/derivative titles: EN/ES/IT canonical sources and ES/IT derivatives. These are genuine expected-security failures, not missing new models/tables. No production code or migration had changed for that run. The other new test cases were not included in this filtered check. Local evidence: `C:/Users/HaTeM/AppData/Local/Temp/bookly-egm003-backend-red.log`.

### In-progress checkpoints (not acceptance)

OpenCode's captured raw tool output records the frontend red phase before its production edits: `BookingOptionsForm.test.tsx` had 1 failure / 6 passes, and the edit-page suite had 14 new failures / 21 existing passes. Early syntax-only failures were corrected before that behavioral run and are not counted as behavioral reproduction.

After the first frontend implementation, the panel and option component suites reported 19 passes in 2.819s. The edit-page suite then failed all 35 tests in 184.945s, including existing tests, so component success did not establish editor acceptance. The implementer is diagnosing that regression. TypeScript emitted no errors on its subsequent focused checkpoint, but the orchestrator has not yet repeated the final frontend gates.

An in-progress backend run of `TourContentGovernanceTest.php`, `TourPublicationGuardTest.php` and `TourPublicationInvariantsTest.php` reported 18 failures / 67 passes / 399 assertions. These are not accepted results; no failure is waived. The new workflow, PostgreSQL race coverage, migration round-trip, public projections and browser acceptance are still pending. Diagnostic probe files and filtered/piped implementer commands are not substitutes for permanent assertions and independently rerun commands with preserved exit codes.

The orchestrator independently copied the current frontend into the isolated Linux runtime. The first focused Jest command started before copy completion was confirmed, so it was followed by a full rerun after confirmed completion: **86 suites / 962 tests passed, 73.317s, exit0**. The focused run itself passed 102 tests in 42.72s; the full rerun is the evidence for the confirmed copied snapshot. Logs: `bookly-egm003-jest-revision-independent.log` and `bookly-egm003-jest-full-independent.log` in the local Temp directory.

That copy restarted Next development mode because configuration timestamps changed. Docker `npm run typecheck` failed on its zero-byte `.next/dev/types/routes.d.ts`; `next typegen` succeeded but regenerated the general route definitions without resolving the development file. A manual direct request to the real creation route then timed out after 90s while server logs showed cold proxy/not-found compilation. No timeout or typecheck exclusion was changed. This runtime checkpoint is unresolved, not a frontend typecheck or browser acceptance pass. The final build/browser verification must resolve it with evidence.

An independent host `npm run typecheck` subsequently exited2 because the newly added accessibility test referenced `ownedEnRow` without importing it. That result is preserved as an in-progress failure; the frontend implementer still owns the file and its correction.

The orchestrator intentionally stopped the initial broad backend relay at a review checkpoint after saving its tracked diff locally. This was a controlled implementation interruption, not a completed or passing dispatch. The resumed core session owns S1–S3/backend S6; a separate GLM session owns S4–S5. File ownership and the shared snapshot-hash method contract are recorded in the manifest. Core retains exclusive database-test execution until release. The checkpoint notes identify concrete API token/DTO, revision-approval, transaction-reconciliation and public-cache/read-boundary issues to verify and repair. No accepted policy was changed, and no native final Goldfish or browser acceptance is claimed by this split.

### Provider limit and reviewed repair checkpoint

All three GLM runs subsequently finished with `status: failed`, exit1, and actual HTTP429 `provider.quota` (Ollama session usage limit): frontend `ses_e253b70c7ffeoVhPfL7gir4ojw`, resumed core `ses_e2543833fffesopRR704m2o7nL`, projection `ses_e24fafd84ffeRteBkloH21FqcV`. Their local result files preserve the failures. None is a successful implementation handoff.

The owner then explicitly selected Nemotron 3 Ultra **free** via OpenRouter with `high` reasoning. `opencode models` listed `openrouter/nvidia/nemotron-3-ultra-550b-a55b:free`, and `opencode auth list` confirmed stored OpenRouter authentication. Three fresh bounded repair contexts started with that exact free model and requested reasoning variant; their captured tool activity confirms provider execution began. No paid model, different provider or provider success is inferred beyond these actual calls.

Both fresh native reviewers completed the [interim implementation Goldfish](supplier-portal-egm003-checkpoint-review.md) with FAIL. The report records actual ownership/API, transaction, replacement, approval, translation-planning and public-projection blockers. It is a checkpoint of incomplete code, not final acceptance or a reversal of the approved v3 design. Repairs and all pending gates still require independent verification and a new final review.

The diagnostic admin-browser attempt initially failed before login controls rendered. A direct HTTP check returned500; Laravel logs showed permission-denied writes to copied session/view directories. Only the isolated acceptance app's `storage` and `bootstrap/cache` ownership was corrected to its actual PHP-FPM user `www-data` (uid82). A subsequent stat confirmed ownership and direct `/admin/login` returned200 with the real Filament assets. Browser navigation still timed out waiting for full load; real administrative acceptance remains pending. No application permission or publication rule was weakened.

### Nemotron daily limit and independent backend checkpoint

All three Nemotron runs then finished failed, exit1, with HTTP429 `provider.rate-limit`: OpenRouter's free daily allowance reported limit50, remaining0. The response advertised reset `2026-11-15T00:00:00Z` (02:00 Cairo); this is the reported reset time, not a guarantee of later availability. No credits were purchased. The completed mutation events show core made no direct file edits, projection added work in `TourTranslationService.php`, and frontend changed `TourRevisionPanel.tsx`; global relay `touchedFiles` is not used to attribute other concurrent changes. The owner was asked how to continue after this external limit.

With all implementer processes finished and no Pest process active, the orchestrator reclaimed the test runner and synchronized current source. These commands ran independently against disposable PostgreSQL / copied source:

```sh
docker exec bookly-egm003-tests php vendor/bin/pest tests/Feature/Admin/Filament/TourContentGovernanceTest.php tests/Feature/Partner/TourRevisionGovernanceTest.php --compact --do-not-cache-result
docker exec bookly-egm003-tests php vendor/bin/pest tests/Feature/Admin/Filament/TourContentGovernanceTest.php --compact --do-not-cache-result
docker exec bookly-egm003-tests php vendor/bin/phpstan analyse --no-progress --memory-limit=1G
docker exec bookly-egm003-tests php vendor/bin/pint --test
```

| Independent check | Actual result |
|---|---|
| Combined permanent governance suites | **14 failed / 31 passed, 146 assertions, 57.63s, exit2**. Undefined refusal helpers and operational round-trip failure are among the observed failures. |
| Permanent Filament security suite, isolated to identify its actual result | **8 passed, 32 assertions, 17.54s, exit0**. These tests cover the named forged source/derivative/presentation and create cases; they do not establish the missing revision review workflow. |
| PHP syntax for every changed/untracked PHP source | **54 files passed, exit0** using `php -l` and an explicit current-file list. Parsing success does not resolve runtime/type errors. |
| PHPStan | **82 errors, exit1**. The independently checked pre-feature baseline had zero errors. No new error was waived as historical. |
| Pint | **771 files checked, 17 style issues, exit1**. No formatting-only success is inferred. |

Pest retains its read-only vendor result-cache warning. Raw logs in local Temp: `bookly-egm003-governance-checkpoint.log`, `bookly-egm003-filament-checkpoint.log`, `bookly-egm003-php-syntax-checkpoint.log`, `bookly-egm003-phpstan-checkpoint.log`, `bookly-egm003-pint-checkpoint.log`. No complete backend, browser, final TypeScript/build, concurrency, migration or final Goldfish gate passed for this implementation.

A recovery archive preserves 76 changed/untracked files without environment/key files: `C:/Users/HaTeM/AppData/Local/Temp/bookly-egm003-workingtree-ivq53prk.tar.gz`, SHA256 `ce85cc4b3418b1acbd6506f46015dea849c8b04483f44b34d334f1a79374e74d`. Failed feature code remains uncommitted. The earlier design and test-only commits remain separate historical evidence.

The [changed-file inventory](supplier-portal-egm003-changed-files.md) lists 87 paths relative to `f07d3d0`, including design/test-only commits and uncommitted code. Requirement-ID preservation was checked against `324cb71`: Spec 018 retained 46 FR/SC identifiers, Spec 019 retained 51 and Spec 020 retained 45, with none added or removed by the current synchronization. All 221 checked local Markdown links resolved after correcting the temporary checker's angle-bracket path parsing; the three new reports passed whitespace/newline checks. Documentation checks do not waive implementation failures.

After Nemotron's panel-only frontend edit, the orchestrator reran the two affected component suites on the current host files: **2 suites / 19 tests passed, 64.949s, exit0**. This narrow recheck does not add 19 distinct tests to the earlier full total or establish browser acceptance. Log: `bookly-egm003-jest-final-panel-checkpoint.log`.

Exact additional checkpoint commands (frontend host working directory for the second command; the syntax list contains the 54 current backend-relative PHP paths):

```sh
docker exec bookly-egm003-web node node_modules/jest/bin/jest.js --runInBand
node node_modules/jest/bin/jest.js --runInBand --runTestsByPath src/components/partner/tours/__tests__/TourRevisionPanel.test.tsx src/components/partner/tours/__tests__/BookingOptionsForm.test.tsx
docker exec bookly-egm003-tests sh -c 'while IFS= read -r php_source; do php -l "$php_source" || exit 1; done < /tmp/egm003-php-syntax-list.txt'
```

### ApMix resumption and administrative browser precondition

The owner selected `apmix/deepseek-v4.1-flash-1017-free` with `high` reasoning after the OpenRouter limit. The model catalogue listed that exact ID, and the new single owned-API dispatch emitted actual completed read tools. Execution is sequential within the existing manifest; no new feature acceptance is inferred from provider availability. Its first target is the owned revision API/isolation repair described in the current matrix.

Further browser diagnosis found that `admin@bookly.test` did not exist in `bookly_accept`. A read-only PHP check first verified that exact database and testing environment and returned `exists:false`. A narrowly guarded local provisioning script then created the deterministic admin factory fixture with the existing permission/role setup, only in that opted-in disposable database. No tour was published or auth bypass added. The real Filament login and tours-table inventory then succeeded through nginx/Livewire. The table's actual initial action label is `Publish`; its row key ends in `.table.records.{id}`. This corrects the speculative browser helper assumption that initial approval was on edit/view with an `Approve` label. The copied app still runs the earlier backend source; this is an environment precondition, not EGM-003 implementation acceptance.

The existing administrative Playwright file then ran unmodified through real authentication:

```sh
docker exec -e DOCKER_ENV=true -e PLAYWRIGHT_BASE_URL=http://nginx bookly-egm003-web node node_modules/@playwright/test/cli.js test tests/e2e/filament-admin.spec.ts --project=chromium --no-deps --reporter=line --output /tmp/egm003-admin-baseline
```

Result: **7 passed, 12.1s, exit0**. It verifies actual Livewire asset routing, login, anonymous/partner denial, wrong-password handling and resource loading on the copied baseline backend. Log: `bookly-egm003-admin-baseline.log`. It does not exercise the new revision workflow.

The development route-type file subsequently measured 5901 bytes instead of zero, and Next logs recorded completed filesystem-cache work. The earlier zero-byte artifact was therefore a transient observed startup state; no exact total warm-up duration or universal cold-start guarantee is claimed. A changed-files-only frontend sync copied 14 current files without rewriting configuration or generated types. Current-source TypeScript/ESLint are being independently rechecked; their result must be recorded before any pass is claimed.

The rechecks completed with **exit0** for both TypeScript and scoped ESLint on the current copied frontend, including the revision editor, panel, options, types/API and new browser/accessibility test files. Logs: `bookly-egm003-typecheck-apmix-checkpoint.log`, `bookly-egm003-lint-apmix-checkpoint.log`.

```sh
docker exec bookly-egm003-web npm run typecheck
docker exec bookly-egm003-web node node_modules/eslint/bin/eslint.js src/components/partner/tours/TourRevisionPanel.tsx src/components/partner/tours/BookingOptionsForm.tsx src/components/partner/tours/__tests__/TourRevisionPanel.test.tsx src/components/partner/tours/__tests__/BookingOptionsForm.test.tsx src/lib/api/partner.ts src/lib/api/types.ts src/types/partner.ts 'src/app/[locale]/(partner)/partner/tours/[id]/edit/page.tsx' 'src/app/[locale]/(partner)/partner/tours/[id]/edit/__tests__/page.test.tsx' tests/e2e/partner/tour-revision.spec.ts tests/e2e/a11y/partner-a11y.spec.ts
```

After those checks finished, only the owned web container was restarted (its configured entrypoint is `sleep infinity`) to stop the development server before building. Source and databases were retained. The ordinary build ran with the existing private-network configuration:

```sh
docker exec -e API_INTERNAL_URL=http://nginx -e NEXT_PUBLIC_API_URL= -e ALLOW_PRIVATE_IMAGE_UPSTREAM=true -e NEXT_TELEMETRY_DISABLED=1 bookly-egm003-web npm run build
```

Result: **exit 0**, Turbopack compilation 45s, TypeScript 17.9s and 89 static pages generated. Log: `bookly-egm003-build-checkpoint.log`. No compiler checks were disabled and no timeout was raised. The server then started via the existing `next start --hostname 0.0.0.0` command inside that container, reported ready in 2.5s, and a direct same-origin HTTP probe returned 200 for `/en/partner/tours/create` in 1587ms. This establishes build/start readiness of this frontend snapshot, not the new backend revision flow or its browser acceptance. Subsequent source changes still need applicable rechecks.

### ApMix execution outcome

The single repair dispatch ended with **failed / exit 1**, model `apmix/deepseek-v4.1-flash-1017-free`, variant `high`, session `ses_e249486beffeAP6LEuapr7mLee`. The provider returned HTTP 429 `provider.quota`: “Your monthly allowance is used up.” No monthly reset time was supplied; none is inferred. Completed direct mutation-tool events were empty, and no owned-API implementation handoff was delivered. The relay's global changed-file list includes existing work and orchestrator documentation, so it is not attributed to this provider.

The API authorization/result/DTO/operational-round-trip defects therefore remain unresolved. Source was not committed or deployed. Current frontend TypeScript, scoped ESLint, production build/start and seven baseline administrative browser checks passed as recorded above; these do not close EGM-003. A new permitted execution method/provider is needed before further delegated implementation can run. The reviewed design and requirement IDs remain unchanged, and the session retains its completed source investigation for resumption.

### Antigravity / Gemini execution, owner-selected recovery

The owner switched delegation to `agy-delegate` and selected `gemini-3.8-flash-high` with `high` effort. Antigravity CLI 1.5.9 listed the model. The first bounded owned-API dispatch used the existing reviewed repair brief, ordinary permissions and no bypass flag. It ended **failed / exit 1**, conversation `02abc1c7-73d9-4503-97fd-852e0656915d`, with no final report: a `command` tool required permission that headless mode auto-denied. This is an execution-permission failure, not a reported model quota failure or a completed code repair. Existing working-tree changes are not attributed to that run. Artifacts are in the local temporary directory `bookly-egm003-agy-owned-api`.

The same conversation was resumed on the same model in read-only plan mode to produce a concrete unified patch using file-reading tools only. The orchestrator will review/apply the proposed patch and execute verification locally. Global permission settings and unrelated MCP configuration were not changed. Patch generation and implementation acceptance are pending; EGM-003 remains open.

### Reviewed Gemini patches and local verification, 2026-11-15

Subsequent read-only proposal runs completed for the owned revision API, publication sealing and public-card coherence. The orchestrator applied reviewed changes and corrected proposal defects locally; proposal self-reports are not test evidence. Working-tree fingerprints also include concurrent orchestrator changes and cannot attribute all changed paths to the model. Feature changes remain uncommitted.

| Actual verification | Result | Local Temp log |
|---|---|---|
| Owned API first repair | 42 passed, 253 assertions, 11.22s | `bookly-egm003-agy-api-first-fix.log` |
| Owned API plus real PostgreSQL ownership races and reconciliation | 57 passed, 318 assertions, 13.50s | `bookly-egm003-agy-api-races-fix.log` |
| Publication sealing plus existing publication invariants | 72 passed, 480 assertions, 64.65s | `bookly-egm003-agy-seal-first-fix.log` |
| Public revision/card coherence | 11 passed, 30 assertions, 36.20s | `bookly-egm003-agy-cards-first-fix.log` |
| Earlier integration check before subsequent card repairs | 9 failed, 68 passed, 533 assertions, 14.17s | `bookly-egm003-agy-api-regressions.log` |

Exact passing commands, run sequentially against the disposable PostgreSQL test runner:

```sh
docker exec bookly-egm003-tests php vendor/bin/pest tests/Feature/Partner/TourRevisionGovernanceTest.php --compact --do-not-cache-result
docker exec bookly-egm003-tests php vendor/bin/pest tests/Feature/Admin/TourRevisionConcurrencyTest.php tests/Feature/Partner/TourRevisionGovernanceTest.php --compact --do-not-cache-result
docker exec bookly-egm003-tests php vendor/bin/pest tests/Feature/Admin/TourRevisionGovernanceTest.php tests/Feature/Admin/TourPublicationInvariantsTest.php --compact --do-not-cache-result
docker exec bookly-egm003-tests php vendor/bin/pest tests/Feature/Search/TourRevisionProjectionTest.php --compact --do-not-cache-result
```

Regression-first evidence includes 9 failing owned-API review cases, 6 failing PostgreSQL race/reconciliation cases, 6 failing publication cases with 2 passing controls, and 11 failing card cases after correcting a malformed fixture. The same focused checks subsequently passed as recorded above. These totals overlap and must not be added as distinct coverage. Pest still emits its read-only vendor result-cache warning.

The owned API now checks fresh ownership/account eligibility under the tour lock and serializes owned reads within that boundary. Forged base tokens cannot destroy a valid proposal, immutable replacement and response reconciliation are covered, and unchanged commercial saves retain identifiers and values. Publication now seals a new immutable approved revision transactionally, preserves the first publication timestamp and rolls back all effects if the real audit insert fails. Public-card projection retains fresh approved content, selected order, review aggregates and bounded relation loading. These focused passes do not verify the complete governance workflow.

A fresh-context interim owned-API Goldfish review identified further request/DTO, field-reference, media/option normalization, approval and translation integration gaps. Several ownership, token and atomic-write findings were repaired and retested; the remaining findings still require implementation and a new final review. PHPStan's last intermediate run reported 42 errors; later edits have not established a clean static-analysis gate. Full backend, current browser revision acceptance, migration/cutover and final Goldfish verification remain outstanding.

### Owner-approved public-visibility exception

The owner explicitly approved HTTP 404 for a still-published tour whose owning partner is not approved and active, before cached content is delivered. Eligible-owner temporary booking unavailability retains HTTP 200. New bookings must independently reject ineligible owners; public discovery, detail, SSR, SEO and cache representations must not expose their tours. Normal suspension-to-draft and historical booking snapshots remain preserved. The ADR, v3 design, execution manifest and affected detail contract record this narrow exception.

An independent fresh-context design reviewer passed the corrected owner-exception technical design and exact manifest only. This is not implementation acceptance. It covers generation envelopes under existing logical cache keys, fresh eligibility checks, structured sitemap dependencies, after-commit invalidation, final-state index reconciliation and the frontend outer-cache boundary. Booking owner-lock ordering requires a real PostgreSQL reproduction before modification.

The first bounded owner-detail proposal run ended exit 1 because Antigravity required renewed authentication and its login wait timed out. No final patch was captured. After the owner confirmed local login, the same `gemini-3.8-flash-high` conversation was resumed in read-only mode. Its retry is pending; no provider change, permission bypass or global connector change was made. EGM-003 remains OPEN; EGM-002/004 historical closure is preserved, EGM-005 remains open, and Phase A readiness is not claimed.

### Frontend outer-cache and option-fixture rechecks

The frontend caching regression first returned **18 failed / 4 passed** (47.053s), proving all six discovery reads still opted into the Next 300-second cache. Replacing those six options with `cache: 'no-store'` returned **22 passed** (3.293s). The broader check passed **4 suites / 47 tests** (29.87s). Scoped ESLint and TypeScript both exited 0. An independent fresh-context frontend Goldfish review passed this bounded slice, independently executing **2 suites / 28 tests**. These fetch-boundary checks do not establish backend eligibility, production Next behavior, browser acceptance or measured load impact. Existing route READMEs were corrected to match the policy.

```sh
# Working directory: frontend
node node_modules/jest/bin/jest.js --runInBand --runTestsByPath src/lib/api/__tests__/caching.test.ts
node node_modules/jest/bin/jest.js --runInBand --runTestsByPath src/lib/api/__tests__/caching.test.ts src/lib/api/__tests__/search.test.ts src/lib/api/__tests__/tours.test.ts 'src/app/[locale]/(public)/tours/[slug]/__tests__/page.test.tsx'
node node_modules/eslint/bin/eslint.js src/lib/api/search.ts src/lib/api/homepage.ts src/lib/api/categories.ts src/lib/api/destinations.ts src/lib/api/__tests__/caching.test.ts
node node_modules/typescript/bin/tsc --noEmit
```

Logs: `bookly-egm003-owner-frontend-cache.log`, `bookly-egm003-owner-frontend-focused.log`, `bookly-egm003-owner-frontend-lint.log`, `bookly-egm003-owner-frontend-typecheck.log` in local Temp. The independent reviewer used `npm test -- --runInBand --no-cache src/lib/api/__tests__/caching.test.ts src/lib/api/__tests__/tours.test.ts`.

After card repairs, the four-suite backend integration recheck returned **7 failed / 79 passed, 633 assertions, 67.74s**, exit 1. It ran the same four paths as the earlier integration check: `Partner/TourRevisionGovernanceTest.php`, `Admin/Filament/TourContentGovernanceTest.php`, `Partner/TourContentInputTest.php`, `Partner/TourOptionsTest.php` under `tests/Feature`, with `--compact --do-not-cache-result`. Log: `bookly-egm003-api-cards-integration.log`.

The existing option translation helper was then updated to pass the actual desired-state generation epoch. A separate regression proves that a legacy no-epoch job invokes no provider and changes no state, version or derivative rows. PHP syntax passed. The complete option suite recheck returned **3 failed / 12 passed, 215 assertions, 14.32s**, exit 1:

```sh
docker exec bookly-egm003-tests php -l tests/Feature/Partner/TourOptionsTest.php
docker exec bookly-egm003-tests php vendor/bin/pest tests/Feature/Partner/TourOptionsTest.php --compact --do-not-cache-result
```

Log: `bookly-egm003-option-epoch-fixtures.log`. The remaining three tests reach previously permitted general published-content/option-structure writes and now receive 422. They must preserve their public fallback/retirement/booking-snapshot assertions while being adapted to the approved review workflow where applicable. No assertion was weakened, test skipped or failure waived. Translation integration and full-suite acceptance remain open.

### Public owner eligibility: implementation and bounded review

The authenticated Gemini owner-detail retry completed exit 0 and returned a proposal. The orchestrator retained the regression-first real HTTP tests, applied reviewed production changes and rejected optional DI fallback and speculative test fixtures. Fresh lifecycle decisions now belong to the shared projection boundary, before cache access, followed by a fresh SQL approved-and-active owner check. The common model predicates cover bookability/SQL selection/publishing, and cached related cards are filtered by current IDs in a batch.

```sh
docker exec bookly-egm003-tests php vendor/bin/pest tests/Feature/Search/TourOwnerEligibilityTest.php --compact --do-not-cache-result
docker exec bookly-egm003-tests php vendor/bin/pest tests/Feature/Search/TourOwnerEligibilityTest.php tests/Feature/Search/TourRevisionProjectionTest.php tests/Feature/Search/TourDetailTest.php tests/Feature/Admin/TourPublicationInvariantsTest.php --compact --do-not-cache-result
docker exec bookly-egm003-tests php vendor/bin/pest tests/Feature/Reviews --compact --do-not-cache-result
```

Evidence sequence: new detail tests **33 failed / 3 passed, 122 assertions, 19.65s** before the fix; then **36 passed, 184 assertions, 5.98s**. The first broader run returned **2 failed / 150 passed, 1615 assertions, 31.93s**: one deliberately superseded unapproved-owner 200 expectation and one positive authoring fixture still using `complete`. The expectation now asserts 404/no data while retaining private-data assertions; the positive fixture is approved, with negative legacy-status coverage retained separately.

Independent scoped Goldfish found an adjacent public reviews lookup. Its real HTTP regression first failed (200 instead of 404; 3 assertions, 2.81s). Adding the existing eligible-owner scope to that public lookup closes this exposure without changing review store/update or unrelated lifecycle behavior. The subsequent broader command above passed **153 tests, 1647 assertions, 23.83s**; the separate existing reviews directory passed **54 tests, 161 assertions, 6.94s**. Scoped independent Goldfish passed both the detail implementation and this follow-up, without claiming broader feature acceptance.

Logs: `bookly-egm003-owner-detail-red.log`, `bookly-egm003-owner-detail-first-fix.log`, `bookly-egm003-owner-detail-regressions.log`, `bookly-egm003-owner-reviews-red.log`, `bookly-egm003-owner-detail-regressions-fix.log`, `bookly-egm003-owner-review-regressions.log`. PHP syntax passed for the named changed files. Scoped Pint first found four issues and then passed nine files after reviewed formatting with hash-checked backups. A full intermediate PHPStan run reported **38 errors**, including two new generic relationship type annotations; those annotations were corrected and the two scoped files then passed PHPStan. The full feature static-analysis gate remains outstanding.

### Guarded discovery cache and additional evidence

The next Gemini proposal completed exit 0 for the two cache services and four public discovery controllers. The orchestrator applied reviewed changes, required explicit DI, rejected an unpersisted generation fallback, and batched all homepage rail IDs together. Existing cache keys, TTLs and homepage tag behavior remain; old raw entries miss, late fills retain their captured generation, and fresh inconsistent-owner probes precede cached delivery. Database facets/aggregates rebuild when inconsistent published owners exist. **Search pagination totals still come from Scout and may lag; universally fresh counts or instantaneous index convergence are not claimed.** Owner-change callbacks and index/sitemap/blog work remain separate unfinished integrations.

Regression-first checks: **19 HTTP failures, 98 assertions, 4.87s** for warm discovery cards/counts and destination existence; three additional cache-envelope tests failed before implementation because the new method did not exist. After implementation, the owner suite passed **59 tests, 302 assertions, 9.15s**. Existing category/homepage/search plus owner checks passed **113 tests, 470 assertions, 14.88s**.

```sh
docker exec bookly-egm003-tests php vendor/bin/pest tests/Feature/Search/CategoryTest.php tests/Feature/Search/HomepageTest.php tests/Feature/Search/SearchToursTest.php tests/Feature/Search/TourOwnerEligibilityTest.php --compact --do-not-cache-result
docker exec bookly-egm003-tests php vendor/bin/phpstan analyse app/Domains/Search/Services/TourPublicCacheService.php app/Domains/Search/Services/TourPublicEligibilityService.php app/Domains/Search/Controllers/Public/SearchController.php app/Domains/Search/Controllers/Public/HomepageController.php app/Domains/Search/Controllers/Public/CategoryController.php app/Domains/Search/Controllers/Public/DestinationController.php --no-progress --memory-limit=512M
```

The scoped PHPStan command passed with no errors. Scoped Pint passed nine current source/test/worker files after formatting. Five more cache tests passed: real non-taggable FileStore, generation persistence/expiration, A→B→A token separation with unrelated sentinel preservation, missing-marker infrastructure failure before building, and measured batch query growth. The first four passed **19 assertions, 2.73s**; the fault test passed **3 assertions, 2.06s**. Warm cache measurements were **2 SQL queries / 2.007ms for 2 cards**, **2 SQL queries / 2.263ms for 8 cards**; the broader run observed 2 queries again (2.622ms/2.625ms). These are local samples, not production scalability or constant-time claims. File-store cleanup verifies its unique owned temporary directory, and cache driver/clock are restored. Independent fresh-context Goldfish passed this bounded implementation and its added tests; automatic owner-hook A→B→A remains unverified.

Logs: `bookly-egm003-owner-catalog-red.log`, `bookly-egm003-owner-envelope-red.log`, `bookly-egm003-owner-catalog-first-fix.log`, `bookly-egm003-owner-catalog-regressions.log`, `bookly-egm003-owner-catalog-phpstan.log`, `bookly-egm003-owner-catalog-pint-fixed.log`, `bookly-egm003-owner-cache-additional.log`, `bookly-egm003-owner-cache-fault.log`. One initial broader command named a nonexistent `DestinationTest.php` and ran no tests; it was corrected after enumerating actual files, without creating or skipping a suite.

### Broader Search and booking pre-fix checkpoint

```sh
docker exec bookly-egm003-tests php vendor/bin/pest tests/Feature/Search --compact --do-not-cache-result
```

The complete Search directory returned **11 failed / 230 passed, 1886 assertions, 33.93s**, exit 2. Three scale tests could not resolve the stopped disposable `bookly-test-meilisearch` service. Two operator tests retained superseded owner-200 expectations, one relation-only bookability fixture had no eligible owner, and one query-bound test measured 6 instead of its existing bound 5. The remaining failures involve the uncompleted revision workflow in projection recovery and old no-epoch job fixtures in search-index tests. None are waived as pre-existing.

The operator expectations were synchronized, missing/inactive owner unit controls added, and the known same-owner relation reused instead of adding another owner query or weakening the bound. These two files then passed **14 tests, 74 assertions, 3.47s**. The existing Meilisearch v1.10 test container was restarted after verifying its tmpfs test configuration/network; `/health` returned `available`. Its scale recheck is pending. Log: `bookly-egm003-owner-search-full.log`; follow-up `bookly-egm003-owner-operator-regressions.log`.

New independent booking acceptance tests reproduced **15 ineligible-owner cases returning 201 instead of 404**. Their authorized retry and same-key mismatch controls remain in place. One initial foreign-traveler fixture reused the test application's cached Sanctum user; it was corrected using the existing `BookingOptionsTest` guard-reset precedent, after which both mismatch controls passed (6 assertions, 4.18s). No authorization check was changed to satisfy this fixture.

A real PHP worker/PostgreSQL reproduction proved the owner-lock gap: while the parent held an owner-row update transaction changing eligibility, the booking worker completed **exit 0, stdout `allowed`, no stderr, one booking persisted**, without blocking on that owner. The permanent race test fails its required wait-then-refusal assertion (3 assertions, 3.81s). Fixtures and their exact dependent rows are cleaned in the disposable database; the helper was corrected to avoid creating an unrelated shared category. This evidence authorizes the owner-first shared lock specified in the reviewed design; a same-model bounded proposal for the booking Action is running. No booking fix or race PASS is yet claimed.

Logs: `bookly-egm003-owner-booking-red.log`, `bookly-egm003-owner-booking-retry-control.log`, `bookly-egm003-owner-booking-race-red.log`, `bookly-egm003-owner-booking-race-diagnostic-red.log`. The full frontend rerun after the outer-cache change passed **86 suites / 980 tests, 77.023s**, exit 0; observed JSDOM navigation and React act warnings remain visible (`bookly-egm003-owner-frontend-full.log`). Current browser revision acceptance and the full backend/static/release gates remain open.

### Booking owner fix, scale recovery and next regression checkpoint

The same-model booking proposal completed and the reviewed Action change is applied. It acquires the observed Partner shared lock before the existing Tour update lock, preserves authorized same-request retries and mismatch validation, refuses new ownership divergence with 409 without a late replacement-owner lock, and denies ineligible owners before reservation/booking/audit/payment effects. Phase 2 payment I/O and option handling remain unchanged.

```sh
docker exec bookly-egm003-tests php vendor/bin/pest tests/Feature/Booking/TourOwnerEligibilityTest.php --compact --do-not-cache-result
docker exec bookly-egm003-tests php vendor/bin/pest tests/Feature/Booking --compact --do-not-cache-result
docker exec bookly-egm003-tests php vendor/bin/phpstan analyse app/Domains/Booking/Actions/CreateBookingAction.php --no-progress --memory-limit=512M
docker exec bookly-egm003-tests php vendor/bin/pest tests/Feature/Search/ScaleTest.php --compact --do-not-cache-result
```

Booking results: first **19 passed, 93 assertions, 5.31s**; expanded tests **21 passed, 107 assertions, 6.16s**; broader Booking directory **104 passed, 423 assertions, 20.17s**. The expanded suite proves actual PostgreSQL eligibility-change blocking followed by 404, Tour ownership-transfer blocking followed by 409/no booking, and preserved private access/unchanged attributes for an explicitly historical confirmed fixture while public detail/new bookings are denied. It does not claim payment-confirmation acceptance. Fresh-context independent Goldfish passed the bounded Action/worker/tests and its added coverage.

Actual schema inspection confirmed `tours.partner_id` is NOT NULL with a foreign key to `partners`; no set-null deletion behavior exists. Orphan tour fixtures were not created and constraints were not disabled. PHPStan first identified three redundant null comparisons from the proposal. They were removed using the verified schema, and the scoped Action analysis passed with no errors. Defensive missing-row rejection remains; this did not change schema or policy.

After restarting the existing tmpfs Meilisearch test container and verifying its health, the three 10,000-tour scale tests passed **44 assertions, 25.13s**, exit 0. This addresses the observed stopped-service failure; no CI or universal production performance pass follows. Logs: `bookly-egm003-owner-booking-first-fix.log`, `bookly-egm003-owner-booking-expanded.log`, `bookly-egm003-owner-booking-regressions.log`, `bookly-egm003-owner-booking-phpstan.log`, `bookly-egm003-owner-booking-phpstan-fixed.log`, `bookly-egm003-owner-search-scale.log`.

The next owner after-commit/index slice remains unimplemented. Permanent regressions using a separate committed PostgreSQL connection produced **8 failed / 2 passed, 30 assertions, 5.33s** before its fix. They prove missing owner invalidation, premature homepage-tag eviction during ownership transactions, absent suspension generation rotation, a deleted tour's captured version surviving eviction, late removal deleting a currently eligible tour, and stale index/deletion jobs leaving documents behind. Rollback and unrelated-field controls pass. A separate real foreign-key deletion refusal control passed **5 assertions, 3.07s** without invalidation or queued work. Scalar fixture rows are individually cleaned; no global database reset is used.

```sh
docker exec bookly-egm003-tests php vendor/bin/pest tests/Feature/Search/TourOwnerEligibilityTest.php --filter 'representations|owner change rolls|tour ownership cache|unrelated partner|normal suspension|deleted tours captured|stale index and removal' --compact --do-not-cache-result
docker exec bookly-egm003-tests php vendor/bin/pest tests/Feature/Search/TourOwnerEligibilityTest.php --filter 'owner deletion is rejected' --compact --do-not-cache-result
```

Logs: `bookly-egm003-owner-hooks-red.log`, `bookly-egm003-owner-delete-control.log`. A bounded Gemini proposal for the six existing owner/cache/event/projection/index files is running. Sitemap/blog and the original revision approval/translation integrations remain outstanding; EGM-003 stays OPEN.

### Owner invalidation repairs and independent Scout queue blocker

The six-file proposal completed and was applied after review. Its first recheck passed 10 of 11 cases (54 assertions, 23.27s); the remaining deletion case proved that evicting version zero after deleting a Tour missed the captured version 37. Additional review regressions exposed repeat invalidation on a no-op Partner save and current-version lookup growth (2 versus 8 SQL queries). One initial batch fixture omitted required price; it was corrected before the 8-versus-2 failure was counted as evidence.

The orchestrator retained captured Tour deletion targets in the existing deletion callback, centralized captured/current-key eviction with ID-batched lookups, and used saved-event dirty facts rather than stale `wasChanged` facts on no-op saves. The complete owner suite then passed **77 tests, 381 assertions, 22.77s**, including real committed after-commit/rollback/FK controls and recording-engine current-state cases. Scoped six-file PHPStan passed. These results preceded the additional Scout production-queue tests and do not establish the later complete owner suite.

A fresh-context Goldfish review nevertheless returned a **blocking P1**: actual `scout.queue` is a nonempty array, and installed Scout `searchable()`/`unsearchable()` enqueue secondary jobs whose later writes lack the claimed lock/eligibility recheck. Existing tests forced queue=false. The stock observer independently emits those jobs too. This is an implementation/coverage defect, not an unresolved owner policy. Design section 8.2 and the manifest were corrected and independently reviewed before mapped-handler implementation. Its first readiness review identified deleted Make IDs lost during restoration; the explicit safe-no-op/retained-Remove limitation was added, after which readiness passed.

Regression evidence:

- Queue-array expansion of explicit Action tests: **3 failed / 3 passed, 18 assertions, 8.53s**. Replacing the three worker calls with installed synchronous Scout methods passed all **6 tests, 30 assertions, 8.36s**.
- Real serialized SyncQueue late Make/Remove cases failed their recorded index-state assertions. The observer fixture first required correction to associate its after-commit callback with the named connection's real transaction and to use QueueFake's actual returned job object; its corrected failure measured engine write transaction level **0**, not a missing test callback.
- Serialized mixed/all-deleted Make and retained-ID Remove tests failed **2 cases, 7 assertions, 9.01s**, before the mapped handler.

### Mapped Scout compatibility handler and measured batch verification

The reviewed Gemini proposal added `ReconcileTourScoutJobAction`, the Bus class map and the shared batch/single-ID projection helper. Root preserved the original single-callback signature, partitioned Tour/non-Tour models rather than trusting only the first type, and retained native non-Tour handling. Stock pending/new queued commands now reconcile available IDs under sorted shared locks in batches of 500; explicit Actions perform synchronous engine operations inside that boundary. The observer and Tour dispatch-containment wrappers remain registered. Empty restored Make commands do not invent deleted IDs; retained Remove keys and explicit deletion Actions own their cleanup.

```sh
docker exec bookly-egm003-tests php vendor/bin/pest tests/Feature/Search/TourOwnerEligibilityTest.php --filter 'Scout|legacy Make|stale index' --compact --do-not-cache-result
docker exec bookly-egm003-tests php vendor/bin/pest tests/Feature/Search/TourOwnerEligibilityTest.php --filter 'serialized stock|real Scout observer|legacy Make restoration' --compact --do-not-cache-result
docker exec bookly-egm003-tests php vendor/bin/phpstan analyse app/Domains/Search/Actions/ReconcileTourScoutJobAction.php app/Domains/Search/Services/TourPublicProjectionService.php app/Domains/Search/Actions/IndexTourAction.php app/Providers/EventServiceProvider.php --no-progress --memory-limit=512M
```

The broad Scout group passed **14 tests, 68 assertions, 51.11s**, including actual serialized SyncQueue execution and observer/restoration cases. The bulk test processes real model transformations but retains only IDs in its external recording engine: **2 IDs: 10 SQL / 1 write / 25.808ms; 8 IDs: 10 SQL / 1 write / 49.669ms; 10,000 IDs: 181 SQL / 20 writes / 44,551.282ms / 81,788,928 peak bytes**. This is local batch evidence, not a production latency promise.

The first 10,000-ID harness exhausted the existing 128MiB PHP limit while retaining both producer and restored model collections plus every output document. Root released the producer collection after serialization and retained only IDs after still executing each actual `toSearchableArray()` transformation. Correctness cases continue retaining complete documents. The same group then passed without increasing memory/time limits. Initial dataset-name-specific CLI filters selected no tests; no pass was credited, and the complete Scout group supplied the recorded evidence.

PHPStan first rejected two dynamic collection macros. The calls now use their installed public trait equivalents on the first nonempty model (`syncMakeSearchable`/`syncRemoveFromSearch`), with identical immediate engine behavior and no ignore rule. The scoped four-file analysis passed; the five real queued/restoration/observer cases reran successfully (**17 assertions, 3.37s**). Independent fresh-context Goldfish passed the mapped-handler slice and reread this static correction. Existing actual Meilisearch scale tests reran successfully: **3 passed, 44 assertions, 26.73s**. Full feature static/browser/backend gates remain outstanding.

Logs: `bookly-egm003-owner-hooks-first-fix.log`, `bookly-egm003-owner-hooks-review-red.log`, `bookly-egm003-owner-version-batch-red.log`, `bookly-egm003-owner-hooks-review-fixed.log`, `bookly-egm003-scout-configured-queue-red.log`, `bookly-egm003-scout-explicit-sync-fixed.log`, `bookly-egm003-scout-legacy-queue-red.log`, `bookly-egm003-observer-sitemap-red.log`, `bookly-egm003-scout-restoration-red.log`, `bookly-egm003-scout-mapped-first-fix.log`, `bookly-egm003-scout-mapped-memory-recheck.log`, `bookly-egm003-scout-sync-api-recheck.log`, `bookly-egm003-scout-phpstan-fixed.log`, `bookly-egm003-scout-meili-scale-recheck.log`.

Operational limits remain explicit: cache failures can raise after the source database commit and prevent later enqueue; only queue-push failures are contained. Suspension can produce multiple legitimate event rotations/jobs. Eviction/queue work grows with tour count despite batched SQL. An already lost deletion dispatch cannot be reconstructed from an all-deleted legacy Make payload; operational orphan-index reconciliation may remain necessary. Public SQL delivery guards independently exclude absent/ineligible rows. No cache/queue distributed atomicity, instant index convergence, production cutover or release readiness is claimed.

### Sitemap pre-fix checkpoint

New owner/XML checks proved **4 failures / 1 passing control** (included with the corrected observer case in `bookly-egm003-observer-sitemap-red.log`: 5 failed / 1 passed overall, 29 assertions, 14.97s). Warm/cold XML retained withdrawn Tour/destination URLs and the outer response still advertised max-age=3600. Existing sitemap checks plus a new real 10,000-tour probe passed **6 tests, 22 assertions, 11.70s** before the guard: cold **25 SQL / 525.487ms**, hot **0 SQL / 3.562ms**, **33,558,528 peak bytes / 4,425,351 XML bytes**. This establishes the unguarded baseline, not visibility acceptance. Log: `bookly-egm003-sitemap-before-guard.log`. The current same-model proposal implements the reviewed structured-fragment/fresh-dependency/no-store boundary; it is still pending application and verification.

### Sitemap structured-cache implementation and scoped Goldfish review

The reviewed Gemini proposal was applied only to SitemapController, TourPublicCacheService and TourPublicEligibilityService. The same logical `bookly:sitemap:xml` key and 3,600-second TTL now store structured fragments in the generation envelope. Every delivery checks cached Tour IDs and destination dependencies in batches; XML responses use `no-store`. Eligible-owner temporarily unavailable URLs and independent blog/taxonomy URLs remain present.

Commands:

```sh
docker exec bookly-egm003-tests vendor/bin/pest tests/Feature/Search/SitemapTest.php tests/Feature/Search/TourOwnerEligibilityTest.php --filter=sitemap --do-not-cache-result
docker exec bookly-egm003-tests vendor/bin/phpstan analyse app/Domains/Search/Controllers/Public/SitemapController.php app/Domains/Search/Services/TourPublicCacheService.php app/Domains/Search/Services/TourPublicEligibilityService.php --no-progress --memory-limit=512M
```

Initial targeted run: **11 passed / 55 assertions / 12.65s**. Fresh-context scoped Goldfish found no blocking correctness issue and identified additional cache-path coverage. Added permanent tests for legacy raw XML rejection, generation rotation, same-key eviction, hot structured reuse despite an unrelated inconsistent owner, and shared-destination retention. Their first run exposed a test setup error: creating an unrelated Tour after warming legitimately rotated the generation, so the supposed hot request was cold. Moving fixture creation before warming preserved the tested invariant; production logic was unchanged. Recheck: **13 passed / 69 assertions / 5.22s**. Measured 10,000-Tour cold **36 SQL / 391.803ms**, hot **11 SQL / 159.141ms**, **46,821,376 peak bytes / 4,425,351 XML bytes**. This is bounded local evidence, not a production latency promise. Scoped PHPStan: **no errors**. PHP syntax passed for all three production files; Pint corrected two scoped style issues with backed-up, reviewed diffs. Pest still reports the pre-existing read-only vendor result-cache warning.

Logs: `bookly-egm003-sitemap-first-fix.log`, `bookly-egm003-sitemap-extra.log` (fixture failure retained), `bookly-egm003-sitemap-extra-fixed.log`, `bookly-egm003-sitemap-phpstan.log`. The full owner+sitemap regression run is separate and pending. Goldfish scope does not close EGM-003.

Public Blog related-Tour HTTP regression reproduced the remaining leak in all three locales: **3 failed / 12 assertions / 3.64s**, log `bookly-egm003-blog-owner-red.log`. Before withdrawal, both eligible-owner unavailable and subsequently withdrawn cards were present. After a raw owner withdrawal bypassing hooks, the response incorrectly retained both. A bounded same-model proposal is running to reuse coherent published+eligible projection without applying the bookable-card filter. Core revision moderation, translation and final browser/static gates remain outstanding.

Sitemap follow-up regression run passed **101 tests / 509 assertions / 70.96s**, including owner withdrawal, actual Scout serialized-queue compatibility and the 10,000-ID batch plus structured sitemap checks. Command: `docker exec bookly-egm003-tests vendor/bin/pest tests/Feature/Search/TourOwnerEligibilityTest.php tests/Feature/Search/SitemapTest.php --do-not-cache-result`. Log: `bookly-egm003-owner-sitemap-combined.log`. Combined-process peak memory reached 113,930,240 bytes during sitemap, below the existing 128MiB limit; no limits were raised.

### Revision approval boundary and translation planning repair checkpoint

Regression-first Action/service checks reproduced **5 failures / 3 assertions / 3.70s**: valid published revision approval refused by the lifecycle matrix, unauthorized direct service approval accepted, and obsolete approval reconciliation left proposed state unchanged. Root made the Action delegate to the service's outcome transaction, added fresh passed-actor authorization within the service owner-first lock, and required the approved+active owner predicate. The same five checks passed **21 assertions / 3.70s**. Logs: `bookly-egm003-approval-boundary-red.log`, `bookly-egm003-approval-boundary-fixed.log`.

A further regression proved missing derivative desired-state planning after approval (**1 failed / 3 passed / 11 assertions / 2.99s**). Approval now compares canonical snapshot hashes and reuses `planSourceDispatches` and `dispatchPlannedTranslations`; the shared planner owns the single advanced epoch, while presentation-only promotion preserves it. The first integrated run passed 80 checks but the planning assertion saw `failed` rather than `pending`: the test runner's sync queue executed the external Gemini boundary without provider configuration. The promotion suite now fakes only GenerateTourTranslationJob delivery, asserting both actual planned rows and exact dispatched tour/locale/hash/epoch identities; provider/job correctness remains a separate required gate. This is not a production fallback or a mock of revision promotion.

The corrected focused suite passed **17 tests / 158 assertions / 5.40s** (`bookly-egm003-promotion-plans-fixed.log`). Additional real PostgreSQL audit-constraint rollback and replay cases are added and their integrated rerun is pending. A fresh-context scoped Goldfish review is running. Scoped static analysis of the Action plus full unfinished service reports **15 errors** (existing nullsafe/dead-branch/type issues); no clean full static gate is claimed. Logs: `bookly-egm003-promotion-translation-red.log`, `bookly-egm003-promotion-publication-integrated.log`, `bookly-egm003-approval-phpstan.log`. Request/correction/rejection validation, payload normalization, translation worker/recovery, admin review UI and end-to-end acceptance remain unfinished; EGM-003 stays OPEN.

### Approval owner race, nested SQL rollback and public blog follow-up

The fresh approval reviewer found a missing owner comparison after waiting for the Tour lock. A real PostgreSQL worker regression reproduced it: **1 failed / 6 passed / 40 assertions / 10.01s**, with actual `pg_blocking_pids` evidence followed by an incorrect `allowed` result after ownership transfer. Moderation now reuses the existing publication guard's owner-first lock and post-lock ownership comparison; it returns 409 on a transfer rather than acquiring a late owner lock. The first dataset-name CLI filter selected no tests and earned no pass; the complete concurrency file supplied the evidence.

The revision-specific audit fault test also exposed a real nested-transaction defect: `runTransaction` called the callback directly whenever an outer transaction existed, leaving PostgreSQL aborted after a caught SQL failure. It now always owns a transaction or nested savepoint. Refusal-as-data still exits the service transaction before throwing, so standalone supersession persists; an external outer transaction retains its ordinary final-commit authority. The installed Laravel transaction manager discards nested-rollback callbacks. After both repairs, the combined Admin governance + concurrency + owned Partner governance run passed **77 tests / 496 assertions / 26.46s**. Command: `docker exec bookly-egm003-tests vendor/bin/pest tests/Feature/Admin/TourRevisionGovernanceTest.php tests/Feature/Admin/TourRevisionConcurrencyTest.php tests/Feature/Partner/TourRevisionGovernanceTest.php --do-not-cache-result`. Logs: `bookly-egm003-approval-owner-race-red.log`, `bookly-egm003-promotion-publication-recheck.log` (nested failure retained), `bookly-egm003-approval-owned-integration.log`. Fresh-context reviewer reread both fixes and reported no remaining scoped blocker; no reviewer Pest run or full-feature acceptance was claimed.

The Blog proposal was reviewed and narrowed: the unnecessary method alias and optional container fallback were discarded, and no existing manual constructor callers were found. Its malformed diff context was corrected during surgical application. Required projection injection, one private predicate loop and a named published+eligible projection preserve existing bookable-card callers. Blog related cards now transform under the same sorted shared-lock/fresh-relation boundary. EN/ES/IT withdrawal plus existing Blog/projection tests passed **24 tests / 89 assertions / 5.91s**; editorial/proposed-content/query-growth checks expanded that to **26 / 99 / 7.97s**. Scoped PHPStan passed with no errors. Fresh-context Goldfish found no blocking scoped issue and requested a >6-candidate backfill test; the added two-unapproved-plus-six-eligible test passed with the full Blog file (**16 / 71 / 4.71s**). Existing independent Blog language policy and eligible-owner unavailable visibility remain unchanged. Logs: `bookly-egm003-blog-projection-fixed.log`, `bookly-egm003-blog-projection-expanded.log`, `bookly-egm003-blog-review-followup.log`, `bookly-egm003-blog-phpstan.log`.

New direct-service authorization tests for requests, corrections and rejection reproduce **3 failures / 3 assertions / 2.66s**. The correction case currently reaches candidate conflict before checking its dedicated permission; the other two accept unauthorized operations. Log: `bookly-egm003-moderation-boundary-red.log`. A bounded same-model proposal is running. It must follow the design's initial-review support for draft/pending/rejected never-public tours; an abbreviated brief saying draft/pending only is not authoritative and will be corrected during review. Original static issues, translation fixture epoch adaptation/recovery, moderation UI and complete browser gates remain open.

### Translation worker identity, failure projection and fixture repair

The four-file translation baseline exposed **21 failures / 33 passes / 297 assertions / 263.43s**, including four 60-second provider-arrival timeouts: the old test worker created three-argument jobs, which correctly became legacy no-epoch no-ops under v3. Captured fixture identities now include the epoch next to the hash before any edit; child workers receive that captured value explicitly. Old-hash jobs never borrow a newer epoch. No timeout was raised, provider barrier skipped, or assertion removed.

Three new worker checks independently failed (**3 assertions / 4.19s**): stale Tour epoch still reached the provider, and permanent/terminal failures changed public readiness without advancing content_version. The worker now checks Tour epoch before provider work and shares guarded failure handling using model updates plus same-transaction content_version advancement. Genuine ready content survives late failure; repeating the same failed category does not advance again. The same worker-state scope, expanded with A→B→A and null-epoch checks, passed **19 / 50 / 6.95s**. A fresh-context Goldfish found no blocking narrow regression and requested actual missing-property deserialization; both constructor-null and serialized-absent epoch cases then passed **2 / 7 / 2.52s**. Scoped worker PHPStan passed with no errors.

Fixture adaptation preserved original translation/fallback/index assertions. Positive public fixture owners are now explicitly approved rather than legacy complete. Two old tests that edited live source through general PUT now first assert the documented atomic 422 content_requires_revision, then exercise actual candidate creation, submission and authorized ordinary approval before checking stale derivatives and fallback. An initial test-helper write through Pest's overloaded property failed; explicit finally cleanup of the locally created moderator fixed it. A first incorrect 409 expectation was corrected against the existing contract's explicit 422, not by changing production policy. Complete four-file recheck passed **59 tests / 459 assertions / 23.16s**; real process arrival/release and first-wins failure races remain exercised.

```sh
docker exec bookly-egm003-tests vendor/bin/pest tests/Feature/Partner/TourTranslationTest.php tests/Feature/Partner/TourTranslationJobStateTest.php tests/Feature/Partner/TourTranslationConcurrencyTest.php tests/Feature/Partner/TourContentRevisionTest.php --do-not-cache-result
docker exec bookly-egm003-tests vendor/bin/phpstan analyse app/Domains/Partner/Jobs/GenerateTourTranslationJob.php --no-progress --memory-limit=512M
```

Logs: `bookly-egm003-translation-epoch-baseline.log`, `bookly-egm003-worker-visibility-red.log`, `bookly-egm003-worker-epoch-first-fix.log`, `bookly-egm003-worker-epoch-reviewed-path.log`, `bookly-egm003-translation-reviewed-edit.log`, `bookly-egm003-translation-reviewed-edit-fixed.log`, `bookly-egm003-worker-aba.log`, `bookly-egm003-translation-epoch-recheck.log`, `bookly-egm003-legacy-payload.log`, `bookly-egm003-worker-phpstan.log`.

### Moderation authorization and initial-review lifecycle

The reviewed moderation proposal adds fresh service-level permission checks, exact proposed/current-base rejection, pre-update rejection audit state and thin rejection Action delegation. Root discarded its unneeded optional dependency and reason echoed in identity metadata. The first focused Admin run passed **23 / 185 / 7.51s**. The independently reviewed design explicitly supports initial-review requests for draft/pending/rejected never-public tours; its binding text takes precedence over the abbreviated proposal brief. New tests showed pending requests did not reject, formerly published drafts were misclassified initial, and answered initial requests remained unanswered by first approval (**3 genuine failures / 2 controls / 14 assertions / 3.13s**, after correcting the test's direct-service input key). Root reuses RejectTourAction for pending rejection with mandatory reason, restricts initial context to never-public state, and closes open/answered initial requests with reviewer attribution, decision reason and transactional audit during publication. The five initial checks passed **19 assertions / 4.05s**.

Fresh Goldfish then found two concrete defects: moderation authorized before its lock wait, and correction-context response incorrectly took the initial epoch branch. Real PostgreSQL permission-revocation workers reproduced **3 failed / 1 approval control / 22 assertions / 9.69s**. After post-lock fresh authorization, the complete concurrency file passed **11 tests / 71 assertions / 13.82s**. An epoch-only correction answer was independently proved accepted; all non-initial contexts now require the exact submitted candidate and captured base. Combined correction/initial checks passed **6 / 23 / 3.86s**. The reviewer confirmed both fixes and clarified that design/ADR do not reserve creation of correction-context requests solely to the exceptional Action: the candidate-based response matches the existing frontend and published-context contract without granting actual correction permission.

Service PHPStan initially retained 18 new-service nullsafe/dead-code errors in the wider 25-error service/payload check. Removing redundant nullsafe-plus-coalesce usage (preserving its fallback semantics), unreachable alias handling, a typed-int fallback and the obsolete replaced lock helper brought the full service's scoped PHPStan to **no errors**. Payload static errors and other full-feature gates remain open.

Logs: `bookly-egm003-moderation-first-fix.log`, `bookly-egm003-initial-review-red.log`, `bookly-egm003-initial-review-red-corrected.log`, `bookly-egm003-initial-review-fixed.log`, `bookly-egm003-moderation-permission-race-red.log`, `bookly-egm003-moderation-permission-race-fixed.log`, `bookly-egm003-correction-response-red.log`, `bookly-egm003-correction-response-fixed.log`, `bookly-egm003-governance-static-current.log`, `bookly-egm003-service-static-fixed.log`.

A nullable-source approval regression also proved that materialization compared canonical fields against Tour rather than the stored source translation, preventing an approved null clear. Comparing the actual immutable source-locale row fixed it; nullable-clear and presentation-only no-source-write checks passed **2 / 5 / 4.09s** (`bookly-egm003-source-materialize-red.log`, `bookly-egm003-source-materialize-fixed.log`).

Field-reference strictness is still RED: **9 failures / 9 assertions / 2.86s**, including missing ValidationException import at 31 references, discarded wire keys/entries, broad tier roots, invented null descendants and unsupported media url/is_cover references. A bounded same-model proposal is running; these failures are not waived. Search's older translation fixtures and recording-engine recovery are being retested separately. EGM-003 remains OPEN, with original payload/DTO, admin review UI, recovery/cutover and full browser gates outstanding.


### 2026-11-15 — owned DTO, payload bounds, moderation UI and translation recovery

This is additive evidence; preceding failed checkpoints remain historical. Field references now reject unknown keys, non-list input, broad roots, invented descendants and unsupported media URL/cover paths. The corrected combined Admin/Partner run passed 89 tests / 500 assertions / 19.55s, followed by 11 strict-list checks / 11 assertions / 2.88s. Fresh-context scoped review passed after its direct resolver finding was reproduced and fixed.

Payload changes preserve omitted legacy optional values, validate changed nested fields, correlate media alt text by URL, choose one media cover, synchronize changed cover into the immutable snapshot and refuse option membership/order/commercial input. Six further regressions reproduced overvalidation of omitted legacy fields, option drift at submit/approval and baseline-cover rewriting. Submission/approval now validate required source title/description and current active option identity/order while preserving inherited optional legacy content; provider input validation remains in the translation worker. No derivative-readiness publication requirement was added.

The owned revision boundary now scopes current approved pointers, base pointers and mutation locks to the same tour, rejects unknown route revisions with 404, and returns bounded linked request metadata plus response-generation observations. Six actual HTTP regressions failed before the fixes. A follow-up Goldfish found read exceptions still mapped to generic 500 on revision detail/history/request history; three real HTTP regressions reproduced this, then passed 9 assertions / 2.78s after thin-controller domain-refusal rendering. These guards do not repair foreign pointers or expose foreign payloads.

Five focused regressions exposed missing public content-version updates during force/recovery, zero-epoch reconciliation, a standalone duration-label operational bypass and media alt-text round trips. Canonical epoch advancement now versions its observable readiness transition; recovery versions actual state repairs once, orphan retries remain write-free, and genuine current ready rows remain untouched. Approval advances one content version even when canonical planning also advances it. Operational saves compare standalone duration labels and pass media through the common URL-correlating merger.

The final bounded Gemini response proposal was reviewed against actual method signatures. Root rejected the preceding proposal's invented schema/signatures and initial-review candidate requirement. The corrected response proposal keeps initial responses revision-free, maps stale observed epochs to 409 REQUEST_NOT_OPEN, checks the current approved base for published/correction responses and commits supersession before returning the refusal. Root retained decision attribution rather than falsely recording a partner response for supersession. Unknown requests return 404. These are contract repairs under existing policy, not new owner decisions.

Combined revision/source/translation validation passed **188 tests / 1065 assertions / 40.92s**, including real PostgreSQL translation workers, same-hash races, readiness reconciliation and nullable source clears:

```sh
docker exec -w /var/www/html bookly-egm003-tests php vendor/bin/pest tests/Feature/Partner/TourRevisionGovernanceTest.php tests/Feature/Partner/TourTranslationTest.php tests/Feature/Partner/TourTranslationJobStateTest.php tests/Feature/Partner/TourTranslationConcurrencyTest.php tests/Feature/Partner/TourContentRevisionTest.php tests/Feature/Admin/TourRevisionGovernanceTest.php --do-not-cache-result
```

Filament comparison modals capture the exact proposed revision ID when opened and continue comparing that captured payload after replacement. The escaped comparison includes nested source, presentation, option and media leaves. Domain failures map to the actual mounted form paths and appear in visible feedback; authorization failures remain 403. Unsupported Hidden.integer usage in the initial proposal was replaced with the installed component's rules API. The full Livewire file passed **19 tests / 133 assertions / 7.95s**; independent scoped Goldfish passed after invisible feedback was reproduced and fixed. Browser acceptance remains a separate gate.

Three historical option scenarios now use ordinary text revision approval, or explicit unpublish→draft structural editing→resubmission/publication. Their original fallback, prices, stable IDs and booking assertions remain. The snapshot scenario processes a deterministic external payment-success event through the real handler/listener, confirms the original booking, approves new option text and verifies the original snapshot is unchanged. Combined publication invariants, Filament governance, option and real permission-race checks passed **109 tests / 812 assertions / 55.28s**:

```sh
docker exec -w /var/www/html bookly-egm003-tests php vendor/bin/pest tests/Feature/Partner/TourOptionsTest.php tests/Feature/Admin/TourPublicationInvariantsTest.php tests/Feature/Admin/Filament/TourContentGovernanceTest.php tests/Feature/Admin/TourRevisionConcurrencyTest.php --do-not-cache-result
```

Full PHPStan passed with no errors after repairing actual by-reference sort usage and new-code dead branches; no baseline suppression was added. Frontend typecheck passed. Full ESLint exited 0 with the existing `_omitted` unused-variable warning in validators/partner.test.ts. Full Jest passed **86 suites / 980 tests / 63.282s**, retaining JSDOM navigation, React act and mocked-image attribute warnings. Later terminal-review UI edits still require final full revalidation.

```sh
docker exec -w /var/www/html bookly-egm003-tests vendor/bin/phpstan analyse --no-progress --memory-limit=512M
docker exec -w /app bookly-egm003-web npm run typecheck
docker exec -w /app bookly-egm003-web npm run lint
docker exec -w /app bookly-egm003-web npm test -- --runInBand
```

### Disposable migration and adoption rehearsal

The dedicated empty database `bookly_egm003_roundtrip_20261115` on the test PostgreSQL container was verified absent before creation. A guarded PHP harness checked APP_ENV=testing, exact database and user before running all migrations, calling the governance migration object's down/up **before adoption**, and running bounded adoption twice. **13 checks passed**, including exact legacy attributes/source preservation, unusual itinerary/non-URL cover preservation, null/zero additive defaults, one owned approved baseline and identical pointer/count on rerun. This was a direct schema round trip, not an Artisan migration-ledger rollback after adoption. No production migration, post-adoption down, cutover, worker drain or live Gemini smoke was executed.

```sh
docker exec -e APP_ENV=testing -e APP_DEBUG=false -e APP_CONFIG_CACHE=/tmp/egm003-roundtrip-config.php -e DB_CONNECTION=pgsql -e DB_HOST=bookly-test-postgres -e DB_PORT=5432 -e DB_DATABASE=bookly_egm003_roundtrip_20261115 -e DB_USERNAME=bookly_test -e DB_PASSWORD= -e DB_URL= -e CACHE_STORE=array -e QUEUE_CONNECTION=database -e SCOUT_DRIVER=collection bookly-egm003-tests php /tmp/bookly-egm003-migration-rehearsal.php
```

Browser fixture `bookly_accept` was backed up before governance migration (SHA256 `DE773DF85329E1A3175088C7D74134C560C6040E542BFFC08988C2257C433A1F`). A separate harness verified testing/database/user identity, then migrated only that disposable database successfully. The existing seeded partner had legacy `complete` status; only that named disposable fixture was explicitly configured approved. Production legacy eligibility rules remain unchanged. The option-less seeded tour was prepared through the actual unpublish/draft-update/submit/approve domain workflow.

### Browser acceptance checkpoint — still OPEN

The copied browser stack uses the real nginx route, Laravel/Filament sessions, partner bearer session and production Next.js build. Build commands initially lacked API URL environment variables, then an incorrectly suffixed local API URL duplicated `/api`; both infrastructure failures are retained. The successful build uses `API_INTERNAL_URL=http://nginx NEXT_PUBLIC_API_URL=http://nginx`, compiled all routes and generated 89 static pages. PHP-FPM had `opcache.validate_timestamps=Off`, so copying files did not activate changed classes; restarting only the owned fixture FPM and checking HTTP readiness resolved the stale-code error. No production configuration was changed.

Playwright helpers were aligned with real existing table `Publish` and edit-page revision actions, actual confirmation windows and mandatory labels. Filament's outer role=dialog element has zero height while `.fi-modal-window` is visible; a browser measurement proved this before correcting the locator. Mixed-save focus is asserted on the actual focused status region. Axe reproduced insufficient contrast on the new approved-summary text and option controls; existing darker design tokens/classes fixed the measured nodes without disabling rules.

The sixth six-journey run passed **5 tests** and failed the rejection-refresh display: the server's active candidate correctly becomes null, but the UI omitted the latest terminal outcome. Three Jest regressions for rejected/withdrawn/superseded state failed before adding the localized outcome; the scoped panel then passed **15 tests**. The actual owned HTTP reload regression failed, then two focused checks passed **15 assertions / 2.36s** after adding a bounded latest_revision summary distinct from active_candidate. Final browser rerun is pending. A separate real option-text reload probe is being added because stored ID-keyed options were not the array expected by the frontend; no successful acceptance is claimed for that gap.

The first broad domain run exhausted its 128 MiB runner during existing Dompdf work, before a complete result. It also discovered two archived diagnostic probe files still present in the copied container; root moved both out of test discovery into temporary recovery locations, preserving their contents and all permanent regression tests. The broad gate is rerunning with an explicit 512 MiB **test-process** limit. This changes no application memory configuration and does not replace the earlier individual 128 MiB scale evidence. Any broad failures remain unresolved until individually inspected.

Evidence logs are under the orchestrator's temporary artifact directory: `bookly-egm003-fieldrefs-corrected-fix.log`, `bookly-egm003-fieldrefs-list-fixed.log`, `bookly-egm003-payload-normalization-first-fix.log`, `bookly-egm003-revision-options-first-fix.log`, `bookly-egm003-filament-feedback-fixed.log`, `bookly-egm003-owned-dto-red.log`, `bookly-egm003-recovery-operational-red.log`, `bookly-egm003-legacy-structure-red.log`, `bookly-egm003-revision-translation-contracts.log`, `bookly-egm003-publication-ui-booking-races.log`, `bookly-egm003-read-refusal-red.log`, `bookly-egm003-read-refusal-fixed.log`, `bookly-egm003-migration-rehearsal.log`, `bookly-egm003-phpstan-current-3.log`, `bookly-egm003-jest-current.log`, `bookly-egm003-governance-browser-first.log` through `bookly-egm003-governance-browser-sixth.log`, `bookly-egm003-terminal-review-red.log`, `bookly-egm003-terminal-review-ui-fixed.log`, `bookly-egm003-terminal-review-api-red.log`, `bookly-egm003-terminal-review-api-fixed.log`, `bookly-egm003-broad-domain-gate.log` and `bookly-egm003-broad-domain-gate-512.log`. Overlapping test runs are not summed. EGM-003 remains OPEN.

### 2026-11-17 — option wire-format reload, browser E2E, broad backend gate, and static/style completion

This additive section records completion of the remaining local implementation and verification gates after the preceding 2026-11-15 checkpoint. Historical failed checkpoints above remain unchanged.

1. **Candidate option-text reload and wire serialization**:
   - Regression-first Jest run (`bookly-egm003-option-map-red.log`, exit 1) reproduced missing option-text restoration when `payload.options` was structured as an ID-keyed object rather than an array.
   - `frontend/src/lib/api/types.ts` (`RevisionSnapshotOptionsWire`) and `frontend/src/app/[locale]/(partner)/partner/tours/[id]/edit/page.tsx` (`parseRevisionOptions`, called from `revisionRestore`) now accept both ordered array and ID-keyed map representations while preserving live option display order. Focused Jest passed **3 suites / 58 tests** in `11.834s` (`bookly-egm003-option-map-green.log`).
   - Backend `TourRevisionService::revisionPayload()` (`backend/app/Domains/Partner/Services/TourRevisionService.php`) now projects `payload['options']` on the wire as an ordered list of `{ id, title, pickup_instructions }` while keeping `$revision->payload` stored intact as an ID-keyed map in PostgreSQL, resolving the wire-serialization regression in `TourRevisionGovernanceTest.php:1010-1021` observed during the broad gate (`bookly-egm003-two-fixes-recheck.log`: **94 passed, 440 assertions, 20.24s** together with `AutoTranslationEngineTest.php`).

2. **Tour after-commit containment and legacy field-engine retirement tests**:
   - `backend/tests/Feature/Partner/TourAfterCommitContainmentTest.php` updated its fixture partner `onboarding_status` to `'approved'` to match the EGM-003 `shouldBeSearchable()` owner-eligibility requirement (previously 3 failures out of 1,379 in `bookly-egm003-broad-domain-gate-512.log`), then passed **12 tests / 101 assertions** in `6.17s` (`bookly-egm003-after-commit-containment-fixed.log`).
   - `backend/tests/Feature/Translation/AutoTranslationEngineTest.php` test #7 was updated per S4 (`translation-generation.md` §"Retirement of the legacy field engine") to verify `Tour::supportsFieldTranslation() === false`, zero dispatches/writes from `AutoTranslationService::syncAndDispatch($tour)`, and safe refusal (`TOUR_TRANSLATION_MANAGED` / `TOUR_SOURCE_NOT_ENGLISH`) from `TranslateContentFieldJob` without touching `TourTranslation`, while keeping all 6 CMS/blog field-engine tests unchanged.

3. **Full frontend and Playwright E2E acceptance (against restored `bookly_accept` + production Next.js build)**:
   - Full frontend Jest: **86 suites / 985 tests passed** in `140.742s`, exit 0 (`bookly-egm003-jest-final.log`; existing JSDOM navigation, React act, and mocked-image `priority` console warnings remain visible).
   - `npm run typecheck`: exit 0 (`bookly-egm003-typecheck-final.log`). `npm run lint`: exit 0 (0 errors; 1 pre-existing unused-variable warning on `_omitted` in `src/lib/validators/__tests__/partner.test.ts:453`, `bookly-egm003-lint-final.log`).
   - Production Next.js build (`API_INTERNAL_URL=http://nginx NEXT_PUBLIC_API_URL=http://nginx ALLOW_PRIVATE_IMAGE_UPSTREAM=true NEXT_TELEMETRY_DISABLED=1`): exit 0, 89 static/dynamic routes compiled (`bookly-egm003-final-build.log`).
   - Real Playwright suites against isolated `bookly-egm003-app/postgres/web/nginx` (with `bookly_accept` cleanly restored from the backed-up SQL dump + governance migration + deterministic fixture setup):
     - `partner.setup.ts` (`--grep "authenticate as partner"`): **1 passed** in `15.9s`, exit 0 (`bookly-egm003-playwright-setup.log`).
     - `tests/e2e/a11y/partner-a11y.spec.ts` (`--project=a11y-partner --no-deps`): **21 passed** in `1.5m`, exit 0 (`bookly-egm003-playwright-a11y.log`).
     - `tests/e2e/partner/tour-create.spec.ts` (`--project=chromium-partner --no-deps --grep "Supplier A2 source selection"`): **3 passed** in `9.2s`, exit 0 (`bookly-egm003-playwright-a2.log`).
     - `tests/e2e/filament-admin.spec.ts` (`--project=chromium --no-deps`): **7 passed** in `36.1s`, exit 0 (`bookly-egm003-playwright-admin.log`).
     - `tests/e2e/partner/tour-revision.spec.ts` (`--project=chromium-partner --no-deps`, rerun after final Step 1 & 2 code edits): **6 passed** in `1.8m`, exit 0 (`bookly-egm003-playwright-revisions.log`).

4. **Final post-edit backend, static, style, and migration round-trip gates**:
   - Commands run inside `bookly-egm003-tests` (and disposable `bookly_egm003_roundtrip_20261115` on `bookly-test-postgres`):

```sh
docker exec -w /var/www/html bookly-egm003-tests php vendor/bin/phpstan analyse --no-progress --memory-limit=1G
docker exec -w /var/www/html bookly-egm003-tests php vendor/bin/pint --test
docker exec -w /var/www/html bookly-egm003-tests php -d memory_limit=1G vendor/bin/pest tests/Feature/Admin tests/Feature/Partner tests/Feature/Search tests/Feature/Booking tests/Feature/Translation tests/Feature/Blog --compact --do-not-cache-result
docker exec -w /var/www/html bookly-egm003-tests php vendor/bin/pest tests/Feature/Reviews --compact --do-not-cache-result
docker exec -w /var/www/html bookly-egm003-tests php -d memory_limit=1G vendor/bin/pest tests/Feature/Payment tests/Feature/Security tests/Unit --compact --do-not-cache-result
```

| Check | Actual post-final-edit result | Local Temp log |
|---|---|---|
| `PHPStan` | `[OK] No errors` across 388 files, exit 0 | `bookly-egm003-phpstan-final.log` |
| `Pint --test` | `PASS 777 files` (exit 0) | `bookly-egm003-pint-final.log` |
| Broad Backend Feature Gate (`Admin`, `Partner`, `Search`, `Booking`, `Translation`, `Blog`, 1G process limit) | **1,284 passed (7,434 assertions)** in `947.97s`, exit 0 (note: read-only vendor result-cache warning retained; includes `10,000`-tour Sitemap cold `36 SQL / 930.29ms` & hot `11 SQL / 271.80ms` and Scout `10,000`-ID batch `181 SQL / 20 writes / 118.24s` against `bookly-test-meilisearch` using `Egm003OwnerRecordingEngine` in tests) | `bookly-egm003-broad-domain-gate-final.log` |
| `tests/Feature/Reviews` | **54 passed (161 assertions)** in `20.45s`, exit 0 | `bookly-egm003-reviews-final.log` |
| `Payment`, `Security`, `Unit` suites | **141 passed (621 assertions)** in `28.05s`, exit 0 | `bookly-egm003-extra-suites-final.log` |
| Disposable migration round-trip (`bookly_egm003_roundtrip_20261115`, `up/down/up` + double adoption) | **13/13 PASS**, exit 0 | `bookly-egm003-migration-rehearsal-final.log` |

5. **Round 1 Goldfish Review Findings & Remediations Applied**:
   - The initial independent Goldfish review round raised 7 specific findings across governance contract compliance and code edge cases:
     1. **`change_summary` contract support**: Migration `2026_11_14_000001_create_tour_content_governance.php` now includes nullable string column `change_summary` (500 chars max). `TourContentRevision` model designates it in `$fillable` and `$casts`. `StoreTourRevisionRequest` exposes `change_summary` validation (`sometimes|nullable|string|max:500`) and accessor methods. `TourRevisionService` stores and returns `change_summary` in all candidate creation/replacement operations and summaries. Filament `revision-comparison.blade.php` displays change summary when present. Frontend `TourRevisionPanel.tsx` and `page.tsx` display inline 422 errors and manage dirty tracking.
     2. **`media: null` validation rule**: `StoreTourRevisionRequest::mediaRules()` was relocated so that submitting `media: null` (meaning no changes to media) passes validation cleanly without being blocked by array element rules.
     3. **Slug trimming normalization**: `TourRevisionPayloadService::normalizePresentationPatch()` now trims and validates slugs cleanly so that whitespace-padded slugs are normalized and preserved in `$normalized['slug']`.
     4. **Administrative correction canonical source fields**: `ProposeTourCorrectionAction` and `EditTour.php` now support all 9 canonical source fields declared by the contract, including lists and structured itineraries.
     5. **Post-approval cache and search invalidation**: `TourRevisionService::approveRevision()` explicitly calls `TourPublicCacheService::invalidateOwnerTargetsAfterCommit()` after updating `tours`, ensuring Meilisearch index synchronization and Redis/catalog cache eviction.
     6. **Request closure on approval**: `TourRevisionService::closeRequestsOnApproval()` now marks open correction requests as approved (`TourRevisionRequest::STATE_APPROVED`).
     7. **Standardized exception payload**: `TourRevisionException::contractPayload()` provides top-level `errors` and `revision_id` fields for consistent client error handling across all domain refusals.

6. **Round 2 Independent Fresh-Context Goldfish Review Outcome**:
   - Three independent fresh-context Goldfish reviewers audited the completed codebase, test logs, and documentation:
     - **`EGM Codebase Details Goldfish`**: **Verdict: PASS**. Verified all 8 implementation areas against the contracts (`change_summary` lifecycle, request validation, exception structure, slug trimming, revision service methods, after-commit invalidation, Filament 9 fields, and frontend dirty/inline error tracking).
     - **`EGM Implementation Readiness Goldfish`**: **Verdict: PASS**. Verified all 12 acceptance boundaries against concrete test executions, confirmed zero regressions against EGM-002 and EGM-004, and verified operational limits and non-claims.
     - **`EGM Style Goldfish`**: **Verdict: PASS**. Verified empirical evidence precedes assertions, accurate line references (including line 504 pointing to `TourRevisionGovernanceTest.php:1010-1021`), test attribution to `Egm003OwnerRecordingEngine` (line 536), clean log references, 1:1 match of 131 files in changed-files inventory, and additive preservation of historical checkpoints.

Operational limits and non-claims remain explicit: all provider/Gemini translation runs in automated tests use deterministic fakes (`Queue::fake()` / `Http::fake()` / fake translators) rather than live external Gemini credentials; Search pagination totals remain eventually consistent with Scout; no CI pipeline, branch merge, production database migration, or live production cutover was executed. Local implementation and gate verification for EGM-003 are complete.
