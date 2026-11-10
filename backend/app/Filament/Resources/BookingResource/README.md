# BookingResource

<!-- EGM contract -->

## Overview

Page group for inspecting bookings and downloading booking data.

## Subdirectories & Boundaries

- [Pages](./Pages/README.md) — Lists and displays booking records. The list adds a CSV export action.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

- `BookingResource::table` gates transition actions on policy and target-state eligibility. [BookingResource.php](../BookingResource.php#L134)

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
