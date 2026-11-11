# Jobs

<!-- EGM contract -->

## Overview

Local source in `ExpirePendingBookingsJob.php`.

## Interface

- [ExpirePendingBookingsJob.php](./ExpirePendingBookingsJob.php#L17) declares `ExpirePendingBookingsJob`, `ExpirePendingBookingsJob::handle()`.

## Invariants

- `ExpirePendingBookingsJob::handle` selects and locks pending bookings whose expiration timestamp has elapsed. [Expiration selection](./ExpirePendingBookingsJob.php#L28).

## Side Effects

- `ExpirePendingBookingsJob::handle` expires and audits the selected bookings, then attempts to cancel associated payment intents after the transaction. [Expiration and cancellation](./ExpirePendingBookingsJob.php#L28).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
