# Bookings

<!-- EGM contract -->

## Overview

Displays booking details, payment and status history, and provides traveler booking filters and cancellation confirmation.

## Interface

- [BookingDetailView.tsx](./BookingDetailView.tsx#L28) declares `default BookingDetailView`.
- [BookingFilters.tsx](./BookingFilters.tsx#L12) declares `default BookingFilters`.
- [BookingStatusTimeline.tsx](./BookingStatusTimeline.tsx#L12) declares `default BookingStatusTimeline`.
- [CancelBookingModal.tsx](./CancelBookingModal.tsx#L16) declares `default CancelBookingModal`.
- [PaymentReceipt.tsx](./PaymentReceipt.tsx#L23) declares `default PaymentReceipt`.

## Invariants

- The cancellation modal receives open, loading and error state from its caller. While loading, both actions are disabled. [CancelBookingModal.tsx](./CancelBookingModal.tsx#L5) [CancelBookingModal.tsx](./CancelBookingModal.tsx#L66)
- Booking status tabs offer all, confirmed, completed and cancelled. [BookingFilters.tsx](./BookingFilters.tsx#L6)

## Side Effects

- Status tabs update only the `status` query parameter and navigate to the localized booking list. The modal forwards explicit cancel or confirm actions to its caller. [BookingFilters.tsx](./BookingFilters.tsx#L18) [CancelBookingModal.tsx](./CancelBookingModal.tsx#L66)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
