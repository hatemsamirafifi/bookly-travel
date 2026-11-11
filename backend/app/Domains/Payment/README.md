# Payment

<!-- EGM contract -->

## Overview

Payment gateways and Stripe Connect, payment intent and refund flows, Stripe webhook reconciliation, booking payment outcomes, and financial ledger access.

## Subdirectories & Boundaries

- [Actions](./Actions/README.md) — Public declarations include `ConfirmDeterministicPaymentAction`, `CreatePaymentIntentAction`, `ProcessRefundAction`.
- [Contracts](./Contracts/README.md) — Public declarations include `PaymentGateway`.
- [Controllers](./Controllers/README.md) — HTTP entry points for incoming Stripe events, deterministic local payment confirmation, partner financial summaries, and admin ledger queries.
- [Events](./Events/README.md) — Public declarations include `PaymentFailed`, `PaymentRefunded`, `PaymentSucceeded`.
- [Jobs](./Jobs/README.md) — Public declarations include `ExpirePendingBookingsJob`.
- [Listeners](./Listeners/README.md) — Public declarations include `ConfirmBookingOnPayment`, `ExpireBookingOnPaymentFailure`, `NotifyAdminOnPaymentFailure`.
- [Models](./Models/README.md) — Public declarations include `FinancialLedgerEntry`, `Payment`, `StripeWebhookEvent`.
- [Services](./Services/README.md) — Public declarations include `ApplicationFeeCalculator`, `DeterministicPaymentGateway`, `LedgerService`.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
