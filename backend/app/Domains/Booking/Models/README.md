# Models

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Booking/Models`.

## Interface

- [Booking.php](./Booking.php#L18) declares `Booking`, `Booking::traveler()`, `Booking::guestIdentity()`, `Booking::tour()`, `Booking::tourOption()`, `Booking::bookingOption()`, `Booking::auditLogs()`, `Booking::governanceAuditLogs()`.
- [Booking.php](./Booking.php#L165) also exposes `Booking::payment()`, `Booking::payments()`, `Booking::generateReference()`, `Booking::startDateTime()`, `Booking::formatPrice()`.
- [BookingAuditLog.php](./BookingAuditLog.php#L8) declares `BookingAuditLog`, `BookingAuditLog::booking()`.

## Invariants

- `Booking::canTransitionTo` enumerates the allowed target statuses per source status. `Booking::canCancel` first requires confirmed status and checks the time remaining before departure. [Status transitions](./Booking.php#L243) and [cancellation timing](./Booking.php#L218).

- Lifecycle constants in [Booking.php](./Booking.php#L29) include `STATUS_PENDING_PAYMENT` → `pending_payment`, `STATUS_CONFIRMED` → `confirmed`, `STATUS_COMPLETED` → `completed`, `STATUS_CANCELLED` → `cancelled`, `STATUS_NO_SHOW` → `no_show`, `STATUS_EXPIRED` → `expired`, `STATUS_CANCELLATION_REQUESTED` → `cancellation_requested`.
- `Booking::canTransitionTo` allows `confirmed` to move to `completed`, `no_show`, `cancellation_requested`, or `cancelled`. `cancellation_requested` can move to `cancelled`, and `pending_payment` can move to `expired`. Other pairs return false. [Transition map](./Booking.php#L243).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
