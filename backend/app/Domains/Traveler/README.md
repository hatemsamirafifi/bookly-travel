# Traveler

<!-- EGM contract -->

## Overview

Traveler account profile operations and authenticated access to the traveler's wishlist and review history.

## Subdirectories & Boundaries

- [Actions](./Actions/README.md) — Public declarations include `ChangePasswordAction`, `GetProfileAction`, `UpdateProfileAction`.
- [Controllers](./Controllers/README.md) — HTTP controllers for traveler profile, wishlist, and review history.
- [DTOs](./DTOs/README.md) — Public declarations include `ProfileResponseDTO`.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
