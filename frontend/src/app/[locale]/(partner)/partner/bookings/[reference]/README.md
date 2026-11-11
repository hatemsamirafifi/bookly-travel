# [reference]

<!-- EGM contract -->

## Overview

Looks up the partner’s view of an individual booking.

## Interface

- `/{locale}/partner/bookings/{reference}` reads the route reference using `useParams`, taking the first entry for an array value. [Route reference](./page.tsx#L18)
- [page.tsx](./page.tsx#L18) declares `default PartnerBookingDetailPage`.

## Invariants

- The query is enabled for a nonempty reference and has a stale time of 30 seconds. [Booking query](./page.tsx#L25)

## Side Effects

- Calls `getBooking(reference)` and provides a client retry action on error. [Booking query](./page.tsx#L25) [Retry](./page.tsx#L55)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
