# Bookly Travel — Full-Codebase Health Audit + Fix Plan

> **For Hermes:** Use subagent-driven-development skill to implement this plan task-by-task.

**Goal:** Audit the entire Bookly Travel codebase (Laravel API + Next.js frontend + infra + tests + docs) and fix all health issues found, ending with green static analysis, green test gates, clean git tree, and current docs.

**Architecture:** Read-only baseline first (inventory → static analysis → test baselines, no fixes), then hygiene → backend → frontend → security → data/infra → docs. Each fix is TDD/verify-then-commit, bite-sized.

**Tech Stack:** Laravel 11 (PHP 8.2+), Sanctum 4, Filament 3.3, Scout + Meilisearch, Stripe, Pest 3, Larastan, Pint / Next.js 16.2.3, React 19.2, TS strict, next-intl (en/es/it), TanStack Query 5, Zustand 5, RHF + Zod, Tailwind 4, Sentry 10 / Postgres 15, Redis 7, nginx, Docker Compose.

---

## Current context / assumptions (from read-only inspection, 2026-09-08)

- Repo root: `F:/Travel Website/bookly travel` (accessed via git-bash; path contains a space — quote it). 1,481 tracked files. Recent commits: Spec 016 blog + E2E remediation (`7a3e676`, `3c1acdf`, `cf98f01`…).
- Backend: `backend/` — 329 `app/*.php` files, domain layout (`Admin/Auth/Blog/Booking/Partner/Payment/Reviews/Search/Traveler/Wishlist`), 104 test files, 54 migrations (latest: blog ×5, partner invitations ×3, booking voucher/start_time). `phpstan.neon` level 5, excludes `app/Filament/`. `pint.json` laravel preset. `phpunit.xml` uses sqlite `:memory:`; `phpunit.pgsql.xml` exists (PG suite).
- Frontend: `frontend/` — Next.js 16 + React 19, `strict: true`, next-intl, Jest + RTL, Playwright incl. `a11y` project, LHCI/Lighthouse. ~50 E2E specs + ~15 a11y specs + Jest unit tests under `src/**/__tests__`.
- Infra: `docker-compose.yml` (nextjs, laravel, queue `default,scout`, scheduler, nginx `:8080`, postgres, redis, meilisearch). Dockerfiles in `backend/`, `frontend/`.
- Tests runner: `run_all_tests.py` (TestSprite; hardcodes a Windows python path; runs `docker exec bookly-backend php artisan cache:clear` between tests; 120s timeout).
- Git status (dirty): `M .gitignore` (adds `testsprite_tests/tmp/`), `M frontend/src/app/[locale]/(partner)/partner/page.tsx`, `M frontend/src/hooks/usePartnerAnalytics.ts`, `M run_all_tests.py`, `M testsprite_tests/*` (5 modified) + 10 new untracked `TC00*.py` + `testsprite-mcp-test-report.md`. `specs/`: 001–016 + `main`. `docs/`: PRD, ADRs, regression audit gate/report, `test-coverage-gap-analysis.md` dated 2026-05-16 (stale — says 107 backend files; now 329).
- Secrets: root `.env` exists (contains `POSTGRES_PASSWORD`); `backend/.env`, `.env.docker` exist. Tracked: only `backend/.env.example` (good — verify before any commit). `git ls-files` shows no `node_modules/`, `vendor/`, `coverage/`, `.next` tracked (good).
- Code signals: no `TODO/FIXME/dd()/dump()` in `backend/app` (grep hit only `in_array`/`is_array` noise — clean). Frontend: `/* eslint-disable @typescript-eslint/no-explicit-any */` in 3 partner pages (`tours/[id]/availability`, `tours/[id]/edit`, `tours/[id]/pricing`) + widespread `catch (err: any)` / `Record<string, any>`.
- Config risks (unverified, need tasks below): `next.config.ts` `images.remotePatterns: [{ protocol: 'https', hostname: '**' }]` (wide open); rewrite fallback `http://nginx`; `du` over workdir timed out (heavy `node_modules`/`vendor`/`coverage`/`playwright-report` — need ignore/prune audit); workdir logs (`build.log`, `dev-server.log`, `frontend-dev.*.log`, `lighthouse-report.html`) may be untracked bloat.

