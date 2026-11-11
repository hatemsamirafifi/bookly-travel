# [slug]

<!-- EGM contract -->

## Overview

Renders a published article and generates article metadata and structured data.

## Interface

- `/{locale}/blog/{slug}` resolves the article with the requested slug and locale. [Article page](./page.tsx#L55)
- [page.tsx](./page.tsx#L13) declares `generateMetadata`, `default BlogDetailPage`.
- [preview](./preview/README.md) — Token-gated rendering for unpublished article previews.

## Invariants

- Metadata consumes canonical and hreflang values from the API. Successful rendering includes `BlogPosting` and breadcrumb structured data. [Metadata](./page.tsx#L13) [Structured data](./page.tsx#L93)
- An upstream gone response renders the removed-article state, rate limiting shows retry guidance, and a missing article calls `notFound()`. [Article error handling](./page.tsx#L63)

## Side Effects

- Reads published article data in metadata and page rendering through `getBlogPost`, whose request uses `cache: 'no-store'`. [Metadata query](./page.tsx#L17) [Page query](./page.tsx#L60) [Fetch policy](../../../../../lib/api/blog.ts#L8)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
