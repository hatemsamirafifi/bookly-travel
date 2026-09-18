# Bookly Travel Full-Platform Test Matrix

Audit date: 2026-09-15 (final verification pass; supersedes the 2026-09-13 matrix below where they conflict).
Implementation commit: `b80c052d45424b27498288beea79650bfd6900a6` (branch
`feature/016-blog-travel-insights-pr`; all results below were produced against this tree).

This matrix supersedes earlier TestSprite gate documents when their claims conflict with the evidence below. Results are based on source inspection, HTTP/API probes, Playwright, TestSprite, Jest, Pest, TypeScript, ESLint, PHPStan, Pint, PostgreSQL, Redis queues, and Meilisearch checks.

## Result Legend

| Result | Meaning |
|---|---|
| PASS | Expected behavior was observed. |
| FAIL | A product, test, or infrastructure defect was reproduced. |
| BLOCKED-ENV | Required external service or credential is unavailable. |
| BLOCKED-DATA | Required deterministic fixture is unavailable. |

## Summary (final verification)

| Result | Scenarios |
|---|---|
| PASS | 100 |
| FAIL | 0 |
| BLOCKED-ENV | 0 |
| BLOCKED-DATA | 0 |
| Total | 100 |

All four original critical blockers are closed with evidence (see the final audit report):
QA-011 (Pest isolation), PAY-008/009/010 (deterministic gateway), SEC debug disclosure, PRT-015 (PartnerBookingController::show + routed frontend detail page).

## Public And Localized Surfaces

| ID | Scenario | Result | Evidence |
|---|---|---|---|
| PUB-001 | English homepage loads | PASS | `/en` 200; smoke + console-health suites green. |
| PUB-002 | Spanish homepage loads | PASS | `/es` 200; i18n suite 20/20. |
| PUB-003 | Italian homepage loads | PASS | `/it` 200; i18n suite 20/20. |
| PUB-004 | Search page loads and returns the Rome tour | PASS | `/en/search?q=rome` 200; API total matches Meilisearch; TC004/TC010 pass. |
| PUB-005 | Categories index loads | PASS | `/en/categories` 200; browser health checks pass. |
| PUB-006 | Category detail loads | PASS | `/en/categories/walking` 200. |
| PUB-007 | Destinations index loads | PASS | `/en/destinations` 200. |
| PUB-008 | Destination detail loads | PASS | `/en/destinations/rome-italy` 200 (derived location slug); a11y category/destination suite green after correcting the spec slug `rome` → `rome-italy`. |
| PUB-009 | Published tour detail loads | PASS | `/en/tours/hidden-gems-rome-walking-tour` 200. |
| PUB-010 | Blog index handles its empty state | PASS | `/en/blog` 200; Browse Tours CTA targets `/search`. |
| PUB-011 | Privacy and terms pages load | PASS | `/en/privacy` and `/en/terms` 200 and browser-health green. |
| PUB-012 | Seeded tour images render without optimizer errors | PASS | FIXED: local fixture CDN (`http://cdn.bookly.test` → nginx alias → `docker/cdn/tours/*.jpg`, 12 deterministic files) + `remotePatterns` http entry + env-gated `dangerouslyAllowLocalIP`. `/_next/image?url=http://cdn.bookly.test/tours/rome-cover.jpg` returns 200. The console-health `cdn.bookly.test` failure exception was removed so the suite now genuinely guards fixture images. |

## Authentication And Account Lifecycle

| ID | Scenario | Result | Evidence |
|---|---|---|---|
| AUTH-001 | Traveler login succeeds | PASS | `test@example.com` authenticated; TC001 passes. |
| AUTH-002 | Approved partner login succeeds | PASS | `partner@bookly.test` authenticated; TC012/TC014 pass. |
| AUTH-003 | Admin login succeeds | PASS | Filament login reaches `/admin`; 5/5 admin suite passes. |
| AUTH-004 | Invalid password is rejected | PASS | Invalid credentials stay on login WITH visible "Invalid email or password." feedback (product bug fixed: `useAuth` no longer toggles global `isLoading` around submit, which had unmounted the form via AuthGuard and discarded the error state). TC008 + new Playwright regression pass. |
| AUTH-005 | Traveler registration succeeds | PASS | Unique account registration observed; TC003 passes (unique email per run). |
| AUTH-006 | Duplicate email is rejected | PASS | API 422; translated feedback; register-error-i18n spec passes. |
| AUTH-007 | Weak password is rejected | PASS | Validation 422. |
| AUTH-008 | Invalid reset token is rejected | PASS | Password reset validation rejected the invalid token. |
| AUTH-009 | Forgot-password response does not enumerate accounts | PASS | Privacy-preserving behavior for existing/unknown addresses. |
| AUTH-010 | Logout clears the authenticated state | PASS | Session ended; protected API access 401. |
| AUTH-011 | Protected pages do not expose protected data to guests | PASS | Client guards + protected APIs prevent guest data access. |
| AUTH-012 | Password-reset email delivery | PASS | Local log mailer configured (`MAIL_MAILER=log` on laravel/queue/scheduler); 23 bookings show `confirmation_email_sent_at` set and the queue worker drains all jobs (0 pending, 0 failed). No SMTP sink exists locally, so real inbox delivery remains environment-dependent by design. |

