# Events

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Payment/Events`.

## Interface

- [PaymentFailed.php](./PaymentFailed.php#L10) declares `PaymentFailed`.
- [PaymentRefunded.php](./PaymentRefunded.php#L10) declares `PaymentRefunded`.
- [PaymentSucceeded.php](./PaymentSucceeded.php#L10) declares `PaymentSucceeded`.
- [RefundCompleted.php](./RefundCompleted.php#L10) declares `RefundCompleted`.

## Invariants

- Each event carries the related `Payment` and `Booking` as readonly model properties. [Success payload](./PaymentSucceeded.php#L14), [failure payload](./PaymentFailed.php#L14), [refunded payload](./PaymentRefunded.php#L14), and [refund-completed payload](./RefundCompleted.php#L14).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
