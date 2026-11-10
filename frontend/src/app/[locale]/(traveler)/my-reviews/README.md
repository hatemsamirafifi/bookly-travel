# My reviews

<!-- EGM contract -->

## Overview

Localized traveler review history.

## Interface

- `/{locale}/my-reviews` resolves localized metadata and mounts `MyReviewsList` with the route locale. [Metadata](./page.tsx#L9) [Review list](./page.tsx#L18)
- [page.tsx](./page.tsx#L9) declares `generateMetadata`, `default MyReviewsPage`.

## Invariants

None recorded in this contract.

## Side Effects

None recorded in this contract.

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
