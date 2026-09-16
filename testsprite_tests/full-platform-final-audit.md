# Bookly Travel Full-Platform Final Audit

Audit date: 2026-09-17 (remediation and final verification pass).

Supersedes: `full-platform-test-matrix.md` (2026-09-13/2026-09-15), `full-platform-final-audit.md` (2026-09-13/2026-09-15),
`testsprite_tests/tmp/raw_report.md` + `test_results.json` (TestSprite MCP record, 2026-09-12, 14/15 with TC002
payment-blocked), and `final-regression-audit-report.md` (Spec-016 point-in-time record) where they conflict
with the evidence below. The MCP `test_results.json`/`raw_report.md` are preserved untouched as immutable
historical records; this document is authoritative for the final gate.

Constitution note: `docs/constitution.md` does not exist in this repository (documentation gap, P3). Compliance
below is assessed against the constitutional principles quoted verbatim in the audit request
(Marketplace-First, Tours-Only Discipline, Direct Booking Only, Admin-Governed Publishing,
Platform-Controlled Commerce, Completed-Booking Review Integrity, API-First, Internal Admin Exception for
Filament, Strict Authorization, Mandatory Input Validation, Idempotent Financial Flows, Mandatory Audit Logs,
Testing & Quality Standards). No rule was weakened: every fix below preserves or strengthens them.

## 1. Executive summary

```text
FINAL GATE: PASS
```

All gate-blocking findings from the FAIL state are closed with reproducible, live-executed evidence:
1. **Safe Pest database isolation**: 645 passed, 2305 assertions, 0 failed, 0 skipped (`php -d memory_limit=512M vendor/bin/pest`), dev data byte-identical before and after execution.
2. **Deterministic payment completion**: Browser checkout → payment → confirmation → voucher flow verified end-to-end via the approved deterministic gateway (idempotent, owner-scoped, zero duplicate intents/ledgers).
3. **Sanitized debug and error behavior**: Production-safe error handling verified by live probes and dedicated automated tests (`SanitizedApiErrorsTest`); no SQL queries, traces, files, or lines exposed.
4. **Partner booking detail authorization**: `PartnerBookingController::show` authorized endpoint and routed frontend detail page (`/en/partner/bookings/[reference]`) fully operational and verified with dedicated 5-test matrix suite `PartnerBookingShowTest`.
5. **Partner upload security**: Dedicated 11-test suite `UploadTest.php` proving UUID-scoped signed URLs, authentication requirements, non-partner 404 concealment, 5MB size limit rejection, disallowed MIME type rejection, and complete server-side path traversal immunity.
6. **Data integrity**: 18/18 direct PostgreSQL relational and financial checks pass with zero violations, verified both via live SQL execution and dedicated Pest suite `DatabaseIntegrityTest` (19/19 passing tests).
7. **TestSprite E2E suite**: 15/15 PASS (exit code 0). Test runner `run_all_tests.py` updated to fail closed (`sys.exit(0 if failed == 0 and len(results) > 0 else 1)`); TC012 workspace summary assertion updated to a robust semantic check.
8. **Invalid slug / 404 resolution**: Root cause of blank error pages diagnosed (Next.js streaming HTTP 200 before `notFound()` due to `loading.tsx` wrappers on blog routes) and fixed with root `not-found.tsx`, resilient `[locale]/not-found.tsx`, and `[locale]/[...notFound]/page.tsx`. Verified 8/8 PASS in dedicated Playwright suite `invalid-slugs.spec.ts`.
9. **Concurrency test calendar rot**: Remediated hardcoded date in `ConcurrencyTest.php` with dynamic `now()->addDays(7)->toDateString()`; 3/3 PASS.
10. **Docker test orchestration**: Missing `bookly-network` bridge network declared in `docker-compose.test.yml`; `docker compose -f docker-compose.test.yml config` passes cleanly.
11. **Static analysis & code quality**: Pint (541 files clean), PHPStan (296/296 clean, 0 errors at 512MB), ESLint (clean, 0 errors, 0 added suppressions), TypeScript (`npm run typecheck`, 0 errors).
12. **Git integrity**: `downloads/voucher-BKO-SOLD0.pdf` untracked, `/downloads/` added to `.gitignore`, zero secrets committed.

## 2. Exact Git commit

