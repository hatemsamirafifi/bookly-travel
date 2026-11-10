# [locale]

<!-- EGM contract -->

## Overview

Localized document shell and route groups for the storefront, account access, traveler account, and partner portal.

## Interface

- [layout.tsx](./layout.tsx#L15) declares `metadata`, `generateStaticParams`, `default LocaleLayout`.
- [not-found.tsx](./not-found.tsx#L9) declares `default NotFound`.
- [(auth)](./%28auth%29/README.md) — Localized layout for authentication and partner account entry routes.
- [(partner)](./%28partner%29/README.md) — Localized partner portal shell with a sidebar, mobile drawer, and header.
- [(public)](./%28public%29/README.md) — Localized storefront layout and homepage for public tour discovery and booking.
- [(traveler)](./%28traveler%29/README.md) — Localized traveler account shell for bookings, reviews, profile, and wishlist.
- [\[...notFound\]](./%5B...notFound%5D/README.md) — Catch-all entry point for unmatched paths under a locale.

## Invariants

- Static params are derived from the configured locales. The layout calls `notFound()` for an unsupported locale before loading messages. [Locale layout](./layout.tsx#L26)
- The layout supplies translation messages, query and auth providers, an error boundary, and the cookie consent banner. [Document providers](./layout.tsx#L43)
- The localized 404 uses the negotiated locale when supported and otherwise uses the configured default. [Localized 404](./not-found.tsx#L9)

## Side Effects

None recorded in this contract.

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