## Proposed approach

1. Baseline everything read-only; record exact failing commands.
2. Fix hygiene first (gitignore, artifacts, env safety) so later gates are trustworthy.
3. Backend gate green (Pint → PHPStan → Pest sqlite → Pest pgsql), then frontend gate green (ESLint → tsc → Jest → Playwright smoke).
4. `any`-cleanup + image-domain lockdown + Sentry/headers check.
5. Security pass (authZ, rate limits, Stripe webhooks, blog preview tokens).
6. Data/infra pass (migrations, Meili/queue/scheduler, PG-vs-sqlite drift, backups).
7. Docs refresh + final regression gate report. Commit after every task.

All shell commands assume git-bash on Windows. Set once per session:

```bash
P="F:/Travel Website/bookly travel"
cd "$P" && git status --short && git log --oneline -5
```

---

## Step-by-step plan

### Task 1: Record baseline inventory (read-only)

**Objective:** Freeze the starting state so fixes are comparable.

**Files:** none (read-only).

**Step 1: Capture tree + counts**

```bash
P="F:/Travel Website/bookly travel"
cd "$P"
git log --oneline -10
git status --short
echo "--- tracked ---"; git ls-files | wc -l
echo "--- backend app files ---"; find backend/app -type f -name "*.php" | wc -l
echo "--- backend tests ---"; find backend/tests -type f | wc -l
echo "--- migrations ---"; ls backend/database/migrations | wc -l
echo "--- frontend src ---"; find frontend/src -type f \( -name "*.ts" -o -name "*.tsx" \) | wc -l
echo "--- e2e ---"; find frontend/tests -type f | wc -l
```

Expected: matches context above (~1481 tracked, ~329 app files, ~104 backend tests, 54 migrations). Save output into the final gate report (Task 22).

**Step 2: Commit:** none (read-only; no commit).

### Task 2: Git hygiene — decide what the dirty tree means

**Objective:** Every dirty/untracked file is either committed intentionally or ignored.

**Files:**
- Modify: `.gitignore`
- Review: `frontend/src/app/[locale]/(partner)/partner/page.tsx`, `frontend/src/hooks/usePartnerAnalytics.ts`, `run_all_tests.py`, `testsprite_tests/`

**Step 1: List everything**

```bash
P="F:/Travel Website/bookly travel"
cd "$P"
git status --short
git diff --stat
git diff .gitignore
git status --short --untracked-files=all | head -n 60
git ls-files | grep -iE "\.log$|coverage|playwright-report|test-results|\.next|lighthouse-report" | head -n 30
```

Expected: dirty list matches context (partner page + hook + run_all_tests.py + testsprite artifacts + .gitignore).

**Step 2: Decide (rule):** source changes (`partner/page.tsx`, `usePartnerAnalytics.ts`, `run_all_tests.py`) → review diff and commit in Task 4. Generated artifacts (logs, coverage, playwright-report, test-results, lighthouse-report, `testsprite_tests/tmp/`) → ignore, never commit.

**Step 3: Verify:** `git status --short` reviewed line-by-line; no `.env` in the list (if one appears, STOP — see Task 3).

### Task 3: Secrets safety gate (blocking)

**Objective:** Prove no secret can be committed.

**Files:** read-only: `.env`, `backend/.env`, `backend/.env.docker`, `backend/.env.example`, `.gitignore`.

**Step 1: Verify tracked set**

```bash
P="F:/Travel Website/bookly travel"
cd "$P"
git ls-files | grep -i env
echo "--- root .env exists? ---"; ls -la .env backend/.env 2>&1
echo "--- gitignore env rules ---"; grep -n -i "env" .gitignore | head -n 20
```

