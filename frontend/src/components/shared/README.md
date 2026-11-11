# Shared

<!-- EGM contract -->

## Overview

Provides the localized cookie banner, render-error recovery, shared skeletons and a reusable wishlist heart.

## Interface

- [CookieConsent.tsx](./CookieConsent.tsx#L6) declares `default CookieConsentBanner`.
- [ErrorBoundary.tsx](./ErrorBoundary.tsx#L15) declares `default ErrorBoundary`.
- [ErrorFallback.tsx](./ErrorFallback.tsx#L14) declares `default ErrorFallback`.
- [Skeleton.tsx](./Skeleton.tsx#L1) declares `SkeletonText`, `SkeletonCard`, `SkeletonTourCard`, `SkeletonTourGrid`, `SkeletonTourDetail`, `SkeletonCheckout`.
- [WishlistHeartButton.tsx](./WishlistHeartButton.tsx#L16) declares `default WishlistHeartButton`.

## Invariants

- The wishlist heart coalesces rapid clicks to a final intent. An intent that returns to the committed state produces no mutation, and scheduled work checks mount status and tour identity. [WishlistHeartButton.tsx](./WishlistHeartButton.tsx#L61)

## Side Effects

- The consent banner delegates accept and decline controls to `react-cookie-consent`, configured with `bookly_cookie_consent` and a 365-day expiry. [CookieConsent.tsx](./CookieConsent.tsx#L11)
- The error boundary lazily imports Sentry and reports the error and component stack after a render failure. The default fallback offers page reload. [ErrorBoundary.tsx](./ErrorBoundary.tsx#L25) [ErrorBoundary.tsx](./ErrorBoundary.tsx#L39)
- The wishlist heart stops the parent click action, schedules a debounced wishlist toggle and publishes a live announcement on completion. [WishlistHeartButton.tsx](./WishlistHeartButton.tsx#L61) [WishlistHeartButton.tsx](./WishlistHeartButton.tsx#L78)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
