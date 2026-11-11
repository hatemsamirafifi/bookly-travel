# My bookings

<!-- EGM contract -->

## Overview

Renders the traveler booking list, status badges, detail links and a callback-driven cancellation prompt.

## Interface

- [BookingCard.tsx](./BookingCard.tsx#L14) declares `default BookingCard`.
- [BookingList.tsx](./BookingList.tsx#L32) declares `default BookingList`.
- [BookingStatusBadge.tsx](./BookingStatusBadge.tsx#L15) declares `default BookingStatusBadge`.
- [CancelBookingButton.tsx](./CancelBookingButton.tsx#L14) declares `default CancelBookingButton`.

## Invariants

- When the URL status filter changes, the list resets pagination to page one. [BookingList.tsx](./BookingList.tsx#L34)
- The cancellation trigger depends on the caller's `canCancel` flag, and confirmation stays open with an error if its callback rejects. [CancelBookingButton.tsx](./CancelBookingButton.tsx#L26) [CancelBookingButton.tsx](./CancelBookingButton.tsx#L82)

## Side Effects

- Reads paginated bookings through `useBookings` and invokes the supplied asynchronous cancellation handler only after confirmation. [BookingList.tsx](./BookingList.tsx#L48) [CancelBookingButton.tsx](./CancelBookingButton.tsx#L26)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
