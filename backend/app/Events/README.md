# Events

<!-- EGM contract -->

## Overview

Shared booking email delivery failure event consumed by the operator alert listener.

## Interface

- [BookingEmailDeliveryFailed.php](./BookingEmailDeliveryFailed.php#L15) declares `BookingEmailDeliveryFailed`.

## Invariants

- `BookingEmailDeliveryFailed` carries a readonly booking and error message. [BookingEmailDeliveryFailed.php](./BookingEmailDeliveryFailed.php#L19)

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
