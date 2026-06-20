# Bookly Specification Strategy — Phase 1

> **Version**: 2.0.0
> **Date**: 2026-04-11 (revised 2026-05-13)
> **Constitution**: v1.1.0
> **Status**: In Progress

---

## 1. Overview

Bookly is a tours-only marketplace platform. Phase 1 delivers the core
booking flow (MVP) across three application surfaces:

| Surface | Technology | Purpose |
|---------|-----------|---------|
| Public traveler website | Next.js 16 (SSR/SSG) | Search, discover, and book tours |
| Partner dashboard | Next.js 16 | Tour CRUD, pricing, availability |
| Admin dashboard | Laravel Filament | Moderation, approval, operations |

All surfaces consume a shared **Laravel API-only backend** with
PostgreSQL, Redis, and Stripe.

This document defines the specification decomposition, execution order,
and precise `/speckit.specify` prompts for all Phase 1 features.

> **NOTE — v2.0.0 Revision**: The original v1.0.0 plan listed 11
> features numbered 001–011. During implementation, the plan was
> re-decomposed: the original 001 (Traveler Auth) was expanded into
> 5 granular phases (001–005), and subsequent features were renumbered
> accordingly. This revision updates all sections to reflect the
> actual project structure. See §4.5 for a mapping from the original
> numbering to the current numbering.

---

## 2. Decision Log

These decisions were resolved during planning and are binding for all
specs.

| # | Question | Decision |
|---|----------|----------|
| Q1 | Reviews in Phase 1? | **Yes (MVP)** — submit only, rating + comment, displayed on tour pages. No partner replies, moderation, or fraud detection. |
| Q2 | Multi-staff partners? | **No** — one account per partner. No roles or team features. |
| Q3 | Refunds? | **Admin-only manual** via Stripe dashboard. Booking status → `refunded`, ledger entry created. No automation, no partial refunds. |
| Q4 | Translation? | **Yes** — English, Spanish, Italian in Phase 1. Architecture prepared for future i18n expansion. |
| Q5 | Notification channels? | **Email only** (queued via Redis). No SMS or push. |
| Q6 | Account vs guest checkout? | **Guest checkout enabled**. Account auto-created after booking. |
| Q7 | Tour images? | **Yes** — multiple image uploads with cover image. Stored in Cloudflare R2. |
| Q8 | Implementation map? | **Dropped**. Constitution + feature specs are sufficient. |
| Q9 | Admin dashboard? | **Filament admin dashboard** included. Partner dashboard is basic Next.js (CRUD tours). |
| Q10 | Booking completion? | **Auto-complete** after tour date via scheduled job. |
| Q11 | Cancellation? | **Minimal** — traveler can cancel before tour date. No penalties, no refund automation. |
| Q12 | Spec structure? | **Approved** — 11 feature specs as defined below. |

---

## 3. Constitution Patch — ✅ Complete

The constitution has been patched to v1.0.1, reflecting:

1. **Filament** added to the approved stack table as the admin dashboard
   framework.
2. **Three-surface architecture** codified:
   - Public website → Next.js 16 (SSR/SSG)
   - Partner dashboard → Next.js 16
   - Admin dashboard → Laravel Filament (server-rendered)

### Amendment v1.1.0 — Internal Admin Exception (2026-06-20)

The constitution was amended from v1.0.1 → **v1.1.0** to formally ratify
the API-first exception for the admin surface. The "Internal Admin
Exception" subsection was added under **API-First**, stating that Laravel
Filament is the sole approved server-rendered surface, applies only to
authenticated admin users, and MUST NOT be extended to public or
partner-facing surfaces — which remain API-first Next.js 16. Filament
was also added to the Approved Core Stack table as the Admin Surface row.
No other principles changed; API-First remains the default architecture.

---

## 4. Feature Decomposition

### 4.1 Spec Directory Structure (Actual)

