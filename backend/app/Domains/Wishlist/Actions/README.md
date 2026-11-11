# Actions

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Wishlist/Actions`.

## Interface

- [AddToWishlistAction.php](./AddToWishlistAction.php#L11) declares `AddToWishlistAction`, `AddToWishlistAction::execute()`.
- [GetWishlistAction.php](./GetWishlistAction.php#L9) declares `GetWishlistAction`, `GetWishlistAction::execute()`.
- [GetWishlistStatusAction.php](./GetWishlistStatusAction.php#L7) declares `GetWishlistStatusAction`, `GetWishlistStatusAction::execute()`.
- [RemoveFromWishlistAction.php](./RemoveFromWishlistAction.php#L8) declares `RemoveFromWishlistAction`, `RemoveFromWishlistAction::execute()`.

## Invariants

- `AddToWishlistAction::execute` requires a numeric ID for an existing tour and rejects an existing entry for the user and tour. [Add guards](./AddToWishlistAction.php#L13).

## Side Effects

- `AddToWishlistAction::execute` creates a user/tour entry. `RemoveFromWishlistAction::execute` deletes by user and tour, returning a not-found error when no row is deleted. [Insert](./AddToWishlistAction.php#L33) and [delete](./RemoveFromWishlistAction.php#L10).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
