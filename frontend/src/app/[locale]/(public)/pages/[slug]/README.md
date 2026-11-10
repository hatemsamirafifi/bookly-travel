# [slug]

<!-- EGM contract -->

## Overview

Published CMS page rendering and metadata for one slug.

## Interface

- `/{locale}/pages/{slug}` resolves the localized page, uses its canonical URL when supplied, and displays any translation warning. [Metadata](./page.tsx#L10) [Page rendering](./page.tsx#L31)
- [page.tsx](./page.tsx#L10) declares `generateMetadata`, `default StaticPageDetailPage`.

## Invariants

- A missing or failed CMS read gives `noindex` metadata and calls `notFound()` in the page. The CMS body is passed through `sanitizeHtml` before insertion. [Metadata fallback](./page.tsx#L15) [Page fallback](./page.tsx#L36) [Sanitized content](./page.tsx#L54)

## Side Effects

- Reads the public CMS endpoint through `fetchPublicStaticPage` with `cache: 'no-store'`. [CMS helper](../../../../../lib/api/pages.ts#L13)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