```text
specs/
├── 001-traveler-auth/               ✅ Implemented
│   ├── spec.md
│   ├── plan.md
│   ├── tasks.md
│   ├── research.md
│   ├── data-model.md
│   ├── quickstart.md
│   ├── contracts/
│   └── checklists/
├── 002-foundational-implementation/ ✅ Implemented
│   ├── spec.md
│   └── checklists/
├── 003-traveler-registration/       ✅ Implemented
│   ├── spec.md
│   ├── plan.md
│   ├── tasks.md
│   ├── research.md
│   ├── data-model.md
│   ├── quickstart.md
│   ├── contracts/
│   └── checklists/
├── 004-traveler-signin/             ✅ Implemented
│   ├── spec.md
│   ├── plan.md
│   ├── tasks.md
│   ├── research.md
│   ├── data-model.md
│   ├── quickstart.md
│   ├── contracts/
│   └── checklists/
├── 005-brute-force-protection/      ✅ Implemented
│   ├── spec.md
│   ├── plan.md
│   ├── tasks.md
│   ├── research.md
│   ├── data-model.md
│   ├── quickstart.md
│   ├── contracts/
│   └── checklists/
├── 006-public-search-discovery/     ✅ Implemented
│   ├── spec.md
│   ├── plan.md
│   ├── tasks.md
│   ├── research.md
│   ├── data-model.md
│   ├── quickstart.md
│   ├── contracts/
│   └── checklists/
├── 007-tour-booking/                ✅ Implemented
│   ├── spec.md
│   ├── plan.md
│   ├── tasks.md
│   ├── research.md
│   ├── data-model.md
│   ├── quickstart.md
│   ├── contracts/
│   └── checklists/
├── 008-payment-processing/          ✅ Implemented (Stripe payments & ledger)
│   ├── spec.md
│   ├── plan.md
│   ├── tasks.md
│   ├── research.md
│   ├── data-model.md
│   ├── quickstart.md
│   ├── contracts/
│   └── checklists/
├── 009-reviews-ratings/             ✅ Implemented (Ratings & traveler reviews)
│   ├── spec.md
│   ├── plan.md
│   ├── tasks.md
│   ├── research.md
│   ├── data-model.md
│   ├── quickstart.md
│   ├── contracts/
│   │   └── api.md
│   └── checklists/
├── 010-public-frontend/             ✅ Implemented (Public search, booking, payment)
│   ├── spec.md
│   ├── plan.md
│   ├── tasks.md
│   ├── research.md
│   ├── data-model.md
│   ├── quickstart.md
│   ├── contracts/
│   │   ├── auth-api.md
│   │   ├── booking-api.md
│   │   └── tours-api.md
│   └── checklists/
│       └── requirements.md
├── 011-tour-management/             ✅ Implemented (Traveler dashboard, bookings, profile & wishlist)
│   ├── spec.md
│   ├── plan.md
│   ├── research.md
│   ├── data-model.md
│   ├── quickstart.md
│   ├── contracts/
│   │   ├── bookings-api.contract.md
│   │   ├── profile-api.contract.md
│   │   ├── reviews-api.contract.md
│   │   └── wishlist-api.contract.md
│   └── checklists/
│       └── requirements.md
├── 012-TBD/                         🔲 Not yet started
├── 013-TBD/                         🔲 Not yet started
├── 014-TBD/                         🔲 Not yet started
└── 015-TBD/                         🔲 Not yet started
```

### 4.2 Feature Summary Table

#### Completed Features (001–011)

| Spec | Feature | Surface(s) | Key Domains | Status |
|------|---------|-----------|-------------|--------|
| 001 | Traveler Auth (Architecture & Spec) | Backend | Auth | ✅ Implemented |
| 002 | Foundational Implementation | Backend, Frontend | Auth, Infrastructure | ✅ Implemented |
| 003 | Traveler Registration | Public, Backend | Auth | ✅ Implemented |
| 004 | Traveler Sign-in | Public, Backend | Auth | ✅ Implemented |
| 005 | Brute Force Protection | Backend | Auth, Security | ✅ Implemented |
| 006 | Public Search & Discovery | Public, Backend | Tours, Search, SEO | ✅ Implemented |
| 007 | Tour Booking | Public, Backend | Bookings, Availability | ✅ Implemented |
| 008 | Payment Processing | Public, Backend | Payments, Finance, Stripe | ✅ Implemented |
| 009 | Reviews & Ratings | Public, Backend | Reviews | ✅ Implemented |
| 010 | Public Frontend | Public, Backend | Discovery, Checkout, Stripe | ✅ Implemented |
| 011 | Tour Management (Traveler Account) | Public, Backend | Traveler Area, Booking Details, Profile | ✅ Implemented |

