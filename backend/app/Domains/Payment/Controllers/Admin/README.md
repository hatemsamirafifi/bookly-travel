# Admin

<!-- EGM contract -->

## Overview

Local source in `FinancialLedgerController.php`.

## Interface

- [FinancialLedgerController.php](./FinancialLedgerController.php#L10) declares `FinancialLedgerController`, `FinancialLedgerController::index()`.

## Invariants

- `FinancialLedgerController::index` applies the validated booking reference, entry type, and inclusive creation date filters to the ledger query when supplied. [Ledger query](./FinancialLedgerController.php#L16).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
