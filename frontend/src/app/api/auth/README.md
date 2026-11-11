# Auth

<!-- EGM contract -->

## Overview

Browser session operations grouped under `/api/auth`.

## Subdirectories & Boundaries

- [session](./session/README.md) — Reads, stores, and deletes the browser’s session cookies.

## Interface

- Source entry points. [Session handler](./session/route.ts#L7)

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
