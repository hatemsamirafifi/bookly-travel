# Verification Evidence: Spec 019 Tour Content Data Model and API

**Owner**: Spec 019 implementation workflow; independent acceptance by root reviewer.
**Created**: 2026-10-02.
**Current scope (2026-10-03)**: T001-T009 setup/foundations independently
reviewed; section 15 records current accepted evidence. Earlier sections
retain their dated findings. US1-US6, final gates, Spec 019 CI/merge and
release acceptance remain pending.

**Traceability**:

- Spec: [spec.md](spec.md) (Spec `019`; UI master-plan Phase 2, Tasks 2.1-2.4)
- Plan: [plan.md](plan.md)
- Tasks: [tasks.md](tasks.md) (T001-T078; current accepted markers match dated evidence)
- Design: [research.md](research.md), [data-model.md](data-model.md),
  [quickstart.md](quickstart.md)
- Contracts: [public detail](contracts/tour-detail-api.md),
  [partner content](contracts/partner-tour-content-api.md),
  [generation/recovery](contracts/translation-generation.md)
- Quality gate: [requirements.md](checklists/requirements.md) (16/16 checked,
  0 unchecked; document readiness, not implementation acceptance)
- Governance: [Constitution v2.1.0](../../.specify/memory/constitution.md)
- Predecessor baseline: [Spec 018 spec](../018-bookly-ui-redesign/spec.md),
  [Spec 018 plan](../018-bookly-ui-redesign/plan.md)
- Program mapping:
  [master plan](../../docs/bookly-ui-redesign-spec-kit-master-plan.md),
  [PRD](../../docs/PRD.md),
  [Phase 2 plan](../../docs/Phase%202%20Implementation%20Plan.md)

## 1. Branch, HEAD, and Active Pointer (checked 2026-10-02)

| Fact | Observed value | Expected | Verdict |
|---|---|---|---|
| Current branch (`git branch --show-current`) | `codex/019-tour-content-data` | `codex/019-tour-content-data` | MATCH |
| HEAD (`git rev-parse HEAD`) | `84ae4e59b49a1603d59a624ff9706f17ff02338f` | `84ae4e5` (prefix of same commit) | MATCH |
| HEAD commit message/date | `docs: mark master-plan Phase 1 implementation complete` / 2026-10-01 | Inherited baseline commit | MATCH |
| Ancestry (`git merge-base --is-ancestor 84ae4e5 HEAD`, exit `0`) | `84ae4e5` is an ancestor of HEAD (HEAD equals the baseline commit) | Baseline retained | MATCH |
| Active pointer (`.specify/feature.json`) | `{"feature_directory": "specs/019-tour-content-data"}` | Spec 019 | MATCH |
| Spec 019 directory status | Untracked (`?? specs/019-tour-content-data/`), 10 files: `spec.md`, `plan.md`, `tasks.md`, `research.md`, `data-model.md`, `quickstart.md`, `contracts/` (3 files), `checklists/requirements.md` | Pre-existing authorized work, preserved | MATCH |
| Prerequisite script (`.specify/scripts/powershell/check-prerequisites.ps1 -Json -RequireTasks -IncludeTasks`) | `FEATURE_DIR: .../specs/019-tour-content-data`; `AVAILABLE_DOCS: research.md, data-model.md, contracts/, quickstart.md, tasks.md` | Complete Spec Kit artifacts present | MATCH |
| Task inventory (`tasks.md`) | 78 checkboxes `T001`-`T078`, all `- [ ]` (pending); 0 checked | 78 pending per task-generation record | MATCH |
| Requirements gate (`checklists/requirements.md`) | 16 `[x]` checked, 0 `[ ]` unchecked | 16/16 read-only quality gate | MATCH |

The extra `- [ ]` literal match in `tasks.md` is the format-description line
("Each task uses `- [ ] Tnnn ...`"), not a 79th task.

## 2. Spec 018 Preservation (checked 2026-10-02)

`git diff --stat -- specs/018-bookly-ui-redesign` (working tree, uncommitted):

```text
specs/018-bookly-ui-redesign/plan.md | 2 ++
specs/018-bookly-ui-redesign/spec.md | 4 +++-
```

Full diff consists of two additive annotation insertions and no deletions of
substantive content:

1. `specs/018-bookly-ui-redesign/plan.md`: +2 lines, a "**Continuation
   governance**" paragraph stating the recorded checks remain historical
   v2.0.0 design evidence, Spec 018 retains the original program plan and
   completed Phase 1 foundations, and active Phase 2 planning belongs to
   Spec 019 with its dependent branch/PR facts.
2. `specs/018-bookly-ui-redesign/spec.md`: governance line extended (+3/-1):
   original baseline pinned to Constitution v2.0.0 with active continuation
   under v2.1.0; plus a "**Spec ID / phase ownership**" paragraph keeping
   `018` as bootstrap/Phase 1 foundations and pointing Phase 2 requirements
   and revised contracts at Spec 019.

No Spec 018 file was deleted, renumbered, or rewritten; research, contracts,
data model, quickstart, tasks, checklists, and `phase-1-verification.md`
under `specs/018-bookly-ui-redesign/` show no diff. Verdict: **Spec 018
preserved** (annotation-only change, consistent with Constitution v2.1.0
items 6-7). These modifications are pre-existing authorized work and were
not made or altered by T001.

## 3. Predecessor / Prerequisite PR Facts (checked 2026-10-02 via `gh`)

Read-only lookup only: `gh pr view 27` / `gh pr view 28` with structured
JSON fields (`state`, `isDraft`, `baseRefName`, `headRefName`, `mergedAt`,
`url`). No comments, messages, merges, or other writes were issued.

| PR | Title | State | isDraft | base | head | mergedAt (UTC) | URL |
|---|---|---|---|---|---|---|---|
| #27 | Apply Bookly UI redesign and supporting tour workflows | **MERGED** | false | `main` | `codex/bookly-ui-redesign-all-work` | **2026-10-02T18:17:25Z** | <https://github.com/hatemsamirafifi/bookly-travel/pull/27> |
| #28 | Implement Phase 1 design foundations and shared shells | **OPEN** | false | `main` | `codex/bookly-ui-phase-1` | null (not merged) | <https://github.com/hatemsamirafifi/bookly-travel/pull/28> |

