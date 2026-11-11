# Src

<!-- EGM contract -->

## Overview

Next.js application source, including the request proxy, route modules, UI components, locale configuration, and shared client and server helpers.

## Interface

- Next.js invokes the default `next-intl` proxy with the shared routing configuration. [proxy.ts](./proxy.ts#L1)
- [proxy.ts](./proxy.ts#L4) declares `default export`, `config`.
- [app](./app/README.md) — Frontend App Router entry points for localized pages, public voucher verification, and the browser session API.
- [components](./components/README.md) — Groups the localized traveler and partner UI by feature, with shared controls and layout. The tour detail delegates booking selection to one controller, and checkout revalidates that selection before submitting.
- [hooks](./hooks/README.md) — React Query adapters for traveler account screens and partner analytics.
- [i18n](./i18n/README.md) — Defines supported locales and loads the matching message catalog for Next.js requests.
- [lib](./lib/README.md) — Shared React Query defaults, image placeholders, currency formatting, design tokens, and a rich-text sanitization pass.
- [types](./types/README.md) — Frontend data contracts for partner and traveler endpoints and the partner tour wizard’s form state.

## Invariants

- The proxy matcher excludes `/v/` so voucher QR links keep their root-level path. It also excludes `/storage/`, the `api` prefix, Next static and image endpoints, and paths containing dots. [proxy.ts](./proxy.ts#L6)

## Side Effects

- Matched requests pass through `next-intl` locale handling under the always-prefixed routing policy. [proxy.ts](./proxy.ts#L4) [i18n/routing.ts](./i18n/routing.ts#L6)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
