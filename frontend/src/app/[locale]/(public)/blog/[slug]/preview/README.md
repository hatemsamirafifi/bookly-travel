# Preview

<!-- EGM contract -->

## Overview

Token-gated rendering for unpublished article previews.

## Interface

- `/{locale}/blog/{slug}/preview` takes a `token` query value and renders `BlogDetail` in preview mode on success. [Query and lookup](./page.tsx#L29) [Preview rendering](./page.tsx#L51)
- [page.tsx](./page.tsx#L18) declares `dynamic`, `generateMetadata`, `default BlogPreviewPage`.

## Invariants

- Preview is force-dynamic and emits `noindex, nofollow`. A missing token or upstream HTTP 403 or 404 calls `notFound()`. Other failures render `BlogUnavailable`. [Route settings](./page.tsx#L18) [Error handling](./page.tsx#L36)

## Side Effects

- Fetches preview content with the encoded slug, token, and locale through `getBlogPostPreview`, which uses a no-store request. [Preview query](./page.tsx#L40) [Fetch policy](../../../../../../lib/api/blog.ts#L72)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
