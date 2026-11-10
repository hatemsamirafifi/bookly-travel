# Partner

<!-- EGM contract -->

## Overview

Partner dashboard and entry points for account status, analytics, bookings, tours, settings, and reviews.

## Interface

- `/{locale}/partner` renders the dashboard and loads account state alongside analytics. [Dashboard](./page.tsx#L95)
- [page.tsx](./page.tsx#L95) declares `default PartnerDashboardPage`.
- [analytics](./analytics/README.md) — Shows partner booking metrics and charts.
- [bookings](./bookings/README.md) — Partner booking list with a reference-specific detail route.
- [onboarding](./onboarding/README.md) — Shows the partner application state with a retry path.
- [profile](./profile/README.md) — Partner profile and payout settings route.
- [reviews](./reviews/README.md) — Partner reviews dashboard under the localized portal.
- [tours](./tours/README.md) — Partner tour index and entry points for creating and managing tours.

## Invariants

- Pending review or an unverified profile without approved status shows pending onboarding content. Other states use the dashboard’s analytics branch. [Status selection](./page.tsx#L121)

## Side Effects

- Starts profile and onboarding status requests in a client effect and ignores their late results after cleanup. Analytics come from `usePartnerAnalytics`. [Dashboard requests](./page.tsx#L95)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
