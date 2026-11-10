# Hooks

<!-- EGM contract -->

## Overview

Shared hooks for authentication, checkout, search navigation, focus management, tour queries, and partner notifications.

## Interface

- [useAuth.tsx](./useAuth.tsx#L24) declares `AuthProvider`, `useAuth`.
- [useCheckout.ts](./useCheckout.ts#L8) declares `useCheckout`.
- [useFilters.ts](./useFilters.ts#L6) declares `FilterState`, `useFilters`.
- [useFocusTrap.ts](./useFocusTrap.ts#L3) declares `useFocusTrap`.
- [usePartnerRealtime.ts](./usePartnerRealtime.ts#L7) declares `usePartnerRealtime`.
- [useTour.ts](./useTour.ts#L6) declares `useTour`.
- [useTours.ts](./useTours.ts#L7) declares `useTours`.

## Invariants

- `useAuth` requires `AuthProvider`. The provider uses global `isLoading` for session restoration and preserves the stored token on recognized abort or network errors. Other restoration failures clear auth and redirect paths matching the protected prefixes to locale login. [useAuth.tsx](./useAuth.tsx#L36) [useAuth.tsx](./useAuth.tsx#L105) [useAuth.tsx](./useAuth.tsx#L148)
- Search filters derive from the current URL. Updates keep the listing pathname and clear pagination when changing filters. `clearAll` preserves the current search term. [useFilters.ts](./useFilters.ts#L18) [useFilters.ts](./useFilters.ts#L63) [useFilters.ts](./useFilters.ts#L104)
- `useTour` keys by slug and locale with five minutes of freshness. `useTours` keys by the supplied search parameters with 30 seconds of freshness. [useTour.ts](./useTour.ts#L6) [useTours.ts](./useTours.ts#L7)

## Side Effects

- Auth restoration and logout interact with the backend and browser token storage, while a `storage` listener synchronizes token changes across tabs. Login and registration trigger guest wishlist sync. [useAuth.tsx](./useAuth.tsx#L36) [useAuth.tsx](./useAuth.tsx#L81) [useAuth.tsx](./useAuth.tsx#L113)
- `useCheckout` writes or resets checkout state and forwards the caller’s idempotency key to booking creation. [useCheckout.ts](./useCheckout.ts#L8)
- `usePartnerRealtime` polls unread notifications every 60 seconds while visible, refreshes when the page becomes visible, and removes timers and the event listener on unmount. `useFocusTrap` focuses the first matching element, wraps Tab navigation at the boundaries, and cleans up its key listener. [usePartnerRealtime.ts](./usePartnerRealtime.ts#L12) [useFocusTrap.ts](./useFocusTrap.ts#L3)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
