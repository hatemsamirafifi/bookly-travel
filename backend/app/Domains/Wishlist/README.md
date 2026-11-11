# Wishlist

<!-- EGM contract -->

## Overview

Persistence and application actions for saved tours, with localized tour summaries and per-tour membership status.

## Subdirectories & Boundaries

- [Actions](./Actions/README.md) — Public declarations include `AddToWishlistAction`, `GetWishlistAction`, `GetWishlistStatusAction`.
- [DTOs](./DTOs/README.md) — Public declarations include `WishlistItemDTO`.
- [Models](./Models/README.md) — Public declarations include `Wishlist`.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
