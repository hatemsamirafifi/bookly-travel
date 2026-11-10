# Account

<!-- EGM contract -->

## Overview

Lists and revokes personal access tokens and delegates password changes for the authenticated user.

## Interface

- [ChangePasswordController.php](./ChangePasswordController.php#L10) declares `ChangePasswordController`, `ChangePasswordController::__invoke()`.
- [SessionController.php](./SessionController.php#L10) declares `SessionController`, `SessionController::index()`, `SessionController::destroy()`.

## Invariants

- `SessionController::index` reads tokens through the authenticated user's relationship and flags the current access token. `destroy` returns 404 if that relationship cannot resolve the supplied token ID. [SessionController.php](./SessionController.php#L16) [SessionController.php](./SessionController.php#L45)
- `ChangePasswordController::__invoke` passes the request user and both passwords to `ChangePasswordAction`, returning 422 with `invalid_credentials` on failure. [ChangePasswordController.php](./ChangePasswordController.php#L15)

## Side Effects

- `SessionController::destroy` deletes the matching personal access token. [SessionController.php](./SessionController.php#L57)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
