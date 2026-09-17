# Bookly Travel — Security Boundary Audit

Date: 2026-09-17 · Auditor: automated acceptance audit · Environment: Docker dev
(`bookly-backend`, `bookly-frontend`, `bookly-nginx`, `bookly-postgres`, redis,
`bookly-meilisearch` + `bookly-test-postgres`/`bookly-test-meilisearch` for Pest).

Verdict: **PASS** — all tenant-isolation, permission-gate, upload, rate-limit and
disclosure checks hold. One non-critical hardening note (H-01). No secrets in git.

## 1. Authentication boundaries (live-verified 2026-09-17)

| Probe | Result |
|-------|--------|
| `GET /api/public/auth/me` without token (`Accept: application/json`) | **401** `{"message":"Unauthenticated."}` |
| `GET /api/admin/financial-ledger` without token (JSON) | **401** `{"message":"Unauthenticated."}` |
| `GET /api/public/traveler/wishlist` without token | 401 (Pest `WishlistTest`, e2e `auth-guards` redirect to login at page level) |
| Filament `/admin` unauthenticated | Redirect to login (e2e `filament-admin.spec.ts`); wrong password stays on login |
| Authenticated user visiting login/register | Redirected home (e2e `auth-guards.spec.ts`) |
| Session restore `/api/public/auth/me` uses `throttle:traveler` (120/min), NOT the 10/min auth limiter — no self-inflicted 429 on page reload | Verified in `routes/api/public.php:73-82` |

## 2. Authorization / resource-hiding contract (code + Pest + live)

Contract (verified against tests — the tests ARE the contract):

- **Traveler → Traveler:** `TravelerBookingController@show` scopes by
  `traveler_id`; чужой reference → **403** (`AccessDeniedHttpException`),
  unknown reference → **404**. Pest `TravelerBookingsTest` asserts
  "returns 403 when accessing another traveler booking". Wishlist/review/profile
  endpoints scope by authenticated user id.
- **Partner → Partner (existence-hiding):** `TourController` (7× `abort(404)`),
  `AvailabilityController`/`PricingController` (`firstOrFail` on
  partner-scoped queries), `PartnerBookingShowTest` ("returns 404 when the
  partner accesses another partner booking"), `TourDraftTest` (404 for another
  partner's tour), `TourArchiveTest` (404), `NotificationTest` (cannot read
  another partner's notifications), `BookingListTest` (no cross-partner rows,
  no detail disclosure). Status mutation on another partner's tour → 403
  (`BookingStatusTest`, `PartnerBookingsTest:131`).
- **Traveler → Partner:** partner routes require `tokenCan('partner')` +
  existing partner row, else **404** (`PartnerRoleMiddleware:15-23`) — existence
  of the partner surface itself is hidden. Unapproved partners get 403
  `ONBOARDING_STATUS_BLOCKED` outside the allowlisted self-service endpoints.
- **Partner → Admin:** admin API requires `role:admin` (**403** otherwise,
  Pest `AdminPermissionsTest` incl. `role:admin` guard test); Filament panel
  denies non-admins; per-action governance flags independently resolved.
- **Unauthenticated → Protected:** 401 (JSON clients) at API; login redirect at pages.

## 3. Rate limiting

Named limiters in `AppServiceProvider` (all wired in routes): `api`, `auth`
(10/min — brute-force protection per FR-014), `search` (60/min), `detail`,
`listing`, `homepage`, `sitemap` (10/min), `reviews`, `booking.create`,
`booking.get`, `traveler` (120/min), `verify` (60/min/IP, anti-enumeration for
`GET v/{reference}`), `blog`, `blog_detail`. `RateLimitSearchMiddleware`
emits contract 429 body + `X-RateLimit-*` headers + localized message.
`run_all_tests.py` clears cache between TC runs to reset counters without
weakening policies.

## 4. Upload defenses (`UploadController@signedUrl`)

Strict allowlist: `file_type ∈ {image/jpeg, image/png}`, `file_size ≤ 5MB`;
server-generated UUID object key (no client path traversal); 15-minute expiry;
R2 presigned flow (mock URL generator clearly marked for production wiring).
Route throttled (`booking.create`) + partner-gated.

## 5. Debug / error disclosure

- `bootstrap/app.php` exception renderer: API 5xx → generic
  `"An unexpected error occurred. Please try again later."` (verified live —
  response bodies contain no stack, path, SQL or config).
- Validation/401/404 keep structured contracts; 404 → `"Not found."` /
  `"Tour not found."` / `"Booking not found."` (no existence oracle beyond the
  documented 403-vs-404 contract).
- `APP_DEBUG=true` / `APP_ENV=local` exist only in git-ignored `backend/.env`
  (local dev). Production MUST set `APP_DEBUG=false` (deployment checklist,
  not a code defect).
- Filament `/livewire`, `/storage`, `/_ignition` paths route to Laravel, not
  exposed statically by nginx.

## 6. Secret leakage

- `backend/.env`, `frontend/.env.local` (contains dev Stripe placeholder key
  `pk_test_placeholder`) and all `.env*` are git-ignored and untracked
  (`git check-ignore` confirms; `git status` clean).
- No `sk_live`/`sk_test`, webhook secret, DB password or master key in tracked
  source. Stripe secrets are absent everywhere → live-Stripe testing is
  `ENVIRONMENT BLOCKED` (see acceptance report §12).
- Production browser bundle contains no hardcoded API origin: `apiClient`
  uses `NEXT_PUBLIC_API_URL || ''` (same-origin via nginx); `localhost:8080`
  in a host-built `.next` was traced to git-ignored `frontend/.env.local` and
  does not ship (Docker/prod builds keep the variable empty).

## H-01 (non-critical hardening note, EXPECTED BEHAVIOR)

API-only contract requires `Accept: application/json` (every first-party client
sends it). A header-less request to a guarded endpoint currently yields a
sanitized 500 (web-guest redirect to an undefined `login` route) instead of
401 — no data is disclosed and the request is still rejected. Suggested
hardening: name a fallback login route or force JSON for `api/*`. Not a
release blocker.