**Deviating finding (T001 -> orchestrator)**: [spec.md](spec.md) and
[plan.md](plan.md) record PR #27 and Phase 1 PR #28 as "pending merge" /
"remain unmerged" as of 2026-10-01/02. As of this check, **PR #27 is
MERGED** (2026-10-02T18:17:25Z) while **PR #28 remains OPEN**. The plan's
"confirm the predecessor/base still matches before implementation" step and
the plan's compatibility/rollout item 1 ("pending all-work/Phase 1 PRs must
be resolved or carried as an explicit stacked dependency before merge")
therefore need reconciliation before T002+ implementation begins. T001 does
not rebase, merge, or reinterpret the baseline; HEAD remains `84ae4e5` and
no merge readiness is claimed from either PR's state. Local presence of code
was not treated as merge evidence, per task instructions.

## 4. Working-Tree Status (checked 2026-10-02, read-only)

`git status --short`:

```text
M .specify/feature.json
M .specify/memory/constitution.md
M .specify/templates/plan-template.md
M .specify/templates/spec-template.md
M .specify/templates/tasks-template.md
M docs/PRD.md
M "docs/Phase 2 Implementation Plan.md"
M docs/bookly-ui-redesign-spec-kit-master-plan.md
M specs/018-bookly-ui-redesign/plan.md
M specs/018-bookly-ui-redesign/spec.md
?? specs/019-tour-content-data/
```

All modifications are pre-existing authorized specification/governance work
(Constitution v2.1.0 propagation, master-plan/PRD/roadmap renumbering,
Spec 018 annotations, feature pointer, templates) plus untracked Spec 019
artifacts. T001 created only this file and did not overwrite, revert, or
discard any of them.

`git diff --check`: exit `0`, no whitespace errors (only LF/CRLF advisory
warnings from Git on already-modified files).

## 5. Separate Acceptance Statuses

Per Constitution v2.1.0 Compliance Review, these are recorded separately.
No phase is marked implemented and no gate is implied passed.

| Gate | Status | Evidence / note |
|---|---|---|
| Implementation (Spec 019 code/tests) | **NOT STARTED** | T001 is documentation-only; T002-T078 pending, 0/78 complete |
| Runtime verification (backend/frontend suites, E2E, adoption/recovery rehearsal) | **NOT RUN** | No test, build, lint, typecheck, or Lighthouse command executed for T001; none required for this documentation-only task |
| CI (remote pipelines) | **NOT RUN / UNKNOWN** | No CI triggered or inspected |
| Merge | **PENDING / BLOCKED on reconciliation** | PR #27 MERGED, PR #28 OPEN (see Section 3); predecessor/base must be reconfirmed per [plan.md](plan.md) before implementation |
| Staging / release | **NOT STARTED** | No deployment, migration, or live-provider activity; explicitly out of scope |

## 6. Commands Executed (all read-only) and Outcomes

| # | Command | Outcome |
|---|---|---|
| 1 | `git branch --show-current` | `codex/019-tour-content-data` |
| 2 | `git rev-parse HEAD` | `84ae4e59b49a1603d59a624ff9706f17ff02338f` |
| 3 | `git merge-base --is-ancestor 84ae4e5 HEAD` | exit `0` — baseline is ancestor (HEAD equals it) |
| 4 | `git log --oneline -8` | HEAD `84ae4e5`; parent chain via `8ff6ba3`, `f3e8e25`, PR #26 merge — no unexpected divergence observed |
| 5 | `git status --short` (+ `--stat`, untracked listing) | 10 modified + `?? specs/019-tour-content-data/` (10 files), as in Section 4 |
| 6 | `git diff -- specs/018-bookly-ui-redesign` | Additive 2-line plan note + 4-line spec governance/ownership note; no deletions of substance |
| 7 | `Get-Content .specify/feature.json -Raw` | `specs/019-tour-content-data` |
| 8 | `.specify/scripts/powershell/check-prerequisites.ps1 -Json -RequireTasks -IncludeTasks` | `FEATURE_DIR` resolves to Spec 019; all design docs + `tasks.md` available |
| 9 | `gh pr view 27 --json state,isDraft,baseRefName,headRefName,mergedAt,url,title` | MERGED, mergedAt `2026-10-02T18:17:25Z`, base `main`, head `codex/bookly-ui-redesign-all-work` |
| 10 | `gh pr view 28 --json state,isDraft,baseRefName,headRefName,mergedAt,url,title` | OPEN, mergedAt null, base `main`, head `codex/bookly-ui-phase-1` |
| 11 | `git diff --check` | exit `0`, clean |
| 12 | Checkbox counts via `Select-String` on `tasks.md` / `requirements.md` | tasks: 78 `T001`-`T078` pending / 0 checked; requirements: 16 checked / 0 unchecked |
| 13 | `Test-Path` on `docs/bookly-ui-redesign-spec-kit-master-plan.md`, `docs/PRD.md`, `docs/Phase 2 Implementation Plan.md`, prerequisite script | All `True`; every relative link in this file targets a verified-existing path |
| — | Runtime gates (`php artisan test`, `npm test/lint/build`, Playwright, Lighthouse) | **Explicitly not run** — documentation-only task, no code changed |

## 7. Unknowns and Follow-ups for the Orchestrator

1. **PR #27 merged after plan text**: plan/spec say both PRs unmerged; #27
   merged 2026-10-02T18:17:25Z. Whether the baseline moves off `84ae4e5`,
   restacks onto `main`, or stays with an explicit stacked dependency on
   open PR #28 is an orchestrator decision — T001 takes no position beyond
   recording the facts.
2. **PR #28 still open**: Phase 1 worktree remains separate and outside this
   task; its merge/removal effect on Spec 019's predecessor record is
   unresolved.
