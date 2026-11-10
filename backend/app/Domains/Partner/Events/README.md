# Events

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Partner/Events`.

## Interface

- [BookingCancelled.php](./BookingCancelled.php#L11) declares `BookingCancelled`, `BookingCancelled::broadcastOn()`, `BookingCancelled::broadcastWith()`, `BookingCancelled::broadcastAs()`.
- [DailySummaryReady.php](./DailySummaryReady.php#L11) declares `DailySummaryReady`, `DailySummaryReady::broadcastOn()`, `DailySummaryReady::broadcastWith()`, `DailySummaryReady::broadcastAs()`.
- [NewBooking.php](./NewBooking.php#L11) declares `NewBooking`, `NewBooking::broadcastOn()`, `NewBooking::broadcastWith()`, `NewBooking::broadcastAs()`.
- [PaymentStatusChanged.php](./PaymentStatusChanged.php#L11) declares `PaymentStatusChanged`, `PaymentStatusChanged::broadcastOn()`, `PaymentStatusChanged::broadcastWith()`, `PaymentStatusChanged::broadcastAs()`.
- [ReviewReceived.php](./ReviewReceived.php#L11) declares `ReviewReceived`, `ReviewReceived::broadcastOn()`, `ReviewReceived::broadcastWith()`, `ReviewReceived::broadcastAs()`.
- [TourApproved.php](./TourApproved.php#L11) declares `TourApproved`, `TourApproved::broadcastOn()`, `TourApproved::broadcastWith()`, `TourApproved::broadcastAs()`.
- [TourRejected.php](./TourRejected.php#L11) declares `TourRejected`, `TourRejected::broadcastOn()`, `TourRejected::broadcastWith()`, `TourRejected::broadcastAs()`.

## Invariants

None recorded in this contract.

## Side Effects

- `NewBooking` broadcasts the booking ID, reference, and tour title to a private `partner.{id}` channel. [Broadcast channel and payload](./NewBooking.php#L22).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