Expected: only `backend/.env.example` tracked. `.gitignore` ignores `.env*` (except example).

**Step 2: If anything else is tracked** (e.g. `backend/.env`), rotate the exposed secret first, then `git rm --cached <file>`, then fix `.gitignore`. Do not proceed to other tasks until this passes.

**Step 3: Commit (only if a fix was needed):**

```bash
git add .gitignore
git commit -m "chore: stop tracking env secrets"
```

### Task 4: Lock down .gitignore + remove workdir bloat from git's sight

**Objective:** Logs, coverage, reports, and tmp artifacts never show as dirty again; intentional source edits committed.

**Files:**
- Modify: `.gitignore`

**Step 1: Append the missing rules** (add only lines not already present):

```gitignore
# test + report artifacts
frontend/coverage/
frontend/playwright-report/
frontend/test-results/
frontend/lighthouse-report.html
frontend/*.log
frontend/*.err.log
frontend/*.out.log
build.log
dev-server.log
testsprite_tests/tmp/
```

**Step 2: Verify**

```bash
P="F:/Travel Website/bookly travel"
cd "$P"
git status --short
git check-ignore -v frontend/build.log frontend/coverage testsprite_tests/tmp/config.json 2>&1
```

Expected: `git status --short` no longer lists logs/coverage/tmp; `check-ignore` prints the matching rule for each.

**Step 3: Commit source edits + ignore rules separately**

```bash
git add .gitignore && git commit -m "chore: ignore logs, coverage, and testsprite tmp artifacts"
git diff -- frontend/src/app/\[locale\]\(partner\)/partner/page.tsx frontend/src/hooks/usePartnerAnalytics.ts run_all_tests.py
# after review:
git add "frontend/src/app/[locale]/(partner)/partner/page.tsx" frontend/src/hooks/usePartnerAnalytics.ts run_all_tests.py
git commit -m "chore: commit pending partner page, analytics hook, and test runner changes"
```

### Task 5: Backend — Pint (format gate)

**Objective:** Zero format diffs.

**Files:** touches `backend/**/*.php` only via formatter.

**Step 1: Run**

```bash
P="F:/Travel Website/bookly travel"
cd "$P/backend"
./vendor/bin/pint --test 2>&1 | tail -n 20
```

Expected on dirty: list of files needing format. Fix:

```bash
./vendor/bin/pint 2>&1 | tail -n 5
./vendor/bin/pint --test 2>&1 | tail -n 3
```

Expected after: `PASS` / no diffs.

**Step 2: Commit**

```bash
cd "$P" && git add backend && git commit -m "style: pint backend formatting"
```

### Task 6: Backend — PHPStan level 5 green

**Objective:** `phpstan analyse` passes with zero errors at the repo's current level.

**Files:** fix where reported; likely `app/Domains/**`, `app/Http/**`.

**Step 1: Baseline**

```bash
P="F:/Travel Website/bookly travel"
cd "$P/backend"
./vendor/bin/phpstan analyse --memory-limit=1G 2>&1 | tail -n 30
```

Expected: pass or a finite error list. Fix errors one file at a time (prefer real types over `ignoreErrors`; note `app/Filament/` is currently excluded — do NOT silently widen the exclusion; if a Filament error blocks, record it for Task 8).

**Step 2: Verify:** rerun until `OK — No errors`.

**Step 3: Commit:** `git add backend && git commit -m "fix: phpstan level 5 green"`.

### Task 7: Backend — Pest suite on sqlite (default phpunit.xml)

**Objective:** Full backend suite green on the default config.

**Files:** fix source/tests as failures dictate.

**Step 1: Run**

```bash
P="F:/Travel Website/bookly travel"
cd "$P/backend"
php artisan config:clear 2>&1 | tail -n 2
./vendor/bin/pest --colors=never 2>&1 | tail -n 25
```

