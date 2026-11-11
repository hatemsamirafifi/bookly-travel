# Analytics

<!-- EGM contract -->

## Overview

Shows partner booking metrics and charts.

## Interface

- `/{locale}/partner/analytics` consumes the summary, chart data, loading status, error, and retry function from `usePartnerAnalytics`. [Analytics page](./page.tsx#L9)
- [page.tsx](./page.tsx#L9) declares `default AnalyticsPage`.

## Invariants

None recorded in this contract.

## Side Effects

None recorded in this contract.

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
