# App

<!-- EGM contract -->

## Overview

Frontend App Router entry points for localized pages, public voucher verification, and the browser session API.

## Interface

- [not-found.tsx](./not-found.tsx#L7) declares `default RootNotFound`.
- [robots.ts](./robots.ts#L9) declares `default robots`.
- [\[locale\]](./%5Blocale%5D/README.md) — Localized document shell and route groups for the storefront, account access, traveler account, and partner portal.
- [api](./api/README.md) — Next.js handlers under the `/api` path. The local auth handler manages browser session cookies.
- [v](./v/README.md) — Standalone layout for the public `/v/[reference]` voucher link.

## Invariants

- Locale-prefixed routes use `en`, `es`, or `it`, with `en` as the default. The proxy excludes `/v/*`, API paths, and the named metadata assets. [Locale routing](../i18n/routing.ts#L3) [Proxy matcher](../proxy.ts#L17)
- The root 404 uses plain markup and links to English home and search pages. [Root 404](./not-found.tsx#L7)
- The robots response allows `/en/`, `/es/`, and `/it/`, disallows `/api/`, and builds the sitemap URL from `NEXT_PUBLIC_SITE_URL` with a `https://bookly.com` fallback. [robots.ts](./robots.ts#L7)

## Side Effects

None recorded in this contract.

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