Expected: `Tests: X passed` (104 files). If failures: fix source first, tests second; mirror the existing Feature-test style per domain.

**Step 2: Commit per fix:** `git add backend && git commit -m "fix: <domain>: <what failed and why>"`.

### Task 8: Backend — Filament exclusion + PHPStan level ambition

**Objective:** Decide explicitly whether `app/Filament/` stays excluded and whether level 5 → 6+ is viable; no silent tech debt.

**Files:**
- Read: `backend/phpstan.neon`, `backend/app/Filament/**`
- Modify only if upgrading: `backend/phpstan.neon`

**Step 1: Trial (no commit unless green)**

```bash
P="F:/Travel Website/bookly travel"
cd "$P/backend"
./vendor/bin/phpstan analyse app/Filament --level=5 --memory-limit=1G 2>&1 | tail -n 30
```

Expected: error list measuring the exclusion debt. Outcome A (small list): fix and remove the `excludePaths` entry. Outcome B (large): keep exclusion, file the error count in the gate report (Task 22) as accepted debt with rationale. Do not add new `ignoreErrors` without a comment linking the reason.

**Step 2: Commit only on Outcome A:** `git add backend && git commit -m "fix: include Filament in phpstan analysis"`.

### Task 9: Backend — PG suite parity (phpunit.pgsql.xml)

**Objective:** Prove the suite also passes on Postgres (prod is PG15 with JSONB; sqlite hides JSONB/collation differences).

**Files:** `backend/phpunit.pgsql.xml`, `docker/postgres/init-test-db.sh`.

**Step 1: Run against the compose postgres**

```bash
P="F:/Travel Website/bookly travel"
docker exec bookly-postgres pg_isready -U bookly
cd "$P/backend"
./vendor/bin/pest -c phpunit.pgsql.xml --colors=never 2>&1 | tail -n 25
```

Expected: green. If PG-only failures (JSONB, case sensitivity, sequences): fix migration/model code, never branch logic on `DB_CONNECTION` unless justified and tested on both.

**Step 2: Commit:** `git add backend && git commit -m "fix: pgsql suite parity"`.

### Task 10: Backend — migrations audit (54 files)

**Objective:** Fresh-installable, ordered, rerunnable migrations with no sqlite/PG drift.

**Files:** `backend/database/migrations/*`, esp. `2026_06_06_*_fix_tours_partner_id*`, `2026_06_20_*`, `2026_07_23_*_drop_unique_payments*`, `2026_08_*` (partner invitations, blog ×5).

**Step 1: Fresh migrate + seed check**

```bash
P="F:/Travel Website/bookly travel"
cd "$P/backend"
php artisan migrate:fresh --seed --env=testing 2>&1 | tail -n 15
php artisan migrate:status 2>&1 | tail -n 10
```

Expected: all 54 run, status `Ran`. Investigate any `fix_*`/`drop_*`/`backfill_*` migration that fails on a fresh DB (classic leftover-from-prod-patch smell) and repair it.

**Step 2: Commit:** `git add backend/database && git commit -m "fix: migrations fresh-installable"`.

### Task 11: Frontend — ESLint green

**Objective:** Zero lint errors.

**Step 1: Run**

```bash
P="F:/Travel Website/bookly travel"
cd "$P/frontend"
npm run lint 2>&1 | tail -n 30
```

Expected: errors concentrated around the 3 `eslint-disable no-explicit-any` partner pages (availability/edit/pricing). Remove the disables as part of Task 13; all other errors fix directly.

**Step 2: Commit:** `git add frontend && git commit -m "fix: eslint green"`.

### Task 12: Frontend — tsc + Jest green

**Objective:** `tsc --noEmit` clean and Jest suite green.

**Step 1: Run**

```bash
P="F:/Travel Website/bookly travel"
cd "$P/frontend"
npm run typecheck 2>&1 | tail -n 30
npm test -- --ci 2>&1 | tail -n 25
```

