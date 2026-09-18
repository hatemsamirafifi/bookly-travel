# Bookly Travel — Product Requirements Document (Consolidated)

## Product Overview

Bookly is a multi-language (English, Spanish, Italian) tour marketplace where travelers discover, book, and pay for tours, and tour partners manage their catalog, bookings, reviews, and analytics. The web frontend is a Next.js 16 App Router application served at `http://localhost:3001` with all routes prefixed by a locale segment (`/en`, `/es`, `/it`). The Laravel REST API is reachable through nginx at `http://localhost:8080/api`.

## User Roles

1. **Guest** — browses, searches, reads blog, and can start a booking checkout.
2. **Traveler** (registered user) — manages bookings, wishlist, reviews, and profile.
3. **Partner** (tour operator) — manages tours, bookings, reviews, analytics, and company profile. Partner accounts require admin approval (onboarding).
4. **Admin** — moderates content (Filament admin panel, out of scope for this frontend test).

### Seeded Test Accounts

| Role | Email | Password |
|---|---|---|
| Traveler | test@example.com | Password123! |
| Partner | partner@bookly.test | password |

The seeded published tour slug is `hidden-gems-rome-walking-tour` (€45.00 per person, group size 1–20). Seeded traveler bookings: `BKO-TEST01` (confirmed, cancellable), `BKO-TEST02` (completed, reviewable), `BKO-TEST03` (completed with review), `BKO-PAST01` (confirmed but inside 24h cancellation window — cancel disabled), `BKO-SOLD0` (fills the 2026-12-01 date to capacity — triggers sold-out 409).

## Core Features

### F1 — Authentication (specs 001, 003, 004, 005)
- Traveler registration with name, email, password; validation errors shown inline.
- Email + password login returning a bearer token; invalid credentials show an error message.
- Logout from header user menu.
- Forgot password (email request) and reset password flows.
- Brute-force protection: repeated failed logins are rate limited (HTTP 429 surfaced as a friendly message).
- Routes: `/en/auth/login`, `/en/auth/register`, `/en/auth/forgot-password`, `/en/auth/reset-password`, `/en/auth/verify-email`.

### F2 — Public Search & Discovery (spec 006, 010)
- Homepage with hero search, featured tours, destinations, and categories (`GET /api/public/homepage`).
- Search results page with query, date, participants, price range, sorting, and pagination (`GET /api/public/search/tours`).
- Category pages (`/en/categories/{slug}`) and destination pages (`/en/destinations/{slug}`).
- Tour detail page (`/en/tours/{slug}`) with gallery, description, highlights, reviews, price, and book CTA (`GET /api/public/tours/{slug}`).

### F3 — Tour Booking & Payment (specs 007, 008)
- Booking page (`/en/booking?tour={slug}`) with date picker, participant selector, and contact details form (name, email, phone).
- `POST /api/public/bookings` creates the booking and returns a Stripe client secret.
- Stripe PaymentElement collects card details; `confirmPayment` completes payment; confirmation page (`/en/booking/confirmation`) shows the booking reference and payment status.
- Sold-out dates return HTTP 409 with an error message before the payment step.
- Price change modal handles price drift between page load and submission.
- Public booking lookup by reference at `/v/{reference}`.

### F4 — Reviews & Ratings (spec 009)
- Travelers can review completed bookings (30-day window) with star rating + comment.
- My Reviews page (`/en/my-reviews`) lists reviews; edit allowed within 48 hours.
- Tour detail pages display visible reviews.

### F5 — Traveler Area
- My Bookings (`/en/my-bookings`): list with status badges; detail page (`/en/my-bookings/{reference}`) with voucher download (`GET .../voucher`) and cancellation (`POST .../cancel`) — free cancellation up to 24h before the tour; inside the window the cancel button is disabled.
- Wishlist (`/en/wishlist`): saved tours grid; add/remove via heart toggle (`/api/public/traveler/wishlist`).
- Profile (`/en/profile`): edit name/locale, change password.
- Unauthenticated access to traveler routes redirects to `/en/auth/login?returnUrl=...`.

### F6 — Partner Tour Management (spec 011)
- Partner dashboard (`/en/partner`) overview.
- Tours list (`/en/partner/tours`) with status; create (`/en/partner/tours/create`), edit (`/en/partner/tours/{id}/edit`), pricing (`.../pricing`), availability (`.../availability`), archive.
- Drafts auto-save (`POST /api/partner/tours/{id}/drafts/save`).
- Image uploads via signed URLs (`POST /api/partner/uploads/signed-url`).

### F7 — Partner Dashboard (spec 012)
- Bookings list (`/en/partner/bookings`) with status updates and cancellation requests.
- Reviews inbox (`/en/partner/reviews`) with public responses.
- Analytics (`/en/partner/analytics`) with date-range metrics and charts.
- Notifications with read/mark-all-read.

### F8 — Partner Onboarding (spec 015)
- Partner registration (`/en/auth/partner-register`) with company details.
- Onboarding status page (`/en/partner/onboarding`) gates the partner area until admin approval; resubmission supported.
- Partner invitation flow via token link (`/en/partner-invite/{token}`).

### F9 — Notifications & Vouchers (spec 014)
- Partner notifications list, mark read, mark all read.
- Traveler voucher download for confirmed bookings (PDF).

### F10 — Blog / Travel Insights (spec 016)
- Blog listing (`/en/blog`) with categories and pagination; post detail (`/en/blog/{slug}`); category filter (`/en/blog/category/{slug}`); draft preview via signed token (`/en/blog/{slug}/preview?token=...`).

## Non-Functional Requirements
- All pages localized in en/es/it via `next-intl`; locale switcher in header.
- Bearer-token (Sanctum) API authentication; no session cookies.
- API errors surface as localized, user-friendly messages (422 validation, 404 not found, 409 conflict, 429 rate limit).

## Known Test Limitations
- Stripe key is a placeholder (`pk_test_placeholder`): the payment element step cannot complete with real card fields; test up to booking creation / sold-out conflict.
- Email is not delivered locally: forgot-password and email verification can only be tested up to the request step.
- Newly registered partners are pending approval; use the seeded partner account for partner-area tests.
