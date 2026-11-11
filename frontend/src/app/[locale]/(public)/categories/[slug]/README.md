# [slug]

<!-- EGM contract -->

## Overview

Filtered and paginated tour listing for one category.

## Interface

- `/{locale}/categories/{slug}` accepts `page`, `sort`, `price_min`, `price_max`, and `duration`. The page passes the query values to `getCategoryTours`. [Route props](./page.tsx#L9) [Category query](./page.tsx#L27)
- [page.tsx](./page.tsx#L14) declares `generateMetadata`, `default CategoryPage`.

## Invariants

- Missing categories call `notFound()`. Other lookup errors are rethrown. The title uses the localized listing string with a title-cased slug. [Metadata](./page.tsx#L14) [Error and listing](./page.tsx#L44)

## Side Effects

- Reads category tours with the 300-second revalidation requested by the API helper. [Fetch policy](../../../../../lib/api/categories.ts#L17)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