Expected: typecheck clean (`strict: true`); Jest green. Fix types before tests; add/extend unit tests for anything fixed in `src/**/__tests__/` mirroring existing RTL style.

**Step 2: Commit:** `git add frontend && git commit -m "fix: typecheck and jest green"`.

### Task 13: Frontend — eliminate `any` leakage (the 3 partner pages + catch sites)

**Objective:** No `eslint-disable no-explicit-any`, no `catch (err: any)`, no `Record<string, any>` payloads in touched code.

**Files:**
- `frontend/src/app/[locale]/(partner)/partner/tours/[id]/availability/page.tsx`
- `frontend/src/app/[locale]/(partner)/partner/tours/[id]/edit/page.tsx`
- `frontend/src/app/[locale]/(partner)/partner/tours/[id]/pricing/page.tsx`
- All `catch (err: any)` sites in `frontend/src/app/[locale]/(public)/blog/**`

**Step 1: Find them**

```bash
P="F:/Travel Website/bookly travel"
cd "$P/frontend"
grep -rn "eslint-disable @typescript-eslint/no-explicit-any" src | head
grep -rn "catch (.*: any)" src | head -n 20
grep -rn "Record<string, any>" src | head -n 20
```

**Step 2: Replace with this pattern** (copy-pasteable; adapt field names):

```tsx
// Before
} catch (err: any) {
  setError(err.message);
}
// After
} catch (err: unknown) {
  setError(err instanceof Error ? err.message : 'Something went wrong');
}
```

```tsx
// Before
const translationsPayload: Record<string, any> = { ... };
// After — type the payload explicitly, e.g.
type TranslationPayload = { locale: 'en' | 'es' | 'it'; title: string; description: string };
const translationsPayload: Record<TranslationPayload['locale'], TranslationPayload> = { ... };
```

**Step 3: Verify:** `npm run lint && npm run typecheck` both clean.

**Step 4: Commit:** `git add frontend && git commit -m "fix: type unsafe any in partner and blog pages"`.

### Task 14: Frontend — lock down `images.remotePatterns` + verify rewrites

**Objective:** Images load only from trusted hosts; `/api/*` rewrite behavior proven in dev and Docker.

**Files:**
- Modify: `frontend/next.config.ts`
- Read: `docker/nginx/nginx.conf`, `docker-compose.yml`

**Step 1: Replace the wildcard** (list actual hosts — CMS, storage, Stripe/CDN — after checking `ImageGallery.tsx`, `BlogDetail.tsx`, seeders):

```ts
// Before
images: { remotePatterns: [{ protocol: 'https', hostname: '**' }] },
// After (example — use the real host list found in code/seeds)
images: {
  remotePatterns: [
    { protocol: 'https', hostname: 'cdn.bookly.example' },
    { protocol: 'https', hostname: 'images.unsplash.com' },
  ],
},
```

**Step 2: Verify**

```bash
P="F:/Travel Website/bookly travel"
cd "$P/frontend"
npm run build 2>&1 | tail -n 10
```

Expected: build succeeds; remote images outside the allowlist now 400 (confirm one negative case in dev).

**Step 3: Commit:** `git add frontend/next.config.ts && git commit -m "fix: restrict next image remote patterns"`.

### Task 15: E2E + a11y — trustworthy smoke gate

**Objective:** A documented, repeatable Playwright smoke gate (incl. a11y project) that passes twice in a row.

**Files:** `frontend/playwright.config.ts`, `frontend/tests/e2e/smoke.spec.ts`.

**Step 1: Run smoke only**

```bash
P="F:/Travel Website/bookly travel"
cd "$P/frontend"
npx playwright test tests/e2e/smoke.spec.ts --reporter=line 2>&1 | tail -n 20
npx playwright test tests/e2e/smoke.spec.ts --reporter=line 2>&1 | tail -n 5
```

