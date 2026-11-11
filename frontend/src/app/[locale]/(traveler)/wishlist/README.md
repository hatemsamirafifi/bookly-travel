# Wishlist

<!-- EGM contract -->

## Overview

Localized wishlist page for a signed-in user.

## Interface

- `/{locale}/wishlist` passes the locale to `WishlistGrid` and translates the page copy and metadata. [Metadata](./page.tsx#L9) [Wishlist page](./page.tsx#L18)
- [page.tsx](./page.tsx#L9) declares `generateMetadata`, `default WishlistPage`.

## Invariants

None recorded in this contract.

## Side Effects

None recorded in this contract.

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
