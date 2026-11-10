# [reference]

<!-- EGM contract -->

## Overview

Authenticated traveler view of one booking, including its itinerary, status, and receipt.

## Interface

- `/{locale}/my-bookings/{reference}` passes the route reference and locale to `BookingDetailClient`. [Detail page](./page.tsx#L18)
- [client.tsx](./client.tsx#L22) declares `default BookingDetailClient`.
- [page.tsx](./page.tsx#L9) declares `generateMetadata`, `default BookingDetailPage`.

## Invariants

- Reviews are shown for completed bookings and voucher download for confirmed bookings. Cancellation is hidden when cancelled, otherwise `can_cancel` controls eligibility with a confirmed-status fallback. [Review form](./client.tsx#L96) [Voucher action](./client.tsx#L118) [Cancellation action](./client.tsx#L134)

## Side Effects

- Loads the booking by reference with retry on error. User actions call the cancel mutation, request the PDF voucher, or submit rating and optional comment for the booking. [Booking query](./client.tsx#L22) [Retry](./client.tsx#L61) [Voucher download](./client.tsx#L29) [Cancellation](./client.tsx#L53) [Review submission](./client.tsx#L99)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
