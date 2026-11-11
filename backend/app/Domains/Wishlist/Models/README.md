# Models

<!-- EGM contract -->

## Overview

Local source in `Wishlist.php`.

## Interface

- [Wishlist.php](./Wishlist.php#L10) declares `Wishlist`, `Wishlist::user()`, `Wishlist::tour()`.

## Invariants

- `Wishlist` relates each row to its `User` and `Tour` through `user()` and `tour()`. [Wishlist relations](./Wishlist.php#L17).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