Expected: green twice (catches the flake history seen in recent `fix(e2e)` commits). If red: fix app code first; touch specs only for genuine selector/timing bugs, and record the reason. Do not weaken assertions to force green.

**Step 2: Then a11y smoke**

```bash
npx playwright test --project=a11y tests/e2e/a11y/homepage-a11y.spec.ts tests/e2e/a11y/search-a11y.spec.ts --reporter=line 2>&1 | tail -n 10
```

**Step 3: Commit:** `git add frontend && git commit -m "fix: stable e2e smoke and a11y gate"`.

### Task 16: TestSprite reconciliation (dirty suite)

**Objective:** The 5 modified + 10 new `testsprite_tests/TC*.py` are either adopted (green) or removed; the runner is portable.

**Files:**
- Review: `testsprite_tests/TC*.py`, `testsprite_tests/*.json`, `run_all_tests.py`

**Step 1: Inspect**

```bash
P="F:/Travel Website/bookly travel"
cd "$P"
git diff --stat -- testsprite_tests run_all_tests.py
git status --short -- testsprite_tests
```

**Step 2: Fix the runner portability smell** — `run_all_tests.py` hardcodes `C:\Users\HaTeM\...python.exe` and shells `docker exec ... cache:clear` between tests (masks rate-limit state). Minimal fix:

```python
# Before
py_bin = r"C:\Users\HaTeM\AppData\Local\Programs\Python\Python311\python.exe"
python_executable = py_bin if os.path.exists(py_bin) else sys.executable
# After
python_executable = sys.executable
```

Keep the `cache:clear` only if a test genuinely needs it; otherwise delete it and let rate-limit tests run against real state (a clearing runner makes brute-force/lockout tests meaningless — flag this in the report).

**Step 3: Adopt-or-delete:** run the new TC files one by one; keep green ones (commit), delete or quarantine red ones with a reason in the gate report. Do not commit `testsprite_tests/tmp/*` (already ignored in Task 4).

**Step 4: Commit:** `git add run_all_tests.py testsprite_tests && git commit -m "test: reconcile testsprite suite and portable runner"`.

### Task 17: Security pass — authZ, rate limits, webhooks, preview tokens

**Objective:** Close the highest-impact holes; prove each with a test or a negative check.

**Files (read then fix):**
- `backend/app/Http/Middleware/RoleMiddleware.php`, `RefreshTokenExpiry.php`, `RateLimitSearchMiddleware.php`
- `backend/app/Domains/Admin/Policies/*`, `backend/app/Policies/*`
- `backend/routes/api/*.php` (throttle shadowing was fixed before — re-verify)
- Payment webhook + ledger: `backend/app/Domains/Payment/**`
- Blog preview: `backend/app/Domains/Blog/Actions/GetBlogPostPreviewAction.php`, `PreviewTokenService.php`, frontend `blog/[slug]/preview/page.tsx` (`robots: noindex`, no cache, 30-min HMAC — verify each property)

**Step 1: Checklist (each gets a yes + evidence or a fix task):**
1. Role gates deny by default; admin/partner/traveler boundaries covered by `PartnerSuspendedAccessTest`-style tests.
2. Login brute-force lockout works WITHOUT the test runner clearing cache (see Task 16).
3. Search/blog throttles apply once (no route-group shadowing).
4. Stripe webhooks verify signatures; `drop_unique_payments_intent_add_composite` intent handling is idempotent.
5. Preview tokens are post-bound, expiring, `noindex`, uncached, absent from sitemap.

**Step 2: Verify:** relevant Pest tests green (`Auth/*`, `Booking/RateLimitTest`, `Blog/BlogThrottleTest`, `Blog/BlogPreviewTest`).

**Step 3: Commit per fix.**

### Task 18: Data/infra pass — compose, Meili, queue, scheduler, backups

**Objective:** `docker compose up` from clean gives a working app with search indexed and mail/queue flowing.

