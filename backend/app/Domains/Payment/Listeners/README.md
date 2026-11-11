# Listeners

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Payment/Listeners`.

## Interface

- [ConfirmBookingOnPayment.php](./ConfirmBookingOnPayment.php#L12) declares `ConfirmBookingOnPayment`, `ConfirmBookingOnPayment::handle()`.
- [ExpireBookingOnPaymentFailure.php](./ExpireBookingOnPaymentFailure.php#L10) declares `ExpireBookingOnPaymentFailure`, `ExpireBookingOnPaymentFailure::handle()`.
- [NotifyAdminOnPaymentFailure.php](./NotifyAdminOnPaymentFailure.php#L9) declares `NotifyAdminOnPaymentFailure`, `NotifyAdminOnPaymentFailure::handle()`.

## Invariants

- `ConfirmBookingOnPayment::handle` and `ExpireBookingOnPaymentFailure::handle` lock the booking and require `pending_payment` before changing its status. Confirmation additionally requires `LedgerService::recordCharge` to report a new entry. [Confirmation](./ConfirmBookingOnPayment.php#L24) and [expiration](./ExpireBookingOnPaymentFailure.php#L20).

## Side Effects

- `ConfirmBookingOnPayment::handle` records the charge, confirms the booking, records its confirmation timestamp and audit event, and dispatches the confirmation email job. [Confirmation effects](./ConfirmBookingOnPayment.php#L35).
- `ExpireBookingOnPaymentFailure::handle` expires the booking and audits the transition as a system action with reason `payment_failed`. [Failure effects](./ExpireBookingOnPaymentFailure.php#L32).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
