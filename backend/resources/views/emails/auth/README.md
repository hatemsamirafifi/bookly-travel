# Auth

<!-- EGM contract -->

## Overview

Localized auth notices for verification, password reset and account lockout.

## Interface

- `emails.auth.verify` and `emails.auth.password-reset` take a localized action URL and expiration minutes. [verify.blade.php](./verify.blade.php#L91), [password-reset.blade.php](./password-reset.blade.php#L91)
- `emails.auth.locked-out` renders the recipient and lock time. [locked-out.blade.php](./locked-out.blade.php#L19)
- Blade template [locked-out.blade.php](./locked-out.blade.php).
- Blade template [password-reset.blade.php](./password-reset.blade.php).
- Blade template [verify.blade.php](./verify.blade.php).

## Invariants

- All three templates look up their matching `emails` translation group with the supplied locale. [verify.blade.php](./verify.blade.php#L6), [password-reset.blade.php](./password-reset.blade.php#L6), [locked-out.blade.php](./locked-out.blade.php#L6)

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
