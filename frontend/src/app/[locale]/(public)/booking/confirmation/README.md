# Confirmation

<!-- EGM contract -->

## Overview

Displays the current status of the booking reached from checkout.

## Interface

- `/{locale}/booking/confirmation` forwards the `ref` query value and locale to `BookingConfirmation`. Missing `ref` becomes an empty string. [Confirmation page](./page.tsx#L23)
- [page.tsx](./page.tsx#L9) declares `generateMetadata`, `default BookingConfirmationPage`.

## Invariants

- The confirmation header reflects the actual booking status returned to the client. [Status header](../../../../../components/booking/BookingConfirmation.tsx#L28)

## Side Effects

- The client redirects HTTP 401 responses to localized sign-in with a return URL for the same booking. [Confirmation client](../../../../../components/booking/BookingConfirmation.tsx#L128)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
