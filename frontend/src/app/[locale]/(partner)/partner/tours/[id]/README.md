# [id]

<!-- EGM contract -->

## Overview

Tour-specific management routes scoped to a tour ID.

## Subdirectories & Boundaries

- [availability](./availability/README.md) — Manages recurring availability rules and one-off exceptions for a partner tour.
- [edit](./edit/README.md) — Partner tour editor for authored content, booking options, media, and review submission.
- [pricing](./pricing/README.md) — Manages participant-based pricing tiers for a partner tour.

## Interface

- Tour management has separate editors for tour content, availability, and pricing tiers. [Tour editor](./edit/page.tsx#L258) [Availability](./availability/page.tsx#L30) [Pricing](./pricing/page.tsx#L18)

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