**Files:** `docker-compose.yml`, `docker/nginx/nginx.conf`, `backend/config/scout.php`, `backend/config/queue.php`, `docker/postgres/init-test-db.sh`.

**Step 1: Health**

```bash
P="F:/Travel Website/bookly travel"
docker compose --project-directory "$P" ps
docker exec bookly-postgres pg_isready -U bookly
docker exec bookly-redis redis-cli ping
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/api/health 2>&1 || curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/
```

**Step 2: Search + queue proof:** create/edit a tour, confirm it reaches Meilisearch (queue runs `default,scout`); confirm scheduler `schedule:work` and queue worker stay up after restart.

**Step 3: Backups:** confirm `postgres_data`/`redis_data` volumes + a documented dump command; if none exists, add the command to docs (Task 21), not a new backup system (YAGNI).

**Step 4: Commit only if a config changed.**

### Task 19: i18n + Sentry + headers verification

**Objective:** No missing locale keys, no silent error swallowing, sane security headers.

**Files:** `frontend/messages/en|es|it.json`, `frontend/sentry.*.config.ts`, `docker/nginx/nginx.conf`.

**Step 1: Key parity**

```bash
P="F:/Travel Website/bookly travel"
cd "$P/frontend"
node -e "const fs=require('fs');const e=JSON.parse(fs.readFileSync('messages/en.json','utf8'));for(const l of ['es','it']){const o=JSON.parse(fs.readFileSync('messages/'+l+'.json','utf8'));const ek=Object.keys(e),ok=Object.keys(o);console.log(l,'missing:',ek.filter(k=>!(k in o)).slice(0,20),'extra:',ok.filter(k=>!(k in e)).slice(0,20));}"
```

Expected: no missing keys (blog `partialTranslation` fallback was patched before — re-verify `blog.partialTranslation` exists in all three).

**Step 2: Sentry:** confirm DSN comes from env (not hardcoded), sourcemaps upload only in CI, AdBlock/offline doesn't break the app.

**Step 3: Headers:** confirm nginx sends `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` at minimum; note HSTS only if TLS terminates there.

**Step 4: Commit per fix.**

### Task 20: Performance sanity — Lighthouse + bundle

**Objective:** Know the number; fix only cheap wins (images, fonts, blocking JS).

**Step 1: Run (desktop, homepage + one tour page)**

```bash
P="F:/Travel Website/bookly travel"
cd "$P/frontend"
npm run build 2>&1 | tail -n 5
npx lhci autorun 2>&1 | tail -n 20
```

Expected: report written; record performance/a11y/SEO scores in the gate report. Fix only `next/image` sizing, `plaiceholder` blur, and font-display issues; no redesign in this plan.

### Task 21: Docs refresh (stale docs are bugs)

**Objective:** Docs describe the code as it is today.

**Files:**
- Regenerate/refresh: `AGENTS.md` (claims 2026-08-22; code moved since), `docs/test-coverage-gap-analysis.md` (claims 107 backend files; now 329), `docs/implementation-plan.md`, `docs/frontend-implementation-plan.md`
- Keep: `docs/PRD.md`, `docs/adr/*`, `CONTEXT.md`

**Step 1: Update counts + tech facts** (Laravel 11, Next 16.2.3, React 19.2, 329/104/54 numbers, PG15/Redis7/Meili, three locales, Sentry, Stripe).
**Step 2: Mark superseded reports** (`phase-validation-*`, `partner-id-cascade-*`) with a one-line header: superseded-by + date, don't rewrite history.
**Step 3: Commit:** `git add AGENTS.md docs CONTEXT.md && git commit -m "docs: refresh audit-era docs and coverage analysis"`.

### Task 22: Final regression gate + report

**Objective:** One command sequence, one report, zero surprises.

**Step 1: Run the full gate in order**