3. **T002-T004 next**: disposable PostgreSQL/Meilisearch isolation,
   frontend command/SSR-fixture verification, and schema/consumer inventory
   are unverified by T001 and must precede foundations (per the task graph
   T001 -> T002/T003, then T004).
4. **No external lookup failure occurred**: `gh` was authenticated and
   returned structured JSON for both PRs, so no uncertainty needs preserving
   on PR state; the only open item is the reconciliation decision above.

## 8. Independent Orchestrator Review and Baseline Refresh

T001 was independently reviewed and marked complete on 2026-10-02. Its earlier
sections preserve the pre-dispatch facts, not the current moving HEAD.

- OpenCode: `opencode/muse-spark-1.3-contributor-free`, version 1.18.34,
  session `ses_f02237e4cffexZwsVcXfhEUp7r`, completed exit 0, reported cost $0.
- Orchestrator repeated branch/pointer/ancestor/prerequisite/PR lookups,
  checked the new document links and whitespace, and confirmed only the
  assigned verification document was added by this OpenCode run.
- `git fetch origin main` refreshed origin/main to `f1a1f37`;
  `git merge-base --is-ancestor 84ae4e5 origin/main` returned 0. Main contains
  the original source baseline and subsequent CI/browser-isolation fixes.
- Verified design/governance documents plus T001 evidence were committed and
  rebased onto that main commit. Current setup commit is `ed0a9f3`;
  `git merge-base --is-ancestor f1a1f37 HEAD` returned 0. Spec 018 remains
  present. No PR was merged or closed by this operation; PR #28 remains open.
  Reconciliation is resolved: retain original ancestry, use the refreshed
  integration base, and do not duplicate Phase 1.
- Partner contract correction follows
  `backend/app/Domains/Partner/Middleware/PartnerRoleMiddleware.php`: missing
  partner capability/record returns 404; gated inactive/unapproved writes
  return 403 with ONBOARDING_STATUS_BLOCKED. Existing behavior is preserved.
- Independent inherited-backend baseline: `docker compose exec -T -e
  GEMINI_API_KEY= laravel php -d memory_limit=512M vendor/bin/pest
  --configuration=phpunit.pgsql.xml tests/Feature/Partner/TourCreateTest.php
  tests/Feature/Partner/TourDraftTest.php
  tests/Feature/Partner/TourTranslationTest.php
  tests/Feature/Search/TourDetailTest.php
  tests/Feature/Admin/TourModerationTest.php` returned exit 0: **60 passed,
  332 assertions, 114.69 seconds**. Output: [baseline-backend.txt](evidence/baseline-backend.txt).
  This proves inherited focused suites only; no new Spec 019 runtime
  behavior is accepted by these results. Bootstrap targets bookly_test
  on bookly-test-postgres; provider key was blank for this test invocation.
- Runtime implementation remains pending. T002-T078 remain unchecked;
  CI, staging, and Spec 019 PR/merge/release acceptance are pending.
  LiveReview was skipped through `lrc review --staged --skip`; global hooks
  remain enabled.

## 9. T002 Disposable Test Isolation and Fake-Provider Setup (checked 2026-10-02)

- `backend/phpunit.pgsql.xml`: forces `DB_CONNECTION=pgsql`,
  `DB_HOST=bookly-test-postgres`, `DB_DATABASE=bookly_test`,
  `DB_USERNAME=bookly_test`, `QUEUE_CONNECTION=sync`,
  `SCOUT_DRIVER=collection` (+ `SCOUT_PREFIX=bookly_test_`), `CACHE_STORE=array`,
  `SESSION_DRIVER=array`; `MEILISEARCH_HOST=http://bookly-test-meilisearch:7700`
  with `MEILISEARCH_KEY=test-key`. Comment states Scout uses the in-memory
  collection driver and sync queue so tests stay isolated with no Meilisearch
  or Redis dependency.
- `backend/tests/bootstrap.php`: sets the same disposable values via
  `putenv`/`$_ENV`/`$_SERVER` before Laravel/Dotenv loads, including
  `APP_ENV=testing`, `PAYMENT_GATEWAY=stripe`, `SCOUT_QUEUE_CONNECTION=sync`.
  Enforces the single-suite owner: when `TEST_TOKEN` is empty, it takes an
  exclusive non-blocking `flock` on
  `storage/framework/testing-database.lock` and throws
  `Another test suite owns the disposable test database.` otherwise.
  ParaTest workers carry `TEST_TOKEN` and receive suffixed databases.
- `docker-compose.test.yml`: defines only `test-postgres` (postgres:15-alpine,
  `POSTGRES_DB=bookly_test`, `POSTGRES_USER=bookly_test`, trust auth, tmpfs
  `/var/lib/postgresql/data`, no host port, no persistent volume) and
  `test-meilisearch` (v1.10, `MEILI_MASTER_KEY=test-key`, tmpfs
  `/meili_data`) on `bookly-network`.
- Live services observed running (`docker ps`): `bookly-test-postgres` and
  `bookly-test-meilisearch` Up; dev `bookly-postgres`/`bookly-meilisearch` are
  separate containers. Test bootstrap targets `bookly_test` on
  `bookly-test-postgres`, never dev/prod data.
- Single backend-suite owner: the process holding the `testing-database.lock`
  flock. Only one database suite runs at a time; controlled multi-connection
  race tests live inside that owning suite and clean their own fixtures.
- Fake-provider rule: `AppServiceProvider` binds `TourContentTranslator` to
  `GeminiTourTranslator`; `GeminiTourTranslator::translate` requires
  backend-only `GEMINI_API_KEY`/`GEMINI_TRANSLATION_MODEL` and performs live
  HTTP to `generativelanguage.googleapis.com` with a 40s timeout.
  `TourTranslationTest` fakes with anonymous `TourContentTranslator`
  implementations and `Http::fake`, asserting no credential/body leakage.
  **Live provider traffic is forbidden in automated tests**: suites run with a
  blank `GEMINI_API_KEY` (`-e GEMINI_API_KEY=`), bind fake translator/HTTP/queue
  implementations, and never call the live Gemini endpoint.

