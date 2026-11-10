# [...notFound]

<!-- EGM contract -->

## Overview

Catch-all entry point for unmatched paths under a locale.

## Interface

- [page.tsx](./page.tsx#L7) declares `default LocaleCatchAll`.

## Invariants

- Calls `notFound()` unconditionally to use the localized boundary. Specific routes take precedence. [Catch-all](./page.tsx#L3) [Boundary](../not-found.tsx#L9)

## Side Effects

None recorded in this contract.

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