#### Remaining Features (012–015) — Not Yet Specified

These features from the original strategy have NOT been specified yet
and need to be created as new specs:

| Spec | Feature | Surface(s) | Key Domains | Depends On |
|------|---------|-----------|-------------|------------|
| 012 | Pricing & Availability | Partner, Backend | Pricing, Availability | 015 |
| 013 | Admin Moderation | Admin, Backend | Admin Ops, Partners, Tours | 015 |
| 014 | Notifications & Vouchers | Backend | Notifications | 007 |
| 015 | Partner Onboarding | Partner, Admin, Backend | Partners, Auth | — |

### 4.3 Dependency Graph (Updated)

```
--- COMPLETED ---
001 Traveler Auth ──→ 002 Foundation ──→ 003 Registration ──→ 004 Sign-in ──→ 005 Brute Force
                                                                                     │
                                                                              006 Search ←──┘
                                                                                     │
                                                                              007 Booking
                                                                                 │
                                                           ┌─────────────────────┼────────────┐
                                                           ↓                     ↓            ↓
                                                    008 Payments          009 Reviews    (future)
                                                           │                     │
                                                           └──────────┬──────────┘
                                                                      │
                                                                      ▼
                                                            010 Public Frontend
                                                                      │
                                                                      ▼
                                                            011 Tour Management

--- REMAINING ---
015 Partner Onboarding ──→ 012 Pricing & Availability
                               │
                               └──→ 013 Admin Moderation

014 Notifications & Vouchers  (depends on 007 ✅)
010 Public Frontend           (✅ Done)
011 Tour Management           (✅ Done, depends on 001 ✅, 007 ✅)
```

### 4.4 Execution Order (Updated)

| Order | Spec | Feature | Depends On | Status |
|-------|------|---------|------------|--------|
| 1 | 001 | Traveler Auth | — | ✅ Done |
| 2 | 002 | Foundational Implementation | 001 | ✅ Done |
| 3 | 003 | Traveler Registration | 002 | ✅ Done |
| 4 | 004 | Traveler Sign-in | 002 | ✅ Done |
| 5 | 005 | Brute Force Protection | 004 | ✅ Done |
| 6 | 006 | Public Search & Discovery | — | ✅ Done |
| 7 | 007 | Tour Booking | 001, 006 | ✅ Done |
| 8 | 008 | Payment Processing | 007 | ✅ Done |
| 9 | 009 | Reviews & Ratings | 007, 008 | ✅ Done |
| 10 | 010 | Public Frontend | — | ✅ Done |
| 11 | 011 | Tour Management (Traveler Account) | 001, 007 | ✅ Done |
| 12 | 012 | Pricing & Availability | 015 | 🔲 Not started |
| 13 | 013 | Admin Moderation | 015 | 🔲 Not started |
| 14 | 014 | Notifications & Vouchers | 007 | 🔲 Not started |
| 15 | 015 | Partner Onboarding | — | 🔲 Not started |

### 4.5 Original → Current Numbering Mapping

For reference, this maps the original v1.0.0 feature numbers to the
current v2.0.0 numbers in the actual workspace directory structure:

| Original (v1.0.0) | Feature | Current (v2.0.0) | Directory in Workspace | Notes |
|--------------------|---------|------------------|-----------------------|-------|
| 001 | Traveler Auth | 001–005 | `001-traveler-auth` through `005-brute-force-protection` | Split into 5 granular auth phases |
| 002 | Partner Onboarding | 015 | Create `015-partner-onboarding` | Partner dashboard onboarding |
| 003 | Tour Management | 011 | `011-tour-management` | Traveler Dashboard, bookings, wishlist |
| 004 | Pricing & Availability | 012 | Create `012-pricing-availability` | Partner pricing & capacity calendar |
| 005 | Admin Moderation | 013 | Create `013-admin-moderation` | Filament admin dashboard widgets |
| 006 | Public Search & Discovery | 010 (consolidated) | `010-public-frontend` | Homepage, tour listings, filters, category |
| 007 | Booking & Checkout | 010 (consolidated) | `010-public-frontend` | Booking details, customer inputs, checkout |
| 008 | Payments & Finance | 010 (consolidated) | `010-public-frontend` | Stripe Elements, ledger tracking, confirmation |
| 009 | Notifications & Vouchers | 014 | Create `014-notifications-vouchers` | Queued async emails, PDF generation |
| 010 | Traveler Reviews | 009 | `009-reviews-ratings` | Ratings, reviews on detail page |
| 011 | Traveler Account & Bookings | 011 | `011-tour-management` | Authenticated traveler dashboard & settings |

