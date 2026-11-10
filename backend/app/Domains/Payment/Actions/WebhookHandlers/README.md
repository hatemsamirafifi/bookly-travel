# WebhookHandlers

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Payment/Actions/WebhookHandlers`.

## Interface

- [HandleAccountUpdated.php](./HandleAccountUpdated.php#L11) declares `HandleAccountUpdated`, `HandleAccountUpdated::execute()`.
- [HandleDisputeClosed.php](./HandleDisputeClosed.php#L11) declares `HandleDisputeClosed`, `HandleDisputeClosed::execute()`.
- [HandleDisputeCreated.php](./HandleDisputeCreated.php#L11) declares `HandleDisputeCreated`, `HandleDisputeCreated::execute()`.
- [HandleInvoiceFinalized.php](./HandleInvoiceFinalized.php#L10) declares `HandleInvoiceFinalized`, `HandleInvoiceFinalized::execute()`.
- [HandleInvoicePaid.php](./HandleInvoicePaid.php#L13) declares `HandleInvoicePaid`, `HandleInvoicePaid::execute()`.
- [HandleInvoicePaymentFailed.php](./HandleInvoicePaymentFailed.php#L10) declares `HandleInvoicePaymentFailed`, `HandleInvoicePaymentFailed::execute()`.
- [HandlePaymentFailed.php](./HandlePaymentFailed.php#L11) declares `HandlePaymentFailed`, `HandlePaymentFailed::execute()`.
- [HandlePaymentSucceeded.php](./HandlePaymentSucceeded.php#L11) declares `HandlePaymentSucceeded`, `HandlePaymentSucceeded::execute()`.
- [HandleRefunded.php](./HandleRefunded.php#L12) declares `HandleRefunded`, `HandleRefunded::execute()`.

## Invariants

- `HandlePaymentSucceeded::execute` and `HandlePaymentFailed::execute` apply the webhook outcome only to pending payments. [Success guard](./HandlePaymentSucceeded.php#L23) and [failure guard](./HandlePaymentFailed.php#L22).

## Side Effects

- `HandleRefunded::execute` updates a succeeded charge to refunded and handles external refunds with the associated refund payment and ledger entry. [Refund reconciliation](./HandleRefunded.php#L18).
- `HandleDisputeClosed::execute` reconciles disputed payments and updates their booking status based on the outcome. [Dispute reconciliation](./HandleDisputeClosed.php#L13).
- `HandleInvoicePaid::execute` reconciles the booking and payment for the paid invoice. [Invoice reconciliation](./HandleInvoicePaid.php#L19).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
