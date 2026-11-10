# FinancialLedgerResource

<!-- EGM contract -->

## Overview

Page group for viewing financial ledger entries and downloading filtered CSV data.

## Subdirectories & Boundaries

- [Pages](./Pages/README.md) — Lists ledger entries, exposes a CSV export, and displays entry details.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

- `FinancialLedgerResource::canViewFinancials` accepts `view_finances` or `view_all_analytics`. Create, edit, and delete are disabled. [FinancialLedgerResource.php](../FinancialLedgerResource.php#L54) [FinancialLedgerResource.php](../FinancialLedgerResource.php#L71)

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
