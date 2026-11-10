# (traveler)

<!-- EGM contract -->

## Overview

Localized traveler account shell for bookings, reviews, profile, and wishlist.

## Interface

- [layout.tsx](./layout.tsx#L5) declares `default TravelerLayout`.
- [my-bookings](./my-bookings/README.md) — Traveler booking list and entry point to individual bookings.
- [my-reviews](./my-reviews/README.md) — Localized traveler review history.
- [profile](./profile/README.md) — Localized traveler profile settings page.
- [wishlist](./wishlist/README.md) — Localized wishlist page for a signed-in user.

## Invariants

- The layout uses the default auth-required `AuthGuard` and shared localized header and footer. After session loading, the guard redirects unauthenticated visitors to localized login with the current path as `returnUrl`. [Layout](./layout.tsx#L5) [Auth guard](../../../components/auth/AuthGuard.tsx#L14)

## Side Effects

None recorded in this contract.

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