---

## 5. Spec Kit Workflow Per Feature

For each numbered feature, execute in order:

```text
/speckit.specify   → Create the feature specification
/speckit.clarify   → Identify and resolve ambiguities
/speckit.plan      → Generate implementation plan with constitution check
/speckit.tasks     → Generate task breakdown
```

Do NOT skip `/speckit.clarify`. Under-specified areas caught early
prevent rework during implementation.

---

## 6. `/speckit.specify` Prompts

### 6.1 — 001 Traveler Authentication

```text
/speckit.specify Create the traveler account and authentication
specification for Bookly, a tours-only marketplace.

Phase 1 decisions:
- Guest checkout is ENABLED. Travelers MUST be able to book tours
  without creating an account first.
- After a guest booking, the system MUST offer automatic account
  creation using the information provided during checkout.
- Travelers who choose to register MUST be able to sign up, sign
  in, sign out, and manage their session.
- Authentication uses Laravel Sanctum (per constitution).
- No social login or OAuth in Phase 1.

The spec MUST define:
- Registration flow (email/password)
- Sign-in and sign-out behavior
- Session management and expiry rules
- Guest checkout identity handling (email capture, deduplication)
- Automatic account creation from guest booking data
- Account ownership and uniqueness rules (email-based)
- Validation requirements for all inputs
- Failure scenarios (duplicate email, invalid credentials,
  expired sessions)
- Password reset flow
- Success criteria that are measurable and testable

The spec MUST NOT prescribe database schema or API contracts.
Defer to the constitution for stack decisions.
```

---

### 6.2 — 002 Partner Onboarding

```text
/speckit.specify Create the partner onboarding specification for
Bookly, a multi-partner tours-only marketplace.

Phase 1 decisions:
- ONE account per partner. No multi-staff access or roles within
  a partner organization.
- Partners may self-register or be invited by an admin.
- Admin approval is required before a partner can create tours.

The spec MUST define:
- Partner registration flow (self-service)
- Admin invitation flow
- Required onboarding information (business name, contact details,
  description, tax/legal identifiers as needed)
- Partner account states (e.g., pending, approved, suspended)
- Admin approval gate before tour creation access
- Partner profile management after approval
- Ownership boundaries (partner sees only their own data)
- Validation requirements
- Failure and edge cases (duplicate registration, rejected
  application, re-application rules)
- Success criteria

The spec MUST NOT include multi-staff, roles, or team features.
One partner = one account in Phase 1.
```

---

### 6.3 — 003 Tour Management

```text
/speckit.specify Create the tour creation and management
specification for Bookly partners in Phase 1.

Phase 1 decisions:
- Tours support MULTIPLE LANGUAGES: English, Spanish, and Italian.
  All translatable content (title, description, inclusions,
  exclusions, highlights) MUST support these three languages.
- Tours require MULTIPLE IMAGE uploads with a designated cover
  image. Images are stored in Cloudflare R2.
- Tours MUST NOT be publicly visible until admin approval
  (constitution Principle IV).
- Tour statuses: draft, pending_review, published, rejected,
  archived.

The spec MUST define:
- Tour creation flow from the partner dashboard
- Required tour fields (title, description, duration, location,
  category, highlights, inclusions, exclusions, meeting point,
  cancellation info, minimum/maximum group size)
- Multi-language content entry for EN, ES, IT
- Image upload requirements (allowed types, max size, max count,
  cover image designation, ordering)
- Draft saving and editing behavior
- Submission for review (draft → pending_review)
- Editing after rejection (with rejection reason visible)
- Editing after publication (re-review requirements if any)
- Ownership enforcement (partner sees/edits only their own tours)
- Archiving behavior
- Validation requirements for all fields
- Edge cases (incomplete drafts, image upload failures, concurrent
  edits)
- Success criteria

The spec MUST NOT define pricing, availability, or booking behavior
(those are separate specs).
```

