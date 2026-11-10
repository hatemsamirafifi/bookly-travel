# Components

<!-- EGM contract -->

## Overview

Groups the localized traveler and partner UI by feature, with shared controls and layout. The tour detail delegates booking selection to one controller, and checkout revalidates that selection before submitting.

## Subdirectories & Boundaries

- [admin](./admin/README.md) — Provides the administrator review moderation panel with filters, pagination and reason-based hide or reinstate actions.
- [auth](./auth/README.md) — Provides localized authentication forms and client-side guards for traveler and partner views.
- [blog](./blog/README.md) — Renders localized article cards, featured and detail views, related content, pagination and unavailable states.
- [booking](./booking/README.md) — Validates traveler checkout selections, creates bookings, handles price changes and payment, and displays the booking status returned by the server.
- [bookings](./bookings/README.md) — Displays booking details, payment and status history, and provides traveler booking filters and cancellation confirmation.
- [home](./home/README.md) — Composes home-page discovery sections from supplied tours, categories, destinations and articles, with section-level recovery UI.
- [layout](./layout/README.md) — Renders localized public navigation and footer, the account menu, mobile navigation and locale switching.
- [my-bookings](./my-bookings/README.md) — Renders the traveler booking list, status badges, detail links and a callback-driven cancellation prompt.
- [partner](./partner/README.md) — Handles partner registration, invitation completion and onboarding status, and groups the dashboard UI by feature.
- [profile](./profile/README.md) — Composes traveler identity, preference and password forms with profile API hooks.
- [reviews](./reviews/README.md) — Displays public tour reviews and aggregates, and provides forms and account views for submitting or editing traveler reviews.
- [search](./search/README.md) — Presents catalog results and reusable tour cards, with query-backed filters, sorting, pagination and responsive filter controls.
- [seo](./seo/README.md) — Builds JSON-LD for tours and other public pages, using the same availability and price validation helpers as booking.
- [shared](./shared/README.md) — Provides the localized cookie banner, render-error recovery, shared skeletons and a reusable wishlist heart.
- [tour](./tour/README.md) — Composes the public tour detail page and shares one option, date and participant selection across desktop and mobile booking surfaces.
- [traveler](./traveler/README.md) — Wraps traveler pages in a common main area with a supplied title, subtitle and child content.
- [ui](./ui/README.md) — Provides reusable form controls, overlays, navigation, feedback and layout primitives used by feature components.
- [wishlist](./wishlist/README.md) — Renders the traveler's saved-tour grid and an authenticated toggle with a server-confirmed baseline.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

- Tour detail and checkout both derive eligibility through `getBookingWithOptionView`. Keep option, date and party coordinates aligned across these surfaces. [TourBookingController.tsx](./tour/TourBookingController.tsx#L234) [BookingForm.tsx](./booking/BookingForm.tsx#L259)

## Side Effects

- Checkout creates and, on price rejection, cancels bookings. Shared error boundaries load the Sentry SDK only when reporting a render error. [BookingForm.tsx](./booking/BookingForm.tsx#L375) [BookingForm.tsx](./booking/BookingForm.tsx#L483) [ErrorBoundary.tsx](./shared/ErrorBoundary.tsx#L25)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
