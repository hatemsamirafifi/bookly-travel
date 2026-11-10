# Seo

<!-- EGM contract -->

## Overview

Builds JSON-LD for tours and other public pages, using the same availability and price validation helpers as booking.

## Interface

- [StructuredData.tsx](./StructuredData.tsx#L19) declares `getSafeAbsoluteImageUrls`, `TouristTripSchema`, `OrganizationSchema`, `ItemListSchema`, `BlogPostingSchema`, `BreadcrumbListSchema`.

## Invariants

- Escape `<` before embedding JSON-LD in a script. Image URLs must resolve to HTTP or HTTPS, and blank, malformed or duplicate URLs are excluded. [StructuredData.tsx](./StructuredData.tsx#L13) [StructuredData.tsx](./StructuredData.tsx#L19)
- A tour offer requires a positive safe cents amount and supported currency. An in-stock offer also requires valid group bounds, a safe minimum estimate and at least one bookable future date. `validFrom` uses the explicit next date only if that date is offered and future. [StructuredData.tsx](./StructuredData.tsx#L47) [StructuredData.tsx](./StructuredData.tsx#L83)

## Side Effects

None recorded in this contract.

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