## 10. T003 Frontend Guides, Commands, SSR Fixtures and Seeded Partners (checked 2026-10-02)

- `frontend/AGENTS.md`: Next.js 16 App Router conventions; Server vs Client
  component separation with React hooks/context; locale-aware `next-intl`
  error boundaries; Tailwind constraints; full access inside `frontend/src/`;
  `npm run lint` + TypeScript strict; Sanctum-token HTTP only, no direct DB.
- Installed Next.js guides read (before code):
  `node_modules/next/dist/docs/01-app/01-getting-started/06-fetching-data.md`
  (server `fetch`/ORM fetches, client fetches, streaming),
  `14-metadata-and-og-images.md` (static `metadata`, dynamic
  `generateMetadata`, file conventions; Server Components only),
  plus `05-server-and-client-components.md` and `08-caching.md` for
  Server/Client boundaries and cache/no-store semantics used by detail SSR.
- `frontend/package.json` scripts: `lint` (`eslint`), `typecheck`
  (`tsc --noEmit`), `test` (`jest`), `test:e2e` (`playwright test`),
  `test:a11y`, `build` (`next build`), `lighthouse`/`lhci`. Deps: Next
  16.2.3, React 19.2.4, next-intl 4.9, zod 4, zustand 5, Playwright 1.60.
- `frontend/playwright.config.ts:18` (re-verified 2026-10-03, file
  unmodified): `const baseURL = process.env.PLAYWRIGHT_BASE_URL || ...`
  override IS present; defaults to `http://nginx` in-container
  (`DOCKER_ENV=true`), CI runner via published nginx port, else
  `http://localhost:8080`. Projects: `setup`
  (traveler + partner storage states), `chromium`/`mobile` base, `a11y`,
  `chromium-authed`/`mobile-authed`, `chromium-partner`/`mobile-partner`
  (depend on `setup`, reuse `tests/.auth/partner.json` seeded
  `partner@bookly.test`), `a11y-partner`. Serial workers + 20s expect/60s
  test timeouts in Docker for cold compiles.
- SSR fixture prerequisite: existing detail browser tests start a local API
  fixture + isolated built Next process; browser route mocks alone cannot
  intercept SSR server fetches. Build/SSR needs `API_INTERNAL_URL` (native:
  `http://127.0.0.1:8080`) or `NEXT_PUBLIC_API_URL`; no secret in
  `NEXT_PUBLIC_*`.
- Seeded partner projects: `tests/e2e/partner.setup.ts` +
  `tests/.auth/partner.json`; partner specs require these authed projects.
  Browser isolation (per task constraints): never copy over `backend/.env`,
  generate keys into real `.env`, seed/reset dev DB, stop user services, or
  bind port 8080. Permitted: isolated compose project (e.g.
  `bookly-spec019-browser`, `CI_HTTP_PORT=8189`) with CI tmpfs DB/separate
  volumes + non-secret test-only env overrides; configurable test base URL
  via existing `PLAYWRIGHT_BASE_URL` (default behavior intact).

## 11. T004 Schema, Authoring Consumers and Focused Baseline (checked 2026-10-02)

- `2026_09_25_000001_add_guide_languages_and_localized_itinerary.php`: adds
  `tours.guide_languages` JSONB nullable + `tour_translations.itinerary` JSONB
  nullable; backfills valid legacy `tours.itinerary` into existing EN rows
  with null itinerary only (strings -> numbered days; structured days
  preserved with T014 bounds; malformed/partially-invalid/empty -> `[]`
  skipped, never fabricated). Reused as-is; no edit to applied migration.
- `2026_09_25_000002_create_tour_translation_states_table.php`: adds
  `tour_translations.important_information` JSONB nullable + creates
  `tour_translation_states` (`tour_id` FK cascade, `locale` 2-char,
  `source_hash` char64, `translated_hash` char64 nullable,
  `status` 16-char, `last_error_code` 40-char nullable, timestamps, unique
  tour+locale). Reused as-is; nullable media alt storage belongs to US4.
- Active authoring consumers: `TourController::store/update` use **inline**
  `$request->validate()` today (store :103, update :167); `StoreTourRequest`
  / `UpdateTourRequest` exist but are unwired. `TourService::createTour`
  / `updateTour` own business writes (scoped `getForPartner` by
  partner-record ID). `TourTranslationService::queueIfChanged` schedules
  ES/IT after commit; `GenerateTourTranslationJob` locks state-only.
  Frontend: `TourWizard.tsx` writes EN/itinerary/difficulty/guide codes but
  drops inclusions/exclusions/important_information; `edit/page.tsx` omits
  difficulty/important_information; `ItineraryEditor.tsx` lacks duration
  control; `validators/partner.ts:48` validates duration but wizard treats
  nested EN errors as translation keys.
- Focused inherited baseline (NOT new acceptance):
  `TourCreateTest/TourDraftTest/TourTranslationTest/TourDetailTest/TourModerationTest`:
  60 passed, 332 assertions, 114.69s (evidence/baseline-backend.txt).
  `TourCreateTest` already covers basic create, difficulty/media order,
  structured EN itinerary persist, malformed itinerary 422, guide codes,
  safe `translation_statuses`, cross-partner scoping, 401/404. Gaps NOT
  closed by foundations and still pending US1 review: full EN field round
  trips, nested-vs-shorthand precedence, omitted/null/[] semantics, exact
  title/list/text/itinerary boundaries, IDOR with distinct user/partner
  IDs, ES/IT/state forgery, atomic content/media rejection (T010);
  opaque/legacy snapshot round trips (T011). Nothing in T002-T009 claims
  T010/T011 acceptance.
- Middleware (actual behavior, preserved):
  `PartnerRoleMiddleware`: missing partner capability/record -> 404;
  inactive/unapproved blocked writes -> 403 `ONBOARDING_STATUS_BLOCKED`;
  unauthenticated -> 401 (via auth); scoped cross-partner unknown -> 404 via
  `getForPartner`. These statuses are not changed by this slice.

