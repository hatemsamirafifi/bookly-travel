# Implementation Plan: Tour Booking

**Branch**: `007-tour-booking` | **Date**: 2026-05-09 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/007-tour-booking/spec.md`

## Summary

Deliver the tour booking flow for Bookly travelers — instant booking confirmation with real-time availability validation, idempotent creation, booking lifecycle management (confirmed → completed/cancelled/no_show), traveler booking management, partner booking visibility, and immutable audit trails. The booking domain integrates with existing tours/auth (specs 003/004), triggers payment events (spec 008), and surfaces via localized Next.js pages (EN/ES/IT) with queued email confirmations.

## Technical Context

**Language/Version**: TypeScript 5.x (Next.js 16 frontend), PHP 8.x (Laravel backend)
**Primary Dependencies**: Next.js 16 (App Router), Laravel (API-only), PostgreSQL, Redis, Laravel Sanctum (auth)
**Storage**: PostgreSQL (bookings, audit log), Redis (cache/queue/sessions/rate limiting)
**Testing**: Pest/PHPUnit (backend), Jest + Playwright (frontend)
**Target Platform**: Web (SSR/ISR public pages, Cloudflare CDN)
**Project Type**: Web application (Next.js frontend + Laravel API backend)
**Performance Goals**: Booking confirmation < 5s, booking list < 1s, audit trail < 2s, zero overbookings
**Constraints**: WCAG 2.1 Level AA, strict rate limiting (10 req/min booking), idempotency via client UUID, tiered data retention (7yr financial / 90-day personal anonymization), atomic availability checks
**Scale/Scope**: 5,000–10,000 tours, 200–500 concurrent travelers, up to 500 bookings per partner

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Evidence |
|-----------|--------|----------|
| I. Marketplace-First | PASS | Bookings belong to travelers on partner-owned tours; platform mediates all transactions |
| II. Tours-Only Discipline | PASS | Booking flow is tours-only; no hotels/flights/transfers |
| III. Direct Booking Only | PASS | FR-004 mandates synchronous confirmed booking response; no request-to-book or waitlist |
| IV. Admin-Governed Publishing | PASS | FR-008 rejects bookings for non-published tours |
| V. Platform-Controlled Commerce | PASS | Booking captures price at confirmation; immutable financial ledger entries; payment events delegated to spec 008 |
| VI. Completed-Booking Review Integrity | PASS | FR-014 transitions to `completed` enable review eligibility; only completed bookings permit reviews |
| API-First | PASS | All data served via Laravel API consumed by Next.js; no server-rendered HTML from backend |
| Thin Controllers | PASS | Booking logic in Actions/Services (CreateBookingAction, CancelBookingAction, etc.) |
| No Direct DB Access from Controllers | PASS | Data access through BookingService and AuditService |
| Business Logic in Services/Actions | PASS | Pricing capture, availability checks, cancellation policy enforcement in dedicated action classes |
| Queueing & Async Work | PASS | Email confirmations dispatched to Redis queue (FR-026); idempotent jobs |
| SEO-First | PASS | Booking pages use SSR/ISR with localized metadata (per spec 006) |
| Security-First Mandate | PASS | FR-010 adds strict rate limiting; FR-006 requires auth; FR-017/FR-019 enforce ownership |
| WCAG Accessibility | PASS | Booking UI follows WCAG 2.1 AA (FR-009 locale-aware UI built per spec 006 a11y standards) |
| Audit Logging | PASS | FR-020–FR-022 mandate immutable audit log entries for all status transitions |
| Idempotent Financial Flows | PASS | FR-003 requires client-generated idempotency key; FR-023 atomic availability checks prevent overbooking |

**Gate Result**: ALL PASS — No violations. Proceed to Phase 0.

## Project Structure

### Documentation (this feature)

```text
specs/007-tour-booking/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output
└── tasks.md             # Phase 2 output (/speckit.tasks)
```

### Source Code (repository root)

```text
backend/
├── app/
│   ├── Domains/
│   │   └── Booking/
│   │       ├── Actions/
│   │       │   ├── CreateBookingAction.php
│   │       │   ├── CancelBookingAction.php
│   │       │   ├── GetTravelerBookingsAction.php
│   │       │   ├── GetPartnerBookingsAction.php
│   │       │   └── TransitionBookingStatusAction.php
│   │       ├── Services/
│   │       │   ├── BookingService.php
│   │       │   ├── AvailabilityService.php
│   │       │   └── AuditService.php
│   │       ├── Controllers/
│   │       │   ├── Public/
│   │       │   │   ├── BookingController.php
│   │       │   │   └── TravelerBookingController.php
│   │       │   └── Partner/
│   │       │       └── PartnerBookingController.php
│   │       ├── DTOs/
│   │       │   ├── CreateBookingDTO.php
│   │       │   └── BookingResponseDTO.php
│   │       ├── Models/
│   │       │   ├── Booking.php
│   │       │   └── BookingAuditLog.php
│   │       └── Middleware/
│   │           └── RateLimitBookingMiddleware.php
│   ├── Models/
│   │   └── Tour.php (adds bookings relationship, availability helper)
│   └── Http/
│       └── Middleware/
│           └── SetLocaleFromRequest.php (reused from spec 006)
├── database/
│   └── migrations/
│       ├── create_bookings_table.php
│       └── create_booking_audit_logs_table.php
├── routes/
│   └── api.php (adds booking routes)
└── tests/
    └── Feature/
        └── Booking/
            ├── CreateBookingTest.php
            ├── CancelBookingTest.php
            ├── TravelerBookingsTest.php
            ├── PartnerBookingsTest.php
            ├── AuditTrailTest.php
            ├── ConcurrencyTest.php
            └── RateLimitTest.php

frontend/
├── src/
│   ├── app/
│   │   └── [locale]/
│   │       ├── booking/
│   │       │   ├── page.tsx                   # Booking form
│   │       │   └── confirmation/
│   │       │       └── page.tsx               # Confirmation page
│   │       └── my-bookings/
│   │           ├── page.tsx                   # Traveler's booking list
│   │           └── [reference]/
│   │               └── page.tsx               # Single booking detail
│   ├── components/
│   │   ├── booking/
│   │   │   ├── BookingForm.tsx
│   │   │   ├── ParticipantSelector.tsx
│   │   │   ├── DateConfirmation.tsx
│   │   │   ├── PriceBreakdown.tsx
│   │   │   └── BookingConfirmation.tsx
│   │   └── my-bookings/
│   │       ├── BookingList.tsx
│   │       ├── BookingCard.tsx
│   │       └── CancelBookingButton.tsx
│   ├── lib/
│   │   └── api/
│   │       ├── bookings.ts
│   │       └── my-bookings.ts
│   └── i18n/
│       ├── en.json (adds booking keys)
│       ├── es.json (adds booking keys)
│       └── it.json (adds booking keys)
└── tests/
    ├── e2e/
    │   ├── booking.spec.ts
    │   └── my-bookings.spec.ts
    └── unit/
        ├── BookingForm.test.tsx
        └── BookingCard.test.tsx
```

**Structure Decision**: Web application structure. The `Booking` domain is a new modular domain in the backend, mirroring the `Search` domain pattern from spec 006. Frontend follows Next.js App Router with `[locale]` dynamic segment. Booking components are organized by surface (booking flow, my-bookings).

## Complexity Tracking

> No constitution violations. This section is intentionally empty.
