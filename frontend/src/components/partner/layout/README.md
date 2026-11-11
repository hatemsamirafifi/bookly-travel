# Layout

<!-- EGM contract -->

## Overview

Provides partner navigation, notification controls, mobile drawer and loading skeletons.

## Interface

- [index.ts](./index.ts#L1) declares `PartnerSidebar`, `PartnerHeader`, `MobileDrawer`.
- [MobileDrawer.tsx](./MobileDrawer.tsx#L7) declares `MobileDrawer`.
- [NotificationBell.tsx](./NotificationBell.tsx#L10) declares `NotificationBell`.
- [PartnerHeader.tsx](./PartnerHeader.tsx#L38) declares `PartnerHeader`.
- [PartnerSidebar.tsx](./PartnerSidebar.tsx#L79) declares `PartnerSidebar`.
- [PartnerSkeleton.tsx](./PartnerSkeleton.tsx#L3) declares `PartnerAnalyticsSkeleton`, `PartnerTourListSkeleton`, `PartnerBookingListSkeleton`, `PartnerReviewListSkeleton`, `PartnerProfileSkeleton`.

## Invariants

- The mobile drawer uses the shared drawer and passes its close handler to the sidebar's navigation callback. [MobileDrawer.tsx](./MobileDrawer.tsx#L7)

## Side Effects

- The notification bell reads from `usePartnerRealtime`, traps focus while open, and refreshes notifications after marking one or all as read. [NotificationBell.tsx](./NotificationBell.tsx#L10) [NotificationBell.tsx](./NotificationBell.tsx#L18)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
