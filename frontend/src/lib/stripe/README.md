# Stripe

<!-- EGM contract -->

## Overview

Provides a shared Stripe.js loader for checkout.

## Interface

- [stripe-client.ts](./stripe-client.ts#L5) declares `getStripe`.

## Invariants

- `getStripe` selects the explicit publishable key, then `NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY`, then an empty string. It caches one `loadStripe` promise for each selected key. [stripe-client.ts](./stripe-client.ts#L3)

## Side Effects

- The first lookup for a key invokes the Stripe.js loader and stores its promise in a module-level map. [stripe-client.ts](./stripe-client.ts#L5)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
