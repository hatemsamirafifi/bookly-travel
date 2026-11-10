# Booking

<!-- EGM contract -->

## Overview

Validates traveler checkout selections, creates bookings, handles price changes and payment, and displays the booking status returned by the server.

## Interface

- `BookingForm` consumes locale and an optional UTC-day snapshot, and reads tour, date, party size and option from the checkout URL. `buildCheckoutAuthRecoveryUrl` builds a locale-specific login continuation that preserves those coordinates. [BookingForm.tsx](./BookingForm.tsx#L52) [BookingForm.tsx](./BookingForm.tsx#L112)
- [BookingConfirmation.tsx](./BookingConfirmation.tsx#L103) declares `default BookingConfirmation`.
- [BookingForm.tsx](./BookingForm.tsx#L52) declares `buildCheckoutAuthRecoveryUrl`, `default BookingForm`.
- [DateConfirmation.tsx](./DateConfirmation.tsx#L12) declares `default DateConfirmation`.
- [DeterministicPaymentForm.tsx](./DeterministicPaymentForm.tsx#L13) declares `default DeterministicPaymentForm`.
- [ParticipantSelector.tsx](./ParticipantSelector.tsx#L13) declares `default ParticipantSelector`.
- [PaymentStatus.tsx](./PaymentStatus.tsx#L13) declares `default PaymentStatus`.
- [PriceBreakdown.tsx](./PriceBreakdown.tsx#L9) declares `default PriceBreakdown`.
- [PriceChangeModal.tsx](./PriceChangeModal.tsx#L22) declares `default PriceChangeModal`.
- [StripePaymentForm.tsx](./StripePaymentForm.tsx#L15) declares `default StripePaymentForm`.

## Invariants

- Only an absent participant count defaults to the effective group minimum. An explicit malformed or out-of-bounds count remains invalid. The current tour response resolves option IDs and supplies option-specific bounds. [BookingForm.tsx](./BookingForm.tsx#L74) [BookingForm.tsx](./BookingForm.tsx#L205)
- Recompute eligibility with the current UTC day and validate the booking payload before creation. Keep the idempotency key stable across retries of the same selection, and guard duplicate in-flight submits synchronously. [BookingForm.tsx](./BookingForm.tsx#L159) [BookingForm.tsx](./BookingForm.tsx#L318)
- A changed price requires explicit acceptance before advancing to payment for the same reference. Declining cancels that reference and rotates the key on successful cancellation. [BookingForm.tsx](./BookingForm.tsx#L388) [BookingForm.tsx](./BookingForm.tsx#L471)
- Only `confirmed` and `completed` receive the success header. Pending, expired and cancelled-family statuses have separate presentation. [BookingConfirmation.tsx](./BookingConfirmation.tsx#L24)

## Side Effects

- Fetches fresh tour detail and submits create and cancel operations through the booking API. A create response with HTTP 401 clears `auth_token` and navigates to the safe login continuation. [BookingForm.tsx](./BookingForm.tsx#L205) [BookingForm.tsx](./BookingForm.tsx#L375) [BookingForm.tsx](./BookingForm.tsx#L431) [BookingForm.tsx](./BookingForm.tsx#L483)
- Stripe calls `retrievePaymentIntent` when a return secret is present and `confirmPayment` with `redirect: 'if_required'` and a booking-specific return URL. The deterministic form calls `confirmDeterministicPayment`. [StripePaymentForm.tsx](./StripePaymentForm.tsx#L27) [StripePaymentForm.tsx](./StripePaymentForm.tsx#L69) [DeterministicPaymentForm.tsx](./DeterministicPaymentForm.tsx#L26)
- Confirmation reads booking detail by reference and offers a retry for generic failures. HTTP 401 leads to login with a return URL, and HTTP 404 renders the missing-booking state. [BookingConfirmation.tsx](./BookingConfirmation.tsx#L114)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
