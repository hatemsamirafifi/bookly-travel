# Wishlist

<!-- EGM contract -->

## Overview

Guest wishlist persistence and per-tour saved-state controls that switch between local and account data.

## Interface

- [guestWishlist.ts](./guestWishlist.ts#L11) declares `GUEST_WISHLIST_STORAGE_KEY`, `GUEST_WISHLIST_EVENT`, `GuestWishlistStorage`, `getGuestWishlist`, `isGuestWishlisted`, `toggleGuestWishlist`, `clearGuestWishlist`, `mergeGuestWishlistIds`.
- [guestWishlist.ts](./guestWishlist.ts#L116) also exposes `syncGuestWishlistToAccount`.
- [useWishlist.ts](./useWishlist.ts#L16) declares `useWishlist`.

## Invariants

- Guest storage uses version `1`, numeric tour IDs, and an `updated_at` timestamp. Reads return an empty list outside the browser or for missing or invalid data. [guestWishlist.ts](./guestWishlist.ts#L11) [guestWishlist.ts](./guestWishlist.ts#L28) [guestWishlist.ts](./guestWishlist.ts#L72)
- Account synchronization clears guest storage after a successful merge and keeps it on request failure. [guestWishlist.ts](./guestWishlist.ts#L116)
- The per-tour hook subscribes to the guest custom event when signed out. When signed in, it ignores a late initial status response after a user toggle and rolls back a failed optimistic write. [useWishlist.ts](./useWishlist.ts#L27) [useWishlist.ts](./useWishlist.ts#L66)

## Side Effects

- Guest toggles and clearing write or remove `bookly_guest_wishlist` and dispatch `bookly:guest-wishlist-updated` after the storage operation succeeds. Signed-in actions call traveler wishlist endpoints. [guestWishlist.ts](./guestWishlist.ts#L72) [guestWishlist.ts](./guestWishlist.ts#L91) [useWishlist.ts](./useWishlist.ts#L76)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
