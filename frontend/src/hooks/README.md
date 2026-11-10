# Hooks

<!-- EGM contract -->

## Overview

React Query adapters for traveler account screens and partner analytics.

## Interface

- [useBookingDetail.ts](./useBookingDetail.ts#L6) declares `useBookingDetail`.
- [useBookings.ts](./useBookings.ts#L14) declares `useBookings`.
- [useCancelBooking.ts](./useCancelBooking.ts#L6) declares `useCancelBooking`.
- [useChangePassword.ts](./useChangePassword.ts#L12) declares `useChangePassword`.
- [useMyReviews.ts](./useMyReviews.ts#L6) declares `useMyReviews`.
- [usePartnerAnalytics.ts](./usePartnerAnalytics.ts#L12) declares `BookingsChartData`, `UsePartnerAnalyticsResult`, `usePartnerAnalytics`.
- [useProfile.ts](./useProfile.ts#L7) declares `useProfile`.
- [useWishlist.ts](./useWishlist.ts#L10) declares `useWishlist`.

## Invariants

- Booking detail queries require a reference. The bookings query keys by locale, status, and page, and fetches the list and summary in parallel. [useBookingDetail.ts](./useBookingDetail.ts#L6) [useBookings.ts](./useBookings.ts#L14)
- Partner analytics keys by date range and tour ID, defaults malformed chart metrics to zero, and suppresses 403 errors in its presentation result. [usePartnerAnalytics.ts](./usePartnerAnalytics.ts#L22) [usePartnerAnalytics.ts](./usePartnerAnalytics.ts#L41)

## Side Effects

- Successful cancellation invalidates booking detail and list queries. Wishlist mutations invalidate the wishlist, and a profile update replaces the cached profile with the API result. [useCancelBooking.ts](./useCancelBooking.ts#L9) [useWishlist.ts](./useWishlist.ts#L23) [useProfile.ts](./useProfile.ts#L20)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