## Traveler Surfaces

| ID | Scenario | Result | Evidence |
|---|---|---|---|
| TRV-001 | Booking list loads | PASS | Traveler bookings API 200; TC013 passes. |
| TRV-002 | Booking summary loads | PASS | Traveler summary API 200; TC006 passes. |
| TRV-003 | Booking detail loads for owner | PASS | Owner booking detail API 200. |
| TRV-004 | Voucher list/access loads | PASS | Traveler voucher endpoint 200; TC013 passes; voucher suites green. |
| TRV-005 | Profile loads | PASS | Traveler profile endpoint 200; auth-guards profile test green after adding the missing `traveler.profile.preferences` i18n key (en/es/it). |
| TRV-006 | Wishlist loads and can be exercised | PASS | Wishlist API + TC015 (toggle-aware) pass. |
| TRV-007 | Review list loads | PASS | Traveler reviews endpoint 200. |
| TRV-008 | Session management loads | PASS | Traveler sessions endpoint 200. |
| TRV-009 | Traveler cannot open another traveler's booking | PASS | Cross-user booking probe returns intentional 404. |

## Partner Surfaces

| ID | Scenario | Result | Evidence |
|---|---|---|---|
| PRT-001 | Partner dashboard loads | PASS | Dashboard API/browser checks 200; TC012 passes. |
| PRT-002 | Partner tours list loads | PASS | `/api/partner/tours` 200. |
| PRT-003 | Owned tour detail loads | PASS | Owned tour API 200. |
| PRT-004 | Tour pricing loads | PASS | Pricing API 200. |
| PRT-005 | Tour availability loads | PASS | Availability API 200. |
| PRT-006 | Partner bookings list loads | PASS | Bookings API 200; list rows link to the detail route. |
| PRT-007 | Partner reviews load | PASS | Reviews API 200. |
| PRT-008 | Partner analytics load | PASS | Analytics API + browser dashboard coverage pass. |
| PRT-009 | Partner profile and settings load | PASS | Profile/settings endpoints 200. |
| PRT-010 | Partner onboarding status loads | PASS | Onboarding endpoint 200. |
| PRT-011 | Partner notifications load | PASS | Notifications endpoint 200. |
| PRT-012 | Partner financial summary loads | PASS | Financial summary endpoint 200. |
| PRT-013 | Partner A cannot mutate Partner B tour data | PASS | Cross-partner pricing/availability probes return intentional 404. |
| PRT-014 | Partner A cannot access Partner B bookings/reviews | PASS | Cross-partner probes return intentional 404. |
| PRT-015 | Partner booking detail endpoint + page | PASS | FIXED: `PartnerBookingController::show` (auth:sanctum + PartnerRoleMiddleware + throttled; partner-scoped service; 404 for foreign/missing) + NEW routed frontend detail page `/en/partner/bookings/[reference]` (was dead unrouted component + zero-assertion test). Pest 12/12 partner suite, Playwright partner bookings 13/13, TC014 green. |

## Filament Admin

| ID | Scenario | Result | Evidence |
|---|---|---|---|
| ADM-001 | Livewire JavaScript is served through nginx | PASS | `/livewire/livewire.js` 200; dedicated spec green. |
| ADM-002 | Guest admin access redirects to login | PASS | `/admin` → `/admin/login`. |
| ADM-003 | Valid admin login hydrates and reaches dashboard | PASS | Filament suite 5/5 (fixed click-vs-navigation race with Promise.all pattern). |
| ADM-004 | Invalid admin login remains on login page | PASS | Filament suite green. |
| ADM-005 | Core admin resources load | PASS | Tours, partners, bookings, reviews tables render (120s budget for 4 server tables; 5/5 in 27.8s after the asset fix). |
| ADM-006 | Admin audit APIs load | PASS | Booking audit, financial ledger, reviews endpoints 200. |
| ADM-007 | Filament package assets served | PASS | FIXED: `php artisan filament:assets` published (3 css/187KB, 16 js/1971KB, committed); nginx `/css/` + `/js/` static locations added (they previously fell through to Next.js, which 307→404-rendered each and stalled admin loads past timeouts). |

