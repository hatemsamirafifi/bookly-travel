# Public

<!-- EGM contract -->

## Overview

Traveler-facing profile endpoints, wishlist list/add/remove/status/merge operations, and the traveler's review history.

## Interface

- [ProfileController.php](./ProfileController.php#L13) declares `ProfileController`, `ProfileController::show()`, `ProfileController::update()`, `ProfileController::changePassword()`.
- [ReviewController.php](./ReviewController.php#L9) declares `ReviewController`, `ReviewController::index()`.
- [WishlistController.php](./WishlistController.php#L16) declares `WishlistController`, `WishlistController::index()`, `WishlistController::store()`, `WishlistController::destroy()`, `WishlistController::status()`, `WishlistController::merge()`.

## Invariants

- `ProfileController` passes the request's authenticated user to the profile actions. [Profile entry points](./ProfileController.php#L15).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