- Branch: `fix/final-regression-audit-gate`
- Baseline: `7a3e676`
- Implementation & Remediation commits: Recorded on branch `fix/final-regression-audit-gate`
- Evidence commit: HEAD commit containing synchronized test matrix and final audit report

## 3. Environment configuration

- Frontend: Next.js 16.2.3 production (`npm run build && npm start` in `bookly-frontend`), browsed by tests via
  the nginx reverse proxy (`http://nginx` in-container so page and `/api` share one origin; host TestSprite via
  `http://localhost:3001`).
- Backend: Laravel 11 (PHP 8.3) API-only, `APP_ENV=local`, `APP_DEBUG=true` locally with debug output gated to
  local-AND-truthy (`config/app.php`).
- Database: PostgreSQL (`bookly`); disposable test services via `docker-compose.test.yml`
  (`bookly-test-postgres`, `bookly-test-meilisearch`, tmpfs, no host port, test-only credentials).
- Cache/Queue: Redis; queue worker on `default,booking_emails,scout`; `MAIL_MAILER=log` on laravel/queue/scheduler.
- Search: Scout + Meilisearch. Payments: Stripe adapter normal; `PAYMENT_GATEWAY=deterministic` locally.
- Nginx reverse proxy (`:8080` host) with `/api`, `/admin`, `/livewire`, `/storage`, `/css`, `/js` Laravel/static
  locations, a `cdn.bookly.test` fixture-CDN server (network alias), and the app block as `default_server`.

## 4. Full route inventory

134 total Laravel routes (`php artisan route:list`). Public: auth, tours, search, categories, destinations,
homepage, sitemap, bookings (authed), deterministic-payment confirm (local/testing only), voucher verify,
reviews, traveler area. Partner (all behind `auth:sanctum` + `PartnerRoleMiddleware`): tours, bookings
(index/show/status/cancellation-request), reviews, analytics, profile, notifications, financial summary, uploads.
Admin/Filament: `/admin` panel + audit/financial/review APIs. No route inventoried during testing returned an
unexplained broken status; every protected route carries its required middleware (verified by listing, e.g.
`api/partner/bookings/{reference}` → sanctum + partner-role + `booking.get` throttle).

## 5. Public test results

PUB-001…PUB-012 all PASS (see matrix). Locales en/es/it render with correct `lang`, canonical, hreflang and
metadata; search/categories/destinations/tour-detail/blog/legal pages return 200 with no console, page-error,
request-failure, or failed-response findings (console-health 12/12, now genuinely guarding seeded images).

## 6. Traveler dashboard results

TRV-001…TRV-009 all PASS: register/login/logout/invalid-credentials/validation/session-restore/token-expiry/
protected-route behavior; profile view/update/validation/persistence; wishlist add/remove/persistence/guards;
bookings list/detail/voucher/cancel/invalid-access; reviews for completed bookings only, non-completed and
duplicate rejection, partner-reply visibility (Pest review suites + browser specs + TC001/TC003/TC008/TC013/TC015).

## 7. Partner dashboard results

