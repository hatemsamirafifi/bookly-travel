# Create

<!-- EGM contract -->

## Overview

Partner tour creation route.

## Interface

- `/{locale}/partner/tours/create` mounts `TourWizard` and sets localized tour metadata. [Metadata](./page.tsx#L9) [Tour wizard](./page.tsx#L18)
- [page.tsx](./page.tsx#L9) declares `generateMetadata`, `default CreateTourPage`.

## Invariants

None recorded in this contract.

## Side Effects

None recorded in this contract.

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
