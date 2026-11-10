# Privacy

<!-- EGM contract -->

## Overview

Localized privacy policy with CMS content and translated fallback copy.

## Interface

- `/{locale}/privacy` requests the `privacy` CMS slug for metadata and body. The page shows the supplied translation warning when present. [Metadata](./page.tsx#L7) [Privacy page](./page.tsx#L25)
- [page.tsx](./page.tsx#L7) declares `generateMetadata`, `default PrivacyPage`.

## Invariants

- Renders sanitized CMS HTML when available and uses bundled `legal.privacy` translations when the CMS lookup yields no page. [Body rendering](./page.tsx#L44)

## Side Effects

- Reads the public CMS endpoint with `cache: 'no-store'`. [CMS helper](../../../../lib/api/pages.ts#L13)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