## 12. Foundations T005-T009 Implementation Results (executed 2026-10-03)

Scope: setup/foundations plus reviewer-ordered regression repairs only.
US1 T010-T024 is explicitly not implemented here and remains pending
orchestrator review. No browser suite was run (deferred to US1). An
isolated `bookly-spec019-browser-*` compose project was observed running
(`docker ps` 2026-10-03) and was left untouched.

### T005 Shared revision hash and lock boundary

- New `backend/tests/Feature/Partner/TourContentRevisionTest.php` (9 tests).
  Before the fix it demonstrated: nested day/stop key reordering changed
  `sourceHash`, and `withContentLock` did not exist (`method_exists` false).
- `TourTranslationService`: added `normalizeSourceProjection` (fixed outer
  field order; day objects rebuilt day/title/description/stops, stop
  objects title/description/duration_minutes; array order preserved;
  null vs `[]` vs `''` preserved; absent stops default to `[]`) and
  `withContentLock` (Tour -> EN -> states in es/it order inside one DB
  transaction; provider/network work stays outside; fresh source rechecked
  by the caller inside the callback).
- Malformed persisted source is kept explicit (scalar day/stop carried as
  title input; non-array itinerary kept verbatim) so it never hashes as
  empty/valid; authored validation (T008) rejects before writes.
- Serialization flags deliberately unchanged
  (`JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE`) so existing persisted
  desired hashes stay comparable; only documented key-order
  canonicalization affects identity.
- Lock-order proof: query-log assertion pins exactly 4 `FOR UPDATE`
  statements in tours -> tour_translations(en) -> states(es) -> states(it)
  order with matching bindings. Transactionality and fresh-source
  visibility are asserted. This does NOT prove concurrency: real
  save/completion interleavings stay US3 acceptance (T034+).

### T006 Shared frontend DTOs (compile-safe only)

- `lib/api/types.ts`: added `EnglishSourceInput`,
  `PartnerTourWritePayload` (full supported writes: source fields,
  translations.en, nullable difficulty, guide_languages + languages alias,
  category/destination/location, numeric duration, group sizes, media,
  pricing_tiers, opaque availability payloads, draft/pending_review
  status), `TranslationReadiness`, `translation_statuses`,
  `TourImage.alt_locale`, `PublicOperatorSummary.verified` (additive).
- `types/partner.ts`: nullable `difficulty_level`, `important_information`,
  structured `itinerary`, `translations.en`, `translation_statuses`,
  `TourMedia.alt_text` nullable + `alt_locale`. `types/tour.ts`:
  nullable `difficulty_level`, `exclusions`, `important_information`.
- `lib/api/partner.ts`: `createTour`/`updateTour` take the typed payload
  (no `as unknown` casts).
- `TourWizard.tsx`: both submissions restored to the full pre-existing
  field set (pricing/availability/min/max included) behind the typed
  payload; string `duration_value` conversion retained;
  `difficulty_level ?? ''` keeps the required Select controlled for the
  nullable type. `tourWizard.ts` initial state extended with
  `exclusions: ''` and `important_information: []`. No payment/booking
  workflow altered.

### T007 Compatible media readers

- `Tour::allImageUrls` now accepts legacy `image` plus `cover`/`gallery`
  rows; first usable cover-typed row leads even at a later sort_order;
  rest follows deterministic sort_order/id order; dedupes safe HTTP(S)
  URLs; legacy `cover_image_url` is fallback only for media-empty tours
  and is never prepended to a valid gallery (stale or not).
- New `backend/tests/Feature/Search/TourMediaReaderTest.php` (7 tests):
  before the fix, cover/gallery rows were invisible and a stale legacy
  cover was prepended. Covers nonzero-sort cover priority, stale/absent
  legacy cover, loaded vs unloaded relation paths, legacy dedup, empty
  gallery, invalid URLs. Typed writers still belong to US4.

### T008 English input boundary

- New `backend/app/Domains/Partner/Requests/TourContentRules.php`:
  `normalizeEnglishPatch` (nested wins, shorthand fills, omitted stays
  absent, null/`[]` retained), `normalizeItinerary` (absent/null stops to
  `[]`, order and repeated days preserved), `sourceRules` (title max120,
  description max5000 with existing create min100 preserved, lists max30 /
  item max500, meeting_point max500, cancellation_policy max2000),
  `itineraryRules` (array max30, day int 1-30, titles nonblank max160,
  descriptions max2000, stops max20, duration int 1-1440, exact nested
  paths), `forgeryRules` (`translations.es/it`, hashes, readiness fields
  `prohibited`) with field-specific messages.
- `StoreTourRequest`/`UpdateTourRequest` merge the boundary while
  preserving existing create requirements and partner-scoped title
  uniqueness. Controller/service application is US1 (T015/T016); the
  active routes still use inline validation.
- New `backend/tests/Feature/Partner/TourContentInputTest.php` (7 tests)
  failed first (`TourContentRules` missing, requests unwired), now green.

### T009 Fixtures and fake-provider barriers

- New `backend/tests/Support/TourContentFixtures.php` (`Tests\Support`,
  autoloadable): deterministic `englishAttributes`, `seedDerivative`
  (current/missing/stale/failed), `partnerPairWithDistinctIds`
  (partner-record IDs distinct from user IDs), `secondConnection`
  (independent PDO to the disposable DB), `barrierKey` (crc32
  advisory-lock keys), `Spec019FakeTranslator` (deterministic suffix
  translations, arrival counts, no network).
- Proven in-suite: second connection reports `current_database() =
  bookly_test`; advisory-lock exclusion/ordering works across the two
  connections; `GEMINI_API_KEY` blank asserted; fake arrivals recorded.
  Cross-connection row fixtures stay invisible inside the
  RefreshDatabase transaction by design; US3 owns true race tests.

### Gate commands and results (actual, 2026-10-03)