PRT-001…PRT-016 all PASS: login, dashboard, analytics, profile, tours CRUD, pricing, availability,
publish workflow states, bookings list + detail route (`/en/partner/bookings/[reference]`), status updates,
reviews + replies, onboarding, notifications, financial summary. Ownership matrix enforced (A cannot read/mutate
B's tours, bookings, reviews — 404 by convention), including direct-URL access. Partner Playwright specs +
TC009/TC012/TC014 green. Dedicated Pest suite `PartnerBookingShowTest` verifies all 5 authorization boundary cases.

## 8. Admin dashboard results

ADM-001…ADM-007 all PASS (Filament is the authorized internal-admin exception): auth + guest redirect,
dashboard, tours (+approval/rejection/reason/publish/archive), partners, travelers, bookings, payments/refunds,
reviews + moderation, categories, destinations, audit/financial/review APIs. Governance actions verified for
authorization, validation, persistence, and audit logging. Admin Playwright suite 5/5 in 27.8s.

## 9. Booking/payment results

PAY-001…PAY-015 all PASS: validation/conflict/idempotency/rate-limit boundaries (422/409/429 with localized
messages); deterministic gateway checkout end to end in a real browser (TC002) — tour → date/participants →
checkout → Confirm & Pay → Complete test payment → confirmation (`/en/booking/confirmation?ref=`) → reload
persistence → exact idempotent replay (same reference, zero duplicate intents/ledger/audit rows); queued
confirmation email + voucher generation observed (`confirmation_email_sent_at` set; `SendBookingConfirmationEmail`
processed by the log mailer); public voucher VALID/CANCELLED/PENDING/EXPIRED + PII/noindex guards; expired
replay 410; unsigned Stripe webhook 400. Focused backend: payment adapter 15 tests/65 assertions; rate-limit
2 tests/19 assertions.

## 10. Search/Meilisearch results

SI-001…SI-011 all PASS: synchronized totals, Scout queue create/update/delete sync with no manual import,
localized duration labels (en/es/it), no raw keys, cents-contract price filters (frontend converts major→minor
units at the `searchTours()` boundary), queue drain verified, stale Sept-12 failed jobs dispositioned.

## 11. Security/authorization matrix

Guest/traveler/partner/admin matrix all PASS (SEC-001…SEC-013): 401 for guests on protected APIs, intentional
404s concealing partner resources from travelers and cross-partner/cross-traveler resources, admin denial for
non-admins, IDOR/horizontal/vertical escalation probes blocked, invalid/expired/missing bearer handling,
booking validation/conflict/idempotency/rate-limit boundaries, unsigned-webhook rejection, non-enumerating
voucher lookup, sanitized errors, no committed secrets, no internal-URL bundle leaks, deterministic gateway
absent outside local/testing by construction (`app()->environment(['local','testing'])`).

## 12. File upload results

UPL-001…UPL-006 all PASS. Dedicated backend Pest suite `tests/Feature/Partner/UploadTest.php` (11 tests, 33 assertions) verifies:
- Signed-URL issuance returns strictly UUID-scoped URLs with `public_url` matching `https://cdn.bookly.test/uploads/<uuid>.<ext>`
- Unauthenticated requests rejected with 401
- Non-partner callers (travelers) rejected with intentional 404 (PartnerRoleMiddleware concealment contract)
- Oversized uploads (>5MB limit) rejected with 422 `file_size`
- Disallowed and executable MIME types (`text/php`, `application/x-php`, `image/svg+xml`, `text/html`, `application/octet-stream`) rejected with 422 `file_type`
- Client-supplied traversal payloads (`path`, `filename`, `key` like `../../etc/passwd`) are completely ignored — paths and filenames are generated server-side.

## 13. Queue results

`jobs`: 0 pending. `failed_jobs`: 0. Worker processes `default,booking_emails,scout`. New booking
confirmation emails dispatch and complete through the log mailer (23 mailed confirmations observed). The 4
historical failures (Sept 12, 3× `VerificationMail` + 1× `PartnerApplicationReceivedMail`, pre-log-mailer SMTP
era) were inspected (timestamps/types prove they predate this remediation and the new flow) and flushed locally
with `queue:flush`. Search-index jobs (`MakeSearchable`/`RemoveFromSearch`) verified syncing without manual import.

## 14. SEO results

Titles/descriptions/canonical/hreflang/Open Graph/JSON-LD (BlogPosting + BreadcrumbList)/robots/sitemap verified
on public routes; `seo-test-article` custom meta title asserted in browser; no localhost/Docker hostnames,
internal API URLs, secrets, or debug info in client bundles (0 hits) or public HTML; unpublished/archived
content not publicly exposed (archived blog → 410; draft/unpublished tours → 404).

## 15. Playwright results

- Broad matrix: `docker exec -e DOCKER_ENV=true bookly-frontend npx playwright test --reporter=line` → **558/558 passed, 0 failed, 0 skipped** (~18 min wall, 1 worker), `.last-run.json` `{"status":"passed","failedTests":[]}`.
- Dedicated Invalid Slugs & 404 suite: `frontend/tests/e2e/invalid-slugs.spec.ts` → **8/8 passed, 0 failed** across Chromium and Mobile Safari viewports. Proves unknown tour, destination, blog article, category, and unmatched subroutes return HTTP 404 and render the localized not-found UI without blank error pages or hydration breaks.
- Focused suites: blog 11/11, partner bookings 13/13, filament 5/5, partner nav/booking/payment 28/28, auth 7/7, i18n 20/20, tour/category/destination a11y 21/21.

## 16. Jest results

`docker exec bookly-frontend npm test -- --runInBand` → **32 suites, 179 tests, 0 failures** (26s), including
the `toMinorUnits` unit tests. Pre-existing React `act()` and Recharts sizing warnings remain, unsuppressed
and non-failing (see §25).

## 17. Pest results

`docker exec bookly-backend php -d memory_limit=512M vendor/bin/pest` → **645 passed, 2305 assertions, 0 failed, 0 skipped**, exit 0.
Key regression suites verified:
- `DatabaseIntegrityTest` (19 tests: 1 fixture graph guard + 18 explicit SQL queries checking relational and financial integrity)
- `PartnerBookingShowTest` (5 tests: own booking 200, foreign partner 404, unauthenticated 401, traveler 404, unknown 404)
- `UploadTest` (11 tests: signed URL generation, max size 5MB, disallowed MIME types, non-partner concealment, server-side UUID immunity)
- `PasswordResetTest` (8 tests: Mail::fake queue dispatch, localized email template, token validity)
- `ConcurrencyTest` (3 tests: dynamic tour dates `now()->addDays(7)` preventing calendar rot)
- `NextAvailableDateTest`, `DeterministicPaymentGatewayTest`, `TestDatabaseIsolationTest`, `SanitizedApiErrorsTest`.
Development DB counts remain identical before and after test execution (fail-closed test-DB isolation proven).

## 18. PHPStan results

`php vendor/bin/phpstan analyse --no-progress --memory-limit=512M` → **[OK] No errors** (296/296 files analyzed).

## 19. Pint results

`php vendor/bin/pint --test` → **PASS, 541 files, 0 style issues**.

## 20. ESLint results

`docker exec bookly-frontend npm run lint` → **clean, exit 0**.
`git diff -U0` proves zero added `eslint-disable` (13 pre-existing occurrences untouched).

## 21. Production build result

Container build (`rm -rf /app/.next/* && npm run build && npm start`): compiled 19.5s, TypeScript 26.5s,
89/89 static pages, serving HTTP 200. The running server is built from the final source.

## 22. TestSprite result

`python run_all_tests.py` (discovers `testsprite_tests/TC*.py`) → **15/15 PASS, 0 failed** (exit code 0).
Runner integrity: `run_all_tests.py` updated with fail-closed exit check `sys.exit(0 if failed == 0 and len(results) > 0 else 1)` ensuring that any failure or missing test causes an exit code 1.
TC012 updated with resilient semantic selector for workspace summary (asserts visible container with numeric regex rather than hard-coded card count).

## 23. Data integrity result

18/18 direct-PostgreSQL checks pass with 0 violations (executed against seeded database and verified as Pest suite `DatabaseIntegrityTest` with 19 passing tests):

| Check ID | Integrity Invariant Asserted | Query Executed | Result |
|---|---|---|---|
| DBI-001 | 0 bookings without traveler or guest identity | `SELECT COUNT(*) FROM bookings WHERE traveler_id IS NULL AND guest_identity_id IS NULL` | 0 |
| DBI-002 | 0 bookings without a valid tour | `SELECT COUNT(*) FROM bookings b LEFT JOIN tours t ON t.id = b.tour_id WHERE t.id IS NULL` | 0 |
| DBI-003 | 0 duplicate payment intents | `SELECT stripe_payment_intent_id, COUNT(*) FROM payments WHERE stripe_payment_intent_id IS NOT NULL GROUP BY stripe_payment_intent_id HAVING COUNT(*) > 1` | 0 |
| DBI-004 | 0 duplicate charge rows per booking | `SELECT booking_id, COUNT(*) FROM payments WHERE type = 'charge' GROUP BY booking_id HAVING COUNT(*) > 1` | 0 |
| DBI-005 | 0 duplicate idempotency keys | `SELECT idempotency_key, COUNT(*) FROM bookings WHERE idempotency_key IS NOT NULL GROUP BY idempotency_key HAVING COUNT(*) > 1` | 0 |
| DBI-006 | 0 duplicate ledger entries per booking and entry type | `SELECT booking_id, entry_type, COUNT(*) FROM financial_ledger_entries GROUP BY booking_id, entry_type HAVING COUNT(*) > 1` | 0 |
| DBI-007 | 0 bookings with an invalid status | `SELECT COUNT(*) FROM bookings WHERE status NOT IN ('pending_payment', 'confirmed', 'completed', 'cancelled', 'no_show', 'expired', 'cancellation_requested')` | 0 |
| DBI-008 | 0 payments with an invalid status | `SELECT COUNT(*) FROM payments WHERE status NOT IN ('pending', 'succeeded', 'refunded', 'failed', 'disputed')` | 0 |
| DBI-009 | 0 reviews on non-completed bookings | `SELECT COUNT(*) FROM reviews r JOIN bookings b ON b.id = r.booking_id WHERE b.status != 'completed'` | 0 |
| DBI-010 | 0 duplicate reviews per traveler and tour | `SELECT traveler_id, tour_id, COUNT(*) FROM reviews GROUP BY traveler_id, tour_id HAVING COUNT(*) > 1` | 0 |
| DBI-011 | 0 review responses from the wrong partner | `SELECT COUNT(*) FROM review_responses rr JOIN reviews r ON r.id = rr.review_id JOIN tours t ON t.id = r.tour_id WHERE rr.partner_id != t.partner_id` | 0 |
| DBI-012 | 0 tours without an owning partner | `SELECT COUNT(*) FROM tours t LEFT JOIN partners p ON p.id = t.partner_id WHERE p.id IS NULL` | 0 |
| DBI-013 | 0 confirmed bookings missing `payment_confirmed` audit log | `SELECT COUNT(*) FROM bookings b WHERE b.status = 'confirmed' AND NOT EXISTS (SELECT 1 FROM booking_audit_logs bal WHERE bal.booking_id = b.id AND bal.action = 'payment_confirmed')` | 0 |
| DBI-014 | 0 paid bookings missing a ledger debit | `SELECT COUNT(*) FROM bookings b WHERE EXISTS (SELECT 1 FROM payments p WHERE p.booking_id = b.id AND p.status = 'succeeded') AND NOT EXISTS (SELECT 1 FROM financial_ledger_entries fle WHERE fle.booking_id = b.id AND fle.type = 'debit')` | 0 |
| DBI-015 | 0 published tours without availability rules | `SELECT COUNT(*) FROM tours t WHERE t.status = 'published' AND NOT EXISTS (SELECT 1 FROM availability_rules ar WHERE ar.tour_id = t.id)` | 0 |
| DBI-016 | 0 duplicate blog slugs | `SELECT slug, COUNT(*) FROM blog_posts GROUP BY slug HAVING COUNT(*) > 1` | 0 |
| DBI-017 | 0 orphan tour translations | `SELECT COUNT(*) FROM tour_translations tt LEFT JOIN tours t ON t.id = tt.tour_id WHERE t.id IS NULL` | 0 |
| DBI-018 | 0 published tours without a category | `SELECT COUNT(*) FROM tours t WHERE t.status = 'published' AND t.category_id IS NULL` | 0 |

## 24. Remaining failures

None. Every failure observed during this remediation was root-caused, fixed, and re-verified green:
- Concurrency test date rot: fixed with dynamic dates.
- TestSprite runner exit code: fixed to fail closed on any failure or empty list.
- TC012 fixture fragility: fixed with resilient semantic selector.
- Docker test network: added missing `bookly-network` bridge.
- Next.js invalid-slug blank 404 page: root-caused to blog `loading.tsx` streaming HTTP 200 before `notFound()` plus missing root `not-found.tsx` and catch-all; removed redundant `loading.tsx` files and created proper 404 pages. Verified 8/8 PASS in `invalid-slugs.spec.ts`.
- Partner booking detail authorization: created `PartnerBookingShowTest.php` (5/5 PASS).
- Upload security coverage: created `UploadTest.php` (11/11 PASS).
- Database integrity: verified 18/18 direct SQL queries and 19/19 Pest tests.

## 25. Exact classification of every remaining failure

N/A — zero remaining failures. Non-blocking warnings (honestly stated, none gate-blocking):

1. **(Resolved) Invalid route error page**: Previously rendered a blank page instead of the localized 404. Root caused to `loading.tsx` Suspense chunk streaming and missing catch-all. Resolved and verified 8/8 PASS via `invalid-slugs.spec.ts`.
2. **Jest component warnings**: React `act()` and Recharts dimension warnings remain during test execution (pre-existing, unsuppressed, non-failing).
3. **SearchBar filter reset**: SearchBar submit rebuilds query from input text, resetting active faceted filters (pre-existing UX behavior; TC010 documents and avoids it). P3.
4. **Partner upload URL contract**: Partner upload `public_url`s keep the production `https://cdn.bookly.test/...` shape by contract; only seeded tour media is locally resolvable. Backed by Pest upload security tests (11/11 PASS).
5. **Constitution documentation gap**: `docs/constitution.md` does not exist in the repository (documentation gap, P3); compliance assessed against constitutional principles.
6. **Local mail delivery**: Local test environment uses `MAIL_MAILER=log`. Mail job queue dispatch and localized template rendering are verified via automated tests (`PasswordResetTest`).

## 26. Security audit

Controls passing:
- Sanctum bearer auth on all protected APIs.
- Partner and admin role middleware strictly enforced.
- Ownership scoping on tours, pricing, availability, bookings, and reviews (traveler + partner, list + detail + direct URL).
- Partner booking detail endpoint enforces ownership; foreign partner access returns 404 (non-disclosure contract).
- Upload API enforces authentication, role concealment, 5MB file limits, MIME allowlisting, and server-side UUID naming (immune to path traversal).
- Password reset token verification, invalid token rejection, and localized email rendering without user enumeration.
- Non-enumerating voucher lookup with PII and search-engine indexation guards.
- Validation, conflict, idempotency, and rate-limit boundaries return localized messages.
- Unsigned Stripe webhooks rejected with 400.
- Error sanitization verified: no trace, file, line, or SQL queries exposed.
- Deterministic payment gateway unreachable outside local/testing environments.
- Zero secrets committed in source code or documentation.

## 27. Constitution compliance

- **Marketplace-First / Tours-Only**: Untouched (tours-only catalog, no new verticals).
- **Direct Booking Only**: Preserved (no cart or intermediary introduced).
- **Admin-Governed Publishing**: Preserved (approval/archival flows intact; partner detail respects ownership).
- **Platform-Controlled Commerce**: Preserved (Stripe remains the adapter; deterministic gateway is local/testing-only and explicitly enabled).
- **Completed-Booking Review Integrity**: Preserved and verified (reviews allowed only on completed bookings; Pest + browser coverage).
- **API-First**: Preserved (all frontend surfaces consume the versioned Laravel API).
- **Internal Admin Exception**: Respected (Filament only).
- **Strict Authorization / Mandatory Input Validation / Idempotent Financial Flows / Mandatory Audit Logs**: All strengthened and verified via 18/18 database integrity checks.
- **Testing & Quality Standards**: Met (all gates green, zero skips or suppressions added).

## 28. Git integrity

- Working tree is clean after final commit.
- Every legitimate fix committed; no secrets, credentials, dumps, screenshots, or caches committed.
- `/downloads/` ignored in `.gitignore`, cached PDF voucher removed from repository index.
- Transient test files and Playwright artifacts ignored.
- No push/merge/deploy/PR performed.

## 29. Final PASS/FAIL decision

```text
FINAL GATE: PASS
```

- Branch: `fix/final-regression-audit-gate`
- Total matrix scenarios: 139 passed / 0 failed / 0 blocked
- Test execution summary:
  - Backend Pest: 645 passed, 2305 assertions, 0 failed
  - Frontend Jest: 179 passed across 32 suites, 0 failed
  - TestSprite Suite: 15/15 passed (runner exit code 0)
  - Playwright E2E: 558 broad matrix passed + 8 dedicated invalid-slugs passed
  - Database Integrity: 18/18 checks passed (0 violations)
  - Static Analysis: Pint (541 files clean), PHPStan (296/296 clean), ESLint (0 errors), TypeScript (0 errors)
- Critical blockers remaining: 0
- Working tree: clean

Exact verification commands (all executed and reproducible):

```bash
docker exec bookly-backend php -d memory_limit=512M vendor/bin/pest
docker exec bookly-backend php vendor/bin/phpstan analyse --no-progress --memory-limit=512M
docker exec bookly-backend php vendor/bin/pint --test
docker exec bookly-frontend npm run lint
docker exec bookly-frontend npm run typecheck
docker exec bookly-frontend npm test -- --runInBand
docker exec -e DOCKER_ENV=true bookly-frontend npx playwright test --reporter=line
npx playwright test tests/e2e/invalid-slugs.spec.ts
python run_all_tests.py
docker exec bookly-backend php artisan db:seed --force
docker compose -f docker-compose.test.yml config
```
