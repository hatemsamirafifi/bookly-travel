# Auth

<!-- EGM contract -->

## Overview

Shared helpers for client token access and safe same-locale navigation after sign-in.

## Interface

- [return-url.ts](./return-url.ts#L2) declares `safeAuthReturnUrl`.
- [token.ts](./token.ts#L3) declares `setTokenGetter`, `getAuthToken`.

## Invariants

- `safeAuthReturnUrl` accepts paths beginning with the current locale prefix, rejects literal backslashes, and verifies the normalized origin and path before preserving the query and fragment. Invalid candidates resolve to the locale root. [return-url.ts](./return-url.ts#L2)
- `getAuthToken` prefers a truthy registered token, falls back to browser storage while auth state hydrates, and returns `null` when neither source is available. [token.ts](./token.ts#L7)

## Side Effects

- `setTokenGetter` updates a module-level getter and `getAuthToken` can read `auth_token` from browser `localStorage`. [token.ts](./token.ts#L1)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
