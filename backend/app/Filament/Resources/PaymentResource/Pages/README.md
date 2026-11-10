# Pages

<!-- EGM contract -->

## Overview

Lists payment records and presents a detail infolist for the selected payment.

## Interface

- [ListPayments.php](./ListPayments.php#L11) declares `ListPayments`.
- [ViewPayment.php](./ViewPayment.php#L14) declares `ViewPayment`, `ViewPayment::getTitle()`, `ViewPayment::infolist()`.

## Invariants

None recorded in this contract.

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
