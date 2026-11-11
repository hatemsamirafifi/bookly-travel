# Types

<!-- EGM contract -->

## Overview

Frontend data contracts for partner and traveler endpoints and the partner tour wizard’s form state.

## Interface

- [partner.ts](./partner.ts#L3) declares `TourStatus`, `BookingStatus`, `NotificationType`, `NotificationPriority`, `Partner`, `TourMedia`, `PricingTier`, `AvailabilityRule`.
- [partner.ts](./partner.ts#L71) also exposes `OwnedTourOption`, `BookingParticipant`, `PartnerBooking`, `PartnerReview`, `TourSummary`, `ReviewResponse`, `Notification`, `PartnerProfile`, `PartnerPayoutInfo`, `PartnerSettings`, `AnalyticsSummary`, `AnalyticsBookingsPoint`, `AnalyticsResponse`, `DateRangeFilter`, `PaginatedPartnerResponse`, `SignedUploadUrl`, `OnboardingStatus`, `BusinessAddress`, `PartnerRegistrationPayload`, `PartnerRegistrationResponse`, `PartnerOnboardingStatus`, `ResubmitPayload`, `InviteValidationResponse`, `InviteCompletionPayload`.
- [tour.ts](./tour.ts#L20) declares `Tour`, `TourDraft`, `TourMedia`, `PricingTier`, `AvailabilityRule`, `AvailabilityException`, `TourStatus`, `SourceLocale`.
- [tour.ts](./tour.ts#L34) also exposes `SOURCE_LOCALES`, `WizardStep`, `WIZARD_STEPS`, `WIZARD_STEP_LABELS`, `DurationUnit`, `DifficultyLevel`, `TourFormData`, `PricingTierFormInput`, `AvailabilityRuleFormInput`, `AvailabilityExceptionFormInput`, `BookingOptionFormInput`, `TourListFilters`, `TourListResponse`, `ImageUploadState`, `INITIAL_TOUR_FORM_DATA`.
- [traveler.ts](./traveler.ts#L1) declares `BookingStatus`, `MoneyValue`, `TravelerTourSummary`, `BookingOptionRecord`, `TravelerBooking`, `PaginatedTravelerResponse`, `TravelerProfile`, `WishlistItem`.
- [traveler.ts](./traveler.ts#L108) also exposes `TravelerReview`.

## Invariants

- Owned tour options use integer minor-unit prices and authored content. The wizard form keeps prices in major-unit strings, uses Sunday `0` through Saturday `6` for weekdays, and distinguishes the local row ID from the persistent `server_id`. [partner.ts](./partner.ts#L65) [tour.ts](./tour.ts#L123)
- Owned tour `source_locale` identifies authored content. `guide_languages` identifies spoken languages, and translation readiness applies to generated locales. [partner.ts](./partner.ts#L106) [partner.ts](./partner.ts#L116)
- Partner analytics returns `summary`, `bookings_over_time`, and `period` at the top level. Revenue is in minor units, and `conversion_rate_available` controls whether the rate has meaning. [partner.ts](./partner.ts#L305) [partner.ts](./partner.ts#L333)
- Traveler bookings support a canonical `pricing` block alongside compatibility price fields. An optional `option` carries the booked snapshot and can be absent or null. [traveler.ts](./traveler.ts#L23) [traveler.ts](./traveler.ts#L32)

## Side Effects

- None

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
