# Auth

<!-- EGM contract -->

## Overview

Traveler registration, login and account lockouts, email verification, password recovery, and guest booking linkage.

## Subdirectories & Boundaries

- [Actions](./Actions/README.md) — Public declarations include `AuthenticateTravelerAction`, `ChangePasswordAction`, `ConvertGuestToAccountAction`.
- [Events](./Events/README.md) — Event payloads for traveler lifecycle and authentication audit subscribers.
- [Listeners](./Listeners/README.md) — Public declarations include `LogAuthEvent`, `SendAccountLockedOutEmail`.
- [Services](./Services/README.md) — Public declarations include `AuthService`.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
