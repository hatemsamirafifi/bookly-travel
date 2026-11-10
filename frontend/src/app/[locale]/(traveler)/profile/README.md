# Profile

<!-- EGM contract -->

## Overview

Localized traveler profile settings page.

## Interface

- `/{locale}/profile` resolves localized metadata and mounts `ProfileSettings` under `TravelerPageShell`. [Metadata](./page.tsx#L10) [Profile page](./page.tsx#L19)
- [page.tsx](./page.tsx#L10) declares `generateMetadata`, `default ProfilePage`.

## Invariants

None recorded in this contract.

## Side Effects

None recorded in this contract.

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
