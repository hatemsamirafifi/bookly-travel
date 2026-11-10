# Services

<!-- EGM contract -->

## Overview

Local source in `AuthService.php`.

## Interface

- [AuthService.php](./AuthService.php#L8) declares `AuthService`, `AuthService::findOrCreateGuestIdentity()`, `AuthService::checkExistingAccount()`.

## Invariants

- `AuthService` trims and lowercases email before lookups. Guest reuse requires an identity with no conversion or anonymization marker. [Guest selection](./AuthService.php#L13) and [registered account lookup](./AuthService.php#L36).

## Side Effects

- `AuthService::findOrCreateGuestIdentity` creates a guest identity with the normalized email, name, and optional phone if no reusable row is found. [Guest creation](./AuthService.php#L26).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
