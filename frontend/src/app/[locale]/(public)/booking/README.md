# Booking

<!-- EGM contract -->

## Overview

Localized checkout entry point that hands tour selection and payment flow to the client form.

## Interface

- `/{locale}/booking` derives its metadata title from the `tour` query value and renders `BookingForm` with the locale. The form reads `tour`, `date`, `participants`, and `option` from the browser query. [Metadata](./page.tsx#L11) [Checkout shell](./page.tsx#L33) [Form inputs](../../../../components/booking/BookingForm.tsx#L118)
- [page.tsx](./page.tsx#L11) declares `generateMetadata`, `default BookingPage`.
- [confirmation](./confirmation/README.md) — Displays the current status of the booking reached from checkout.

## Invariants

- The page wraps `BookingForm` in `Suspense` because the form calls `useSearchParams()`. [Suspense boundary](./page.tsx#L40)

## Side Effects

- The client form submits a booking with an idempotency key and handles payment through the selected gateway. Payment success routes to localized confirmation with the returned booking reference. [Booking submission](../../../../components/booking/BookingForm.tsx#L375) [Payment flow](../../../../components/booking/BookingForm.tsx#L413) [Payment success](../../../../components/booking/BookingForm.tsx#L457)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
