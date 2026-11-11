# [slug]

<!-- EGM contract -->

## Overview

Filtered and paginated tour listing for one destination.

## Interface

- `/{locale}/destinations/{slug}` accepts `page`, `sort`, `category`, `price_min`, `price_max`, and `duration`. The page passes the query values to `getDestinationTours`. [Route props](./page.tsx#L9) [Destination query](./page.tsx#L27)
- [page.tsx](./page.tsx#L14) declares `generateMetadata`, `default DestinationPage`.

## Invariants

- Missing destinations call `notFound()`. Other lookup errors are rethrown. The title uses the localized listing string with a title-cased slug. [Metadata](./page.tsx#L14) [Error and listing](./page.tsx#L45)

## Side Effects

- Reads destination tours with the 300-second revalidation requested by the API helper. [Fetch policy](../../../../../lib/api/destinations.ts#L17)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
