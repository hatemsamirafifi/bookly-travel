# Bookings

<!-- EGM contract -->

## Overview

Partner booking list with a reference-specific detail route.

## Interface

- `/{locale}/partner/bookings` delegates the index to the partner `BookingList` component. [Booking list](./page.tsx#L3)
- [page.tsx](./page.tsx#L3) declares `default BookingsPage`.
- [\[reference\]](./%5Breference%5D/README.md) — Looks up the partner’s view of an individual booking.

## Invariants

See the linked child contracts.

## Side Effects

None recorded in this contract.

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
