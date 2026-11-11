# Models

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Payment/Models`.

## Interface

- [FinancialLedgerEntry.php](./FinancialLedgerEntry.php#L9) declares `FinancialLedgerEntry`, `FinancialLedgerEntry::booking()`, `FinancialLedgerEntry::payment()`.
- [Payment.php](./Payment.php#L9) declares `Payment`, `Payment::booking()`.
- [StripeWebhookEvent.php](./StripeWebhookEvent.php#L7) declares `StripeWebhookEvent`.

## Invariants

- `Payment::booted` rejects creation when `stripe_payment_intent_id` is empty. `Payment::casts` reads the amount as an integer and metadata as an array. [Creation guard](./Payment.php#L11) and [attribute casts](./Payment.php#L35).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
