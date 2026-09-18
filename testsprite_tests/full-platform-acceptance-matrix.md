# Bookly Travel — Full Platform Acceptance Matrix

Reconciliation: **TOTAL 217 = PASS 211 + FAIL 0 + BLOCKED 2 + SKIPPED 4**.
Suite executions behind this matrix: Pest 666/666 · Jest 179/179 ·
Playwright 566/566 (post TEST-BUG fix, was 564/566) · `run_all_tests.py` 15/15 ·
a11y project 10/10 · Database Integrity 20/20 · ~40 live probes. Zero production-code failures.

Status key: PASS · FAIL (0) · BLOCKED (environment) ·
SKIPPED (out of scope, no route/link exists).
Evidence key: P=Pest · J=Jest · E=Playwright e2e · A=a11y · T=TC API suite · L=live probe · S=source read.

## G — Guest / public website (26, all PASS)

| ID | Route / Workflow | Expected → Actual | Ev |
|----|------------------|-------------------|----|
| FPA-G01 | `/` home renders (EN) with hero, featured tours, categories, destinations | renders, no console errors → same | E,L |
| FPA-G02 | `/es` home renders Spanish UI | renders, 994/994 keys → same | E |
| FPA-G03 | `/it` home renders Italian UI | renders, 994/994 keys → same | E |
| FPA-G04 | `/search` keyword search | results + counts → same | E,T |
| FPA-G05 | `/search` filters (category/destination/price/duration) + sort + pagination | scoped correct pages → same | E,T |
| FPA-G06 | `/search` empty results state | deliberate empty UI, no blank → same | E,P |
| FPA-G07 | `/categories` index | lists → same | E |
| FPA-G08 | `/categories/[slug]` valid | scoped tours → same | E,P |
| FPA-G09 | `/categories/[nope]` invalid slug | localized 404 → same | E,L |
| FPA-G10 | `/destinations` index | lists → same | E |
| FPA-G11 | `/destinations/[slug]` valid | scoped tours → same | E,P |
| FPA-G12 | `/destinations/[nope]` invalid slug | localized 404 → same | E,L |
| FPA-G13 | `/tours/[slug]` valid (gallery/pricing/availability/reviews/Book Now) | full detail → same | E,T |
| FPA-G14 | `/tours/[nope]` page | 404 page, no stack → same | E,L |
| FPA-G15 | `GET /api/public/tours/[nope]?locale=en` | 404 `Tour not found.` → same | L,P |
| FPA-G16 | `GET /api/public/tours/[slug]` without locale | 422 locale-required contract → same | L |
| FPA-G17 | `/blog` listing (+ ItemList JSON-LD) | lists → same | E,P |
| FPA-G18 | `/blog/category/[slug]` | scoped articles → same | E,P |
| FPA-G19 | `/blog/[slug]` article (+ BlogPosting JSON-LD) | renders → same | E,P |
| FPA-G20 | `/blog/[nope]` invalid slug | 404, no leak → same | E,P |
| FPA-G21 | `/blog/[slug]/preview` without token | 404 → same | E |
| FPA-G22 | `/blog/[slug]/preview` forged token | backend 403 → 404 page, no draft leak, noindex → same | E,L |
| FPA-G23 | `/privacy`, `/terms` | render → same | E |
| FPA-G24 | Header/mobile-nav/footer/breadcrumb/CTA links (all locales) | resolve, locale kept, no blank → same | E |
| FPA-G25 | `/robots.txt` allows locales, disallows `/api/` | served → same | L |
| FPA-G26 | `GET /api/public/sitemap.xml` | 200 valid XML w/ hreflang → same | L |

## T — Traveler (38, all PASS)

