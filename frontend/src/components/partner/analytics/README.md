# Analytics

<!-- EGM contract -->

## Overview

Formats supplied partner dashboard statistics and chart series, including bookings, revenue and conversion availability.

## Interface

- [AnalyticsSummary.tsx](./AnalyticsSummary.tsx#L20) declares `AnalyticsSummary`.
- [BookingsChart.tsx](./BookingsChart.tsx#L20) declares `BookingsChart`.
- Source file [index.ts](./index.ts).

## Invariants

- Treat `conversion_rate` as an already scaled percentage and display unavailable when `conversion_rate_available` is false. Upcoming bookings and review count cards require numeric values. [AnalyticsSummary.tsx](./AnalyticsSummary.tsx#L20)
- Chart revenue uses the EUR minor-unit formatter, with a distinct no-data state for an empty series. [BookingsChart.tsx](./BookingsChart.tsx#L20) [BookingsChart.tsx](./BookingsChart.tsx#L44)

## Side Effects

None recorded in this contract.

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
