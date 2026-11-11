# Destinations

<!-- EGM contract -->

## Overview

Destination index and destination-specific tour listings.

## Interface

- `/{locale}/destinations` loads the localized destination list and renders `DestinationShowcase`. A failed fetch supplies an empty list. [Destination index](./page.tsx#L18)
- [page.tsx](./page.tsx#L10) declares `generateMetadata`, `default DestinationsPage`.
- [\[slug\]](./%5Bslug%5D/README.md) — Filtered and paginated tour listing for one destination.

## Invariants

See the linked child contracts.

## Side Effects

- Reads destination data through `getDestinations`, which requests a 300-second revalidation interval. [Fetch policy](../../../../lib/api/destinations.ts#L9)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