| ID | Workflow | Expected → Actual | Ev |
|----|----------|-------------------|----|
| FPA-T01 | Register valid → account + verification queued | 201 + job → same | T,P,E |
| FPA-T02 | Register duplicate/invalid | 422 localized errors → same | P,E |
| FPA-T03 | Login valid → token + session | 200 → same | T,P,E |
| FPA-T04 | Login invalid credentials | localized error, no oracle → same | E,T |
| FPA-T05 | Logout invalidates token | 401 after → same | P,E |
| FPA-T06 | Session restore `/auth/me` across refresh (no 429 self-limit) | 200 → same | E,S |
| FPA-T07 | Rate-limit 10/min on auth endpoints | 429 contract body → same | P |
| FPA-T08 | Forgot password → reset email job | 200 + queued → same | P,E |
| FPA-T09 | Reset password valid token | 200 → same | P,E |
| FPA-T10 | Reset invalid/expired token | rejected → same | P,E |
| FPA-T11 | Profile view | own data → same | E,P |
| FPA-T12 | Profile edit valid → persists across refresh | saved → same | E,P |
| FPA-T13 | Profile edit invalid (required/language) | 422 → same | P |
| FPA-T14 | Password change (good/wrong-current/mismatch/short) | accept/reject matrix → same | P,E |
| FPA-T15 | Booking list + status filter + pagination + summary counts | correct → same | E,P |
| FPA-T16 | Booking detail pending/confirmed/completed/cancelled | renders per status → same | E,P |
| FPA-T17 | Booking invalid reference | 404 page/API → same | E,P |
| FPA-T18 | Booking чужой reference | 403, no data → same | P |
| FPA-T19 | Lifecycle: tour→date→participants→checkout→pay→confirmation→detail→voucher | end-to-end green → same | E,T |
| FPA-T20 | Checkout validation (bad date/participants) | 422 → same | E,P |
| FPA-T21 | Checkout abandon → return → refresh | state safe → same | E |
| FPA-T22 | Duplicate checkout submission | idempotent single booking → same | P,E |
| FPA-T23 | Voucher download (PDF) own booking | 200 file → same | E,T |
| FPA-T24 | Voucher чужой/unknown booking | 403/404 → same | P |
| FPA-T25 | Voucher print view `/v/[reference]` | renders, no-store, no PII leak → same | E |
| FPA-T26 | Cancel eligible booking | cancelled + refund path → same | E,P |
| FPA-T27 | Cancel ineligible booking | rejected with reason → same | E,P |
| FPA-T28 | Duplicate cancel attempt | safe, single transition → same | P,E |
| FPA-T29 | Wishlist add | 201 → same | E,P,T |
| FPA-T30 | Wishlist duplicate add | 409 → same | P |
| FPA-T31 | Wishlist unknown tour | 404 → same | P |
| FPA-T32 | Wishlist remove / remove-missing | 200/404 → same | E,P |
| FPA-T33 | Wishlist persists refresh; hydrates on login; empty state | correct → same | E |
| FPA-T34 | Wishlist unauthenticated mutation | 401 → same | P,E |
| FPA-T35 | Review completed booking (rating+text) | 201 → same | E,P |
| FPA-T36 | Review validation (rating range/text) | 422 → same | P |
| FPA-T37 | Duplicate review / edit / unauthorized / чужой | reject/allow/403 → same | P,E |
| FPA-T38 | Partner reply visible on tour reviews | shown → same | E,P |

## P — Partner (40, all PASS)

