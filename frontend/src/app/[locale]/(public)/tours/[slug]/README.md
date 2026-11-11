# [slug]

<!-- EGM contract -->

## Overview

Public tour details, booking-entry context, metadata, and route-specific fallback states.

## Interface

- `/{locale}/tours/{slug}` loads tour data for the locale and passes a UTC date string and locale to `TourDetail`. The route emits `TouristTrip` structured data. [Tour detail](./page.tsx#L79) [Successful render](./page.tsx#L102)
- [error.tsx](./error.tsx#L16) declares `default TourDetailError`.
- [not-found.tsx](./not-found.tsx#L15) declares `default TourNotFound`.
- [page.tsx](./page.tsx#L41) declares `generateMetadata`, `default TourDetailPage`.

## Invariants

- Metadata uses API canonical and hreflang fields and filters image URLs before putting them in Open Graph. [Tour metadata](./page.tsx#L41)
- Missing tours call `notFound()` in metadata and rendering. Archived tours show a localized withdrawn state with `noindex` metadata. Other render errors propagate to the route’s retry boundary. [Metadata errors](./page.tsx#L62) [Page errors](./page.tsx#L86) [Retry boundary](./error.tsx#L16)

## Side Effects

- Both metadata and rendering call `getTourDetail`. The helper shares results through React’s request-scoped cache and uses no-store network requests. [Fetch policy](../../../../../lib/api/tours.ts#L9)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
