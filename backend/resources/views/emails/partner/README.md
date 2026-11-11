# Partner

<!-- EGM contract -->

## Overview

Partner onboarding, status and booking emails. Four unqualified Blade views sit alongside locale-specific view groups.

## Interface

- `emails.partner.approved`, `emails.partner.rejected`, `emails.partner.new-booking` and `emails.partner.booking-cancelled` extend the shared email layout. [Approval](./approved.blade.php#L1), [Rejection](./rejected.blade.php#L1), [New booking](./new-booking.blade.php#L1), [Cancelled booking](./booking-cancelled.blade.php#L1)
- Blade template [approved.blade.php](./approved.blade.php).
- Blade template [booking-cancelled.blade.php](./booking-cancelled.blade.php).
- Blade template [new-booking.blade.php](./new-booking.blade.php).
- Blade template [rejected.blade.php](./rejected.blade.php).
- [application-received](./application-received/README.md) — Localized partner application receipt email.
- [approved](./approved/README.md) — Localized partner approval email.
- [booking-cancelled](./booking-cancelled/README.md) — Localized partner booking cancellation email.
- [invitation](./invitation/README.md) — Localized partner invitation email.
- [new-booking](./new-booking/README.md) — Localized notification to the partner about a new booking.
- [reinstated](./reinstated/README.md) — Localized partner reinstatement email.
- [rejected](./rejected/README.md) — Localized partner application rejection email.
- [suspended](./suspended/README.md) — Localized partner suspension email.

## Invariants

See the linked child contracts.

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
