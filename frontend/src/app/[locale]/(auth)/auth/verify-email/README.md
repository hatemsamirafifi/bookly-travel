# Verify email

<!-- EGM contract -->

## Overview

Localized email verification status page.

## Interface

- `/{locale}/auth/verify-email` forwards the first `status` query value to `VerifyEmailClient`. [Query handling](./page.tsx#L29) [Verification client](./page.tsx#L39)
- [page.tsx](./page.tsx#L7) declares `generateMetadata`, `default VerifyEmailPage`.

## Invariants

- The canonical verification URL is query-free. [Metadata](./page.tsx#L7)

## Side Effects

None recorded in this contract.

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
