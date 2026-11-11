# Wishlist

<!-- EGM contract -->

## Overview

Renders the traveler's saved-tour grid and an authenticated toggle with a server-confirmed baseline.

## Interface

- [WishlistButton.tsx](./WishlistButton.tsx#L28) declares `default WishlistButton`.
- [WishlistGrid.tsx](./WishlistGrid.tsx#L15) declares `default WishlistGrid`.

## Invariants

- The toggle requires a completed status read before mutation. Tour or session changes invalidate pending work, and a rapid toggle back to the confirmed baseline sends no request. [WishlistButton.tsx](./WishlistButton.tsx#L43) [WishlistButton.tsx](./WishlistButton.tsx#L89) [WishlistButton.tsx](./WishlistButton.tsx#L125) [WishlistButton.tsx](./WishlistButton.tsx#L152)

## Side Effects

- Reads wishlist status, debounces add or remove requests and rolls back a rejected optimistic change to the confirmed state. The grid reads saved tours and supplies a removal action through `useWishlist`. [WishlistButton.tsx](./WishlistButton.tsx#L104) [WishlistButton.tsx](./WishlistButton.tsx#L152) [WishlistGrid.tsx](./WishlistGrid.tsx#L15) [WishlistGrid.tsx](./WishlistGrid.tsx#L51)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
