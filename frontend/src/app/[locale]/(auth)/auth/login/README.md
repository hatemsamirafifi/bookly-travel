# Login

<!-- EGM contract -->

## Overview

Renders the localized sign-in form with optional session recovery context.

## Interface

- `/{locale}/auth/login` accepts `returnUrl` and `sessionExpired`. Repeated query keys use the first value. The form receives the expired-session flag when the value is `1`. [Query handling](./page.tsx#L31) [Form props](./page.tsx#L41)
- [page.tsx](./page.tsx#L8) declares `generateMetadata`, `default LoginPage`.

## Invariants

- Localized metadata uses the query-free login URL as canonical. [Metadata](./page.tsx#L8)

## Side Effects

None recorded in this contract.

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
