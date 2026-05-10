# Research: Payment Processing (008)

**Date**: 2026-05-11  
**Feature**: [spec.md](./spec.md)

## R1: Stripe Payment Intents — Backend-First Pattern

**Decision**: Use Stripe Payment Intents API with server-side creation and client-side confirmation via Stripe Elements.

**Rationale**: Payment Intents is Stripe's recommended flow for PCI-compliant card payments. The backend creates the Payment Intent after reserving availability, returns the `client_secret` to the frontend, and Stripe Elements handles card tokenization entirely client-side (SAQ-A scope — no card data touches our server).

**Alternatives considered**:
- **Stripe Charges API**: Deprecated for new integrations. Payment Intents supersedes it with built-in SCA/3DS support.
- **Stripe Checkout (hosted)**: Redirects the traveler off-site, breaking the seamless booking UX. Suitable for simpler flows but not for marketplace-style embedded checkout.
- **Manual capture (`capture_method: manual`)**: Adds complexity (two-phase capture) without benefit — we can simply expire `pending_payment` bookings if payment fails.

## R2: Laravel Cashier vs. Direct Stripe SDK

**Decision**: Use `stripe/stripe-php` SDK directly, not Laravel Cashier.

**Rationale**: Laravel Cashier is designed for subscription billing (recurring charges, plan management, customer portal). Bookly uses one-time charges per booking, making Cashier's abstractions unnecessary overhead. The `stripe/stripe-php` SDK provides direct access to Payment Intents, Refunds, and Webhooks with full control.

**Alternatives considered**:
- **Laravel Cashier**: Opinionated subscription model; would require fighting the abstractions for one-time charges. Not suitable.

## R3: Webhook Signature Verification

**Decision**: Use `Stripe\Webhook::constructEvent()` with the `STRIPE_WEBHOOK_SECRET` env var.

**Rationale**: Stripe signs every webhook payload with an HMAC-SHA256 signature. The SDK provides `constructEvent()` which verifies the signature, rejects replays (via timestamp tolerance), and parses the event payload in one call. This is the standard approach.

**Alternatives considered**:
- **Manual HMAC verification**: Reinventing the wheel; the SDK handles edge cases (clock tolerance, multiple signatures).

## R4: Idempotency Strategy

**Decision**: Use Stripe's built-in `Idempotency-Key` header (from the booking's idempotency key) for Payment Intent creation, and use `stripe_event_id` uniqueness for webhook deduplication.

**Rationale**: Stripe's idempotency mechanism guarantees that retrying a Payment Intent creation with the same key returns the same result. For webhooks, storing the `event_id` and checking existence before processing ensures exactly-once semantics.

**Alternatives considered**:
- **Application-level locking**: Adds complexity without benefit; Stripe's idempotency is purpose-built for this.

## R5: Frontend Stripe.js Integration

**Decision**: Use `@stripe/stripe-js` and `@stripe/react-stripe-js` packages for React/Next.js integration.

**Rationale**: Official React bindings provide `<Elements>` provider and `<CardElement>` components that handle Stripe.js loading, PCI-compliant card input, and `confirmCardPayment()` flow. Works with Next.js App Router (`'use client'` components).

**Alternatives considered**:
- **Manual Stripe.js script tag**: Works but loses React lifecycle integration, error boundaries, and TypeScript types.

## R6: Two-Step Booking Flow (spec 007 Integration)

**Decision**: Refactor `CreateBookingAction` to create bookings in `pending_payment` status with a Stripe Payment Intent. A new `ConfirmBookingPaymentAction` processes the payment confirmation webhook and transitions to `confirmed`.

**Rationale**: The current `CreateBookingAction` creates bookings directly as `confirmed`. With payment integration, the flow becomes: (1) reserve availability + create Payment Intent → booking is `pending_payment`, (2) webhook confirms payment → booking transitions to `confirmed`. This prevents orphan bookings and ensures no charge without availability.

**Integration impact on spec 007**:
- `AvailabilityService.checkAndReserve()` must count `pending_payment` bookings in addition to `confirmed`/`completed`.
- `Booking` model gets two new status constants: `STATUS_PENDING_PAYMENT` and `STATUS_EXPIRED`.
- `CreateBookingAction` changes initial status from `confirmed` to `pending_payment` and stores `stripe_payment_intent_id`.
- A new `ExpirePendingBookingsJob` runs every 5 minutes to expire stale `pending_payment` bookings (>15 min old).

## R7: Financial Ledger Immutability Enforcement

**Decision**: Enforce immutability at three levels: (1) No `updated_at` on the model (`UPDATED_AT = null`), (2) No `update()` or `delete()` methods exposed, (3) Database-level trigger (optional, defense-in-depth) preventing UPDATE/DELETE on the `financial_ledger_entries` table.

**Rationale**: Constitutional requirement (Principle V). Multiple layers ensure that even if application code is buggy, the ledger remains tamper-proof.

## R8: Pending Payment Expiry

**Decision**: A scheduled job (`ExpirePendingBookingsJob`) runs every 5 minutes, queries `pending_payment` bookings older than 15 minutes, transitions them to `expired`, and releases availability.

**Rationale**: 15-minute hold window balances traveler UX (enough time to enter card details, handle 3DS challenges) against fairness to other travelers waiting for availability. 5-minute job frequency ensures max 20-minute effective hold.

**Alternatives considered**:
- **Event-driven (Stripe webhook `payment_intent.canceled`)**: Depends on Stripe events which may be delayed; not reliable for timeout enforcement.
- **Per-booking scheduled delay**: Laravel doesn't support per-record delayed jobs without a queue backend that supports arbitrary delays.
