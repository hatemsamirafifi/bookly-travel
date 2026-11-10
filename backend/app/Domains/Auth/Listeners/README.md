# Listeners

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Auth/Listeners`.

## Interface

- [LogAuthEvent.php](./LogAuthEvent.php#L8) declares `LogAuthEvent`, `LogAuthEvent::handle()`.
- [SendAccountLockedOutEmail.php](./SendAccountLockedOutEmail.php#L18) declares `SendAccountLockedOutEmail`, `SendAccountLockedOutEmail::handle()`.

## Invariants

- `SendAccountLockedOutEmail::handle` reloads the user and skips email when the stored email marker matches the current lockout expiration. [Duplicate email guard](./SendAccountLockedOutEmail.php#L33).

## Side Effects

- `SendAccountLockedOutEmail::handle` sends the lockout mail and sets `last_lockout_email_sent_at` to `locked_until`. [Mail and marker update](./SendAccountLockedOutEmail.php#L44).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