---

### 6.4 — 004 Pricing and Availability

```text
/speckit.specify Create the pricing and availability management
specification for Bookly tours in Phase 1.

Phase 1 context:
- Partners set pricing and availability for their own tours.
- Only tours with valid pricing AND available dates are sellable.
- Direct booking only (instant confirmation, no request-to-book).

The spec MUST define:
- Pricing model for Phase 1 (per-person pricing at minimum;
  clarify if tiered pricing e.g., adult/child/infant is needed)
- Currency handling (which currencies are supported in Phase 1)
- Availability calendar management (which dates/times a tour runs)
- Capacity per departure (max participants per date/time slot)
- Sellable inventory rules (how the system determines a tour is
  bookable on a given date)
- Real-time availability validation at booking time
- Conflict prevention (overbooking protection)
- What happens when pricing changes after a booking is made
  (booked price is snapshotted and immutable)
- What happens when availability is removed after a booking exists
- Partner UX for managing pricing and calendar
- Validation requirements
- Edge cases (zero availability, past dates, overlapping time
  slots)
- Success criteria
```

---

### 6.5 — 005 Admin Moderation

```text
/speckit.specify Create the admin moderation and operations
specification for Bookly Phase 1.

Phase 1 decisions:
- The admin dashboard is built with Laravel Filament (server-
  rendered, separate from the Next.js public/partner surfaces).
- Admin capabilities include: partner approval, tour approval,
  booking oversight, and basic platform management.
- Manual refunds are initiated via Stripe dashboard (not in-app
  automation), but the booking status MUST update to 'refunded'
  and a ledger entry MUST be created.
- Audit logging is mandatory for all admin actions (per
  constitution).

The spec MUST define:
- Admin roles and permission boundaries
- Partner moderation workflow (review, approve, reject, suspend)
- Tour moderation workflow (review, approve with publication,
  reject with reason, unpublish)
- Booking management view (list, filter, view details, status)
- Refund status tracking (when manually processed via Stripe)
- Dashboard views and information architecture
- Audit log requirements (what is logged, what fields, retention)
- Separation between admin powers and partner permissions
- Notification to partners on approval/rejection decisions
- Edge cases (approve then unpublish, reject resubmission,
  suspended partner with active tours)
- Success criteria

The spec MUST NOT duplicate booking lifecycle logic (defined in
007) or payment processing logic (defined in 008).
```

---

### 6.6 — 006 Public Search and Discovery

```text
/speckit.specify Create the public search and discovery
specification for Bookly, a tours-only marketplace.

Phase 1 decisions:
- Multi-language support: English, Spanish, Italian. Public pages
  MUST be served in all three languages with localized routes.
- Only admin-approved (published) tours with valid pricing and
  availability appear in search results.
- SEO-first: all public pages use Next.js SSR/SSG for crawlable
  HTML (per constitution).
- Search is abstracted via Laravel Scout (not direct SQL).

The spec MUST define:
- Homepage discovery experience (featured tours, categories,
  destinations)
- Search behavior (text search, what fields are searchable)
- Filter options (location/destination, category, price range,
  duration, date, language of tour)
- Sort options (relevance, price, rating, newest)
- Tour listing cards (what information is shown: title, cover
  image, price, rating, location, duration)
- Tour detail page (full information, image gallery, pricing,
  availability calendar, booking CTA, reviews)
- URL structure and localized routes (/en/tours/..., /es/tours/...,
  /it/tours/...)
- Canonical tags and sitemap generation
- Empty states (no results, no tours in category)
- Handling of unpublished, unavailable, or sold-out tours
  (excluded from results, 404 or redirect if accessed directly)
- Mobile-first responsive behavior
- Performance targets (Lighthouse score 90 or above, per
  constitution)
- Meta tags, Open Graph, structured data (schema.org)
- Pagination behavior
- Success criteria
```

---

### 6.7 — 007 Booking and Checkout