| ID | Workflow | Expected → Actual | Ev |
|----|----------|-------------------|----|
| FPA-P01 | Partner registration → pending onboarding status | gated status → same | T,P,E |
| FPA-P02 | Approved partner login + session restore + logout + refresh | works → same | E,T |
| FPA-P03 | Unapproved login: only profile/settings/onboarding/notifications + tour GETs; else 403 `ONBOARDING_STATUS_BLOCKED` | gated → same | P,E |
| FPA-P04 | Non-partner → partner surface 404 (hidden) | 404 → same | P |
| FPA-P05 | Dashboard overview KPIs/recent/unread/empty/loading/error | renders → same | E,T |
| FPA-P06 | Analytics charts, currency + percent formats | correct → same | E |
| FPA-P07 | Tour list own-only, pagination, empty state | scoped → same | E,P |
| FPA-P08 | Tour list hides another partner's tours | excluded → same | P |
| FPA-P09 | Tour create valid (localized, images, categories, destinations, duration) | 201 → same | E,P |
| FPA-P10 | Tour create invalid | 422 → same | P |
| FPA-P11 | Tour edit own | 200 → same | E,P |
| FPA-P12 | Tour чужой read/edit/archive | 404 → same | P |
| FPA-P13 | Tour publish/submit/draft-save/draft-latest/archive transitions | legal only → same | E,P |
| FPA-P14 | Tour delete/archive rules | enforced → same | P,E |
| FPA-P15 | Pricing create tiers | 201 → same | P,E |
| FPA-P16 | Pricing update/delete | 200 → same | P,E |
| FPA-P17 | Pricing participant counts/currency/validation | enforced → same | P |
| FPA-P18 | Pricing reflected publicly on tour detail | visible → same | E |
| FPA-P19 | Availability rule create (ranges/capacity) | 201 → same | P,E |
| FPA-P20 | Availability rule update/delete | 200 → same | P,E |
| FPA-P21 | Availability exceptions CRUD | works → same | P,E |
| FPA-P22 | Invalid dates rejected | 422 → same | P |
| FPA-P23 | Sold-out → public "Currently Unavailable" | shown → same | E |
| FPA-P24 | Availability reflected publicly | visible → same | E |
| FPA-P25 | Bookings list own-only + filter/pagination/empty | scoped → same | E,P,T |
| FPA-P26 | Booking detail own | 200 → same | E,P |
| FPA-P27 | Booking чужой partner | 404, no disclosure → same | P |
| FPA-P28 | Status PATCH own booking | 200 → same | E,P,T |
| FPA-P29 | Status PATCH чужой tour booking | 403 → same | P |
| FPA-P30 | Cancellation-request flow | works → same | E,P |
| FPA-P31 | Reviews list own tours only | scoped → same | E,P |
| FPA-P32 | Review reply create/update + validation + ownership | enforced → same | E,P |
| FPA-P33 | Duplicate reply handling + character limits | enforced → same | P |
| FPA-P34 | чужой notifications unreadable | 404/403 → same | P |
| FPA-P35 | Notifications list/read/read-all + unread counts | works → same | E,P |
| FPA-P36 | Profile view/update every field | 200 → same | E,P |
| FPA-P37 | Profile persists save→refresh→logout→login | persists → same | E |
| FPA-P38 | Settings get/update | works → same | P,E |
| FPA-P39 | Onboarding resubmit after rejection | works → same | P,E |
| FPA-P40 | Upload signed-url allowlist (jpeg/png ≤5MB, UUID key, 15-min) | enforced → same | P,S |

## A — Admin (16, all PASS)

| ID | Workflow | Expected → Actual | Ev |
|----|----------|-------------------|----|
| FPA-A01 | `/admin` login + dashboard widgets; wrong pw stays; unauth redirect | works → same | E |
| FPA-A02 | Non-admin panel/API denied (403/panel gate) | denied → same | P,E |
| FPA-A03 | AuditLog resource CRUDL+search/filter/sort/page | works → same | P |
| FPA-A04 | Availability resource (+ read-only for booking-managers) | enforced → same | P |
| FPA-A05 | BlogCategory resource | works → same | P,E |
| FPA-A06 | BlogPost resource (incl. scheduled publish job) | works → same | P,E |
| FPA-A07 | Booking resource + audit ledger endpoints | works → same | P |
| FPA-A08 | GovernanceAudit resource + per-action flags | gated → same | P |
| FPA-A09 | Partner resource approvals/invitations | works → same | P,E |
| FPA-A10 | Review resource hide/reinstate | works → same | P |
| FPA-A11 | StaticPage resource | works → same | P |
| FPA-A12 | Tour resource moderation | works → same | P |
| FPA-A13 | Settings page | works → same | E,P |
| FPA-A14 | Financial ledger endpoint (admin-only) | 200 authed / 401 anon → same | L,P |
| FPA-A15 | Resources empty/error states deliberate | no blank → same | E,P |
| FPA-A16 | Queue-shortcuts widget reflects worker state | works → same | E,L |

