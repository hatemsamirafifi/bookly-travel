# Controllers

<!-- EGM contract -->

## Overview

HTTP entry points for incoming Stripe events, deterministic local payment confirmation, partner financial summaries, and admin ledger queries.

## Subdirectories & Boundaries

- [Admin](./Admin/README.md) — Public declarations include `FinancialLedgerController`.
- [Partner](./Partner/README.md) — Public declarations include `FinancialSummaryController`.
- [Public](./Public/README.md) — Public declarations include `DeterministicPaymentController`, `StripeWebhookController`.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