```text
/speckit.specify Create the booking and checkout specification for
Bookly Phase 1.

Phase 1 decisions:
- INSTANT BOOKING ONLY with direct confirmation (constitution
  Principle III).
- GUEST CHECKOUT ENABLED. Travelers can book without an account.
  After booking, the system offers automatic account creation.
- Traveler cancellation is allowed BEFORE the tour date. Status
  changes to 'cancelled'. No penalties or automated refunds.
- Booking auto-completes after the tour date passes, via a
  scheduled background job. Status changes to 'completed'.
- Idempotent booking creation (duplicate submission protection,
  per constitution).

The spec MUST define:
- Booking lifecycle states: created, confirmed, completed, with
  branches to cancelled and refunded
- Checkout flow steps (select date/participants, enter details,
  payment, confirmation)
- Required customer inputs (name, email, phone, participant
  count, special requests)
- Guest vs authenticated checkout differences
- Automatic account creation offer after guest booking
- Booking confirmation behavior (what the traveler sees/receives)
- Financial snapshot at booking time (price, currency, fees are
  locked and immutable)
- Duplicate submission protection (idempotency mechanism)
- Availability validation at checkout time (race condition
  handling)
- Cancellation flow (traveler-initiated, before tour date only)
- Auto-completion scheduled job behavior (timing, edge cases)
- Booking reference/confirmation code generation
- Failure scenarios (payment fails, availability gone, session
  expired)
- Edge cases (booking for past date, cancellation on tour day,
  double-booking same traveler)
- Success criteria

The spec MUST NOT define payment processing details (see 008) or
notification/voucher delivery (see 009).
```

---

### 6.8 — 008 Payments and Finance

```text
/speckit.specify Create the payments and financial tracking
specification for Bookly Phase 1.

Phase 1 decisions:
- Stripe is the payment gateway (per constitution). Architecture
  MUST allow future gateway extensibility.
- Full payment at booking time. No deposits or installments.
- Manual refunds only (admin processes via Stripe dashboard).
  The system tracks refund status and creates a ledger entry.
- No partial refunds in Phase 1.
- No automated partner payouts in Phase 1 (constitution out-of-
  scope).
- Every financial transaction MUST produce an immutable ledger
  entry (constitution Principle V).

The spec MUST define:
- Stripe integration approach (Payment Intents API recommended)
- Payment flow triggered from booking checkout
- Idempotency key strategy for payment creation
- Successful payment confirmation and booking linkage
- Failed payment handling (retry, user messaging, booking cleanup)
- Financial ledger requirements (what is recorded per transaction:
  amount, currency, type, booking reference, Stripe reference,
  timestamp, status)
- Ledger immutability rules (append-only, no edits, no deletes)
- Refund tracking (manual refund in Stripe, webhook or manual
  sync, status update plus ledger entry)
- Currency handling (which currencies in Phase 1, conversion
  rules if any)
- Platform fee/commission model (if applicable in Phase 1, or
  deferred)
- Webhook handling for Stripe events (payment success, failure,
  refund)
- Security requirements (no card data stored, PCI compliance
  via Stripe Elements/Checkout)
- Edge cases (webhook delivery failure, duplicate webhooks,
  payment succeeded but booking failed)
- Success criteria
```

---

### 6.9 — 009 Notifications and Vouchers

```text
/speckit.specify Create the notification delivery and booking
voucher specification for Bookly Phase 1.

Phase 1 decisions:
- EMAIL ONLY. No SMS or push notifications.
- All emails MUST be queued via Redis (per constitution async
  policy).
- All emails MUST be retry-safe (idempotent delivery).
- Multi-language support: emails MUST be sent in the traveler's
  preferred language (English, Spanish, or Italian).

The spec MUST define:
- Required email notifications and their triggers:
  * Booking confirmation (after successful payment)
  * Booking cancellation confirmation
  * Booking voucher delivery
  * Partner notification on new booking
  * Partner notification on booking cancellation
  * Partner approval/rejection notification (from admin moderation)
- Voucher requirements:
  * What information the voucher contains (booking reference,
    tour name, date, time, participants, meeting point, QR code
    or reference code)
  * Voucher format (PDF attachment and/or inline email content)
  * Voucher delivery timing (with confirmation email or separate)
  * Voucher accessibility (downloadable from traveler account if
    account exists)
- Email content requirements (what each email MUST include)
- Multi-language email rendering (template per language)
- Queue and retry behavior (max retries, backoff strategy,
  dead letter handling)
- Failure handling (email delivery fails, what happens to the
  booking? Answer: booking is NOT affected, failure is logged)
- Sender identity and reply-to configuration
- Edge cases (guest without account accessing voucher, email
  bounce, duplicate delivery prevention)
- Success criteria
```

