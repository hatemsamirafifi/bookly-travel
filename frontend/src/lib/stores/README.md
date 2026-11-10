# Stores

<!-- EGM contract -->

## Overview

Zustand stores for checkout state and partner tour wizard drafts.

## Interface

- [checkout-store.ts](./checkout-store.ts#L48) declares `useCheckoutStore`.
- [tourWizard.ts](./tourWizard.ts#L153) declares `normalizeWizardState`, `mergePersistedWizardState`, `WizardState`, `WizardActions`, `useTourWizardStore`.

## Invariants

- Checkout generates an idempotency key on initialization and reset when `crypto` is available. The persisted state includes the key, booking selection, guest details, and special requests. [checkout-store.ts](./checkout-store.ts#L48)
- Loading a draft and rehydrating a persisted wizard use `normalizeWizardState`. Missing or invalid restored source locales fall back to `en`, invalid difficulty becomes `null`, and older list, itinerary, and option data is normalized. Rehydration clears the dirty and submitting flags. [tourWizard.ts](./tourWizard.ts#L153) [tourWizard.ts](./tourWizard.ts#L208) [tourWizard.ts](./tourWizard.ts#L504)
- `adoptSavedOptionIds` associates server IDs with submitted rows by position after skipping placeholders. Adopting IDs preserves the dirty flag. [tourWizard.ts](./tourWizard.ts#L455)

## Side Effects

- Checkout persists to `bookly-checkout` in `sessionStorage`. The tour wizard persists `currentStep` and `formData` to `partner-tour-wizard` in `localStorage`. [checkout-store.ts](./checkout-store.ts#L61) [tourWizard.ts](./tourWizard.ts#L512)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
