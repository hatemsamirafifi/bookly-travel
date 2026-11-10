# Booking

<!-- EGM contract -->

## Overview

Localized traveler email views for booking confirmation, cancellation and voucher delivery.

## Subdirectories & Boundaries

- [cancelled](./cancelled/README.md) — Localized traveler cancellation email.
- [confirmed](./confirmed/README.md) — Localized traveler confirmation email.
- [voucher](./voucher/README.md) — Localized traveler email accompanying a booking voucher.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