- Backend (from repo root, disposable PG, blank provider key):
  `docker compose exec -T -e GEMINI_API_KEY= laravel php -d
  memory_limit=512M vendor/bin/pest --configuration=phpunit.pgsql.xml`
  with the 3 new files + `TourCreateTest/TourDraftTest/
  TourTranslationTest/TourDetailTest/TourModerationTest`:
  **83 passed, 425 assertions, 106.84s, exit 0** (23 new foundation +
  60 inherited; inherited results are regression checks, not new
  acceptance). Full-suite logs: `/tmp/spec019-full8.log` (container).
- `php vendor/bin/pint --test` on all 10 touched PHP files: PASS after
  auto-fixes. `phpstan analyse` on the 6 touched app/support paths:
  **No errors** (initial Tour.php property findings fixed via TourMedia
  `@property` tags + typed closure).
- Frontend (from `frontend/`): `npm run typecheck`: PASS (two new
  nullability errors from the extended DTOs fixed at the source).
  Scoped `npx eslint` on all 6 touched TS/TSX files: PASS with no
  output. Full `npm run lint` (whole project) **times out on this host
  (>600s) — environment limitation, NOT a pass**; full-lint acceptance
  stays pending.
- Jest `--runInBand`: `partner.test.ts` **19 passed** (after recreating
  the host-wiped `%TEMP%/jest` cache dir); 7 tour/partner/api suites
  **38 passed**. Total 57 passed, 0 skipped/weakened.
- New tests use `spec019`-prefixed helpers (no global Pest collisions);
  combined multi-file runs verified. Pint-normalized style only; no
  test was skipped or loosened and no assertion weakened.
- `git diff --check`: clean (verified at report time).
- Pending after this slice: US1 T010-T024, then US2-US6 and T073-T078
  cross-cutting gates (full lint/build, Lighthouse, adoption/recovery
  rehearsal, FR/SC reconciliation).

## 13. Foundation Rework After Review (executed 2026-10-03, second pass)

Section 12 is retained as the first-pass record; this section records the
reviewer-ordered corrections. US1 T010-T024 remains unimplemented and no
full-spec completion is claimed. Orchestrator-owned files observed during
this pass (`docs/...master-plan.md`, `research.md`, `spec.md`,
`evidence/foundation-*.json`, isolated `bookly-spec019-browser-*`
stack, PR28 merged, origin/main `803048e`) were left untouched; merge and
integration ownership stays with the orchestrator.

### Corrected boundary contract (review items 1-2, 5)

- `TourContentRules::sourceRules`: title is now a non-null string whenever
  supplied in both create and update (`translations.en.title` likewise);
  description follows the shared nullable draft rule in both Requests (the
  obsolete update `min:100`/non-null override is removed).
- `UpdateTourRequest` no longer carries the obsolete `tours.title`
  uniqueness override (titles live in `tour_translations`, not `tours`);
  ownership scoping stays in the controller/service layer for US1 wiring.
- `normalizeItinerary` passes non-array input through verbatim and keeps
  explicit `stops: null` as null; only an absent `stops` key normalizes to
  `[]`. Validation therefore reports exact field errors instead of this
  layer silently clearing malformed input.
- Forgery rejection is now behavioral, not rule-string based:
  `forbiddenPresentPaths` scans raw input and reports every authored
  non-EN locale (`es/it/fr/de/...`, any value including null/empty) and
  every platform-owned key top-level or nested under a translations entry,
  by precise path. Both Requests enforce it through `withValidator`
  after-hooks (Laravel `prohibited` lets present-but-null/empty values
  through, so the hook is the enforcement). The old `forgeryRules()` /
  `messages()` scaffolding is removed.
- `TourTranslationService` normalization preserves malformed day/stop
  scalars and non-array stops lists verbatim (no invented titled objects,
  no numeric-string coercion); only valid objects get key-order
  canonicalization and absent-stops defaults. Serialization flags remain
  the inherited set. `sourcePayload` exposes the raw preservation and
  `applyStrings` rejects malformed source via real validation.

### Real row-lock proof (review item 3)

- New test seeds dedicated fixtures on its own named default connection
  (`spec019_owner`, unique slug, committed for real) so a second
  connection can see them despite the wrapping RefreshDatabase
  transaction; the original default is restored and fixtures cleaned in
  `finally`. While `withContentLock` holds, the contender's NOWAIT
  `SELECT ... FOR UPDATE` on the Tour, EN, es-state and it-state rows
  each fail with SQLSTATE `55P03`; after release all four lock cleanly.
  Every contender statement is NOWAIT/bounded — no hangs possible.
- Query-log order pinning (Tour -> EN -> es -> it with exact bindings) is
  retained as the order proof; the NOWAIT test is the reality proof.
  True job interleavings remain US3.

### Fixtures, gate and standalone fake (review items 4, 8)

- `partnerPairWithDistinctIds` now assigns explicit high partner IDs with
  `partners_id_seq` repair and throws unless both record IDs differ from
  both user IDs and each other; proven by a dedicated guarantee test.
- New `Spec019Gate` (advisory-lock hold/tryHold/release) gives US3 a
  controlled pause/release hook; proven held-then-released in-suite.
- The fake lives in its own PSR-4 file
  (`tests/Support/Spec019FakeTranslator.php`): `class_exists` is true
  straight from `vendor/autoload.php`, and the filtered fake test passes
  in isolation (exit 0, 1 passed / 4 assertions).

### Concrete write DTOs (review item 6)

- `availability_rules/exceptions: unknown[]` replaced with concrete
  `PartnerAvailabilityRuleInput` / `PartnerAvailabilityExceptionInput`
  (server shapes; wizard form objects assign directly) plus
  `PartnerTourMediaInput` / `PartnerPricingTierInput`.
- `EnglishSourceInput.title` is now `title?: string` (non-null when
  supplied); top-level `description?: string | null` (nullable draft
  description supported in both places).
