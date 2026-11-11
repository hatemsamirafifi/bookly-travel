# Onboarding

<!-- EGM contract -->

## Overview

Shows the partner application state with a retry path.

## Interface

- `/{locale}/partner/onboarding` renders `OnboardingStatusBanner` from the returned status data. [Onboarding view](./page.tsx#L75)
- [page.tsx](./page.tsx#L11) declares `default PartnerOnboardingPage`.

## Invariants

- The page waits for auth and status loading. It presents a retryable error if the status request fails or yields no data. [Loading and error handling](./page.tsx#L48)

## Side Effects

- Fetches `getOnboardingStatus()` once authenticated, with a login redirect carrying this page’s return URL when unauthenticated. [Auth and status request](./page.tsx#L21)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
