# Terms

<!-- EGM contract -->

## Overview

Localized terms of service with CMS content and translated fallback copy.

## Interface

- `/{locale}/terms` requests the `terms` CMS slug for metadata and body. The page shows the supplied translation warning when present. [Metadata](./page.tsx#L7) [Terms page](./page.tsx#L25)
- [page.tsx](./page.tsx#L7) declares `generateMetadata`, `default TermsPage`.

## Invariants

- Renders sanitized CMS HTML when available and uses bundled `legal.terms` translations when the CMS lookup yields no page. [Body rendering](./page.tsx#L44)

## Side Effects

- Reads the public CMS endpoint with `cache: 'no-store'`. [CMS helper](../../../../lib/api/pages.ts#L13)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
