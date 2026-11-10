# Register

<!-- EGM contract -->

## Overview

Localized traveler registration page.

## Interface

- `/{locale}/auth/register` forwards the optional `returnUrl` query value to `RegisterForm`. [Registration page](./page.tsx#L31)
- [page.tsx](./page.tsx#L8) declares `generateMetadata`, `default RegisterPage`.

## Invariants

- Localized metadata uses the query-free registration URL as canonical. [Metadata](./page.tsx#L8)

## Side Effects

None recorded in this contract.

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
