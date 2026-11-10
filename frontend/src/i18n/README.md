# I18n

<!-- EGM contract -->

## Overview

Defines supported locales and loads the matching message catalog for Next.js requests.

## Interface

- [request.ts](./request.ts#L4) declares `default export`.
- [routing.ts](./routing.ts#L3) declares `locales`, `Locale`, `routing`.

## Invariants

- The locale set is `en`, `es`, and `it`, with `en` as the default and an always-present prefix on locale-managed routes. [routing.ts](./routing.ts#L3)
- Request configuration prefers an explicit locale to the route segment and falls back to English if the selected locale is missing or unsupported. [request.ts](./request.ts#L4)

## Side Effects

- Request configuration imports the validated locale’s JSON message bundle. [request.ts](./request.ts#L16)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