- `translation_statuses` removed from public `TourDetail` (the public
  contract carries only singular `translation_status`; verified
  `GetTourDetailAction.php:86`). Owned es/it readiness is now
  `OwnedTranslationStatus` (`pending|ready|stale|failed`, no `source`),
  consumed by partner `Tour.translation_statuses`; the widened
  `TranslationReadiness` alias is deleted.
- `lib/api/partner.ts` uses a top-level `import type`; no dead
  imports/helpers remain. All wizard pricing/availability fields intact.

### Corrected gate methodology and results (review item 7)

- Exit-code discipline fixed: every gate below redirects to a host-side
  log with PowerShell `$LASTEXITCODE` captured numerically. The earlier
  `EXIT:True` lines were PowerShell interpolating `$?` inside the remote
  `sh -c` string — a bool, not a shell status — and are discarded as
  evidence. Piped `Select-Object -First N` output was never the pass
  criterion; full logs were read for the `Tests:`/`OK`/`PASS` lines.
- The first `TourContentInputTest` run timed out with no output (missing
  class + cold migration under host pressure): environment uncertainty,
  NOT red evidence. The rerun after creating the class failed honestly
  (class-not-found per test), then passed after implementation — genuine
  TDD red/green, preserved in container `/tmp/spec019-input-test*.log`.
- Backend full set (same 8 files as §12), blank provider key:
  **exit 0 — 89 passed, 535 assertions, 75.51s** (29 new foundation +
  60 inherited). New-file rerun post-Pint: **exit 0 — 29 passed, 203
  assertions**. Filtered fake-only run: **exit 0 — 1 passed**.
- `pint --test` (11 touched PHP files): **exit 0 — PASS** (after
  auto-fixes). `phpstan analyse` (8 touched paths): **exit 0 — No
  errors**.
- Frontend gates ran in the isolated `bookly-spec019-browser` nextjs
  container (Node v22.23.3), dev services untouched:
  `npm run typecheck` **exit 0**; `npm test -- --runInBand` (validator +
  7 consumer suites) **exit 0 — 8 suites, 57 tests, 114.232s**.
  Full `npm run lint` **exit 1 with the only 3 errors in
  `frontend/.phase1-evidence/extract-phase1.cjs`** (`no-require-imports`;
  git-ignored pre-existing Phase 1 tooling, unrelated to this slice,
  left untouched) — all 7 touched TS/TSX files lint clean. The earlier
  native `npm run lint` >600s timeout is a Windows-host I/O limitation,
  not a result.
- `git diff --check` clean (CRLF advisories only, on orchestrator-owned
  docs). Staged diff empty; nothing committed.
- Standing by for the independent reviewer rerun. Pending acceptance
  after foundations: US1 T010-T024 first, then US2-US6 and T073-T078.

## 14. Delta Corrections After Second Review (executed 2026-10-03, third pass)

Sections 12-13 retained as history. This pass closes the five follow-up
items; US1 T010-T024 remains unimplemented and no full-spec completion
is claimed. Orchestrator metadata, evidence JSON, browser stack and
`.todo` untouched; no git mutations; provider key blank everywhere.

### Item 1 — explicit null stops rejected, not treated as empty

- `itineraryRules` day-stops rule is now `sometimes|array|max:20`
  (nullable removed), matching the partner contract table and
  data-model: stops is an ordered array, absent defaults to `[]`, valid
  zero stops is `[]`. `normalizeItinerary` still passes explicit null
  through untouched so validation rejects it at the exact `.stops` path.
  Duration/description nullability unchanged.
- New behavioral case `rejects explicit null stops instead of treating
  null as empty` asserts failure at
  `translations.en.itinerary.0.stops`; the boundary-valid case uses
  `stops: []`. Null is never described as empty.

### Item 2 — provider/job/error and unexpected EN keys rejected

- `forbiddenPresentPaths` now additionally rejects: any
  `translations.en` key outside the 9-field source allowlist with its
  precise path (`provider_body`, `custom_field`, ...), and seven
  top-level provider/job/error state fields (`provider`,
  `provider_body`, `provider_error`, `provider_response`, `job_id`,
  `job_state`, `generation_status`), including present-but-null/empty
  values. Deeper unknown keys (inside days/stops) cannot reach writes
  because only validated data is applied.
- Behavioral cases added: `translations.en.provider_body=null`,
  top-level `provider_error=null` + `job_state=[]`, unexpected
  `translations.en.custom_field`, and a valid lifecycle/pricing/
  availability payload proving non-source input stays intact. These were
  unwired-rule defects (the active controller already rejects non-EN
  locales independently); not deployed-API bypass claims.

### Item 3 — real fake pause plus accurate lock documentation

- `Spec019Gate` removed from `TourContentFixtures.php` (no matching
  PSR-4 file, and wrapping advisory primitives never paused the fake).
  `barrierKey` removed with it as unused scaffolding.
- `Spec019FakeTranslator::onArrival` hook added: a Fiber test suspends
  inside `translate()` after the arrival is recorded, the parent
  observes suspension + arrival with no output yet, resumes, and asserts
  the completed deterministic output — an actual tested pause/release on
  the fake, no network, deterministic. Real job interleavings stay US3.
- The separate-connection NOWAIT Tour/EN/es/it lock test is kept and now
  documented (code + here) as proving conflicting lock acquisition on
  real rows, not actual concurrent writes or jobs.

### Item 4 — full lint green via generated-artifact ignore

- `frontend/eslint.config.mjs` `globalIgnores` gained
  `.phase1-evidence/**` only (generated local audit artifact,
  git-ignored, not production source). No application/test source
  excluded, no rule disabled, evidence/helper files preserved.
- Full `npm run lint` in the isolated nextjs container: **exit 0**
  (previously exit 1 solely on that artifact; scoped-file lint was never
  claimed as full-lint success).

### Item 5 — hash-normalizer docblock corrected

- The stale private-method docblock claiming scalar days become titled
  objects now describes the actual raw-preserving projection.

### Final gate results (numeric `$LASTEXITCODE`, host-side logs)

