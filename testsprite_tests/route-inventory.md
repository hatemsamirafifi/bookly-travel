# Bookly Travel — Complete Route Inventory

Generated: 2026-09-17 (UTC) · Branch: `fix/final-regression-audit-gate` · HEAD: `1020531`
Method: source-tree enumeration (`frontend/src/app`, `backend/routes/api/*.php`,
`backend/app/Filament`), confirmed by `next build` route table (89 pages) and
`php artisan route:list --path=api` (96 routes).

Locale prefix: every `[locale]` page serves `/en/*`, `/es/*`, `/it/*`
(`frontend/src/i18n/routing.ts`, `localePrefix: 'always'`, default `en`).
Invalid locale renders `not-found` (`[locale]/layout.tsx` → `notFound()`).

---

## 1. Public frontend pages — `frontend/src/app/[locale]/(public)/` (15 routes)

| # | Route (× en/es/it) | Source file | Notes |
|---|--------------------|-------------|-------|
| 1 | `/` (home) | `(public)/page.tsx` | Search hero, featured tours, categories, destinations |
| 2 | `/search` | `(public)/search/page.tsx` | Keyword + category/destination/price/duration filters, sort, pagination |
| 3 | `/categories` | `(public)/categories/page.tsx` | Category index |
| 4 | `/categories/[slug]` | `(public)/categories/[slug]/page.tsx` | Scoped tour listing; invalid slug → localized 404 |
| 5 | `/destinations` | `(public)/destinations/page.tsx` | Destination index |
| 6 | `/destinations/[slug]` | `(public)/destinations/[slug]/page.tsx` | Scoped tour listing; invalid slug → localized 404 |
| 7 | `/tours/[slug]` | `(public)/tours/[slug]/page.tsx` | `generateMetadata` (canonical + Open Graph), `notFound()` on unknown slug; booking widget links to `/booking?tour=&participants=&date=` |
| 8 | `/booking` (checkout) | `(public)/booking/page.tsx` | Checkout: date/participants summary, localized `<title>` per locale |
| 9 | `/booking/confirmation` | `(public)/booking/confirmation/page.tsx` | Post-payment confirmation, localized title |
| 10 | `/blog` | `(public)/blog/page.tsx` | Article listing (`ItemListSchema` JSON-LD) |
| 11 | `/blog/[slug]` | `(public)/blog/[slug]/page.tsx` | Article (`BlogPostingSchema` + `BreadcrumbListSchema`); gone → "Article No Longer Available" |
| 12 | `/blog/[slug]/preview` | `(public)/blog/[slug]/preview/page.tsx` | `force-dynamic`, signed-token draft preview, `robots: noindex/nofollow`, missing/invalid token → 404 |
| 13 | `/blog/category/[slug]` | `(public)/blog/category/[slug]/page.tsx` | Category article listing (`BreadcrumbListSchema`) |
| 14 | `/privacy` | `(public)/privacy/page.tsx` | Static legal page |
| 15 | `/terms` | `(public)/terms/page.tsx` | Static legal page |

Out of scope (no route, no nav link — verified in `Header.tsx`, `MobileNavPanel.tsx`,
`Footer.tsx`): dedicated `/about` and `/contact` pages, standalone `/checkout` and
`/voucher` pages (checkout = `/booking`; voucher = `/v/[reference]` below).

## 2. Voucher + utility routes (no locale prefix)

| # | Route | Source | Notes |
|---|-------|--------|-------|
| 16 | `/v/[reference]` | `src/app/v/[reference]/page.tsx` | Public voucher verification → `GET /api/public/v/{reference}` (throttle:verify, `no-store`) |
| 17 | `/api/auth/session` | `src/app/api/auth/session/route.ts` | Next.js session helper |
| 18 | `/robots.txt` | `src/app/robots.ts` | Allows `/en/ /es/ /it/`, disallows `/api/`, advertises `${SITE_URL}/sitemap.xml` (resolved: Next.js rewrite proxies `/sitemap.xml` → backend `/api/public/sitemap.xml`, returns 200 XML) |
| 19 | `/_not-found`, `[...notFound]` | `src/app/[locale]/[...notFound]/page.tsx`, `not-found.tsx` | Localized 404 boundary; `ErrorBoundary` + `ErrorFallback` wrap all locale routes |

## 3. Auth pages — `frontend/src/app/[locale]/(auth)/` (8 files)

`/auth/login`, `/auth/register`, `/auth/forgot-password`, `/auth/reset-password`,
`/auth/verify-email`, `/auth/partner-register`, `/partner-register`,
`/partner-invite/[token]` (invitation completion). Authenticated users visiting
login/register are redirected home (e2e `auth-guards.spec.ts`).

## 4. Traveler dashboard — `(traveler)/` (5 routes, auth-guarded, unauthenticated → login)

| # | Route | Source |
|---|-------|--------|
| 20 | `/profile` | `(traveler)/profile/page.tsx` |
| 21 | `/my-bookings` | `(traveler)/my-bookings/page.tsx` |
| 22 | `/my-bookings/[reference]` | `(traveler)/my-bookings/[reference]/page.tsx` (+ `client.tsx`) |
| 23 | `/my-reviews` | `(traveler)/my-reviews/page.tsx` |
| 24 | `/wishlist` | `(traveler)/wishlist/page.tsx` |

## 5. Partner dashboard — `(partner)/partner` (12 routes, `partner` middleware + approval gating)

