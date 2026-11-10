# AvailabilityResource

<!-- EGM contract -->

## Overview

Page group for inspecting tour availability.

## Subdirectories & Boundaries

- [Pages](./Pages/README.md) — Lists tours and displays the availability details of the selected tour.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

- `AvailabilityResource` denies create, edit, and delete. [AvailabilityResource.php](../AvailabilityResource.php#L60)

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
