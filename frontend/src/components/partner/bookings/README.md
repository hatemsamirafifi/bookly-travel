# Bookings

<!-- EGM contract -->

## Overview

Lists partner reservations and provides controls to mark them complete or request cancellation.

## Interface

- [BookingDetail.tsx](./BookingDetail.tsx#L14) declares `BookingDetail`.
- [BookingFilters.tsx](./BookingFilters.tsx#L9) declares `BookingFilterValues`, `BookingFilters`.
- [BookingList.tsx](./BookingList.tsx#L12) declares `BookingList`.
- [index.ts](./index.ts#L1) declares `BookingList`, `BookingDetail`, `BookingFilters`.

## Invariants

- The UI offers completion for confirmed bookings whose tour date has arrived, and cancellation requests for confirmed or cancellation-requested bookings. [BookingDetail.tsx](./BookingDetail.tsx#L19)

## Side Effects

- Reads bookings through a filter-keyed React Query cache. Completion updates the booking status, cancellation sends the reason from a browser prompt, and successful mutations invalidate `partner-bookings`. [BookingList.tsx](./BookingList.tsx#L17) [BookingDetail.tsx](./BookingDetail.tsx#L27) [BookingDetail.tsx](./BookingDetail.tsx#L46)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
