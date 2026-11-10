# [slug]

<!-- EGM contract -->

## Overview

Shows a paginated archive for one blog category.

## Interface

- `/{locale}/blog/category/{slug}` accepts optional `page`. Unparseable or below-one page values fall back to one and the content request asks for 12 articles. [Archive query](./page.tsx#L69)
- [loading.tsx](./loading.tsx#L3) declares `default BlogCategoryLoading`.
- [page.tsx](./page.tsx#L21) declares `generateMetadata`, `default BlogCategoryPage`.

## Invariants

- Metadata preserves the encoded non-default page in canonical and language alternate links. The page emits breadcrumb structured data, calls `notFound()` for HTTP 404, and renders `BlogUnavailable` for other fetch errors. [Metadata](./page.tsx#L21) [Error handling](./page.tsx#L90) [Breadcrumbs](./page.tsx#L101)

## Side Effects

- Fetches category metadata and the requested list of posts through `getBlogCategory` using no-store requests. [Metadata query](./page.tsx#L35) [Posts query](./page.tsx#L84) [Fetch policy](../../../../../../lib/api/blog.ts#L47)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