---

### 6.10 — 010 Traveler Reviews

```text
/speckit.specify Create the traveler review specification for
Bookly Phase 1.

Phase 1 decisions:
- Reviews are allowed ONLY for completed bookings (constitution
  Principle VI).
- MVP scope: submit review with rating plus comment.
- Reviews are displayed on tour detail pages.
- One review per booking per traveler.
- NO partner replies in Phase 1.
- NO advanced moderation or fraud detection in Phase 1.

The spec MUST define:
- Review submission flow (where and when the traveler can leave
  a review)
- Review prompt/reminder (email after booking completes, or
  in-account prompt, or both)
- Required review fields (numeric rating scale, text comment)
- Rating scale definition (1-5 stars recommended; confirm)
- Validation rules (minimum/maximum comment length, profanity
  filter or not)
- Review display on tour detail pages (average rating, individual
  reviews, sort order, pagination)
- Review display on tour listing cards (average rating, review
  count)
- Review eligibility verification (booking MUST be in 'completed'
  status, one review per booking)
- Review editing and deletion rules (can traveler edit/delete
  their review?)
- Multi-language considerations (review is in whatever language
  the traveler writes; no translation requirement)
- Basic moderation (admin can hide/remove inappropriate reviews
  via Filament dashboard)
- Edge cases (booking cancelled after review window opens,
  traveler has no account because of guest checkout)
- Success criteria
```

---

### 6.11 — 011 Traveler Account and Bookings

```text
/speckit.specify Create the traveler account and booking management
specification for Bookly Phase 1.

Phase 1 context:
- Travelers may have accounts from registration OR from automatic
  account creation after guest checkout.
- This spec covers the authenticated traveler experience AFTER
  sign-in (the auth flows are defined in spec 001).

The spec MUST define:
- Traveler dashboard overview (upcoming bookings, past bookings,
  account status)
- Booking list view (filterable by status: confirmed, completed,
  cancelled, refunded)
- Booking detail view (tour info, date, participants, payment
  summary, voucher download, cancellation action, review action)
- Cancellation action from booking detail (links to cancellation
  flow defined in spec 007)
- Review action from booking detail (links to review flow defined
  in spec 010)
- Voucher download/access from booking detail
- Profile management (name, email, phone, password change)
- Account created via guest checkout: what does the traveler see
  on first login? (bookings already linked)
- Multi-language preference setting (English, Spanish, Italian)
- Booking history retention (how far back can travelers see?)
- Edge cases (account with no bookings, merged guest bookings,
  deleted/suspended account)
- Success criteria

The spec MUST NOT redefine booking lifecycle, payment details,
or notification behavior (those are in specs 007, 008, 009).
```

---

## 7. Pre-Execution Checklist

- [x] Constitution patched to v1.0.1
- [x] Constitution amended to v1.1.0 (Internal Admin Exception under API-First)
- [x] `specs/` directory created at project root
- [x] This document saved and committed
- [x] Docker dev environment operational
- [x] CI pipeline configured (GitHub Actions)

---

## 8. Post-Specification Workflow

For each feature spec, the workflow is:

1. Each spec feeds into `/speckit.plan` for implementation design
2. Each plan feeds into `/speckit.tasks` for task breakdown
3. Implementation proceeds spec-by-spec in dependency order
4. Each spec is independently testable and deliverable
5. Commits reference the spec number (e.g., `feat(008): payment processing`)

---

## 9. Current Project Status (as of 2026-05-31)

### Progress Summary

| Category | Count | Features |
|----------|-------|----------|
| Features implemented | 11 | 001–007, 008, 009, 010, 011 |
| Features ready to build | 0 | |
| Features partially specified | 0 | |
| Features not yet specified | 4 | 012–015 |
| **Total Phase 1 features** | **15** | |

### Recommended Next Actions

1. **Specify 014 (Notifications & Vouchers)** — all dependencies satisfied (007 ✅). Can be specified in parallel.
2. **Specify 015 (Partner Onboarding)** — no dependencies. Unlocks 012 → 013 chain.
