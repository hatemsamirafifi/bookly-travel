# Reset password

<!-- EGM contract -->

## Overview

Localized password reset form populated from a recovery link.

## Interface

- `/{locale}/auth/reset-password` forwards the `token` and `email` query values to `ResetPasswordForm`. Repeated query keys use the first value. [Query handling](./page.tsx#L30) [Reset form](./page.tsx#L40)
- [page.tsx](./page.tsx#L8) declares `generateMetadata`, `default ResetPasswordPage`.

## Invariants

- The canonical reset URL omits token and email parameters. [Metadata](./page.tsx#L8)

## Side Effects

None recorded in this contract.

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
