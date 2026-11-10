# Tours

<!-- EGM contract -->

## Overview

Partner tour index and entry points for creating and managing tours.

## Interface

- `/{locale}/partner/tours` delegates the tour index to `TourList`. [Tour list](./page.tsx#L3)
- [page.tsx](./page.tsx#L3) declares `default ToursPage`.
- [\[id\]](./%5Bid%5D/README.md) — Tour-specific management routes scoped to a tour ID.
- [create](./create/README.md) — Partner tour creation route.

## Invariants

See the linked child contracts.

## Side Effects

None recorded in this contract.

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