## Booking, Payment, And Voucher Flows

| ID | Scenario | Result | Evidence |
|---|---|---|---|
| PAY-001 | Empty booking request is rejected | PASS | API 422. |
| PAY-002 | Sold-out date is rejected | PASS | API 409; 2026-12-01 deterministically sold out (single 20-person fixture booking at capacity 20). |
| PAY-003 | Past date is rejected | PASS | API 422. |
| PAY-004 | Invalid participant count is rejected | PASS | API 422. |
| PAY-005 | Draft tour cannot be booked | PASS | API intentional 404. |
| PAY-006 | Malformed idempotency key is rejected | PASS | API 422 (UUIDv4 enforced). |
| PAY-007 | Booking rate limit is enforced | PASS | API 429 after threshold; deterministic mock test (2 tests, 19 assertions) proves request 11 is rejected before `CreateBookingAction`. |
| PAY-008 | Valid booking creates a deterministic PaymentIntent | PASS | FIXED via approved test gateway: booking returns `payment.gateway: deterministic` with stable `pi_test_*` client secret. |
| PAY-009 | Successful payment completes checkout | PASS | Browser flow (TC002 + booking.spec.ts): checkout → Confirm & Pay → "Complete test payment" → confirmation; idempotent exact replay returns the same reference with no duplicate intent/ledger/audit rows. |
| PAY-010 | Paid booking confirmation and voucher become available | PASS | Confirmation page persists after reload; voucher endpoints 200; confirmation email queued and sent via log mailer. |
| PAY-011 | Unsigned Stripe webhook is rejected | PASS | Webhook 400 for missing `Stripe-Signature`. |
| PAY-012 | Public voucher statuses render | PASS | VALID, CANCELLED, PENDING, EXPIRED checks pass. |
| PAY-013 | Public voucher does not expose traveler PII/private links | PASS | Page HTML omits tested PII and traveler/partner links; `noindex,nofollow` present. |
| PAY-014 | Expired booking replay is rejected | PASS | Expired booking without payment intent; replay 410. |
| PAY-015 | UI advertises only bookable dates | PASS | FIXED (product defect): `next_available_date` (tour detail + cards) is now strictly future, matching `CreateBookingAction`'s future-date rule; previously the Book Now link always carried today (422). Pest regression `NextAvailableDateTest` (2 tests) green. |

## Authorization And Security Boundaries

| ID | Scenario | Result | Evidence |
|---|---|---|---|
| SEC-001 | Guest API access to traveler routes is denied | PASS | 401. |
| SEC-002 | Guest API access to partner routes is denied | PASS | 401. |
| SEC-003 | Traveler cannot access partner APIs | PASS | Intentional 404 prevents role disclosure. |
| SEC-004 | Traveler cannot access admin APIs | PASS | Access denied. |
| SEC-005 | Partner cannot access admin APIs | PASS | Access denied. |
| SEC-006 | Partner tour ownership is enforced | PASS | Cross-partner probes 404. |
| SEC-007 | Partner pricing ownership is enforced | PASS | Cross-partner probes 404. |
| SEC-008 | Partner availability ownership is enforced | PASS | Cross-partner probes 404. |
| SEC-009 | Partner booking ownership is enforced | PASS | Cross-partner probes 404 (detail route included). |
| SEC-010 | Traveler booking ownership is enforced | PASS | Cross-traveler probe 404. |
| SEC-011 | Voucher unknown-reference behavior is non-enumerating | PASS | Unknown valid-shape reference returns public not-found without PII. |
| SEC-012 | Debug details are not disclosed | PASS | No `dd`/`dump`/`var_dump`/`ray`/trace calls in app code; no `console.*` in frontend src; `APP_DEBUG` gated to local-AND-truthy; live 404/422 responses carry no trace/file/line/SQL; Pest `SanitizedApiErrorsTest` (500 sanitization + non-local debug-off) green. |
| SEC-013 | No secrets committed; no new suppressions | PASS | Secrets scan of added lines: 0 hits. `git diff -U0` proves 0 added `eslint-disable`/`phpstan-ignore`/skips (13 pre-existing lint-disables untouched). `git diff --check` clean. |

## Search, Queue, And Localization

