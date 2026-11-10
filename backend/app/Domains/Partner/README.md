# Partner

<!-- EGM contract -->

## Overview

Partner onboarding and account operations, owner-scoped tour editing and review submission, authored tour content and translations, booking access, and partner notifications.

## Subdirectories & Boundaries

- [Actions](./Actions/README.md) — Public declarations include `CompletePartnerInvitationAction`, `ResubmitPartnerApplicationAction`.
- [Contracts](./Contracts/README.md) — Public declarations include `SourceAwareTourContentTranslator`, `TourContentTranslator`.
- [Controllers](./Controllers/README.md) — Partner HTTP endpoints for tours and drafts, bookings, reviews, availability, pricing, profile and onboarding, Stripe Connect, analytics, uploads, and notifications.
- [Events](./Events/README.md) — Public declarations include `BookingCancelled`, `DailySummaryReady`, `NewBooking`.
- [Jobs](./Jobs/README.md) — Public declarations include `GenerateTourTranslationJob`, `SendDailySummaryJob`.
- [Middleware](./Middleware/README.md) — Public declarations include `PartnerRoleMiddleware`.
- [Models](./Models/README.md) — Public declarations include `AvailabilityException`, `AvailabilityRule`, `Notification`.
- [Policies](./Policies/README.md) — Public declarations include `TourPolicy`.
- [Requests](./Requests/README.md) — Public declarations include `CompleteInvitationRequest`, `PartnerRegistrationRequest`, `PartnerResubmitRequest`.
- [Rules](./Rules/README.md) — Public declarations include `ValidIban`.
- [Services](./Services/README.md) — Public declarations include `AnalyticsService`, `BookingService`, `GeminiTourTranslator`.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
