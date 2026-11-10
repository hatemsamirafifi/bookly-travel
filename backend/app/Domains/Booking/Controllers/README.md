# Controllers

<!-- EGM contract -->

## Overview

HTTP entry points for public booking and verification, partner booking operations, and booking audit queries.

## Subdirectories & Boundaries

- [Admin](./Admin/README.md) — Public declarations include `AuditController`.
- [Partner](./Partner/README.md) — Public declarations include `PartnerBookingController`.
- [Public](./Public/README.md) — Public declarations include `BookingController`, `TravelerBookingController`, `VerificationController`.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