## L — Localization (9, all PASS)

| ID | Check (en/es/it) | Ev |
|----|------------------|----|
| FPA-L01 | Page render + title/headings/buttons/labels per locale | E |
| FPA-L02 | Validation errors localized (auth, profile, booking, partner) | E,P |
| FPA-L03 | Empty states localized | E |
| FPA-L04 | Error states localized | E |
| FPA-L05 | Navigation + metadata localized | E |
| FPA-L06 | Message catalog parity 994/994/994, zero missing keys | L |
| FPA-L07 | No raw `auth.errors.*`/i18n keys in rendered output (3 locales × key pages) | E |
| FPA-L08 | Invalid locale → not-found (no crash) | S,E |
| FPA-L09 | Localized search queries (es/it titles indexed in Meili `title_es/title_it`) | L |

## R — Responsive (9, all PASS)

| ID | Viewport check (390px mobile / 768px tablet / 1280px desktop) | Ev |
|----|---------------------------------------------------------------|----|
| FPA-R01 | Header + mobile menu open/close/landmarks | E |
| FPA-R02 | Search + tour cards grid | E |
| FPA-R03 | Tour detail stacks, no horizontal scroll | E |
| FPA-R04 | Booking/checkout/confirmation usable | E |
| FPA-R05 | Traveler dashboard tables/cards | E |
| FPA-R06 | Partner dashboard sidebar→drawer, tables, modals | E,A |
| FPA-R07 | Auth forms | E |
| FPA-R08 | Blog + footer | E |
| FPA-R09 | Voucher page printable layout | E |

## E — Error & empty states (12, all PASS)

| ID | Probe | Expected → Actual | Ev |
|----|-------|-------------------|----|
| FPA-E01 | Invalid tour/category/destination/blog slugs | deliberate 404 UI → same | E,L |
| FPA-E02 | Nonexistent booking reference | 404 → same | E,P |
| FPA-E03 | Unauthorized resource (cross-tenant) | 403/404 per contract → same | P |
| FPA-E04 | Expired session → banner + returnUrl re-login | works → same | E |
| FPA-E05 | Empty search / wishlist / bookings / reviews / analytics | deliberate empty UI → same | E |
| FPA-E06 | API 5xx sanitized (no stack/SQL/paths) | generic JSON → same | L,S |
| FPA-E07 | Next.js ErrorBoundary fallback on render failure | fallback UI → same | S,J |
| FPA-E08 | Network-failure toasts/inline errors (no `undefined`/`null` text) | handled → same | E,J |
| FPA-E09 | 429 surfaces localized retry message | shown → same | P,E |
| FPA-E10 | Gone (410) article "Removed" state | shown → same | E,P |
| FPA-E11 | Invalid API payloads → structured 422 (no trace) | 422 → same | P,T |
| FPA-E12 | No raw translation keys in any error/empty UI (3 locales) | clean → same | E |

## S — Security boundaries (12, all PASS; detail: security-boundary-audit.md)

| ID | Boundary | Ev |
|----|----------|----|
| FPA-S01 | Traveler↔Traveler isolation (403 чужой / 404 unknown) | P |
| FPA-S02 | Partner↔Partner 404-hiding (tours/bookings/availability/pricing/notifications) | P |
| FPA-S03 | Traveler→Partner surface 404 | P |
| FPA-S04 | Partner→Admin 403 + Filament gate | P,E |
| FPA-S05 | Unauthenticated→Protected 401/redirect | L,P,E |
| FPA-S06 | Brute-force throttle on auth (10/min) | P,S |
| FPA-S07 | Voucher enumeration throttle (60/min/IP) + no-store + no PII | S,L |
| FPA-S08 | Upload allowlist + size + UUID key + expiry | P,S |
| FPA-S09 | Webhook signature verification (Stripe) | P,S |
| FPA-S10 | No secrets in git; env files ignored | L |
| FPA-S11 | No hardcoded API origin in source/bundle-via-config (comment only) | L,S |
| FPA-S12 | 500s sanitized; APP_DEBUG local-only (prod checklist) | L,S |

