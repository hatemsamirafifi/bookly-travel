# Events

<!-- EGM contract -->

## Overview

Event payloads for traveler lifecycle and authentication audit subscribers.

## Interface

- [AccountLockedOut.php](./AccountLockedOut.php#L10) declares `AccountLockedOut`.
- [EmailVerified.php](./EmailVerified.php#L10) declares `EmailVerified`.
- [GuestConvertedToAccount.php](./GuestConvertedToAccount.php#L10) declares `GuestConvertedToAccount`.
- [LoginFailed.php](./LoginFailed.php#L10) declares `LoginFailed`.
- [PasswordChanged.php](./PasswordChanged.php#L10) declares `PasswordChanged`.
- [PasswordReset.php](./PasswordReset.php#L10) declares `PasswordReset`.
- [TravelerLoggedIn.php](./TravelerLoggedIn.php#L10) declares `TravelerLoggedIn`.
- [TravelerRegistered.php](./TravelerRegistered.php#L10) declares `TravelerRegistered`.

## Invariants

- `LoginFailed` carries the submitted email, optional associated user, and a flag indicating rejection due to account lockout. [Login failure payload](./LoginFailed.php#L19).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
