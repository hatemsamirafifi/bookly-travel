# Reviews

<!-- EGM contract -->

## Overview

Partner reviews dashboard under the localized portal.

## Interface

- `/{locale}/partner/reviews` resolves localized metadata and renders `PartnerReviewsDashboard`. [Metadata](./page.tsx#L9) [Reviews page](./page.tsx#L18)
- [page.tsx](./page.tsx#L9) declares `generateMetadata`, `default ReviewsPage`.

## Invariants

None recorded in this contract.

## Side Effects

None recorded in this contract.

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
