# Forgot password

<!-- EGM contract -->

## Overview

Renders the localized password recovery request form.

## Interface

- `/{locale}/auth/forgot-password` uses `AuthPageShell` and `ForgotPasswordForm`. [Recovery page](./page.tsx#L30)
- [page.tsx](./page.tsx#L8) declares `generateMetadata`, `default ForgotPasswordPage`.

## Invariants

- Metadata uses the locale-specific recovery URL as canonical. [Metadata](./page.tsx#L8)

## Side Effects

None recorded in this contract.

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
