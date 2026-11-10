# Public

<!-- EGM contract -->

## Overview

Public-facing authentication flows and authenticated password and personal access token management.

## Subdirectories & Boundaries

- [Account](./Account/README.md) — Lists and revokes personal access tokens and delegates password changes for the authenticated user.
- [Auth](./Auth/README.md) — JSON adapters for traveler registration, login, logout, guest identity and conversion, password reset, and verification.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

- `EmailVerificationController::verify` validates the URL signature and current email hash before marking a user verified. [EmailVerificationController.php](./Auth/EmailVerificationController.php#L22)

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