| # | Route | Source |
|---|-------|--------|
| 25 | `/partner` (overview) | `(partner)/partner/page.tsx` |
| 26 | `/partner/analytics` | `(partner)/partner/analytics/page.tsx` |
| 27 | `/partner/bookings` | `(partner)/partner/bookings/page.tsx` |
| 28 | `/partner/bookings/[reference]` | `(partner)/partner/bookings/[reference]/page.tsx` |
| 29 | `/partner/onboarding` | `(partner)/partner/onboarding/page.tsx` |
| 30 | `/partner/profile` | `(partner)/partner/profile/page.tsx` |
| 31 | `/partner/reviews` | `(partner)/partner/reviews/page.tsx` |
| 32 | `/partner/tours` | `(partner)/partner/tours/page.tsx` |
| 33 | `/partner/tours/create` | `(partner)/partner/tours/create/page.tsx` |
| 34 | `/partner/tours/[id]/edit` | `(partner)/partner/tours/[id]/edit/page.tsx` |
| 35 | `/partner/tours/[id]/availability` | `(partner)/partner/tours/[id]/availability/page.tsx` |
| 36 | `/partner/tours/[id]/pricing` | `(partner)/partner/tours/[id]/pricing/page.tsx` |

Unapproved partners are gated to profile/settings/onboarding/notifications + read-only
tour GETs (`PartnerRoleMiddleware`, 403 `ONBOARDING_STATUS_BLOCKED` otherwise).

## 6. Admin — Filament (`backend/app/Filament/`, served via nginx `/admin` + `/livewire` → Laravel)

Resources (10): `AuditLogResource`, `AvailabilityResource`, `BlogCategoryResource`,
`BlogPostResource`, `BookingResource`, `GovernanceAuditResource`, `PartnerResource`,
`ReviewResource`, `StaticPageResource`, `TourResource` — each with List/Create/View/Edit
/Delete-Search-Filter-Sort-Pagination per Filament conventions.
Pages (2): `Dashboard.php`, `Settings.php`.
Widgets (3): `PlatformOverviewWidget`, `QueueShortcutsWidget`, `RecentBookingsWidget`.
Access: non-admin → denied (Pest `AdminPermissionsTest`, e2e `filament-admin.spec.ts`:
unauthenticated `/admin` redirects to login; wrong password stays on login).

## 7. Backend API — 96 routes (`php artisan route:list --path=api`)

### 7a. Public — `routes/api/public.php`, prefix `/api/public` (51 routes)

- Auth (`throttle:auth`, 10/min): `POST auth/register`, `POST auth/partners/register`,
  `GET auth/partners/invitation/{token}`, `POST auth/partners/invitation/{token}/complete`,
  `POST auth/login`, `POST auth/guest/identity`, `POST auth/guest/convert`,
  `POST auth/forgot-password`, `POST auth/reset-password`,
  `GET auth/email/verify/{id}/{hash}` (named `auth.verify`),
  `POST auth/resend-verification` (sanctum), `PUT auth/change-password` (sanctum),
  `POST auth/logout` (sanctum); `GET auth/me` (sanctum + `throttle:traveler`)
- Account (sanctum): `GET account/sessions`, `POST account/change-password`
- Discovery: `GET search/tours`, `GET tours/{slug}`, `GET categories/`,
  `GET categories/{slug}/tours`, `GET destinations/`, `GET destinations/{slug}/tours`,
  `GET homepage/`, `GET sitemap.xml`
- Booking (sanctum): `POST bookings/`; `POST webhooks/stripe` (signature-verified);
  `POST bookings/{reference}/deterministic-payment/confirm` (local/testing only)
- Voucher verify: `GET v/{reference}` (unauthenticated, `throttle:verify`, `no-store`)
- Reviews: `POST reviews/`, `PUT reviews/{review}` (sanctum); `GET tours/{slug}/reviews`
- Traveler (sanctum + `throttle:traveler`): `GET/… traveler/bookings` (index, summary,
  show, cancel, voucher download), `GET/PUT traveler/profile` (+ change-password),
  `GET/DELETE traveler/sessions`, `GET/POST/DELETE traveler/wishlist` (+ status),
  `GET traveler/reviews`
- Blog: `GET blog/`, `GET blog/category/{slug}`, `GET blog/{slug}/preview`, `GET blog/{slug}`

### 7b. Partner — `routes/api/partner.php`, prefix `/api/partner`, `auth:sanctum` + `partner` (39 routes)

- `POST uploads/signed-url` (jpeg/png ≤5MB allowlist, 15-min expiry)
- Tours CRUD + `drafts/save`, `drafts/latest`, `submit`, `archive` (cross-partner → 404)
- `{tourId}/pricing` CRUD; `{tourId}/availability` rules + exceptions CRUD
- Bookings: index/show/`{reference}/status` PATCH/`cancellation-request`
- Reviews: index, responses POST/PUT · Analytics index · Profile/Settings GET+PUT ·
  `onboarding-status`, `onboarding/status`, `onboarding/resubmit` · Notifications
  index/`{id}/read`/`read-all` · `financial-summary`

### 7c. Admin — `routes/api/admin.php`, prefix `/api/admin`, `auth:sanctum` + `role:admin` (6 routes)

`GET audit/bookings`, `GET audit/bookings/{reference}`, `GET financial-ledger`,
`GET reviews`, `POST reviews/{review}/hide`, `POST reviews/{review}/reinstate`.

## 8. Infrastructure routing (nginx `docker/nginx/nginx.conf`, `docker-compose.yml`)

- `/api/*` → Laravel backend; `/admin`, `/livewire`, `/storage`, `/css`, `/js` → Laravel;
  `/` → Next.js (`nextjs_app`); `:8080` host → container `:80`.
- Browser API calls are same-origin relative (`NEXT_PUBLIC_API_URL` empty in Docker;
  `API_INTERNAL_URL=http://nginx` for SSR). Production browser bundles must keep
  `NEXT_PUBLIC_API_URL` empty/unset or a public origin — verified no hardcoded origin
  in source (only a code comment references localhost).