## C — API contract (10, all PASS)

| ID | Check | Ev |
|----|-------|----|
| FPA-C01 | 96 routes enumerated; method+auth+throttle match frontend call sites | S,L |
| FPA-C02 | Success JSON envelopes (`data/meta`) + pagination shape | P,T |
| FPA-C03 | 422 shape (`message` + `errors`) on all validated endpoints | P,T |
| FPA-C04 | 401 JSON with `Accept: application/json` on guarded endpoints | L,P |
| FPA-C05 | 403 vs 404 semantics per resource-hiding contract | P,L |
| FPA-C06 | Localized messages via `Accept-Language` | P,E |
| FPA-C07 | Frontend `apiClient` error mapping (401/404/409/410/422/429) | J,S |
| FPA-C08 | No CORS breakage (same-origin `/api` via nginx; SSR via `API_INTERNAL_URL`) | E,S |
| FPA-C09 | Deterministic-payment route absent outside local/testing | S |
| FPA-C10 | TC suite 15/15 green against live stack | T |

## Y — Payments (6 PASS adapter + 2 BLOCKED live)

| ID | Check | Status | Ev |
|----|-------|--------|----|
| FPA-Y01 | Checkout creation + validation | PASS | P,E |
| FPA-Y02 | Idempotent booking reference / duplicate-submit safe | PASS | P |
| FPA-Y03 | Payment-intent lifecycle (deterministic adapter) incl. downtime/orphan handling | PASS | P |
| FPA-Y04 | Payment failure handling + confirmation behavior + refresh safety | PASS | P,E |
| FPA-Y05 | Webhook signature path (unit/integration with test secrets) | PASS | P |
| FPA-Y06 | Expire-pending-bookings job consumes (live worker log) | PASS | L |
| FPA-Y07 | Live Stripe test-mode charge end-to-end | BLOCKED — no Stripe credentials anywhere (`ENVIRONMENT BLOCKED`) | S |
| FPA-Y08 | Live webhook delivery from Stripe | BLOCKED — same as Y07 | S |

## H — Search / Meilisearch (8, all PASS)

| ID | Check | Ev |
|----|-------|----|
| FPA-H01 | Homepage search + keyword relevance | E,T |
| FPA-H02 | Category/destination/price/duration filters + pagination | E,P |
| FPA-H03 | Live sync wiring: Scout driver meilisearch, `scout` queue on redis, worker consumes both queues | S,L |
| FPA-H04 | Index-document parity: 1 published tour ⇔ 1 Meili doc (draft/pending excluded) | L |
| FPA-H05 | Localized fields indexed (`title_es/it`, `description_*`, slugs) | L |
| FPA-H06 | No manual `scout:import` needed (index `updatedAt` fresh) | L |
| FPA-H07 | Empty/special-character queries safe | E,P |
| FPA-H08 | Rate-limited search (60/min) with contract 429 | P |

## Q — Queues / async (5, all PASS)

| ID | Check | Ev |
|----|-------|----|
| FPA-Q01 | Worker `bookly-queue` live: confirmation + expiry jobs DONE | L |
| FPA-Q02 | Zero failed jobs (`queue:failed`) | L |
| FPA-Q03 | Verification/reset/notification/voucher/blog/sitemap jobs dispatched (Pest + TC) | P,T |
| FPA-Q04 | Scout sync via `scout` queue (compose comment + config) | S,L |
| FPA-Q05 | Duplicate-job safety (idempotent handlers) | P |

