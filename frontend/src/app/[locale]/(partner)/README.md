# (partner)

<!-- EGM contract -->

## Overview

Localized partner portal shell with a sidebar, mobile drawer, and header.

## Interface

- [layout.tsx](./layout.tsx#L11) declares `default PartnerLayout`.
- [partner](./partner/README.md) — Partner dashboard and entry points for account status, analytics, bookings, tours, settings, and reviews.

## Invariants

- The client layout wraps the portal in `PartnerAuthGuard`. The guard only renders children for the `partner` role after session loading. [Layout](./layout.tsx#L24) [Partner guard](../../../components/auth/PartnerAuthGuard.tsx#L18)

## Side Effects

- Opening the mobile drawer records the focused menu control. Closing the drawer restores focus on the next animation frame. [Close handler](./layout.tsx#L19) [Open handler](./layout.tsx#L42)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
