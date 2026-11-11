# Categories

<!-- EGM contract -->

## Overview

Tour category index and category-specific catalog pages.

## Interface

- `/{locale}/categories` loads the localized category list and renders `CategoryGrid`. A failed fetch supplies an empty list. [Category index](./page.tsx#L18)
- [page.tsx](./page.tsx#L10) declares `generateMetadata`, `default CategoriesPage`.
- [\[slug\]](./%5Bslug%5D/README.md) — Filtered and paginated tour listing for one category.

## Invariants

See the linked child contracts.

## Side Effects

- Reads category data through `getCategories`, which requests a 300-second revalidation interval. [Fetch policy](../../../../lib/api/categories.ts#L9)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
