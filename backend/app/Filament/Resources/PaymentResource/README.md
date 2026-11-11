# PaymentResource

<!-- EGM contract -->

## Overview

Page group for inspecting payment transactions.

## Subdirectories & Boundaries

- [Pages](./Pages/README.md) — Lists payment records and presents a detail infolist for the selected payment.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

- `PaymentResource::canViewFinancials` accepts `view_finances` or `view_all_analytics`. Create, edit, and delete are disabled. [PaymentResource.php](../PaymentResource.php#L75) [PaymentResource.php](../PaymentResource.php#L92)

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
