# [reference]

<!-- EGM contract -->

## Overview

Renders a public voucher verification card for a booking reference.

## Interface

- `/v/[reference]` takes a reference from async route params and chooses the display language from `Accept-Language`. [Route props](./page.tsx#L21) [Page](./page.tsx#L107)
- [page.tsx](./page.tsx#L51) declares `dynamic`, `revalidate`, `generateMetadata`, `default VerificationPage`.

## Invariants

- The page is force-dynamic with zero revalidation and emits `noindex, nofollow`. Language selection uses the highest positive weighted supported base language and defaults to English. [Route settings](./page.tsx#L48) [Metadata](./page.tsx#L94)
- The page distinguishes not-found, rate-limited, and other API error states. A successful card displays the reference, status, tour title and date, participant count, and timestamps when available. [Error handling](./page.tsx#L123) [Verification card](./page.tsx#L197)

## Side Effects

- Fetches `/api/public/v/{reference}` using the URI-encoded reference and negotiated locale. [Voucher lookup](./page.tsx#L116)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
