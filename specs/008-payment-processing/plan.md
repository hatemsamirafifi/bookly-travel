# Implementation Plan: Payment Processing

**Branch**: `008-payment-processing` | **Date**: 2026-05-11 | **Spec**: [spec.md](./spec.md)  
**Input**: Feature specification from `/specs/008-payment-processing/spec.md`

## Summary

Deliver Stripe payment processing for Bookly — payment capture on booking confirmation, automatic refund on cancellation, webhook-driven payment lifecycle, immutable financial ledger, partner revenue visibility, and admin financial audit trail. The payment domain integrates with the existing booking domain (spec 007) by refactoring the booking flow into a two-step process: (1) reserve availability + create Stripe Payment Intent → `pending_payment`, (2) Stripe webhook confirms payment → `confirmed`. Frontend uses Stripe Elements embedded on the booking page for PCI-compliant card input (SAQ-A scope).

**Cross-spec dependency**: This feature modifies spec 007's `CreateBookingAction`, `AvailabilityService`, and `Booking` model. Spec 007 MUST be fully deployed before payment integration.

## Technical Context

**Language/Version**: TypeScript 5.x (Next.js 16 frontend), PHP 8.x (Laravel backend)  
**Primary Dependencies**: Next.js 16 (App Router), Laravel (API-only), PostgreSQL, Redis, `stripe/stripe-php` (backend), `@stripe/stripe-js` + `@stripe/react-stripe-js` (frontend)  
**CSS Framework**: Tailwind CSS (inherited from spec 006 — no deviation)  
**Storage**: PostgreSQL (payments, financial_ledger_entries, stripe_webhook_events), Redis (cache/queue)  
**Testing**: Pest/PHPUnit (backend), Jest (frontend)  
**Target Platform**: Web (SSR/ISR public pages, Cloudflare CDN)  
**Project Type**: Web application (Next.js frontend + Laravel API backend)  
**Performance Goals**: Payment capture < 5s, webhook processing < 2s, refund initiation < 5s, ledger query < 1s  
**Constraints**: PCI SAQ-A (card data never touches server), EUR-only, idempotent payments via Stripe idempotency keys, 15-min pending payment timeout, immutable financial ledger  
**Scale/Scope**: 5,000–10,000 tours, 500 bookings/day peak, 100 concurrent payment flows

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Evidence |
|-----------|--------|----------|
| I. Marketplace-First | PASS | Platform mediates all payments; partners never collect directly |
| II. Tours-Only Discipline | PASS | Payment flow is tours-only; no other verticals |
| III. Direct Booking Only | PASS | Instant booking with synchronous payment confirmation via webhook |
| IV. Admin-Governed Publishing | PASS | Only published tours can be booked/paid |
| V. Platform-Controlled Commerce | PASS | All payments through Stripe via platform; immutable financial ledger entries; every transaction produces append-only audit record |
| VI. Completed-Booking Review Integrity | PASS | Payment status does not affect review eligibility — only `completed` booking status matters |
| API-First | PASS | All payment data served via Laravel API consumed by Next.js |
| Thin Controllers | PASS | Payment logic in Actions/Services (CreatePaymentIntentAction, ProcessWebhookAction, etc.) |
| No Direct DB Access from Controllers | PASS | All data access through PaymentService, LedgerService |
| Business Logic in Services/Actions | PASS | Charge capture, refund orchestration, webhook processing in dedicated action classes |
| Queueing & Async Work | PASS | Webhook processing and expiry jobs dispatched to Redis queue |
| Security-First Mandate | PASS | Webhook signature verification, Stripe keys in env vars, no card data on server (SAQ-A) |
| Idempotent Financial Flows | PASS | Stripe idempotency keys for charges; webhook event_id deduplication for webhooks; duplicate refund prevention |
| Audit Logging | PASS | Every payment state change produces an immutable ledger entry |
| Secrets Handling | PASS | STRIPE_KEY, STRIPE_SECRET, STRIPE_WEBHOOK_SECRET all from env vars (FR-017) |

**Gate Result**: ALL PASS — No violations. Proceed.

## Project Structure

### Documentation (this feature)

```text
specs/008-payment-processing/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output
│   └── payment-api.md
└── tasks.md             # Phase 2 output (/speckit.tasks)
```

### Source Code (repository root)

