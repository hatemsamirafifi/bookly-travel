# Pricing

<!-- EGM contract -->

## Overview

Manages participant-based pricing tiers for a partner tour.

## Interface

- `/{locale}/partner/tours/{id}/pricing` resolves the tour ID and edits each tier’s name, price, and participant limits. [Pricing page](./page.tsx#L18)
- [page.tsx](./page.tsx#L18) declares `default PricingPage`.

## Invariants

- The save payload sends a parsed price and maps an empty maximum participant field to null. [Save payload](./page.tsx#L59)

## Side Effects

- Loads and mutates pricing through bearer-authenticated API requests. POST creates a tier, PUT updates a tier, and confirmed DELETE removes a tier. Successful writes reload the list. [Load](./page.tsx#L34) [Save](./page.tsx#L59) [Delete](./page.tsx#L100)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
