# (auth)

<!-- EGM contract -->

## Overview

Localized layout for authentication and partner account entry routes.

## Interface

- [layout.tsx](./layout.tsx#L5) declares `default AuthLayout`.
- [auth](./auth/README.md) — Localized sign-in, account registration, password recovery, and email verification pages.
- [partner-invite](./partner-invite/README.md) — Token-addressed entry point for accepting a partner invitation.
- [partner-register](./partner-register/README.md) — Public partner application form under the locale prefix.

## Invariants

- The layout wraps its contents in guest-mode `AuthGuard`. The guard waits for session loading and sends authenticated users through the safe return-URL helper. [Layout](./layout.tsx#L5) [Auth guard](../../../components/auth/AuthGuard.tsx#L20)

## Side Effects

None recorded in this contract.

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
