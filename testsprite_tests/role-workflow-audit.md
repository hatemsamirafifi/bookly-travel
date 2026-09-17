# Bookly Travel — Role Workflow Audit (Guest · Traveler · Partner · Admin)

Date: 2026-09-17 · Evidence: Pest 645/645 · Jest 179/179 · Playwright 566/566
(post-fix) · `run_all_tests.py` 15/15 · live probes via nginx (`http://nginx`).

## GUEST (unauthenticated)

| Workflow | Evidence | Result |
|----------|----------|--------|
| Home renders, search hero, featured tours, categories, destinations (en/es/it) | e2e `homepage`, `i18n`, `smoke`; TC004/TC007/TC010 | PASS |
| Keyword search + category/destination/price/duration filters + sort + pagination | e2e `search`; TC010; Pest `Search/*` | PASS |
| Empty results + special-character queries render deliberate empty state | e2e `search`, `invalid-slugs`; Pest search tests | PASS |
| Category/destination index + detail; invalid slug → 404 page + API 404 | e2e `category-destination`, `invalid-slugs`; live curl 404 | PASS |
| Tour detail (gallery, pricing, availability, reviews, Book Now → `/booking?…`) | e2e `tour-detail` incl. tablet/desktop/mobile no-overflow; TC005/TC011 | PASS |
| Blog listing/category/article; invalid slug 404; gone article "Removed" state | e2e `blog-list/detail/seo/i18n`; Pest `Blog/*` | PASS |
| Draft preview: missing token → 404; bad signature → backend 403 → 404 page, no leak, `noindex` | e2e `blog-preview` (2/2 after TEST-BUG fix); live curl 403 + 404-page | PASS |
| Voucher verification `/v/[reference]` (unauthenticated, rate-limited, no-store, no PII) | e2e `voucher-verification`; TC013 | PASS |
| Registration (validation, duplicate, verification queued) / login (invalid → localized error, no enumeration oracle beyond contract) / logout / forgot + reset (incl. invalid/expired token) / session restore + expiry banner + returnUrl | e2e `auth`, `auth-guards`, `auth-navigation`, `register-error-i18n`; TC001/TC003/TC008; Pest `Auth/*`, `Security/*` | PASS |
| Privacy/terms render; header/mobile-nav/footer/breadcrumb/CTA links resolve with locale preserved | e2e `auth-navigation`, `console-health`, `smoke`; no about/contact links exist (out of scope, no dead links) | PASS |

## TRAVELER (authenticated)

| Workflow | Evidence | Result |
|----------|----------|--------|
| Profile view/edit, validation errors localized, persistence across refresh, password change (wrong-current/mismatch/short rejected) | e2e `profile` (+a11y); Pest `Traveler/ProfileTest` (9/9) | PASS |
| Booking lifecycle: tour → date → participants → checkout → (deterministic) payment → confirmation → detail → voucher download | e2e `booking`, `checkout`, `payment`, `booking-detail`; TC002/TC006/TC011/TC013 | PASS |
| Checkout abandon/return/refresh; duplicate submission safe (idempotent reference) | e2e `checkout`, `payment`; Pest `Payment/*`, `Booking/*` | PASS |
| My-bookings list (status filter, pagination, summary counts) + detail per status (pending/confirmed/completed/cancelled) + invalid reference 404 + чужой reference 403 | e2e `my-bookings`, `booking-detail`, `traveler-dashboard`; Pest `Booking/TravelerBookingsTest` | PASS |
| Cancellation: eligible → cancel + refund path; ineligible → rejected; duplicate attempt safe | e2e `cancel-booking` (modal, keep-booking, failure handling); Pest booking tests | PASS |
| Wishlist: add/remove, 409 duplicate, 404 unknown tour, persistence across refresh, hydration on login, cleared view on logout, empty state | e2e `wishlist` (+a11y); TC015; Pest `Traveler/WishlistTest` (8/8) | PASS |
| Reviews: completed-booking review, rating + text validation, duplicate rejected, edit, чужой review 403, partner reply visible | e2e `review-submission`, `my-reviews` (+a11y); Pest `Reviews/*` | PASS |

## PARTNER (approved; unapproved gating verified)

| Workflow | Evidence | Result |
|----------|----------|--------|
| Registration → onboarding status; approved login; unapproved login gated to profile/settings/onboarding/notifications + tour GETs; non-partner → 404 (surface hidden); logout; refresh persistence | e2e `partner/*`, `auth-guards`; TC009/TC012; Pest `Partner/*`, `Auth/*` | PASS |
| Dashboard overview: KPIs, recent bookings/reviews, unread counts, charts (currency/percent formats), empty/loading/error states | e2e `partner/dashboard`, `partner/analytics`, `partner/navigation`; TC012 | PASS |
| Tours: list (no cross-partner rows), create (validation, localization, images, categories, destinations, duration), edit, archive, submit-for-review, drafts save/latest; чужой tour → 404 | e2e `partner/tours`, `tour-create`, `tour-edit`; Pest `Partner/Tour*` | PASS |
| Pricing: create/update/delete tiers, participant counts, currency, validation, public reflection on tour detail | e2e `partner` tours/pricing paths; Pest `Partner/*Pricing*` | PASS |
| Availability: rules + exceptions CRUD, date ranges, capacity, sold-out → "Currently Unavailable" publicly, invalid dates rejected | e2e `tour-detail` + partner specs; Pest `Partner/*Availability*`, `Admin/AvailabilityReadonlyTest` | PASS |
| Bookings: list/detail own bookings, cross-partner → 404, status PATCH (чужой tour → 403), cancellation-request flow, filter/pagination/empty states | e2e `partner/bookings`; TC014; Pest `Partner/Booking*`, `Booking/PartnerBookingsTest` | PASS |
| Reviews: list, reply POST/PUT, validation, ownership, duplicate-reply handling, чужой notifications unreadable | e2e `partner/reviews`; Pest `Partner/NotificationTest`, `Reviews/*` | PASS |
| Profile/settings: every editable field persists across save → refresh → logout → login | e2e `partner/profile`; Pest `Partner/Profile*` | PASS |

## ADMIN (Filament + admin API)

| Workflow | Evidence | Result |
|----------|----------|--------|
| Panel login, dashboard widgets (overview, queue shortcuts, recent bookings), wrong-password stays, unauthenticated redirect | e2e `filament-admin` (chromium + mobile); Pest `Admin/AdminPermissionsTest`, `Admin/Filament/*` | PASS |
| Per resource (AuditLog, Availability, BlogCategory, BlogPost, Booking, GovernanceAudit, Partner, Review, StaticPage, Tour): list/create/view/edit/delete, search/filter/sort/pagination, validation, authorization, empty/error states | Pest `Admin/*` (incl. governance-flag gating, availability read-only for booking-managers); e2e admin specs | PASS |
| Partner approvals/invitations, tour moderation, review hide/reinstate, audit booking ledger, financial ledger | Pest `Admin/*`, `Payment/*`; admin API 6 routes verified | PASS |

## Cross-role negative matrix (all PASS — see security-boundary-audit.md)

Traveler↔Traveler 403/404 · Partner↔Partner 404-hiding · Traveler→Partner 404 ·
Partner→Admin 403 · Unauthenticated→Protected 401/redirect · No leakage in any
direction (645 Pest incl. `Security/*`, 566 Playwright incl. guards, TC suite).
