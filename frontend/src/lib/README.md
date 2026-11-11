# Lib

<!-- EGM contract -->

## Overview

Shared React Query defaults, image placeholders, currency formatting, design tokens, and a rich-text sanitization pass.

## Interface

- [design-tokens.ts](./design-tokens.ts#L5) declares `designTokens`, `DesignToken`.
- [images.ts](./images.ts#L1) declares `DEFAULT_BLUR_DATA_URL`, `getBlurDataUrl`, `getImagePlaceholderProps`.
- [query-provider.tsx](./query-provider.tsx#L10) declares `default QueryProvider`.
- [sanitizeHtml.ts](./sanitizeHtml.ts#L72) declares `sanitizeHtml`.
- [utils.ts](./utils.ts#L4) declares `cn`, `formatExactCentsDecimal`, `toMinorUnits`, `formatCurrency`.
- [api](./api/README.md) — HTTP transport and typed endpoint adapters for public content, authentication, booking and checkout, traveler accounts, and partner operations.
- [auth](./auth/README.md) — Shared helpers for client token access and safe same-locale navigation after sign-in.
- [hooks](./hooks/README.md) — Shared hooks for authentication, checkout, search navigation, focus management, tour queries, and partner notifications.
- [reviews](./reviews/README.md) — HTTP adapters for public reviews, partner review listings, and admin moderation.
- [stores](./stores/README.md) — Zustand stores for checkout state and partner tour wizard drafts.
- [stripe](./stripe/README.md) — Provides a shared Stripe.js loader for checkout.
- [tours](./tours/README.md) — Pure derivations for tour detail presentation, booking selection, option-aware checkout, and localized validation messages.
- [validators](./validators/README.md) — Zod schemas for customer and partner forms, search sorting, and mapping backend validation errors to authoring controls.
- [wishlist](./wishlist/README.md) — Guest wishlist persistence and per-tour saved-state controls that switch between local and account data.

## Invariants

- `sanitizeHtml` is a secondary filter alongside the backend allow-list. It removes the configured dangerous elements and inline event handlers and filters `href` and `src` after limited entity decoding. [sanitizeHtml.ts](./sanitizeHtml.ts#L1) [sanitizeHtml.ts](./sanitizeHtml.ts#L38) [sanitizeHtml.ts](./sanitizeHtml.ts#L63) [sanitizeHtml.ts](./sanitizeHtml.ts#L72)
- `formatExactCentsDecimal` expects safe integer cents. `formatCurrency` uses exact integer arithmetic for safe EUR amounts, and `toMinorUnits` rounds the converted cent value. [utils.ts](./utils.ts#L17) [utils.ts](./utils.ts#L36) [utils.ts](./utils.ts#L60) [utils.ts](./utils.ts#L73)
- `QueryProvider` creates one client per mounted provider with 30-second query freshness, one retry, and window-focus refetching disabled. [query-provider.tsx](./query-provider.tsx#L10)
- Image placeholders prefer `blur_data_url`, then `cover_image_blur`, then the built-in gradient. The helper can run in the browser. [images.ts](./images.ts#L4)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
