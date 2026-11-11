# Blog

<!-- EGM contract -->

## Overview

Paginated travel article index, category archives, article details, and token-gated previews.

## Interface

- `/{locale}/blog` accepts optional `category` and `page` query values and requests 12 articles per page. Unparseable or below-one page values fall back to page one. [Index page](./page.tsx#L78)
- [page.tsx](./page.tsx#L22) declares `generateMetadata`, `default BlogIndexPage`.
- [\[slug\]](./%5Bslug%5D/README.md) — Renders a published article and generates article metadata and structured data.
- [category](./category/README.md) — Category archives for published travel articles.

## Invariants

- Canonical and locale alternate links preserve `category` and non-default `page`. The first unfiltered page highlights the first featured article or first available article, then removes that article from the grid. [Metadata](./page.tsx#L47) [Featured selection](./page.tsx#L121)
- Successful results emit `ItemList` structured data. Rate limits display an unavailable state with retry guidance. [Unavailable state](./page.tsx#L104) [Structured data](./page.tsx#L131)

## Side Effects

- Fetches published articles through `getBlogPosts` with `cache: 'no-store'`. [Index query](./page.tsx#L98) [Fetch policy](../../../../lib/api/blog.ts#L23)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