```bash
P="F:/Travel Website/bookly travel"
cd "$P/backend" && ./vendor/bin/pint --test 2>&1 | tail -n 2
./vendor/bin/phpstan analyse --memory-limit=1G --no-progress 2>&1 | tail -n 3
./vendor/bin/pest --colors=never 2>&1 | tail -n 5
cd "$P/frontend" && npm run lint 2>&1 | tail -n 3
npm run typecheck 2>&1 | tail -n 3
npm test -- --ci 2>&1 | tail -n 5
npx playwright test tests/e2e/smoke.spec.ts --reporter=line 2>&1 | tail -n 5
cd "$P" && git status --short
```

Expected: all green, `git status --short` shows only the intended report file.

**Step 2: Write `docs/final-regression-audit-gate.md`** (new entry, don't overwrite the old one): date, commit SHA, gate table (command → result → evidence), accepted-debt list (Filament exclusion, PHPStan level, any deferred PG-only quirk, any quarantined TestSprite TC), and the next recommended milestone.

**Step 3: Commit:** `git add docs && git commit -m "docs: final regression gate after health audit"`.

---

## Files likely to change

- `.gitignore` (Task 4)
- `backend/**` — format/type/test fixes (Tasks 5–10, 17); possibly `phpstan.neon` (Task 8)
- `frontend/next.config.ts`, partner pages, blog pages, API clients (Tasks 11–15, 19–20)
- `run_all_tests.py`, `testsprite_tests/TC*.py` (Task 16)
- `docker-compose.yml`, `docker/nginx/nginx.conf` (Tasks 14, 18–19)
- `AGENTS.md`, `docs/*`, `docs/final-regression-audit-gate.md` (Tasks 21–22)

## Tests / validation

| Gate | Command (from repo root `$P`) | Expected |
|------|-------------------------------|----------|
| Pint | `cd backend && ./vendor/bin/pint --test` | no diffs |
| PHPStan | `cd backend && ./vendor/bin/phpstan analyse --memory-limit=1G` | No errors |
| Pest sqlite | `cd backend && ./vendor/bin/pest` | all passed |
| Pest pgsql | `cd backend && ./vendor/bin/pest -c phpunit.pgsql.xml` | all passed |
| ESLint | `cd frontend && npm run lint` | no errors |
| Typecheck | `cd frontend && npm run typecheck` | clean |
| Jest | `cd frontend && npm test -- --ci` | all passed |
| Playwright smoke | `cd frontend && npx playwright test tests/e2e/smoke.spec.ts` | green ×2 |
| A11y sample | `cd frontend && npx playwright test --project=a11y tests/e2e/a11y/homepage-a11y.spec.ts tests/e2e/a11y/search-a11y.spec.ts` | green |
| Git clean | `git status --short` | only intended files |

## Risks, tradeoffs, and open questions

- **PG vs sqlite drift (real):** prod uses PG JSONB-localized columns; default suite runs sqlite. Task 9 is non-negotiable; if PG suite can't run locally, run it in CI/Docker and record where.
- **`cache:clear` in the TestSprite runner** may be hiding broken rate-limit/brute-force behavior. Removing it (Task 16) could turn green E2E red — that's a find, not a regression.
- **PHPStan level 5 + Filament exclusion** is modest for a payments marketplace. Raising the level or including Filament may surface dozens of errors; Task 8 scopes that explicitly instead of letting it balloon.
- **`images.hostname: '**'`** may be load-bearing for partner-uploaded external URLs. Task 14 needs the real host list from seeds/uploads before locking down, or images break.
- **E2E flakes** have a history (`fix(e2e)` commits). Task 15 demands green-twice and forbids weakening assertions.
- **Stale docs** (`AGENTS.md`, gap analysis) will mislead the next agent if Task 21 is skipped — don't skip it.
- Open questions for the owner: (1) Is `http://localhost:8080` + nginx the canonical local entrypoint everyone uses? (2) Which image hosts are legitimate for tours/blog? (3) Should TestSprite `TC*.py` become the release gate, or stay advisory behind Pest + Playwright?
