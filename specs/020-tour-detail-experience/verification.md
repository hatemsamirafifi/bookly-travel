# Verification Record: Spec 020 Tour Detail Experience (Setup / Readiness Source Inspection)

**Scope**: Bounded T001–T003 source-inspection evidence only. No frontend behavior implemented. No task is marked complete. Runtime acceptance is NOT claimed from source inspection.
**Branch**: `codex/020-tour-detail-experience`
**Date**: 2026-10-14 (Africa/Cairo)
**Authorities**: `specs/020-tour-detail-experience/{spec.md,plan.md,data-model.md,contracts/,quickstart.md,tasks.md}`; `AGENTS.md`; `frontend/AGENTS.md`. `CLAUDE.md` active-Spec pointer is stale (see §5).

## T001 — Branch, pointer, ancestry, dependencies (source inspection)

- `git branch --show-current` → `codex/020-tour-detail-experience`.
- `git rev-parse HEAD` → `4afdbc6e0bec2d29341ea229b7b7f1b2226ecf27` (`refactor(ui): remove the footer logo white backing`).
- `git rev-parse origin/main` → `9e241b9377d42f904081cb7b2bca1871e3b4388d` (Merge PR #29, Spec 019).
- `git merge-base origin/main HEAD` → `9e241b9377d42f904081cb7b2bca1871e3b4388d`.
- `git merge-base --is-ancestor origin/main HEAD` → success (exit 0 semantics; PowerShell `$?` True): `origin/main` IS an ancestor of `HEAD`.
- Retained six shell commits above `origin/main` (oldest→newest):
  1. `4628f28e15f30c68db6e100478234e8e82fa7353` refactor(ui): consolidate public header navigation
  2. `316e50d4d89a498a3578c93ab8c9c707a2f763ac` feat(ui): use the official Bookly Travel header logo
  3. `bd8589a1890b24fc656c3df773ecaef94e263798` fix(frontend): exclude static assets from locale redirects
  4. `f638e944fed3564451f02fddf04549b29ac3b98b` feat(ui): add the official Bookly slogan logo to the footer
  5. `71c9dca724f02dca8f848d5ca9771b1b47a9465b` refactor(ui): remove the extra footer tagline
  6. `4afdbc6e0bec2d29341ea229b7b7f1b2226ecf27` refactor(ui): remove the footer logo white backing (= HEAD)
- `git diff --stat origin/main...HEAD` touches only header/footer/static-asset/proxy/test surface (10 files, +119/−33), including `frontend/public/brand/` logo assets, `Header.tsx`, `Footer.tsx`, `LocaleSwitcher.tsx`, `MobileNavPanel.tsx`, `proxy.ts`, `Header.test.tsx`, `shared-shell.spec.ts`, plus the master-plan doc. No Spec 020 implementation files are in this diff.
- `.specify/feature.json` content: `{ "feature_directory": "specs/020-tour-detail-experience" }` — active pointer resolves to 020.
- `git status --short` (preserved, untouched):
  - `M .specify/feature.json`
  - `M docs/bookly-ui-redesign-spec-kit-master-plan.md`
  - `?? competitor-profiles/`
  - `?? specs/020-tour-detail-experience/`
- No branch switch, reset, clean, stage, commit, push, PR, or deployment performed.
- Spec 019 deployment prerequisite and shell-dependency merge readiness are RECORDED as separate pending gates, not verified here. The six retained commits are an explicit unmerged dependency per `plan.md`; their presence is not release acceptance.

## T002 — Instructions, manifests, bundled docs, primitives, ignores, planned-file audit

- Governing instructions read: root `AGENTS.md` (generic stack summary; PHP 8.3+/TS/Next.js notes are stale aggregates, not Spec 020 authority) and `frontend/AGENTS.md` (Next.js 16 App Router conventions; requires reading bundled `node_modules/next/dist/docs/` before writing code; server/client separation; no direct DB access; next-intl message schemas).
- Installed frontend manifest (`frontend/package.json`): `next 16.2.3`, `react`/`react-dom 19.2.4`, `next-intl ^4.9.1`, `jest ^30.4.2`, `@playwright/test ^1.60.0`, `typescript ^5` (installed `5.9.3` per `node -e`), `tailwindcss ^4`, `eslint ^9`. Installed `next` version confirmed `16.2.3` via `node -e`.
- Backend manifest (`backend/composer.json`): `php ^8.2`, `laravel/framework ^11.0`, `laravel/sanctum ^4.0`, `filament/filament ^3.3`. Plan's note stands: do NOT infer a PHP 8.3 minimum from the stale AGENTS summary.
- Bundled Next docs verified present as real `.md` guides under `frontend/node_modules/next/dist/docs/`:
  - `01-app/03-api-reference/01-directives/use-server.md` (title: use server; server-function directive).
  - `01-app/03-api-reference/01-directives/use-client.md` (title: use client; client-boundary entry point).
  - `01-app/03-api-reference/02-components/image.md` (title: Image Component; 1425 lines).
  - `01-app/03-api-reference/03-file-conventions/error.md` (title: error.js; error boundaries must be Client Components).
- UI primitives present in `frontend/src/components/ui/` (23 entries), including `Dialog.tsx`, `Accordion.tsx`, `Container.tsx`, `Breadcrumbs.tsx`, `CarouselRail.tsx`, `LoadingSkeleton.tsx`, `ErrorState.tsx`, `RatingBadge.tsx`, `SectionHeading.tsx`, plus `__tests__/`. File contents not re-verified beyond existence in this bounded job.
- Ignores: root `.gitignore` excludes `.env`/`.env.*`, `backend/vendor/`, storage/framework artifacts, and IDE/OS files; `frontend/.gitignore` excludes `/node_modules`, `/.next/`, `/tests/.auth` (generated Playwright storage state), `.env*`, coverage/lighthouseci outputs.
- Planned-file audit (existence only; missing planned files are expected pre-implementation targets, not failures):
  - Tour route dir `frontend/src/app/[locale]/(public)/tours/[slug]/` exists with `page.tsx` + `__tests__/`; route-local `loading.tsx`/`error.tsx` are absent (planned in T037, not yet implemented).
  - `frontend/src/components/tour/` contains `TourDetail.tsx`, `TourSectionNav.tsx`, `AvailabilityCalendar.tsx`, `BookingCTA.tsx`, `ImageGallery.tsx`, `MobileBookingAction.tsx`, `OperatorSummary.tsx`, `RelatedTours.tsx`, `ReviewList.tsx`, `__tests__/`; planned `TourHeader/TourGallery/TourOverview/TourFacts/TourHighlights/TourItinerary/TourInclusions/TourMeetingPoint/TourReviews/BookingCard/MobileBookingBar/TourBookingController` are absent (expected).
  - `frontend/src/lib/tours/` does not exist yet (planned `detail-presentation.ts`, `booking-selection.ts` + `__tests__/` are T006–T007/T020–T023 targets); existing `frontend/src/lib/api/{tours,types,client}.ts` present.
  - `frontend/messages/{en,es,it}.json` present (catalog key work is T009/T039, not started).
  - `frontend/lighthouserc.js` is EN-only (4 EN URLs, 3 runs, pessimistic ≥0.9); ES/IT detail URLs are a planned T071 change, not present.
- Checklist `specs/020-tour-detail-experience/checklists/requirements.md` is read-only, 16/16 PASS as document review (specification quality only; not runtime evidence). Not modified.

## T003 — Environment readiness (source + bounded probes; NOT acceptance)

- Full environment record: `specs/020-tour-detail-experience/evidence/environment.md`.
- `docker compose ps` at inspection time: 8 services `Up ~28 minutes`: `bookly-backend`, `bookly-frontend` (published `0.0.0.0:3001→3000`), `bookly-meilisearch`, `bookly-nginx` (`0.0.0.0:8080→80`), `bookly-postgres` (healthy), `bookly-queue`, `bookly-redis` (healthy), `bookly-scheduler`.
- Image-vs-host caveat (from `docker-compose.yml` `nextjs` service): frontend production code comes from the image (`npm run build && npm start`); only `./frontend/.env.local` is bind-mounted read-only plus anonymous `/app/node_modules` and `/app/.next`. Comment states: "Rebuild this service after changing frontend source." A running container therefore does NOT automatically include later host edits.
- Bounded read-only probes (`curl.exe --max-time 12`), recorded honestly:
  - `http://localhost:8080/` → `000`, timeout after ~12.0s (unreachable within bound; NOT readiness).
  - `http://localhost:3001/` → `000`, timeout after ~12.0s (unreachable within bound; NOT readiness).
  - `http://localhost:8080/en/tours/hidden-gems-rome-walking-tour` → `000`, timeout after ~12.0s (unreachable within bound; NOT readiness).
  - `http://localhost:8080/api/up` → `404` after ~8.5s (a response was received, but 404 is not a health pass and not Spec 020 readiness).
- No restart/build/reseed/clear, no backend tests, no competing Jest/build run from this job (root owns baseline Jest separately).
- Playwright prerequisites noted from source: `frontend/playwright.config.ts` baseURL is `http://localhost:8080` on host dev (`http://nginx` inside Docker); `chromium`/`mobile` run logged-out specs; `booking/checkout/payment/wishlist` specs run only in `chromium-authed`/`mobile-authed` via seeded storage state; `setup` project saves traveler/partner states. Seeded-auth validity and deterministic payment mode were NOT executed here.
- Disposable backend config noted from source: `backend/phpunit.pgsql.xml` targets `bookly-test-postgres`/`bookly_test` with array cache/session, sync queue, collection Scout driver; `docker-compose.test.yml` defines disposable `test-postgres` (no host port, tmpfs) and `test-meilisearch`. No test service was started here.

## Incomplete gates / blockers (nothing marked complete)

- T001: source ancestry recorded; Spec 019 deployment prerequisite and shell-dependency merge/release readiness remain PENDING (require target-environment/merge verification outside this job).
- T002: source/primitive/doc audit recorded; full content-level verification of every planned import path is PENDING (deferred to implementation tasks that compile against them).
- T003: BLOCKED as acceptance — proxy/published-frontend probes timed out within the 12s bound and `/api/up` returned 404; SSR harness prerequisites, disposable PostgreSQL startup, seeded traveler/partner auth, and deterministic payment mode are unverified here. An unavailable/unproven runtime is not a passing baseline. Root runtime check and T004 baseline capture own these gates.
- T004 and all later tasks: untouched; baseline Jest output is owned by root and not duplicated here.

## Documentation discrepancies noted

1. `CLAUDE.md` "Active Spec-Kit Feature" still points to Spec 014 (`014-notifications-vouchers`); authoritative pointer for this job is `.specify/feature.json` → `specs/020-tour-detail-experience` (020). Per task brief, plan/task artifacts govern; the CLAUDE.md pointer is stale.
2. Root `AGENTS.md` "Active Technologies" aggregates older feature/fix notes (including a PHP 8.3+ summary); `backend/composer.json` (`php ^8.2`) and `plan.md` govern — do not infer a PHP 8.3 minimum.
3. `frontend/lighthouserc.js` collects EN URLs only; it cannot prove SC-008 (EN/ES/IT) unmodified — extension to ES/IT detail URLs is planned work (T071), not current evidence.
4. No other plan/spec contradictions found in this bounded read; research.md's stated UTC-today booking rule and bounded auth-continuation gap are reflected in FR-017/FR-022 as documented.


## Root review and local readiness confirmation — 2026-10-14

The earlier bounded probe failures remain diagnostic evidence; subsequent root checks verified the local runtime. Public EN detail returned HTTP200 in0.286s, with60 offered dates, EUR pricing, EN content/itinerary and not-unavailable status (actual public response retained at evidence/baseline/public-detail-en.json). Internal nginx→Next tour-page request exited0. Both seeded test-auth fixtures returned200 and issued nested data.token; only status/boolean evidence retained, no tokens. Runtime services.payment.gateway is deterministic and its bound class is DeterministicPaymentGateway. Disposable test services started; PostgreSQL public-contract suite passed47 tests/1083 assertions in21.16s.

Native Node24.14.1/npm11.12.1 differ from the guide's Node22/npm10 recommendation. No dependencies were changed. First native focused Jest attempt stalled before any result and was stopped only after verifying its exact owned PID/command. A single-suite retry with --watchman=false passed6 tests; the complete focused baseline with that flag then passed8 suites/64 tests in14.188s. This establishes a usable runner configuration, not a proven causal diagnosis of the first stall. All baseline logs are retained.

Root reviewed the delegated evidence against actual files, bundled server/client/error/image guides, primitive interfaces, git ancestry/pointer and ignore configuration. T001-T004 are accepted for the verified local implementation baseline; external target deployment and retained-shell merge readiness remain separate future T073 gates. No source behavior has changed yet.
