# DTOs

<!-- EGM contract -->

## Overview

Local source in `WishlistItemDTO.php`.

## Interface

- [WishlistItemDTO.php](./WishlistItemDTO.php#L7) declares `WishlistItemDTO`, `WishlistItemDTO::fromWishlist()`.

## Invariants

- `WishlistItemDTO::fromWishlist` marks a tour available only when published and with upcoming availability. When translations are loaded, it selects the requested locale, the authored source, or the first available translation. The slug is the fallback display name. [Translation order](./WishlistItemDTO.php#L9) and [availability](./WishlistItemDTO.php#L32).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