```text
backend/
├── app/
│   ├── Domains/
│   │   ├── Booking/
│   │   │   ├── Actions/
│   │   │   │   ├── CreateBookingAction.php          # MODIFIED: creates pending_payment + Payment Intent
│   │   │   │   └── CancelBookingAction.php          # MODIFIED: triggers Stripe refund
│   │   │   ├── Models/
│   │   │   │   └── Booking.php                      # MODIFIED: new statuses, stripe columns
│   │   │   └── Services/
│   │   │       └── AvailabilityService.php           # MODIFIED: count pending_payment in capacity
│   │   └── Payment/
│   │       ├── Actions/
│   │       │   ├── CreatePaymentIntentAction.php     # NEW: creates Stripe Payment Intent
│   │       │   ├── ProcessRefundAction.php           # NEW: issues Stripe refund
│   │       │   └── ProcessStripeWebhookAction.php    # NEW: routes webhook events
│   │       ├── Jobs/
│   │       │   └── ExpirePendingBookingsJob.php      # NEW: scheduled every 5 min
│   │       ├── Services/
│   │       │   ├── StripeService.php                 # NEW: Stripe SDK wrapper
│   │       │   ├── PaymentService.php                # NEW: payment CRUD
│   │       │   └── LedgerService.php                 # NEW: immutable ledger writes
│   │       ├── Controllers/
│   │       │   ├── Public/
│   │       │   │   └── StripeWebhookController.php   # NEW: webhook endpoint
│   │       │   ├── Partner/
│   │       │   │   └── FinancialSummaryController.php # NEW: partner revenue API
│   │       │   └── Admin/
│   │       │       └── FinancialLedgerController.php  # NEW: admin ledger API
│   │       ├── Models/
│   │       │   ├── Payment.php                       # NEW
│   │       │   ├── FinancialLedgerEntry.php          # NEW (immutable)
│   │       │   └── StripeWebhookEvent.php            # NEW
│   │       ├── Events/
│   │       │   ├── PaymentSucceeded.php              # NEW
│   │       │   ├── PaymentFailed.php                 # NEW
│   │       │   └── RefundCompleted.php               # NEW
│   │       └── Listeners/
│   │           ├── ConfirmBookingOnPayment.php        # NEW: pending → confirmed
│   │           ├── ExpireBookingOnPaymentFailure.php  # NEW: pending → expired
│   │           └── NotifyAdminOnPaymentFailure.php    # NEW: reuses spec 007 alert channel
│   ├── config/
│   │   └── services.php                              # MODIFIED: add stripe config block
│   └── Providers/
│       └── EventServiceProvider.php                  # MODIFIED: register payment events
├── database/
│   └── migrations/
│       ├── 2026_05_11_100001_create_payments_table.php
│       ├── 2026_05_11_100002_create_financial_ledger_entries_table.php
│       ├── 2026_05_11_100003_create_stripe_webhook_events_table.php
│       └── 2026_05_11_100004_add_payment_columns_to_bookings_table.php
├── routes/
│   ├── api/public.php                                # MODIFIED: add webhook route
│   └── api/partner.php                               # MODIFIED: add financial-summary route
│   └── api/admin.php                                 # MODIFIED: add financial-ledger route
└── tests/
    └── Feature/
        └── Payment/
            ├── PaymentCaptureTest.php
            ├── RefundTest.php
            ├── WebhookTest.php
            ├── LedgerImmutabilityTest.php
            ├── PendingExpiryTest.php
            └── PartnerFinancialSummaryTest.php

frontend/
├── src/
│   ├── components/
│   │   └── booking/
│   │       ├── BookingForm.tsx                        # MODIFIED: add Stripe Elements
│   │       ├── StripePaymentForm.tsx                  # NEW: card input via Stripe Elements
│   │       └── PaymentStatus.tsx                      # NEW: payment receipt display
│   ├── lib/
│   │   ├── api/
│   │   │   └── bookings.ts                           # MODIFIED: handle client_secret response
│   │   └── stripe/
│   │       └── stripe-client.ts                      # NEW: Stripe.js initialization
│   └── i18n/
│       ├── en.json                                   # MODIFIED: add payment keys
│       ├── es.json                                   # MODIFIED: add payment keys
│       └── it.json                                   # MODIFIED: add payment keys
```

**Structure Decision**: Web application structure. The `Payment` domain is a new modular domain in the backend, following the `Booking` and `Search` domain patterns. It integrates with the `Booking` domain through events and direct service calls. Frontend extends the existing booking form with Stripe Elements.

## Complexity Tracking

> No constitution violations. This section is intentionally empty.
