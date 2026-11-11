# Api

<!-- EGM contract -->

## Overview

Next.js handlers under the `/api` path. The local auth handler manages browser session cookies.

## Subdirectories & Boundaries

- [auth](./auth/README.md) — Browser session operations grouped under `/api/auth`.

## Interface

- Source entry points. [Session handler](./auth/session/route.ts#L24)

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
