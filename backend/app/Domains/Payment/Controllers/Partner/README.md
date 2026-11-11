# Partner

<!-- EGM contract -->

## Overview

Local source in `FinancialSummaryController.php`.

## Interface

- [FinancialSummaryController.php](./FinancialSummaryController.php#L11) declares `FinancialSummaryController`, `FinancialSummaryController::index()`.

## Invariants

- `FinancialSummaryController` requires a partner and scopes payment aggregation by the partner's tours. [Authorization](./FinancialSummaryController.php#L27) and [ownership filter](./FinancialSummaryController.php#L82).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
