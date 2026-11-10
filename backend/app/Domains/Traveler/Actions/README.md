# Actions

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Traveler/Actions`.

## Interface

- [ChangePasswordAction.php](./ChangePasswordAction.php#L11) declares `ChangePasswordAction`, `ChangePasswordAction::execute()`.
- [GetProfileAction.php](./GetProfileAction.php#L8) declares `GetProfileAction`, `GetProfileAction::execute()`.
- [UpdateProfileAction.php](./UpdateProfileAction.php#L10) declares `UpdateProfileAction`, `UpdateProfileAction::execute()`.

## Invariants

- `ChangePasswordAction::execute` checks the supplied current password against the traveler's stored hash before updating it. [Current password guard](./ChangePasswordAction.php#L26).

## Side Effects

- `UpdateProfileAction::execute` persists profile changes after validating the supplied fields. [Profile update](./UpdateProfileAction.php#L12).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
