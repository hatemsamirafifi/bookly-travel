# Profile

<!-- EGM contract -->

## Overview

Contains local-state controls for business information, payout fields and partner notification preferences.

## Interface

- Source file [index.ts](./index.ts).
- [NotificationSettings.tsx](./NotificationSettings.tsx#L8) declares `NotificationSettings`.
- [PayoutForm.tsx](./PayoutForm.tsx#L8) declares `PayoutForm`.
- [ProfileForm.tsx](./ProfileForm.tsx#L9) declares `ProfileForm`.

## Invariants

- Business and payout controls initialize to empty fields. The notification toggles initialize to true. [ProfileForm.tsx](./ProfileForm.tsx#L9) [PayoutForm.tsx](./PayoutForm.tsx#L8) [NotificationSettings.tsx](./NotificationSettings.tsx#L8)

## Side Effects

- Input changes update component-local state. Each save button currently renders without a save handler. [ProfileForm.tsx](./ProfileForm.tsx#L51) [PayoutForm.tsx](./PayoutForm.tsx#L45) [NotificationSettings.tsx](./NotificationSettings.tsx#L33)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
