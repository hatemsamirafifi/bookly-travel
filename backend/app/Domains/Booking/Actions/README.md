# Actions

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Booking/Actions`.

## Interface

- [CancelBookingAction.php](./CancelBookingAction.php#L18) declares `CancelBookingAction`, `CancelBookingAction::execute()`.
- [CreateBookingAction.php](./CreateBookingAction.php#L23) declares `CreateBookingAction`, `CreateBookingAction::execute()`.
- [GetPartnerBookingsAction.php](./GetPartnerBookingsAction.php#L11) declares `GetPartnerBookingsAction`, `GetPartnerBookingsAction::execute()`.
- [GetTravelerBookingsAction.php](./GetTravelerBookingsAction.php#L9) declares `GetTravelerBookingsAction`, `GetTravelerBookingsAction::execute()`.
- [TransitionBookingStatusAction.php](./TransitionBookingStatusAction.php#L16) declares `TransitionBookingStatusAction`, `TransitionBookingStatusAction::execute()`.
- [VerificationAction.php](./VerificationAction.php#L23) declares `VerificationAction`, `VerificationAction::execute()`.

## Invariants

- `CreateBookingAction::execute` locks the tour row before checking capacity and creating the pending booking. The idempotency key can only be reused for the same booking request. [Creation transaction](./CreateBookingAction.php#L54) and [request identity check](./CreateBookingAction.php#L310).
- `CancelBookingAction::execute` locks the booking and checks traveler ownership. It accepts only confirmed bookings and checks the cancellation cutoff. An already cancelled booking fails the confirmed-status check. [Cancellation guards](./CancelBookingAction.php#L27).
- `TransitionBookingStatusAction::execute` checks tour ownership, the requested target status, and departure timing before applying a partner transition. [Partner transition](./TransitionBookingStatusAction.php#L22).
- Booking creation requires an option when the tour has any options. Foreign, unknown, or retired options fail validation on `tour_option_id`. Reusing a key compares traveler, tour, option, date, and participant count. A difference produces HTTP 409. [Option resolution](./CreateBookingAction.php#L266) · [Replay comparison](./CreateBookingAction.php#L310).
- Partner completion and no-show transitions require a `confirmed` booking whose tour date precedes today. Only `completed` and `no_show` are accepted targets. [Transition guards](./TransitionBookingStatusAction.php#L36).

## Side Effects

- `CreateBookingAction::execute` creates the pending booking and obtains payment details after the booking transaction. Payment initialization failure can expire the pending booking. [Creation](./CreateBookingAction.php#L148), [payment initialization](./CreateBookingAction.php#L213), and [failure handling](./CreateBookingAction.php#L327).
- `CancelBookingAction::execute` stores the cancellation, invokes refund processing, and records a traveler audit entry. It schedules traveler mail with `DB::afterCommit`. The partner mailable is queued for after commit when a partner email is available. [Cancellation effects](./CancelBookingAction.php#L53) · [Partner mailable](../../../Mail/PartnerBookingCancelledMail.php#L25).
- The booking transaction reserves capacity under a tour lock and commits before payment initialization. A matching replay of a confirmed or completed booking returns it with no payment block. An expired replay returns HTTP 410. Failed initialization can expire a still-pending booking and returns HTTP 503. [Transaction](./CreateBookingAction.php#L54) · [Replay and gateway handling](./CreateBookingAction.php#L211).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
