# Emails

<!-- EGM contract -->

## Overview

Shared transactional email layout and message-specific Blade view groups.

## Interface

- `emails.layout` yields `title` and `content` sections within the Bookly mail frame. [layout.blade.php](./layout.blade.php#L6), [layout.blade.php](./layout.blade.php#L32)
- Blade template [layout.blade.php](./layout.blade.php).
- [auth](./auth/README.md) — Localized auth notices for verification, password reset and account lockout.
- [booking](./booking/README.md) — Localized traveler email views for booking confirmation, cancellation and voucher delivery.
- [partner](./partner/README.md) — Partner onboarding, status and booking emails. Four unqualified Blade views sit alongside locale-specific view groups.

## Invariants

See the linked child contracts.

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
