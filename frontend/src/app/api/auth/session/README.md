# Session

<!-- EGM contract -->

## Overview

Reads, stores, and deletes the browser’s session cookies.

## Interface

- `GET /api/auth/session` returns `{ user, token }` reconstructed from the two cookies. Missing cookies or a JSON parse failure return HTTP 401 with both fields null. [GET](./route.ts#L7)
- `POST /api/auth/session` consumes a JSON body with `user` and `token`. `POST` and `DELETE` return `{ success: true }`. [POST](./route.ts#L24) [DELETE](./route.ts#L47)
- [route.ts](./route.ts#L7) declares `GET`, `POST`, `DELETE`.

## Invariants

- Both cookies are HTTP-only, same-site lax, scoped to `/`, and expire after seven days. `Secure` is enabled in production. [Cookie options](./route.ts#L28)
- The cookie endpoint trusts the submitted `user` and `token` values on `POST`. `GET` checks cookie presence and parses session JSON. These handlers perform no Laravel credential validation. [Cookie read](./route.ts#L7) · [Cookie write](./route.ts#L24).

## Side Effects

- `POST` writes `bookly_auth_token` and JSON-encoded `bookly_session`. `DELETE` clears both. [Cookie writes](./route.ts#L28) [Cookie deletion](./route.ts#L47)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