- Backend 8-file set: **exit 0 — 92 passed / 547 assertions / 85.47s**
  (32 new foundation + 60 inherited regression-only). Post-Pint rerun
  of the 3 new files: **exit 0 — 32 passed / 215 assertions**.
  Filtered standalone-fake run earlier: **exit 0 — 1 passed**.
  A mid-pass failure is recorded honestly: a bad edit duplicated the
  fake's method body (parse error, exit 2 in `spec019-delta1.log`),
  repaired and green thereafter.
- `pint --test` (11 files): **exit 0 — PASS**. `phpstan` (8 paths):
  **exit 0 — No errors**.
- Container frontend: `typecheck` **exit 0**; `npm run lint` **exit 0**;
  Jest 8 suites **exit 0 — 57 passed, 0 snapshots**.
- `git diff --check` **exit 0** (CRLF advisories only, on
  orchestrator-owned docs). Staged diff empty; nothing committed.
- Standing by for the independent reviewer rerun. Pending: US1 T010-T024,
  then US2-US6 and T073-T078.

## 15. Independent Foundation Acceptance (root reviewer, 2026-10-03)

T001-T009 accepted; US1 T010-T024 is next. HEAD before this acceptance
commit is `7d60da9`; every gate ran on the reviewed working tree including
the corrections below. A later main integration must preserve this source.

- Read production/test diffs against the contracts; no inherited test was
  removed, weakened or skipped. Removed the advisory wrapper; actual fake
  pause/resume and separate-connection row-lock acquisition are verified.
  Real job/write interleavings remain US3 acceptance.
- Reviewer corrected the partner read DTO to the actual localized-row
  array returned by `TourController::show`, using concrete source fields;
  write input remains nested EN. Strengthened the rollback regression to
  assert the intended exception message, preventing a different database
  failure from being mistaken for successful rollback proof. Clarified
  hash docblocks: object-key projection is separate from bounds validation.
- Backend suite owner for the independent rerun: root reviewer; the
  OpenCode process exited first. `phpunit.pgsql.xml`/bootstrap force the
  disposable `bookly_test` database on `bookly-test-postgres`; automated
  provider key blank and no live provider traffic. Existing applied
  migrations and raw legacy/derived data preserved.

| Gate | Actual result | Full retained output |
|---|---|---|
| PostgreSQL 8-file focused set | exit 0; 92 passed / 548 assertions / 35.10s (32 new foundation + 60 inherited) | [backend](evidence/reviewer-foundations-backend-focused.txt) |
| Pint, 11 PHP files | exit 0; PASS | [Pint](evidence/reviewer-foundations-pint.txt) |
| PHPStan, affected paths/support | exit 0; no errors | [PHPStan](evidence/reviewer-foundations-phpstan.txt) |
| Full frontend lint | exit 0 | [lint](evidence/reviewer-foundations-frontend-lint.txt) |
| Frontend typecheck | exit 0 | [types](evidence/reviewer-foundations-frontend-typecheck.txt) |
| Focused Jest, 8 suites | exit 0; 57 passed / 5.555s | [Jest](evidence/reviewer-foundations-frontend-focused-jest.txt) |

[Manifest](evidence/reviewer-foundations-manifest.jsonl) records UTC start,
finish and numeric exits. [Exact rerun commands](evidence/reviewer-foundations-commands.ps1)
retain this host's paths and runtime prerequisites; frontend ran in the isolated Node
v22.23.3 browser container. Jest emits existing TourCard mock/act warnings;
its inherited tests were untouched. The Laravel lockfile is v11.54.0.
Repository log copies remove terminal whitespace only for Git checks;
original unmodified logs remain in the external delegation evidence directory.

The independent [final rule probe](evidence/foundation-boundary-probe-final-20261003.json)
verified all ten cases, including exact null-stops/unknown-EN/provider/job
rejection paths and accepted empty stops/valid patch. Bare Composer
PSR-4 autoload independently returned true for fake and fixtures (exit 0).
This is reusable-rule acceptance; actual endpoint wiring/round trips remain
US1. Source/itinerary DTOs, compatible image readers, and T009 barriers are
shared foundations, not complete story or browser acceptance.

Runtime prepared: separate `bookly-spec019-browser` project, port 8189,
seeded disposable storage/database, isolated Node/.next volumes and
Chromium/dependencies installed (exit 0). Production Next build/server and
integrated browser acceptance remain pending. The generated local Phase 1
artifact is now excluded from ESLint; application/test coverage remains.
The prior native lint timeout's cause was not established; it is not
reliable evidence of a particular host I/O bottleneck.

User authorized the currently configured translation model. Existing local
backend reports configured Gemini `gemini-3.5-flash-lite`; sanitized
[metadata request](evidence/configured-model-metadata-20261003.json) returned
HTTP 200 with generateContent supported. No real generation yet; automated
suites stay fake/key blank. Remote staging host is not identified; local
isolation must not be described as a remote staging deployment.

US3 prerequisite probe: existing PHP has `pcntl_fork=false`,
`pcntl_async_signals=false`, `proc_open=true` (exit 0). Installed
`Illuminate/Queue/Worker.php::supportsAsyncSignals` checks `pcntl` before
registering alarm-based timeouts; the current Dockerfile omits it. T043
must verify an effective timeout in an isolated review image, rather than
accepting flags alone. No personal service was changed.

Predecessor recheck: `gh pr view 28 --json state,mergedAt,url,statusCheckRollup`
returned MERGED at 2026-10-02T21:55:43Z and every returned check concluded
SUCCESS. Latest fetched main `803048e` still awaits clean-tree integration;
Spec 018 remains preserved. Spec 019 CI/merge/release are independently
pending. Execution follows the user's explicit OpenCode + Spec Kit workflow
with reviewed increments and this evidence ledger.

Guard review: concrete read shape, raw-preservation documentation and
specific rollback exception proof corrected; no new package or fabricated
production result. clean-code-guard: 2 fixed, 0 remaining foundation flags;
test-guard: 1 assertion strengthened. docs-guard: current statuses corrected
and historical pass counts retained with their dates.