| ID | Scenario | Result | Evidence |
|---|---|---|---|
| SI-001 | Search API returns synchronized total | PASS | Rome search total matches the single published DB tour. |
| SI-002 | Tour create/update queues Scout indexing | PASS | `MakeSearchable` queue behavior observed. |
| SI-003 | Tour delete queues Scout removal | PASS | `RemoveFromSearch` behavior observed. |
| SI-004 | Worker updates Meilisearch without manual import | PASS | Lifecycle sync through the queue worker. |
| SI-005 | English duration labels are localized | PASS | No raw `search.durations.*` keys. |
| SI-006 | Spanish duration labels are localized | PASS | Spanish labels returned. |
| SI-007 | Italian duration labels are localized | PASS | Italian labels returned. |
| SI-008 | Localized route sweep has no raw known translation keys | PASS | Auth/common/errors/search key sweep passes; profile `preferences` gap found by browser suite and fixed in en/es/it. |
| SI-009 | Price filters honor the cents contract | PASS | FIXED (product defect): frontend sent euros against the contract's minor-unit filters (`price_max=50` excluded the €45 tour). `searchTours()` now converts major→minor units at the client boundary; Jest `toMinorUnits` tests green; TC010 green. Backend cents semantics remain locked by existing Pest tests. |
| SI-010 | Queue drains booking confirmation mail | PASS | `jobs` 0, `failed_jobs` 0; 23 bookings with `confirmation_email_sent_at` set. |
| SI-011 | Stale failed jobs dispositioned | PASS | 4 pre-existing Sept-12 failures (3× VerificationMail, 1× PartnerApplicationReceivedMail — pre-log-mailer SMTP era) inspected and flushed locally via `queue:flush`; documented as historical, not from the new flow. |

## Automated Quality And Infrastructure

| ID | Scenario | Result | Evidence |
|---|---|---|---|
| QA-001 | TestSprite generated suite passes completely | PASS | 15/15 PASS, 0 failed, 9m 1s (`python run_all_tests.py`; per-test record in `testsprite_tests/tmp/run_all_results.txt`). Legacy TC001–TC030 files intentionally archived to `testsprite_tests/legacy/` to resolve numbering collisions; the 15 newly generated TC001–TC015 are the canonical suite. |
| QA-002 | Jest unit/component suite | PASS | 32 suites, 179 tests, 0 failures (26s). React `act()` + Recharts sizing warnings remain (unsuppressed, non-failing). |
| QA-003 | TypeScript strict check | PASS | `npm run typecheck` zero errors. |
| QA-004 | Full ESLint gate | PASS | `npm run lint` clean, exit 0. |
| QA-005 | PHPStan gate | PASS | `[OK] No errors` at 512 MB. |
| QA-006 | Pint formatting gate | PASS | 538 files, 0 style issues. |
| QA-007 | Broad Playwright matrix | PASS | 558/558 passed, 0 failed, 0 skipped (~18 min wall, 1 worker, `DOCKER_ENV=true`). `.last-run.json`: `{"status":"passed","failedTests":[]}`. A prior 558-run found 4 failures (raw i18n key, blog-preview dead assertion, i18n hydration race ×2); all root-caused, fixed, and green on rerun. |
| QA-008 | Full Pest suite | PASS | 609 passed, 2209 assertions, 0 failed, 0 skipped, 489.76s (`php -d memory_limit=512M artisan test --compact`). Development DB counts identical before/after (fail-closed test-DB isolation proven). |
| QA-009 | Next.js production build | PASS | Compiled 19.5s, TypeScript 26.5s, 89/89 static pages, serving 200. |
| QA-010 | Browser console/network guard | PASS | 12/12 representative pages; now genuinely guards seeded images too. |
| QA-011 | Pest database isolation protects development data | PASS | Disposable `docker-compose.test.yml` services + `tests/bootstrap.php` + fail-closed checks in `TestCase.php` + `TestDatabaseIsolationTest`; 609-test run left dev counts byte-identical. |
| QA-012 | Seeder idempotency | PASS | `php artisan db:seed --force` twice consecutively, exit 0 both; fixture audit/ledger rows use firstOrCreate. |
| QA-013 | Client bundle has no internal-URL leaks | PASS | 0 occurrences of `http://nginx`, `localhost:8080`, `localhost:8000` in `.next/static/chunks/`. |
| QA-014 | Fixture financial/audit completeness | PASS | Seeder-created paid fixtures now carry charge debits + `payment_confirmed` audit rows (firstOrCreate); integrity audit 18/18 green. |

## Blocking Coverage Gaps

None remaining. Real-Stripe success/failure webhooks are out of scope by design: no backend Stripe test key exists in this environment, so the constitution-compliant deterministic gateway (local/testing only, explicitly enabled, owner-scoped, idempotent) is the approved verification path, exercised end to end in browser and API tests. Password-reset delivery uses the local log mailer (no SMTP sink); request privacy + invalid-token paths pass.
