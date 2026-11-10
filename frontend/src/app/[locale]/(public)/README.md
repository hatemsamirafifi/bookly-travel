# (public)

<!-- EGM contract -->

## Overview

Localized storefront layout and homepage for public tour discovery and booking.

## Interface

- `/{locale}` renders a discovery homepage using the localized catalog and up to three blog posts. Successful catalog rendering emits organization structured data. [Homepage](./page.tsx#L45) [Structured data](./page.tsx#L67)
- [layout.tsx](./layout.tsx#L4) declares `default PublicLayout`.
- [page.tsx](./page.tsx#L22) declares `generateMetadata`, `default HomePage`.
- [blog](./blog/README.md) — Paginated travel article index, category archives, article details, and token-gated previews.
- [booking](./booking/README.md) — Localized checkout entry point that hands tour selection and payment flow to the client form.
- [categories](./categories/README.md) — Tour category index and category-specific catalog pages.
- [destinations](./destinations/README.md) — Destination index and destination-specific tour listings.
- [pages](./pages/README.md) — Public CMS content addressed by locale and page slug.
- [privacy](./privacy/README.md) — Localized privacy policy with CMS content and translated fallback copy.
- [search](./search/README.md) — Localized server-rendered tour search with filters, sorting, and pagination.
- [terms](./terms/README.md) — Localized terms of service with CMS content and translated fallback copy.
- [tours](./tours/README.md) — Tour detail routes for published catalog slugs.

## Invariants

- The layout places routed content in `main-content` between the localized header and footer. [Public layout](./layout.tsx#L4)
- Catalog failure keeps the hero visible with recovery UI. Blog fetch failure supplies an empty list. Metadata uses localized fallback copy when homepage data is unavailable. [Metadata fallback](./page.tsx#L22) [Homepage recovery](./page.tsx#L49)

## Side Effects

- Starts the catalog and blog requests together with `Promise.allSettled`. Homepage data requests 300-second revalidation and blog reads use no-store. [Homepage requests](./page.tsx#L49) [Catalog fetch policy](../../../lib/api/homepage.ts#L4) [Blog fetch policy](../../../lib/api/blog.ts#L23)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
