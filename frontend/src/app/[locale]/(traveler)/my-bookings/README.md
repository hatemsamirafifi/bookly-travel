# My bookings

<!-- EGM contract -->

## Overview

Traveler booking list and entry point to individual bookings.

## Interface

- `/{locale}/my-bookings` renders `BookingList` with the route locale under a localized account page shell. [List page](./page.tsx#L20)
- [page.tsx](./page.tsx#L10) declares `generateMetadata`, `default MyBookingsPage`.
- [\[reference\]](./%5Breference%5D/README.md) — Authenticated traveler view of one booking, including its itinerary, status, and receipt.

## Invariants

See the linked child contracts.

## Side Effects

None recorded in this contract.

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