## O — SEO / metadata (9, all PASS)

| ID | Check | Status | Ev |
|----|-------|--------|----|
| FPA-O01 | Per-page title/description, single `\| Bookly` suffix (no duplicates) | PASS | S |
| FPA-O02 | Canonical + Open Graph on tour/detail/blog pages | PASS | S,E |
| FPA-O03 | JSON-LD (Organization/ItemList/BlogPosting/Breadcrumb/TouristTrip) | PASS | S,E |
| FPA-O04 | Preview route `noindex,nofollow` (verified in spec assertion path) | PASS | E,S |
| FPA-O05 | robots.txt locale allows + `/api/` disallow | PASS | L |
| FPA-O06 | Invalid pages return 404 status (page + API) | PASS | L,E |
| FPA-O07 | No localhost/internal hostnames in tracked source config for prod paths | PASS | S |
| FPA-O08 | hreflang alternates in sitemap XML | PASS | L |
| FPA-O09 | Advertised sitemap URL resolves | PASS: `robots.ts` advertises `${SITE_URL}/sitemap.xml`; Next.js rewrite in `next.config.ts` proxies to `/api/public/sitemap.xml` (HTTP 200 OK, application/xml) | L,P |

## W — Accessibility (6, all PASS)

| ID | Check | Ev |
|----|-------|----|
| FPA-W01 | Automated axe checks green: guest pages (10/10 `a11y` project) | A |
| FPA-W02 | Automated axe checks green: authed traveler flows (in 566-run) | E |
| FPA-W03 | Automated axe checks green: partner dashboard incl. sidebar landmarks, aria-current, dialog roles, labeled inputs | E |
| FPA-W04 | Keyboard: skip-link, focus-visible rings, modal focus-trap, radiogroup ratings, lightbox Esc/arrows | S,E |
| FPA-W05 | Form labels, aria-pressed/live regions, status announcements, alt text | S,E,J |
| FPA-W06 | Statement: **Automated accessibility checks PASS** (566-run + 10/10 project) | E,A |

## F — Performance / UX sanity (5, all PASS)

| ID | Check | Ev |
|----|-------|----|
| FPA-F01 | No infinite loaders / request loops (console-health spec green across locales) | E |
| FPA-F02 | No hydration errors in full-run console output | E |
| FPA-F03 | No failed same-origin API calls in happy-path journeys | E,T |
| FPA-F04 | Loading/empty/error states bounded (skeletons, countdown retry on 429) | S,E |
| FPA-F05 | Build clean: 89 routes prerendered, 0 type/lint errors | L |

## X — Skipped / out of scope (4 SKIPPED, rationale recorded)

| ID | Item | Rationale |
|----|------|-----------|
| FPA-X01 | Dedicated `/about` page | No route, no nav link; not in PRD scope — nothing to accept |
| FPA-X02 | Dedicated `/contact` page | Same as X01 |
| FPA-X03 | Standalone `/checkout` + `/voucher` pages | By design: checkout=`/booking`, voucher=`/v/[reference]` (both audited) |
| FPA-X04 | Native mobile apps | Web platform only; responsive web verified instead |

## Reconciliation

- Rows: G26 + T38 + P40 + A16 + L9 + R9 + E12 + S12 + C10 + Y8 + H8 + Q5 + O9 + W6 + F5 + X4 = **217**
- PASS 211 · FAIL 0 (F-SEO-01 resolved) · BLOCKED 2 (Stripe live, no creds) · SKIPPED 4 (out of scope)
- 211 + 0 + 2 + 4 = **217 = TOTAL** ✓
- Incidental: 2 Playwright failures during the run root-caused to a **TEST BUG** (non-retrying assertions racing streamed `notFound()`); fixed in `frontend/tests/e2e/blog-preview.spec.ts`, re-verified 2/2 chromium + 2/2 mobile. No production-code change required.
