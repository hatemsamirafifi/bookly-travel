# Partner

<!-- EGM contract -->

## Overview

Local source in `PartnerBookingController.php`.

## Interface

- [PartnerBookingController.php](./PartnerBookingController.php#L12) declares `PartnerBookingController`, `PartnerBookingController::show()`, `PartnerBookingController::index()`, `PartnerBookingController::updateStatus()`.

## Invariants

- `PartnerBookingController` uses an owner-scoped query for booking detail and returns 404 when the scoped lookup fails. [Partner detail](./PartnerBookingController.php#L13).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
