# Partner

<!-- EGM contract -->

## Overview

Handles partner registration, invitation completion and onboarding status, and groups the dashboard UI by feature.

## Interface

- [OnboardingStatusBanner.tsx](./OnboardingStatusBanner.tsx#L14) declares `OnboardingStatusBanner`.
- [PartnerInviteForm.tsx](./PartnerInviteForm.tsx#L16) declares `PartnerInviteForm`.
- [PartnerRegistrationForm.tsx](./PartnerRegistrationForm.tsx#L31) declares `PartnerRegistrationForm`.
- [ResubmissionForm.tsx](./ResubmissionForm.tsx#L14) declares `ResubmissionForm`.
- [analytics](./analytics/README.md) — Formats supplied partner dashboard statistics and chart series, including bookings, revenue and conversion availability.
- [bookings](./bookings/README.md) — Lists partner reservations and provides controls to mark them complete or request cancellation.
- [layout](./layout/README.md) — Provides partner navigation, notification controls, mobile drawer and loading skeletons.
- [profile](./profile/README.md) — Contains local-state controls for business information, payout fields and partner notification preferences.
- [reviews](./reviews/README.md) — Displays partner review pages and provides a form to create or edit the partner's response.
- [tours](./tours/README.md) — Provides partner tour authoring, draft and review submission, availability and pricing forms, media upload and content preview.

## Invariants

- Registration and invitation forms validate required business and contact fields before submission. The onboarding banner offers application resubmission for rejected accounts. [PartnerRegistrationForm.tsx](./PartnerRegistrationForm.tsx#L94) [PartnerInviteForm.tsx](./PartnerInviteForm.tsx#L45) [OnboardingStatusBanner.tsx](./OnboardingStatusBanner.tsx#L50)

## Side Effects

- Calls partner registration, invitation completion and resubmission APIs. Successful resubmission reports pending status and `can_create_tours: false` through the optional callback. [PartnerRegistrationForm.tsx](./PartnerRegistrationForm.tsx#L158) [PartnerInviteForm.tsx](./PartnerInviteForm.tsx#L117) [ResubmissionForm.tsx](./ResubmissionForm.tsx#L77)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
